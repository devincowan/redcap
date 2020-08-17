<?php
// Table changes
$azure_quickstart = (isset($GLOBALS['azure_quickstart']) && $GLOBALS['azure_quickstart'] == '1') ? '1' : '0';
$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('azure_quickstart', '$azure_quickstart');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);