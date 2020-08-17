-- Add placeholders for upcoming feature
INSERT INTO redcap_config VALUES ('promis_registration_id', '');
INSERT INTO redcap_config VALUES ('promis_token', '');
-- Allow users to edit/delete Field Comments
ALTER TABLE  `redcap_projects` ADD  `field_comment_edit_delete` INT( 1 ) NOT NULL DEFAULT  '1'
	COMMENT  'Allow users to edit or delete Field Comments' AFTER  `data_resolution_enabled`;
ALTER TABLE  `redcap_data_quality_resolutions` ADD  `field_comment_edited` INT( 1 ) NOT NULL DEFAULT  '0' COMMENT  'Denote if field comment was edited';
-- Rename a config setting
update redcap_config set field_name = 'hook_functions_file' where field_name = 'custom_functions_file';
-- Add a config setting
INSERT INTO redcap_config VALUES ('sams_logout', '');
-- Alter user rights table comment
ALTER TABLE  `redcap_user_rights` CHANGE  `data_quality_resolution`  `data_quality_resolution` INT( 1 )
	NOT NULL DEFAULT  '0' COMMENT  '0=No access, 1=View only, 2=Respond, 3=Open, close, respond, 4=Open only, 5=Open and respond';