-- Change index and add new fields
ALTER TABLE  `redcap_surveys_participants`
	DROP INDEX  `participant_email` ,
	ADD INDEX  `participant_email_phone` (  `participant_email` ,  `participant_phone` ),
	ADD  `access_code_numeral` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL AFTER  `access_code`,
	ADD UNIQUE (`access_code_numeral`),
	CHANGE  `delivery_preference`  `delivery_preference` ENUM(  'EMAIL',  'VOICE_INITIATE',  'SMS_INITIATE',
		'SMS_INVITE_MAKE_CALL',  'SMS_INVITE_RECEIVE_CALL' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE  `redcap_surveys_emails_recipients` ADD  `delivery_type` ENUM(  'EMAIL',  'VOICE_INITIATE', 'SMS_INITIATE' ,
	'SMS_INVITE_MAKE_CALL', 'SMS_INVITE_RECEIVE_CALL') NOT NULL DEFAULT  'EMAIL';
ALTER TABLE  `redcap_surveys_scheduler_queue` CHANGE  `reason_not_sent`  `reason_not_sent` ENUM(  'EMAIL ADDRESS NOT FOUND',
	'PHONE NUMBER NOT FOUND',  'EMAIL ATTEMPT FAILED',  'UNKNOWN',  'SURVEY ALREADY COMPLETED', 'VOICE/SMS SETTING DISABLED',
	'ERROR SENDING SMS',  'ERROR MAKING VOICE CALL' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL
	COMMENT  'Explanation of why invitation did not send, if applicable';
ALTER TABLE  `redcap_projects`
	CHANGE  `twilio_option_sms_invite`  `twilio_option_sms_invite_make_call` TINYINT( 1 ) NOT NULL DEFAULT  '0',
	ADD  `twilio_option_sms_invite_receive_call` TINYINT( 1 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_surveys_emails` CHANGE  `delivery_type`  `delivery_type` ENUM(  'PARTICIPANT_PREF',  'EMAIL',  'VOICE_INITIATE',
	'SMS_INITIATE',  'SMS_INVITE_MAKE_CALL',  'SMS_INVITE_RECEIVE_CALL' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  'EMAIL';
ALTER TABLE  `redcap_surveys_scheduler` CHANGE  `delivery_type`  `delivery_type` ENUM(  'EMAIL',  'VOICE_INITIATE',  'SMS_INITIATE',
	'SMS_INVITE_MAKE_CALL',  'SMS_INVITE_RECEIVE_CALL', 'PARTICIPANT_PREF' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  'EMAIL';