-- Add new Home Page option
insert into redcap_config values ('homepage_contact_url', '');
-- Add new proxy option
insert into redcap_config values ('proxy_username_password', '');
-- Add new indexes
ALTER TABLE `redcap_user_information`
	ADD INDEX(`user_sponsor`), ADD INDEX(`user_inst_id`), ADD INDEX `user_comments` (`user_comments`(255));