-- Add new table
CREATE TABLE `redcap_log_open_requests` (
`log_view_id` int(10) NOT NULL DEFAULT '0',
`mysql_process_id` int(10) DEFAULT NULL,
`php_process_id` int(10) DEFAULT NULL,
PRIMARY KEY (`log_view_id`),
KEY `mysql_process_id` (`mysql_process_id`),
KEY `php_process_id` (`php_process_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_log_open_requests`
ADD FOREIGN KEY (`log_view_id`) REFERENCES `redcap_log_view` (`log_view_id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- Add new cron job
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('ClearLogOpenRequests', 'Clear all items from redcap_log_open_requests table older than X hours.',  'ENABLED',  1800,  300,  1,  0, NULL , 0, NULL);
-- Add new table for mobile app
drop table if exists redcap_mobile_app_log;
CREATE TABLE `redcap_mobile_app_log` (
`mal_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`log_event_id` int(10) DEFAULT NULL,
`event` enum('INIT_PROJECT','INIT_DOWNLOAD_DATA','INIT_DOWNLOAD_DATA_PARTIAL','REINIT_PROJECT','REINIT_DOWNLOAD_DATA','REINIT_DOWNLOAD_DATA_PARTIAL') COLLATE utf8_unicode_ci DEFAULT NULL,
`details` text COLLATE utf8_unicode_ci,
PRIMARY KEY (`mal_id`),
KEY `log_event_id` (`log_event_id`),
KEY `project_id_user` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_mobile_app_log`
ADD FOREIGN KEY (`log_event_id`) REFERENCES `redcap_log_event` (`log_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- Clear DDP records timestamps due to old bug
update redcap_ddp_records set updated_at = null, item_count = null;