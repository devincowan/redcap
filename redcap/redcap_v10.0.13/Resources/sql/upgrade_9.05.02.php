<?php

$sql = "
INSERT INTO redcap_config (field_name, value) VALUES ('external_modules_allow_activation_user_request', '1');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);