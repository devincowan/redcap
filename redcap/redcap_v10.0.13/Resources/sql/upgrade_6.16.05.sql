ALTER TABLE `redcap_user_information` 
	ADD `messaging_email_preference` ENUM('NONE','X_HOURS','DAILY','ALL') NOT NULL DEFAULT 'X_HOURS' AFTER `api_token`, 
	ADD `messaging_email_urgent_all` TINYINT(1) NOT NULL DEFAULT '1' AFTER `messaging_email_preference`;