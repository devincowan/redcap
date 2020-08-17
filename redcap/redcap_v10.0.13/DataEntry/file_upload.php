<?php


// Only accept Post submission
if ($_SERVER['REQUEST_METHOD'] != 'POST') exit;

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

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$field_name = substr($_POST['field_name'], 0, strpos($_POST['field_name'], "-"));
$isSignatureField = ($Proj->metadata[$field_name]['element_validation_type'] == 'signature');
$id = rawurldecode(urldecode($_GET['id']));
$instance = (isset($_GET['instance']) && is_numeric($_GET['instance']) && $_GET['instance'] > 1) ? $_GET['instance'] : 1;

if ((isset($_GET['event_id']) && !$Proj->validateEventId($_GET['event_id'])) || !isset($Proj->metadata[$field_name])) {
	exit('ERROR!');
} else {
	$event_id = $_GET['event_id'];
}

// Default success value
$result = 0;

//If user is a double data entry person, append --# to record id when saving
if (isset($user_rights) && $double_data_entry && $user_rights['double_data'] != 0) {
	$id .= "--" . $user_rights['double_data'];
}

// SURVEYS: Use the surveys/index.php page as a pass through for certain files (file uploads/downloads, etc.)
if (isset($_GET['s']) && !empty($_GET['s']))
{
	$file_download_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_download.php");
	$file_delete_page   = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_delete.php");
}
else
{
	$file_download_page = APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=$project_id";
	$file_delete_page   = APP_PATH_WEBROOT . "DataEntry/file_delete.php?pid=$project_id&page=" . $_GET['page'];
}

// BASE64 IMAGE DATA: Determine if file uploaded as normal FILE input field or as base64 data image via POST
if (isset($_POST['myfile_base64']) && $_POST['myfile_base64'] != '') {
	// Save the image data as file to the temp directory
	$_FILES['myfile']['type'] = "image/png";
	$_FILES['myfile']['name'] = "signature_" . date('Y-m-d_Hi') . ".png";
	$_FILES['myfile']['tmp_name'] = APP_PATH_TEMP . $_FILES['myfile']['name'];
	$saveSuccessfully = file_put_contents($_FILES['myfile']['tmp_name'], base64_decode(str_replace(' ', '+', $_POST['myfile_base64'])));
	$_FILES['myfile']['size'] = filesize($_FILES['myfile']['tmp_name']);
}

// Replace existing file with new version? (as opposed to adding new after deleting old)
$addNewVersion = (!$isSignatureField && $_POST['myfile_replace'] == '1' && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID));

// Upload the file and return the doc_id from the edocs table
$doc_id = $doc_size = 0;
$doc_name = "";
if (isset($_FILES['myfile']))
{
    // File Upload vault upload to external server: Get the file contents to use it later
    if (!$isSignatureField && Files::fileUploadPasswordVerifyExternalStorageEnabledProject($project_id)) {
        $doc_contents = file_get_contents($_FILES['myfile']['tmp_name']);
    }
    // Store the file
	$doc_id = Files::uploadFile($_FILES['myfile']);
	$doc_size = $_FILES['myfile']['size'];
	$doc_name = trim(strip_tags(str_replace("'", "", html_entity_decode(stripslashes($_FILES['myfile']['name']), ENT_QUOTES))));
	if ($doc_name == "") {
		$doc_id = 0;
	}
}

// Check if file is larger than max file upload limit
if (($doc_size/1024/1024) > maxUploadSizeEdoc() || (!isset($_POST['myfile_base64']) && $_FILES['myfile']['error'] != UPLOAD_ERR_OK))
{
	// Delete temp file
	unlink($_FILES['myfile']['tmp_name']);
	// Give error response
	print "<script language='javascript' type='text/javascript'>
			window.parent.window.stopUpload($result,'$field_name','$doc_id','$doc_name','".js_escape($id)."','$doc_size','$event_id','$file_download_page','$file_delete_page','','$instance');
			window.parent.window.alert('ERROR: CANNOT UPLOAD FILE!\\n\\nThe uploaded file is ".round_up($doc_size/1024/1024)." MB in size, '+
									'thus exceeding the maximum file size limit of ".maxUploadSizeEdoc()." MB.');
		   </script>";
	exit;
}

//Update tables if file was successfully uploaded
if ($doc_id != 0) {

	$result = 1;

	// Check if event_id exists in URL. If not, then this is not "longitudinal" and has one event, so retrieve event_id.
	if (!isset($_GET['event_id']) || $_GET['event_id'] == "") {
		$sql = "select m.event_id from redcap_events_metadata m, redcap_events_arms a where a.arm_id = m.arm_id and a.project_id = $project_id limit 1";
		$_GET['event_id'] = db_result(db_query($sql), 0);
	}

	// Do not save doc_id in data table if we're on a survey
	if (!defined("NOAUTH"))
	{
		// Update data table with $doc_id value
		$q = db_query("select 1 from redcap_data WHERE record = '".db_escape($id)."' and project_id = $project_id 
					   and event_id = {$_GET['event_id']} and instance ".($instance == '1' ? "is null" : "= '$instance'")." limit 1");
		// Record exists. Now see if field has had a previous value. If so, update; if not, insert.
		$fileFieldValueExists = (db_num_rows($q) > 0);
		if ($fileFieldValueExists)
		{
			$query = "UPDATE redcap_data SET value = '$doc_id' WHERE record = '".db_escape($id)."' AND field_name = '$field_name' 
					  AND project_id = $project_id AND event_id = {$_GET['event_id']}"
				   . " and instance ".($instance == '1' ? "is null" : "= '$instance'");
			$q2 = db_query($query);
			if (db_affected_rows($q2) == 0) {
				// Insert since update failed
				$query = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
						  VALUES ($project_id, {$_GET['event_id']}, '".db_escape($id)."', '$field_name', '$doc_id', 
						  ".($instance == '1' ? "null" : "'$instance'").")";
				db_query($query);
			}
			// Do logging of file upload (but not on surveys)
            $logdescip = $addNewVersion ? "Upload document (new version)" : "Upload document";
			defined("NOAUTH") or Logging::logEvent($query,"redcap_data","doc_upload",$id,"$field_name = '$doc_id'",$logdescip,
													"", "", "", true, null, $_GET['instance']);
            // SURVEY INVITATION SCHEDULER: Return count of invitation scheduled, if any
            if (!empty($Proj->surveys)) {
                $surveyScheduler = new SurveyScheduler($project_id);
                $numInvitationsScheduled = $surveyScheduler->checkToScheduleParticipantInvitation($id);
            }
			// Check if alert should be sent
            $eta = new Alerts();
            $eta->saveRecordAction($project_id, $id, '', $_GET['event_id'], $instance, $_GET['s']);
		}
		// If record doesn't exist yet, insert both doc_id and record id into data table
		// (but NOT if auto-numbering is enabled, which will cause problems here)
		elseif (!$fileFieldValueExists && !$auto_inc_set)
		{
			// Add field value to data table
			$query2 =  "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
						VALUES ($project_id, {$_GET['event_id']}, '".db_escape($id)."', '$field_name', '$doc_id', 
					   ".($instance == '1' ? "null" : "'$instance'").")";
			db_query($query2);
			//Do logging of new record creation
			if (!Records::recordExists($project_id, $id)) {
				// Add record id row
				$query1 =  "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
							VALUES ($project_id, {$_GET['event_id']}, '".db_escape($id)."', '$table_pk', '".db_escape($id)."', 
						   ".($instance == '1' ? "null" : "'$instance'").")";
				db_query($query1);
				// Log the creation of the record
				Logging::logEvent($query1,"redcap_data","insert",$id,"$table_pk = '".db_escape($id)."'","Create record");
			}
			// Do logging of file upload (but not on surveys)
			defined("NOAUTH") or Logging::logEvent($query2,"redcap_data","doc_upload",$id,"$field_name = '$doc_id'","Upload document", 
													"", "", "", true, null, $_GET['instance']);
			// If record doesn't exit yet AND the user creating it is in a DAG, then make sure to assign the record to their DAG
			if ($user_rights['group_id'] != "") 
			{
				// Add DAG assignment to data table
				$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) "
					 . "VALUES ($project_id, {$_GET['event_id']}, '".db_escape($id)."', '__GROUPID__', '".db_escape($user_rights['group_id'])."')";
				db_query($sql);
				// Log the DAG assignment (but not on surveys)
				$dag_log_descrip  = "Assign record to Data Access Group";
				$group_name = $Proj->getUniqueGroupNames($user_rights['group_id']);
				defined("NOAUTH") or Logging::logEvent($sql, "redcap_data", "update", $id, "redcap_data_access_group = '$group_name'", $dag_log_descrip);
				// Update record list table
				Records::updateRecordDagInRecordListCache(PROJECT_ID, $id, $user_rights['group_id']);	
			}
            // SURVEY INVITATION SCHEDULER: Return count of invitation scheduled, if any
            if (!empty($Proj->surveys)) {
                $surveyScheduler = new SurveyScheduler($project_id);
                $numInvitationsScheduled = $surveyScheduler->checkToScheduleParticipantInvitation($id);
            }
            // Check if alert should be sent
            $eta = new Alerts();
            $eta->saveRecordAction($project_id, $id, '', $_GET['event_id'], $instance, $_GET['s']);
        }
	}

	// File Upload vault upload to external server
    if (!$isSignatureField && Files::fileUploadPasswordVerifyExternalStorageEnabledProject($project_id)) {
    	// Save the "stored_name" of the file to the external server
		$stored_name = Files::getEdocName($doc_id, true);
        $storedFileExternal = Files::writeUploadedFileToVaultExternalServer($stored_name, $doc_contents);
        // Log this extra event (but not for surveys)
        defined("NOAUTH") or Logging::logEvent("", "redcap_data", "update", $id, "Document upload was confirmed with password\n(field = '$field_name')", "");
    }
}

// Give response
$doc_size = " (" . round_up($doc_size/1024/1024) . " MB)";
// Set hash of the doc_id to verification later
$doc_id_hash = Files::docIdHash($doc_id);
// Ouput javascript
print "<script language='javascript' type='text/javascript'>
		window.parent.dataEntryFormValuesChanged = true;
		window.parent.window.stopUpload($result,'$field_name','$doc_id','$doc_name','".js_escape($id)."','$doc_size','$event_id','$file_download_page','$file_delete_page','$doc_id_hash','$instance');
	   </script>";
