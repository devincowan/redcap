<?php


// Only accept Post submission
if ($_SERVER['REQUEST_METHOD'] != 'POST') exit;

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$drw_upload_option_enabled) exit("ERROR!");

// Get file attributes
$doc_name = str_replace("'", "", html_entity_decode(stripslashes($_FILES['myfile']['name']), ENT_QUOTES));
$doc_size = $_FILES['myfile']['size'];

// Check if file is larger than max file upload limit
if (($doc_size/1024/1024) > maxUploadSizeAttachment() || $_FILES['file']['error'] != UPLOAD_ERR_OK)
{
	// Delete temp file
	unlink($_FILES['myfile']['tmp_name']);
	// Give error response
	print "<script language='javascript' type='text/javascript'>
			window.parent.window.dataResolutionStopUpload('','');
			window.parent.window.alert('ERROR: CANNOT UPLOAD FILE!\\n\\nThe uploaded file is ".round_up($doc_size/1024/1024)." MB in size, '+
									'thus exceeding the maximum file size limit of ".maxUploadSizeAttachment()." MB.');
		   </script>";
	exit;
}

// Upload the file and return the doc_id from the edocs table
$doc_id = Files::uploadFile($_FILES['myfile']);

// If error
if ($doc_id == 0) {
	print "<script language='javascript' type='text/javascript'>
			window.parent.window.alert('ERROR!');
		   </script>";
	exit;
}

// Set document name and append doc_size
if (strlen($doc_name) > 24) $doc_name = substr($doc_name, 0, 22) . "...";
$doc_name .= " (" . round_up($doc_size/1024/1024) . " MB)";

// Give response
print "<script language='javascript' type='text/javascript'>
		window.parent.window.dataResolutionStopUpload('$doc_id','".js_escape($doc_name)."');
	   </script>";

## Logging
// Set event_id for logging only
$_GET['event_id'] = $_POST['event_id'];
// Set data values as json_encoded
$logDataValues = json_encode(array('doc_id'=>$doc_id,'record'=>$_POST['record'],'event_id'=>$_POST['event_id'],
					'field'=>$_POST['field'],'rule_id'=>$_POST['rule_id']));
Logging::logEvent("","redcap_edocs_metadata","MANAGE",$_POST['record'],$logDataValues,"Upload document for data query response");

