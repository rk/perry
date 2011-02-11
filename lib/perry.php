<?php

/**
 * Perry is a functional, semi-modular framework for PHP web applications based on
 * the Ruby framework Sinatra. Since Ruby had Sinatra, and Javascript has Sammy.js,
 * I figured it was high time to bring Perry to PHP.
 *
 * Perry uses REST verbs and static/regex routes to respond to various requests.
 * The URI matching is done first by static means, then by Regular Expressions.
 *
 * LICENSE:
 * Copyright (c) 2011, Robert Kosek
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 *   1.) Redistributions of source code must retain the above copyright notice, this list of
 *       conditions and the following disclaimer.
 *
 *   2.) Redistributions in binary form must reproduce the above copyright notice, this list of
 *       conditions and the following disclaimer in the documentation and/or other materials
 *       provided with the distribution.
 *
 *   3.) Neither the name of Robert Kosek nor the names of its contributors may be used to
 *       endorse or promote products derived from this software without specific prior written
 *       permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Robert Kosek <robert.kosek@thewickedflea.com>
 * @version 0.2
 * @copyright Robert Kosek, 5 February, 2011
 * @license BSD License <license.txt>
 * @package Perry
 **/

/**
 * Returns an array consisting of the values of the items in $keys, in the order given in
 * $keys. Helps with captures being in order, etc.
 *
 * @param Array $array 
 * @param Array $keys 
 * @return Array
 * @author Robert Kosek
 */
function array_select_values_of(Array $array, Array $keys) {
	$result = array();
	
	foreach($keys as $key) {
		$result[] = array_shift($array[$key]);
	}
	
	return $result;
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
      $perry->error($title, $message);
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
  
  // $routes[get|post|put|delete][string|pattern] = Closure
  private $routes = array();
  
  private function register_route($verb, $pattern, Closure $callback) {
    if(empty($this->routes[$verb])) {
      $this->routes[$verb] = array();
    }

		$route = array('callback' => $callback);

		// dynamic routes that sport named sections are now "compiled" into a regular expression
		// automatically.
		if(strpos($pattern, '<')) {
			// save a list of named keys in the route
			preg_match_all('/\<([-_\w\d]+)\>/i', $pattern, $named_keys);
			$route['keys'] = $named_keys[1];
			
			// escape regex symbols, and make groups as optional non-capturing subpatterns
			$regex = str_replace(array('.', '(', ')'), array('\.', '(?:', ')?'), $pattern);
			
			// named matches from named URI components
			$regex = preg_replace('/\<([-_\w\d]+)\>/i', '(?P<${1}>[-_\w\d]+)', $regex);
			
			// % instead of the standard slashes to allow for slashes in the regex
			$this->routes[$verb]["%${regex}%i"] = $route;
		} else {
	    $this->routes[$verb][$pattern] = $route;
		}
  }
  
  /**
   * This takes a pattern of either static or dynamic routes and will save it to an array of
	 * routes. Dynamic routes are compiled to a regular expression, support optional groups, and
	 * have an explicit format of: "/static_match/<article_id>(/<action>)"
   *
   * @param string $pattern 
   * @param Closure $callback 
   * @return Perry
   * @author Robert Kosek
   */
  public function get($pattern, Closure $callback) {
    $this->register_route('get', $pattern, $callback);
    return $this;
  }

  /**
   * This registers a PUT action with a pattern similar to the GET verb.
   *
   * @see Perry::get()
   * @param string $pattern 
   * @param Closure $callback 
   * @return Perry
   * @author Robert Kosek
   */
  public function put($pattern, Closure $callback) {
    $this->register_route('put', $pattern, $callback);
    return $this;
  }

  /**
   * This registers a POST action with a pattern similar to the GET verb.
   *
   * @see Perry::get()
   * @param string $pattern 
   * @param Closure $callback 
   * @return Perry
   * @author Robert Kosek
   */
  public function post($pattern, Closure $callback) {
    $this->register_route('post', $pattern, $callback);
    return $this;
  }

  /**
   * This registers a DELETE action with a pattern similar to the GET verb.
   *
   * @see Perry::get()
   * @param string $pattern 
   * @param Closure $callback 
   * @return Perry
   * @author Robert Kosek
   */
  public function delete($pattern, Closure $callback) {
    $this->register_route('delete', $pattern, $callback);
    return $this;
  }
  
  private $filters = array('before'=>array(), 'after'=>array());
  
  /**
   * This registers a before filter that matches either a static or regex pattern, without care
   * for the request method. As the name implies the filter is triggered BEFORE the request is
   * passed to the Response.
   *
   * @see Perry::trigger_filters()
   * @see Request::matches()
   * @param string $pattern 
   * @param Closure $callback 
   * @return void
   * @author Robert Kosek
   */
  public function before($pattern, Closure $callback) {
    $this->filters['before'][$pattern] = $callback;
  }
  
  /**
   * This registers an after filter that matches either a static or regex pattern, without care
   * for the request method. As the name implies the filter is triggered AFTER the request is
   * passed to the Response.
   *
   * @see Perry::trigger_filters()
   * @see Request::matches()
   * @param string $pattern 
   * @param Closure $callback 
   * @return void
   * @author Robert Kosek
   */
  public function after($pattern, Closure $callback) {
    $this->filters['after'][$pattern] = $callback;
  }
  
  /**
   * This triggers all associated/matching filters for the given time, before or after, the
   * Response execution.
   *
   * @see Perry::before()
   * @see Perry::after()
   * @see Request::matches()
   * @param string $when 
   * @param Request $request 
   * @return void
   * @author Robert Kosek
   */
  private function trigger_filters($when, Request $request) {
    foreach($this->filters[$when] as $pattern => $callback) {
      if($matches = $request->matches($pattern)) {
        call_user_func(&$callback, $request, is_array($matches) ? $matches : null);
      }
    }
  }
  
  private $response = null;
  
  /**
   * This method handles a given request. It attempts to match a route to the URI, and if it
   * fails to do so the response defaults to the Perry::not_found() method.
   *
   * @see Perry::get()
   * @see Perry::trigger_filters()
   * @see Request::matches()
   * @param Request $request 
   * @return void
   * @author Robert Kosek
   */
  public function handle(Request $request) {
    $uri  = $request->uri;
    $verb = $request->method;
    
    $callback = array($this, 'not_found');
    $params   = array();
    
    if(isset($this->routes[$verb][$uri])) {
      $callback = $this->routes[$verb][$uri]['callback'];
    } else {
      foreach($this->routes[$verb] as $pattern => $route_data) {
        if(($pattern[0] == '%') && ($request->matches($pattern, $matches))) {
					$data = array_select_values_of($matches, $route_data['keys']);
          $callback = $route_data['callback'];
					$request->params = &$data;
          $params = &$data;
          break;
        }
      }
    }

		array_unshift($params, $request); // prepend the request to the parameters

    $this->trigger_filters('before', $request);
    $this->response = new Response($callback, $params);
    $this->response->execute();
    $this->trigger_filters('after', $request);
  }
    
  /**
   * Redirects from the present action to the $to address with an optional redirect code and
   * immediately exits. Only supports 301 permanent, 302 temporary (no reason), and 307.
   *
   * @return void
   * @author Robert Kosek
   **/
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
        $this->error('Invalid Redirect Code', "<p>The code {$code} is not a valid redirect code, or has not been registered with Perry.</p>");
    }
    header('HTTP/1.1 '.$response);
    header('Location: '.$to);
    exit();
  }
  
  /**
   * Renders a 404 error page with basic information.
   *
   * TODO: Make this use Response::view() and be implemented as a Closure, so that it can be
   * overridden by a project.
   *
   * @see perry_error()
   * @param Request $request 
   * @return void
   * @author Robert Kosek
   */
  public function not_found(Request $request) {
    $this->error("404 Not Found", "<p>Perry is a great guy, but even he doesn't know what to do with a route like: <code>{$request->uri}</code></p>");
  }

	/**
	 * This is the universal "error" page for Perry apps. Pass it a title and a message,
	 * and the function will output it and halt operation.
	 *
	 * @param string $title
	 * @param string $message
	 * @author Robert Kosek
	 */
	public function error($title, $message) {
		die(Response::view('perry-error', array(
			'title'   => $title,
			'message' => $message
		)));
	}
}

class Response {
  private $callback = null;
  private $params   = null;
  
  public function __construct(Closure $callback, Array $params) {
    $this->callback = $callback;
    $this->params   = $params;
  }
  
  public function execute() {
    $func = &$this->callback; // must be dereferenced before being called
    call_user_func_array($func, $this->params);
  }
  
  public static function view($template, Array $locals) {
		$file = PERRY_ROOT . "/views/${template}.php";

		if(file_exists($file)) {
	    ob_start();
      extract($locals);

	    include $file; // include "" or die(""); appears to return null

	    $result = ob_get_contents();
	    ob_end_clean();
	
			return $result;
		} else {
			global $perry;
			$perry->error('Missing Template', "<p>The template &quot;${template}&quot; doesn't exist.</p>");
		}
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
	
	public function matches($pattern, &$matches) {
	  if((strcmp($this->uri, $pattern) == 0) || ((int)preg_match_all($pattern, $this->uri, &$matches) == 1)) {
	    return true;
	  }
	  return false;
	}
}

$perry   = new Perry;
$request = Request::getInstance();
register_shutdown_function(array($perry, 'handle'), $request);