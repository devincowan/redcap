<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$library_id = $_POST['library_id'];
$list = $_POST['imageList'];
if (!is_numeric($library_id)) exit;


// Returns the contents of an edoc file when given its "stored_name" on the file system (i.e. from the edocs_metadata table)
function getEdocContents($stored_name)
{
	global $edoc_storage_option;

	if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {

		//Download from "edocs" folder (use default or custom path for storage)
		$local_file = EDOC_PATH . $stored_name;
		if (file_exists($local_file) && is_file($local_file))
		{
			// Open file for reading and output
			$fp = fopen($local_file, 'rb');
			$contents = fread($fp, filesize($local_file));
			fclose($fp);
		}
		else
		{
			## Give error message
			return false;
		}

	} elseif ($edoc_storage_option == '2') {
		// S3
		try {
			$s3 = Files::s3client();
			$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_name));
			$contents = $object['Body'];
		} catch (Aws\S3\Exception\S3Exception $e) {
			return false;
		}

	} elseif ($edoc_storage_option == '4') {
		// Azure
		$blobClient = Files::azureBlobClient();
		$blob = $blobClient->getBlob($GLOBALS['azure_container'], $stored_name);
		$contents = stream_get_contents($blob->getContentStream());
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
			return false;
		}
		$http_status = $wdc->get($webdav_path . $stored_name, $contents); //$contents is produced by webdav class
		$wdc->close();

	}
	// Return the file contents
	return $contents;
}

try {

	// Chop off first doc_id in the list of doc_id's
	$listArray = explode(",", $list, 2);

	// Upload the first image id in the list
	if (count($listArray) > 0 && is_numeric($listArray[0]))
	{
		//send the image to the library
		$sql = "select stored_name from redcap_edocs_metadata where doc_id = " . $listArray[0];
		$qry = db_query($sql);
		if ($row = db_fetch_assoc($qry))
		{
			// Retrieve the contents of the attachment
			$contents = getEdocContents($row['stored_name']);
			if ($contents !== false && $contents != '')
			{
				$params = array(
					'imgData'=> $contents,
					'imgName'=> $row['stored_name'],
					'library_id'=>$library_id
				);
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($curl, CURLOPT_VERBOSE, 0);
				curl_setopt($curl, CURLOPT_URL, SHARED_LIB_UPLOAD_ATTACH_URL);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_TIMEOUT, 1000);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
				$response = curl_exec($curl);
				// error_log('$response for file '.$row['stored_name'].' is '.$response);
				curl_close($curl);
			}
			else
			{
				// error_log('error uploading file '.$row['stored_name'].' - file has no content');
			}
		}
	}

	// Relaunch image loader iteratively for any remaining images
	if (count($listArray) > 1)
	{
		$list = $listArray[1];

		$params = array('library_id'=>$library_id, 'imageList'=>$list);

		$imgCurl = curl_init();
		curl_setopt($imgCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($imgCurl, CURLOPT_VERBOSE, 0);
		curl_setopt($imgCurl, CURLOPT_URL, APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/SharedLibrary/image_loader.php");
		curl_setopt($imgCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($imgCurl, CURLOPT_POST, true);
		curl_setopt($imgCurl, CURLOPT_TIMEOUT, 1000);
		curl_setopt($imgCurl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($imgCurl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
		curl_setopt($imgCurl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		$response = curl_exec($imgCurl);
		// error_log($response);
		curl_close($imgCurl);
	}

}
catch (Exception $e)
{
   // error_log("error uploading file: ".$e->getMessage());
}
