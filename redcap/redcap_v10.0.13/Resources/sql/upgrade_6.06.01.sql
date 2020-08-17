-- Add backend placeholders for future features
ALTER TABLE `redcap_surveys`
	ADD `text_to_speech` INT(1) NOT NULL DEFAULT '0',
	ADD `text_to_speech_language` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'en';
ALTER TABLE `redcap_metadata`
	ADD `video_url` VARCHAR(255) NULL DEFAULT NULL,
	ADD `video_display_inline` TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE `redcap_metadata_temp`
	ADD `video_url` VARCHAR(255) NULL DEFAULT NULL,
	ADD `video_display_inline` TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE `redcap_metadata_archive`
	ADD `video_url` VARCHAR(255) NULL DEFAULT NULL AFTER `misc`,
	ADD `video_display_inline` TINYINT(1) NOT NULL DEFAULT '0' AFTER `video_url`;
INSERT INTO redcap_config (field_name, value) VALUES ('enable_field_attachment_video_url', '0');
INSERT INTO redcap_config (field_name, value) VALUES ('enable_survey_text_to_speech', '0');
-- Add new table for caching new record names
CREATE TABLE IF NOT EXISTS `redcap_new_record_cache` (
`project_id` int(10) NOT NULL DEFAULT '0',
`event_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
`creation_time` datetime DEFAULT NULL,
UNIQUE KEY `proj_record_event` (`project_id`,`record`,`event_id`),
KEY `creation_time` (`creation_time`),
KEY `event_id` (`event_id`),
FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Save new record names to prevent record duplication';
delete from redcap_crons where cron_name = 'ClearNewRecordCache';
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('ClearNewRecordCache', 'Clear all items from redcap_new_record_cache table older than X hours.',  'ENABLED',  10800,  300,  1,  0, NULL , 0, NULL);