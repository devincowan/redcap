<?php


// Sleep for a short amount of time to pace several simulataneous requests
if (isset($_GET['usleep']) && is_numeric($_GET['usleep']))
{
	usleep((int)$_GET['usleep']);
}

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
	@define("NOAUTH", true);
} elseif (!isset($_GET['pid']) && isset($_GET['origin']) && $_GET['origin'] == 'messaging') {
	// If viewing an image in a User Messaging thread, which is not in a project, then bypass init project
	define("FORCE_INIT_GLOBAL", true);
}

if (defined("FORCE_INIT_GLOBAL")) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Ensure the file attachment belongs to a thread that the current user has access to
	if (!Messenger::fileBelongsToUserThread($_GET['id'])) {
		exit("{$lang['global_01']}!");
	}
} else {
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
}

// If ID is not in query_string, then return error
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) exit("{$lang['global_01']}!");

// Confirm the hash of the doc_id
if (!isset($_GET['origin'])){
	if (!isset($_GET['doc_id_hash']) || (isset($_GET['doc_id_hash']) && $_GET['doc_id_hash'] != Files::docIdHash($_GET['id']))) {
		exit("{$lang['global_01']}!");
	}
}

// Ensure that this file belongs to this project
$sql = "select * from redcap_edocs_metadata where doc_id = '" . db_escape($_GET['id']). "' and delete_date is null";
if (defined("PROJECT_ID")) $sql .= " and project_id = " . PROJECT_ID;
$q = db_query($sql);
$edoc_info = db_fetch_assoc($q);
$isValidFile = db_num_rows($q);

if (!$isValidFile)
{
	## Give error message
	header('Content-type: image/png');
}
else
{
	## Display image

	// If missing mime-type, then try to add it manually (especially for PNGs from jSignature)
	if ($edoc_info['mime_type'] == '') {
		$edoc_info['mime_type'] = 'image/'.strtolower(getFileExt($edoc_info['doc_name']));
	}

	if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {

		//Download from "edocs" folder (use default or custom path for storage)
		$local_file = EDOC_PATH . $edoc_info['stored_name'];
		if (file_exists($local_file) && is_file($local_file))
		{
			// Set image header
			header('Content-type: ' . $edoc_info['mime_type']);
			// Output image data
			ob_end_flush();
			readfile_chunked($local_file);
		}
		else
		{
			## Give error message
			header('Content-type: image/png');
		}

	} elseif ($edoc_storage_option == '2') {
		// S3
		try {
			$s3 = Files::s3client();
			$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$edoc_info['stored_name']));
			// Set image header
			header('Content-type: ' . $edoc_info['mime_type']);
			// Output image data
			print $object['Body'];
			flush();
		} catch (Aws\S3\Exception\S3Exception $e) {
			## Give error message
			header('Content-type: image/png');
		}

	} elseif ($edoc_storage_option == '4') {
		// Azure
		$blobClient = Files::azureBlobClient();
		$blob = $blobClient->getBlob($GLOBALS['azure_container'], $edoc_info['stored_name']);
		$data = stream_get_contents($blob->getContentStream());
		// Set image header
		header('Content-type: ' . $edoc_info['mime_type']);
		// Output image data
		print $data;
		flush();

	} else {

		//Download using WebDAV
		if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
		$wdc = new WebdavClient();
		$wdc->set_server($webdav_hostname);
		$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
		$wdc->set_user($webdav_username);
		$wdc->set_pass($webdav_password);
		$wdc->set_protocol(1); //use HTTP/1.1
		$wdc->set_debug(false);
		if (!$wdc->open()) {
			## Give error message
			header('Content-type: image/png');
		}
		$http_status = $wdc->get($webdav_path . $edoc_info['stored_name'], $contents); //$contents is produced by webdav class
		$wdc->close();

		//Send file headers and contents
		header('Content-type: ' . $edoc_info['mime_type']);
		print $contents;
		flush();

	}

}
