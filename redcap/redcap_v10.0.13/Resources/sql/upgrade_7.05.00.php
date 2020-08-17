<?php

// Add REDCap Messenger system notification
$title = "New survey feature";
$msg = "You may now allow your survey participants to download a PDF of their responses after they have completed a survey. This is a great way to provide participants with an electronic copy of their survey responses. To enable this setting for a survey, you will find it halfway down the Survey Settings page in the Online Designer. If enabled, a download button will appear below the survey's acknowledgement text after the participant has completed the survey. For more details, see the Survey Settings page.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);

// Other SQL updates
?>
INSERT INTO redcap_config (field_name, value) VALUES ('fhir_ddp_enabled', '0');
ALTER TABLE `redcap_projects` ADD `realtime_webservice_type` ENUM('CUSTOM','FHIR') NOT NULL DEFAULT 'CUSTOM' AFTER `realtime_webservice_enabled`;
ALTER TABLE `redcap_ehr_access_tokens` ADD `refresh_token` TEXT NULL DEFAULT NULL AFTER `access_token`;
ALTER TABLE `redcap_ehr_access_tokens` ADD `mrn` VARCHAR(255) NULL DEFAULT NULL COMMENT 'If different from patient id' AFTER `patient`;
ALTER TABLE `redcap_ehr_access_tokens` ADD UNIQUE KEY `mrn` (`mrn`);
ALTER TABLE `redcap_ehr_access_tokens` ADD KEY `token` (`access_token`(255));