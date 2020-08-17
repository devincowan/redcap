-- Increase session data size in table
ALTER TABLE  `redcap_sessions` CHANGE  `session_data`  `session_data` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
-- Add PROMIS settings for future use
INSERT INTO  `redcap_config` VALUES ('promis_api_base_url', 'https://www.assessmentcenter.net/ac_api/');
INSERT INTO  `redcap_config` VALUES ('promis_enabled', '1');
ALTER TABLE  `redcap_surveys` ADD  `promis_skip_question` INT( 1 ) NOT NULL DEFAULT  '0'
	COMMENT  'Allow participants to skip questions on PROMIS CATs';