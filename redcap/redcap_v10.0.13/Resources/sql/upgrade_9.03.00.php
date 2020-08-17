<?php

$sql = "
INSERT INTO redcap_config (field_name, value) VALUES
('file_upload_vault_filesystem_type', ''),
('file_upload_vault_filesystem_host', ''),
('file_upload_vault_filesystem_username', ''),
('file_upload_vault_filesystem_password', ''),
('file_upload_vault_filesystem_path', ''),
('file_upload_vault_filesystem_private_key_path', ''),
('file_upload_versioning_enabled', '1'),
('file_upload_versioning_global_enabled', '1');
ALTER TABLE `redcap_projects` ADD `file_upload_vault_enabled` TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE `redcap_projects` ADD `file_upload_versioning_enabled` TINYINT(1) NOT NULL DEFAULT '1';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);