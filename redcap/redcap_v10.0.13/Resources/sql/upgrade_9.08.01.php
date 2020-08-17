<?php

$sql = "
ALTER TABLE redcap_projects_user_hidden COLLATE utf8mb4_unicode_ci;
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);