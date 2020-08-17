-- Modify tables for upcoming reports functionality
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS redcap_reports;
CREATE TABLE redcap_reports (
  report_id int(10) NOT NULL AUTO_INCREMENT,
  project_id int(10) NOT NULL,
  title text COLLATE utf8_unicode_ci,
  report_order int(3) NOT NULL DEFAULT '1',
  user_access enum('ALL','SELECTED') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  orderby_field1 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  orderby_sort1 enum('ASC','DESC') COLLATE utf8_unicode_ci DEFAULT NULL,
  orderby_field2 varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  orderby_sort2 enum('ASC','DESC') COLLATE utf8_unicode_ci DEFAULT NULL,
  preset_export_format varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  preset_archive_files int(1) NOT NULL DEFAULT '0',
  preset_export_dags int(1) NOT NULL DEFAULT '0',
  preset_export_survey_fields int(1) NOT NULL DEFAULT '0',
  preset_remove_identifiers int(1) NOT NULL DEFAULT '0',
  preset_hash_recordid int(1) NOT NULL DEFAULT '0',
  preset_remove_unval_text_fields int(1) NOT NULL DEFAULT '0',
  preset_remove_notes_fields int(1) NOT NULL DEFAULT '0',
  preset_remove_date_fields int(1) NOT NULL DEFAULT '0',
  preset_shift_dates int(1) NOT NULL DEFAULT '0',
  preset_shift_survey_timestamps int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (report_id),
  UNIQUE KEY project_report_order (project_id,report_order),
  KEY project_id (project_id)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_access_dags'
--

DROP TABLE IF EXISTS redcap_reports_access_dags;
CREATE TABLE redcap_reports_access_dags (
  report_id int(10) NOT NULL AUTO_INCREMENT,
  group_id int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (report_id,group_id),
  KEY group_id (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_access_roles'
--

DROP TABLE IF EXISTS redcap_reports_access_roles;
CREATE TABLE redcap_reports_access_roles (
  report_id int(10) NOT NULL DEFAULT '0',
  role_id int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (report_id,role_id),
  KEY role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_access_users'
--

DROP TABLE IF EXISTS redcap_reports_access_users;
CREATE TABLE redcap_reports_access_users (
  report_id int(10) NOT NULL AUTO_INCREMENT,
  username varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (report_id,username),
  KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_fields'
--

DROP TABLE IF EXISTS redcap_reports_fields;
CREATE TABLE redcap_reports_fields (
  rf_id int(10) NOT NULL AUTO_INCREMENT,
  report_id int(10) DEFAULT NULL,
  field_name varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  field_order int(3) DEFAULT NULL,
  limiter_event_id int(10) DEFAULT NULL,
  limiter_operator enum('E','NE','GT','GTE','LT','LTE','CONTAINS','CHECKED','UNCHECKED') COLLATE utf8_unicode_ci DEFAULT NULL,
  limiter_value varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (rf_id),
  UNIQUE KEY report_id_field_name_order (report_id,field_name,field_order),
  KEY field_name (field_name),
  KEY limiter_event_id (limiter_event_id),
  KEY report_id_field_order (report_id,field_order)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_filter_dags'
--

DROP TABLE IF EXISTS redcap_reports_filter_dags;
CREATE TABLE redcap_reports_filter_dags (
  report_id int(10) NOT NULL AUTO_INCREMENT,
  group_id int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (report_id,group_id),
  KEY group_id (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_filter_events'
--

DROP TABLE IF EXISTS redcap_reports_filter_events;
CREATE TABLE redcap_reports_filter_events (
  report_id int(10) NOT NULL AUTO_INCREMENT,
  event_id int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (report_id,event_id),
  KEY event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table 'redcap_reports_filter_records'
--

DROP TABLE IF EXISTS redcap_reports_filter_records;
CREATE TABLE redcap_reports_filter_records (
  report_id int(10) NOT NULL AUTO_INCREMENT,
  record varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  UNIQUE KEY report_record (report_id,record)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `redcap_reports`
--
ALTER TABLE `redcap_reports`
  ADD CONSTRAINT redcap_reports_ibfk_1 FOREIGN KEY (project_id) REFERENCES redcap_projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_access_dags`
--
ALTER TABLE `redcap_reports_access_dags`
  ADD CONSTRAINT redcap_reports_access_dags_ibfk_1 FOREIGN KEY (group_id) REFERENCES redcap_data_access_groups (group_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_reports_access_dags_ibfk_2 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_access_roles`
--
ALTER TABLE `redcap_reports_access_roles`
  ADD CONSTRAINT redcap_reports_access_roles_ibfk_1 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_reports_access_roles_ibfk_2 FOREIGN KEY (role_id) REFERENCES redcap_user_roles (role_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_access_users`
--
ALTER TABLE `redcap_reports_access_users`
  ADD CONSTRAINT redcap_reports_access_users_ibfk_1 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_fields`
--
ALTER TABLE `redcap_reports_fields`
  ADD CONSTRAINT redcap_reports_fields_ibfk_1 FOREIGN KEY (limiter_event_id) REFERENCES redcap_events_metadata (event_id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_reports_fields_ibfk_2 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_filter_dags`
--
ALTER TABLE `redcap_reports_filter_dags`
  ADD CONSTRAINT redcap_reports_filter_dags_ibfk_1 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_reports_filter_dags_ibfk_2 FOREIGN KEY (group_id) REFERENCES redcap_data_access_groups (group_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_filter_events`
--
ALTER TABLE `redcap_reports_filter_events`
  ADD CONSTRAINT redcap_reports_filter_events_ibfk_1 FOREIGN KEY (event_id) REFERENCES redcap_events_metadata (event_id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT redcap_reports_filter_events_ibfk_2 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `redcap_reports_filter_records`
--
ALTER TABLE `redcap_reports_filter_records`
  ADD CONSTRAINT redcap_reports_filter_records_ibfk_1 FOREIGN KEY (report_id) REFERENCES redcap_reports (report_id) ON DELETE CASCADE ON UPDATE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
ALTER TABLE  `redcap_reports_fields` ADD  `limiter_group_operator` ENUM(  'AND',  'OR' ) NULL DEFAULT NULL AFTER  `field_order`;
ALTER TABLE  `redcap_reports_fields` DROP  `rf_id`;
ALTER TABLE  `redcap_reports` CHANGE  `report_order`  `report_order` INT( 3 ) NULL DEFAULT NULL;