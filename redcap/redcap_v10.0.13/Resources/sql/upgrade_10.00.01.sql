-- placeholders for break the glass settings
-- status of the break the glass feature; can be 'enabled', 'access_token', 'username_token'
insert into redcap_config values ('fhir_break_the_glass_enabled', '');
-- The type of the EHR user that REDCap has mapped to the current user in the EHR launch process.
insert into redcap_config values ('fhir_break_the_glass_ehr_usertype', 'SystemLogin');

-- FOLLOWING SETTINGS ARE SPECIFIC TO THE 'USERNAME_TOKEN' TYPE OF ACCESS
-- type of user used in the username_token; 'EMP', 'Local' or 'Windows'
insert into redcap_config values ('fhir_break_the_glass_token_usertype', 'EMP');
-- username_token username
insert into redcap_config values ('fhir_break_the_glass_token_username', '');
-- username_token password
insert into redcap_config values ('fhir_break_the_glass_token_password', '');
-- base URL for the username_token endpoint. It differs from the FHIR base url
insert into redcap_config values ('fhir_break_the_glass_username_token_base_url', '');
-- add project-level option
ALTER TABLE `redcap_projects`
	ADD `break_the_glass_enabled` tinyint(1)
	NOT NULL
	DEFAULT '0'
	COMMENT 'Are users allowed to use the Epic feature Break-the-Glass feature?'
	AFTER `datamart_enabled`;