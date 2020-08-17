<?php
global $format, $returnFormat, $post;



// disable for production
$Proj = new Project();
if($Proj->project['status'] > 0)
{
	RestUtility::sendResponse(400, $lang['api_102'], $returnFormat);
	exit;
}

// Check for required privileges
if ($post['design_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_124'], $returnFormat));

# get all the records to be exported
$content = delArms();

# Logging
Logging::logEvent("", "redcap_events_arms", "MANAGE", PROJECT_ID, "arm_number in (" . implode(", ", $post['arms']) . ")", "Delete arms (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);


function delArms()
{
	global $post, $lang, $Proj;

	if(!isset($post['arms']) || empty($post['arms']) || !is_array($post['arms'])) {
		die(RestUtility::sendResponse(400, $lang['api_104']));
	}

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	$count = 0;
	$errors = array();

	foreach($post['arms'] as $arm)
	{
		if (is_numeric($arm) && Arm::getArm(PROJECT_ID, $arm))
		{
			$count += Arm::delArm(PROJECT_ID, $arm);
		}
		else
		{
			$errors[] = $arm;
		}
	}

	if (!empty($errors)) {
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		die(RestUtility::sendResponse(400, $lang['api_105'] . " " . implode(", ", $errors)));
	}

	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");

	return $count;
}
