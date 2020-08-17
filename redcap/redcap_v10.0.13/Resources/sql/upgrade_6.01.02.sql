-- Add new attributes for upcoming voice/sms functionality
ALTER TABLE  `redcap_projects` ADD INDEX (  `twilio_account_sid` );
ALTER TABLE  `redcap_surveys_participants` ADD  `participant_phone` VARCHAR( 50 ) NULL DEFAULT NULL AFTER  `participant_identifier`;
ALTER TABLE  `redcap_projects` ADD  `survey_phone_participant_field` VARCHAR( 255 ) NULL DEFAULT NULL
	COMMENT  'Field name that stores participant phone number' AFTER  `survey_email_participant_field`;