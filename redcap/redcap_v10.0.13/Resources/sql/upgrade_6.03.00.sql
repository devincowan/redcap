-- Fixes and additions for DDP
ALTER TABLE  `redcap_projects` CHANGE  `realtime_webservice_offset_days`  `realtime_webservice_offset_days`
	FLOAT( 3 ) NOT NULL DEFAULT  '1' COMMENT  'Default value of days offset';
ALTER TABLE  `redcap_ddp_mapping` CHANGE  `preselect`  `preselect` ENUM(  'MIN',  'MAX',  'FIRST',  'LAST',  'NEAR' )
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Preselect a source value for temporal fields only';