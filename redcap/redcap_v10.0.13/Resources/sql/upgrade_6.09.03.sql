-- Add placeholder for upcoming survey theme functionality
ALTER TABLE `redcap_surveys` ADD `text_size` TINYINT(2) NULL DEFAULT NULL, ADD `font_family` TINYINT(2) NULL DEFAULT NULL ;
-- Fix some tables
drop table if exists `redcap_folder_projects`;
drop table if exists `redcap_folders`;
CREATE TABLE `redcap_folder_projects` (
`ui_id` int(10) DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`folder_id` int(10) DEFAULT NULL,
UNIQUE KEY `ui_id_project_folder` (`ui_id`,`project_id`,`folder_id`),
KEY `folder_id` (`folder_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `redcap_folders` (
`folder_id` int(10) NOT NULL AUTO_INCREMENT,
`ui_id` int(10) DEFAULT NULL,
`name` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
`position` int(10) DEFAULT NULL,
`foreground` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`background` varchar(6) COLLATE utf8_unicode_ci DEFAULT NULL,
`collapsed` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`folder_id`),
UNIQUE KEY `ui_id_name_uniq` (`ui_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_folder_projects`
ADD FOREIGN KEY (`folder_id`) REFERENCES `redcap_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_folders`
ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;