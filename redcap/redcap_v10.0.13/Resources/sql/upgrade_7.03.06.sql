-- Clear out some messaging tables
ALTER TABLE `redcap_messages_status` DROP `viewed_time`;
delete from redcap_messages_threads where thread_id > 3;
delete from redcap_messages where thread_id > 3 or thread_id <= 3;
delete from redcap_messages_recipients where thread_id > 3 or thread_id <= 3;
ALTER TABLE redcap_messages AUTO_INCREMENT=1;
ALTER TABLE redcap_messages_recipients AUTO_INCREMENT=1;
INSERT INTO redcap_messages_recipients (recipient_id, thread_id, all_users) VALUES 
(1, 1, 1), 
(2, 2, 1),
(3, 3, 1);
update redcap_user_information set messaging_email_preference = 'NONE';
ALTER TABLE `redcap_user_information` CHANGE `messaging_email_preference` `messaging_email_preference` 
	ENUM('NONE','2_HOURS','4_HOURS','6_HOURS','8_HOURS','12_HOURS','DAILY') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '4_HOURS';
update redcap_user_information set messaging_email_preference = '4_HOURS';
alter table redcap_messages add key `message_body` (`message_body`(255));
ALTER TABLE `redcap_messages_recipients` DROP INDEX `recipient_user_id`, ADD UNIQUE `recipient_user_thread_id` (`recipient_user_id`, `thread_id`);
-- Fix foreign keys in dashboard table
CREATE TABLE `redcap_record_dashboards2` (
 `rd_id` int(11) NOT NULL AUTO_INCREMENT,
 `project_id` int(11) DEFAULT NULL,
 `title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
 `description` text COLLATE utf8_unicode_ci,
 `filter_logic` text COLLATE utf8_unicode_ci,
 `orientation` enum('V','H') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'H',
 `group_by` enum('form','event') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'event',
 `selected_forms_events` text COLLATE utf8_unicode_ci,
 `arm` tinyint(2) DEFAULT NULL,
 `sort_event_id` int(11) DEFAULT NULL,
 `sort_field_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
 `sort_order` enum('ASC','DESC') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ASC',
 PRIMARY KEY (`rd_id`),
 KEY `project_id` (`project_id`),
 KEY `sort_event_id` (`sort_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_record_dashboards2`
 ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 ADD FOREIGN KEY (`sort_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
delete from redcap_record_dashboards where project_id not in (select project_id from redcap_projects);
insert into redcap_record_dashboards2 select * from redcap_record_dashboards;
drop table redcap_record_dashboards;
rename table redcap_record_dashboards2 to redcap_record_dashboards;

-- Add External Modules tables
CREATE TABLE IF NOT EXISTS `redcap_external_module_settings` (
`external_module_id` int(11) NOT NULL,
`project_id` int(11) DEFAULT NULL,
`key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
`type` varchar(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'string',
`value` text COLLATE utf8_unicode_ci NOT NULL,
KEY `external_module_id` (`external_module_id`),
KEY `key` (`key`),
KEY `project_id` (`project_id`),
KEY `value` (`value`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `redcap_external_modules` (
`external_module_id` int(11) NOT NULL AUTO_INCREMENT,
`directory_prefix` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
PRIMARY KEY (`external_module_id`),
UNIQUE KEY `directory_prefix` (`directory_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `redcap_external_module_settings`
ADD FOREIGN KEY (`external_module_id`) REFERENCES `redcap_external_modules` (`external_module_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;