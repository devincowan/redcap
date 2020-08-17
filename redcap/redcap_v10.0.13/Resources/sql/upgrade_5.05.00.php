<?php

// Add new Piping Example as a disable template
include APP_PATH_DOCROOT."Resources".DS."sql".DS."create_demo_db11.sql";
print "
-- For upgrades, leave the new tempate as disabled by default.
update redcap_projects_templates set enabled = 0 where project_id = @project_id;
";

// Other back-end changes
print "-- Remove unnecessary table column
ALTER TABLE  `redcap_user_roles` DROP  `role_description_lang_var`;
-- Add indexes for user_information
ALTER TABLE  `redcap_user_information` ADD INDEX (  `user_firstname` );
ALTER TABLE  `redcap_user_information` ADD INDEX (  `user_lastname` );
-- Decrease window of time to check and delete temp files
UPDATE `redcap_crons` SET  `cron_frequency` =  '120' WHERE cron_name = 'RemoveTempAndDeletedFiles';
-- Add system offline custom message
insert into redcap_config values ('system_offline_message', '');

set foreign_key_checks=0;
-- Table structure for table 'redcap_user_roles'
DROP TABLE IF EXISTS redcap_user_roles;
CREATE TABLE IF NOT EXISTS redcap_user_roles (
  role_id int(10) NOT NULL AUTO_INCREMENT,
  project_id int(10) DEFAULT NULL,
  role_name varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Name of user role',
  lock_record int(1) NOT NULL DEFAULT '0',
  lock_record_multiform int(1) NOT NULL DEFAULT '0',
  lock_record_customize int(1) NOT NULL DEFAULT '0',
  data_export_tool int(1) NOT NULL DEFAULT '1',
  data_import_tool int(1) NOT NULL DEFAULT '1',
  data_comparison_tool int(1) NOT NULL DEFAULT '1',
  data_logging int(1) NOT NULL DEFAULT '1',
  file_repository int(1) NOT NULL DEFAULT '1',
  double_data int(1) NOT NULL DEFAULT '0',
  user_rights int(1) NOT NULL DEFAULT '1',
  data_access_groups int(1) NOT NULL DEFAULT '1',
  graphical int(1) NOT NULL DEFAULT '1',
  reports int(1) NOT NULL DEFAULT '1',
  design int(1) NOT NULL DEFAULT '0',
  calendar int(1) NOT NULL DEFAULT '1',
  data_entry text COLLATE utf8_unicode_ci,
  api_export int(1) NOT NULL DEFAULT '0',
  api_import int(1) NOT NULL DEFAULT '0',
  record_create int(1) NOT NULL DEFAULT '1',
  record_rename int(1) NOT NULL DEFAULT '0',
  record_delete int(1) NOT NULL DEFAULT '0',
  dts int(1) NOT NULL DEFAULT '0' COMMENT 'DTS adjudication page',
  participants int(1) NOT NULL DEFAULT '1',
  data_quality_design int(1) NOT NULL DEFAULT '0',
  data_quality_execute int(1) NOT NULL DEFAULT '0',
  data_quality_resolution int(1) NOT NULL DEFAULT '0' COMMENT '0=No access, 1=View only, 2=Respond, 3=Open, close, respond',
  random_setup int(1) NOT NULL DEFAULT '0',
  random_dashboard int(1) NOT NULL DEFAULT '0',
  random_perform int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (role_id),
  KEY project_id (project_id)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
-- Constraints for table `redcap_user_roles`
ALTER TABLE `redcap_user_roles`
  ADD CONSTRAINT redcap_user_roles_ibfk_1 FOREIGN KEY (project_id) REFERENCES redcap_projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE;
set foreign_key_checks=1;
";