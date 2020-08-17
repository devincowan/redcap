<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
// Determine if adding to very bottom of table or not
$is_last = ($_POST['this_sq_id'] == "") ? 1 : 0;
// Determine if editing an existing question or not
$edit_question = ($_POST['sq_id'] == "") ? 0 : 1;
// Determine if a section header rather than a real field
$is_section_header = (isset($_POST['field_type']) && $_POST['field_type'] == "section_header") ? 1 : 0;
// Determine if WAS a section header but was changed to a real field
$was_section_header = (!$is_section_header && isset($_POST['wasSectionHeader']) && $_POST['wasSectionHeader']);
// Default for table row in DOM that should be deleted
$delete_row = "";
// Required Field value
$_POST['field_req'] = ($_POST['field_req'] == "") ? 0 : $_POST['field_req'];
// Clean the variable name
$_POST['field_name'] = preg_replace("/[^0-9a-z_]/", "", strtolower($_POST['field_name']));
// Validate the form name
if (!($status > 0 ? isset($Proj->forms_temp[$_POST['form_name']]) : isset($Proj->forms[$_POST['form_name']]))) {
	// Send back JS error msg
	print  "<script type='text/javascript'>
			window.parent.window.alert('".js_escape($lang['global_01'])."');
			window.parent.window.location.reload();
			</script>";
	exit;
}
// Edoc_id value and video url
$_POST['edoc_id']   = (isset($_POST['field_type']) && $_POST['field_type'] == 'descriptive' && is_numeric($_POST['edoc_id'])) ? $_POST['edoc_id'] : "";
$_POST['video_url'] = (isset($_POST['field_type']) && $_POST['field_type'] == 'descriptive' && $_POST['edoc_id'] == '' && $_POST['video_url'] != '') ? trim($_POST['video_url']) : "";
$_POST['video_display_inline'] = (isset($_POST['video_display_inline']) && $_POST['video_display_inline'] == '1') ? '1' : '0';
// Remove any MC choices for descriptive fields
if ($_POST['field_type'] == 'descriptive') {
	$_POST['element_enum'] = "";
}

// Edoc image/file attachment display value
$_POST['edoc_display_img'] = ($_POST['field_type'] == 'descriptive' && is_numeric($_POST['edoc_id']) && is_numeric($_POST['edoc_display_img'])) ? $_POST['edoc_display_img'] : 0;
// Check custom alignment
$align_options = array('', 'LV', 'LH', 'RV', 'RH');
$_POST['custom_alignment'] = (isset($_POST['custom_alignment']) && $_POST['field_type'] != 'descriptive' && $_POST['field_type'] != 'section_header' && in_array($_POST['custom_alignment'], $align_options)) ? $_POST['custom_alignment'] : "";
// If field_type is missing, then set as Text field
if (!isset($_POST['field_type']) || (isset($_POST['field_type']) && $_POST['field_type'] == "")) {
	$_POST['field_type'] = 'text';
}
// Ontology auto-suggest: Add ontology name as element_enum attribute
$has_ontology_provider = OntologyManager::hasOntologyProviders();

$enable_ontology_auto_suggest_field = false;
if ($has_ontology_provider && $_POST['field_type'] == "text" && $_POST['ontology_auto_suggest'] != '') {
	$_POST['element_enum'] = $_POST['ontology_auto_suggest'];
	$enable_ontology_auto_suggest_field = true;
}

// If a text field with any kind of date validation with min/max range set, reformat min/max data value to YMD format when saving
if ($_POST['field_type'] == "text" && (substr($_POST['val_type'], -4) == "_mdy" || substr($_POST['val_type'], -4) == "_dmy"))
{
	// Check validation min
	if ($_POST['val_min'] != "") {
		// If has time component, remove it temporarily to convert the date separately
		$this_date = $_POST['val_min'];
		$this_time = "";
		if (substr($_POST['val_type'], 0, 8) == "datetime") {
			list ($this_date, $this_time) = explode(" ", $_POST['val_min']);
		}
		if (substr($_POST['val_type'], -4) == "_mdy") {
			$_POST['val_min'] = trim(DateTimeRC::date_mdy2ymd($this_date) . " " . $this_time);
		} else {
			$_POST['val_min'] = trim(DateTimeRC::date_dmy2ymd($this_date) . " " . $this_time);
		}
	}
	// Check validation max
	if ($_POST['val_max'] != "") {
		// If has time component, remove it temporarily to convert the date separately
		$this_date = $_POST['val_max'];
		$this_time = "";
		if (substr($_POST['val_type'], 0, 8) == "datetime") {
			list ($this_date, $this_time) = explode(" ", $_POST['val_max']);
		}
		if (substr($_POST['val_type'], -4) == "_mdy") {
			$_POST['val_max'] = trim(DateTimeRC::date_mdy2ymd($this_date) . " " . $this_time);
		} else {
			$_POST['val_max'] = trim(DateTimeRC::date_dmy2ymd($this_date) . " " . $this_time);
		}
	}
}


// SQL Field: Do extra server-side check to ensure that only super users can add/edit "sql" field types
if ($_POST['field_type'] == 'sql' && !$super_user)
{
	// Send back JS error msg
	print  "<script type='text/javascript'>
			window.parent.window.alert('".js_escape($lang['design_272'])."');
			window.parent.window.location.reload();
			</script>";
	exit;
}


// Set slider labels as val type, min, and max
if ($_POST['field_type'] == "slider") {
	$_POST['slider_label_left']   = trim($_POST['slider_label_left']);
	$_POST['slider_label_middle'] = trim($_POST['slider_label_middle']);
	$_POST['slider_label_right']  = trim($_POST['slider_label_right']);
	// Determine how to delimit the enum string
	$_POST['element_enum'] = trim("{$_POST['slider_label_left']} | {$_POST['slider_label_middle']} | {$_POST['slider_label_right']}");
	if ($_POST['element_enum'] == "|  |") $_POST['element_enum'] = "";
	// Set slider display value
	$_POST['val_type'] = ($_POST['slider_display_value'] == 'on') ? 'number' : '';
	// Set defaults for min/max as blank
	$_POST['val_min']  = "";
	$_POST['val_max']  = "";
}
// If a file upload 'signature' field, then add 'signature' as the validation type
elseif ($_POST['field_type'] == "file" && $_POST['isSignatureField'] == '1')
{
	$_POST['val_type'] = 'signature';
}
// Enable auto-complete for drop-downs?
elseif ($_POST['field_type'] == "select" || $_POST['field_type'] == "sql")
{
	$_POST['val_type'] = ($_POST['dropdown_autocomplete'] == 'on') ? "autocomplete" : "";
	// Set defaults for min/max as blank
	$_POST['val_min']  = "";
	$_POST['val_max']  = "";
}
// Make sure only text fields and sliders have any validation/slider labels (could get left over when changing field type)
elseif ($_POST['field_type'] != "text" && $_POST['field_type'] != "slider")
{
	$_POST['val_type'] = "";
	$_POST['val_min']  = "";
	$_POST['val_max']  = "";
}
// Make sure we restore legacy validation values when saving
elseif ($_POST['field_type'] == "text")
{
	if ($_POST['val_type'] == "number") {
		$_POST['val_type'] = "float";
	} elseif ($_POST['val_type'] == "integer") {
		$_POST['val_type'] = "int";
	}
}


// Determine if a section header rather than a real field
$is_section_header = ($_POST['field_type'] == "section_header") ? 1 : 0;

// If using matrix formatting for a radio or checkbox, capture the grid_name
$grid_name = "";
if (($_POST['field_type'] == "checkbox" || $_POST['field_type'] == "radio") &&
	((isset($_POST['grid_name_dd']) && $_POST['grid_name_dd'] != '') || (isset($_POST['grid_name_text']) && $_POST['grid_name_text'] != '')))
{
	$grid_name = ($_POST['grid_name_dd'] != '') ? $_POST['grid_name_dd'] : $_POST['grid_name_text'];
	// Ensure that only specified charcters are allowed
	if ($grid_name == "" || $grid_name == js_escape($lang['design_297']) || !preg_match("/^[a-z0-9_]+$/", $grid_name)) {
		$grid_name = "";
	}
}








/**
 * EDITING EXISTING QUESTION
 * If was a Section Header but it being converted to a real field, then simply ADD as a new field
 */
if ($edit_question && !$was_section_header)
{

	// Set associated values for query
	$element_validation_checktype = ($_POST['field_type'] == "text") ? "soft_typed" : "";

	// Parse multiple choices
	if ($_POST['element_enum'] != "" && ($_POST['field_type'] == "checkbox" || $_POST['field_type'] == "advcheckbox" || $_POST['field_type'] == "radio" || $_POST['field_type'] == "select")) {
		$_POST['element_enum'] = DataEntry::autoCodeEnum($_POST['element_enum']);
	// Clean calc field equation
	} elseif ($_POST['field_type'] == "calc") {
		$_POST['element_enum'] = html_entity_decode(trim(str_replace($br_orig, $br_repl, $_POST['element_enum'])), ENT_QUOTES);
	// Ensure that most fields do not have a "select choice" value
	} elseif (!$enable_ontology_auto_suggest_field && in_array($_POST['field_type'], array("text", "textarea", "notes", "file", "yesno", "truefalse"))) {
		$_POST['element_enum'] = "";
	}

	// Edit field's section header
	if ($is_section_header)
	{
		// If user is changing a field into a section header, delete actual field and move section header down one field in metadata table.
		if (isset($_POST['field_name']) && $_POST['field_name'] != "")
		{
			// See if a section header already exists for the field. If so, append new value onto it and move down one field
			$sql = "select field_order, element_preceding_header from $metadata_table where project_id = $project_id
					and field_name = '{$_POST['field_name']}' limit 1";
			$q = db_query($sql);
			$sh_existing1 = db_result($q, 0, "element_preceding_header");
			$forder_existing1 = db_result($q, 0, "field_order");
			// See if section header exists for the succeeding field
			$sql = "select field_name, element_preceding_header from $metadata_table where project_id = $project_id
					and field_order > $forder_existing1 order by field_order limit 1";
			$q = db_query($sql);
			$sh_existing2 = db_result($q, 0, "element_preceding_header");
			$fieldname_existing2 = db_result($q, 0, "field_name");
			// Append other section header values onto submitted one
			if ($sh_existing1 != "") $_POST['field_label']  = $sh_existing1 . "<br><br>" . $_POST['field_label'];
			if ($sh_existing2 != "") $_POST['field_label'] .= "<br><br>" . $sh_existing2;
			// Move section header to succeeding field
			$sql = "update $metadata_table set element_preceding_header = " . checkNull($_POST['field_label']) . "
					where project_id = $project_id and field_name = '$fieldname_existing2'";
			db_query($sql);
			// Delete current field and reduce field_order of following fields
			db_query("delete from $metadata_table where project_id = $project_id and field_name = '{$_POST['field_name']}'");
			db_query("update $metadata_table set field_order = field_order - 1 where project_id = $project_id and field_order > $forder_existing1");
			## FORM MENU: Always make sure the form_menu_description value stays only with first field on form
			// Set all field's form_menu_description as NULL
			$sql = "update $metadata_table set form_menu_description = NULL where project_id = $project_id and form_name = '{$_POST['form_name']}'";
			db_query($sql);
			// Now set form_menu_description for first field
			$sql = "update $metadata_table set form_menu_description = '".db_escape(label_decode($Proj->forms[$_POST['form_name']]['menu']))."'
					where project_id = $project_id and form_name = '{$_POST['form_name']}' order by field_order limit 1";
			db_query($sql);
			// Run javascript to reload table
			print  "<script type='text/javascript'>
					window.parent.window.reloadDesignTable('{$_POST['form_name']}');
					</script>";
			exit;

		}
		// Modify section header normally
		else {
			$sql = "update $metadata_table set "
				 . "element_preceding_header = " . checkNull($_POST['field_label']) . " "
				 . "where project_id = $project_id and field_name = '{$_POST['sq_id']}'";
		}

	}
	// Edit field itself
	else
	{
		// CHECK IF NEED TO DELETE EDOC: If edoc_id is blank then set as "deleted" in edocs_metadata table (development only OR if added then deleted in Draft Mode)
		// Get current edoc_id
		$q = db_query("select edoc_id from $metadata_table where project_id = $project_id and field_name = '{$_POST['sq_id']}' limit 1");
		$current_edoc_id = db_result($q, 0);
		if (empty($_POST['edoc_id']) || $current_edoc_id != $_POST['edoc_id'])
		{
			Design::deleteEdoc($_POST['sq_id']);
		}

		// Update field
		$sql = "update $metadata_table set "
			 . "field_name = '{$_POST['field_name']}', "
			 . "element_label = " . checkNull($_POST['field_label']) . ", "
			 . "field_req = '{$_POST['field_req']}', "
			 . "field_phi = " . checkNull($_POST['field_phi']) . ", "
			 . "element_note = " . checkNull($_POST['field_note']) . ", "
			 . "element_type = '{$_POST['field_type']}', "
			 . "element_validation_type = " . checkNull($_POST['val_type']) . ", "
			 . "element_validation_checktype = " . checkNull($element_validation_checktype) . ", "
			 . "element_enum = " . checkNull($_POST['element_enum']) . ", "
			 . "element_validation_min = " . checkNull($_POST['val_min']) . ", "
			 . "element_validation_max = " . checkNull($_POST['val_max']) . ", "
			 . "edoc_id = " . checkNull($_POST['edoc_id']) . ", "
			 . "edoc_display_img = {$_POST['edoc_display_img']}, "
			 . "custom_alignment = " . checkNull($_POST['custom_alignment']) . ", "
			 . "question_num = " . checkNull(isset($_POST['question_num']) ? $_POST['question_num'] : null) . ", "
			 . "grid_name = " . checkNull($grid_name) . ", "
			 . "video_url = " . checkNull($_POST['video_url']) . ", "
			 . "video_display_inline = " . checkNull($_POST['video_display_inline']) . ", "
			 . "misc = " . checkNull(trim($_POST['field_annotation'])) . " "
			 . "where project_id = $project_id and field_name = '{$_POST['sq_id']}'";

	}
	$q = db_query($sql);

	// Logging
	if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$_POST['field_name'],"field_name = '{$_POST['field_name']}'","Edit project field");


/**
 * ADDING NEW QUESTION
 */
} else {

	// Reformat value if adding field directly above a Section Header (i.e. ends with "-sh")
	if (substr($_POST['this_sq_id'], -3) == "-sh") {
		$_POST['this_sq_id'] = substr($_POST['this_sq_id'], 0, -3);
		$possible_sh_attached = false;
	} else {
		// Set flag and check later if field directly below has a Section Header (i.e. are we adding a field "between" a SH and a field?)
		$possible_sh_attached = true;
	}

	## Section Headers ONLY
	if ($is_section_header) {

		// Prevent user from adding section header as last field
		if ($_POST['this_sq_id'] == "") {
			exit("<script type='text/javascript'>
				  window.parent.window.resetAddQuesForm();
				  window.parent.window.alert('".remBr($lang['design_201'])."');
				  </script>");
		}

		// Update field
		$sql = "update $metadata_table set element_preceding_header = " . checkNull($_POST['field_label']) . " where project_id = $project_id "
			 . "and field_name = '{$_POST['this_sq_id']}'";
		$q = db_query($sql);
		// Set field name of field its attached to
		$_POST['field_name'] = $_POST['this_sq_id'];
		// Logging
		if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$last_field,"field_name = '$last_field'","Edit project field");

	## All field types (except section headers)
	} else {

		// Check new form_name value to see if it already exists. If so, unset the value to mimic field-adding behavior for an existing form.
		if (isset($_POST['add_form_name'])) {
			$formExists = db_result(db_query("select count(1) from $metadata_table where project_id = $project_id
					and form_name = '".db_escape($_POST['form_name'])."' limit 1"), 0);
			if ($formExists) {
				unset($_POST['add_form_name']);
			}
		}

		// Creating new form or editing existing?
		if (isset($_POST['add_form_name']) && isset($_POST['add_before_after'])) {
			// NEW FORM being added
			$form_menu_description = "'" . db_escape(strip_tags(label_decode($_POST['add_form_name']))) . "'";
			if ($_POST['add_before_after']) {
				// Place after selected form
				$sql = "select max(field_order)+1 from $metadata_table where project_id = $project_id and form_name = '".db_escape($_POST['add_form_place'])."'";
			} elseif (!$_POST['add_before_after']) {
				// Place before selected form
				$sql = "select min(field_order) from $metadata_table where project_id = $project_id and form_name = '".db_escape($_POST['add_form_place'])."'";
			}
		} else {
			// EXISTING FORM
			$form_menu_description = "NULL";
			// Determine if adding to very bottom of table or not. If so, get position of last field on form + 1
			if ($is_last) {
				$sql = "select max(field_order) from $metadata_table where project_id = $project_id and form_name = '{$_POST['form_name']}'";
			// Obtain the destination field's field_order value (i.e. field_order of field that will be located after this new one)
			} else {
				$sql = "select field_order from $metadata_table where project_id = $project_id and field_name = '{$_POST['this_sq_id']}' limit 1";
			}
		}
		// Get the following question's field order
		$new_field_order = db_result(db_query($sql), 0);
		// Increment added to all fields occurring after this new one. If creating a new form, also add extra increment
		// number for field_order to give extra room for the Form Status field created
		$increase_field_order = isset($_POST['add_form_name']) ? 2 : 1;

		// Increase field_order of all fields after this new one
		db_query("update $metadata_table set field_order = field_order + $increase_field_order where project_id = $project_id and field_order >= $new_field_order");
		// Set associated values for query
		$element_validation_checktype = "";
		if ($_POST['field_type'] == "text") {
			$element_validation_checktype = "soft_typed";
		// Parse multiple choices
		} elseif ($_POST['element_enum'] != "" && ($_POST['field_type'] == "checkbox" || $_POST['field_type'] == "advcheckbox" || $_POST['field_type'] == "radio" || $_POST['field_type'] == "select")) {
			$_POST['element_enum'] = DataEntry::autoCodeEnum($_POST['element_enum']);
		// Clean calc field equation (and for "sql" field types also)
		} elseif ($_POST['element_enum'] != "") {
			$_POST['element_enum'] = html_entity_decode(trim(str_replace(isset($br_orig) ? $br_orig : '', isset($br_repl) ? $br_repl : '', $_POST['element_enum'])), ENT_QUOTES);
		}
		// Query to create new field
		$sql = "insert into $metadata_table (project_id, field_name, field_phi, form_name, form_menu_description, field_order,
				field_units, element_preceding_header, element_type, element_label, element_enum, element_note, element_validation_type,
				element_validation_min, element_validation_max, element_validation_checktype, branching_logic, field_req,
				edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, grid_name, grid_rank, misc, video_url, video_display_inline)
				values
				($project_id, '".db_escape($_POST['field_name'])."', " . checkNull($_POST['field_phi']) . ", "
			 . "'{$_POST['form_name']}', $form_menu_description, '$new_field_order', NULL, NULL, '{$_POST['field_type']}', "
			 . checkNull($_POST['field_label']) . ", "
			 . checkNull($_POST['element_enum']) . ", "
			 . checkNull($_POST['field_note']) . ", "
			 . checkNull($_POST['val_type']) . ", "
			 . checkNull($_POST['val_min']) . ", "
			 . checkNull($_POST['val_max']) . ", "
			 . checkNull($element_validation_checktype) . ", "
			 . "NULL, "
			 . "'{$_POST['field_req']}', "
			 . checkNull($_POST['edoc_id']) . ", "
			 . $_POST['edoc_display_img'] . ", "
			 . checkNull($_POST['custom_alignment']) . ", "
			 . "NULL, "
			 . checkNull(isset($_POST['question_num']) ? $_POST['question_num'] : null) . ", "
			 . checkNull($grid_name) . ", "
			 . "0, "
			 . checkNull(trim($_POST['field_annotation'])) . ", "
			 . checkNull($_POST['video_url']) . ", "
			 . checkNull($_POST['video_display_inline'])
			 . ")";
		$q = db_query($sql);
		// Logging
		if ($q) {
			Logging::logEvent($sql,$metadata_table,"MANAGE",$_POST['field_name'],"field_name = '{$_POST['field_name']}'","Create project field");
		} else {
			// UNDO previous "reorder" query: Decrease field_order of all fields after where this new one should've gone
			db_query("update $metadata_table set field_order = field_order - $increase_field_order
						 where project_id = $project_id and field_order >= ".($new_field_order + $increase_field_order));
			// If field failed to save, then give error msg and reload form completely
			print  "<script type='text/javascript'>
					window.parent.window.alert(window.parent.window.woops);
					window.parent.window.reloadDesignTable('{$_POST['form_name']}');
					</script>";
			exit;
		}

		// If creating a new form, also add Form Status field
		if (isset($_POST['add_form_name']))
		{
			// Add the Form Status field
			$sql = "insert into $metadata_table (project_id, field_name, form_name, field_order, element_type,
					element_label, element_enum, element_preceding_header) values ($project_id, '{$_POST['form_name']}_complete',
					'{$_POST['form_name']}', '".($new_field_order+1)."', 'select', 'Complete?',
					'0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
			$q = db_query($sql);
			// Logging
			if ($q) Logging::logEvent($sql,$metadata_table,"MANAGE",$_POST['form_name'],"form_name = '{$_POST['form_name']}'","Create data collection instrument");

			// Only if in Development...
			if ($status == 0) {
				// Grant all users full access rights (default) to the new form
				$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[{$_POST['form_name']},1]') where project_id = $project_id";
				db_query($sql);
				// Add new forms to events_forms table ONLY if not longitudinal (if longitudinal, user will designate form for events later)
				if (!$longitudinal) {
					$sql = "insert into redcap_events_forms (event_id, form_name) select e.event_id, '{$_POST['form_name']}' from redcap_events_metadata e,
							redcap_events_arms a where a.arm_id = e.arm_id and a.project_id = $project_id limit 1";
					db_query($sql);
				}
			}

		// NOT adding a new form, so deal with some logic and placement issues
		} else {

			## SECTION HEADER PLACEMENT
			// Check if we are adding a field "between" a SH and a field? If so, move SH to new field from one directly after it.
			$reloadDesignTable = false;
			if ($possible_sh_attached && !$is_last)
			{
				$sql = "select element_preceding_header from $metadata_table where project_id = $project_id and form_name = '{$_POST['form_name']}'
						and field_order = (select field_order+1 from $metadata_table where project_id = $project_id
						and field_name = '{$_POST['field_name']}' limit 1) and element_preceding_header is not null limit 1";
				$q = db_query($sql);
				if (db_num_rows($q) > 0) {
					// Yes, we are adding a field "between" a SH and a field. Move the SH to the field we just created.
					$sh_value = db_result($q, 0);
					// If changed a SH to a real field, then don't reattach the SH, but instead set to null
					if ($was_section_header) {
						$sh_value = "";
					}
					$sql = "update $metadata_table set element_preceding_header = " . checkNull($sh_value) . " where project_id = $project_id
							and field_name = '{$_POST['field_name']}' limit 1";
					$q = db_query($sql);
					// Get name of field directly after the new one we created.
					$sql = "select field_name from $metadata_table where project_id = $project_id and form_name = '{$_POST['form_name']}'
							and field_order = ".($new_field_order+1)." limit 1";
					$following_field = db_result(db_query($sql), 0);
					// Set SH value from other field to NULL now that we have copied it to new field
					$sql = "update $metadata_table set element_preceding_header = NULL where project_id = $project_id and field_name = '$following_field' limit 1";
					$q = db_query($sql);
					// Set value for row in table to be deleted in DOM (delete section header on following field, which is now null)
					$delete_row = $following_field . "-sh";
					// Set flag to reload table on page
					$reloadDesignTable = true;
				}
			}

			## FORM MENU: Always make sure the form_menu_description value stays only with first field on form
			// Set all field's form_menu_description as NULL
			$sql = "update $metadata_table set form_menu_description = NULL where project_id = $project_id and form_name = '{$_POST['form_name']}'";
			db_query($sql);
			// Now set form_menu_description for first field
			$form_menu = ($status > 0) ? $Proj->forms_temp[$_POST['form_name']]['menu'] : $Proj->forms[$_POST['form_name']]['menu'];
			$sql = "update $metadata_table set form_menu_description = '".db_escape(label_decode($form_menu))."'
					where project_id = $project_id and form_name = '{$_POST['form_name']}' order by field_order limit 1";
			db_query($sql);
			
			// Reload form completely in order to associate section header with newly added field below it
			if ($reloadDesignTable) {
				print  "<script type='text/javascript'>
						window.parent.window.reloadDesignTable('{$_POST['form_name']}');
						</script>";
				exit;
			}
		}

	}


}



/*
print  "<script type='text/javascript'>window.parent.window.alert('";
foreach ($_POST as $key=>$value) { print "$key=>$value, "; }
print  "');</script>";
exit;
 */




// RELOAD DESIGN TABLE if fields are not in proper order
// OR if matrix field was add/edited.
// OR if edited the Primary Key field.
// If not, reload table on page, else do insertRow into table.
if (($_POST['field_name'] == $table_pk) || $grid_name != "" || $Proj->checkReorderFields($metadata_table))
{
	// Reload form completely in order to associate section header with newly added field below it
	print  "<script type='text/javascript'>
			window.parent.window.reloadDesignTable('{$_POST['form_name']}');
			</script>";
}
// Reload whole page if Primary Key field's variable name was modified
elseif ($_POST['sq_id'] == $table_pk && $_POST['sq_id'] != $_POST['field_name'])
{
	print  "<script type='text/javascript'>
			window.parent.window.showProgress(1);
			window.parent.window.location.href = window.parent.window.app_path_webroot+window.parent.window.page+'?pid='+window.parent.window.pid+'&page='+window.parent.window.getParameterByName('page');
			</script>";
}
// Insert new row into table and close "Add/Edit Field" dialog pop-up
else
{
	// Insert row into table
	print  "<script type='text/javascript'>
			window.parent.window.insertRow('draggable', '{$_POST['field_name']}', $edit_question, $is_last, 0, $is_section_header, '$delete_row');
			</script>";
	// If an auto-suggest ontology field, then enable for this field
	if ($enable_ontology_auto_suggest_field) {
		print  "<script type='text/javascript'>
				window.parent.window.setTimeout(function(){
					window.parent.window.document.forms['form'].{$_POST['field_name']}.setAttribute('onclick', \"initWebServiceAutoSuggest('{$_POST['field_name']}',1);\");
				},500);
				</script>";
	}
	## If field_name was renamed AND other fields have branching logic dependent upon it, then give notice
	if (!$is_section_header && $edit_question && $_POST['sq_id'] != $_POST['field_name'])
	{
		// Check if the table_pk is being deleted. If so, give back different response so as to inform the user of change.
		$sql = "select field_name, element_label from $metadata_table where project_id = $project_id and (
				(element_type = 'calc' and
					(element_enum like '%[{$_POST['sq_id']}]%' or element_enum like '%[{$_POST['sq_id']}(%)]%')
				) or
				(branching_logic like '%[{$_POST['sq_id']}]%') or
				(branching_logic like '%[{$_POST['sq_id']}(%)]%')
				) order by field_order";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$response = "";
			while ($row = db_fetch_assoc($q))
			{
				$response .= RCView::SP . RCView::SP . "-" . RCView::SP . RCView::b($row['field_name']) . RCView::SP
						   . "-" . RCView::SP . RCView::escape($row['element_label']) . RCView::br();
			}
			// Set message with list of fields
			$response = $lang['design_478'] . RCView::br() . RCView::br(). $lang['design_479']
					  . " (<b>{$_POST['sq_id']}</b> => <b>{$_POST['field_name']}</b>) " . $lang['design_480'] . RCView::br() . $response;
			// Display message in a popup
			print  "<script type='text/javascript'>
					window.parent.window.simpleDialog('".js_escape($response)."','".js_escape($lang['design_477'])."',null,650);
					</script>";
		}
	}
}

// Check if the table_pk has changed during this script. If so, give user a prompt alert.
if (Design::recordIdFieldChanged())
{
	print  "<script type='text/javascript'>
			window.parent.window.update_pk_msg(false,'field');
			</script>";
}


// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if form is a survey, and if so, if has any branching logic. If so, disable question auto numbering.
if (Design::checkDisableSurveyQuesAutoNum($_POST['form_name']))
{
	// Give user a prompt as notice of this change
	print  "<script type='text/javascript'>
			setTimeout(function(){
				window.parent.window.alert(window.parent.window.disabledAutoQuesNumMsg);
			},300);
			</script>";
}

// If user changes the equation of a calc field (in development only), give them a warning if SOME data is saved for that field
if ($status == '0' && $_POST['field_type'] == "calc" && trim(isset($Proj->metadata[$_POST['field_name']]) ? $Proj->metadata[$_POST['field_name']]['element_enum'] : '') != trim($_POST['element_enum']))
{
	// Check for any data for this calc field
	$q = db_query("select 1 from redcap_data where project_id = $project_id and field_name = '".db_escape($_POST['field_name'])."' limit 1");
	$hasData = (db_num_rows($q) > 0);
	// Equation changed AND it has data
	if ($hasData) {
		print  "<script type='text/javascript'>
				window.parent.window.simpleDialog('".js_escape($lang['design_515'])."','".js_escape($lang['design_514'])."');
				</script>";
	}
}

// Field Embedding: Get a list of all embedded fields on this instrument to toggle the button/div for each field
$Proj->loadMetadata();
$embeddedVarsThisInstrument = Piping::getEmbeddedVariables(PROJECT_ID, $_POST['form_name'], null, true);
$embeddedVarsThisField = Piping::getEmbeddedVariablesForField(PROJECT_ID, $_POST['field_name'], true);
foreach ($embeddedVarsThisInstrument as &$this_field) $this_field = "tr#".$this_field."-tr";
print  "<script type='text/javascript'>
		window.parent.window.toggleEmbeddedFieldsButtonDesigner('".implode(",", $embeddedVarsThisInstrument)."');
		</script>";
// If a field is somehow embedded inside itself, display a warning to user
if (in_array($_POST['field_name'], $embeddedVarsThisField)) {
	print  "<script type='text/javascript'>
			window.parent.window.simpleDialog('".js_escape('<div class="yellow fs14">'.$lang['design_796']." <b>{".$_POST['field_name']."}</b> ".$lang['design_797'].'</div>')."');
			</script>";
}