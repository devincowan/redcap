<?php

$sql = "
ALTER TABLE `redcap_surveys_emails` ADD `email_sender_display` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Email sender display name' AFTER `email_sender`;
ALTER TABLE `redcap_surveys_scheduler` ADD `email_sender_display` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Email sender display name' AFTER `email_sender`;
ALTER TABLE `redcap_surveys` ADD `confirmation_email_from_display` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Email sender display name' AFTER `confirmation_email_from`;
ALTER TABLE `redcap_alerts` ADD `email_from_display` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Email sender display name' AFTER `email_from`;
INSERT INTO redcap_config (field_name, value) VALUES ('from_email_domain_exclude', '');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);