
CREATE TABLE `redcap_todo_list` (
`request_id` int(11) NOT NULL AUTO_INCREMENT,
`request_from` int(11) DEFAULT NULL,
`request_to` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`todo_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
`action_url` text COLLATE utf8_unicode_ci,
`status` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
`request_time` datetime DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`request_completion_time` datetime DEFAULT NULL,
`request_completion_userid` int(11) DEFAULT NULL,
`comment` text COLLATE utf8_unicode_ci,
PRIMARY KEY (`request_id`),
KEY `project_id` (`project_id`),
KEY `request_completion_userid` (`request_completion_userid`),
KEY `request_from` (`request_from`),
KEY `request_time` (`request_time`),
KEY `status` (`status`),
KEY `todo_type` (`todo_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_todo_list`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`request_completion_userid`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL,
ADD FOREIGN KEY (`request_from`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;

delete from redcap_config where field_name in ('project_contact_prod_changes_email', 'project_contact_prod_changes_name');
ALTER TABLE `redcap_projects`
  DROP `project_contact_prod_changes_name`,
  DROP `project_contact_prod_changes_email`;

-- Add new instance column to tables
ALTER TABLE  `redcap_locking_data` 
	ADD `instance` SMALLINT(4) NOT NULL DEFAULT '1' AFTER  `form_name`,
	drop index proj_rec_event_form,
	ADD UNIQUE KEY `proj_rec_event_form_instance` (`project_id`,`record`,`event_id`,`form_name`,`instance`);
ALTER TABLE  `redcap_esignatures` 
	ADD `instance` SMALLINT(4) NOT NULL DEFAULT '1' AFTER  `form_name`,
	drop index proj_rec_event_form,
	ADD UNIQUE KEY `proj_rec_event_form_instance` (`project_id`,`record`,`event_id`,`form_name`,`instance`);	
ALTER TABLE `redcap_surveys_response_values` 
	ADD `instance` SMALLINT(4) NULL DEFAULT NULL AFTER `value`,
	DROP INDEX `event_id`,
	ADD KEY `event_id_instance` (`event_id`,`instance`);
ALTER TABLE `redcap_surveys_scheduler_queue` 
	ADD `instance` SMALLINT(4) NOT NULL DEFAULT '1' AFTER `record`,
	DROP INDEX `email_recip_id_record`,
	ADD UNIQUE KEY `email_recip_id_record` (`email_recip_id`,`record`,`reminder_num`,`instance`),
	DROP INDEX `ss_id_record`,
	ADD UNIQUE KEY `ss_id_record` (`ss_id`,`record`,`reminder_num`,`instance`);
ALTER TABLE `redcap_surveys_response` 
	ADD `instance` SMALLINT(4) NOT NULL DEFAULT '1' AFTER `record`,
	DROP INDEX `participant_record`,
	ADD UNIQUE KEY `participant_record` (`participant_id`,`record`,`instance`),
	DROP INDEX `record_participant`,
	ADD KEY `record_participant` (`record`,`participant_id`,`instance`);
ALTER TABLE `redcap_data_quality_status` 
	ADD `instance` SMALLINT(4) NOT NULL DEFAULT '1' AFTER `field_name`,
	DROP INDEX `nonrule_proj_record_event_field`,
	ADD UNIQUE KEY `nonrule_proj_record_event_field` (`non_rule`,`project_id`,`record`,`event_id`,`field_name`,`instance`),
	DROP INDEX `pd_rule_proj_record_event_field`,
	ADD UNIQUE KEY `pd_rule_proj_record_event_field` (`pd_rule_id`,`record`,`event_id`,`field_name`,`project_id`,`instance`),
	DROP INDEX `rule_record_event`,
	ADD UNIQUE KEY `rule_record_event` (`rule_id`,`record`,`event_id`,`instance`),
	DROP INDEX `pd_rule_proj_record_event`,
	ADD KEY `pd_rule_proj_record_event` (`pd_rule_id`,`record`,`event_id`,`project_id`,`instance`);
