-- Add new columns
ALTER TABLE `redcap_mobile_app_devices` ADD `revoked` TINYINT NOT NULL DEFAULT '0' AFTER `nickname`;
ALTER TABLE `redcap_surveys` ADD `repeat_survey_btn_location` ENUM('BEFORE_SUBMIT','AFTER_SUBMIT') NOT NULL DEFAULT 'BEFORE_SUBMIT' AFTER `repeat_survey_btn_text`;