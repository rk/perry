<?php

/**
 * Perry is a functional, semi-modular framework for PHP web applications based on
 * the Ruby framework Sinatra. Since Ruby had Sinatra, and Javascript has Sammy.js,
 * I figured it was high time to bring Perry to PHP.
 *
 * Perry uses REST verbs and static/regex routes to respond to various requests.
 * The URI matching is done first by static means, then by Regular Expressions.
 *
 * @author Robert Kosek <robert.kosek@thewickedflea.com>
 * @version 0.1
 * @copyright Robert Kosek, 5 February, 2011
 * @package Perry
 **/

/**
 * Define DocBlock
 **/


/**
 * This is the universal "error" page for Perry apps. Pass it a title and a message,
 * and the function will output it and halt operation.
 *
 * @author Robert Kosek
 * @copyright Robert Kosek, 5 February, 2011
 */
function perry_error($title, $message) {
  $content = <<<ERROR
<html>
  <head>
    <title>Perry has encountered an error!</title>
    <style type="text/css">
      html, body { min-height: 100%; }
      html {
        background: #cedce7; /* old browsers */
        background: -moz-linear-gradient(top, #cedce7 0%, #596a72 100%); /* firefox */
        background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#cedce7), color-stop(100%,#596a72)); /* webkit */
        filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#cedce7', endColorstr='#596a72',GradientType=0 ); /* ie */
      }
      body { margin: 0 auto; width: 700px; padding: 25px 50px; background: white; background: rgba(255,255,255,0.8); }
      h1 { font-family: sans-serif; }
      p { font-size: 1.2em; line-height: 1.4; }
      img { margin-left: 1em; padding: 4px; border: 1px solid gray; }
    </style>
  </head>
  <body>
    <img src="/~robert/perry/images/perry.jpeg" align="right">
    <h1>{$title}</h1>
    {$message}
  </body>
</html>
ERROR;
  die($content);
}

set_error_handler(function($severity, $message, $file, $line, $context) {
  global $perry;
  
  switch($severity) {
    case E_ERROR:
    case E_COMPILE_ERROR:
    case E_USER_ERROR:
      $title   = "Perry has encountered an ERROR";
      $message = <<<MESSAGE
<p>
  <strong>Error:</strong> {$message}
</p>
<p>
  <em>File:</em> {$file}<br>
  <em>Line:</em> {$line}
</p>
MESSAGE;
      perry_error($title, $message);
      break;
    case E_WARNING:
    case E_COMPILE_WARNING:
    case E_USER_WARNING:
      $perry->errors[] = array(
        'severity' => 'WARNING',
        'message'  => $message,
        'file'     => $file,
        'line'     => $line
      );
      break;
    case E_NOTICE:
    case E_USER_NOTICE:
      $perry->errors[] = array(
        'severity' => 'NOTICE',
        'message'  => $message,
        'file'     => $file,
        'line'     => $line
      );
      break;
  }
  
  return true;
});

class Perry {
  public $errors = array();
  
  // $routes[verb][pattern] = callback
  public $routes = array();
  
  private function registerRoute($verb, $pattern, $callback) {
    if(empty($this->routes[$verb])) {
      $this->routes[$verb] = array();
    }
    $this->routes[$verb][$pattern] = $callback;
  }
  
  public function get($pattern, $callback) {
    $this->registerRoute('get', $pattern, $callback);
    return $this;
  }

  public function put($pattern, $callback) {
    $this->registerRoute('put', $pattern, $callback);
    return $this;
  }

  public function post($pattern, $callback) {
    $this->registerRoute('post', $pattern, $callback);
    return $this;
  }

  public function delete($pattern, $callback) {
    $this->registerRoute('delete', $pattern, $callback);
    return $this;
  }
  
  private $response = array();
  
  public function handle($request) {
    $uri  = $request->uri;
    $verb = $request->method;
    
    if(isset($this->routes[$verb][$uri])) {
      $this->response = new Response($this->routes[$verb][$uri], array($request, $this));
      return $this;
    } else {
      foreach($this->routes[$verb] as $pattern => $func) {
        if(preg_match($pattern, $uri, $matches)) {
          $this->response = new Response($func, array($matches, $request, $this));
          return $this;
        }
      }
    }

    $this->response = new Response(array($this, 'not_found'), array($request, $this));
    return $this;
  }
    
  
  public function render() {
    echo $this->response->render();
  }
  
  public function redirect($to, $code=302) {
    switch($code) {
      case 301:
        $response = '301 Moved Permanently';
        break;
      case 302:
        $response = '302 Found';
        break;
      case 307:
        $response = '307 Temporary Redirect';
        break;
      default:
        perry_error('Invalid Redirect Code', "<p>The code {$code} is not a valid redirect code, or has not been registered with Perry.</p>");
    }
    header('HTTP/1.1 '.$response);
    header('Location: '.$to);
    exit();
  }
  
  public function not_found($request) {
    perry_error("404 Not Found", "<p>Perry is a great guy, but even he doesn't know what to do with a route like: <code>{$request->uri}</code></p>");
  }
}

class Response {
  private $callback = null;
  private $params   = null;
  
  public function __construct($callback, $params) {
    $this->callback = $callback;
    $this->params   = $params;
  }
  
  public function render() {
    global $perry;
    
    ob_start();

    $func = &$this->callback; // must be dereferenced before being called by call_user_func_array
    call_user_func_array($func, $this->params);

    $result = ob_get_contents();
    ob_end_clean();
    
    return $result;
  }
}

class Request {
  public $uri    = null;
	public $method = null;
	public $params = null;
	
	public static function getInstance() {
	  static $instance;
	  if(empty($instance)) {
	    $instance = new Request($_SERVER['PATH_INFO'] ?: '/');
	  }
    return $instance;
	}
	
	private function __construct($path) {
		$this->uri    = $path;
		$this->params = &$_POST;
		
		$this->method = 'get';
		if(empty($_POST) == false) {
			$this->method = 'post';
			
			if(isset($_POST['_method']) && ($_POST['_method'] == 'put' || $_POST['_method'] == 'delete')) {
				$this->method = $_POST['_method'];
			}
		}
	}
}

$perry = new Perry;