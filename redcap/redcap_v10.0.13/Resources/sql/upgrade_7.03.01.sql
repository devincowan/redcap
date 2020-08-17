ALTER TABLE `redcap_messages_threads` 
	ADD `project_id` INT(11) NULL DEFAULT NULL COMMENT 'Associated project', 
	ADD INDEX (`project_id`),
	ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects`(`project_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `redcap_log_view_requests` 
	ADD `ui_id` INT(11) NULL DEFAULT NULL COMMENT 'FK from redcap_user_information', 
	ADD INDEX (`ui_id`),
	ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information`(`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;
update redcap_auth set password_answer = null, password_question = null;
ALTER TABLE `redcap_auth` CHANGE `password_answer` `password_answer` TEXT
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Hashed answer to password recovery question';