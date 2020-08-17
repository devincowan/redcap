-- Set CATs server URL
UPDATE `redcap_config` SET `value` = 'https://www.redcap-cats.org/promis_api/' WHERE `field_name` = 'promis_api_base_url';
-- Modify tables for upcoming reports functionality
ALTER TABLE `redcap_reports`
  DROP `preset_export_format`,
  DROP `preset_archive_files`,
  DROP `preset_export_dags`,
  DROP `preset_export_survey_fields`,
  DROP `preset_remove_identifiers`,
  DROP `preset_hash_recordid`,
  DROP `preset_remove_unval_text_fields`,
  DROP `preset_remove_notes_fields`,
  DROP `preset_remove_date_fields`,
  DROP `preset_shift_dates`,
  DROP `preset_shift_survey_timestamps`;
ALTER TABLE  `redcap_reports_fields` CHANGE  `limiter_operator`  `limiter_operator`
	ENUM(  'E',  'NE',  'GT',  'GTE',  'LT',  'LTE',  'CHECKED',  'UNCHECKED',  'CONTAINS',  'NOT_CONTAIN',  'STARTS_WITH',  'ENDS_WITH' )
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE  `redcap_reports` ADD  `orderby_field3` VARCHAR( 100 ) NULL DEFAULT NULL ,
	ADD  `orderby_sort3` ENUM(  'ASC',  'DESC' ) NULL DEFAULT NULL;
ALTER TABLE  `redcap_reports` ADD  `output_dags` INT( 1 ) NOT NULL AFTER  `user_access` ,
	ADD  `output_survey_fields` INT( 1 ) NOT NULL AFTER  `output_dags`;
-- Disable the rApache plot service, if enabled
update redcap_config set value = '2' where field_name = 'enable_plotting' and value != '0';