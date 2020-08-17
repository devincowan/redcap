<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Validate request first
if (!$super_user || !isset($_POST['settingName']) || !isset($_POST['value'])) exit('0');

// Save value in redcap_config table
$sql = "update redcap_config set value = '".db_escape($_POST['value'])."' where field_name = '".db_escape($_POST['settingName'])."'";

// Output response
print (db_query($sql) ? '1' : '0');