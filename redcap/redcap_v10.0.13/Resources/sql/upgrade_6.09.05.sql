-- Add new user-level API token
ALTER TABLE `redcap_user_information` ADD `api_token` VARCHAR(64) NULL DEFAULT NULL , ADD UNIQUE (`api_token`) ;