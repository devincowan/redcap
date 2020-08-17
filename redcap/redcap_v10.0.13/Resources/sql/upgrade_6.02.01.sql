-- Add new survey features
ALTER TABLE  `redcap_surveys`
	ADD  `hide_back_button` TINYINT( 1 ) NOT NULL DEFAULT  '0',
	ADD  `show_required_field_text` TINYINT( 1 ) NOT NULL DEFAULT  '1',
	ADD  `confirmation_email_subject` TEXT NULL DEFAULT NULL ,
	ADD  `confirmation_email_content` TEXT NULL DEFAULT NULL ,
	ADD  `confirmation_email_from` VARCHAR( 255 ) NULL DEFAULT NULL,
	ADD  `confirmation_email_attachment` INT( 10 ) NULL DEFAULT NULL COMMENT 'FK for redcap_edocs_metadata',
	ADD INDEX (  `confirmation_email_attachment` ),
	ADD FOREIGN KEY (  `confirmation_email_attachment` ) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- Add placeholder for upcoming mobile app
ALTER TABLE  `redcap_user_rights` ADD  `mobile_app` INT( 1 ) NOT NULL DEFAULT  '0' AFTER  `api_import`;
ALTER TABLE  `redcap_user_roles` ADD  `mobile_app` INT( 1 ) NOT NULL DEFAULT  '0' AFTER  `api_import`;
-- Change Twilio presets
ALTER TABLE  `redcap_surveys_emails` CHANGE  `delivery_type`  `delivery_type`
	ENUM(  'EMAIL',  'VOICE_INITIATE',  'SMS_INITIATE',  'SMS_INVITE', 'PARTICIPANT_PREF' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  'EMAIL';
ALTER TABLE  `redcap_surveys_scheduler` CHANGE  `delivery_type`  `delivery_type`
	ENUM(  'EMAIL',  'VOICE_INITIATE',  'SMS_INITIATE',  'SMS_INVITE', 'PARTICIPANT_PREF' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  'EMAIL';
ALTER TABLE  `redcap_surveys_participants` ADD  `delivery_preference` ENUM(  'EMAIL',  'VOICE_INITIATE',  'SMS_INITIATE',  'SMS_INVITE' ) NULL DEFAULT NULL;