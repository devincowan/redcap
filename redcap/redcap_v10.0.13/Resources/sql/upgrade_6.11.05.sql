-- Fix validation issue with date and datetime fields
UPDATE redcap_validation_types SET
	regex_js = replace(regex_js, '([-\\/.]?)', '([-\\/])'),
	regex_php = replace(regex_php, '([-\\/.]?)', '([-\\/])')
	WHERE data_type IN ('date', 'datetime', 'datetime_seconds');
-- Add new config setting
INSERT INTO redcap_config (field_name, value) VALUES ('cross_domain_access_control', '');