-- Add extra Twilio system option
INSERT INTO redcap_config VALUES ('twilio_display_info_project_setup', '0');
-- Enable global setting for upcoming mobile app
INSERT INTO redcap_config VALUES ('mobile_app_enabled', '1');
-- Add new table for upcoming mobile app
CREATE TABLE `redcap_mobile_app_files` (
`af_id` int(10) NOT NULL AUTO_INCREMENT,
`doc_id` int(10) NOT NULL,
`type` enum('ESCAPE_HATCH','LOGGING') COLLATE utf8_unicode_ci DEFAULT NULL,
`user_id` int(10) DEFAULT NULL,
PRIMARY KEY (`af_id`),
KEY `doc_id` (`doc_id`),
KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_mobile_app_files`
ADD FOREIGN KEY (`doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;