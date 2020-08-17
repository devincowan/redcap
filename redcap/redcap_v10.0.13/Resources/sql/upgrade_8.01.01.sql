-- Add @READONLY action tag to all instances of @TODAY and @NOW
update redcap_metadata_archive set misc = replace(replace(misc, '@TODAY', '@TODAY @READONLY'), '@NOW', '@NOW @READONLY') where (misc like '%@TODAY%' or misc like '%@NOW%');
update redcap_metadata_temp set misc = replace(replace(misc, '@TODAY', '@TODAY @READONLY'), '@NOW', '@NOW @READONLY') where (misc like '%@TODAY%' or misc like '%@NOW%');
update redcap_metadata set misc = replace(replace(misc, '@TODAY', '@TODAY @READONLY'), '@NOW', '@NOW @READONLY') where (misc like '%@TODAY%' or misc like '%@NOW%');
-- Increase size of ExtMods table field
ALTER TABLE `redcap_external_module_settings` CHANGE `value` `value` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;