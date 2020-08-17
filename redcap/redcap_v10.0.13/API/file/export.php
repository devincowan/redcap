<?php


# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}
// If user has De-id rights or Remove Identifiers rights AND this File Upload field is an Identifier, then give error
elseif ($post['export_rights'] > 1 && isset($Proj->metadata[$post['field']]) && $Proj->metadata[$post['field']]['field_phi'] == '1') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because this File Upload field is an Identifier field while at the same time you currently have either "De-Identified" data export rights or "Remove all tagged Identifier fields" data export rights, which prevent you from exporting fields tagged as Identifiers. Higher level data export rights are required for this operation.'));
}

$project_id = $post['projectid'];
$record = $post['record'];
$fieldName = $post['field'];
$eventName = $post['event'];
$eventId = "";

# Get the event id for the item to be downloaded
if ($longitudinal)
{
	# check the event that was passed in and get the id associated with it
	if ($eventName != "") {
		$event = Event::getEventIdByKey($project_id, array($eventName));

		if (count($event) > 0 && $event[0] != "") {
			$eventId = $event[0];
		}
		else {
			RestUtility::sendResponse(400, "invalid event");
		}
	}
	else {
		RestUtility::sendResponse(400, "invalid event");
	}
}
else
{
	$sql = "SELECT m.event_id
			FROM redcap_events_metadata m, redcap_events_arms a
			WHERE a.project_id = $project_id and a.arm_id = m.arm_id
			LIMIT 1";
	$eventId = db_result(db_query($sql), 0);
}

// If project has repeating forms/events, then use the repeat_instance
$form_name = $Proj->metadata[$fieldName]['form_name'];
$instance = (isset($post['repeat_instance']) && is_numeric($post['repeat_instance']) && $post['repeat_instance'] > 0) ? $post['repeat_instance'] : 1;
if (!$Proj->isRepeatingForm($eventId, $form_name) && !($Proj->longitudinal && $Proj->isRepeatingEvent($eventId))) {
	$instance = 1;
}

# check to make sure the record exists
$sql = "SELECT 1
		FROM redcap_data
		WHERE project_id = $project_id
			AND record = '".db_escape($record)."'
			AND event_id = $eventId
			LIMIT 1";
$result = db_query($sql);
if (db_num_rows($result) == 0) {
	RestUtility::sendResponse(400, "The record '$record' does not exist");
}

# determine if the field exists in the metadata table and if of type 'file'
$sql = "SELECT 1
		FROM redcap_metadata
		WHERE project_id = $project_id
			AND field_name = '".db_escape($fieldName)."'
			AND element_type = 'file'";
$metadataResult = db_query($sql);
if (db_num_rows($metadataResult) == 0) {
	RestUtility::sendResponse(400, "The field '$fieldName' does not exist or is not a 'file' field");
}

# get the doc_id from the data table
$sql = "SELECT *
		FROM redcap_data
		WHERE project_id = $project_id
			AND record = '".db_escape($record)."'
			AND event_id = $eventId
			AND field_name = '".db_escape($fieldName)."'";
$sql .= $instance > 1 ? " AND instance = '".db_escape($instance)."'" : " AND instance is NULL";
$result = db_query($sql);
if (db_num_rows($result) == 0) {
	RestUtility::sendResponse(400, "There is no file to download for this record");
}

# get the file information
$row = db_fetch_assoc($result);
$sql = "SELECT *
		FROM redcap_edocs_metadata
		WHERE project_id = $project_id
			AND doc_id = '".db_escape($row['value'])."'";
$q = db_query($sql);
if (db_num_rows($q) == 0) {
	RestUtility::sendResponse(400, "There is no file to download for this record");
}

$this_file = db_fetch_array($q);


// For content=fileinfo, return JSON of size of file in bytes AND also the original file name
// (method only used for mobile app to detect file name and size of file)
if (isset($post['fileinfo']))
{
	$content = json_encode(array('size'=>(int)$this_file['doc_size'], 'name'=>$this_file['doc_name']));
	RestUtility::sendResponse(200, $content, 'json');
}
	
$_GET['event_id'] = $eventId; // Set event_id for logging purposes only


if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
{
	# verify that the edoc folder exists
	if (!is_dir(EDOC_PATH)) {
		$message = "The server folder ".EDOC_PATH." does not exist! Thus it is not a valid directory for edoc file storage";
		RestUtility::sendResponse(400, $message);
	}

	# create full path to the file
	$local_file = EDOC_PATH . $this_file['stored_name'];

	# determine of the file exists on the server
	if (file_exists($local_file) && is_file($local_file)) {
		# log the request
		logEvent();

		# Send the response to the requestor
		RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
	}
	else {
		$message = "The file \"$local_file\" (\"{$this_file['doc_name']}\") does not exist";
		RestUtility::sendResponse(400, $message);
	}
}

elseif ($edoc_storage_option == '2')
{
	// S3
	$local_file = APP_PATH_TEMP . $this_file['stored_name'];
	try {
		$s3 = Files::s3client();
		$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$this_file['stored_name'], 'SaveAs'=>$local_file));
		# log the request
		logEvent();
    	# Send the response to the requestor
		RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
		// Now remove file from temp directory
		unlink($local_file);
	} catch (Aws\S3\Exception\S3Exception $e) {
		$message = "Error obtaining the file \"{$this_file['doc_name']}\"";
		RestUtility::sendResponse(400, $message);
	}
}

elseif ($edoc_storage_option == '4')
{
	// Azure
	$local_file = APP_PATH_TEMP . $this_file['stored_name'];
	$blobClient = Files::azureBlobClient();
	$blob = $blobClient->getBlob($GLOBALS['azure_container'], $this_file['stored_name']);
	file_put_contents($local_file, $blob->getContentStream());
	# log the request
	logEvent();
	# Send the response to the requestor
	RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
	// Now remove file from temp directory
	unlink($local_file);
}
else
{
	# Download using WebDAV
	if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); //use HTTP/1.1
	$wdc->set_debug(false);
	if (!$wdc->open()) {
		RestUtility::sendResponse(400, "Could not open server connection");
	}
	if (substr($webdav_path,-1) != '/') {
		$webdav_path .= '/';
	}
	$http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
	$wdc->close();

	# log the request
	logEvent();

	# Send the response to the requestor
	RestUtility::sendFileContents(200, $contents, $this_file['doc_name'], $this_file['mime_type']);
}

/**
 * function to log the event
 */
function logEvent()
{
	global $post, $record, $field, $sql, $playground, $instance;
	Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",$record,$field,"Download file (API$playground)", 
						"", "", "", true, null, $instance);
}
