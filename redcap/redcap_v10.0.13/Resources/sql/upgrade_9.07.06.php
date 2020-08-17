<?php

$sql = "
RENAME TABLE `redcap_ehr_datamart_counts` TO `redcap_ehr_import_counts`;
ALTER TABLE `redcap_ehr_import_counts` ADD `counts_Observation` MEDIUMINT(7) NULL DEFAULT NULL AFTER `counts_Patient`;
ALTER TABLE `redcap_ehr_import_counts` ADD `type` ENUM('CDP','CDM') NOT NULL DEFAULT 'CDP' AFTER `ts`;
ALTER TABLE `redcap_ehr_import_counts` ADD UNIQUE `type_project_record` (`type`, `project_id`, `record`);
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);