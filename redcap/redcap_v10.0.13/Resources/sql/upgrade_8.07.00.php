<?php
// New tables
$newTables = "
-- Tables required by External Modules Framework
CREATE TABLE IF NOT EXISTS `redcap_external_modules_log` (
`log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`timestamp` datetime NOT NULL,
`ui_id` int(11) DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`external_module_id` int(11) DEFAULT NULL,
`project_id` int(11) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`message` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
PRIMARY KEY (`log_id`),
KEY `message` (`message`(190)),
KEY `record` (`record`),
KEY `external_module_id` (`external_module_id`),
KEY `redcap_log_redcap_projects_record` (`project_id`,`record`),
KEY `ui_id` (`ui_id`),
KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redcap_external_modules_log_parameters` (
`log_id` bigint(20) unsigned NOT NULL,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
PRIMARY KEY (`log_id`,`name`(191)),
KEY `name` (`name`(191)),
KEY `value` (`value`(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_external_modules_log_parameters`
ADD FOREIGN KEY (`log_id`) REFERENCES `redcap_external_modules_log` (`log_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";
// If db is using UT8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($newTables);