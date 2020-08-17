-- Change index on table (first, clear out table to prevent unique key issues)
SET FOREIGN_KEY_CHECKS = 0;
drop table if exists redcap_new_record_cache;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE `redcap_new_record_cache` (
`project_id` int(10) NOT NULL DEFAULT '0',
`event_id` int(10) DEFAULT NULL,
`arm_id` int(11) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`creation_time` datetime DEFAULT NULL,
UNIQUE KEY `proj_record_event` (`project_id`,`record`),
KEY `arm_id` (`arm_id`),
KEY `creation_time` (`creation_time`),
KEY `event_id` (`event_id`),
KEY `record_arm` (`record`,`arm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Save new record names to prevent record duplication';
ALTER TABLE `redcap_new_record_cache`
ADD FOREIGN KEY (`arm_id`) REFERENCES `redcap_events_arms` (`arm_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;