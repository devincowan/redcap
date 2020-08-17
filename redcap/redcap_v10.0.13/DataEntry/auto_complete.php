<?php


require dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must be accessed via AJAX
if (!$isAjax) exit("ERROR!");
// Set max number of records to return
$limit = 50;

//Retrieve matching records to populate auto-complete box
if (isset($_GET['term'])) {
	## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
	System::killConcurrentRequests(1);
	// Set search term
	$queryString = db_escape(urldecode($_GET['term']));
	$queryStringLength = strlen($queryString);
	// Loop through records
	$recs = array();
	$recordList = Records::getRecordList($project_id, $user_rights['group_id'], ($double_data_entry && $user_rights['double_data'] != "0"), false,
					$_GET['arm'], $limit, 0, array(), false, $queryString);
	foreach ($recordList as $record) {
		$value = $label = $record;
		// Add boldness to search term
		$pos = stripos($record, $queryString);
		$label = substr($value, 0, $pos)
			. "<b style=\"color:#319AFF;\">".substr($value, $pos, $queryStringLength)."</b>"
			. substr($value, $pos+$queryStringLength);
		$recs[] = array('value'=>$value, 'label'=>$label);
	}
	//Render JSON
	print json_encode_rc($recs);
} else {
	// User should not be here! Redirect to index page.
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}
