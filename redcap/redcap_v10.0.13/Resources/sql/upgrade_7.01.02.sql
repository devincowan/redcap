CREATE TABLE `redcap_record_dashboards` (
`rd_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(11) DEFAULT NULL,
`title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
`description` text COLLATE utf8_unicode_ci,
`filter_logic` text COLLATE utf8_unicode_ci,
`orientation` enum('V','H') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'H',
`group_by` enum('form','event') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'event',
`excluded_forms_events` text COLLATE utf8_unicode_ci,
PRIMARY KEY (`rd_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `redcap_record_dashboards`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`);
-- Fix bug when deleting repeating survey instances
delete p from (redcap_surveys_participants p, redcap_surveys_response r, redcap_events_repeat e, redcap_surveys s)
left join redcap_data d on d.project_id = s.project_id and r.instance = d.instance and d.event_id = p.event_id and r.record = d.record and d.field_name = concat(s.form_name,'_complete')
where r.participant_id = p.participant_id and e.event_id = p.event_id and s.survey_id = p.survey_id and r.instance > 1 and d.project_id is null;
-- Add new configuration setting
INSERT INTO redcap_config (field_name, value) VALUES ('user_messaging_enabled', '0');