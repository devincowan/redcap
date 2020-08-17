INSERT INTO redcap_config (field_name, value) VALUES
('auto_prod_changes_check_identifiers', '0');
DROP TABLE IF EXISTS `redcap_record_counts`;
CREATE TABLE `redcap_record_counts` (
`project_id` int(11) NOT NULL,
`record_count` int(11) DEFAULT NULL,
`time_of_count` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`project_id`),
KEY `time_of_count` (`time_of_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_record_counts`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('RemoveOutdatedRecordCounts', 'Delete all rows from the record counts table older than X days.', 'ENABLED', 3600, 60, 1, 0, NULL, 0, NULL);

-- SQL TO CREATE A REDCAP DEMO PROJECT --
set @project_title = 'Repeating Instruments';

-- Obtain default values --
set @institution = (select value from redcap_config where field_name = 'institution' limit 1);
set @site_org_type = (select value from redcap_config where field_name = 'site_org_type' limit 1);
set @grant_cite = (select value from redcap_config where field_name = 'grant_cite' limit 1);
set @project_contact_name = (select value from redcap_config where field_name = 'project_contact_name' limit 1);
set @project_contact_email = (select value from redcap_config where field_name = 'project_contact_email' limit 1);
set @headerlogo = (select value from redcap_config where field_name = 'headerlogo' limit 1);
set @auth_meth = (select value from redcap_config where field_name = 'auth_meth_global' limit 1);
-- Create project --
INSERT INTO `redcap_projects`
(project_name, app_title, status, count_project, auth_meth, creation_time, production_time, institution, site_org_type, grant_cite, project_contact_name, project_contact_email, headerlogo, display_project_logo_institution, auto_inc_set) VALUES
(concat('redcap_demo_',LEFT(sha1(rand()),6)), @project_title, 1, 0, @auth_meth, now(), now(), @institution, @site_org_type, @grant_cite, @project_contact_name, @project_contact_email, @headerlogo, 0, 1);
set @project_id = LAST_INSERT_ID();
-- User rights --
INSERT INTO `redcap_user_rights` (`project_id`, `username`, `expiration`, `group_id`, `lock_record`, `data_export_tool`, `data_import_tool`, `data_comparison_tool`, `data_logging`, `file_repository`, `double_data`, `user_rights`, `data_access_groups`, `graphical`, `reports`, `design`, `calendar`, `data_entry`, `data_quality_execute`) VALUES
(@project_id, 'site_admin', NULL, NULL, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 0, 1, '', 1);
-- Create single arm --
INSERT INTO redcap_events_arms (project_id, arm_num, arm_name) VALUES (@project_id, 1, 'Arm 1');
set @arm_id = LAST_INSERT_ID();
-- Create single event --
INSERT INTO redcap_events_metadata (arm_id, day_offset, offset_min, offset_max, descrip) VALUES (@arm_id, 0, 0, 0, 'Event 1');
set @event_id = LAST_INSERT_ID();
-- Insert into redcap_metadata --
INSERT INTO `redcap_metadata` (`project_id`, `field_name`, `field_phi`, `form_name`, `form_menu_description`, `field_order`, `field_units`, `element_preceding_header`, `element_type`, `element_label`, `element_enum`, `element_note`, `element_validation_type`, `element_validation_min`, `element_validation_max`, `element_validation_checktype`, `branching_logic`, `field_req`, `edoc_id`, `edoc_display_img`, `custom_alignment`, `stop_actions`, `question_num`, `grid_name`, `grid_rank`, `misc`, `video_url`, `video_display_inline`) VALUES
(@project_id, 'record_id', NULL, 'demographics', 'Demographics', 1, NULL, NULL, 'text', 'Study ID', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'first_name', '1', 'demographics', NULL, 2, NULL, NULL, 'text', 'First Name', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'last_name', '1', 'demographics', NULL, 3, NULL, NULL, 'text', 'Last Name', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'gender', NULL, 'demographics', NULL, 4, NULL, NULL, 'radio', 'Gender', '0, Female \\n 1, Male \\n 2, Other \\n 3, Prefer not to say', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'notes', NULL, 'demographics', NULL, 5, NULL, NULL, 'textarea', 'Notes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'demographics_complete', NULL, 'demographics', NULL, 6, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'medication_name', NULL, 'medications', 'Medications', 7, NULL, NULL, 'text', 'Medication name', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'dosage', NULL, 'medications', NULL, 8, NULL, NULL, 'text', 'Dosage', NULL, 'mg', 'float', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'medications_complete', NULL, 'medications', NULL, 9, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'family_member', NULL, 'family_members', 'Family Members', 10, NULL, 'Family member information', 'text', 'Name of family member', NULL, NULL, NULL, NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'relation_to_patient', NULL, 'family_members', NULL, 11, NULL, NULL, 'select', 'Relation to patient', '1, Sibling\\n2, Spouse\\n3, Parent\\n4, Child very long choice right here that is long\\n5, Other', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'age_of_family_member', NULL, 'family_members', NULL, 12, NULL, NULL, 'text', 'Age of family member', NULL, NULL, 'int', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'family_members_complete', NULL, 'family_members', NULL, 13, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'visit_date', NULL, 'visits', 'Visits', 14, NULL, NULL, 'text', 'Date', NULL, NULL, 'date_mdy', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, '@TODAY', NULL, 0),
(@project_id, 'weight', NULL, 'visits', NULL, 15, NULL, NULL, 'text', 'Weight', NULL, NULL, 'float', NULL, NULL, 'soft_typed', NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'other_visit_data', NULL, 'visits', NULL, 16, NULL, NULL, 'textarea', 'Other data', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'visits_complete', NULL, 'visits', NULL, 17, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeyn', NULL, 'adverse_events', 'Adverse Events', 18, NULL, NULL, 'radio', 'Were any adverse events experienced?', '0, No\\n1, Yes', 'Indicate if the subject experienced any adverse events.', NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aespid', NULL, 'adverse_events', NULL, 19, NULL, NULL, 'text', 'AE Identifier', NULL, 'Record unique identifier for each adverse event for this subject.<br><br>Number sequence for all following forms should not duplicate existing numbers for the subject.', NULL, NULL, NULL, 'soft_typed', '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeterm', NULL, 'adverse_events', NULL, 20, NULL, NULL, 'text', 'What is the adverse event term?', NULL, 'Record only one diagnosis, sign or symptom per form (e.g., nausea and vomiting should not be recorded in the same entry, but as two separate entries).  See eCRF completion instruction for more information.', NULL, NULL, NULL, 'soft_typed', '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeoccur', NULL, 'adverse_events', NULL, 21, NULL, NULL, 'radio', 'Does the subject have (specific adverse event)?', '0, No\\n1, Yes', 'Please indicate if (specific adverse event) has occurred /is occurring by checking Yes or No.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aestdat', NULL, 'adverse_events', NULL, 22, NULL, NULL, 'text', 'What is the date the adverse event started?', NULL, 'Record the start date of the adverse event using the MM-DD-YYYY format.', 'date_mdy', NULL, NULL, 'soft_typed', '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aesttim', NULL, 'adverse_events', NULL, 23, NULL, NULL, 'text', 'At what time did the adverse event start?', NULL, 'If appropriate, record the time the AE started using the HH:MM (24-hour clock) format.', 'time', NULL, NULL, 'soft_typed', '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeongo', NULL, 'adverse_events', NULL, 24, NULL, NULL, 'radio', 'Is the adverse event still ongoing?', '0, No\\n1, Yes', 'Select one.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeendat', NULL, 'adverse_events', NULL, 25, NULL, NULL, 'text', 'What date did the adverse event end?', NULL, 'Record the end date of the adverse event using the MM-DD-YYYY format.', 'date_mdy', NULL, NULL, 'soft_typed', '[aeongo] = "0"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeentim', NULL, 'adverse_events', NULL, 26, NULL, NULL, 'text', 'At what time did the adverse event end?', NULL, 'If appropriate, record the time the AE ended using the HH:MM (24-hour clock) format.', 'time', NULL, NULL, 'soft_typed', '[aeongo] = "0"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aesev', NULL, 'adverse_events', NULL, 27, NULL, NULL, 'radio', 'What was the severity of the adverse event?', '1, Mild\\n2, Moderate\\n3, Severe', 'The reporting physician/healthcare professional will assess the severity of the event using the sponsor-defined categories. This assessment is subjective and the reporting physician/ healthcare professional should use medical judgment to compare the reported Adverse Event to similar type events observed in clinical practice. Severity is not equivalent to seriousness.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aetoxgr', NULL, 'adverse_events', NULL, 28, NULL, NULL, 'radio', 'What is the toxicity grade of the adverse event?', '1, Grade 1\\n2, Grade 2\\n3, Grade 3\\n4, Grade 4\\n5, Grade 5', 'Severity CTCAE Grade<br><br>The reporting physician/healthcare professional will assess the severity of the adverse event using the toxicity grades.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeser', NULL, 'adverse_events', NULL, 29, NULL, NULL, 'radio', 'Is the adverse event serious?', '0, No\\n1, Yes', 'Assess if an adverse event should be classified as serious based on the serious criteria defined in the protocol.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aescong', NULL, 'adverse_events', NULL, 30, NULL, NULL, 'radio', 'Is the adverse event associated with a congenital anomaly or birth defect?', '0, No\\n1, Yes', 'Record whether the serious adverse event was associated with congenital anomaly or birth defect.', NULL, NULL, NULL, NULL, '[aeser] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aesdisab', NULL, 'adverse_events', NULL, 31, NULL, NULL, 'radio', 'Did the adverse event result in Persistent or significant disability or incapacity?', '0, No\\n1, Yes', 'Record whether the serious adverse event resulted in a persistent or significant disability or incapacity.', NULL, NULL, NULL, NULL, '[aeser] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aesdth', NULL, 'adverse_events', NULL, 32, NULL, NULL, 'radio', 'Did the adverse event result in death?', '0, No\\n1, Yes', 'Record whether the serious adverse event resulted in death.', NULL, NULL, NULL, NULL, '[aeser] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeshosp', NULL, 'adverse_events', NULL, 33, NULL, NULL, 'radio', 'Did the adverse event result in initial or prolonged hospitalization for the subject?', '0, No\\n1, Yes', 'Record whether the serious adverse event resulted in an initial or prolonged hospitalization.', NULL, NULL, NULL, NULL, '[aeser] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeslife', NULL, 'adverse_events', NULL, 34, NULL, NULL, 'radio', 'Is the adverse event Life Threatening?', '0, No\\n1, Yes', 'Record whether the serious adverse event is life threatening.', NULL, NULL, NULL, NULL, '[aeser] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aesmie', NULL, 'adverse_events', NULL, 35, NULL, NULL, 'radio', 'Is the adverse event a medically important event not covered by other ?serious? criteria?', '0, No\\n1, Yes', 'Record whether the serious adverse event is an important medical event, which may be defined in the protocol or in the Investigator Brochure.', NULL, NULL, NULL, NULL, '[aeser] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aerel', NULL, 'adverse_events', NULL, 36, NULL, NULL, 'radio', 'Is this event related to study treatment?', '1, Definitely\\n2, Probably\\n3, Possibly\\n4, Not Related', 'Indicate if the cause of the adverse event is related to the study treatment and cannot be reasonably explained by other factors (e.g., subject\'s clinical state, concomitant therapy, and/or other interventions).', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeacn', NULL, 'adverse_events', NULL, 37, NULL, NULL, 'radio', 'What action was taken with study treatment?', '1, Dose Increased\\n2, Dose Not Changed\\n3, Dose Reduced\\n4, Drug Interrupted\\n5, Drug Withdrawn\\n6, Not Applicable\\n99, Unknown', 'Record changes made to the study treatment resulting from the adverse event.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeacnoth', NULL, 'adverse_events', NULL, 38, NULL, NULL, 'textarea', 'What other action was taken in response to this adverse event?', NULL, 'Record all action(s) taken resulting from the adverse event.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aeout', NULL, 'adverse_events', NULL, 39, NULL, NULL, 'radio', 'What was the outcome of this adverse event?', '1, Fatal\\n2, Not recovered / Not resolved\\n3, Recovered / Resolved\\n4, Recovered / Resolved with sequelae\\n5, Recovering / Resolving\\n99, Unknown', 'Record the appropriate outcome of the event in relation to the subject\'s status.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'aedis', NULL, 'adverse_events', NULL, 40, NULL, NULL, 'radio', 'Did the adverse event cause the subject to be discontinued from the study?', '0, No\\n1, Yes', 'Record if the AE caused the subject to discontinue from the study.', NULL, NULL, NULL, NULL, '[aeyn] = "1"', 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(@project_id, 'adverse_events_complete', NULL, 'adverse_events', NULL, 41, NULL, 'Form Status', 'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0);
INSERT INTO `redcap_events_repeat` (`event_id`, `form_name`, `custom_repeat_form_label`) VALUES
(@event_id, 'adverse_events', NULL),
(@event_id, 'family_members', '[family_member]'),
(@event_id, 'medications', '[medication_name] [dosage]mg'),
(@event_id, 'visits', '[weight]kg ([visit_date])');
INSERT INTO `redcap_projects_templates` (`project_id`, `title`, `description`, `enabled`)
	VALUES (@project_id,  @project_title,  'Example classic project showcasing the Repeating Instruments functionality.',  '1');