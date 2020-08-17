CREATE TABLE `redcap_ehr_user_map` (
`ehr_username` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`redcap_userid` int(11) DEFAULT NULL,
UNIQUE KEY `ehr_username` (`ehr_username`),
UNIQUE KEY `redcap_userid` (`redcap_userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_ehr_user_map`
ADD FOREIGN KEY (`redcap_userid`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
INSERT INTO redcap_config (field_name, value) VALUES
('fhir_ehr_type', ''),
('fhir_ehr_mrn_identifier', '');
CREATE TABLE `redcap_ehr_access_tokens` (
`patient` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`expiration` datetime DEFAULT NULL,
`access_token` text COLLATE utf8_unicode_ci,
UNIQUE KEY `patient` (`patient`),
KEY `expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
INSERT INTO redcap_config (field_name, value) VALUES
('fhir_endpoint_authorize_url', ''),
('fhir_endpoint_token_url', '');