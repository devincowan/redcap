<?php

$sql = "
ALTER TABLE  `redcap_projects`
    ADD `datamart_allow_repeat_revision` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If true, a normal user can run a revision multiple times',
    ADD `datamart_allow_create_revision` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If true, a normal user can request a new revision';

CREATE TABLE `redcap_ehr_datamart_revisions` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`project_id` int(11) DEFAULT NULL,
`request_id` int(11) DEFAULT NULL,
`user_id` int(11) DEFAULT NULL,
`mrns` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`date_min` date DEFAULT NULL,
`date_max` date DEFAULT NULL,
`fields` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`approved` tinyint(1) NOT NULL DEFAULT '0',
`is_deleted` tinyint(1) NOT NULL DEFAULT '0',
`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
`executed_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `request_id` (`request_id`),
KEY `project_id` (`project_id`),
KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_ehr_datamart_revisions`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`request_id`) REFERENCES `redcap_todo_list` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_surveys`
  ADD `pdf_econsent_signature_field1` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_allow_edit`,
  ADD `pdf_econsent_signature_field2` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_signature_field1`,
  ADD `pdf_econsent_signature_field3` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_signature_field2`,
  ADD `pdf_econsent_signature_field4` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_signature_field3`,
  ADD `pdf_econsent_signature_field5` VARCHAR(100) NULL DEFAULT NULL AFTER `pdf_econsent_signature_field4`;

INSERT INTO `redcap_config` (`field_name`, `value`) VALUES ('pdf_econsent_system_custom_text', '');
ALTER TABLE `redcap_projects`
  DROP `realtime_webservice_datamart_info`,
  ADD `datamart_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is project a Clinical Data Mart project?';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);