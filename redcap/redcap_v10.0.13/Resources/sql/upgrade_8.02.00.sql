INSERT INTO redcap_config (field_name, value) VALUES
('user_sponsor_dashboard_enable', '1');
INSERT INTO redcap_config (field_name, value) VALUES
('user_sponsor_set_expiration_days', '365');
ALTER TABLE `redcap_surveys_scheduler` CHANGE `condition_send_time_lag_days` `condition_send_time_lag_days` INT(4) NULL DEFAULT NULL COMMENT 'Wait X days to send invites after condition is met';