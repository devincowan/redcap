<?php


// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
}

include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Make sure we have all the correct elements needed
if (!isset($_POST['transmitter_field']) || !isset($_POST['transmitter_field_value']) || !isset($_POST['json_piping_receiver_dropdown_fields'])) exit('');

// Clean field value passed
$_POST['transmitter_field_value'] = html_entity_decode($_POST['transmitter_field_value'], ENT_QUOTES);
// If transmitter field is a MDY or DMY date[time] field, then convert its value back to YMD for replacement purposes
$field_validation = $Proj->metadata[$_POST['transmitter_field']]['element_validation_type'];
if (substr($field_validation, 0, 4) == 'date' && (substr($field_validation, -4) == '_mdy' || substr($field_validation, -4) == '_dmy')) {
	$_POST['transmitter_field_value'] = DateTimeRC::datetimeConvert($_POST['transmitter_field_value'], substr($field_validation, -3), 'ymd');
}

// Parse the JSON for json_piping_receiver_dropdown_fields
$json_piping_receiver_dropdown_fields = json_decode(html_entity_decode($_POST['json_piping_receiver_dropdown_fields'], ENT_QUOTES),true);
// Set array of drop-down fields whose options need updating
$dropdownsToUpdate = $json_piping_receiver_dropdown_fields[$_POST['transmitter_field']];
// Validate the array
if (!is_array($dropdownsToUpdate) || empty($dropdownsToUpdate)) exit('');

// Build array of all fields used as piping receivers in our drop-down fields' options
$fieldsGetData = array();
foreach ($dropdownsToUpdate as $this_field) {
	$fieldsGetData = array_merge($fieldsGetData, array_keys(getBracketedFields($Proj->metadata[$this_field]['element_enum'], true, true, true)));
}
$fieldsGetData = array_unique($fieldsGetData);


// Is this a repeating instrument/event?
$isRepeatingForm = $Proj->isRepeatingForm($_POST['event_id'], $_POST['page']);
$isRepeatingEvent = $Proj->isRepeatingEvent($_POST['event_id']);

// Obtain saved data for all piping receivers used in field labels and MC option labels
$piping_record_data = Records::getData('array', $_POST['record'], $fieldsGetData);
// Pre-fill fields in array
foreach ($fieldsGetData as $this_field) {
	if ($isRepeatingForm) {
		$piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][$_POST['page']][$_POST['instance']][$this_field] = "";
	} elseif ($isRepeatingEvent) {
		$piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][''][$_POST['instance']][$this_field] = "";
	} else {
		$piping_record_data[$_POST['record']][$_POST['event_id']][$this_field] = "";
	}
}

// Also layer on any unsaved values on the current page
if (isset($_POST['formdata'])) {
	// Decode JSON
	$unsaved_form_data = json_decode($_POST['formdata'], true);
	if (is_array($unsaved_form_data)) {
		// Loop through current form data
		foreach ($unsaved_form_data as $this_field=>$this_val) {
			if ($isRepeatingForm && isset($piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][$_POST['page']][$_POST['instance']][$this_field])) {
				$piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][$_POST['page']][$_POST['instance']][$this_field] = $this_val;
			} elseif ($isRepeatingEvent && isset($piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][''][$_POST['instance']][$this_field])) {
				$piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][''][$_POST['instance']][$this_field] = $this_val;
			} elseif (isset($piping_record_data[$_POST['record']][$_POST['event_id']][$this_field])) {
				$piping_record_data[$_POST['record']][$_POST['event_id']][$this_field] = $this_val;
			}
		}
	}
}
// Now add the value of the field value passed via Post to $piping_record_data (since it is not saved yet)
if ($isRepeatingForm) {
	$piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][$_POST['page']][$_POST['instance']][$_POST['transmitter_field']] = $_POST['transmitter_field_value'];
} elseif ($isRepeatingEvent) {
	$piping_record_data[$_POST['record']]['repeat_instances'][$_POST['event_id']][''][$_POST['instance']][$_POST['transmitter_field']] = $_POST['transmitter_field_value'];
} else {
	$piping_record_data[$_POST['record']][$_POST['event_id']][$_POST['transmitter_field']] = $_POST['transmitter_field_value'];
}

// Loop through dropdown fields and add their choices to array with field_name as key
$dropdownsNewOptions = array();
foreach ($dropdownsToUpdate as $this_field) {
	// Parse each option one at a time (so that we only update the options that need updating)
	foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $this_code=>$this_label) {
		// Replace the enum (but only if a data value exists for the field being replaced, otherwise leave as is - less unnecessary work to do by client)
		$new_label = strip_tags(Piping::replaceVariablesInLabel($this_label, $_POST['record'], $_POST['event_id'], $_POST['instance'], $piping_record_data, true,
								null, true, "", 1, false, false, $_POST['page']));
		// Add new choices to array (but only if it changed)
		if ($this_label != $new_label) {
			$dropdownsNewOptions[$this_field][$this_code] = $new_label;
		}
	}
}

// Return the field's and their new options as JSON-encoded text to be parsed via JavaScript
print json_encode_rc($dropdownsNewOptions);
