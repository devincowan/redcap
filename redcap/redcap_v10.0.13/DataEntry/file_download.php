<?php



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
if (!isset($_GET['doc_id_hash']) || (isset($_GET['doc_id_hash']) && $_GET['doc_id_hash'] != Files::docIdHash($_GET['id']))) {
	exit("{$lang['global_01']}!");
}
$instance = (isset($_GET['instance']) && is_numeric($_GET['instance']) && $_GET['instance'] > 1) ? $_GET['instance'] : 1;
$version_log = "";

if (!defined("FORCE_INIT_GLOBAL"))
{
	// If downloading an attachment for a project, then verify
	if (isset($_GET['type']) && $_GET['type'] == "attachment" && !defined("NOAUTH"))
	{
		if (DataEntry::isRecordValue()) {
			// If the file is actually a data value on a record, then it's not an attachment, so stop here
			die("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");
		}
	}
    // Older version of a file via File Version History in Data History popup
    elseif (isset($_GET['doc_version']) && is_numeric($_GET['doc_version']) && isset($_GET['doc_version_hash']))
    {
        if ($_GET['doc_version_hash'] != Files::docIdHash($_GET['id']."v".$_GET['doc_version'])) {
            exit("{$lang['global_01']}!");
        }
        $version_log = " (V{$_GET['doc_version']})";
    }
	// Surveys only: Perform double checking to make sure the survey participant has rights to this file
	elseif (isset($_GET['s']) && !empty($_GET['s']))
	{
		DataEntry::checkSurveyFileRights();
	}
	// Non-surveys: Check form-level rights and DAGs to ensure user has access to this file
	elseif (!isset($_GET['s']) || empty($_GET['s']))
	{
		DataEntry::checkFormFileRights();
	}
}

//Download file from the "edocs" web server directory
$sql = "select * from redcap_edocs_metadata where doc_id = '" . db_escape($_GET['id']). "' and delete_date is null";
if (defined("PROJECT_ID")) $sql .= " and project_id = " . PROJECT_ID;
$q = db_query($sql);
if (!db_num_rows($q)) {
	die("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");
}
$this_file = db_fetch_assoc($q);


if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
	// LOCAL
	//Use custom edocs folder (set in Control Center)
	if (!is_dir(EDOC_PATH))
	{
		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		print  "<div class='red'>
					<b>{$lang['global_01']}!</b><br>{$lang['file_download_04']} <b>".EDOC_PATH."</b> {$lang['file_download_05']} ";
		if ($super_user) print "{$lang['file_download_06']} <a href='".APP_PATH_WEBROOT."ControlCenter/modules_settings.php' style='text-decoration:underline;font-family:verdana;font-weight:bold;'>{$lang['global_07']}</a>.";
		print  "</div>";
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	//Download from "edocs" folder (use default or custom path for storage)
	$local_file = EDOC_PATH . $this_file['stored_name'];
	if (file_exists($local_file) && is_file($local_file))
	{
		header('Pragma: anytextexeptno-cache', true);
		if (isset($_GET['stream'])) {
			// Stream the file (e.g. audio)
			header('Content-Type: '.mime_content_type($local_file));
			header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
			header('Content-Length: ' . $this_file['doc_size']);
			header("Content-Transfer-Encoding: binary");
			header('Accept-Ranges: bytes');
			header('Connection: Keep-Alive');
			header('X-Pad: avoid browser bug');
			header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
		} else {
			// Download
			header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
			header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
		}
		ob_end_flush();
		readfile_chunked($local_file);
	}
	else
	{
	    die('<b>'.$lang['global_01'].$lang['colon'].'</b> '.$lang['file_download_08'].' <b>"'.$local_file.
	    	'"</b> ("'.$this_file['doc_name'].'") '.$lang['file_download_09'].'!');
	}

} elseif ($edoc_storage_option == '2') {
	// S3
	try {
		$s3 = Files::s3client();
		$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$this_file['stored_name'], 'SaveAs'=>APP_PATH_TEMP . $this_file['stored_name']));
		header('Pragma: anytextexeptno-cache', true);
		if (isset($_GET['stream'])) {
			// Stream the file (e.g. audio)
			header('Content-Type: '.mime_content_type(APP_PATH_TEMP . $this_file['stored_name']));
			header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
			header('Content-Length: ' . $this_file['doc_size']);
			header("Content-Transfer-Encoding: binary");
			header('Accept-Ranges: bytes');
			header('Connection: Keep-Alive');
			header('X-Pad: avoid browser bug');
			header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
		} else {
			// Download
			header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
			header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
		}
		ob_end_flush();
		readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
		// Now remove file from temp directory
		unlink(APP_PATH_TEMP . $this_file['stored_name']);
	} catch (Aws\S3\Exception\S3Exception $e) {
	}

} elseif ($edoc_storage_option == '4') {
	// Azure
	$blobClient = Files::azureBlobClient();
	$blob = $blobClient->getBlob($GLOBALS['azure_container'], $this_file['stored_name']);
	file_put_contents(APP_PATH_TEMP . $this_file['stored_name'], $blob->getContentStream());
	header('Pragma: anytextexeptno-cache', true);
	if (isset($_GET['stream'])) {
		// Stream the file (e.g. audio)
		header('Content-Type: '.mime_content_type(APP_PATH_TEMP . $this_file['stored_name']));
		header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
		header('Content-Length: ' . $this_file['doc_size']);
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		header('Connection: Keep-Alive');
		header('X-Pad: avoid browser bug');
		header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
	} else {
		// Download
		header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
		header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
	}
	ob_end_flush();
	readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
	// Now remove file from temp directory
	unlink(APP_PATH_TEMP . $this_file['stored_name']);

} else {

	//  WebDAV
	if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); //use HTTP/1.1
	$wdc->set_debug(false);
	if (!$wdc->open()) {
		exit($lang['global_01'].': '.$lang['file_download_11']);
	}
	if (substr($webdav_path,-1) != '/') {
		$webdav_path .= '/';
	}
	$http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
	$wdc->close();

	//Send file headers and contents
	header('Pragma: anytextexeptno-cache', true);
	if (isset($_GET['stream'])) {
		// Stream the file (e.g. audio)
		header('Content-Type: '.$this_file['mime_type']);
		header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
		header('Content-Length: ' . $this_file['doc_size']);
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		header('Connection: Keep-Alive');
		header('X-Pad: avoid browser bug');
		header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
	} else {
		// Download
		header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
		header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
	}
	ob_clean();
	flush();
	print $contents;

}

// Do logging
if (isset($_GET['type']) && $_GET['type'] == "attachment")
{
	// When downloading field image/file attachments
	defined("NOAUTH") or Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",$_GET['record'],$_GET['field_name'],"Download image/file attachment");
}
else
{
	// When downloading edoc files on a data entry form/survey
	defined("NOAUTH") or Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",$_GET['record'],$_GET['field_name'].$version_log,"Download uploaded document",
													"", "", "", true, null, $instance);
}
