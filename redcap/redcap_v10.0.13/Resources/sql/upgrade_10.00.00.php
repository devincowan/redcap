<?php
// Import new project template create_demo_db13.sql
print file_get_contents(APP_PATH_DOCROOT."Resources/sql/create_demo_db13.sql");


$sql = "
ALTER TABLE `redcap_projects` ADD `twilio_hide_in_project` TINYINT(1) NOT NULL DEFAULT '0' AFTER `twilio_modules_enabled`;
ALTER TABLE `redcap_user_whitelist` CHANGE `username` `username` varchar(191) COLLATE utf8_unicode_ci NOT NULL DEFAULT '';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);


// Add Messenger system notification
$url = $GLOBALS['redcap_base_url'].((substr($GLOBALS['redcap_base_url'], -1) != "/") ? "/" : "")."redcap_v10.0.0/DataEntry/field_embedding_explanation.php";
$title = "New feature: Field Embedding";
$msg = "The Field Embedding feature in REDCap is the ultimate way to customize your surveys and data collection instruments to make them look exactly how you want. Field Embedding allows you to reposition fields on a survey or data entry form to embed them in a new location on the same page. Embedding fields gives you greater control over the look and feel of your instrument. For example, you may place fields in a grid/table for a more compact user-friendly page, or you can position some fields close together in a group if they are related. 

For Field Embedding to work, just place the variable name of a REDCap field in curly brackets { } - e.g. {date_of_birth} - inside a field label, section header, or choice label. Once done, that field will be repositioned to that specific location on the page. It's as simple as that!

For more information on Field Embedding and to view screenshots of how it looks, see $url";
print Messenger::generateNewSystemNotificationSQL($title, $msg);