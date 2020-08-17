<?php
// New tables
$newTables = "
-- Reset this temporary table so that it can be rebuilt dynamically
TRUNCATE TABLE `redcap_record_counts`;
-- Add new status option
ALTER TABLE `redcap_record_counts` CHANGE `record_list_status` `record_list_status` 
	ENUM('NOT_STARTED','PROCESSING','COMPLETE','FIX_SORT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NOT_STARTED';
";
// If db is using UT8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($newTables);