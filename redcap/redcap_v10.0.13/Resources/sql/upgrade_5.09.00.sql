-- Set config values for international date/number/time abstraction
insert into redcap_config values ('default_number_format_decimal', '.');
insert into redcap_config values ('default_number_format_thousands_sep', ',');
-- ALTER TABLE  `redcap_user_information` ADD  `number_format_decimal` ENUM(  '.',  ',' ) NOT NULL DEFAULT  '.' COMMENT  'User''s preferred decimal format';
-- ALTER TABLE  `redcap_user_information` ADD  `number_format_thousands_sep` ENUM( ',', '.', '', 'SPACE', '\'' ) NOT NULL DEFAULT  ',' COMMENT  'User''s preferred thousands separator';
-- Add project encoding setting
insert into redcap_config values ('project_encoding', '');
ALTER TABLE  `redcap_projects` ADD  `project_encoding` ENUM(  'japanese_sjis',  'chinese_utf8' ) NULL DEFAULT NULL
	COMMENT  'Encoding to be used for various exported files' AFTER  `project_language`;
-- Set project encoding value for projects using Japanese or Chinese language files
update redcap_projects set project_encoding = if (left(project_language,8) = 'japanese', 'japanese_sjis', if (left(project_language,7) = 'chinese', 'chinese_utf8', null) )
	where project_language like 'japanese%' or project_language like 'chinese%';
-- Modify validation_types table slightly
update redcap_validation_types set data_type = 'text' where data_type
	not in ('date', 'datetime', 'datetime_seconds', 'email', 'integer', 'mrn', 'number', 'number_comma_decimal', 'phone', 'postal_code', 'ssn', 'text', 'time', 'char');
ALTER TABLE  `redcap_validation_types` CHANGE  `data_type`  `data_type`
	ENUM(  'date',  'datetime',  'datetime_seconds',  'email',  'integer',  'mrn',  'number', 'number_comma_decimal', 'phone',  'postal_code',  'ssn',  'text',  'time',  'char' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
-- New validation types
INSERT INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `legacy_value`, `visible`) VALUES
('number_comma_decimal', 'Number (comma as decimal)', '/^[-+]?[0-9]*,?[0-9]+([eE][-+]?[0-9]+)?$/', '/^[-+]?[0-9]*,?[0-9]+([eE][-+]?[0-9]+)?$/', 'number_comma_decimal', NULL, 0),
('number_1dp_comma_decimal',  'Number (1 decimal place - comma as decimal)',  '/^-?\\d+,\\d$/',  '/^-?\\d+,\\d$/',  'number_comma_decimal', NULL ,  '0'),
('number_2dp_comma_decimal',  'Number (2 decimal places - comma as decimal)',  '/^-?\\d+,\\d{2}$/',  '/^-?\\d+,\\d{2}$/',  'number_comma_decimal', NULL ,  '0'),
('number_3dp_comma_decimal',  'Number (3 decimal places - comma as decimal)',  '/^-?\\d+,\\d{3}$/',  '/^-?\\d+,\\d{3}$/',  'number_comma_decimal', NULL ,  '0'),
('number_4dp_comma_decimal',  'Number (4 decimal places - comma as decimal)',  '/^-?\\d+,\\d{4}$/',  '/^-?\\d+,\\d{4}$/',  'number_comma_decimal', NULL ,  '0');
-- Modify survey notifications to allow secondary and tertiary addresses
update redcap_actions set action_response = null where action_response = 'EMAIL';
ALTER TABLE  `redcap_actions` CHANGE  `action_response`  `action_response` ENUM(  'NONE',  'EMAIL_PRIMARY',  'EMAIL_SECONDARY',  'EMAIL_TERTIARY',  'STOPSURVEY',  'PROMPT' )
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
update redcap_actions set action_response = 'EMAIL_PRIMARY' where action_response is null;
-- Re-add default_datetime_format to config table if didn't get added in 5.8.2
delete from redcap_config where field_name = 'default_datetime_format';
insert into redcap_config values ('default_datetime_format', 'M/D/Y_12');