-- Add new column to user info table to record UI state
ALTER TABLE `redcap_user_information` ADD `ui_state` TEXT NULL DEFAULT NULL AFTER `messaging_email_urgent_all`;
-- Repeating survey option
ALTER TABLE `redcap_surveys` ADD `repeat_survey_enabled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `enhanced_choices`;
ALTER TABLE `redcap_surveys` ADD `repeat_survey_btn_text` VARCHAR(255) NULL DEFAULT NULL AFTER `repeat_survey_enabled`;
-- New mobile app table field
ALTER TABLE `redcap_mobile_app_files` ADD `device_id` INT(10) NULL DEFAULT NULL AFTER `user_id`, ADD INDEX (`device_id`);
ALTER TABLE `redcap_mobile_app_files` ADD FOREIGN KEY (`device_id`) REFERENCES `redcap_mobile_app_devices`(`device_id`) ON DELETE SET NULL ON UPDATE CASCADE;
