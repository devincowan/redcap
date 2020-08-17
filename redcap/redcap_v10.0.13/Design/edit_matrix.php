<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = '0';

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// Check if we have all values needed
if (!(isset($_POST['choices']) && isset($_POST['grid_name']) && isset($_POST['labels']) && isset($_POST['fields']))
	|| !preg_match("/^[a-z0-9_]+$/", $_POST['grid_name'])
) {
	exit('0');
}

// Check new form_name value to see if it already exists. If so, unset the value to mimic field-adding behavior for an existing form.
if (!empty($_POST['add_form_name'])) {
	$formExists = db_result(db_query("select count(1) from $metadata_table where project_id = $project_id
			and form_name = '".db_escape($form)."' limit 1"), 0);
	if ($formExists) unset($_POST['add_form_name']);
} else {
	unset($_POST['add_form_name']);
}

// Convert and filter input values
$field_type = ($_POST['field_type'] == 'radio') ? 'radio' : 'checkbox';
$form = $_POST['form'];
$section_header = $_POST['section_header'];
$next_field = trim($_POST['next_field']);
$grid_name = trim($_POST['grid_name']);
$old_grid_name = trim($_POST['old_grid_name']);

$choices_array = array();
foreach (parseEnum(trim($_POST['choices'])) as $code=>$label) {
	$choices_array[] = "$code, $label";
}
$choices = implode(" \\n ", $choices_array);

$labels_array = array();
if (substr($_POST['labels'], -1) == "\n") $_POST['labels'] = substr($_POST['labels'], 0, -1);
foreach (explode("\n", $_POST['labels']) as $label) {
	$label = trim($label);
	$labels_array[] = decode_filter_tags($label);
}

$fields_array = array();
$fields_array_sql = array();
if (substr($_POST['fields'], -1) == "\n") $_POST['fields'] = substr($_POST['fields'], 0, -1);
foreach (explode("\n", $_POST['fields']) as $field) {
	// Each variable name must only be letters, numbers, and underscores
	$field = trim($field);
	if ($field == '' || !preg_match("/^[a-z0-9_]+$/", $field)) {
		exit('0');
	}
	$fields_array[] = $field;
	$fields_array_sql[] = db_escape($field);
}

$field_req_array = array();
if (substr($_POST['field_reqs'], -1) == "\n") $_POST['field_reqs'] = substr($_POST['field_reqs'], 0, -1);
foreach (explode("\n", $_POST['field_reqs']) as $field_req) {
	$field_req_array[] = ($field_req != "1") ? "0" : "1";
}

// setup field ranking array for use on Online Designer Add Matrix
$field_rank_array = array();
if (substr($_POST['field_ranks'], -1) == "\n") $_POST['field_ranks'] = substr($_POST['field_ranks'], 0, -1);
foreach (explode("\n", $_POST['field_ranks']) as $field_rank) {
	$field_rank_array[] = ($field_rank != "1") ? "0" : "1";
}


$ques_num_array = array();
if (substr($_POST['ques_nums'], -1) == "\n") $_POST['ques_nums'] = substr($_POST['ques_nums'], 0, -1);
$ques_num_array = explode("\n", $_POST['ques_nums']);

$field_annotation_array = explode("|-RCANNOT-|", trim($_POST['field_annotations']));


## ARE WE ADDING OR EDITING A MATRIX?
$existing_matrix = (isset($_POST['current_field']) && $_POST['current_field'] != '');
if ($existing_matrix) {
	## EDITING EXISTING MATRIX
	// Get field order of first field in matrix
	$matrix_field_names = ($status > 0) ? $Proj->matrixGroupNamesTemp[$old_grid_name] : $Proj->matrixGroupNames[$old_grid_name];
	if (empty($matrix_field_names)) exit('0');
	$firstFieldInMatrix = $matrix_field_names[0];
	$new_field_order = ($status > 0) ? $Proj->metadata_temp[$firstFieldInMatrix]['field_order'] : $Proj->metadata[$firstFieldInMatrix]['field_order'];
	// Get the form menu lable for the first matrix field (if it has one) so we can preserve it
	$form_menu_label = ($status > 0) ? $Proj->metadata_temp[$firstFieldInMatrix]['form_menu_description'] : $Proj->metadata[$firstFieldInMatrix]['form_menu_description'];
	// Now delete all these fields from metadata so we can re-add them below
	$sql = "delete from $metadata_table where project_id = $project_id and field_name in ('".implode("', '", $matrix_field_names)."')";
	db_query($sql);
	// Decrease field_order of all fields after the matrix we just deleted (so that there is no gap before we re-add them)
	$sql = "update $metadata_table set field_order = field_order - ".count($matrix_field_names)."
			where project_id = $project_id and field_order >= $new_field_order";
	$q = db_query($sql);
} else {
	## ADDING MATRIX
	// GET NEW FIELD ORDER
	// Get the form menu label for $next_field (if it has one) so we can move it to the first field in the next matrix
	$form_menu_label = ($status > 0 && $next_form_menu_desc)
		? $Proj->metadata_temp[$next_field]['form_menu_description']
		: (isset($Proj->metadata[$next_field]) ? $Proj->metadata[$next_field]['form_menu_description'] : '');
	// Determine if adding to very bottom of table or not. If so, get position of last field on form + 1
	if ($next_field == '') {
		$sql = "select max(field_order) from $metadata_table where project_id = $project_id and form_name = '".db_escape($form)."'";
	// Obtain the destination field's field_order value (i.e. field_order of field that will be located after this new one)
	} else {
		$sql = "select field_order from $metadata_table where project_id = $project_id and field_name = '".db_escape($next_field)."' limit 1";
	}
	// Get the following question's field order
	$new_field_order = db_result(db_query($sql), 0);
	// If we are adopting a SH for this next matrix from the field immediately following it, then set that field's SH to null
	if (isset($_POST['sectionHeaderAdopt']) && !empty($_POST['sectionHeaderAdopt'])) {
		$sql = "update $metadata_table set element_preceding_header = null where project_id = $project_id
				and field_name = '".db_escape($_POST['sectionHeaderAdopt'])."'";
		db_query($sql);
	}
}


// Increase field_order of all fields after these new ones
$sql = "update $metadata_table set field_order = field_order + ".count($fields_array)."
		where project_id = $project_id and field_order >= $new_field_order";
$q = db_query($sql);
// Make sure the grid_name doesn't already exist
$sql = "select 1 from $metadata_table where project_id = $project_id and grid_name = '".db_escape($grid_name)."' limit 1";
if (db_num_rows(db_query($sql))) exit('0');
// Make sure the variable names don't already exist
$sql = "select 1 from $metadata_table where project_id = $project_id and field_name in ('".implode("', '", $fields_array_sql)."') limit 1";
if (db_num_rows(db_query($sql))) exit('0');


## SAVE FIELDS
$errors = 0;
$counter = 0;
$form_menu_label = label_decode($form_menu_label);
$sql_all = array();
// Loop through each field
foreach ($fields_array as $i=>$field)
{
	// Get label and field req value for this variable
	$label = $labels_array[$i];
	$field_req = $field_req_array[$i];
	$field_rank = $field_rank_array[$i]; // also get the new field_rank value for this variable
	$question_num = $ques_num_array[$i];
	$field_annotation = $field_annotation_array[$i];
	// Set section header or form menu label for first field in group, if has value
	$this_sh = ($counter == 0) ? $section_header : "";
	$this_form_label = ($counter == 0) ? $form_menu_label : "";
	// Preserve any existing values for fields
	$branching_logic = ($status > 0)
		? $Proj->metadata_temp[$field]['branching_logic']
		: (isset($Proj->metadata[$field]) ? $Proj->metadata[$field]['branching_logic'] : '');
	$stop_actions = ($status > 0)
		? $Proj->metadata_temp[$field]['stop_actions']
		: (isset($Proj->metadata[$field]) ? $Proj->metadata[$field]['stop_actions'] : '');
	// Query to create new field
	$sql_all[] = $sql = "insert into $metadata_table (project_id, field_name, field_phi, form_name, form_menu_description, field_order,
						field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
						element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req,
						edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc)
						values
						($project_id, '".db_escape($field)."', NULL, "
					 . "'".db_escape($form)."', ".checkNull($this_form_label).", '".$new_field_order++."', NULL, ".checkNull($this_sh).", '"
					 . db_escape($field_type)."', " . checkNull($label) . ", " . checkNull($choices) . ", NULL, NULL, NULL, NULL, NULL, "
					 . checkNull($branching_logic) . ", " . "'" . db_escape($field_req) . "', NULL, 0, NULL, " . checkNull($stop_actions)
					 . ", " . checkNull($question_num) . ", " . checkNull($grid_name) . ", " . checkNull($field_rank) . ", "
					 . checkNull($field_annotation) . ")";
	if (db_query($sql)) {
		$response = '1';
	} else {
		$errors++;
	}
	// Increment counter
	$counter++;
}

// If creating a new form, also add Form Status field
if (isset($_POST['add_form_name']))
{
	// Add the Form Status field
	$sql = "insert into $metadata_table (project_id, field_name, form_name, field_order, element_type,
			element_label, element_enum, element_preceding_header) values ($project_id, '{$form}_complete',
			'$form', '".($new_field_order++)."', 'select', 'Complete?',
			'0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
	$q = db_query($sql);
	// Logging
	if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$form,"form_name = '$form'","Create data collection instrument");
	// Only if in Development...
	if ($status == 0) {
		// Grant all users full access rights (default) to the new form
		$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[$form,1]') where project_id = $project_id";
		db_query($sql);
		// Add new forms to events_forms table ONLY if not longitudinal (if longitudinal, user will designate form for events later)
		if (!$longitudinal) {
			$sql = "insert into redcap_events_forms (event_id, form_name) select e.event_id, '$form' from redcap_events_metadata e,
					redcap_events_arms a where a.arm_id = e.arm_id and a.project_id = $project_id limit 1";
			db_query($sql);
		}
	}
}

## FORM MENU: Always make sure the form_menu_description value stays only with first field on form
// Set all field's form_menu_description as NULL
$sql = "update $metadata_table set form_menu_description = NULL where project_id = $project_id and form_name = '{$form}'";
db_query($sql);
// Now set form_menu_description for first field
$form_menu = ($status > 0) ? $Proj->forms_temp[$form]['menu'] : $Proj->forms[$form]['menu'];
$sql = "update $metadata_table set form_menu_description = '".db_escape(label_decode($form_menu))."'
		where project_id = $project_id and form_name = '{$form}' order by field_order limit 1";
db_query($sql);

// Check for sql errors
if ($errors > 0) {
	exit('0');
} else {
	// Check if the table_pk has changed during the recording. If so, give back different response so as to inform the user of change.
	if (Design::recordIdFieldChanged()) {
		$response = '2';
	} elseif (Design::checkDisableSurveyQuesAutoNum($form)) {
		$response = '3';
	}
	// Log this event
	$fieldNamesLog = "grid_name = '$grid_name'\nfield_name = '" . implode("'\nfield_name = '", $fields_array) . "'";
	$descrLog = ($existing_matrix) ? "Edit matrix of fields" : "Create matrix of fields";
	Logging::logEvent(implode(";\n", $sql_all),$metadata_table,"MANAGE",$grid_name,$fieldNamesLog,$descrLog);
	// Return successful response
	exit($response);
}

