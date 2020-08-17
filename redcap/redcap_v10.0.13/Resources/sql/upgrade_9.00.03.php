<?php

$sql = "
ALTER TABLE `redcap_projects` CHANGE `project_encoding` `project_encoding` ENUM('japanese_sjis','chinese_utf8','chinese_utf8_traditional') 
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Encoding to be used for various exported files';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);