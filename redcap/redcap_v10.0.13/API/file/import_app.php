<?php


# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;
$primaryKey = $Proj->table_pk;
$project_id = $post['projectid'];

# check to see if a file was uploaded
if (count($_FILES) == 0) RestUtility::sendResponse(400, "No valid file was uploaded");

# make sure there were no errors associated with the uploaded file
if ($_FILES['file']['error'] != 0) RestUtility::sendResponse(400, "There was a problem with the uploaded file");

// Prevent data writes for projects in inactive or archived status
if ($Proj->project['status'] > 1) {
	if ($Proj->project['status'] == '2') {
		$statusLabel = "Analysis/Cleanup";
	} else {
		$statusLabel = "[unknown]";
	}
	die(RestUtility::sendResponse(403, "The file cannot be uploaded because the project is in $statusLabel status."));
}

# get file information
$fileData = $_FILES['file'];

$docName = str_replace("'", "", html_entity_decode(stripslashes($fileData['name']), ENT_QUOTES));
$docSize = $fileData['size'];

# Check if file is larger than max file upload limit
if (($docSize/1024/1024) > maxUploadSizeEdoc() || $fileData['error'] != UPLOAD_ERR_OK) {
	RestUtility::sendResponse(400, "The uploaded file exceeded the maximum file size limit of ".maxUploadSize()." MB");
}

# Upload the file and return the doc_id from the edocs table
$docId = Files::uploadFile($fileData);

# Update tables if file was successfully uploaded
if ($docId != 0)
{
	// Detect type of file
	$docTypesAll = array('ESCAPE_HATCH', 'LOGGING');
	$docType = (in_array($post['file_type'], $docTypesAll)) ? $post['file_type'] : 'ESCAPE_HATCH';
	// Add to mobile app files table
        $device_id = "";
        if ($post['uuid'] !== "")
        {
                $presql1= "SELECT device_id, revoked FROM redcap_mobile_app_devices WHERE (uuid = '".db_escape($post['uuid'])."') AND (project_id = ".$project_id.") LIMIT 1;";
                $preq1 = db_query($presql1);
                $row = db_fetch_assoc($preq1);
                if (!$row)  // no devices
                {
                        $presql2 = "INSERT INTO redcap_mobile_app_devices (uuid, project_id) VALUES('".db_escape($post['uuid'])."', ".$project_id.");";
                        db_query($presql2);
                        $preq1 = db_query($presql1);
                        $row = db_fetch_assoc($preq1);
                }
                if ($row && ($row['revoked'] == "0"))
                {
	                $sql = "INSERT INTO redcap_mobile_app_files (doc_id, type, user_id, device_id)
			                VALUES ('$docId', '$docType', (select ui_id from redcap_user_information where username = '".db_escape(USERID)."'), ".$row['device_id'].")";
	                db_query($sql);
	                // Log file upload
	                Logging::logEvent($sql,"redcap_mobile_app_files","DOC_UPLOAD",$docId,"doc_id = $docId","Upload document to mobile app archive");
                }
				else
				{
					RestUtility::sendResponse(403, "Your device does not have appropriate permissions to upload a file.");
				}
        }
        else
        {
			$sql = "INSERT INTO redcap_mobile_app_files (doc_id, type, user_id)
					VALUES ('$docId', '$docType', (select ui_id from redcap_user_information where username = '".db_escape(USERID)."'))";
			db_query($sql);
			// Log file upload
			Logging::logEvent($sql,"redcap_mobile_app_files","DOC_UPLOAD",$docId,"doc_id = $docId","Upload document to mobile app archive");
		}
}
else {
	RestUtility::sendResponse(400, "A problem occurred while trying to save the uploaded file");
}

# Send the response to the requester
RestUtility::sendResponse(200);
