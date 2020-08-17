<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// For development purposes only
if (!($isAjax && $super_user && isDev())) exit("ERROR!");

// Replace install.sql file with current table structure
$tableCheck = new SQLTableCheck();
$success = file_put_contents(APP_PATH_DOCROOT . "Resources/sql/install.sql", $tableCheck->build_install_file_from_tables());

// Response
print ($success === false) ? '0' : '1';