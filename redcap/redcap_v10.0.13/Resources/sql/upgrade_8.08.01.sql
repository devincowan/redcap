INSERT INTO redcap_config (field_name, value) VALUES
('homepage_announcement_login', '1');
INSERT INTO redcap_config (field_name, value) VALUES
('user_messaging_prevent_admin_messaging', '0');
ALTER TABLE `redcap_projects` ADD `pdf_hide_secondary_field` TINYINT(1) NOT NULL DEFAULT '0' AFTER `pdf_show_logo_url`;
delete from redcap_record_counts;