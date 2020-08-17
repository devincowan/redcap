ALTER TABLE `redcap_surveys_pdf_archive` 
	ADD `identifier` VARCHAR(255) NULL DEFAULT NULL AFTER `instance`, 
	ADD `version` VARCHAR(64) NULL DEFAULT NULL AFTER `identifier`, 
	ADD `type` VARCHAR(64) NULL DEFAULT NULL AFTER `version`;
ALTER TABLE `redcap_projects`
  DROP `pdf_econsent_filesystem_type`,
  DROP `pdf_econsent_filesystem_host`,
  DROP `pdf_econsent_filesystem_username`,
  DROP `pdf_econsent_filesystem_password`,
  DROP `pdf_econsent_filesystem_path`,
  DROP `pdf_econsent_filesystem_port`;
INSERT INTO redcap_config (field_name, value) VALUES
('pdf_econsent_filesystem_type', ''),
('pdf_econsent_filesystem_host', ''),
('pdf_econsent_filesystem_username', ''),
('pdf_econsent_filesystem_password', ''),
('pdf_econsent_filesystem_path', ''),
('pdf_econsent_filesystem_private_key_path', ''),
('pdf_econsent_system_enabled', '1');