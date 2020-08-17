-- Add column for upcoming feature
ALTER TABLE  `redcap_data_quality_status`
	ADD  `assigned_user_id` INT( 10 ) NULL COMMENT  'UI ID of user assigned to query',
	ADD INDEX (  `assigned_user_id` );
ALTER TABLE  `redcap_data_quality_status` ADD FOREIGN KEY (  `assigned_user_id` )
	REFERENCES  `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE ;
-- Modify column for upcoming feature
ALTER TABLE  `redcap_data_quality_resolutions` CHANGE  `response` `response`
	ENUM( 'DATA_MISSING', 'TYPOGRAPHICAL_ERROR', 'CONFIRMED_CORRECT', 'WRONG_SOURCE', 'OTHER' )
	CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Response category if user responded to query';
ALTER TABLE  `redcap_data_quality_status` CHANGE  `query_status`  `query_status`
	ENUM(  'OPEN',  'CLOSED',  'VERIFIED',  'DEVERIFIED' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT  'Status of data query';
ALTER TABLE  `redcap_data_quality_resolutions` CHANGE  `current_query_status`  `current_query_status`
	ENUM(  'OPEN',  'CLOSED',  'VERIFIED',  'DEVERIFIED' ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Current query status of thread';
-- Add new upload max category (copy value from edoc file upload max)
insert into redcap_config select 'file_attachment_upload_max', value from redcap_config where field_name = 'edoc_upload_max';