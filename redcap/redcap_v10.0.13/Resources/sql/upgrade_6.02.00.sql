-- Add new attributes for upcoming voice/sms functionality
ALTER TABLE  `redcap_projects`
	ADD  `twilio_option_voice_initiate` TINYINT( 1 ) NOT NULL DEFAULT  '0',
	ADD  `twilio_option_sms_initiate` TINYINT( 1 ) NOT NULL DEFAULT  '0',
	ADD  `twilio_option_sms_invite` TINYINT( 1 ) NOT NULL DEFAULT  '0';
-- New survey scheduler attributes
ALTER TABLE  `redcap_surveys_scheduler_queue`
	ADD  `reminder_num` INT( 3 ) NOT NULL DEFAULT  '0' COMMENT  'Email reminder instance (0 = original invitation)' AFTER  `email_recip_id`,
	DROP INDEX  `email_recip_id_record` ,
	ADD UNIQUE  `email_recip_id_record` (  `email_recip_id` ,  `record` ,  `reminder_num` ),
	DROP INDEX  `ss_id_record` ,
	ADD UNIQUE  `ss_id_record` (  `ss_id` ,  `record` ,  `reminder_num` );
-- Changes for upcoming survey reminder functionality
ALTER TABLE  `redcap_surveys_scheduler_queue`
	CHANGE  `status`  `status` ENUM(  'QUEUED',  'SENDING',  'SENT',  'DID NOT SEND',  'DELETED' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci
	NOT NULL DEFAULT  'QUEUED' COMMENT  'Survey invitation status (default=QUEUED)';
ALTER TABLE `redcap_surveys_scheduler`
	ADD `reminder_type` enum('TIME_LAG','NEXT_OCCURRENCE','EXACT_TIME')
		COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'When to send reminders after original invite is sent',
	ADD `reminder_timelag_days` int(3) DEFAULT NULL COMMENT 'Wait X days to send reminders',
	ADD `reminder_timelag_hours` int(2) DEFAULT NULL COMMENT 'Wait X hours to send reminders',
	ADD `reminder_timelag_minutes` int(2) DEFAULT NULL COMMENT 'Wait X seconds to send reminders',
	ADD `reminder_nextday_type` enum('DAY','WEEKDAY','WEEKENDDAY','SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY')
		COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Wait till specific day/time to send reminders',
	ADD `reminder_nexttime` time DEFAULT NULL COMMENT 'Wait till specific day/time to send reminders',
	ADD `reminder_exact_time` datetime DEFAULT NULL COMMENT 'Wait till exact date/time to send reminders',
	ADD `reminder_num` INT( 3 ) NOT NULL DEFAULT  '0' COMMENT 'Reminder recurrence';