<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$library_id = $_POST['library_id'];
$newFormName = $_POST['$newFormName'];
if (!is_numeric($library_id)) exit;

//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// Get edoc file info (has already been added to table for this form but image has not been downloaded yet)
$sql = "select e.doc_id, e.stored_name from redcap_edocs_metadata e, $metadata_table m
		where m.project_id = $project_id and m.project_id = e.project_id
		and m.edoc_id = e.doc_id and e.delete_date is null and m.form_name = '" . db_escape($newFormName) . "'";
$q = db_query($sql);
while ($edocs = db_fetch_assoc($q))
{
	$doc_id = $edocs['doc_id'];
	$stored_name = $edocs['stored_name'];
	$new_stored_name = date('YmdHis') . "_pid" . PROJECT_ID . "_" . generateRandomHash(6) . getFileExt($stored_name, true);

	// Download this attachment
	$curlImg = curl_init();
	curl_setopt($curlImg, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curlImg, CURLOPT_VERBOSE, 0);
	curl_setopt($curlImg, CURLOPT_URL, SHARED_LIB_DOWNLOAD_URL . "?attr=image&id=$library_id&file=$stored_name");
	curl_setopt($curlImg, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curlImg, CURLOPT_HTTPGET, true);
	curl_setopt($curlImg, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
	curl_setopt($curlImg, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
	$attachmentContents = curl_exec($curlImg);
	curl_close($curlImg);

	$result = 0;
	if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
	{
		// Local: Upload to "edocs" folder
		$fh = fopen(EDOC_PATH . $new_stored_name, "wb");
		fwrite($fh, $attachmentContents);
		$result = 1;
	}
	elseif ($edoc_storage_option == '2')
	{
		// S3
		try {
			$s3 = Files::s3client();
			$s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_name, 'Body'=>$attachmentContents, 'ACL'=>'private'));
			$result = 1;
		} catch (Aws\S3\Exception\S3Exception $e) {
			
		}
	}
	elseif ($edoc_storage_option == '4')
	{
		// Azure
		$blobClient = Files::azureBlobClient();
		$blobClient->createBlockBlob($GLOBALS['azure_container'], $stored_name, $attachmentContents);
		$result = 1;
	}
	else
	{
		// Upload using WebDAV
		if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
		$wdc = new WebdavClient();
		$wdc->set_server($webdav_hostname);
		$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
		$wdc->set_user($webdav_username);
		$wdc->set_pass($webdav_password);
		$wdc->set_protocol(1); // use HTTP/1.1
		$wdc->set_debug(false); // enable debugging?
		if (!$wdc->open()) {
			sleep(1);
		}
		if (substr($webdav_path,-1) != '/') {
			$webdav_path .= '/';
		}
		$http_status = $wdc->put($webdav_path . $new_stored_name, $attachmentContents);
		$result = 1;

	}

	if ($result == 1) {
		// If successfully downloaded file, update edocs_metadata table with new stored_name
		$update_edocs = "update redcap_edocs_metadata set stored_name = '$new_stored_name', stored_date = '".NOW."'
						 where project_id = $project_id and doc_id = $doc_id";
	} else {
		// Error occurred, so set flags to 'deleted' in edocs_metadata table
		$update_edocs = "update redcap_edocs_metadata set stored_name = '$new_stored_name', stored_date = '".NOW."',
						 delete_date = '".NOW."', date_deleted_server = '".NOW."'
						 where project_id = $project_id and doc_id = $doc_id";
		// error_log("unable to download attachment doc_id $doc_id for project_id $project_id");
	}
	db_query($update_edocs);
}