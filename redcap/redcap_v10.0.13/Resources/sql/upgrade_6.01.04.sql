-- Add new attributes for upcoming voice/sms functionality
ALTER TABLE  `redcap_projects` DROP  `twilio_voice_gender`, CHANGE  `twilio_voice_language`
	`twilio_voice_language` VARCHAR( 5 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  'en';