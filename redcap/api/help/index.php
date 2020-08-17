<?php


// Set constant for distinguishing this file from plugins or modules that call redcap_connect.php
define("REDCAP_CONNECT_NONVERSIONED", true);
// Call redcap_connect
include dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "redcap_connect.php";
// Call the file in the REDCap version directory
include dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "redcap_v" . $redcap_version . DIRECTORY_SEPARATOR . "API" . DIRECTORY_SEPARATOR . "help.php";