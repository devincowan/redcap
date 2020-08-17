-- Add new table for upcoming Twilio integration
CREATE TABLE `redcap_surveys_phone_codes` (
`phone_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
`twilio_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
`access_code` varchar(12) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`phone_number`,`twilio_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
-- Modify mobile app log table
ALTER TABLE  `redcap_mobile_app_log` DROP INDEX  `project_id_user` ,
	ADD INDEX  `project_id_event` (  `project_id` ,  `event` ),
	CHANGE  `event`  `event` ENUM(  'INIT_PROJECT',  'INIT_DOWNLOAD_DATA',  'INIT_DOWNLOAD_DATA_PARTIAL',  'REINIT_PROJECT',
	'REINIT_DOWNLOAD_DATA',  'REINIT_DOWNLOAD_DATA_PARTIAL',  'SYNC_DATA' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
-- Add extra setting for Twilio service enabling
ALTER TABLE  `redcap_projects` ADD  `twilio_pre_enabled` INT( 1 ) NOT NULL DEFAULT  '0' AFTER  `survey_auth_fail_window`;
update redcap_projects set twilio_pre_enabled = 1 where twilio_enabled = 1;