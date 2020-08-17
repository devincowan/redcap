<?php

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;

// Begin transaction
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");

// Ensure this is a POST request
if (!isset($_POST['app_title']) || empty($_POST['app_title'])) exit("ERROR!");

// Make sure user has ability to create projects
$userInfo = User::getUserInfo(USERID);
if (!$userInfo['allow_create_db']) exit("ERROR: You do not have Create Project privileges!");

// If a normal user tries to create a project when only super users can create projects, then return error
if ($superusers_only_create_project && !$super_user) exit("ERROR: You do not have Create Project privileges!");

// Check if any errors occurred when uploading an ODM file (if applicable)
if (isset($_FILES['odm'])) ODM::checkErrorsOdmFileUpload($_FILES['odm']);

// Remove any HTML in the title
$_POST['app_title'] = Project::cleanTitle($_POST['app_title']);

// Create new name derived from Project Title (and check for duplication with existing projects)
$new_app_name = Project::getValidProjectName($_POST['app_title']);

// Catch if user selected multiple Research options for Purpose
$_POST['purpose_other'] = isset($_POST['purpose_other']) ? Project::purpToStr($_POST['purpose_other']) : '';

// Make sure other parameters were set properly
$_POST['repeatforms']     = (isset($_POST['repeatforms']) && in_array($_POST['repeatforms'], array(0, 1))) ? $_POST['repeatforms'] : 0;
$_POST['purpose']         = (isset($_POST['purpose']) && is_numeric($_POST['purpose'])) ? $_POST['purpose'] : 'NULL';
$_POST['scheduling']      = (isset($_POST['scheduling']) && in_array($_POST['scheduling'], array(0, 1))) ? $_POST['scheduling'] : 0;
$_POST['surveys_enabled'] = (isset($_POST['surveys_enabled']) && is_numeric($_POST['surveys_enabled'])) ? $_POST['surveys_enabled'] : 0;
$_POST['randomization']   = (isset($_POST['randomization']) && in_array($_POST['randomization'], array(0, 1))) ? $_POST['randomization'] : 0;

// Enable auto-numbering for all new projects
$auto_inc_set = 1;

// Set flag if creating the project from a template
$isTemplate = (isset($_POST['copyof']) && is_numeric($_POST['copyof']) && isset($_POST['project_template_radio']) && $_POST['project_template_radio'] == '1');
// Data Mart project?
$ehrDataMartProject = ($fhir_data_mart_create_project && $_POST['project_template_radio'] == '3');
// Set the log_event table
$log_event_table = Logging::getSmallestLogEventTable();

/**
 * Insert defaults and user-defined values for this new project
 */
// Insert into redcap_projects table
$sql = "insert into redcap_projects (project_name, scheduling, repeatforms, purpose, purpose_other, app_title, creation_time, created_by,
		project_pi_firstname, project_pi_mi, project_pi_lastname, project_pi_email, project_pi_alias, project_pi_username, project_irb_number,
		project_grant_number, surveys_enabled, auto_inc_set, randomization, auth_meth, template_id, data_resolution_enabled, project_note, datamart_enabled, log_event_table) values
		('$new_app_name', {$_POST['scheduling']}, {$_POST['repeatforms']}, {$_POST['purpose']},
		" . checkNull($_POST['purpose_other']) . ",
		'".db_escape($_POST['app_title'])."', '".NOW."', (select ui_id from redcap_user_information where username = '$userid' limit 1),
		" . ((!isset($_POST['project_pi_firstname']) || $_POST['project_pi_firstname'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_firstname'])."'") . ",
		" . ((!isset($_POST['project_pi_mi']) || $_POST['project_pi_mi'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_mi'])."'") . ",
		" . ((!isset($_POST['project_pi_lastname']) || $_POST['project_pi_lastname'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_lastname'])."'") . ",
		" . ((!isset($_POST['project_pi_email']) || $_POST['project_pi_email'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_email'])."'") . ",
		" . ((!isset($_POST['project_pi_alias']) || $_POST['project_pi_alias'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_alias'])."'") . ",
		" . ((!isset($_POST['project_pi_username']) || $_POST['project_pi_username'] == "") ? "NULL" : "'".db_escape($_POST['project_pi_username'])."'") . ",
		" . ((!isset($_POST['project_irb_number']) || $_POST['project_irb_number'] == "") ? "NULL" : "'".db_escape($_POST['project_irb_number'])."'") . ",
		" . ((!isset($_POST['project_grant_number']) || $_POST['project_grant_number'] == "") ? "NULL" : "'".db_escape($_POST['project_grant_number'])."'") . ",
		{$_POST['surveys_enabled']}, $auto_inc_set, {$_POST['randomization']}, '".db_escape($auth_meth_global)."',
		".($isTemplate ? $_POST['copyof'] : "null").", '".($field_comment_log_enabled_default == '0' ? '0' : '1')."',
		".checkNull(trim($_POST['project_note'])).", ".($ehrDataMartProject ? '1' : '0').", '$log_event_table')";
$q = db_query($sql);
if (!$q || db_affected_rows() != 1) {
	print db_error();
	queryFail($sql);
}
// Get this new project's project_id
$new_project_id = db_insert_id();
define("PROJECT_ID", $new_project_id);
$user = isset($_POST['username']) ? $_POST['username'] : $userid;
ProjectFolders::addNewProjectFolders(User::getUserInfo($user), $new_project_id, $_POST);

// Get default values for redcap_projects table columns
$redcap_projects_defaults = getTableColumns('redcap_projects');

// Insert project defaults into redcap_projects
Project::setDefaults($new_project_id);


/**
 * COPYING PROJECT OR CREATING NEW PROJECT USING TEMPLATE
 */
## If copying an existing project
if (isset($_POST['copyof']) && is_numeric($_POST['copyof']))
{
	// Message flag used for dialog pop-up
	$msg_flag = ($isTemplate) ? "newproject" : "copiedproject";

	// Verify project_id of original
	$q = db_query("select randomization from redcap_projects where project_id = {$_POST['copyof']} limit 1");
	if (!$q || db_num_rows($q) < 1) {
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		exit("ERROR!");
	}
	$row = db_fetch_assoc($q);

	$copyof_project_id = $_POST['copyof'];

	// Set randomization flag for project
	$randomization = (isset($row['randomization'])) ? $row['randomization'] : 0;

	// Copy metadata fields
	$sql = "insert into redcap_metadata select '$new_project_id', field_name, field_phi, form_name, form_menu_description, field_order,
			field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
			element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req, NULL,
			edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline
			from redcap_metadata where project_id = $copyof_project_id";
	$q = db_query($sql);

	## CHECK FOR EDOC FILE ATTACHMENTS: Copy all files on the server, if being used (one at a time)
	$sql = "select field_name, edoc_id from redcap_metadata where project_id = $copyof_project_id and edoc_id is not null";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Copy file on server
		$new_edoc_id = copyFile($row['edoc_id'], $new_project_id);
		if (is_numeric($new_edoc_id))
		{
			// Now update new field's edoc_id value
			$sql = "update redcap_metadata set edoc_id = $new_edoc_id where project_id = $new_project_id and field_name = '{$row['field_name']}'";
			db_query($sql);
		}
	}

	// Copy arms/events (one event at a time)
	$eventid_translate = array(); // Store old event_id as key and new event_id as value
	$q = db_query("select arm_id, arm_num, arm_name from redcap_events_arms where project_id = $copyof_project_id");
	while ($row = db_fetch_assoc($q)) {
		// Copy arm
		db_query("insert into redcap_events_arms (project_id, arm_num, arm_name) values ($new_project_id, {$row['arm_num']}, '".db_escape($row['arm_name'])."')");
		$this_arm_id = db_insert_id();
		$q2 = db_query("select * from redcap_events_metadata where arm_id = {$row['arm_id']}");
		while ($row2 = db_fetch_assoc($q2))
		{
			// Copy event
			db_query("insert into redcap_events_metadata (arm_id, day_offset, offset_min, offset_max, descrip, custom_event_label) values
						 ($this_arm_id, {$row2['day_offset']}, {$row2['offset_min']}, {$row2['offset_max']}, 
						 '".db_escape($row2['descrip'])."', ".checkNull($row2['custom_event_label']).")");
			$this_event_id = db_insert_id();
			// Get old event_id of copied project and translate to new equivalent event_id for new project
			$eventid_translate[$row2['event_id']] = $this_event_id;
			// Copy events-forms matching
			db_query("insert into redcap_events_forms (event_id, form_name) select '$this_event_id', form_name from redcap_events_forms where event_id = {$row2['event_id']}");
		}
	}

	// Copy some defined project-level values from the project being copied
	$projectFieldsCopy = array( "repeatforms", "scheduling", "randomization", "surveys_enabled", "field_comment_edit_delete",
								"display_today_now_button", "auto_inc_set", "require_change_reason", "secondary_pk",
								"history_widget_enabled", "order_id_by", "custom_record_label", "enable_participant_identifiers",
								"survey_email_participant_field", "data_resolution_enabled", "project_language", "project_encoding",
								"display_project_logo_institution", "survey_auth_enabled", "survey_auth_field1", "survey_auth_event_id1",
								"survey_auth_field2", "survey_auth_event_id2", "survey_auth_field3", "survey_auth_event_id3",
								"survey_auth_min_fields", "survey_auth_apply_all_surveys", "survey_auth_custom_message",
								"survey_auth_fail_limit", "survey_auth_fail_window", "disable_autocalcs", "custom_index_page_note",
								"custom_data_entry_note", "realtime_webservice_type", "realtime_webservice_offset_days", "realtime_webservice_offset_plusminus",
								"missing_data_codes");
	// Also include the custom project-level settings
	if (!$isTemplate) $projectFieldsCopy = array_merge($projectFieldsCopy, Project::$overwritableGlobalVars);
	// Retrieve field values from project being copied and update newly created project
	$sql = "select " . implode(", ", $projectFieldsCopy) . " from redcap_projects where project_id = $copyof_project_id";
	$q = db_query($sql);
	$row = db_fetch_assoc($q);
	$updateVals = array();
	foreach ($projectFieldsCopy as $this_field)
	{
		// If users are not allowed to create surveys (global setting), then set surveys_enabled = 0
		if (!$enable_projecttype_singlesurveyforms && $this_field == "surveys_enabled") {
			$row[$this_field] = '0';
		}
		// Translate some event_ids (if applicable)
		if (substr($this_field, 0, -1) == "survey_auth_event_id" && is_numeric($row[$this_field])) {
			$row[$this_field] = $eventid_translate[$row[$this_field]];
		}
		// Use checkNull if column's default value is NULL
		if (array_key_exists($this_field, $redcap_projects_defaults) && $redcap_projects_defaults[$this_field] === null) {
			$updateVals[] = $this_field . " = " . checkNull(label_decode($row[$this_field]));
		} else {
			$updateVals[] = $this_field . " = '" . db_escape(label_decode($row[$this_field])) . "'";
		}
	}
	$sql = "update redcap_projects set " . implode(", ", $updateVals) . " where project_id = $new_project_id";
	db_query($sql);

	// Copy any Shared Library instrument mappings
	$sql = "insert into redcap_library_map (project_id, form_name, `type`, library_id, upload_timestamp, acknowledgement, acknowledgement_cache, promis_key)
			select '$new_project_id', form_name, `type`, library_id, upload_timestamp, acknowledgement, acknowledgement_cache, promis_key
			from redcap_library_map where project_id = $copyof_project_id";
	$q = db_query($sql);

	// Copy any surveys
	$surveyid_translate = array(); // Store old survey_id as key and new survey_id as value
	$sql = "select * from redcap_surveys where project_id = $copyof_project_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$sql = "insert into redcap_surveys (project_id, form_name, title, instructions, acknowledgement, question_by_section,
				question_auto_numbering, survey_enabled, save_and_return, hide_title, view_results, min_responses_view_results, check_diversity_view_results,
				end_survey_redirect_url, promis_skip_question,
				survey_auth_enabled_single, edit_completed_response, hide_back_button, show_required_field_text,
				confirmation_email_subject, confirmation_email_content, confirmation_email_from, text_to_speech,
				text_to_speech_language, end_survey_redirect_next_survey, theme, text_size, font_family,
				theme_bg_page, theme_text_buttons, theme_text_title, theme_bg_title,
				theme_text_question, theme_bg_question, theme_text_sectionheader, theme_bg_sectionheader,
				enhanced_choices, repeat_survey_enabled, repeat_survey_btn_text, repeat_survey_btn_location, 
				response_limit, response_limit_include_partials, response_limit_custom_text,
				survey_time_limit_days, survey_time_limit_hours, survey_time_limit_minutes, end_of_survey_pdf_download,
				save_and_return_code_bypass, email_participant_field, pdf_auto_archive,
				pdf_econsent_version, pdf_econsent_type, pdf_econsent_firstname_field, pdf_econsent_firstname_event_id,
				pdf_econsent_lastname_field, pdf_econsent_lastname_event_id, pdf_econsent_dob_field, pdf_econsent_dob_event_id) values
				($new_project_id, ".checkNull($row['form_name']).", ".checkNull($row['title']).", ".checkNull($row['instructions']).",
				".checkNull($row['acknowledgement']).", {$row['question_by_section']},
				{$row['question_auto_numbering']}, 1, {$row['save_and_return']}, {$row['hide_title']}, {$row['view_results']},
				{$row['min_responses_view_results']}, {$row['check_diversity_view_results']},
				".checkNull(label_decode($row['end_survey_redirect_url'])).",
				{$row['promis_skip_question']},
				{$row['survey_auth_enabled_single']}, {$row['edit_completed_response']}, {$row['hide_back_button']}, {$row['show_required_field_text']},
				".checkNull($row['confirmation_email_subject']).", ".checkNull($row['confirmation_email_content']).", ".checkNull($row['confirmation_email_from']).",
				{$row['text_to_speech']}, '{$row['text_to_speech_language']}', '{$row['end_survey_redirect_next_survey']}',
				".checkNull($row['theme']).", ".checkNull($row['text_size']).", ".checkNull($row['font_family']).",
				".checkNull($row['theme_bg_page']).", ".checkNull($row['theme_text_buttons']).", ".checkNull($row['theme_text_title']).", ".checkNull($row['theme_bg_title']).",
				".checkNull($row['theme_text_question']).", ".checkNull($row['theme_bg_question']).", ".checkNull($row['theme_text_sectionheader']).", ".checkNull($row['theme_bg_sectionheader']).",
				".checkNull($row['enhanced_choices']).", ".checkNull($row['repeat_survey_enabled']).", ".checkNull($row['repeat_survey_btn_text']).", ".checkNull($row['repeat_survey_btn_location']).",
				".checkNull($row['response_limit']).", ".checkNull($row['response_limit_include_partials']).", ".checkNull($row['response_limit_custom_text']).",
				".checkNull($row['survey_time_limit_days']).", ".checkNull($row['survey_time_limit_hours']).", ".checkNull($row['survey_time_limit_minutes']).", 
				".checkNull($row['end_of_survey_pdf_download']).",".checkNull($row['save_and_return_code_bypass']).",
				".checkNull($row['email_participant_field']).", ".checkNull($row['pdf_auto_archive']).",
				".checkNull($row['pdf_econsent_version']).", ".checkNull($row['pdf_econsent_type']).",
				".checkNull($row['pdf_econsent_firstname_field']).", ".checkNull($eventid_translate[$row['pdf_econsent_firstname_event_id']]).",
				".checkNull($row['pdf_econsent_lastname_field']).", ".checkNull($eventid_translate[$row['pdf_econsent_lastname_event_id']]).",
				".checkNull($row['pdf_econsent_dob_field']).", ".checkNull($eventid_translate[$row['pdf_econsent_dob_event_id']]).")";
		db_query($sql);
		$this_survey_id = db_insert_id();
		// Get old event_id of copied project and translate to new equivalent event_id for new project
		$surveyid_translate[$row['survey_id']] = $this_survey_id;
		// Copy the logo file and get new edoc_id
		if (!empty($row['logo']))
		{
			$edoc_id = copyFile($row['logo'], $new_project_id);
			// Add new edoc_id to surveys table for this survey
			if (!empty($edoc_id)) {
				$sql = "update redcap_surveys set logo = $edoc_id where survey_id = $this_survey_id";
				db_query($sql);
			}
		}
		// Copy the email confirmation attachment and get new edoc_id
		if (!empty($row['confirmation_email_attachment']))
		{
			$edoc_id = copyFile($row['confirmation_email_attachment'], $new_project_id);
			// Add new edoc_id to surveys table for this survey
			if (!empty($edoc_id)) {
				$sql = "update redcap_surveys set confirmation_email_attachment = $edoc_id where survey_id = $this_survey_id";
				db_query($sql);
			}
		}
	}

	// Copy redcap_events_repeat for repeating forms/events
	$q = db_query("select * from redcap_events_repeat where event_id in (".prep_implode(array_keys($eventid_translate)).")");
	while ($row = db_fetch_assoc($q)) {
		db_query("insert into redcap_events_repeat (event_id, form_name, custom_repeat_form_label) 
				  values (".$eventid_translate[$row['event_id']].", '".db_escape($row['form_name'])."', ".checkNull($row['custom_repeat_form_label']).")");
	}

	// Copy data access groups (do one at a time to grab old/new values for matching later)
	$groupid_array = array();
	$q = db_query("select * from redcap_data_access_groups where project_id = $copyof_project_id");
	while ($row = db_fetch_assoc($q)) {
		db_query("insert into redcap_data_access_groups (project_id, group_name) values ($new_project_id, '".db_escape($row['group_name'])."')");
		$groupid_array[$row['group_id']] = db_insert_id();

	}
	// Copy DAG Switcher assignments
	if (!empty($groupid_array)) {
		// db_query("insert into redcap_data_access_groups_users (project_id, group_id, username) select '$new_project_id', from  where project_id = $copyof_project_id");
		$q = db_query("select group_id, username from redcap_data_access_groups_users where project_id = $copyof_project_id");
		while ($row = db_fetch_assoc($q)) {
			if ($row['group_id'] != null && !isset($groupid_array[$row['group_id']])) continue;
			db_query("insert into redcap_data_access_groups_users (project_id, group_id, username) values ($new_project_id, ".checkNull($groupid_array[$row['group_id']]).", '".db_escape($row['username'])."')");
		}
	}

	## COPY LOCKING CUSTOMIZATIONS
	$sql = "insert into redcap_locking_labels (project_id, form_name, label, display, display_esignature)
			select '$new_project_id', form_name, label, display, display_esignature	from redcap_locking_labels
			where project_id = $copyof_project_id";
	db_query($sql);

	## COPY RECORD STATUS DASHBOARDS
	if ($isTemplate || (isset($_POST['copy_record_dash']) && $_POST['copy_record_dash'] == "on"))
	{		
		$q = db_query("select * from redcap_record_dashboards where project_id = $copyof_project_id");
		while ($row = db_fetch_assoc($q)) {
			if ($row['selected_forms_events'] != '') {
				$selected_forms_events = array();
				foreach (explode(",", $row['selected_forms_events']) as $attr) {
					list ($this_event_id, $this_form) = explode(":", $attr, 2);
					$this_event_id = $eventid_translate[$this_event_id];
					$selected_forms_events[] = $this_event_id.":".$this_form;
				}
				$row['selected_forms_events'] = implode(",", $selected_forms_events);
			}
			db_query("insert into redcap_record_dashboards (project_id, title, description, filter_logic, orientation, group_by, selected_forms_events, arm, sort_event_id, sort_field_name, sort_order) 
					  values ('$new_project_id', ".checkNull($row['title']).", ".checkNull($row['description']).", ".checkNull($row['filter_logic']).", ".checkNull($row['orientation']).", ".checkNull($row['group_by']).", ".checkNull($row['selected_forms_events']).", ".checkNull($row['arm']).", ".checkNull($eventid_translate[$row['sort_event_id']]).", ".checkNull($row['sort_field_name']).", ".checkNull($row['sort_order']).")");
		}
	}

	## COPY USER ROLES (do one at a time to grab old/new values for matching later)
	$userRoleId_array = array();
	if ($isTemplate || (isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on"))
	{
		$q = db_query("select * from redcap_user_roles where project_id = $copyof_project_id");
		while ($row = db_fetch_assoc($q)) {
			// Set role_id before we remove it
			$this_role_id = $row['role_id'];
			// Remove project_id and role_id from $row since we don't need them
			unset($row['project_id'], $row['role_id']);
			// Loop through $row values and escape them for query
			foreach ($row as &$val) $val = checkNull($val);
			// Set the field names and corresponding values for query
			$role_fields = implode(", ", array_keys($row));
			$role_values = implode(", ", $row);
			db_query("insert into redcap_user_roles (project_id, $role_fields) values ($new_project_id, $role_values)");
			// Add role_id to array for later
			$userRoleId_array[$this_role_id] = db_insert_id();
		}
	}

	## COPY REPORTS (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_reports']) && $_POST['copy_reports'] == "on"))
	{
		// List of all db tables relating to reports, excluding redcap_reports
		$tables = array('redcap_reports_fields', 'redcap_reports_filter_events', 'redcap_reports_access_dags', 'redcap_reports_filter_dags');
		// If copying users/roles, then include the user/role report tables
		if (isset($_POST['copy_users']) && $_POST['copy_users'] == "on") {
			$tables[] = 'redcap_reports_access_users';
		}
        if (isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on") {
            $tables[] = 'redcap_reports_access_roles';
        }
		// Loop through ALL reports one by one
		$reportid_translate = array();
		$sql = "select * from redcap_reports where project_id = $copyof_project_id order by report_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$report_id = $row['report_id'];
			// Set project_id of new project
			$row['project_id'] = $new_project_id;
			unset($row['report_id']);
			// If we're not copying users, then set report access to ALL
			if (!isset($_POST['copy_users']) && !isset($_POST['copy_roles'])) {
				$row['user_access'] = 'ALL';
			}
			// Insert into reports table
			$sqlr = "insert into redcap_reports (".implode(', ', array_keys($row)).") values (".prep_implode($row, true, true).")";
			$qr = db_query($sqlr);
			$new_report_id = db_insert_id();
			$reportid_translate[$report_id] = $new_report_id;
			// Now loop through all other report tables and add
			foreach ($tables as $table_name) {
				// Loop through all rows in this table
				$sqlr2 = "select * from $table_name where report_id = $report_id";
				$q2 = db_query($sqlr2);
				while ($row2 = db_fetch_assoc($q2)) {
					// Set new report_id
					$row2['report_id'] = $new_report_id;
					// If has event_id, role_id, or group_id, then replace with new project's values
					if (isset($row2['event_id'])) {
						$row2['event_id'] = $eventid_translate[$row2['event_id']];
					}
					if (isset($row2['limiter_event_id'])) {
						$row2['limiter_event_id'] = $eventid_translate[$row2['limiter_event_id']];
					}
					if (isset($row2['group_id'])) {
						$row2['group_id'] = $groupid_array[$row2['group_id']];
					}
					if (isset($row2['role_id'])) {
						$row2['role_id'] = $userRoleId_array[$row2['role_id']];
					}
					// Insert
					$sqlr3 = "insert into $table_name (".implode(', ', array_keys($row2)).") values (".prep_implode($row2, true, true).")";
					$q3 = db_query($sqlr3);
				}
			}
		}
		// COPY REPORT FOLDERS: Loop through ALL report folders one by one
		if ($isTemplate || (isset($_POST['copy_report_folders']) && $_POST['copy_report_folders'] == "on"))
		{
			$sql = "select * from redcap_reports_folders where project_id = $copyof_project_id order by position";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$folder_id = $row['folder_id'];
				unset($row['folder_id']);
				$row['project_id'] = $new_project_id;
				// Insert into redcap_reports_folders table
				$sqlr = "insert into redcap_reports_folders (".implode(', ', array_keys($row)).") values (".prep_implode($row, true, true).")";
				$qr = db_query($sqlr);
				$new_folder_id = db_insert_id();
				// Now add this report folders' reports
				$sqlr2 = "select report_id from redcap_reports_folders_items where folder_id = $folder_id";
				$q2 = db_query($sqlr2);
				while ($row2 = db_fetch_assoc($q2)) {
					$sqlr3 = "insert into redcap_reports_folders_items (folder_id, report_id) 
							  values ($new_folder_id, '".db_escape($reportid_translate[$row2['report_id']])."')";
					db_query($sqlr3);
				}
			}
		}
	}

	## COPY THE PROJECT BOOKMARKS
	if ($isTemplate || (isset($_POST['copy_external_links']) && $_POST['copy_external_links'] == "on"))
	{
		$sql = "insert into redcap_external_links (project_id, link_order, link_url, link_label, open_new_window, link_type,
				user_access, append_record_info, append_pid, link_to_project_id)
				select '$new_project_id', link_order, link_url, link_label, open_new_window, link_type,
				user_access, append_record_info, append_pid, link_to_project_id
				from redcap_external_links
				where project_id = $copyof_project_id";
		db_query($sql);
	}


	## COPY DATA QUALITY RULES (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_dq_rules']) && $_POST['copy_dq_rules'] == "on"))
	{
		$sql = "insert into redcap_data_quality_rules (project_id, rule_order, rule_name, rule_logic, real_time_execute)
				select '$new_project_id', rule_order, rule_name, rule_logic, real_time_execute from redcap_data_quality_rules
				where project_id = $copyof_project_id";
		db_query($sql);
	}

	## COPY SETTINGS FOR SURVEY QUEUE AND AUTOMATED SURVEY INVITATIONS (if a template OR if desired for copy)
	if ($isTemplate || (isset($_POST['copy_survey_queue_auto_invites']) && $_POST['copy_survey_queue_auto_invites'] == "on"))
	{
		// COPY SURVEY QUEUE
		$sql = "select distinct q.* from redcap_surveys_queue q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = $copyof_project_id and m.project_id = s.project_id
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id order by q.sq_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$sql = "insert into redcap_surveys_queue (survey_id, event_id, active, auto_start, condition_surveycomplete_survey_id,
					condition_surveycomplete_event_id, condition_andor, condition_logic) values
					('".db_escape($surveyid_translate[$row['survey_id']])."', '".db_escape($eventid_translate[$row['event_id']])."',
					'".db_escape($row['active'])."', '".db_escape($row['auto_start'])."',
					".checkNull($surveyid_translate[$row['condition_surveycomplete_survey_id']]).",
					".checkNull($eventid_translate[$row['condition_surveycomplete_event_id']]).",
					'".db_escape($row['condition_andor'])."', ".checkNull($row['condition_logic']).")";
			db_query($sql);
		}
		// COPY AUTOMATED SURVEY INVITATIONS
		$sql = "select distinct q.* from redcap_surveys_scheduler q, redcap_surveys s, redcap_metadata m, redcap_events_metadata e,
				redcap_events_arms a where s.survey_id = q.survey_id and s.project_id = $copyof_project_id and m.project_id = s.project_id
				and s.form_name = m.form_name and q.event_id = e.event_id and e.arm_id = a.arm_id order by q.ss_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$sql = "insert into redcap_surveys_scheduler (survey_id, event_id, instance, active, email_subject, email_content, email_sender, email_sender_display,
					condition_surveycomplete_survey_id, condition_surveycomplete_event_id, condition_surveycomplete_instance, condition_andor, condition_logic,
					condition_send_time_option, condition_send_time_lag_days, condition_send_time_lag_hours,
					condition_send_time_lag_minutes, condition_send_next_day_type, condition_send_next_time, condition_send_time_exact,
					delivery_type, reminder_type, reminder_timelag_days, reminder_timelag_hours, reminder_timelag_minutes, 
					reminder_nextday_type, reminder_nexttime, reminder_exact_time, reminder_num, reeval_before_send) values
					('".db_escape($surveyid_translate[$row['survey_id']])."', '".db_escape($eventid_translate[$row['event_id']])."', '".db_escape($row['instance'])."',
					'0', '".db_escape($row['email_subject'])."', '".db_escape($row['email_content'])."', '".db_escape($row['email_sender'])."', ".checkNull($row['email_sender_display']).",
					".checkNull($surveyid_translate[$row['condition_surveycomplete_survey_id']]).",
					".checkNull($eventid_translate[$row['condition_surveycomplete_event_id']]).",
					".checkNull($row['condition_surveycomplete_instance']).",
					'".db_escape($row['condition_andor'])."', ".checkNull($row['condition_logic']).", '".db_escape($row['condition_send_time_option'])."',
					".checkNull($row['condition_send_time_lag_days']).", ".checkNull($row['condition_send_time_lag_hours']).",
					".checkNull($row['condition_send_time_lag_minutes']).", ".checkNull($row['condition_send_next_day_type']).",
					".checkNull($row['condition_send_next_time']).", ".checkNull($row['condition_send_time_exact']).",
					".checkNull($row['delivery_type']).", ".checkNull($row['reminder_type']).", ".checkNull($row['reminder_timelag_days']).",
					".checkNull($row['reminder_timelag_hours']).", ".checkNull($row['reminder_timelag_minutes']).", ".checkNull($row['reminder_nextday_type']).",
					".checkNull($row['reminder_nexttime']).", ".checkNull($row['reminder_exact_time']).", ".checkNull($row['reminder_num']).", 
					".checkNull($row['reeval_before_send']).")";
			db_query($sql);
		}
	}

    ## COPY ALL ACTIVE ALERTS (if a template OR if desired for copy)
    if ($isTemplate || (isset($_POST['copy_alerts']) && $_POST['copy_alerts'] == "on"))
    {
        $sql = "select * from redcap_alerts where project_id = $copyof_project_id and email_deleted = 0 order by alert_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Copy any file attachments and get new edoc_id
            $alertAttachFields = array('email_attachment1', 'email_attachment2', 'email_attachment3', 'email_attachment4', 'email_attachment5');
            foreach ($alertAttachFields as $thisAttachField) {
                if (!empty($row[$thisAttachField])) {
                    $row[$thisAttachField] = copyFile($row[$thisAttachField], $new_project_id);
                }
            }
            unset($row['alert_id'], $row['email_timestamp_sent'], $row['email_sent']);
            $row['project_id'] = $new_project_id;
            $row['email_deleted'] = 1; // Set all alerts to be deactivated, just in case
            $row['form_name_event'] = $eventid_translate[$row['form_name_event']];
            $sql = "insert into redcap_alerts (".implode(', ', array_keys($row)).") 
                    values (".prep_implode($row, true, true).")";
            db_query($sql);
        }
    }

	## COPY RECORDS (if applicable)
	if (!$isTemplate && isset($_POST['copy_records']) && $_POST['copy_records'] == "on")
	{
		// COPY BIOONTOLOGY WEB SERVICE'S CACHED DATA
		$sql = "insert into redcap_web_service_cache (project_id, service, category, value, label)
				select '$new_project_id', service, category, value, label from redcap_web_service_cache 
				where project_id = $copyof_project_id";
		db_query($sql);
		
		## COPY DATA: Transfer data one event at a time
		foreach ($eventid_translate as $old_event_id=>$new_event_id)
		{
			$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, `value`, instance)
					select '$new_project_id', '$new_event_id', record, field_name, `value`, instance
					from redcap_data where project_id = $copyof_project_id and event_id = $old_event_id";
			db_query($sql);
		}
		## COPY DATA ACCESS GROUP ASSIGNMENTS
		if (count($groupid_array) > 0) {
			// Loop through all records and update their DAG assignment with the new group_ids
			foreach ($groupid_array as $old_id=>$new_id) {
				$sql = "update redcap_data set value = '".db_escape($new_id)."' where project_id = $new_project_id
						and field_name = '__GROUPID__' and value = '".db_escape($old_id)."'";
				db_query($sql);
			}
		}

		## COPY EDOCS: Move the "file" field type values separately (because the docs will have to be copied in the file system)
		$sql = "select distinct d.* from redcap_metadata m, redcap_data d where m.project_id = $new_project_id
				and m.project_id = d.project_id and m.field_name = d.field_name and m.element_type = 'file'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Make sure edoc_id is numerical. If so, copy file. If not, fix this corrupt data and don't copy file.
			$edoc_id = $row['value'];
			// Get edoc_id of new file copy
			$new_edoc_id = (is_numeric($edoc_id)) ? copyFile($edoc_id, $new_project_id) : '';
			// Set the new edoc_id value in the redcap_data table
			$sql = "update redcap_data set value = '$new_edoc_id' where project_id = {$row['project_id']} and event_id = {$row['event_id']}
					and record = '" . db_escape($row['record']) . "' and field_name = '{$row['field_name']}'";
			$sql .= " and instance ".($row['instance'] == '' ? "is NULL" : "= '".db_escape($row['instance'])."'");
			db_query($sql);
		}
	}

	// RANDOMIZATION: If using randomization, copy the basic randomization setup (but not the allocation tables)
	$sql = "select * from redcap_randomization where project_id = $copyof_project_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$sql = "insert into redcap_randomization (project_id, stratified, group_by, target_field, target_event,
			source_field1, source_event1, source_field2, source_event2, source_field3, source_event3, source_field4, source_event4,
			source_field5, source_event5, source_field6, source_event6, source_field7, source_event7, source_field8, source_event8,
			source_field9, source_event9, source_field10, source_event10, source_field11, source_event11, source_field12, source_event12,
			source_field13, source_event13, source_field14, source_event14, source_field15, source_event15)
			values ($new_project_id, ".checkNull($row['stratified']).", ".checkNull($row['group_by']).", ".checkNull($row['target_field']).", ".checkNull($eventid_translate[$row['target_event']]).", ".checkNull($row['source_field1']).", ".checkNull($eventid_translate[$row['source_event1']]).",
			".checkNull($row['source_field2']).", ".checkNull($eventid_translate[$row['source_event2']]).", ".checkNull($row['source_field3']).", ".checkNull($eventid_translate[$row['source_event3']]).", ".checkNull($row['source_field4']).", ".checkNull($eventid_translate[$row['source_event4']]).", ".checkNull($row['source_field5']).", ".checkNull($eventid_translate[$row['source_event5']]).",
			".checkNull($row['source_field6']).", ".checkNull($eventid_translate[$row['source_event6']]).", ".checkNull($row['source_field7']).", ".checkNull($eventid_translate[$row['source_event7']]).", ".checkNull($row['source_field8']).", ".checkNull($eventid_translate[$row['source_event8']]).", ".checkNull($row['source_field9']).", ".checkNull($eventid_translate[$row['source_event9']]).",
			".checkNull($row['source_field10']).", ".checkNull($eventid_translate[$row['source_event10']]).", ".checkNull($row['source_field11']).", ".checkNull($eventid_translate[$row['source_event11']]).", ".checkNull($row['source_field12']).", ".checkNull($eventid_translate[$row['source_event12']]).", ".checkNull($row['source_field13']).", ".checkNull($eventid_translate[$row['source_event13']]).",
			".checkNull($row['source_field14']).", ".checkNull($eventid_translate[$row['source_event14']]).", ".checkNull($row['source_field15']).", ".checkNull($eventid_translate[$row['source_event15']]).")";
		$q = db_query($sql);
	}
	
	// COPY DDP MAPPINGS
	$sql = "select * from redcap_ddp_mapping where project_id = $copyof_project_id";
	$q = db_query($sql);
	if (db_num_rows($q) > 0) {
		while ($row = db_fetch_assoc($q))
		{
			$sql = "insert into redcap_ddp_mapping (external_source_field_name, is_record_identifier, project_id, 
					event_id, field_name, temporal_field, preselect) values
					(".checkNull($row['external_source_field_name']).", ".checkNull($row['is_record_identifier']).", $new_project_id, 
					".checkNull($eventid_translate[$row['event_id']]).", ".checkNull($row['field_name']).", ".checkNull($row['temporal_field']).", ".checkNull($row['preselect']).")";
			db_query($sql);
		}
		$sql = "insert into redcap_ddp_preview_fields (project_id, field1, field2, field3, field4, field5) 
				select $new_project_id, field1, field2, field3, field4, field5 from redcap_ddp_preview_fields where project_id = $copyof_project_id";
		db_query($sql);
	}
	
	// Log this in the copied project
	if (!$isTemplate) {
		Logging::logEvent("","redcap_projects","MANAGE",$copyof_project_id,"project_id = $copyof_project_id", 
						  "Copy project as PID=$new_project_id (\"{$_POST['app_title']}\")", "", "", $copyof_project_id);
	}

	// Log this in the newly created project
	$copiedProjectTitle = strip_tags(db_result(db_query("select app_title from redcap_projects where project_id = $copyof_project_id"), 0));
	Logging::logEvent("","redcap_projects","MANAGE",$new_project_id,"project_id = $new_project_id",($isTemplate ? "Create project using template" : "Copy project from PID=$copyof_project_id (\"$copiedProjectTitle\")"));

	# USER RIGHTS
	// COPY USER RIGHTS (OF SINGLE USER OF ALL USERS)
	if (isset($_POST['username']) && $superusers_only_create_project && $super_user) {
		// Set username of the user requesting copy
		$single_user_copy = $_POST['username'];
	} else {
		// Set username of the user requesting copy
		$single_user_copy = $userid;
	}
	if ($isTemplate) {
		// ADD USER RIGHTS FOR CREATOR/REQUESTER ONLY (SINCE IT'S A TEMPLATE)
		$sql = "INSERT INTO redcap_user_rights (project_id, username, data_entry, design, data_quality_design, data_quality_execute,
				random_setup, random_dashboard, random_perform, mobile_app, mobile_app_download_data)
				VALUES ($new_project_id, '".db_escape($single_user_copy)."', '', 1, 1, 1, $randomization, $randomization, $randomization,
				".(($mobile_app_enabled && $api_enabled) ? '1' : '0').", ".(($mobile_app_enabled && $api_enabled) ? '1' : '0').")";
		$q = db_query($sql);
	} else {
        // Copy this user (and others, if applicable)
        $sql = "insert into redcap_user_rights (project_id, username, expiration, role_id, group_id, lock_record, lock_record_multiform, data_export_tool,
				data_import_tool, data_comparison_tool, data_logging, file_repository, double_data, user_rights, data_access_groups, graphical,
				reports, design, calendar, data_entry, record_create, record_rename, record_delete, participants, data_quality_design, data_quality_execute,
				data_quality_resolution, random_setup, random_dashboard, random_perform)
				select '$new_project_id', username, expiration, role_id, group_id, lock_record, lock_record_multiform, data_export_tool, data_import_tool,
				data_comparison_tool, data_logging, file_repository, double_data, user_rights, data_access_groups, graphical, reports, design,
				calendar, data_entry, record_create, record_rename, record_delete, participants, data_quality_design, data_quality_execute,
				data_quality_resolution, random_setup, random_dashboard, random_perform
				from redcap_user_rights where project_id = $copyof_project_id";
        if (isset($_POST['copy_users']) && $_POST['copy_users'] == "on") {
            // Copy all users
            $q = db_query($sql);
        } else {
            // Only copy the current normal user
            $q = db_query($sql . " and username = '$single_user_copy'");
        }
        // For super users that were not originally on the project being copied, make sure they get added as well
        if ($super_user && $single_user_copy == $userid) {
            // Give default rights for everything since they're a super user and can access everything anyway
            $sql = "insert into redcap_user_rights (project_id, username, mobile_app, mobile_app_download_data)
					values ($new_project_id, '" . db_escape($userid) . "',
					" . (($mobile_app_enabled && $api_enabled) ? '1' : '0') . ", " . (($mobile_app_enabled && $api_enabled) ? '1' : '0') . ")";
            $q = db_query($sql);
        } // If the current user is a normal user and is also in a role, make sure we first given them their role's privileges before we remove them from the role in the new project
        elseif (!$super_user) {
            $oldRights = UserRights::getPrivileges($copyof_project_id, $userid);
            $oldRights = $oldRights[$copyof_project_id][$userid];
            if ($oldRights['role_id'] != '') {
                unset($oldRights['role_id'], $oldRights['group_id'], $oldRights['api_token'], $oldRights['external_module_config'], $oldRights['role_name'], $oldRights['forms'], $oldRights['project_id'], $oldRights['username']);
                $sql = array();
                foreach ($oldRights as $key => $val) {
                    $sql[] = "$key = " . checkNull($val);
                }
                $sql = "update redcap_user_rights set " . implode(", ", $sql) . " 
                        where project_id = $new_project_id and username = '" . db_escape($userid) . "'";
                $q = db_query($sql);
            }
        }
        // Loop through all users and update their rights with the new group_ids
        if (count($groupid_array) > 0) {
            foreach ($groupid_array as $old_id => $new_id) {
                db_query("update redcap_user_rights set group_id = $new_id where group_id = $old_id and project_id = $new_project_id");
            }
        }
        // Loop through all users and update their rights with the new role_ids
        if (count($userRoleId_array) > 0 && isset($_POST['copy_roles']) && $_POST['copy_roles'] == "on") {
            foreach ($userRoleId_array as $old_id => $new_id) {
                db_query("update redcap_user_rights set role_id = $new_id where role_id = $old_id and project_id = $new_project_id");
            }
        } else {
            // If we're not copying roles, make sure no user is in a role that might've been copied
            db_query("update redcap_user_rights set role_id = null where project_id = $new_project_id");
        }
		// ALWAYS make sure that the user doing the copying has access to User Rights, Setup/Design, and is NOT in a role
		db_query("update redcap_user_rights set user_rights = 1, design = 1, role_id = null,
				  mobile_app = ".(($mobile_app_enabled && $api_enabled) ? '1' : '0').",
				  mobile_app_download_data = ".(($mobile_app_enabled && $api_enabled) ? '1' : '0')."
				  where username = '".db_escape($userid)."' and project_id = $new_project_id");
	}

	if(isset($_POST['copy_folders']) && $_POST['copy_folders'] == '1')
	{
		$user = isset($_POST['username']) ? $_POST['username'] : $userid;
		ProjectFolders::copyProjectFolders(User::getUserInfo($user), $copyof_project_id, $new_project_id);
	}

	// COPY ANY EXTERNAL MODULES ENABLED FOR PROJECT (BUT LEAVE THEM DISABLED BY DEFAULT)
	if (isset($_POST['copy_module_settings']) && $_POST['copy_module_settings'] == "on") 
	{
		if (method_exists('\ExternalModules\ExternalModules', 'copySettings')) {
			\ExternalModules\ExternalModules::copySettings($copyof_project_id, $new_project_id);
		}
		else{
			$sql = "insert into redcap_external_module_settings (external_module_id, project_id, `key`, type, value)
				   select external_module_id, '$new_project_id', `key`, type, value from redcap_external_module_settings
				   where project_id = $copyof_project_id and `key` != 'enabled'";
			db_query($sql);
		}
	}

    // If user requested copy, then send user email confirmation of copy
	if (isset($_POST['username']) && $superusers_only_create_project && $super_user) {
		// Email the user requesting this db
		$email = new Message();
		$email->setFrom($project_contact_email);
		$email->setFromName($GLOBALS['project_contact_name']);
		$email->setTo($_POST['user_email']);
		if ($isTemplate) {
			// Create project email
			$emailSubject  =   "[REDCap] {$lang['create_project_32']}";
			$emailContents =   "{$lang['create_project_33']}
								<b>" . html_entity_decode($_POST['app_title'], ENT_QUOTES) . "</b>.<br><br>
								<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$new_project_id&msg=newproject'>{$lang['create_project_31']}</a>";
		} else {
			// Copy project email
			$emailSubject  =   "[REDCap] {$lang['create_project_28']}";
			$emailContents =   "{$lang['create_project_30']}
								<b>" . html_entity_decode($_POST['app_title'], ENT_QUOTES) . "</b>.<br><br>
								<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$new_project_id&msg=newproject'>{$lang['create_project_31']}</a>";
		}
		$email->setBody($emailContents, true);
		$email->setSubject($emailSubject);
		$email->send();
		//update redcap_todo_list
		ToDoList::updateTodoStatusNewProject((int)$_POST['request_id'], $new_project_id);
		// Commit to db
		db_query("COMMIT");db_query("SET AUTOCOMMIT=1");
		// Redirect super user to a confirmation page
		redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=approved_copy&user_email=" . $_POST['user_email']);
	}

}

/**
 * CREATING A NEW PROJECT
 */
else {
	// Determine project creation type
	$odmUpload = (isset($_FILES['odm']) && $_FILES['odm']['size'] > 0);
	$odmEdocId = (isset($_POST['odm_edoc_id']) && is_numeric($_POST['odm_edoc_id']));
	// Logging
	$logDescrip = "Create project";
	if ($odmEdocId	|| $odmUpload) $logDescrip .= " using REDCap XML file";
	elseif ($ehrDataMartProject) $logDescrip .= " (Clinical Data Mart)";
	Logging::logEvent("","redcap_projects","MANAGE",$new_project_id,"project_id = $new_project_id",$logDescrip);

	// Message flag used for dialog pop-up
	$msg_flag = "newproject";

	// ODM file import or EHR data mart (which also using ODM file)
	if ($odmEdocId	|| $odmUpload || $ehrDataMartProject) {
		// Set flag
		define("CREATE_PROJECT_ODM", true);
		// Get contents from file
		if ($ehrDataMartProject) {
			$ehrDataMartOdm = APP_PATH_DOCROOT."Resources/misc/redcap_fhir_data_mart.xml";
			$odm = file_get_contents($ehrDataMartOdm);
			if (empty($odm)) {
				db_query("ROLLBACK");
				db_query("SET AUTOCOMMIT=1");
				exit("ERROR: Could not find the following file on the server: $ehrDataMartOdm");
			}
		} elseif ($odmUpload) {
			$odm = file_get_contents($_FILES['odm']['tmp_name']);
			unlink($_FILES['odm']['tmp_name']);
		} else {
			list ($odm_mime_type, $odm_doc_name, $odm) = Files::getEdocContentsAttributes($_POST['odm_edoc_id']);
		}
		// Get uploaded file's contents and parse it
		$odm_response = ODM::parseOdm($odm);
		$errors = $odm_response['errors'];
		// EHR Data Mart: Retrieve EHR data and add to new project
		if (empty($errors) && $ehrDataMartProject) 
		{
			try {
				$dataMart = new DataMart();
				$dataMartSettings = $_POST['datamart'];
				if($request_id = $dataMartSettings['request_id'])
				{
					// approving a revision request
					$revision = $dataMart->getRevisionFromRequest($request_id);
					if($revision) {
						$revision = $revision->setProjectId($new_project_id);
						$revision = $dataMart->approveRevision($revision); //approve revision and save to database
					}
				}else
				{
					// add the first revision to the project
					$revision = $dataMart->addRevision(array(
						'user_id' => User::getUIIDByUsername($userid),
						'project_id' => $new_project_id,
						'mrns' => $dataMartSettings['mrns'],
						'date_min' => $dataMartSettings['daterange']['min'],
						'date_max' => $dataMartSettings['daterange']['max'],
						'fields' => $dataMartSettings['fields'],
					));
				}
			} catch (\Exception $e) {
				$errors = array('datamart' => $e->getMessage());
			}
		}
		// Check for errors
		if (!empty($errors))
		{
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->PrintHeaderExt();
			// TABS			
			include APP_PATH_VIEWS . 'HomeTabs.php';			
			// Errors
			print RCView::div(array('style'=>'text-align:left;margin:60px 0;width:100%;max-width:800px;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;margin:5px 0;'), $lang['create_project_129']) .
					"<ul><li>" . implode("</li><li>", $errors) . "</li></ul>"
				  );
			$objHtmlPage->PrintFooter();
			// Undo all changes if any errors occur
			db_query("ROLLBACK");
			db_query("SET AUTOCOMMIT=1");
			deleteProjectNow($new_project_id, false);
			exit;
		}
		
		// Set $form_names array
		$form_names = array();
		$sql = "select distinct form_name from redcap_metadata where project_id = $new_project_id";
		$q = db_query($sql);
		while ($rowf = db_fetch_assoc($q)) {
			$form_names[] = $rowf['form_name'];
		}
	}
	else
	{
		// Give this new project an arm and an event (default)
		Project::insertDefaultArmAndEvent($new_project_id);
		// Now add the new project's metadata
		$form_names = createMetadata($new_project_id, $_POST['surveys_enabled']);
	}

	## USER RIGHTS
	if (isset($_POST['username']) && $superusers_only_create_project && $super_user)
	{
		// Insert user rights for this new project for user REQUESTING the project
		$mobile_app = ($mobile_app_enabled && $api_enabled) ? 1 : 0;
		Project::insertUserRightsProjectCreator($new_project_id, $_POST['username'], 0, $mobile_app, $form_names);

		// Email the user requesting this db
		$email = new Message();
		$email->setFrom($project_contact_email);
		$email->setFromName($GLOBALS['project_contact_name']);
		$email->setTo($_POST['user_email']);
		$emailSubject  =   "[REDCap] {$lang['create_project_32']}";
		$emailContents =   "{$lang['create_project_33']}
							<b>" . html_entity_decode($_POST['app_title'], ENT_QUOTES) . "</b>.<br><br>
							<a href='" . APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/ProjectSetup/index.php?pid=$new_project_id&msg=newproject'>{$lang['create_project_31']}</a>";
		$email->setBody($emailContents, true);
		$email->setSubject($emailSubject);
		//update redcap_todo_list first
		ToDoList::updateTodoStatusNewProject((int)$_POST['request_id'], $new_project_id);
		$email->send();

		// Commit to db
		db_query("COMMIT");db_query("SET AUTOCOMMIT=1");
		// Redirect super user to a confirmation page
		redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=approved_new&new_pid=$new_project_id&user_email=" . $_POST['user_email']);
	}
	else
	{
		// Insert user rights for this new project for user CREATING the project
		$mobile_app = ($mobile_app_enabled && $api_enabled) ? 1 : 0;
		Project::insertUserRightsProjectCreator($new_project_id, $userid, $_POST['randomization'], $mobile_app, $form_names);
	}
}


// Commit to db
db_query("COMMIT");db_query("SET AUTOCOMMIT=1");
// Redirect to the new project
redirect(APP_PATH_WEBROOT . "ProjectSetup/index.php?pid=$new_project_id&msg=$msg_flag");
