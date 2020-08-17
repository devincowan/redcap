CREATE TABLE `redcap_mobile_app_devices` (
`device_id` int(10) NOT NULL AUTO_INCREMENT,
`uuid` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`nickname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`device_id`),
UNIQUE KEY `uuid_project_id` (`uuid`,`project_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_mobile_app_devices` ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_mobile_app_log` ADD `device_id` INT(10) NULL DEFAULT NULL AFTER `log_event_id`, ADD INDEX (`device_id`);
ALTER TABLE `redcap_mobile_app_log` ADD FOREIGN KEY (`device_id`) REFERENCES `redcap_mobile_app_devices`(`device_id`) ON DELETE SET NULL ON UPDATE CASCADE;
CREATE TABLE `redcap_data_dictionaries` (
`dd_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`doc_id` int(10) DEFAULT NULL,
PRIMARY KEY (`dd_id`),
KEY `doc_id` (`doc_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_data_dictionaries`
ADD FOREIGN KEY (`doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_data_dictionaries` ADD `ui_id` INT(10) NULL DEFAULT NULL AFTER `doc_id`, ADD INDEX (`ui_id`);
ALTER TABLE `redcap_data_dictionaries` ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information`(`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- Account manager user type
ALTER TABLE `redcap_user_information` CHANGE `super_user_administration` `account_manager` INT(1) NOT NULL DEFAULT '0';
-- Fix for incorrectly coded PROMIS assessment questions (Sleep119, Sleep120, Sleep4)
update redcap_metadata m, redcap_library_map l
set m.element_enum = '5, Not at all \\n 4, A little bit \\n 3, Somewhat \\n 2, Quite a bit \\n 1, Very much' 
where l.project_id = m.project_id and m.form_name = l.form_name and l.type = 1
and (m.field_name like 'promis_sleep119%' or m.field_name like 'promis_sleep120%'
or m.field_name like 'promis_sleep4\_%' or m.field_name = 'promis_sleep4')
and m.field_name not like '%qposition' and m.field_name not like '%stderror' and m.field_name not like '%tscore'
and m.element_preceding_header like 'Sleep%';