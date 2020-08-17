-- Add new attributes for upcoming voice/sms functionality
ALTER TABLE  `redcap_surveys_emails` ADD  `delivery_type` ENUM(  'EMAIL',  'VOICE',  'SMS' ) NOT NULL DEFAULT  'EMAIL';
ALTER TABLE  `redcap_surveys_scheduler` ADD  `delivery_type` ENUM(  'EMAIL',  'VOICE',  'SMS' ) NOT NULL DEFAULT  'EMAIL';
ALTER TABLE  `redcap_surveys_emails_recipients` ADD  `static_phone` VARCHAR( 50 )
	NULL DEFAULT NULL COMMENT  'Static phone number of recipient (used when participant has no phone number)';
ALTER TABLE  `redcap_surveys_scheduler_queue` CHANGE  `reason_not_sent`  `reason_not_sent`
	ENUM(  'EMAIL ADDRESS NOT FOUND',  'EMAIL ATTEMPT FAILED',  'UNKNOWN',  'SURVEY ALREADY COMPLETED',
	'VOICE/SMS SETTING DISABLED',  'ERROR SENDING SMS', 'ERROR MAKING VOICE CALL' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci
	NULL DEFAULT NULL COMMENT  'Explanation of why invitation did not send, if applicable';
INSERT INTO redcap_config (field_name, value) VALUES ('twilio_enabled_global', '0');
ALTER TABLE  `redcap_projects` ADD  `twilio_voice_gender` ENUM(  'woman',  'man' ) NOT NULL DEFAULT  'man',
	ADD  `twilio_voice_language` ENUM(  'en',  'en-gb',  'es',  'fr',  'de',  'it' ) NOT NULL DEFAULT  'en';