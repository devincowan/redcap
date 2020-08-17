<?php

$sql = "
ALTER TABLE `redcap_ehr_import_counts` 
    DROP INDEX `type_project_record`, 
    ADD INDEX `type_project_record` (`type`, `project_id`, `record`),
    DROP INDEX `ts_project`;
ALTER TABLE `redcap_ehr_import_counts` ADD `adjudicated` tinyint(1) NOT NULL DEFAULT '0' AFTER type;
ALTER TABLE `redcap_ehr_import_counts` ADD KEY `ts_project_adjud` (`ts`,`project_id`,`adjudicated`);
ALTER TABLE `redcap_ehr_import_counts` ADD KEY `type_adjud_project_record` (`type`,`adjudicated`,`project_id`,`record`);
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);