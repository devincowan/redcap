RENAME TABLE `redcap_user_whitelist` TO `redcap_user_allowlist`;
update redcap_config set field_name = 'alerts_email_freeform_domain_allowlist' where field_name = 'alerts_email_freeform_domain_whitelist';
update redcap_config set field_name = 'email_domain_allowlist' where field_name = 'email_domain_whitelist';
update redcap_config set field_name = 'enable_user_allowlist' where field_name = 'enable_user_whitelist';