CREATE TABLE `redcap_external_modules_downloads` (
`module_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
`time_downloaded` datetime DEFAULT NULL,
PRIMARY KEY (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Modules downloaded from the external modules repository';
ALTER TABLE `redcap_external_modules_downloads` ADD `module_id` INT(11) NULL AFTER `module_name`, ADD UNIQUE `module_id` (`module_id`);
ALTER TABLE `redcap_external_modules_downloads` ADD `time_deleted` DATETIME NULL DEFAULT NULL AFTER `time_downloaded`;
ALTER TABLE `redcap_ehr_access_tokens` 
	DROP INDEX `token_owner_patient`,
	DROP INDEX `access_token`,
	DROP INDEX `mrn`;
ALTER TABLE `redcap_ehr_access_tokens`
	ADD UNIQUE KEY `token_owner_patient` (`token_owner`,`patient`),
	ADD KEY `access_token` (`access_token`(255)),
	ADD KEY `mrn` (`mrn`);