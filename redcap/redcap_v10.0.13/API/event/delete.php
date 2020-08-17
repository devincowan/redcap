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

// delete the events
$content = delEvents();

// Logging
Logging::logEvent("", "redcap_events", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Delete events (API$playground)");

// Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);


function delEvents()
{
	global $post, $format, $Proj;

	$count = 0;

	foreach($post['events'] as $e)
	{
		$id = $Proj->getEventIdUsingUniqueEventName($e);
		$count += Event::delete($id);
	}

	return $count;
}
