-- Add placeholders for upcoming features
insert into redcap_config values ('realtime_webservice_url_metadata', '');
insert into redcap_config values ('realtime_webservice_url_data', '');
insert into redcap_config values ('realtime_webservice_url_user_access', '');
ALTER TABLE  `redcap_projects` ADD  `realtime_webservice_enabled` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'Is real-time web service enabled for external data import?';
-- Table structure for table 'redcap_web_service_mapping'
DROP TABLE IF EXISTS redcap_web_service_mapping;
CREATE TABLE redcap_web_service_mapping (
  map_id int(10) NOT NULL AUTO_INCREMENT,
  project_id int(10) DEFAULT NULL,
  field_name varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  event_id int(10) DEFAULT NULL,
  external_source_field_name varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Unique name of field mapped from external data source',
  is_record_identifier int(1) DEFAULT NULL COMMENT '1=Yes, Null=No',
  PRIMARY KEY (map_id),
  UNIQUE KEY project_field_event (project_id,field_name,event_id),
  UNIQUE KEY project_identifier (project_id,is_record_identifier),
  KEY project_id (project_id),
  KEY field_name (field_name),
  KEY event_id (event_id),
  KEY external_source_field_name (external_source_field_name)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_mapping`
  ADD CONSTRAINT redcap_web_service_mapping_ibfk_1 FOREIGN KEY (project_id) REFERENCES redcap_projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_web_service_mapping_ibfk_2 FOREIGN KEY (event_id) REFERENCES redcap_events_metadata (event_id) ON DELETE CASCADE ON UPDATE CASCADE;
-- Rename a couple tables
RENAME TABLE `redcap_surveys_ip_cache` TO `redcap_ip_cache`;
RENAME TABLE `redcap_surveys_banned_ips` TO `redcap_ip_banned`;
-- Add new cron jpb
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('ClearIPCache',  'Clear all IP addresses older than X minutes from the redcap_ip_cache table.',  'ENABLED',  '180',  '60',  '1',  '0', NULL , NULL ,  '0', NULL);
-- Add new setting
insert into redcap_config values ('page_hit_threshold_per_minute', '600');
