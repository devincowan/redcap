ALTER TABLE `redcap_user_information` CHANGE `ui_state` `ui_state` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `redcap_data_quality_status` ADD `repeat_instrument` VARCHAR(100) NULL DEFAULT NULL AFTER `field_name`;
ALTER TABLE `redcap_mobile_app_devices` DROP INDEX `uuid_project_id`;
ALTER TABLE `redcap_mobile_app_devices` ADD UNIQUE KEY `uuid_project_id` (`uuid`,`project_id`);
UPDATE `redcap_validation_types` SET
	`regex_js` = '/^([_a-z0-9-'']+)([.+][_a-z0-9-'']+)*@([a-z0-9-]+)(\\.[a-z0-9-]+)*(\\.[a-z]{2,63})$/i',
	`regex_php` = '/^([_a-z0-9-'']+)([.+][_a-z0-9-'']+)*@([a-z0-9-]+)(\\.[a-z0-9-]+)*(\\.[a-z]{2,63})$/i' 
	WHERE `validation_name` = 'email';
ALTER TABLE `redcap_mobile_app_log` 
	ADD `ui_id` INT(11) NULL DEFAULT NULL AFTER `project_id`, 
	ADD INDEX (`ui_id`),
	ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information`(`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;
update redcap_mobile_app_log a, redcap_user_information i, redcap_log_event e 
	set a.ui_id = i.ui_id where e.log_event_id = a.log_event_id and e.user = i.username and a.project_id = e.project_id;