<?php



/**
 * FILES Class
 * Contains methods used with regard to uploaded files
 */
class Files
{
	/**
	 * DETERMINE IF WE'RE ON A VERSION OF PHP THAT SUPPORTS ZIPARCHIVE (PHP 5.2.0)
	 * Returns boolean.
	 */
	public static function hasZipArchive()
	{
		return (class_exists('ZipArchive'));
	}


	/**
	 * DETERMINE IF PROJECT HAS ANY "FILE UPLOAD" FIELDS IN METADATA
	 * Returns boolean.
	 */
	public static function hasFileUploadFields()
	{
		global $Proj;
		return $Proj->hasFileUploadFields;
	}


	/**
	 * CALCULATE SERVER SPACE USAGE OF FILES UPLOADED
	 * Returns usage in bytes
	 */
	public static function getEdocSpaceUsage()
	{
		// Default
		$total_edoc_space_used = 0;
		// Get space used by edoc file uploading on data entry forms. Count using table values (since we cannot easily call external server itself).
		$sql = "select if(sum(doc_size) is null, 0, sum(doc_size)) from redcap_edocs_metadata where date_deleted_server is null";
		$total_edoc_space_used += db_result(db_query($sql), 0);
		// Additionally, get space used by send-it files (for location=1 only, because loc=3 is edocs duplication). Count using table values (since we cannot easily call external server itself).
		$sql = "select if(sum(doc_size) is null, 0, sum(doc_size)) from redcap_sendit_docs
				where location = 1 and expire_date > '".NOW."' and date_deleted is null";
		$total_edoc_space_used += db_result(db_query($sql), 0);
		// Return total
		return $total_edoc_space_used;
	}


    /**
     * RETURN THE ORIGINAL FILENAME OF AN EDOC FILE FROM THE EDOC_ID
     */
    public static function getEdocName($edoc_id, $returnStoredName=false)
    {
        if (!is_numeric($edoc_id)) return false;
        // Return user-facing filename or the stored filename of the file on the server?
		$col = $returnStoredName ? 'stored_name' : 'doc_name';
        // Download file from the "edocs" web server directory
        $sql = "select $col from redcap_edocs_metadata where doc_id = " . db_escape($edoc_id);
        $q = db_query($sql);
        if (!db_num_rows($q)) return false;
        $this_file = db_fetch_assoc($q);
        return $this_file[$col];
    }


    /**
     * RETURN FALSE IF THE FILE HAS BEEN DELETED BY USER, AND IF TRUE THEN RETURN TIME OF DELETION
     */
    public static function wasEdocDeleted($edoc_id)
    {
        if (!is_numeric($edoc_id)) return false;
        $sql = "select delete_date from redcap_edocs_metadata where doc_id = " . db_escape($edoc_id);
        $q = db_query($sql);
        if (db_num_rows($q) == 0) return false;
        $delete_date = db_result($q, 0);
        return ($delete_date == '') ? false : $delete_date;
    }


	/**
	 * RETURN THE CONTENTS AS A STRING OF AN EDOC FILE FROM EDOC STORAGE LOCATION
	 * Returns array of "mime_type" (string), "doc_name" (string), and "contents" (string) or FALSE if failed
	 */
	public static function getEdocContentsAttributes($edoc_id)
	{
		global $lang, $edoc_storage_option, $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;

		if (!is_numeric($edoc_id)) return false;

		// Download file from the "edocs" web server directory
		$sql = "select * from redcap_edocs_metadata where doc_id = ".db_escape($edoc_id);
		$q = db_query($sql);
		if (!db_num_rows($q)) return false;
		$this_file = db_fetch_assoc($q);

		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			//Download from "edocs" folder (use default or custom path for storage)
			$local_file = EDOC_PATH . $this_file['stored_name'];
			if (file_exists($local_file) && is_file($local_file)) {
				return array($this_file['mime_type'], $this_file['doc_name'], file_get_contents($local_file));
			}
		} elseif ($edoc_storage_option == '2') {
			// S3
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$this_file['stored_name']));
				return array($this_file['mime_type'], $this_file['doc_name'], $object['Body']);
			} catch (Aws\S3\Exception\S3Exception $e) {
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = Files::azureBlobClient();
			$blob = $blobClient->getBlob($GLOBALS['azure_container'], $this_file['stored_name']);
			return array($this_file['mime_type'], $this_file['doc_name'], stream_get_contents($blob->getContentStream()));
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
				return false;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
			$wdc->close();
			return array($this_file['mime_type'], $this_file['doc_name'], $contents);
		}
	}


	/**
	 * MOVES FILE FROM EDOC STORAGE LOCATION TO REDCAP'S TEMP DIRECTORY
	 * Returns full file path in temp directory, or FALSE if failed to move it to temp.
	 */
	public static function copyEdocToTemp($edoc_id, $prependHashToFilename=false, $prependTimestampToFilename=false)
	{
		global $edoc_storage_option, $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;

		if (!is_numeric($edoc_id)) return false;

		// Get filenames from edoc_id
		$q = db_query("select doc_name, stored_name from redcap_edocs_metadata where delete_date is null and doc_id = ".db_escape($edoc_id));
		if (!db_num_rows($q)) return false;
		$edoc_orig_filename = db_result($q, 0, 'doc_name');
		$stored_filename = db_result($q, 0, 'stored_name');

		// Set full file path in temp directory. Replace any spaces with underscores for compatibility.		
		$filename_tmp = APP_PATH_TEMP
					  . ($prependTimestampToFilename ? date('YmdHis') . "_" : '')
					  . ($prependHashToFilename ? substr(sha1(rand()), 0, 8) . '_' : '')
					  . str_replace(" ", "_", $edoc_orig_filename);

		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL
			if (file_put_contents($filename_tmp, file_get_contents(EDOC_PATH . $stored_filename))) {
				return $filename_tmp;
			}
			return false;
		} elseif ($edoc_storage_option == '2') {
			// S3
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_filename, 'SaveAs'=>$filename_tmp));
				return $filename_tmp;
			} catch (Aws\S3\Exception\S3Exception $e) {
				return false;
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = Files::azureBlobClient();
			$blob = $blobClient->getBlob($GLOBALS['azure_container'], $stored_filename);
			file_put_contents($filename_tmp, $blob->getContentStream());
			return $filename_tmp;
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
				sleep(1);
				return false;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->get($webdav_path . $stored_filename, $contents); //$contents is produced by webdav class
			$wdc->close();
			if (file_put_contents($filename_tmp, $contents)) {
				return $filename_tmp;
			}
			return false;
		}
		return false;
	}


	/**
	 * DETERMINE IF PROJECT HAS AT LEAST ONE FILE ALREADY UPLOADED FOR A "FILE UPLOAD" FIELD
	 * Returns boolean.
	 */
	public static function hasUploadedFiles()
	{
		global $user_rights;
		// If has no file upload fields, then return false
		if (!self::hasFileUploadFields()) return false;
		// If user is in a DAG, limit to only records in their DAG
		$group_sql = "";
		if ($user_rights['group_id'] != "") {
			$group_sql  = "and d.record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])) . ")";
		}
		// Check if there exists at least one uploaded file
		$sql = "select 1 from redcap_data d, redcap_metadata m where m.project_id = ".PROJECT_ID."
				and m.project_id = d.project_id and d.field_name = m.field_name $group_sql
				and m.element_type = 'file' and d.value != '' limit 1";
		$q = db_query($sql);
		// Return true if one exists
		return (db_num_rows($q) > 0);
	}


	/**
	 * RETURN HASH OF DOC_ID FOR A FILE IN THE EDOCS_METADATA TABLE
	 * This is used for verifying files, especially when uploaded when the record does not exist yet.
	 * Also to protect from people randomly discovering other people's uploaded files by modifying the URL.
	 */
	public static function docIdHash($doc_id)
	{
		global $salt, $__SALT__;
		return sha1($salt . $doc_id . (isset($__SALT__) ? $__SALT__ : ""));
	}


	/**
	 * REPLACE SINGLE LINE IN FILE USING LINE NUMBER
	 * Using very little memory, replaces given line number in file with $replacement_string.
	 * Assumes \n for line break characters.
	 */
	function replaceLineInFile($file, $replacement_string, $line_to_replace)
	{
		if ($line_to_replace < 1) return false;

		// Get contents of the given line
		$fileSearch = new SplFileObject($file);
		$fileSearch->seek($line_to_replace-1); // this is zero based so need to subtract 1
		$lineContents = $fileSearch->current();
		if ($lineContents == "") return false;

		// Get positions
		$linePosEnd = $fileSearch->ftell();
		$lineLength = strlen($lineContents);
		$linePosBegin = $linePosEnd - $lineLength;
		$fileSearch = null; // Close the file object

		// Append new line character to replacement string
		$replacement_string .= "\n";

		// Copy file's contents to temp file in memory (uses very little memory)
		$fpFile = fopen($file, "rw+");
		$fpTemp = fopen('php://temp', "rw+");
		stream_copy_to_stream($fpFile, $fpTemp);

		// Move file's to the position
		fseek($fpFile, $linePosBegin);
		fseek($fpTemp, $linePosEnd);
		// Add the new line to the file
		fwrite($fpFile, $replacement_string);
		// The file ends up with extra stuff appended to the end, so truncate it (need to get file size first)
		$filestat = fstat($fpFile);
		ftruncate($fpFile, $filestat['size'] - $lineLength + strlen($replacement_string));

		// Replace original file with original's with replaced line
		stream_copy_to_stream($fpTemp, $fpFile);

		// Close files
		fclose($fpFile);
		fclose($fpTemp);

		// Return true
		return true;
	}


	/**
	 * REMOVE LINES IN FILE USING LINE NUMBER
	 * Using very little memory, removes specified lines in file with $replacement_string.
	 * Assumes \n for line break characters. Assumes line 1 is the first line of file.
	 */
	function removeLinesInFile($file, $begin_line_num, $num_lines_remove)
	{
		if ($num_lines_remove < 1) return false;

		// Get contents of the given line
		$fileSearch = new SplFileObject($file);
		$fpTemp = fopen('php://temp', "w+");
		// Loop through file to move designated lines to temp file
		for ($line_num = $begin_line_num; $line_num <= ($begin_line_num + $num_lines_remove - 1); $line_num++) {
			$fileSearch->seek($line_num-1); // this is zero based so need to subtract 1
			// Add this line to temp file
			fwrite($fpTemp, $fileSearch->current());
			// If we're at the end of the file, then stop here
			if ($fileSearch->eof()) break;
		}
		$fileSearch = null;
		// Copy temp file's contents to original file in memory (uses very little memory)
		$fpFile = fopen($file, "w+");
		ftruncate($fpFile, 0);
		fseek($fpTemp, 0);
		stream_copy_to_stream($fpTemp, $fpFile);
		// Close files
		fclose($fpFile);
		fclose($fpTemp);
		// Return true
		return true;
	}


	/**
	 * UPLOAD FILE INTO EDOCS FOLDER (OR OTHER SERVER VIA WEBDAV) AND RETURN EDOC_ID# (OR "0" IF FAILED)
	 * Determine if file uploaded as normal FILE input field or as base64 data image via POST, in which $base64data will not be null.
	 */
	public static function uploadFile($file, $project_id = null)
	{
		global $edoc_storage_option;

		// Get basic file values
		$doc_name  = trim(strip_tags(str_replace("'", "", html_entity_decode(stripslashes( $file['name']), ENT_QUOTES))));
		$mime_type = mime_content_type($file['tmp_name']);
		$doc_size  = $file['size'];
		$tmp_name  = $file['tmp_name'];

		if($project_id == null && defined("PROJECT_ID")){
			$project_id = PROJECT_ID;
		}

		// Default result of success
		$result = 0;
		$file_extension = getFileExt($doc_name);
		$stored_name = date('YmdHis') . "_pid" . ($project_id ? $project_id : "0") . "_" . generateRandomHash(6) . getFileExt($doc_name, true);

		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL: Upload to "edocs" folder (use default or custom path for storage)
			if (@move_uploaded_file($tmp_name, EDOC_PATH . $stored_name)) {
				$result = 1;
			}
			if ($result == 0 && @rename($tmp_name, EDOC_PATH . $stored_name)) {
				$result = 1;
			}
			if ($result == 0 && file_put_contents(EDOC_PATH . $stored_name, file_get_contents($tmp_name))) {
				$result = 1;
				unlink($tmp_name);
			}

		} elseif ($edoc_storage_option == '2') {
			// S3
			try {
				$s3 = Files::s3client();
				$s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_name, 'Body'=>file_get_contents($tmp_name), 'ACL'=>'private'));
				$result = 1;
				unlink($tmp_name);
			} catch (Aws\S3\Exception\S3Exception $e) {
				
			}

		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = Files::azureBlobClient();
			$result = $blobClient->createBlockBlob($GLOBALS['azure_container'], $stored_name, file_get_contents($tmp_name));
			if ($result) {
				$result = 1;
				unlink($tmp_name);
			}
		} else {

			// WebDAV
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
				return 0;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			// Check the file size
			$max_file_size = 2147483648; // 2GB in bytes
			if (filesize($tmp_name) > $max_file_size ) {
				$http_status = $wdc->put_file( $webdav_path . $stored_name,  $tmp_name );
			}
			else {
				$fp      = fopen($tmp_name, 'rb');
				$content = fread($fp, filesize($tmp_name));
				fclose($fp);
				if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
					$doc_name = stripslashes($doc_name);
				}
				$target_path = $webdav_path . $stored_name;
				$http_status = $wdc->put($target_path,$content);
			}
			$result = 1;
			unlink($tmp_name);
			$wdc->close();
		}

		// Return doc_id (return "0" if failed)
		if ($result == 0) {
			// For base64 data images stored in temp directory, remove them when done
			if ($base64data != null) unlink($tmp_name);
			// Return error
			return 0;
		} else {
			// Add file info the redcap_edocs_metadata table for retrieval later
			$q = db_query("INSERT INTO redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id, stored_date)
						  VALUES ('" . db_escape($stored_name) . "', '" . db_escape($mime_type) . "', '" . db_escape($doc_name) . "',
						  '" . db_escape($doc_size) . "', '" . db_escape($file_extension) . "',
						  " . ($project_id ? $project_id : "null") . ", '".NOW."')");
			return (!$q ? 0 : db_insert_id());
		}

	}


	// Return array of mime types
    public static function get_mime_types()
	{
        return array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            // ms office
            'rtf' => 'application/rtf',
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );
	}


	// Determine the file extension based on the Mime Type passed.
	// Return false if not found
    public static function get_file_extension_by_mime_type($mimetype)
	{
		$mimetype = trim(strtolower($mimetype));
		$mime_types = self::get_mime_types();
		return array_search($mimetype, $mime_types);
	}


	// Determine the Mime Type of a file (this is a soft check that is initially based on filename
	// and is not as strict as PHP's mime_content_type)
    public static function mime_content_type($filename)
	{
		$mime_types = self::get_mime_types();
        $ext = strtolower(array_pop(explode('.',$filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
			$semicolonPos = strpos($mimetype, ";");
			if ($semicolonPos !== false) {
				$mimetype = trim(substr($mimetype, 0, $semicolonPos));
			}
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }


	// When a script shuts down, delete a file or set it to be deleted by a cron
    public static function delete_file_on_shutdown($handler, $filename, $deleteNow=false)
	{
		// Delete the file now (and if fails, then set cron to delete it)
		if ($deleteNow) {
			// If cannot delete file (if may still be open somehow), then put in db table to delete by cron later
			fclose($handler);
			unlink($filename);
		}
		// Set file to be deleted when this script ends
		else {
			register_shutdown_function('Files::delete_file_on_shutdown', $handler, $filename, true);
		}
	}


	/**
	 * DELETE TEMP FILES AND EXPIRED SEND-IT FILES ONLY AT CERTAIN TIMES
	 * FOR EACH GIVEN WEB REQUEST IN INIT_GLOBAL.PHP AND INIT_PROJECT.PHP
	 */
	public static function manage_temp_files()
	{
		// Clean up any temporary files sitting on the web server (for various reasons)
		// Only force this once every 10000 requests to allow each web server to flush temp
		// if using load balancing, which won't be as easily cleared by the cron.
		self::remove_temp_deleted_files(defined("LOG_VIEW_ID") && LOG_VIEW_ID % 10000 == 0);
	}


	/**
	 * DELETE TEMP FILES AND EXPIRED SEND-IT FILES (RUN ONCE EVERY 20 MINUTES)
	 */
	public static function remove_temp_deleted_files($forceAction=false)
	{
		global $temp_files_last_delete, $edoc_storage_option;

		// Make sure variable is set
		if ($temp_files_last_delete == "" || !isset($temp_files_last_delete)) return;

		// Set X number of minutes to delete temp files
		$checkEveryXMin = 30;
		// Only delete temp files that are X minutes old or more
		$checkAgeXMin = 60;

		// If temp files have not been checked/deleted in the past X minutes, then run procedure to delete them.
		if ($forceAction || strtotime(NOW)-strtotime($temp_files_last_delete) > $checkEveryXMin*60)
		{
			// Initialize counter for number of docs deleted
			$docsDeleted = 0;

			## DELETE ALL FILES IN TEMP DIRECTORY IF OLDER THAN X MINUTES OLD
			// Make sure temp dir is writable and exists
			if (($edoc_storage_option != '3' && is_dir(APP_PATH_TEMP) && is_writeable(APP_PATH_TEMP))
				// If using Google Cloud Storage, ensure that the temp and edocs buckets aren't the same
				// (so we don't accidentally delete permanent files).
				|| !($edoc_storage_option == '3' && APP_PATH_TEMP == EDOC_PATH))
			{
				// Put temp file names into array
				$dh = opendir(APP_PATH_TEMP);
				$files = array();
				while (false != ($filename = readdir($dh))) {
					$files[] = $filename;
				}
				// Timestamp of X min ago
				$x_min_ago = date("YmdHis", mktime(date("H"),date("i")-$checkAgeXMin,date("s"),date("m"),date("d"),date("Y")));
				// Loop through all filed in temp dir
				foreach ($files as $key => $value) {
					// Delete ANY files that begin with a 14-digit timestamp
					$file_time = substr($value, 0, 14);
					// If file is more than one hour old, delete it
					if (is_numeric($file_time) && $file_time < $x_min_ago) {
						// Delete the file
						unlink(APP_PATH_TEMP . $value);
					}
				}
			}

			## DELETE ANY SEND-IT OR EDOC FILES THAT ARE FLAGGED FOR DELETION
			$docid_deleted = array();
			// Loop through list of expired Send-It files (only location=1, which excludes edocs and file repository files)
			// and Edoc files that were deleted by user over 30 days ago.
			$sql = "(select 'sendit' as type, document_id, doc_name from redcap_sendit_docs where location = 1 and expire_date < '".NOW."'
					and date_deleted is null)
					UNION
					(select 'edocs' as type, doc_id as document_id, stored_name as doc_name from redcap_edocs_metadata where
					delete_date is not null and date_deleted_server is null and delete_date < DATE_ADD('".NOW."', INTERVAL -1 MONTH))";
			$q = db_query($sql);

			// Delete from local web server folder
			if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
			{
				while ($row = db_fetch_assoc($q))
				{
					// Delete file, and if successfully deleted, then add to list of files deleted
					unlink(EDOC_PATH . $row['doc_name']);
					$docid_deleted[$row['type']][] = $row['document_id'];
				}
			}
			// Delete from S3
			elseif ($edoc_storage_option == '2')
			{
				global $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;
				$s3 = Files::s3client();
				while ($row = db_fetch_assoc($q))
				{
					// Delete file, and if successfully deleted, then add to list of files deleted
                    try {
                        $s3->deleteObject(array('Bucket' => $GLOBALS['amazon_s3_bucket'], 'Key' => $row['doc_name']));
                    } catch (Exception $e) { }
                    $docid_deleted[$row['type']][] = $row['document_id'];
				}
			}
			// Delete from Azure
			elseif ($edoc_storage_option == '4')
			{
				$blobClient = Files::azureBlobClient();
				while ($row = db_fetch_assoc($q))
				{
				    try {
                        // Delete file, and if successfully deleted, then add to list of files deleted
                        $blobClient->deleteBlob($GLOBALS['azure_container'], $row['doc_name']);
                    } catch (Exception $e) { }
                    $docid_deleted[$row['type']][] = $row['document_id'];
				}
			}
			// Delete from external server via webdav
			elseif ($edoc_storage_option == '1')
			{
				// Call webdav class and open connection to external server
				if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
				$wdc = new WebdavClient();
				$wdc->set_server($webdav_hostname);
				$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
				$wdc->set_user($webdav_username);
				$wdc->set_pass($webdav_password);
				$wdc->set_protocol(1);  // use HTTP/1.1
				$wdc->set_debug(false); // enable debugging?
				$wdc->open();
				if (substr($webdav_path,-1) != "/" && substr($webdav_path,-1) != "\\") {
					$webdav_path .= '/';
				}
				while ($row = db_fetch_assoc($q))
				{
					// Delete file
					$http_status = $wdc->delete($webdav_path . $row['doc_name']);
					$docid_deleted[$row['type']][] = $row['document_id'];
				}
			}

			// For all Send-It files deleted here, add date_deleted timestamp to table
			if (isset($docid_deleted['sendit']))
			{
				db_query("update redcap_sendit_docs set date_deleted = '".NOW."' where document_id in (" . implode(",", $docid_deleted['sendit']) . ")");
				$docsDeleted += db_affected_rows();
			}
			// For all Edoc files deleted here, add date_deleted_server timestamp to table
			if (isset($docid_deleted['edocs']))
			{
				db_query("update redcap_edocs_metadata set date_deleted_server = '".NOW."' where doc_id in (" . implode(",", $docid_deleted['edocs']) . ")");
				$docsDeleted += db_affected_rows();
			}

			## Now that all temp/send-it files have been deleted, reset time flag in config table
			db_query("update redcap_config set value = '".NOW."' where field_name = 'temp_files_last_delete'");

			// Return number of docs deleted
			return $docsDeleted;
		}
	}

	// Obtain image width and height of an uploaded image. Return null if not an image
	public static function getImgWidthHeight($filepath)
	{
		$valid_img_types = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP);
		$img_height = $img_width = null;
		$imgfile_size = getimagesize($filepath);
		if ($imgfile_size && in_array($imgfile_size[2], $valid_img_types)) {
			$img_height = $imgfile_size[1];
			$img_width = $imgfile_size[0];
		}
		return array($img_width, $img_height);
	}

	// Obtain image width and height of an edoc file by doc_id
	public static function getImgWidthHeightByDocId($doc_id)
	{
		global $edoc_storage_option;
		// Only do this for local storage (performance-wise)
		if ($edoc_storage_option != '0' && $edoc_storage_option != '3') return null;
		$sql = "select stored_name from redcap_edocs_metadata 
				where doc_id = '" . db_escape($doc_id). "' and delete_date is null";
		$q = db_query($sql);
		if (db_num_rows($q) == 0) return null;
		$filename = db_result($q, 0);
		return self::getImgWidthHeight(EDOC_PATH . $filename);
	}

	// Validate a base64 encoded image string. Return boolean regarding if a valid image. 
	public static function check_base64_image($base64) 
	{
		$img = base64_decode($base64);
		if (!$img) return false;
		$img_filename = APP_PATH_TEMP . date('YmdHis') . "_tmpfile" . substr(sha1(rand()), 0, 6);
		file_put_contents($img_filename, $img);
		$info = getimagesize($img_filename);
		unlink($img_filename);
		if ($info[0] > 0 && $info[1] > 0 && $info['mime']) {
			return true;
		}
		return false;
	}

    // If the feature "File Upload Version History" is enabled for the whole SYSTEM
    public static function fileUploadVersionHistoryEnabledSystem()
    {
        return ($GLOBALS['file_upload_versioning_global_enabled'] != '');
    }

    // If the feature "File Upload Version History" is enabled for a given PROJECT
    public static function fileUploadVersionHistoryEnabledProject($project_id)
    {
        $Proj = new Project($project_id);
        return (self::fileUploadVersionHistoryEnabledSystem() && $Proj->project['file_upload_versioning_enabled'] == '1');
    }

    // If the feature "Password verification for File Upload fields with duplicate storage on external server" is enabled for the whole SYSTEM
    public static function fileUploadPasswordVerifyExternalStorageEnabledSystem()
    {
        return ($GLOBALS['file_upload_vault_filesystem_type'] != '');
    }

    // If the feature "Password verification for File Upload fields with duplicate storage on external server" is enabled for a given PROJECT
    public static function fileUploadPasswordVerifyExternalStorageEnabledProject($project_id)
    {
        $Proj = new Project($project_id);
        return (self::fileUploadPasswordVerifyExternalStorageEnabledSystem() && $Proj->project['file_upload_vault_enabled'] == '1');
    }

	// If the feature "Password verification for File Upload fields with duplicate storage on external server" is enabled for a given PROJECT,
    // then store file on that server
	public static function writeUploadedFileToVaultExternalServer($filename, $file_contents)
	{
		try
		{
			// Add slash to end of root path
			$pathLastChar = substr($GLOBALS['file_upload_vault_filesystem_path'], -1);
			if ($pathLastChar != "/" && $pathLastChar != "\\") {
				$GLOBALS['file_upload_vault_filesystem_path'] .= "/";
			}
			// Not enabled for project
			if ($GLOBALS['file_upload_vault_filesystem_type'] == '') {
				return null;
			}
			// WEBDAV
			elseif ($GLOBALS['file_upload_vault_filesystem_type'] == 'WEBDAV')
			{
				$settings = array(
					'baseUri' => $GLOBALS['file_upload_vault_filesystem_host'],
					'userName' => $GLOBALS['file_upload_vault_filesystem_username'],
					'password' => $GLOBALS['file_upload_vault_filesystem_password']
				);
				$client = new Sabre\DAV\Client($settings);
				$adapter = new League\Flysystem\WebDAV\WebDAVAdapter($client, $GLOBALS['file_upload_vault_filesystem_path']);
			}
			// SFTP
			elseif ($GLOBALS['file_upload_vault_filesystem_type'] == 'SFTP')
			{
				$settings = array(
					'host' => $GLOBALS['file_upload_vault_filesystem_host'],
					'port' => 22,
					'username' => $GLOBALS['file_upload_vault_filesystem_username'],
					'password' => $GLOBALS['file_upload_vault_filesystem_password'],
					'root' => $GLOBALS['file_upload_vault_filesystem_path'],
					'timeout' => 10
				);
				if ($GLOBALS['file_upload_vault_filesystem_private_key_path'] != '') {
					$settings['privateKey'] = $GLOBALS['file_upload_vault_filesystem_private_key_path'];
				}
				$adapter = new League\Flysystem\Sftp\SftpAdapter($settings);
			}
			// Instantiate the filesystem
			$filesystem = new League\Flysystem\Filesystem($adapter);
			// Write the file
			$response = $filesystem->write($filename, $file_contents);
			// Return boolean regarding success
			return $response;
		}
		// ERROR
		catch (Exception $e)
		{
			return false;
		}
	}

	// Store an entire record as a PDF in the File Repository. Return boolean on whether successful.
	public static function archiveRecordAsPDF($project_id, $record, $arm)
	{
		$Proj = new Project($project_id);
		$recordFilename = str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z- ]/", "", $record)));
		$pdf_filename = APP_PATH_TEMP . "pid" . $Proj->project_id . "_id" . $recordFilename . "_" . date('Y-m-d_His') . ".pdf";
		// Obtain the compact PDF of the response
		$pdf_contents = REDCap::getPDF($record, null, null, false, null, true);
		// Temporarily store file in temp
		file_put_contents($pdf_filename, $pdf_contents);
		// Add PDF to edocs_metadata table
		$pdfFile = array('name'=>basename($pdf_filename), 'type'=>'application/pdf',
						 'size'=>filesize($pdf_filename), 'tmp_name'=>$pdf_filename);
		$pdf_edoc_id = Files::uploadFile($pdfFile);
		unlink($pdf_filename);
		if ($pdf_edoc_id == 0) return false;
		// Add to table
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "insert into redcap_locking_records_pdf_archive (doc_id, project_id, record, arm_id) values
				($pdf_edoc_id, $project_id, '".db_escape($record)."', '".db_escape($arm_id)."')";
		$q = db_query($sql);
		// Store file on external server
		$storedFileExternal = Files::writeRecordLockingPdfToExternalServer(basename($pdf_filename), $pdf_contents);
		// Return boolean on success
		return ($q && $storedFileExternal !== false);
	}

	// If project has External Storage enabled for the PDF Auto-Archiver, then store file on that server
	public static function writeFilePdfAutoArchiverToExternalServer($filename, $file_contents) 
	{
		try 
		{	
			// Add slash to end of root path
			$pathLastChar = substr($GLOBALS['pdf_econsent_filesystem_path'], -1);
			if ($pathLastChar != "/" && $pathLastChar != "\\") {
				$GLOBALS['pdf_econsent_filesystem_path'] .= "/";
			}		
			// Not enabled for project
			if ($GLOBALS['pdf_econsent_filesystem_type'] == '') {
				return false;
			}
			// WEBDAV
			elseif ($GLOBALS['pdf_econsent_filesystem_type'] == 'WEBDAV') 
			{
				$settings = array(
					'baseUri' => $GLOBALS['pdf_econsent_filesystem_host'],
					'userName' => $GLOBALS['pdf_econsent_filesystem_username'],
					'password' => $GLOBALS['pdf_econsent_filesystem_password']
				);
				$client = new Sabre\DAV\Client($settings);
				$adapter = new League\Flysystem\WebDAV\WebDAVAdapter($client, $GLOBALS['pdf_econsent_filesystem_path']);
			} 
			// SFTP
			elseif ($GLOBALS['pdf_econsent_filesystem_type'] == 'SFTP') 
			{
				$settings = array(
					'host' => $GLOBALS['pdf_econsent_filesystem_host'],
					'port' => 22,
					'username' => $GLOBALS['pdf_econsent_filesystem_username'],
					'password' => $GLOBALS['pdf_econsent_filesystem_password'],
					'root' => $GLOBALS['pdf_econsent_filesystem_path'],
					'timeout' => 10
				);
				if ($GLOBALS['pdf_econsent_filesystem_private_key_path'] != '') {
					$settings['privateKey'] = $GLOBALS['pdf_econsent_filesystem_private_key_path'];
				}
				$adapter = new League\Flysystem\Sftp\SftpAdapter($settings);
			}
			// Instantiate the filesystem
			$filesystem = new League\Flysystem\Filesystem($adapter);
			// Write the file
			$response = $filesystem->write($filename, $file_contents);
			// Return boolean regarding success
			return $response;
		} 
		// ERROR
		catch (Exception $e) 
		{
			return false;
		}
	}

	// If project has External Storage enabled for the Record-locking PDF confirmation, then store file on that server
	public static function writeRecordLockingPdfToExternalServer($filename, $file_contents)
	{
		try
		{
			// Add slash to end of root path
			$pathLastChar = substr($GLOBALS['record_locking_pdf_vault_filesystem_path'], -1);
			if ($pathLastChar != "/" && $pathLastChar != "\\") {
				$GLOBALS['record_locking_pdf_vault_filesystem_path'] .= "/";
			}
			// Not enabled for project
			if ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == '') {
				return null;
			}
			// WEBDAV
			elseif ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == 'WEBDAV')
			{
				$settings = array(
					'baseUri' => $GLOBALS['record_locking_pdf_vault_filesystem_host'],
					'userName' => $GLOBALS['record_locking_pdf_vault_filesystem_username'],
					'password' => $GLOBALS['record_locking_pdf_vault_filesystem_password']
				);
				$client = new Sabre\DAV\Client($settings);
				$adapter = new League\Flysystem\WebDAV\WebDAVAdapter($client, $GLOBALS['record_locking_pdf_vault_filesystem_path']);
			}
			// SFTP
			elseif ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == 'SFTP')
			{
				$settings = array(
					'host' => $GLOBALS['record_locking_pdf_vault_filesystem_host'],
					'port' => 22,
					'username' => $GLOBALS['record_locking_pdf_vault_filesystem_username'],
					'password' => $GLOBALS['record_locking_pdf_vault_filesystem_password'],
					'root' => $GLOBALS['record_locking_pdf_vault_filesystem_path'],
					'timeout' => 10
				);
				if ($GLOBALS['record_locking_pdf_vault_filesystem_private_key_path'] != '') {
					$settings['privateKey'] = $GLOBALS['record_locking_pdf_vault_filesystem_private_key_path'];
				}
				$adapter = new League\Flysystem\Sftp\SftpAdapter($settings);
			}
			// Instantiate the filesystem
			$filesystem = new League\Flysystem\Filesystem($adapter);
			// Write the file
			$response = $filesystem->write($filename, $file_contents);
			// Return boolean regarding success
			return $response;
		}
			// ERROR
		catch (Exception $e)
		{
			return false;
		}
	}

	// When using AWS S3 for file storage, obtain the region name (eu-west-3) from the endpoint (s3.eu-west-3.amazonaws.com, s3-eu-west-3.amazonaws.com)
	public static function getAwsS3RegionFromEndpoint($endpoint) 
	{
		$region = trim($endpoint);
		// If region is blank, then default to us-east-1
		if ($region == '') return 'us-east-1';
		// First, clean the endpoint of an prefixes
		$region = str_replace(array("https://", "http://", "www."), array("", "", ""), $endpoint);
		// Remove the amazonaws.com ending
		$region = str_replace(".amazonaws.com", "", $region);
		// Remove s3. and s3- from the beginning
		$region = str_replace(array("s3.", "s3-"), array("", ""), $region);
		// Return the region name
		return $region;
	}	

	// When using AWS S3 for file storage, obtain the region name (eu-west-3) from the redcap_config table
	public static function getS3Region()
	{
		$region = trim($GLOBALS['amazon_s3_endpoint']);
		// If region is blank, then default to us-east-1
		return ($region == '' ? 'us-east-1' : $region);
	}

	// When using AWS S3 for file storage, instantiate and return the S3 client
	public static function s3client()
	{
		try {
			$credentials = new Aws\Credentials\Credentials($GLOBALS['amazon_s3_key'], $GLOBALS['amazon_s3_secret']);
			$s3 = new Aws\S3\S3Client(array('version'=>'latest', 'region'=>self::getS3Region(), 'credentials'=>$credentials));
			return $s3;
		} catch (Aws\S3\Exception\S3Exception $e) {
			// Failed
			return false;
		}
	}

	// When using Azure Blob Storage for file storage, instantiate and return the client
	public static function azureBlobClient()
	{
		try {
			$connectionString = "DefaultEndpointsProtocol=https;AccountName={$GLOBALS['azure_app_name']};AccountKey={$GLOBALS['azure_app_secret']}";
			$blobClient = MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService($connectionString);
			return $blobClient;
		} catch (Exception $e) {
			// Failed
			return false;
		}
	}
    
	// Truncate a file name to X characters while still maintaining the file extension
	public static function truncateFileName($filename, $charLimit, $truncateMarkFromEnd=9)
	{
		$origLength = strlen($filename);
		if ($origLength > $charLimit) {
			$filename = trim(substr($filename, 0, $charLimit - $truncateMarkFromEnd))."...".trim(substr($filename, $origLength - $truncateMarkFromEnd));
		}
		return $filename;
	}

    // Delete file by doc_id
    public static function deleteFileByDocId($doc_id)
    {
        $doc_id = intval($doc_id);
        if (!$doc_id) return false;
        $sql = "UPDATE redcap_edocs_metadata
				SET delete_date = '".NOW."'
				WHERE doc_id = $doc_id and delete_date is null";
        return db_query($sql);
    }
}