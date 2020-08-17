<?php

global $format, $returnFormat, $post;

// Check for required privileges
if ($post['record_delete'] != '1' || $post['api_import'] != '1') die(RestUtility::sendResponse(400, $lang['api_135'], $returnFormat));
if (!is_array($post['records'])) die(RestUtility::sendResponse(400, $lang['api_136'], $returnFormat));
if (empty($post['records'])) die(RestUtility::sendResponse(400, $lang['api_137'], $returnFormat));

// Instantiate project objecct
$Proj = new Project(PROJECT_ID);	

// delete the records 
$content = delRecords();

// Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function delRecords()
{
	global $post, $Proj, $lang, $playground;
	// If Arm was passed, then get its arm_id
	$arm_id = null;
	if (isset($_POST['arm']) && $Proj->longitudinal && $Proj->multiple_arms) {
		$arm_id = $Proj->getArmIdFromArmNum($_POST['arm']);
		// Error: arm is incorrecct
		if (!$arm_id) die(RestUtility::sendResponse(400, $lang['api_132']));
		// Set event_id (for logging only) so that the logging denotes the correct arm
		$_GET['event_id'] = $Proj->getFirstEventIdArm($_POST['arm']);
	}
	// First check if all records submitted exist
	$existingRecords = Records::getData('array', $post['records'], $Proj->table_pk);
	// Return error if some records don't exist
	if (count($existingRecords) != count($post['records'])) {
		die(RestUtility::sendResponse(400, $lang['api_131'] . " " . implode(", ", array_diff($post['records'], array_keys($existingRecords)))));
	}
	// Loop through all and delete each
	foreach($post['records'] as $r) {
		Records::deleteRecord($r, $Proj->table_pk, $Proj->multiple_arms, $Proj->project['randomization'], $Proj->project['status'],
							  $Proj->project['require_change_reason'], $arm_id, " (API$playground)");
	}
	// Return count of records deleted
    return count($post['records']);
}
