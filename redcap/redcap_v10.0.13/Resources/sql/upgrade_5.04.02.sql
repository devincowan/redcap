-- Increase group_id to 10 characters max in some places
ALTER TABLE  `redcap_data_access_groups` CHANGE  `group_id`  `group_id` INT( 10 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `redcap_events_calendar` CHANGE  `group_id`  `group_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_user_rights` CHANGE  `group_id`  `group_id` INT( 10 ) NULL DEFAULT NULL;
-- Increase ui_id to 10 characters max in some places
ALTER TABLE  `redcap_actions` CHANGE  `recipient_id`  `recipient_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'FK user_information';
ALTER TABLE  `redcap_metadata_prod_revisions` CHANGE  `ui_id_requester`  `ui_id_requester` INT( 10 ) NULL DEFAULT NULL ,
	CHANGE  `ui_id_approver`  `ui_id_approver` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_projects` CHANGE  `created_by`  `created_by` INT( 10 ) NULL DEFAULT NULL COMMENT  'FK from User Info';
ALTER TABLE  `redcap_user_information` CHANGE  `ui_id`  `ui_id` INT( 10 ) NOT NULL AUTO_INCREMENT;
-- Increase project_id to 10 characters max in some places
ALTER TABLE  `redcap_data_access_groups` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_user_rights` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_docs` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_edocs_metadata` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_esignatures` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_events_arms` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_events_calendar` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_library_map` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_locking_data` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_surveys_response_values` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_metadata_prod_revisions` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_standard_map_audit` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_metadata_temp` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_metadata` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_metadata_archive` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_data` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_log_event` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_log_view` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_projects` CHANGE  `project_id`  `project_id` INT( 10 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `redcap_project_checklist` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_standard_map` CHANGE  `project_id`  `project_id` INT( 10 ) NULL DEFAULT NULL;
-- Increase other PK id's to 10 characters max in some places
ALTER TABLE  `redcap_standard` CHANGE  `standard_id`  `standard_id` INT( 10 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `redcap_standard_code` CHANGE  `standard_code_id`  `standard_code_id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
	CHANGE  `standard_id`  `standard_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_standard_map` CHANGE  `standard_map_id`  `standard_map_id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
	CHANGE  `standard_code_id`  `standard_code_id` INT( 10 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `redcap_standard_map_audit` CHANGE  `standard_code`  `standard_code` INT( 10 ) NULL DEFAULT NULL;
-- Increase survey hash length to 10 characters
ALTER TABLE  `redcap_surveys_participants` CHANGE  `hash`  `hash` VARCHAR( 10 )
	CHARACTER SET latin1 COLLATE latin1_general_cs NULL DEFAULT NULL;
-- Add placeholders for upcoming enhancements
insert into redcap_config values ('openid_provider_url', '');
insert into redcap_config values ('openid_provider_name', '');
-- Add upcoming user_roles table
CREATE TABLE `redcap_user_roles` (
  `role_id` int(10) NOT NULL AUTO_INCREMENT,
  `project_id` int(10) DEFAULT NULL COMMENT 'NULL = system-wide role used by any project',
  `role_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Name of user role',
  `role_description` text COLLATE utf8_unicode_ci COMMENT 'Short description of user role',
  `role_description_lang_var` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '$lang variable name for system-level roles (for abstracted language)',
  `group_id` int(10) DEFAULT NULL,
  `lock_record` int(1) NOT NULL DEFAULT '0',
  `lock_record_multiform` int(1) NOT NULL DEFAULT '0',
  `lock_record_customize` int(1) NOT NULL DEFAULT '0',
  `data_export_tool` int(1) NOT NULL DEFAULT '1',
  `data_import_tool` int(1) NOT NULL DEFAULT '1',
  `data_comparison_tool` int(1) NOT NULL DEFAULT '1',
  `data_logging` int(1) NOT NULL DEFAULT '1',
  `file_repository` int(1) NOT NULL DEFAULT '1',
  `double_data` int(1) NOT NULL DEFAULT '0',
  `user_rights` int(1) NOT NULL DEFAULT '1',
  `data_access_groups` int(1) NOT NULL DEFAULT '1',
  `graphical` int(1) NOT NULL DEFAULT '1',
  `reports` int(1) NOT NULL DEFAULT '1',
  `design` int(1) NOT NULL DEFAULT '0',
  `calendar` int(1) NOT NULL DEFAULT '1',
  `data_entry` text COLLATE utf8_unicode_ci,
  `data_entry_all_forms` int(11) DEFAULT NULL COMMENT 'For ALL forms: 0=No access, 1=Read only, 2=Edit, 3=Edit survey response',
  `api_export` int(1) NOT NULL DEFAULT '0',
  `api_import` int(1) NOT NULL DEFAULT '0',
  `record_create` int(1) NOT NULL DEFAULT '1',
  `record_rename` int(1) NOT NULL DEFAULT '0',
  `record_delete` int(1) NOT NULL DEFAULT '0',
  `dts` int(1) NOT NULL DEFAULT '0' COMMENT 'DTS adjudication page',
  `participants` int(1) NOT NULL DEFAULT '1',
  `data_quality_design` int(1) NOT NULL DEFAULT '0',
  `data_quality_execute` int(1) NOT NULL DEFAULT '0',
  `data_quality_resolution` int(1) NOT NULL DEFAULT '0' COMMENT '0=No access, 1=View only, 2=Respond, 3=Open, close, respond',
  `random_setup` int(1) NOT NULL DEFAULT '0',
  `random_dashboard` int(1) NOT NULL DEFAULT '0',
  `random_perform` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`role_id`),
  KEY `project_id` (`project_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_user_roles`
  ADD CONSTRAINT `redcap_user_roles_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `redcap_user_roles_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- Add role_id to user_rights table
ALTER TABLE  `redcap_user_rights` ADD  `role_id` INT( 10 ) NULL DEFAULT NULL AFTER  `expiration`, ADD INDEX (  `role_id` );
ALTER TABLE  `redcap_user_rights` ADD FOREIGN KEY (  `role_id` ) REFERENCES `redcap_user_roles` (`role_id`) ON DELETE SET NULL ON UPDATE CASCADE ;