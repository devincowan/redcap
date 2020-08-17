<?php
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Determines the field root, suffix value, and length for padding.
function determine_repeat_parts($field_name) {
    // See if it ends in digits
    $re = "/^(.+)(\\d+)?$/U";
    preg_match($re, $field_name, $matches);
    if (isset($matches[2])) {
        $match = $matches[1];
        // Determine the interger value and how many characters it is counter is (e.g. 001 vs 01 vs 1)
        $root = $matches[1];
        $suffix_value = intval($matches[2]);
        $suffix_length = strlen($matches[2]);
    } else {
        $root = $matches[1] . '_'; // The entire field + a spacer
        $suffix_value = 1;         // A blank value is considered 1
        $suffix_length = 1;        // No padding
    }
    return array($root, $suffix_value, $suffix_length);
}

if (isset($_GET['field_name'])) {

	// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
	$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
	
    list($root, $num, $padding) = determine_repeat_parts($_GET['field_name']);
    do {
        $num++;
        $suffix_padded = str_pad($num, $padding, '0', STR_PAD_LEFT);
        $new_field_name = $root . $suffix_padded;

        $varExists = db_result(db_query("select count(1) from $metadata_table where project_id = $project_id and field_name = '$new_field_name'"), 0);
    } while ($varExists);

	// Get original question's field_order and reset the field_order for all fields
	$this_field_order = db_result(db_query("select field_order from $metadata_table where project_id = $project_id and field_name = '{$_GET['field_name']}' limit 1"), 0);
	db_query("update $metadata_table set field_order = field_order + 1 where project_id = $project_id and field_order > $this_field_order");
	$new_field_order = $this_field_order + 1;

	// Copy the field from original (while setting new field_name and field_order)
	$sql = "insert into $metadata_table (project_id, field_name, field_phi, form_name, form_menu_description, field_order,
			field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
			element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req,
			edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline)
			select project_id, '$new_field_name', field_phi, form_name, NULL, '$new_field_order',
			field_units, NULL, element_type, element_label, element_enum, element_note, element_validation_type,
			element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req,
			NULL, edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline from $metadata_table
			where project_id = $project_id and field_name = '{$_GET['field_name']}'";
	$q1 = db_query($sql);

	## COPY EDOC FILE: If field has edoc file attachment, copy the file on the web server and generate new edoc_id
	// Get current edoc_id of source field
	$sql = "select edoc_id from $metadata_table where project_id = $project_id and field_name = '{$_GET['field_name']}' limit 1";
	$q = db_query($sql);
	$edoc_id = (db_num_rows($q) > 0) ? db_result($q, 0) : null;
	// If edoc_id exists, then copy file on server
	$new_edoc_id = copyFile($edoc_id);
	if (is_numeric($new_edoc_id))
	{
		// Now update new field's edoc_id value
		$sql = "update $metadata_table set edoc_id = $new_edoc_id where project_id = $project_id and field_name = '$new_field_name'";
		$q = db_query($sql);
	}

	// Logging
	if ($q1) Logging::logEvent($sql,$metadata_table,"MANAGE",$_GET['field_name'],"field_name = '{$_GET['field_name']}'","Copy project field");

	// Give response back
	print ($q1 ? $new_field_name : '0');

}
