<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('dkim_private_key', '');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);