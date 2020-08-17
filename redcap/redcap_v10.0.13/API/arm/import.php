<?php
global $format, $returnFormat, $post;





// disable for production
$Proj = new Project();
if($Proj->project['status'] > 0 && !$enable_edit_prod_events)
{
	RestUtility::sendResponse(400, $lang['api_102'], $returnFormat);
	exit;
}
elseif ($Proj->project['status'] > 0 && $enable_edit_prod_events && isset($post['override']) && $post['override'] == '1')
{
	RestUtility::sendResponse(400, $lang['api_107'], $returnFormat);
	exit;
}

// Check for required privileges
if ($post['design_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_124'], $returnFormat));

// add/update all the arms
$content = putArms();

// Logging
Logging::logEvent("", "redcap_arms", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import arms (API$playground)");

// Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);


function putArms()
{
	global $post, $format, $lang;

	$data = removeBOMfromUTF8($post['data']);
	$override = isset($post['override']) ? (int)$post['override'] : 0;

	switch($format)
	{
	case 'json':
		// Decode JSON into array
		$data = json_decode($data, true);
		if ($data == '') return $lang['data_import_tool_200'];
		break;
	case 'xml':
		// Decode XML into array
		$data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
		if ($data == '' || !isset($data['arms'])) return $lang['data_import_tool_200'];
		$data = fixXML($data);
		break;
	case 'csv':
		// Decode CSV into array
		$data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
		$data = csvToArray($data);
		break;
	}

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	list ($count, $errors) = Arm::addArms(PROJECT_ID, $data, $override);

	if (!empty($errors)) {
		// ERROR: Roll back all changes made and return the error message
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		die(RestUtility::sendResponse(400, implode("\n", $errors)));
	}

	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");

	return $count;
}

// match JSON
function fixXML($data)
{
	$a = array();

	foreach($data as $arms)
	{
		foreach($arms as $items)
		{
			foreach($items as $arm)
			{
				$a[] = $arm;
			}
		}
	}

	return $a;
}
