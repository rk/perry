<!DOCTYPE html>
<html>
  <head>
    <title><?php echo $title; ?> &ndash; Perry Error</title>
    <style type="text/css">
      html, body { height: 100%; }
      html {
        background: #cedce7;
        background: -moz-linear-gradient(top, #cedce7 0%, #596a72 100%);
        background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#cedce7), color-stop(100%,#596a72));
        filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#cedce7', endColorstr='#596a72', GradientType=0);
      }
      body { -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; margin: 0 auto; width: 700px; padding: 25px 50px; background: white; background: rgba(255,255,255,0.8); }
      h1 { font-family: sans-serif; }
      p { font-size: 1.2em; line-height: 1.4; }
      img { margin-left: 1em; background: #EEE; padding: 4px; border: 1px solid gray; }
    </style>
  </head>
  <body>
		<header>
	    <img src="/~robert/perry/images/perry.jpeg" align="right">
	    <h1><?php echo $title; ?></h1>
		</header>
		<article>
	    <?php echo $message; ?>
		</article>
  </body>
</html>