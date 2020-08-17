<?php

$sql = "
-- Enable the REDCap URL Shortener
UPDATE redcap_config SET value = '1' WHERE field_name = 'enable_url_shortener_redcap';
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);