<?php
// Table changes
$aws_quickstart = (isset($GLOBALS['aws_quickstart']) && $GLOBALS['aws_quickstart'] == '1') ? '1' : '0';
$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('aws_quickstart', '$aws_quickstart');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);