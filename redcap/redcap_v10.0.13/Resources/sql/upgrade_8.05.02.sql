INSERT INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `legacy_value`, `visible`) 
VALUES ('postalcode_french', 'Code Postal 5 caracteres (France)', '/^((0?[1-9])|([1-8][0-9])|(9[0-8]))[0-9]{3}$/', '/^((0?[1-9])|([1-8][0-9])|(9[0-8]))[0-9]{3}$/', 'postal_code', NULL, '0');
INSERT INTO redcap_config (field_name, value) VALUES
('realtime_webservice_convert_timestamp_from_gmt', '0'),
('fhir_convert_timestamp_from_gmt', '0');