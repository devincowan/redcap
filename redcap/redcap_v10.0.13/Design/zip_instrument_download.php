<?php


// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Make sure server has ZipArchive ability (i.e. is on PHP 5.2.0+)
if (!Files::hasZipArchive()) {
	exit('ERROR: ZipArchive is not installed. It must be installed to use this feature.');
}

// If calling draft mode, then make sure project is in draft mode
if (isset($_GET['draft']) && !($status > 0 && $draft_mode > 0)) unset($_GET['draft']);

// Get form fields
$forms = (isset($_GET['draft'])) ? $Proj->forms_temp : $Proj->forms;
if (!isset($forms[$_GET['page']])) exit("ERROR!");

// Set name of temp file for zip
$inOneHour = date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y")));
$target_zip = APP_PATH_TEMP . "{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
$download_filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($forms[$_GET['page']]['menu'], ENT_QUOTES)))), 0, 20)
				   . "_".(isset($_GET['draft']) ? "draft_" : "").date("Y-m-d_Hi").".zip";
$zip_parent_folder = "attachments";
// Generate data dictionary file for *just* this form
$data_dictionary = addBOMtoUTF8(MetaData::getDataDictionary('csv', true, array_keys($forms[$_GET['page']]['fields']), array(), false, isset($_GET['draft'])));

// If using WebDAV storage, then connect to WebDAV beforehand
if ($edoc_storage_option == '1') {
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
}

// Create zip file
$zip = new ZipArchive;
// Start writing to zip file
if ($zip->open($target_zip, ZipArchive::CREATE) !== TRUE) exit("ERROR!");
// Add OriginID.txt to zip file
$zip->addFromString("OriginID.txt", SERVER_NAME);
// Add data dictionary to zip file
$zip->addFromString("instrument.csv", $data_dictionary);
// Add any attachments
$metadata = (isset($_GET['draft'])) ? $Proj->metadata_temp : $Proj->metadata;
$edocs = $video_urls = array();
foreach ($metadata as $this_field=>$attr) {
	// Only look at descriptive fields for this form
	if (!($attr['form_name'] == $_GET['page'] && $attr['element_type'] == 'descriptive')) continue;
	// Add edoc_id to array
	if (is_numeric($attr['edoc_id'])) {
		$edocs[$attr['edoc_id']]['field'] = $this_field;
	}
	// Add video URL to array
	elseif ($attr['video_url'] != '') {
		$video_urls[$this_field] = $attr['video_url'];
	}
}
// If attachments exist, then get their file metadata attributes
if (!empty($edocs)) {
	// Get file metadata from table
	$sql = "select doc_id, stored_name, doc_name from redcap_edocs_metadata
			where project_id = $project_id and doc_id in (".prep_implode(array_keys($edocs)).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$edocs[$row['doc_id']]['stored_name'] = $row['stored_name'];
		$edocs[$row['doc_id']]['doc_name'] = $row['doc_name'];
	}
	// Loop through documents and add them to zip
	foreach ($edocs as $this_edoc_id=>$attr) {
		// Set attachment filename in the zip
		$attachment_zip_filename = "$zip_parent_folder/{$attr['field']}/{$attr['doc_name']}";
		// If not using local storage for edocs, then obtain file contents before adding to zip
		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL: Add from "edocs" folder (use default or custom path for storage)
			if (file_exists(EDOC_PATH . $attr['stored_name'])) {
				// Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
				$zip->addFile(EDOC_PATH . $attr['stored_name'], $attachment_zip_filename);
			}
		} elseif ($edoc_storage_option == '2') {
			// S3
			// Open connection to create file in memory and write to it
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$attr['stored_name'], 'SaveAs'=>APP_PATH_TEMP . $attr['stored_name']));
				// Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
				if (file_exists(APP_PATH_TEMP . $attr['stored_name'])) {
					// Get file's contents from temp directory and add file contents to zip file
					$zip->addFromString($attachment_zip_filename, file_get_contents(APP_PATH_TEMP . $attr['stored_name']));
					// Now remove file from temp directory
					unlink(APP_PATH_TEMP . $attr['stored_name']);
				}
			} catch (Aws\S3\Exception\S3Exception $e) {
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = Files::azureBlobClient();
			$blob = $blobClient->getBlob($GLOBALS['azure_container'], $attr['stored_name']);
			$data = stream_get_contents($blob->getContentStream());
			$zip->addFromString($attachment_zip_filename, $data);
			unset($data);
		} else {
			// WebDAV
			$contents = '';
			$wdc->get($webdav_path . $attr['stored_name'], $contents); //$contents is produced by webdav class
			// Add file contents to zip file
			if ($contents == null) $contents = '';
			$zip->addFromString($attachment_zip_filename, $contents);
		}
	}
}
// Add video_urls, if any
foreach ($video_urls as $this_field=>$this_url) {
	// Add to zip
	$zip->addFromString("$zip_parent_folder/$this_field/video_url.URL", "[InternetShortcut]\nURL=".$this_url);
}
// Done adding to zip file
$zip->close();
// Logging
$log_descrip = "Download instrument ZIP file";
Logging::logEvent("", "redcap_metadata", "MANAGE", $_GET['page'], "form_name = ".$_GET['page'], $log_descrip);
// Download file and then delete it from the server
header('Pragma: anytextexeptno-cache', true);
header('Content-Type: application/octet-stream"');
header('Content-Disposition: attachment; filename="'.$download_filename.'"');
header('Content-Length: ' . filesize($target_zip));
ob_end_flush();
readfile_chunked($target_zip);
unlink($target_zip);