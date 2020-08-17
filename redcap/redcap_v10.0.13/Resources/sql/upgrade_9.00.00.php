<?php

$sql = "
CREATE TABLE `redcap_alerts` (
`alert_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`alert_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_deleted` tinyint(1) NOT NULL DEFAULT '0',
`alert_expiration` datetime DEFAULT NULL,
`form_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Instrument Name',
`form_name_event` int(10) DEFAULT NULL COMMENT 'Event ID',
`alert_condition` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Conditional logic',
`ensure_logic_still_true` tinyint(1) NOT NULL DEFAULT '0',
`prevent_piping_identifiers` tinyint(1) NOT NULL DEFAULT '1',
`email_incomplete` tinyint(1) DEFAULT '0' COMMENT 'Send alert for any form status?',
`email_from` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email From',
`email_to` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email To',
`email_cc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email CC',
`email_bcc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email BCC',
`email_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Subject',
`alert_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Message',
`email_failed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_attachment_variable` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'REDCap file variables',
`email_attachment1` int(10) DEFAULT NULL,
`email_attachment2` int(10) DEFAULT NULL,
`email_attachment3` int(10) DEFAULT NULL,
`email_attachment4` int(10) DEFAULT NULL,
`email_attachment5` int(10) DEFAULT NULL,
`email_repetitive` tinyint(1) DEFAULT '0' COMMENT 'Re-send alert on form re-save?',
`cron_send_email_on` enum('now','date','time_lag','next_occurrence') COLLATE utf8mb4_unicode_ci DEFAULT 'now' COMMENT 'When to send alert',
`cron_send_email_on_date` datetime DEFAULT NULL COMMENT 'Exact time to send',
`cron_send_email_on_time_lag_days` int(4) DEFAULT NULL,
`cron_send_email_on_time_lag_hours` int(3) DEFAULT NULL,
`cron_send_email_on_time_lag_minutes` int(3) DEFAULT NULL,
`cron_send_email_on_next_day_type` enum('DAY','WEEKDAY','WEEKENDDAY','SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DAY',
`cron_send_email_on_next_time` time DEFAULT NULL,
`cron_repeat_for` smallint(4) NOT NULL DEFAULT '0' COMMENT 'Repeat every # of days',
`cron_repeat_for_units` enum('DAYS','HOURS','MINUTES') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DAYS',
`email_timestamp_sent` datetime DEFAULT NULL COMMENT 'Time last alert was sent',
`email_sent` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Has at least one alert been sent?',
PRIMARY KEY (`alert_id`),
KEY `alert_expiration` (`alert_expiration`),
KEY `email_attachment1` (`email_attachment1`),
KEY `email_attachment2` (`email_attachment2`),
KEY `email_attachment3` (`email_attachment3`),
KEY `email_attachment4` (`email_attachment4`),
KEY `email_attachment5` (`email_attachment5`),
KEY `form_name_event` (`form_name_event`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_alerts_recurrence` (
`aq_id` int(10) NOT NULL AUTO_INCREMENT,
`alert_id` int(10) DEFAULT NULL,
`creation_date` datetime DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`instrument` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT NULL,
`send_option` enum('now','date','time_lag','next_occurrence') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'now',
`times_sent` smallint(4) DEFAULT NULL,
`last_sent` datetime DEFAULT NULL,
`status` enum('IDLE','QUEUED','SENDING') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDLE',
`first_send_time` datetime DEFAULT NULL,
`next_send_time` datetime DEFAULT NULL,
PRIMARY KEY (`aq_id`),
UNIQUE KEY `alert_id_record_instrument_instance` (`alert_id`,`record`,`event_id`,`instrument`,`instance`),
KEY `creation_date` (`creation_date`),
KEY `event_id` (`event_id`),
KEY `first_send_time` (`first_send_time`),
KEY `last_sent` (`last_sent`),
KEY `next_send_time_alert_id_status` (`next_send_time`,`alert_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_alerts_sent` (
`alert_sent_id` int(10) NOT NULL AUTO_INCREMENT,
`alert_id` int(10) NOT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`instrument` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT '1',
`last_sent` datetime DEFAULT NULL,
PRIMARY KEY (`alert_sent_id`),
UNIQUE KEY `alert_id_record_event_instrument_instance` (`alert_id`,`record`,`event_id`,`instrument`,`instance`),
KEY `event_id_record_alert_id` (`event_id`,`record`,`alert_id`),
KEY `last_sent` (`last_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_alerts_sent_log` (
`alert_sent_log_id` int(10) NOT NULL AUTO_INCREMENT,
`alert_sent_id` int(10) DEFAULT NULL,
`time_sent` datetime DEFAULT NULL,
`email_from` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_to` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_cc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`email_bcc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`subject` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`attachment_names` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`alert_sent_log_id`),
KEY `alert_sent_id_time_sent` (`alert_sent_id`,`time_sent`),
KEY `email_from` (`email_from`),
KEY `time_sent` (`time_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_alerts`
ADD FOREIGN KEY (`email_attachment1`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`email_attachment2`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`email_attachment3`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`email_attachment4`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`email_attachment5`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`form_name_event`) REFERENCES `redcap_events_metadata` (`event_id`),
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_alerts_recurrence`
ADD FOREIGN KEY (`alert_id`) REFERENCES `redcap_alerts` (`alert_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_alerts_sent`
ADD FOREIGN KEY (`alert_id`) REFERENCES `redcap_alerts` (`alert_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_alerts_sent_log`
ADD FOREIGN KEY (`alert_sent_id`) REFERENCES `redcap_alerts_sent` (`alert_sent_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Add new cron job
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('AlertsNotificationsSender', 'Sends notifications for Alerts', 'ENABLED', 60, 1800, 5, 0, NULL, 0, NULL),
('AlertsNotificationsDatediffChecker', 'Check all conditional logic in Alerts that uses \"today\" inside datediff() function', 'ENABLED', 14400, 7200, 1, 0, NULL, 0, NULL);

-- Add system-level configs
INSERT INTO `redcap_config` (`field_name`, `value`) VALUES 
('alerts_email_freeform_domain_allowlist', ''), 
('alerts_allow_email_variables', '1'), 
('alerts_allow_email_freeform', '1');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);

// Add Messenger system notification
$title = "New feature: Alerts & Notifications";
$msg = "You may now build triggered alerts for sending customized email notifications to one or more recipients, in which the emails may be activated or scheduled when a form/survey is saved and/or based on conditional logic whenever data is saved or imported. For an alert, you simply need to 1) define how the alert gets triggered, 2) determine when the notification should be sent, and 3) specify the recipient, message text, etc.

If you have \"Project Setup & Design\" privileges, you will see an \"Alerts & Notifications\" link on the left-hand project menu under Applications. Please read the instructions on that page to explore all the powerful options when building your alerts. ENJOY!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);