ALTER TABLE `redcap_record_dashboards` 
	ADD `arm` TINYINT(2) NULL DEFAULT NULL AFTER `excluded_forms_events`, 
	ADD `sort_event_id` INT(11) NULL DEFAULT NULL AFTER `arm`, 
	ADD `sort_field_name` VARCHAR(100) NULL DEFAULT NULL AFTER `sort_event_id`, 
	ADD `sort_order` ENUM('ASC','DESC') NOT NULL DEFAULT 'ASC' AFTER `sort_field_name`;
ALTER TABLE `redcap_record_dashboards` 
	CHANGE `excluded_forms_events` `selected_forms_events` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	ADD INDEX(`sort_event_id`),
	ADD FOREIGN KEY (`sort_event_id`) REFERENCES `redcap_events_metadata`(`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_user_information` ADD `messaging_email_ts` DATETIME NULL DEFAULT NULL AFTER `messaging_email_urgent_all`;

CREATE TABLE `redcap_messages` (
`message_id` int(10) NOT NULL AUTO_INCREMENT,
`thread_id` int(10) DEFAULT NULL COMMENT 'Thread that message belongs to (FK from redcap_messages_threads)',
`sent_time` datetime DEFAULT NULL COMMENT 'Time message was sent',
`author_user_id` int(10) DEFAULT NULL COMMENT 'Author of message (FK from redcap_user_information)',
`message_body` text COLLATE utf8_unicode_ci COMMENT 'The message itself',
`attachment_doc_id` int(10) DEFAULT NULL COMMENT 'doc_id if there is an attachment (FK from redcap_edocs_metadata)',
`stored_url` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
PRIMARY KEY (`message_id`),
KEY `attachment_doc_id` (`attachment_doc_id`),
KEY `author_user_id` (`author_user_id`),
KEY `sent_time` (`sent_time`),
KEY `thread_id` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `redcap_messages_recipients` (
`recipient_id` int(10) NOT NULL AUTO_INCREMENT,
`thread_id` int(10) DEFAULT NULL COMMENT 'Thread that recipient belongs to (FK from redcap_messages_threads)',
`recipient_user_id` int(10) DEFAULT NULL COMMENT 'Individual recipient in thread (FK from redcap_user_information)',
`all_users` tinyint(1) DEFAULT '0' COMMENT 'Set if recipients = ALL USERS',
`prioritize` tinyint(1) NOT NULL DEFAULT '0',
`conv_leader` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`recipient_id`),
KEY `recipient_user_id` (`recipient_user_id`),
KEY `thread_id_users` (`thread_id`,`all_users`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `redcap_messages_status` (
`status_id` int(10) NOT NULL AUTO_INCREMENT,
`message_id` int(10) DEFAULT NULL COMMENT 'FK from redcap_messages',
`recipient_id` int(10) DEFAULT NULL COMMENT 'Individual recipient in thread (FK from redcap_messages_recipients)',
`recipient_user_id` int(10) DEFAULT NULL COMMENT 'Individual recipient in thread (FK from redcap_user_information)',
`viewed_time` datetime DEFAULT NULL COMMENT 'Time message was viewed (NULL = not viewed yet)',
`urgent` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`status_id`),
KEY `message_id` (`message_id`),
KEY `recipient_id` (`recipient_id`),
KEY `recipient_user_id` (`recipient_user_id`),
KEY `viewed_time` (`viewed_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `redcap_messages_threads` (
`thread_id` int(10) NOT NULL AUTO_INCREMENT,
`type` enum('CHANNEL','NOTIFICATION','CONVERSATION') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Type of entity',
`channel_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Only for channels',
`invisible` tinyint(1) NOT NULL DEFAULT '0',
`archived` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`thread_id`),
KEY `type_channel` (`type`,`channel_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `redcap_messages`
ADD FOREIGN KEY (`attachment_doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`author_user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`thread_id`) REFERENCES `redcap_messages_threads` (`thread_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_messages_recipients`
ADD FOREIGN KEY (`recipient_user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`thread_id`) REFERENCES `redcap_messages_threads` (`thread_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_messages_status`
ADD FOREIGN KEY (`message_id`) REFERENCES `redcap_messages` (`message_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`recipient_id`) REFERENCES `redcap_messages_recipients` (`recipient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`recipient_user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO redcap_messages_threads (thread_id, type, channel_name, invisible, archived) VALUES
(1, 'NOTIFICATION', 'What''s new', 0, 0),
(2, 'NOTIFICATION', NULL, 0, 0),
(3, 'NOTIFICATION', 'Notifications', 0, 0);

INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('UserMessagingEmailNotifications', 'Send notification emails to users who are logged out but have received a user message or notification.', 'ENABLED', 60, 600, 1, 0, NULL, 0, NULL);