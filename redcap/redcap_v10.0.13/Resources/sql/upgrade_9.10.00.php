<?php

$sql = "";

$sql .= "
ALTER TABLE `redcap_projects` ADD `record_locking_pdf_vault_enabled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `missing_data_codes`;
ALTER TABLE `redcap_projects` ADD `record_locking_pdf_vault_custom_text` TEXT NULL DEFAULT NULL AFTER `record_locking_pdf_vault_enabled`;

INSERT INTO redcap_config (field_name, value) VALUES
('record_locking_pdf_vault_filesystem_type', ''),
('record_locking_pdf_vault_filesystem_host', ''),
('record_locking_pdf_vault_filesystem_username', ''),
('record_locking_pdf_vault_filesystem_password', ''),
('record_locking_pdf_vault_filesystem_path', ''),
('record_locking_pdf_vault_filesystem_private_key_path', '');

CREATE TABLE `redcap_locking_records` (
`lr_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`arm_id` int(10) NOT NULL,
`username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`timestamp` datetime DEFAULT NULL,
PRIMARY KEY (`lr_id`),
UNIQUE KEY `arm_id_record` (`arm_id`,`record`),
KEY `project_record` (`project_id`,`record`),
KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_locking_records`
ADD FOREIGN KEY (`arm_id`) REFERENCES `redcap_events_arms` (`arm_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_locking_records_pdf_archive` (
`doc_id` int(10) DEFAULT NULL,
`project_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`arm_id` int(10) NOT NULL,
UNIQUE KEY `doc_id` (`doc_id`),
KEY `arm_id_record` (`arm_id`,`record`),
KEY `project_record` (`project_id`,`record`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_locking_records_pdf_archive`
ADD FOREIGN KEY (`doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`arm_id`) REFERENCES `redcap_events_arms` (`arm_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);

// Add Messenger system notification
$title = "New feature: Lock entire records";
$msg = "While records have always been able to be locked (i.e., made read-only) for individual data collection instruments in a project, you may now easily lock an ENTIRE record so that no data in the record can ever be modified while it is locked.

WHAT HAS CHANGED? It is important to note that the old user privilege \"Lock all forms\" has now been converted into the new record-level locking feature, which works completely independently from instrument-level locking (i.e., the checkbox at the bottom of data entry forms). Instead of that particular user privilege allowing you to lock all forms individually (which was the previous behavior), it will now serve in a slightly different capacity as the record-level locking user privilege to lock an entire record fully.

HOW TO USE IT: You may lock an entire record via the \"choose action for record\" drop-down on the Record Home Page or by clicking the \"Lock Entire Record\" link on the project's left-hand menu when viewing a record. Note: Since the record locking and instrument locking are completely separate features, they both may be used together in a project, if you wish. However, please note that since record locking is a higher-level locking than instrument locking, an entire record may be locked or unlocked while one or more instruments are currently locked, but an instrument cannot be locked or unlocked while the entire record is locked. ENJOY!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);