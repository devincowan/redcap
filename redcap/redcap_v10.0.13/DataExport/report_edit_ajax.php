<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Count errors
$errors = 0;

// Validate report_id and see if already exists
$report_id = (int)$_GET['report_id'];
if ($report_id != 0) {
	$report = DataExport::getReports($report_id);
	if (empty($report)) exit('0');
}

// Report title
$title = strip_tags(label_decode($_POST['__TITLE__']));
$description = filter_tags(label_decode($_POST['description']));
// User access rights
$user_access_users = $user_access_roles = $user_access_dags = array();
if (isset($_POST['user_access_users'])) {
	$user_access_users = $_POST['user_access_users'];
	if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
}
if (isset($_POST['user_access_roles'])) {
	$user_access_roles = $_POST['user_access_roles'];
	if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
}
if (isset($_POST['user_access_dags'])) {
	$user_access_dags = $_POST['user_access_dags'];
	if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
}
$user_access = ($_POST['user_access_radio'] == 'SELECTED'
				&& (count($user_access_users) + count($user_access_roles) + count($user_access_dags)) > 0) ? 'SELECTED' : 'ALL';
// User EDIT access rights
$user_edit_access_users = $user_edit_access_roles = $user_edit_access_dags = array();
if (isset($_POST['user_edit_access_users'])) {
	$user_edit_access_users = $_POST['user_edit_access_users'];
	if (!is_array($user_edit_access_users)) $user_edit_access_users = array($user_edit_access_users);
}
if (isset($_POST['user_edit_access_roles'])) {
	$user_edit_access_roles = $_POST['user_edit_access_roles'];
	if (!is_array($user_edit_access_roles)) $user_edit_access_roles = array($user_edit_access_roles);
}
if (isset($_POST['user_edit_access_dags'])) {
	$user_edit_access_dags = $_POST['user_edit_access_dags'];
	if (!is_array($user_edit_access_dags)) $user_edit_access_dags = array($user_edit_access_dags);
}
$user_edit_access = ($_POST['user_edit_access_radio'] == 'SELECTED'
				&& (count($user_edit_access_users) + count($user_edit_access_roles) + count($user_edit_access_dags)) > 0) ? 'SELECTED' : 'ALL';
// Sort fields
$orderby_field1 = (isset($Proj->metadata[$_POST['sort'][0]])) ? $_POST['sort'][0] : '';
$orderby_sort1 = ($orderby_field1 == '') ? '' : $_POST['sortascdesc'][0];
$orderby_field2 = (isset($Proj->metadata[$_POST['sort'][1]])) ? $_POST['sort'][1] : '';
$orderby_sort2 = ($orderby_field2 == '') ? '' : $_POST['sortascdesc'][1];
$orderby_field3 = (isset($Proj->metadata[$_POST['sort'][2]])) ? $_POST['sort'][2] : '';
$orderby_sort3 = ($orderby_field3 == '') ? '' : $_POST['sortascdesc'][2];
// If the first or second sort field is blank, then skip it
if ($orderby_field2 == '' && $orderby_field3 != '') {
	$orderby_field2 = $orderby_field3;
	$orderby_sort2 = $orderby_sort3;
	$orderby_field3 = $orderby_sort3 = '';
}
if ($orderby_field1 == '' && $orderby_field2 != '') {
	$orderby_field1 = $orderby_field2;
	$orderby_sort1 = $orderby_sort2;
	$orderby_field2 = $orderby_field3;
	$orderby_sort2 = $orderby_sort3;
	$orderby_field3 = $orderby_sort3 = '';
}
// Live Filters
$livefilter_field1 = ($_POST['livefilter'][0] == DataExport::LIVE_FILTER_EVENT_FIELD || $_POST['livefilter'][0] == DataExport::LIVE_FILTER_DAG_FIELD || isset($Proj->metadata[$_POST['livefilter'][0]])) ? $_POST['livefilter'][0] : '';
$livefilter_field2 = ($_POST['livefilter'][1] == DataExport::LIVE_FILTER_EVENT_FIELD || $_POST['livefilter'][1] == DataExport::LIVE_FILTER_DAG_FIELD || isset($Proj->metadata[$_POST['livefilter'][1]])) ? $_POST['livefilter'][1] : '';
$livefilter_field3 = ($_POST['livefilter'][2] == DataExport::LIVE_FILTER_EVENT_FIELD || $_POST['livefilter'][2] == DataExport::LIVE_FILTER_DAG_FIELD || isset($Proj->metadata[$_POST['livefilter'][2]])) ? $_POST['livefilter'][2] : '';
// If the first or second live filter is blank, then skip it
if ($livefilter_field2 == '' && $livefilter_field3 != '') {
	$livefilter_field2 = $livefilter_field3;
	$livefilter_field3 = '';
}
if ($livefilter_field1 == '' && $livefilter_field2 != '') {
	$livefilter_field1 = $livefilter_field2;
	$livefilter_field2 = $livefilter_field3;
	$livefilter_field3 = '';
}
// Filter type
$filter_type = (isset($_POST['filter_type']) && $_POST['filter_type'] == 'on') ? 'RECORD' : 'EVENT';
// Options to include DAG names and/or survey fields in report
$outputDags = (isset($_POST['output_dags']) && $_POST['output_dags'] == 'on') ? '1' : '0';
$outputSurveyFields = (isset($_POST['output_survey_fields']) && $_POST['output_survey_fields'] == 'on') ? '1' : '0';
$outputMissingDataCodes = (isset($_POST['output_missing_data_codes']) && !empty($missingDataCodes) && $_POST['output_missing_data_codes'] == 'on') ? '1' : '0';
$removeLineBreaksInValues = (isset($_POST['remove_line_breaks_in_values']) && $_POST['remove_line_breaks_in_values'] == 'on') ? '1' : '0';
$combine_checkbox_values = (isset($_POST['combine_checkbox_values']) && $_POST['combine_checkbox_values'] == 'on') ? '1' : '0';
// Check for advanced logic or simple logic
$advanced_logic = '';
if (isset($_POST['advanced_logic']) && trim($_POST['advanced_logic']) != '') {
	$advanced_logic = $_POST['advanced_logic'];
}

// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");

// Save report in reports table
if ($report_id != 0) {
	// Update
	$sqlr = $sql = "update redcap_reports set title = '".db_escape($title)."', user_access = '".db_escape($user_access)."',
			user_edit_access = '".db_escape($user_edit_access)."',
			description = ".checkNull($description).", combine_checkbox_values = '".db_escape($combine_checkbox_values)."',
			orderby_field1 = ".checkNull($orderby_field1).", orderby_sort1 = ".checkNull($orderby_sort1).",
			orderby_field2 = ".checkNull($orderby_field2).", orderby_sort2 = ".checkNull($orderby_sort2).",
			orderby_field3 = ".checkNull($orderby_field3).", orderby_sort3 = ".checkNull($orderby_sort3).",
			output_dags = ".checkNull($outputDags).", output_survey_fields = ".checkNull($outputSurveyFields).",
			advanced_logic = ".checkNull($advanced_logic).", filter_type = ".checkNull($filter_type).",
			dynamic_filter1 = ".checkNull($livefilter_field1).", dynamic_filter2 = ".checkNull($livefilter_field2).",
			dynamic_filter3 = ".checkNull($livefilter_field3).", output_missing_data_codes = ".checkNull($outputMissingDataCodes).", 
			remove_line_breaks_in_values = ".checkNull($removeLineBreaksInValues)."
			where project_id = ".PROJECT_ID." and report_id = $report_id";
	if (!db_query($sql)) $errors++;
} else {
	// Get next report_order number
	$q = db_query("select max(report_order) from redcap_reports where project_id = ".PROJECT_ID);
	$new_report_order = db_result($q, 0);
	$new_report_order = ($new_report_order == '') ? 1 : $new_report_order+1;
	// Insert
	$sqlr = $sql = "insert into redcap_reports (project_id, title, description, combine_checkbox_values, user_access, user_edit_access,
			orderby_field1, orderby_sort1, orderby_field2,
			orderby_sort2, orderby_field3, orderby_sort3, output_dags, output_survey_fields, report_order, filter_type, advanced_logic,
			dynamic_filter1, dynamic_filter2, dynamic_filter3, output_missing_data_codes, remove_line_breaks_in_values)
			values (".PROJECT_ID.", '".db_escape($title)."', ".checkNull($description).", '".db_escape($combine_checkbox_values)."', 
			'".db_escape($user_access)."', '".db_escape($user_edit_access)."', ".checkNull($orderby_field1).",
			".checkNull($orderby_sort1).", ".checkNull($orderby_field2).", ".checkNull($orderby_sort2).",
			".checkNull($orderby_field3).", ".checkNull($orderby_sort3).", ".checkNull($outputDags).",
			".checkNull($outputSurveyFields).", $new_report_order, ".checkNull($filter_type).", ".checkNull($advanced_logic).",
			".checkNull($livefilter_field1).", ".checkNull($livefilter_field2).", ".checkNull($livefilter_field3).", 
			".checkNull($outputMissingDataCodes).", ".checkNull($removeLineBreaksInValues).")";
	if (!db_query($sql)) $errors++;
	// Set new report_id
	$report_id = db_insert_id();
}

// USER ACCESS
$sql = "delete from redcap_reports_access_users where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_access_users as $this_user) {
	$sql = "insert into redcap_reports_access_users values ($report_id, '".db_escape($this_user)."')";
	if (!db_query($sql)) $errors++;
}
$sql = "delete from redcap_reports_access_roles where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_access_roles as $this_role_id) {
	$this_role_id = (int)$this_role_id;
	$sql = "insert into redcap_reports_access_roles values ($report_id, '".db_escape($this_role_id)."')";
	if (!db_query($sql)) $errors++;
}
$sql = "delete from redcap_reports_access_dags where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_access_dags as $this_group_id) {
	$this_group_id = (int)$this_group_id;
	$sql = "insert into redcap_reports_access_dags values ($report_id, '".db_escape($this_group_id)."')";
	if (!db_query($sql)) $errors++;
}
// USER ACCESS (EDIT)
$sql = "delete from redcap_reports_edit_access_users where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_edit_access_users as $this_user) {
	$sql = "insert into redcap_reports_edit_access_users values ($report_id, '".db_escape($this_user)."')";
	if (!db_query($sql)) $errors++;
}
$sql = "delete from redcap_reports_edit_access_roles where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_edit_access_roles as $this_role_id) {
	$this_role_id = (int)$this_role_id;
	$sql = "insert into redcap_reports_edit_access_roles values ($report_id, '".db_escape($this_role_id)."')";
	if (!db_query($sql)) $errors++;
}
$sql = "delete from redcap_reports_edit_access_dags where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_edit_access_dags as $this_group_id) {
	$this_group_id = (int)$this_group_id;
	$sql = "insert into redcap_reports_edit_access_dags values ($report_id, '".db_escape($this_group_id)."')";
	if (!db_query($sql)) $errors++;
}
// FIELDS & LIMITERS
$sql = "delete from redcap_reports_fields where report_id = $report_id";
if (!db_query($sql)) $errors++;
$field_order = 1;
foreach ($_POST['field'] as $this_field) {
	if ($this_field == '' || !isset($Proj->metadata[$this_field])) continue;
	$sql = "insert into redcap_reports_fields (report_id, field_name, field_order)
			values ($report_id, '".db_escape($this_field)."', ".($field_order++).")";
	if (!db_query($sql)) $errors++;
}
// Only do simple filter logic if not have advanced logic defined
if ($advanced_logic == '') {
	foreach ($_POST['limiter'] as $key=>$this_field)
	{
		if ($this_field == '' || !isset($Proj->metadata[$this_field])) continue;
		// Get event_id
		$limiter_event_id = ($longitudinal && isset($_POST['limiter_event'][$key])) ? $_POST['limiter_event'][$key] : '';
		// Check if field is a Text field with MDY or DMY date validation. If so, convert to YMD format before saving.
		$limiter_value = $_POST['limiter_value'][$key];
		if ($limiter_value != '' && isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['element_type'] == 'text'
			&& substr($Proj->metadata[$this_field]['element_validation_type'], 0, 4) == "date"
			&& (substr($Proj->metadata[$this_field]['element_validation_type'], -4) == "_dmy" || substr($Proj->metadata[$this_field]['element_validation_type'], -4) == "_mdy"))
		{
			$thisValType = $Proj->metadata[$this_field]['element_validation_type'];
			if (in_array($thisValType, array('date_mdy', 'datetime_mdy', 'datetime_seconds_mdy', 'date_dmy', 'datetime_dmy', 'datetime_seconds_dmy'))) {
				$limiter_value = DateTimeRC::datetimeConvert($limiter_value, substr($thisValType, -3), 'ymd');
			}
		}
		$sql = "insert into redcap_reports_fields (report_id, field_name, field_order, limiter_group_operator, limiter_event_id,
				limiter_operator, limiter_value) values ($report_id, '".db_escape($this_field)."', ".($field_order++).",
				".checkNull($_POST['limiter_group_operator'][$key]).", ".checkNull($limiter_event_id).",
				".checkNull($_POST['limiter_operator'][$key]).", '".db_escape($limiter_value)."')";
		if (!db_query($sql)) $errors++;
	}
}
$sql = "delete from redcap_reports_filter_events where report_id = $report_id";
if (!db_query($sql)) $errors++;
if (isset($_POST['filter_events'])) {
	if (!is_array($_POST['filter_events'])) $_POST['filter_events'] = array($_POST['filter_events']);
	foreach ($_POST['filter_events'] as $this_event_id) {
		$this_event_id = (int)$this_event_id;
		$sql = "insert into redcap_reports_filter_events values ($report_id, '".db_escape($this_event_id)."')";
		if (!db_query($sql)) $errors++;
	}
}
$sql = "delete from redcap_reports_filter_dags where report_id = $report_id";
if (!db_query($sql)) $errors++;
if (isset($_POST['filter_dags'])) {
	if (!is_array($_POST['filter_dags'])) $_POST['filter_dags'] = array($_POST['filter_dags']);
	foreach ($_POST['filter_dags'] as $this_group_id) {
		$this_group_id = (int)$this_group_id;
		$sql = "insert into redcap_reports_filter_dags values ($report_id, '".db_escape($this_group_id)."')";
		if (!db_query($sql)) $errors++;
	}
}


// If there are errors, then roll back all changes
if ($errors > 0) {
	// Errors occurred, so undo any changes made
	db_query("ROLLBACK");
	// Return '0' for error
	exit('0');
} else {
	// Logging
	$log_descrip = ($_GET['report_id'] != 0) ? "Edit report" : "Create report";
	Logging::logEvent($sqlr, "redcap_projects", "MANAGE", $report_id, "report_id = $report_id", $log_descrip);
	// Commit changes
	db_query("COMMIT");
	// Response
	$dialog_title = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle')) .
						RCView::span(array('style'=>'color:green;vertical-align:middle'), $lang['report_builder_01']);
	$dialog_content = 	RCView::div(array('style'=>'font-size:14px;'),
							$lang['report_builder_73'] . " \"" .
							RCView::span(array('style'=>'font-weight:bold;'), RCView::escape($title)) .
							"\" " . $lang['report_builder_74']
						);
	// Output JSON response
	print json_encode_rc(array('report_id'=>$report_id, 'newreport'=>($_GET['report_id'] == 0 ? 1 : 0),
							'title'=>$dialog_title, 'content'=>$dialog_content));
}