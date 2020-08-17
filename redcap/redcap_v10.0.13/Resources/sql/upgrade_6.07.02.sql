-- Add hidden upcoming features
ALTER TABLE `redcap_user_information` ADD `two_factor_auth_twilio_prompt_phone` TINYINT(1) NOT NULL DEFAULT '1' ;
drop table if exists `redcap_two_factor_sms_response`;
drop table if exists `redcap_two_factor_response`;
CREATE TABLE `redcap_two_factor_response` (
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
ALTER TABLE `redcap_two_factor_response`
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_projects`
	ADD `two_factor_exempt_project` TINYINT(1) NOT NULL DEFAULT '0',
	ADD `two_factor_force_project` TINYINT(1) NOT NULL DEFAULT '0';