<?php



// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If ID is not in query_string, then return error
if (!is_numeric($_GET['id']) || !is_numeric($_GET['res_id'])) exit("{$lang['global_01']}!");


//Download file from the "edocs" web server directory
$sql = "select m.* from redcap_edocs_metadata m, redcap_data_quality_resolutions r
		where m.project_id = $project_id and m.doc_id = ".checkNull($_GET['id'])."
		and r.res_id = ".checkNull($_GET['res_id'])." and r.upload_doc_id = m.doc_id limit 1";
$q = db_query($sql);
if (!db_num_rows($q)) exit("<b>{$lang['global_01']}:</b> {$lang['file_download_03']}");
$this_file = db_fetch_array($q);


if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {

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
		header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
		header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
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
		header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
		header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
		ob_end_flush();
		readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
		// Now remove file from temp directory
		unlink(APP_PATH_TEMP . $this_file['stored_name']);
	} catch (Aws\S3\Exception\S3Exception $e) {
	    die('<b>'.$lang['global_01'].$lang['colon'].'</b> '.$lang['file_download_08'].' "'.$this_file['doc_name'].'" '.$lang['file_download_09'].'!');
	}

} elseif ($edoc_storage_option == '4') {
	// Azure
	$blobClient = Files::azureBlobClient();
	$blob = $blobClient->getBlob($GLOBALS['azure_container'], $this_file['stored_name']);
	file_put_contents(APP_PATH_TEMP . $this_file['stored_name'], $blob->getContentStream());
	header('Pragma: anytextexeptno-cache', true);
	header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
	header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
	ob_end_flush();
	readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
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
		exit($lang['global_01'].': '.$lang['file_download_11']);
	}
	if (substr($webdav_path,-1) != '/') {
		$webdav_path .= '/';
	}
	$http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
	$wdc->close();

	//Send file headers and contents
	header('Pragma: anytextexeptno-cache', true);
	header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
	//header('Content-Length: '.$this_file['doc_size']);
	header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
	ob_clean();
	flush();
	print $contents;

}

## Logging
// Set event_id in query string just for logging purposes
$_GET['event_id'] = $queryAttr['event_id'];
// Obtain record, event, field, rule from res_id
$dq = new DataQuality();
$queryAttr = $dq->getDataResAttributesFromResId($_GET['res_id']);
// Set data values as json_encoded
$logDataValues = json_encode(array('res_id'=>$_GET['res_id'],'doc_id'=>$_GET['id'],'record'=>$queryAttr['record'],'event_id'=>$queryAttr['event_id'],
					'field'=>$queryAttr['field_name'],'rule_id'=>$queryAttr['rule_id']));
// Lot it
Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",$queryAttr['record'],$logDataValues,"Download uploaded document for data query response");
