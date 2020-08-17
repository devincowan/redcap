-- New e-Consent setting
ALTER TABLE `redcap_surveys` ADD `pdf_econsent_allow_edit` TINYINT(1) NOT NULL DEFAULT '0' AFTER `pdf_econsent_dob_event_id`;