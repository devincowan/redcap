<?php


# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}

$project_id = $post['projectid'];
$field = $post['field'];

// Validate the field name
if (!isset($Proj->metadata[$field])) {
	RestUtility::sendResponse(400, "Invalid field");
}
// Make sure the field is a descriptive field and has an attachment
if ($Proj->metadata[$field]['element_type'] != 'descriptive') {
	RestUtility::sendResponse(400, "The field '$field' is not a 'descriptive' field");
}
// Make sure the field is a descriptive field and has an attachment
if (!($Proj->metadata[$field]['edoc_id'] != '' && $Proj->metadata[$field]['edoc_display_img'] == '1')) {
	RestUtility::sendResponse(400, "The field '$field' does not have an attachment");
}

// Set edoc_id
$edoc_id = $Proj->metadata[$field]['edoc_id'];

# get the file information
$row = db_fetch_assoc($result);
$sql = "SELECT *
		FROM redcap_edocs_metadata
		WHERE project_id = $project_id
			AND doc_id = $edoc_id";
$q = db_query($sql);
if (db_num_rows($q) == 0) {
	RestUtility::sendResponse(400, "There is no file to download for this record");
}

$this_file = db_fetch_array($q);


// For content=filesize, return JSON of size of file in bytes AND also the original file name
// (method only used for mobile app to detect file name and size of file)
if (isset($post['fileinfo']))
{
	$content = json_encode(array('size'=>(int)$this_file['doc_size'], 'name'=>$this_file['doc_name']));
	RestUtility::sendResponse(200, $content, 'json');
}


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

	# Send the response to the requestor
	RestUtility::sendFileContents(200, $contents, $this_file['doc_name'], $this_file['mime_type']);
}