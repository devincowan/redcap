<?php
// New tables
$newTables = "
-- Add new table for future functionality
drop table if exists redcap_record_list;
CREATE TABLE `redcap_record_list` (
`project_id` int(10) NOT NULL,
`arm` tinyint(2) NOT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
`dag_id` int(10) DEFAULT NULL,
`sort` mediumint(7) DEFAULT NULL,
PRIMARY KEY (`project_id`,`arm`,`record`),
UNIQUE KEY `sort_project_arm` (`sort`,`project_id`,`arm`),
KEY `dag_project_arm` (`dag_id`,`project_id`,`arm`),
KEY `project_record` (`project_id`,`record`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_record_list`
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_reports_folders` (
`folder_id` int(10) NOT NULL AUTO_INCREMENT,
`ui_id` int(10) DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`position` smallint(3) DEFAULT NULL,
`collapsed` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`folder_id`),
KEY `project_id_ui_id` (`project_id`,`ui_id`),
KEY `ui_id` (`ui_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_reports_folders_items` (
`folder_id` int(10) DEFAULT NULL,
`report_id` int(10) DEFAULT NULL,
UNIQUE KEY `folder_id_report_id` (`folder_id`,`report_id`),
KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_reports_folders`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_reports_folders_items`
ADD FOREIGN KEY (`folder_id`) REFERENCES `redcap_reports_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`report_id`) REFERENCES `redcap_reports` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";
// If db is using UT8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($newTables);