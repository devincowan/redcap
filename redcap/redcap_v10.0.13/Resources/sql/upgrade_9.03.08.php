<?php

$sql = "
ALTER TABLE `redcap_record_counts` ADD `time_of_list_cache` TIMESTAMP NULL DEFAULT NULL AFTER `record_list_status`, ADD INDEX (`time_of_list_cache`);
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);