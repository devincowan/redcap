DROP TABLE IF EXISTS `redcap_ehr_access_tokens`;
CREATE TABLE `redcap_ehr_access_tokens` (
`patient` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`mrn` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'If different from patient id',
`token_owner` int(11) DEFAULT NULL COMMENT 'REDCap User ID',
`expiration` datetime DEFAULT NULL,
`access_token` text COLLATE utf8_unicode_ci,
`refresh_token` text COLLATE utf8_unicode_ci,
UNIQUE KEY `token_owner_mrn` (`token_owner`,`mrn`),
UNIQUE KEY `token_owner_patient` (`token_owner`,`patient`),
KEY `access_token` (`access_token`(255)),
KEY `expiration` (`expiration`),
KEY `mrn` (`mrn`),
KEY `patient` (`patient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_ehr_access_tokens`
ADD FOREIGN KEY (`token_owner`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;