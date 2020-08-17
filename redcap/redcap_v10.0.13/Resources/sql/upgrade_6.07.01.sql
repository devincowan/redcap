-- Add hidden upcoming features
INSERT INTO redcap_config (field_name, value) VALUES ('two_factor_auth_trust_period_days', '0');
INSERT INTO redcap_config (field_name, value) VALUES ('two_factor_auth_email_enabled', '1');
INSERT INTO redcap_config (field_name, value) VALUES ('two_factor_auth_authenticator_enabled', '1');
CREATE TABLE `redcap_two_factor_sms_response` (
`tf_id` int(10) NOT NULL AUTO_INCREMENT,
`user_id` int(10) DEFAULT NULL,
`time_sent` datetime DEFAULT NULL,
`phone_number` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
`verified` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`tf_id`),
KEY `phone_number` (`phone_number`),
KEY `time_sent` (`time_sent`),
KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_two_factor_sms_response`
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_user_information` ADD `user_phone_sms` VARCHAR(50) NULL DEFAULT NULL AFTER `user_phone`;
RENAME TABLE `redcap_two_factor_sms_response` TO `redcap_two_factor_response`;