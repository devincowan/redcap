<?php

$sql = "
ALTER TABLE `redcap_data_quality_status` DROP INDEX `event_id`, ADD INDEX `event_record` (`event_id`, `record`);
ALTER TABLE `redcap_projects` ADD INDEX `auth_meth` (`auth_meth`);
ALTER TABLE `redcap_projects` ADD INDEX `date_deleted` (`date_deleted`);
ALTER TABLE `redcap_sessions` ADD INDEX `session_expiration` (`session_expiration`);
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);