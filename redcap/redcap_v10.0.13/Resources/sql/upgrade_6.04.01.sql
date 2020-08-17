-- Add new user right setting for mobile app
ALTER TABLE `redcap_user_rights` ADD `mobile_app_download_data` INT(1) NOT NULL DEFAULT '0' AFTER `mobile_app`;
ALTER TABLE `redcap_user_roles` ADD `mobile_app_download_data` INT(1) NOT NULL DEFAULT '0' AFTER `mobile_app`;