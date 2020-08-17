-- Optimize log_event table columns/indexes (will take a LONG time to run)
update redcap_log_event set pk = left(pk, 200) where length(pk) > 200;
update redcap_log_event set description = concat(left(description, 97),'...') where length(description) > 100;
ALTER TABLE  `redcap_log_event`
	CHANGE `pk` `pk` VARCHAR( 200 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	CHANGE  `description`  `description` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	DROP INDEX  `pk`, ADD INDEX  `pk` ( `pk` );
-- Add indexes to log_view table (will take a LONG time to run)
ALTER TABLE  `redcap_log_view`
	ADD INDEX  `user_project` ( `user`, `project_id` ),
	ADD INDEX  `project_event_record` ( `project_id`, `event_id`, `record` );
-- Add user rights categories for upcoming Real-time Web Services functionality
ALTER TABLE  `redcap_user_rights`
	ADD  `realtime_webservice_mapping` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'User can map fields for RTWS',
	ADD  `realtime_webservice_adjudicate` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'User can adjudicate data for RTWS';
ALTER TABLE  `redcap_user_roles`
	ADD  `realtime_webservice_mapping` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'User can map fields for RTWS',
	ADD  `realtime_webservice_adjudicate` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'User can adjudicate data for RTWS';
-- RTWS exclude table
DROP TABLE IF EXISTS redcap_web_service_mapping_exclude;
CREATE TABLE redcap_web_service_mapping_exclude (
  map_id int(10) NOT NULL,
  record varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  UNIQUE KEY map_id_record_value (map_id,record,`value`),
  KEY map_id (map_id),
  KEY map_id_record (map_id,record)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_mapping_exclude`
  ADD CONSTRAINT redcap_web_service_mapping_exclude_ibfk_1 FOREIGN KEY (map_id) REFERENCES redcap_web_service_mapping (map_id) ON DELETE CASCADE ON UPDATE CASCADE;
