<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Page is only usable is Field Comment Log is enabled
if ($data_resolution_enabled != '1') exit('0');

// Instantiate DataQuality object
$dq = new DataQuality();

// Obtain html
$html = $dq->renderFieldCommentLog(label_decode(urldecode($_GET['record'])), $_GET['event_id'],
			$_GET['field'], $_GET['group_id'], $_GET['user'], label_decode(urldecode($_GET['keyword'])));

// Return as JSON
print json_encode_rc(array('html'=>$html));