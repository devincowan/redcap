-- Modify tables for upcoming Web Services feature
ALTER TABLE  `redcap_web_service_mapping` CHANGE  `external_source_field_name`  `external_source_field_name`
	VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Unique name of field mapped from external data source';
ALTER TABLE  `redcap_projects`
	ADD  `realtime_webservice_offset_days` INT( 3 ) NOT NULL DEFAULT  '1' COMMENT  'Default value of days offset',
	ADD  `realtime_webservice_offset_plusminus` ENUM(  '+',  '-',  '+-' ) NOT NULL DEFAULT  '+-'
	COMMENT  'Default value of plus-minus range for days offset';
ALTER TABLE  `redcap_web_service_mapping` ADD  `preselect` ENUM(  'MIN',  'MAX',  'FIRST',  'LAST' )
	NULL DEFAULT NULL COMMENT  'Preselect a source value for temporal fields only';