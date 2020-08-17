-- Modify/add RTWS tables
DROP TABLE IF EXISTS redcap_web_service_mapping_exclude;
CREATE TABLE redcap_web_service_mapping_data (
  md_id int(10) NOT NULL AUTO_INCREMENT,
  map_id int(10) NOT NULL,
  mr_id int(10) DEFAULT NULL,
  source_timestamp datetime DEFAULT NULL COMMENT 'Date of service from source system',
  source_value text COLLATE utf8_unicode_ci COMMENT 'Data value from source system',
  adjudicated int(1) NOT NULL DEFAULT '0' COMMENT 'Has source value been adjudicated?',
  exclude int(1) NOT NULL DEFAULT '0' COMMENT 'Has source value been excluded?',
  PRIMARY KEY (md_id),
  KEY map_id (map_id),
  KEY map_id_timestamp (map_id,source_timestamp),
  KEY map_id_mr_id (map_id,mr_id),
  KEY map_id_exclude (map_id,exclude),
  KEY mr_id (mr_id),
  KEY map_id_adjudicated (map_id,adjudicated),
  KEY map_id_mr_id_timestamp_value (map_id,mr_id,source_timestamp,source_value(200))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Cached data values from web service';
CREATE TABLE redcap_web_service_mapping_records (
  mr_id int(10) NOT NULL AUTO_INCREMENT,
  project_id int(10) DEFAULT NULL,
  record varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  updated_at datetime DEFAULT NULL COMMENT 'Time of last data fetch',
  item_count int(10) DEFAULT NULL COMMENT 'New item count as of last data fetch',
  PRIMARY KEY (mr_id),
  UNIQUE KEY project_record (project_id,record),
  KEY project_id (project_id),
  KEY project_updated_at (project_id,updated_at)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_mapping_data`
  ADD CONSTRAINT redcap_web_service_mapping_data_ibfk_1 FOREIGN KEY (map_id) REFERENCES redcap_web_service_mapping (map_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_web_service_mapping_data_ibfk_2 FOREIGN KEY (mr_id) REFERENCES redcap_web_service_mapping_records (mr_id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `redcap_web_service_mapping_records`
  ADD CONSTRAINT redcap_web_service_mapping_records_ibfk_1 FOREIGN KEY (project_id) REFERENCES redcap_projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE;
-- Fix bug to cron job
UPDATE `redcap_crons` SET cron_name =  'ReminderUserAccessDashboardEmail' WHERE cron_name =  'ReminderUserAccessDashboardEmailer';
UPDATE  `redcap_crons` SET `cron_description` = 'At a regular interval, email all users to remind them to visit the User Access Dashboard page. Enables the ReminderUserAccessDashboardEmail cron job.'
	WHERE cron_name =  'ReminderUserAccessDashboard';
-- Add config value
insert into redcap_config values ('realtime_webservice_data_fetch_interval', '86400');
ALTER TABLE  `redcap_web_service_mapping_records` ADD INDEX (  `updated_at` );
ALTER TABLE  `redcap_web_service_mapping_records` ADD  `fetch_status` ENUM(  'QUEUED',  'FETCHING' ) NULL COMMENT  'Current status of data fetch for this record';
ALTER TABLE  `redcap_web_service_mapping_records` ADD INDEX (  `fetch_status` );
ALTER TABLE  `redcap_web_service_mapping_records` ADD INDEX  `project_id_fetch_status` (  `project_id` ,  `fetch_status` );
-- Add new crons
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('RtwsQueueRecordsAllProjects', 'Queue records that are ready to be fetched from the external source system via the Real-time Web Service Data Import service.',
	'ENABLED',  '300',  '600',  '1', '0', NULL , NULL ,  '0', NULL);
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('RtwsFetchRecordsAllProjects', 'Fetch data from the external source system for records already queued by the Real-time Web Service Data Import service.',
	'ENABLED',  '60',  '1800',  '10', '0', NULL , NULL ,  '0', NULL);
INSERT INTO `redcap_crons` (`cron_name` ,`cron_description` ,`cron_enabled` ,`cron_frequency` ,`cron_max_run_time` ,
	`cron_instances_max` ,`cron_instances_current` ,`cron_last_run_start` ,`cron_last_run_end` ,`cron_times_failed` ,`cron_external_url`)
	VALUES ('PurgeCronHistory', 'Purges all rows from the crons history table that are older than one week.',
	'ENABLED',  '86400',  '600',  '1', '0', NULL , NULL ,  '0', NULL);

ALTER TABLE  `redcap_crons_history` ADD INDEX (  `cron_run_start` );
ALTER TABLE  `redcap_crons_history` ADD INDEX (  `cron_run_end` );

insert into redcap_config values ('suspend_users_inactive_send_email', 1);