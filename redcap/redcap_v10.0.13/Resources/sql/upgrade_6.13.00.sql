-- Remove unnecessary fields
ALTER TABLE `redcap_projects`
  DROP `mobile_project`,
  DROP `mobile_project_export_flag`;
-- Add new system setting
INSERT INTO redcap_config (field_name, value) VALUES
('display_project_xml_backup_option', '1');
-- Change table defaults for better MySQL compatibility
ALTER TABLE `redcap_history_size` CHANGE `date` `date` DATE NOT NULL DEFAULT '1000-01-01';
ALTER TABLE `redcap_history_version` CHANGE `date` `date` DATE NOT NULL DEFAULT '1000-01-01';