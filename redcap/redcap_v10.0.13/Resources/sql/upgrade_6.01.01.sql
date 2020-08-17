-- Add new fields for upcoming voice/sms functionality
ALTER TABLE  `redcap_projects`
	ADD  `twilio_enabled` INT( 1 ) NOT NULL DEFAULT  '0',
	ADD  `twilio_account_sid` VARCHAR( 64 ) NULL,
	ADD  `twilio_auth_token` VARCHAR( 64 ) NULL,
	ADD  `twilio_from_number` BIGINT( 16 ) NULL;