<?php

require 'lib/perry.php';

$perry->get('/', function($request, $perry) {
  echo "<h1>Perry says, &quot;Hi there.&quot;</h1>";
});


/**
 * This end to the script should not be changed. It handles the request by finding the necessary
 * route and function. Then it cleans up any leaky content creation (nothing should be output by
 * this time so don't worry) and finally executes the action.
 */
$perry->handle(Request::getInstance())
      ->render();