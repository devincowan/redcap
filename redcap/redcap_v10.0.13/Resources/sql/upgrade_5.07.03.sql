 -- Make sure that no user role has DTS privileges
update redcap_user_roles set dts = 0 where dts = 1;
-- New tables for DDP
DROP TABLE IF EXISTS redcap_web_service_mapping_preview;
CREATE TABLE redcap_web_service_mapping_preview (
  project_id int(10) NOT NULL,
  field1 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  field2 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  field3 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  field4 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  field5 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_mapping_preview`
  ADD CONSTRAINT redcap_web_service_mapping_preview_ibfk_1 FOREIGN KEY (project_id) REFERENCES redcap_projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE;
DROP TABLE IF EXISTS redcap_web_service_mapping_logging;
CREATE TABLE redcap_web_service_mapping_logging (
  ml_id int(10) NOT NULL AUTO_INCREMENT,
  time_viewed datetime DEFAULT NULL COMMENT 'Time the data was displayed to the user',
  user_id int(10) DEFAULT NULL COMMENT 'PK from user_information table',
  project_id int(10) DEFAULT NULL,
  source_id varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'ID value from source system (e.g. MRN)',
  PRIMARY KEY (ml_id),
  KEY source_id (source_id),
  KEY project_id (project_id),
  KEY user_id (user_id),
  KEY user_project (user_id,project_id),
  KEY time_viewed (time_viewed)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DROP TABLE IF EXISTS redcap_web_service_mapping_logging_data;
CREATE TABLE redcap_web_service_mapping_logging_data (
  ml_id int(10) DEFAULT NULL COMMENT 'PK from mapping_logging table',
  source_field varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Field name from source system',
  source_timestamp datetime DEFAULT NULL COMMENT 'Date of service from source system',
  md_id int(10) DEFAULT NULL COMMENT 'PK from mapping_data table',
  KEY ml_id (ml_id),
  KEY source_timestamp (source_timestamp),
  KEY md_id (md_id),
  KEY source_field (source_field)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_web_service_mapping_logging`
  ADD CONSTRAINT redcap_web_service_mapping_logging_ibfk_1 FOREIGN KEY (project_id) REFERENCES redcap_projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_web_service_mapping_logging_ibfk_2 FOREIGN KEY (user_id) REFERENCES redcap_user_information (ui_id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `redcap_web_service_mapping_logging_data`
  ADD CONSTRAINT redcap_web_service_mapping_logging_data_ibfk_1 FOREIGN KEY (ml_id) REFERENCES redcap_web_service_mapping_logging (ml_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_web_service_mapping_logging_data_ibfk_2 FOREIGN KEY (md_id) REFERENCES redcap_web_service_mapping_data (md_id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE  `redcap_web_service_mapping_data` DROP INDEX  `map_id_mr_id_timestamp_value` ,
	ADD INDEX  `map_id_mr_id_timestamp_value` (  `map_id` ,  `mr_id` ,  `source_timestamp` ,  `source_value` ( 255 ) );
-- Clear table
DELETE FROM `redcap_web_service_mapping_records`;

-- Remove superfluous table indexes
ALTER TABLE `redcap_data` DROP INDEX  `project_id`;
ALTER TABLE `redcap_data` DROP INDEX  `project_record`;
ALTER TABLE `redcap_data` DROP INDEX  `record_field`;
ALTER TABLE `redcap_actions` DROP INDEX `survey_id`;
ALTER TABLE `redcap_auth_history` DROP INDEX `username`;
ALTER TABLE `redcap_data_quality_status` DROP INDEX  `pd_rule_id`;
ALTER TABLE `redcap_data_quality_status` DROP INDEX   `project_id`;
ALTER TABLE `redcap_data_quality_status` DROP INDEX `rule_id`;
ALTER TABLE `redcap_docs` DROP INDEX `project_id`;
ALTER TABLE `redcap_esignatures` DROP INDEX  `proj_rec`;
ALTER TABLE `redcap_esignatures` DROP INDEX  `proj_rec_event`;
ALTER TABLE `redcap_esignatures` DROP INDEX  `project_id`;
ALTER TABLE `redcap_events_arms` DROP INDEX `project_id`;
ALTER TABLE `redcap_events_calendar` DROP INDEX `project_id`;
ALTER TABLE `redcap_events_forms` DROP INDEX `event_id`;
ALTER TABLE `redcap_events_metadata` DROP INDEX `arm_id`;
ALTER TABLE `redcap_locking_data` DROP INDEX  `proj_rec`;
ALTER TABLE `redcap_locking_data` DROP INDEX `proj_rec_event`;
ALTER TABLE `redcap_locking_data` DROP INDEX `project_id`;
ALTER TABLE `redcap_locking_labels` DROP INDEX `project_id`;
ALTER TABLE `redcap_log_event` DROP INDEX  `event`;
ALTER TABLE `redcap_log_event` DROP INDEX `project_id`;
ALTER TABLE `redcap_log_view` DROP INDEX  `project_id`;
ALTER TABLE `redcap_log_view` DROP INDEX `user`;
ALTER TABLE `redcap_metadata` DROP INDEX `project_id`;
ALTER TABLE `redcap_metadata_archive` DROP INDEX `project_id`;
ALTER TABLE `redcap_metadata_prod_revisions` DROP INDEX `project_id`;
ALTER TABLE `redcap_metadata_temp` DROP INDEX `project_id`;
ALTER TABLE `redcap_page_hits` DROP INDEX `date2`;
ALTER TABLE `redcap_project_checklist` DROP INDEX `project_id`;
ALTER TABLE `redcap_randomization_allocation` DROP INDEX  `rid`;
ALTER TABLE `redcap_randomization_allocation` DROP INDEX `rid_status`;
ALTER TABLE `redcap_surveys` DROP INDEX `project_id`;
ALTER TABLE `redcap_surveys_emails` DROP INDEX `survey_id`;
ALTER TABLE `redcap_surveys_emails` DROP INDEX `email_id_sent`;
ALTER TABLE `redcap_surveys_participants` DROP INDEX `survey_id`;
ALTER TABLE `redcap_surveys_response` DROP INDEX `participant_id`;
ALTER TABLE `redcap_surveys_response_users` DROP INDEX `response_id`;
ALTER TABLE `redcap_surveys_response_values` DROP INDEX  `project_id`;
ALTER TABLE `redcap_surveys_response_values` DROP INDEX `project_record`;
ALTER TABLE `redcap_surveys_response_values` DROP INDEX `record_field`;
ALTER TABLE `redcap_surveys_scheduler` DROP INDEX  `condition_surveycomplete_survey_id`;
ALTER TABLE `redcap_surveys_scheduler` DROP INDEX `survey_id`;
ALTER TABLE `redcap_surveys_scheduler_queue` DROP INDEX  `email_recip_id`;
ALTER TABLE `redcap_surveys_scheduler_queue` DROP INDEX `scheduled_time_to_send`;
ALTER TABLE `redcap_surveys_scheduler_queue` DROP INDEX `ss_id`;
ALTER TABLE `redcap_web_service_mapping` DROP INDEX `project_id`;
ALTER TABLE `redcap_web_service_mapping` DROP INDEX `project_field_event`;
ALTER TABLE `redcap_web_service_mapping_data` DROP INDEX  `map_id`;
ALTER TABLE `redcap_web_service_mapping_data` DROP INDEX `map_id_mr_id`;
ALTER TABLE `redcap_web_service_mapping_logging` DROP INDEX `user_id`;
ALTER TABLE `redcap_web_service_mapping_records` DROP INDEX `project_id`;
ALTER TABLE `redcap_external_links_dags` DROP INDEX `ext_id`;
ALTER TABLE `redcap_external_links_exclude_projects` DROP INDEX `ext_id`;
ALTER TABLE `redcap_external_links_users` DROP INDEX `ext_id`;
ALTER TABLE `redcap_docs_to_edocs` DROP INDEX `docs_id`;
ALTER TABLE `redcap_library_map` DROP INDEX `project_id`;
-- Add new index
ALTER TABLE `redcap_surveys` ADD INDEX  `survey_expiration_enabled` (  `survey_expiration` ,  `survey_enabled` );
-- Rework existing indexes
ALTER TABLE `redcap_web_service_mapping_records` DROP INDEX  `project_updated_at` ,
	ADD INDEX  `project_updated_at` (  `updated_at` ,  `project_id` );
ALTER TABLE  `redcap_web_service_mapping_records` DROP INDEX  `updated_at`;
ALTER TABLE  `redcap_web_service_mapping_records` DROP INDEX  `project_id_fetch_status` ,
	ADD INDEX  `project_id_fetch_status` (  `fetch_status` ,  `project_id` );
ALTER TABLE  `redcap_web_service_mapping_records` DROP INDEX  `fetch_status`;
-- Add new DDP options
insert into redcap_config values ('realtime_webservice_global_enabled', '0');
insert into redcap_config values ('realtime_webservice_custom_text', '');
insert into redcap_config values ('realtime_webservice_display_info_project_setup', '1');
insert into redcap_config values ('realtime_webservice_source_system_custom_name', '');
insert into redcap_config values ('realtime_webservice_user_rights_super_users_only', '1');
update redcap_config set value = '24' where field_name = 'realtime_webservice_data_fetch_interval';
-- Rename DDP tables
rename table redcap_web_service_mapping_logging_data to redcap_ddp_log_view_data;
rename table redcap_web_service_mapping_logging to redcap_ddp_log_view;
rename table redcap_web_service_mapping_preview to redcap_ddp_preview_fields;
rename table redcap_web_service_mapping_data to redcap_ddp_records_data;
rename table redcap_web_service_mapping_records to redcap_ddp_records;
rename table redcap_web_service_mapping to redcap_ddp_mapping;
ALTER TABLE  `redcap_ddp_log_view_data` CHANGE  `ml_id`  `ml_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'PK from ddp_log_view table',
	CHANGE  `md_id`  `md_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'PK from ddp_records_data table';
ALTER TABLE  `redcap_ddp_records_data` CHANGE  `map_id`  `map_id` INT( 10 ) NOT NULL COMMENT  'PK from ddp_mapping table',
	CHANGE  `mr_id`  `mr_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'PK from ddp_records table';
ALTER TABLE  `redcap_ddp_records_data` CHANGE  `source_value`  `source_value` TEXT CHARACTER SET utf8
	COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT  'Encrypted data value from source system';
set foreign_key_checks=0;
truncate table redcap_ddp_records_data;
truncate table redcap_ddp_records;
set foreign_key_checks=1;
-- Rename DDP crons jobs
UPDATE `redcap_crons` SET  `cron_name` =  'DDPQueueRecordsAllProjects',
	`cron_description` =  'Queue records that are ready to be fetched from the external source system via the DDP service.'
	WHERE  `cron_name` =  'RtwsQueueRecordsAllProjects';
UPDATE `redcap_crons` SET  `cron_name` =  'DDPFetchRecordsAllProjects',
	`cron_description` =  'Fetch data from the external source system for records already queued by the DDP service.'
	WHERE  `cron_name` =  'RtwsFetchRecordsAllProjects';
-- More DDP changes
ALTER TABLE  `redcap_ddp_records_data` DROP INDEX  `map_id_adjudicated` ,
	ADD INDEX  `mr_id_adjudicated` (  `mr_id` ,  `adjudicated` );
ALTER TABLE  `redcap_ddp_records_data` DROP INDEX  `map_id_exclude` ,
	ADD INDEX  `mr_id_exclude` (  `mr_id` ,  `exclude` );
ALTER TABLE  `redcap_ddp_records_data` DROP INDEX  `mr_id`;
-- Add new column
ALTER TABLE  `redcap_projects` ADD  `last_logged_event` DATETIME NULL DEFAULT NULL;