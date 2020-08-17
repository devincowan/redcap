-- Add new column to DQ status table
ALTER TABLE  `redcap_data_quality_status` CHANGE  `rule_id`  `rule_id` INT( 10 ) NULL DEFAULT NULL COMMENT  'FK from data_quality_rules table';
ALTER TABLE  `redcap_data_quality_status` CHANGE  `status`  `status` INT( 2 ) NULL DEFAULT NULL COMMENT  'Current status of discrepancy';
ALTER TABLE  `redcap_data_quality_status` ADD  `non_rule` INT( 1 ) NULL COMMENT  '1 for non-rule, else NULL' AFTER  `pd_rule_id`;
ALTER TABLE  `redcap_data_quality_status` ADD  `query_status` ENUM(  'OPEN',  'CLOSED' ) NULL COMMENT  'Status of data query';
ALTER TABLE  `redcap_data_quality_status` ADD INDEX  `project_query_status` (  `project_id` ,  `query_status` );
ALTER TABLE  `redcap_data_quality_status` ADD UNIQUE  `nonrule_proj_record_event_field` (  `non_rule` ,  `project_id` ,  `record` ,  `event_id` ,  `field_name` );
-- Add new DQ table
DROP TABLE IF EXISTS redcap_data_quality_resolutions;
CREATE TABLE redcap_data_quality_resolutions (
  res_id int(10) NOT NULL AUTO_INCREMENT,
  status_id int(10) DEFAULT NULL COMMENT 'FK from data_quality_status',
  ts datetime DEFAULT NULL COMMENT 'Date/time added',
  user_id int(10) DEFAULT NULL COMMENT 'Current user',
  response_requested int(1) NOT NULL DEFAULT '0' COMMENT 'Is a response requested?',
  response enum('OTHER','CONFIRMED','TYPOGRAPHICAL_ERROR','REQUIRED_UPDATE') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Did current user respond to request?',
  `comment` text COLLATE utf8_unicode_ci COMMENT 'Text for comment',
  current_query_status enum('OPEN','CLOSED') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Current query status of thread',
  upload_doc_id int(10) DEFAULT NULL COMMENT 'FK of uploaded document',
  PRIMARY KEY (res_id),
  KEY doc_id (upload_doc_id),
  KEY status_id (status_id),
  KEY user_id (user_id)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_data_quality_resolutions`
  ADD CONSTRAINT redcap_data_quality_resolutions_ibfk_1 FOREIGN KEY (status_id) REFERENCES redcap_data_quality_status (status_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_data_quality_resolutions_ibfk_2 FOREIGN KEY (user_id) REFERENCES redcap_user_information (ui_id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_data_quality_resolutions_ibfk_3 FOREIGN KEY (upload_doc_id) REFERENCES redcap_edocs_metadata (doc_id) ON DELETE SET NULL ON UPDATE CASCADE;
-- Drop unnecessary DQ table
Drop table if exists redcap_data_quality_changelog;
-- Reset any password security answers that were entered as blank because of being multi-byte string
update redcap_auth set password_answer = null, password_question_reminder = null, password_question = null
	where password_answer = 'd41d8cd98f00b204e9800998ecf8427e';
-- Add new column to redcap_projects for upcoming feature
ALTER TABLE  `redcap_projects` ADD  `data_resolution_enabled` INT( 1 ) NOT NULL DEFAULT  '1' COMMENT  'Enable data queries functionality';
-- Add new column to user_rights for upcoming feature
ALTER TABLE  `redcap_user_rights` ADD  `data_quality_resolution` INT( 1 ) NOT NULL DEFAULT  '1'
	COMMENT  '0=None, 1=Comments only, 2=DQ Resolution' AFTER  `data_quality_execute`;
-- Change file_extension size for uploaded files
ALTER TABLE  `redcap_edocs_metadata` CHANGE  `file_extension`  `file_extension` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
-- Fix mistaken timestamp if user has a user_lastactivity that occurs after their suspended time
update redcap_user_information x, (SELECT i.username, timestamp(max(e.ts)) as user_lastactivity
	FROM redcap_user_information i, redcap_log_event e WHERE i.user_suspended_time is not null and i.user_lastactivity is not null
	and i.user_suspended_time < i.user_lastactivity and e.user = i.username and e.ts < i.user_suspended_time*1 group by i.username) y
	set x.user_lastactivity = y.user_lastactivity where x.username = y.username;