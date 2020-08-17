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

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Check if we have field_name in query string
if (!isset($_GET['field_name']) || (isset($_GET['field_name']) && !isset($Proj->metadata[$_GET['field_name']])))
{
	// Error
	if ($isAjax) {
		exit;
	} else {
		redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
	}
}



/**
 * CHECK UNIQUENESS OF FIELD VALUES FOR SECONDARY_PK
 */

// Default: Check to make sure ALL current values for the field given are unique.
if (!isset($_GET['record']) && !isset($_GET['value']))
{
	// Get a count of all duplicated values for the field submitted
	$sql = "select sum(duplicates) from (select count(1) as duplicates from
			(select distinct record, value from redcap_data where project_id = $project_id
			and field_name = '{$_GET['field_name']}') x group by value) y where duplicates > 1";
	$q = db_query($sql);
	// Return the number of duplicates
	$duplicates = db_result($q, 0);
	print (is_numeric($duplicates)) ? $duplicates : 0;
}

// If value and record are given, check uniqueness against all other records' values.
elseif (isset($_GET['record']) && isset($_GET['value']) && $secondary_pk != "" && $secondary_pk == $_GET['field_name'])
{
	$_GET['value'] = urldecode($_GET['value']);
	$_GET['record'] = urldecode($_GET['record']);
	// If field is a MDY or DMY date/time field, then convert value
	$val_type = $Proj->metadata[$_GET['field_name']]['element_validation_type'];
	if (substr($val_type, 0, 4) == 'date' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_dmy')) {
		$_GET['value'] = DateTimeRC::datetimeConvert($_GET['value'], substr($val_type, -3), 'ymd');
	}
	// Get a count of all duplicated values for the $secondary_pk field (exclude submitted record name when counting)
	$sql = "select count(1) from redcap_data where project_id = $project_id and field_name = '$secondary_pk'
			and value = '" . db_escape($_GET['value']) . "' and record != '' and record != '" . db_escape($_GET['record']) . "'";
	$q = db_query($sql);
	// Return the number of duplicates
	print db_result($q, 0);
}
