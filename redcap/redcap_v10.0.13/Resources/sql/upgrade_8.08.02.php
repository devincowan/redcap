<?php
// New tables
$newTables = "
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE `redcap_reports_folders_items`;
DROP TABLE `redcap_reports_folders`;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE `redcap_reports_folders` (
`folder_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`position` smallint(4) DEFAULT NULL,
PRIMARY KEY (`folder_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_reports_folders_items` (
`folder_id` int(10) DEFAULT NULL,
`report_id` int(10) DEFAULT NULL,
UNIQUE KEY `folder_id_report_id` (`folder_id`,`report_id`),
KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_reports_folders`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_reports_folders_items`
ADD FOREIGN KEY (`folder_id`) REFERENCES `redcap_reports_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`report_id`) REFERENCES `redcap_reports` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_reports_folders` ADD UNIQUE `position_project_id` (`position`, `project_id`);

ALTER TABLE `redcap_record_list` DROP INDEX `sort_project_arm`, ADD INDEX `sort_project_arm` (`sort`, `project_id`, `arm`);
";
// If db is using UT8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($newTables);