<?php

// Change this for production implementation and debugging info.
define('PRODUCTION', false);
// The real absolute path to this index.php file.
define('PERRY_ROOT', dirname(realpath(__FILE__)));

require 'lib/perry.php';

$perry->get('/', function($request) {
  echo "<h1>Perry says, &quot;Hi there.&quot;</h1>";
  
  foreach($perry->errors as $error) {
    echo <<<ERROR
<p class="{$error['severity']}">
  <em>{$error['severity']}:</em> {$error['message']} in file <code>{$error['file']}</code>
  on line <code>{$error['line']}</code>.
</p>
ERROR;
  }
});