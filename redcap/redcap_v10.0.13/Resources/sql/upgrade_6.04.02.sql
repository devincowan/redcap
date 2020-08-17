-- Add new user info setting for upcoming two-factor authentication feature
ALTER TABLE `redcap_user_information`
	ADD `two_factor_auth_secret` VARCHAR(20) NULL DEFAULT NULL,
	ADD INDEX(`two_factor_auth_secret`);
INSERT INTO redcap_config (field_name, value) VALUES ('two_factor_auth_enabled', '0');
ALTER TABLE `redcap_user_information` ADD `user_phone` VARCHAR(50) NULL DEFAULT NULL AFTER `user_email3`;