ALTER TABLE `redcap_new_record_cache` ADD `arm_id` INT(11) NULL DEFAULT NULL AFTER `event_id`, ADD INDEX (`arm_id`);
ALTER TABLE `redcap_new_record_cache` ADD FOREIGN KEY (`arm_id`) REFERENCES `redcap_events_arms`(`arm_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_new_record_cache` ADD UNIQUE KEY `record_arm` (`record`,`arm_id`);
update redcap_new_record_cache c, redcap_events_metadata m set c.arm_id = m.arm_id where m.event_id = c.event_id;