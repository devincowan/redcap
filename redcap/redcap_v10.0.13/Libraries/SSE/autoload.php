<?php

/**
 * autoload function
 * use this or (better) vendor/autoload.php
 *
 * @param string $className
 * @return void
 */
function server_sent_events_autoload($className) {
//   $classWithNamespace = explode('\\', $className);
  $match = preg_match("/^(REDCap)\\\\(SSE)\\\\([^\\\\]+)/", $className, $matches);
  if($match==false) return; // pattern not matched or error
  // check the namespace
  $fileName = $matches[3];


  $filePath = dirname(__FILE__) . '/src/' . $fileName . '.php';
  if (file_exists($filePath)) {
    require_once($filePath);
  }
}

spl_autoload_register('server_sent_events_autoload');