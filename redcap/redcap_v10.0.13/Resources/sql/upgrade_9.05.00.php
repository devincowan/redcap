<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('enable_url_shortener_redcap', '0');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);