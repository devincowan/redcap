<?php

$sql = "
ALTER TABLE `redcap_projects` ADD `missing_data_codes` TEXT NULL DEFAULT NULL;
ALTER TABLE `redcap_reports` ADD `output_missing_data_codes` INT(1) NOT NULL DEFAULT '0' AFTER `output_survey_fields`;

CREATE TABLE `redcap_ehr_datamart_counts` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`ts` datetime DEFAULT NULL,
`project_id` int(11) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`counts_Patient` mediumint(7) DEFAULT NULL,
`counts_Condition` mediumint(7) DEFAULT NULL,
`counts_MedicationOrder` mediumint(7) DEFAULT NULL,
`counts_AllergyIntolerance` mediumint(7) DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `project_record` (`project_id`,`record`),
KEY `ts_project` (`ts`,`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_ehr_datamart_counts`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);

// Add Messenger system notification
$title = "New feature: Missing Data Codes";
$msg = "Project fields that have a blank/missing value may be marked with a custom \"Missing Data Code\" to note why the value is blank (e.g. NASK = Not Asked). You are able to define your own codes in any given project and in a format very similar to defining the choices of a multiple choice field. These missing data codes may be used to aid in data analysis by specifying why a field lacks a value. The codes will be saved as the literal data values for the fields and thus may be optionally exported in a data export, viewed in a report, or utilized in branching logic (among other things). 

To learn more about missing data codes, see the section in the Additional Customizations popup on the Project Setup page in any of your projects. ENJOY!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);