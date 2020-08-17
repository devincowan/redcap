<?php



class SendIt
{
	// Render the Send-It upload page
	public static function renderUploadPage()
	{
		// Set vars as global in order to make them available here and when rendering the view (temporary)
		global $fileLocation, $originalFilename, $failedEmails, $successfulEmails, $expireHour, $expireMin, $expireMonth, 
			   $expireDay, $expireYear, $fileSize;
			   
		extract($GLOBALS);
		
		// User should not have "loc" in the URL unless submitted file, so refresh page if so.
		if ($_SERVER['REQUEST_METHOD'] != 'POST' && isset($_GET['loc']) && $_GET['loc'] == "1") {
			redirect(PAGE_FULL);
		}

		// Set initial variables
		$yourName = $user_firstname. ' '. $user_lastname;
		$errors = array();
		$fileLocation = (isset($_GET['loc']) && is_numeric($_GET['loc']) ? $_GET['loc'] : 1);
		$_GET['id'] = $fileId = ((isset($_GET['id']) && is_numeric($_GET['id'])) ? $_GET['id'] : 0);

		// Check if user truly has rights to file AND get filename of file if from file repository or data entry page (i.e. it has already been uploaded)
		if ($fileLocation != 1) {

			// Ensure user has rights to a project that this file is attached to
			if ($fileLocation == 2) { //file repository
				if (!SUPER_USER) {
					$sql = "select 1 from redcap_docs d, redcap_user_rights u where d.docs_id = $fileId and d.project_id = u.project_id
							and u.username = '".db_escape($userid)."' limit 1";
				} else {
					$sql = "select 1 from redcap_docs d where d.docs_id = $fileId";
				}
			} elseif ($fileLocation == 3) { //data entry form
				if (!SUPER_USER) {
					$sql = "select 1 from redcap_edocs_metadata d, redcap_user_rights u where d.doc_id = $fileId and d.project_id = u.project_id
							and u.username = '".db_escape($userid)."' limit 1";
				} else {
					$sql = "select 1 from redcap_edocs_metadata d where d.doc_id = $fileId";
				}
			}
			$q = db_query($sql);
			if (db_num_rows($q) < 1) {
				// User does not have access to this file!
				exit($lang['sendit_01']);
			}

			// Get filename
			if ($fileLocation == 2) //file repository
				$query = "SELECT project_id, docs_name as docName, docs_name as storedName, docs_size as docSize, docs_type as docType FROM redcap_docs
					WHERE docs_id = $fileId";
			else if ($fileLocation == 3) //data entry form
				$query = "SELECT project_id, doc_name as docName, stored_name as storedName, doc_size as docSize, mime_type as docType FROM redcap_edocs_metadata
					WHERE doc_id = $fileId";
			$result = db_query($query);
			$row = db_fetch_assoc($result);
			// Get file metadata
			$originalFilename = $row['docName'];
			$fileSize = $row['docSize'];
			$newFilename = $row['storedName'];
			$fileType = $row['docType'];
			// Get project id for logging purposes
			define("PROJECT_ID", $row['project_id']);

		}



		/**
		 * PROCESS THE POSTED FORM ELEMENTS
		 */
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			// Form elements
			$formFields = array('recipients', 'subject', 'message', 'confirmation', 'expireDays');
			foreach ($formFields as $field) {
				$$field = (isset($_POST[$field])) ? $_POST[$field] : '';
			}
		}



		/**
		 * CHECK FOR ANY FILE UPLOAD ERRORS
		 */
		$upload_errors = false;
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && $fileLocation == 1)
		{
			// If file is larger than PHP upload limits
			if (empty($_FILES) || $_FILES['file']['error'] != UPLOAD_ERR_OK)
			{
				// Set error msg
				$errors[] = $lang['sendit_02'] . " " . $lang['docs_63'];
				// Set flag
				$upload_errors = true;
			}
			// If file is larger than REDCap upload limits
			elseif (($_FILES['file']['size']/1024/1024) > maxUploadSizeSendit())
			{
				// Delete uploaded file from server
				unlink($_FILES['file']['tmp_name']);
				// Set error msg
				$errors[] = $lang['sendit_03'] . ' (' . round_up($_FILES['file']['size']/1024/1024) . ' MB) ' .
							 $lang['sendit_04'] . ' ' . maxUploadSizeSendit() . ' MB ' . $lang['sendit_05'];
				// Set flag
				$upload_errors = true;
			}

			// Unset some posted variables to reset the page without having to reload it
			if ($upload_errors)
			{
				$_POST  = array();
				$_FILES = array();
				$fileId = '';
				unset($_GET['id']);
				$subject = trim(str_replace(array("\"","[REDCap Send-It]"), array("&quot;",""), $subject));
			}
		}



		/**
		 * PROCESS AND SAVE FILE IF WAS UPLOADED WITHOUT ERRORS
		 */
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$upload_errors)
		{
			// Save file if one was provided
			if (isset($_FILES['file']['tmp_name']) && strlen($_FILES['file']['tmp_name']) > 0 )
			{
				$tempFilename = $_FILES['file']['tmp_name'];
				$originalFilename = str_replace(' ', '', $_FILES['file']['name']);
				$newFilename = date('YmdHis') . "_sendit_" . SendIt::createUniqueFilename(SendIt::getFileExtension($originalFilename));
				$fileType = $_FILES['file']['type'];
				$fileSize = $_FILES['file']['size'];

				// Move uploaded file to edocs folder
				if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
				{
					if (!move_uploaded_file($tempFilename, EDOC_PATH . $newFilename)) {
						$errors[] = $lang['sendit_02'];
					}
				}
				// S3
				elseif ($edoc_storage_option == '2')
				{
					try {
						$s3 = Files::s3client();
						$s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$newFilename, 'Body'=>file_get_contents($tempFilename), 'ACL'=>'private'));
					} catch (Aws\S3\Exception\S3Exception $e) {
						$errors[] = $lang['sendit_02'];
					}
				}
				// Azure
				elseif ($edoc_storage_option == '4')
				{
					$blobClient = Files::azureBlobClient();
					$blobClient->createBlockBlob($GLOBALS['azure_container'], $newFilename, file_get_contents($tempFilename));
				}
				// Move to external server via webdav
				else
				{
					if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
					$wdc = new WebdavClient();
					$wdc->set_server($webdav_hostname);
					$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
					$wdc->set_user($webdav_username);
					$wdc->set_pass($webdav_password);
					$wdc->set_protocol(1); // use HTTP/1.1
					$wdc->set_debug(false); // enable debugging?
					if (!$wdc->open()) {
						$errors[] = $lang['sendit_02'];
					}
					if (substr($webdav_path,-1) != "/" && substr($webdav_path,-1) != "\\") {
						$webdav_path .= '/';
					}
					if ($fileSize > 0) {
						$fp      = fopen($tempFilename, 'rb');
						$content = fread($fp, filesize($tempFilename));
						fclose($fp);
						$http_status = $wdc->put($webdav_path . $newFilename, $content);

					}
				}
			}

			// Validate all the email addresses
			$emailAddresses = str_replace(array("\r\n","\n","\r",";"," "), array(',',',',',',',',''), $recipients);
			$emailAddresses = explode(',', $emailAddresses);
			foreach($emailAddresses as $value)
			{
				$value = trim($value);
				if ($value != '' && !isEmail($value))
				{
					$errors[] = $lang['sendit_06'];
					break;
				}
			}

			// If no errors exist during upload, then process (add to tables, send emails, etc.)
			if (count($errors) == 0)
			{
				$send = (isset($_POST['confirmation'])) ? 1 : 0;
				$expireDate = date('Y-m-d H:i:s', strtotime("+$expireDays days"));
				$expireYear = substr($expireDate, 0, 4);
				$expireMonth = substr($expireDate, 5, 2);
				$expireDay = substr($expireDate, 8, 2);
				$expireHour = substr($expireDate, 11, 2);
				$expireMin = substr($expireDate, 14, 2);

				// Add entry to sendit_docs table
				$query = "INSERT INTO redcap_sendit_docs (doc_name, doc_orig_name, doc_type, doc_size, send_confirmation, expire_date, username,
							location, docs_id, date_added)
						  VALUES ('$newFilename', '".db_escape($originalFilename)."', '$fileType', '$fileSize', $send, '$expireDate', '".db_escape($userid)."',
							$fileLocation, $fileId, '".NOW."')";
				db_query($query);
				$newId = db_insert_id();

				// Logging
				if ($fileLocation == 1) {
					$logDescrip = "Upload and send file (Send-It)";
				} elseif ($fileLocation == 2) {
					$logDescrip = "Send file from file respository (Send-It)";
				} elseif ($fileLocation == 3) {
					$logDescrip = "Send file from data entry form (Send-It)";
				}
				Logging::logEvent($query,"redcap_sendit_docs","MANAGE",$newId,"document_id = $newId",$logDescrip);

				// Set email subject
				$subject_prefix = "[REDCap Send-It] ";
				if ($subject == '') {
					$subject = $subject_prefix . $lang['sendit_57'] . ' ' . $yourName;
				} else {
					$subject = $subject_prefix . $subject;
				}
				$subject = html_entity_decode($subject, ENT_QUOTES);

				// Set email From address
				$fromEmailTemp = 'user_email' . ((isset($_POST['emailFrom']) && $_POST['emailFrom'] > 1) ? $_POST['emailFrom'] : '');
				$fromEmail = $$fromEmailTemp;
				if (!isEmail($fromEmail)) $fromEmail = $user_email;

				// Begin set up of email to send to recipients
				$email = new Message();
				$email->setFrom($fromEmail);
				if (isset($_POST['emailFrom']) && $_POST['emailFrom'] > 1) {
					$email->setFromName(""); // Add user's secondary and tertiary display name
                } else {
					$email->setFromName($GLOBALS['user_firstname'] . " " . $GLOBALS['user_lastname']);
				}
				$email->setSubject($subject);

				// Loop through each recipient and send email
				$successfulEmails = array();
				$failedEmails = array();
				foreach ($emailAddresses as $value)
				{
					// If a non-blank email address AND not a duplicated email address
					if (trim($value) != '' && !in_array($value, $successfulEmails))
					{
						// create key for unique url
						$key = strtoupper(substr(uniqid(sha1(mt_rand())), 0, 25));

						// create password
						$pwd = generateRandomHash(8, false, true);

						$query = "INSERT INTO redcap_sendit_recipients (email_address, sent_confirmation, download_date, download_count, document_id, guid, pwd)
								  VALUES ('$value', 0, NULL, 0, $newId, '$key', '" . md5($pwd) . "')";
						$q = db_query($query);

						// Download URL
						$url = APP_PATH_WEBROOT_FULL. 'redcap_v'. $redcap_version. '/SendIt/download.php?'. $key;

						// Message from sender
						$note = "";
						if ($_POST['message'] != "") {
							$note = "$yourName {$lang['sendit_56']}<br>" . nl2br(strip_tags(html_entity_decode($_POST['message'], ENT_QUOTES))) . '<br>';
						}
						// Get YMD timestamp of the file's expiration time
						$expireTimestamp = date('Y-m-d H:i:s', mktime( $expireHour, $expireMin, 0, $expireMonth, $expireDay, $expireYear));

						// Email body
						$body =    "<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
									$yourName {$lang['sendit_51']} \"$originalFilename\" {$lang['sendit_52']} " .
									date('l', mktime( $expireHour, $expireMin, 0, $expireMonth, $expireDay, $expireYear)) . ",
									" . DateTimeRC::format_ts_from_ymd($expireTimestamp) . "{$lang['period']}
									{$lang['sendit_53']}<br><br>
									{$lang['sendit_54']}<br>
									<a href=\"$url\">$url</a><br><br>
									$note
									<br>-----------------------------------------------<br>
									{$lang['sendit_55']} " . CONSORTIUM_WEBSITE_DOMAIN . ".
									</body></html>";

						// Construct email and send
						$email->setTo($value);
						$email->setBody($body);
						if ($email->send()) {
							// Add to list of emails sent
							$successfulEmails[] = "<span class='notranslate'>$value</span>";
							// Now send follow-up email containing password
							$bodypass = "<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
										{$lang['sendit_50']}<br><br>
										$pwd<br><br>
										</body></html>";
							$email->setSubject("Re: $subject");
							$email->setBody($bodypass);
							sleep(2); // Hold for a second so that second email somehow doesn't reach the user first
							$email->send();
						} else {
							// Add emails to array if email didn't send
							$failedEmails[] = "<span class='notranslate'>$value</span>";
							// Display the email to the user on the webpage
							?>
							<div style="max-width:700px;text-align:left;font-size:11px;background-color:#f5f5f5;border:1px solid #ddd;padding:5px;margin:20px;">
								<b style="color:#800000;">Email did NOT send!</b><br>
								<b>Sent to:</b> <?php echo $value ?>
								<hr>
								<?php echo $body ?>
								<hr>
								<?php echo "{$lang['sendit_50']}<br><br>$pwd" ?>
							</div>
							<?php
						}

					}
				}
			}
		}

		// If Send-It has not been enabled, then give error message to user
		if (($fileLocation == '1' && $sendit_enabled != '1' && $sendit_enabled != '2')
			|| ($fileLocation == '2' && $sendit_enabled != '1' && $sendit_enabled != '3'))
		{
			System::redirectHome();
		}
		
		// Render the view
		$SendItController = new SendItController();
		$SendItController->render('SendIt/Upload.php', $GLOBALS);
	}
	
	// Render the Send-It download page
	public static function renderDownloadPage()
	{
		global $error, $doc_size;
		extract($GLOBALS);
		
		// Initial variables
		$key = trim(substr(trim($_SERVER['QUERY_STRING']), 0, 25));
		$error = '';

		// Check file's expiration and if key is valid
		if (strlen($key) > 0)
		{
			$query = "select r.*, d1.*,
					  (select e.gzipped from redcap_docs d, redcap_docs_to_edocs de, redcap_edocs_metadata e
					  where d.docs_id = de.docs_id and de.doc_id = e.doc_id and d.docs_id = d1.docs_id) as gzipped
					  from redcap_sendit_recipients r, redcap_sendit_docs d1
					  where r.document_id = d1.document_id and r.guid = '".db_escape($key)."'";
			$result = db_query($query);
			if (db_num_rows($result))
			{
				// Set file attributes in array
				$row = db_fetch_assoc($result);
				// Set expiration date
				$expireDate = $row['expire_date'];
				// Determine if file is gzipped
				$gzipped = $row['gzipped'];
				// Set error msg if file has expired
				if ($expireDate < NOW) $error = $lang['sendit_36'];
			}
			else
			{
				$error = $lang['sendit_37']; //invalid key
			}
		}
		else
		{
			$error = $lang['sendit_37']; //no key was provided
		}


		// Obtain the size of the file in MB
		$doc_size = round_up($row['doc_size']/1024/1024);

		// Process the password submitted and begin file download
		if ( isset($_POST['submit']) )
		{
			if ( $row['pwd'] == md5(trim($_POST['pwd'])) )
			{
				// If user requested confirmation, then send them email (but only the initial time it was downloaded, to avoid multiple emails)
				if ($row['send_confirmation'] == 1 && $row['sent_confirmation'] == 0)
				{
					// Get the uploader's email address
					$sql = "SELECT user_email FROM redcap_user_information WHERE username = '{$row['username']}' limit 1";
					$uploader_email = db_result(db_query($sql), 0);

					// Send confirmation email to the uploader
					$body =    "<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
								{$lang['sendit_46']} \"{$row['doc_orig_name']}\" ($doc_size MB){$lang['sendit_47']} {$row['email_address']} {$lang['global_51']}
								" . date('l') . ", " . DateTimeRC::format_ts_from_ymd(NOW) . "{$lang['period']}<br><br><br>
								{$lang['sendit_48']} <a href=\"" . APP_PATH_WEBROOT_FULL . "\">REDCap Send-It</a>!
								</body></html>";
					$email = new Message();
					$email->setFrom($project_contact_email);
					$email->setFromName($GLOBALS['project_contact_name']);
					$email->setTo($uploader_email);
					$email->setBody($body);
					$email->setSubject('[REDCap Send-It] '.$lang['sendit_49']);
					$email->send();
				}

				// Log this download event in the table
				$recipientId = $row['recipient_id'];
				$querylog = "UPDATE redcap_sendit_recipients SET download_date = '".NOW."', download_count = (download_count+1),
							 sent_confirmation = 1 WHERE recipient_id = $recipientId";
				db_query($querylog);

				// Set flag to determine if we're pulling the file from the file system or redcap_docs table (legacy storage for File Repository)
				$pullFromFileSystem = ($row['location'] == '3' || $row['location'] == '1');

				// If file is in File Repository, retrieve it from redcap_docs table (UNLESS we determine that it's in the file system)
				if ($row['location'] == '2')
				{
					// Determine if in redcap_docs table or file system and then download it
					$query = "SELECT d.*, e.doc_id as edoc_id FROM redcap_docs d LEFT JOIN redcap_docs_to_edocs e
							  ON e.docs_id = d.docs_id LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
							  WHERE d.docs_id = " . $row['docs_id'];
					$result = db_query($query);
					$row = db_fetch_assoc($result);
					// Check location
					if ($row['edoc_id'] === NULL) {
						// Download file from redcap_docs table (legacy BLOB storage)
						header('Pragma: anytextexeptno-cache', true);
						header('Content-Type: '. $row['docs_type']);
						header('Content-Disposition: attachment; filename='. str_replace(' ', '', $row['docs_name']));
						ob_clean();
						flush();
						print $row['docs_file'];
					} else {
						// Set flag to pull the file from the file system instead
						$pullFromFileSystem = true;
						// Reset values that were overwritten
						$row['docs_id'] = $row['edoc_id'];
						$row['location'] = '2';
					}
				}
				// If file stored on form or uploaded from Home page Send-It location, retrieve it from edocs location
				if ($pullFromFileSystem)
				{
					// Retrieve values for loc=3 (since loc=1 values are already stored in $row) or for loc=2 if stored in file system
					if ($row['location'] == '3' || $row['location'] == '2')
					{
						$query = "SELECT project_id, mime_type as doc_type, doc_name as doc_orig_name, stored_name as doc_name
								  FROM redcap_edocs_metadata WHERE doc_id = " . $row['docs_id'];
						$result = db_query($query);
						$row = db_fetch_assoc($result);
					}

					// Retrieve from EDOC_PATH location (LOCAL STORAGE)
					if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
					{
						// Download file
						header('Pragma: anytextexeptno-cache', true);
						header('Content-Type: '. $row['doc_type']);
						header('Content-Disposition: attachment; filename=' . str_replace(array(' ',','), array('',''), $row['doc_orig_name']));
						// GZIP decode the file (if is encoded)
						if ($gzipped) {
							list ($contents, $nothing) = gzip_decode_file(file_get_contents(EDOC_PATH . $row['doc_name']));
							ob_clean();
							flush();
							print $contents;
						} else {
							ob_end_flush();
							readfile_chunked(EDOC_PATH . $row['doc_name']);
						}
					}
					// S3
					elseif ($edoc_storage_option == '2')
					{
						// S3
						try {
							$s3 = Files::s3client();
							$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$row['doc_name'], 'SaveAs'=>APP_PATH_TEMP . $row['doc_name']));
							header('Pragma: anytextexeptno-cache', true);
							header('Content-Type: '. $row['doc_type']);
							header('Content-Disposition: attachment; filename=' . str_replace(array(' ',','), array('',''), $row['doc_orig_name']));
							// GZIP decode the file (if is encoded)
							if ($gzipped) {
								list ($contents, $nothing) = gzip_decode_file(file_get_contents(APP_PATH_TEMP . $row['doc_name']));
								ob_clean();
								flush();
								print $contents;
							} else {
								ob_end_flush();
								readfile_chunked(APP_PATH_TEMP . $row['doc_name']);
							}
							// Now remove file from temp directory
							unlink(APP_PATH_TEMP . $row['doc_name']);
						} catch (Aws\S3\Exception\S3Exception $e) {
						}
					}
					// Azure
					elseif ($edoc_storage_option == '4')
					{
						
						$blobClient = Files::azureBlobClient();
						$blob = $blobClient->getBlob($GLOBALS['azure_container'], $row['doc_name']);
						file_put_contents(APP_PATH_TEMP . $row['doc_name'], $blob->getContentStream());
						header('Pragma: anytextexeptno-cache', true);
						header('Content-Type: '. $row['doc_type']);
						header('Content-Disposition: attachment; filename=' . str_replace(array(' ',','), array('',''), $row['doc_orig_name']));
						// GZIP decode the file (if is encoded)
						if ($gzipped) {
							list ($contents, $nothing) = gzip_decode_file(file_get_contents(APP_PATH_TEMP . $row['doc_name']));
							ob_clean();
							flush();
							print $contents;
						} else {
							ob_end_flush();
							readfile_chunked(APP_PATH_TEMP . $row['doc_name']);
						}
						// Now remove file from temp directory
						unlink(APP_PATH_TEMP . $row['doc_name']);
					}
					// Retrieve from external server via webdav
					elseif ($edoc_storage_option == '1')
					{
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
							exit("{$lang['global_01']}{$lang['colon']} {$lang['sendit_39']}");
						}
						$http_status = $wdc->get($webdav_path . $row['doc_name'], $contents); //$contents is produced by webdav class
						$wdc->close();
						// Download file
						header('Pragma: anytextexeptno-cache', true);
						header('Content-Type: '. $row['doc_type']);
						header('Content-Disposition: attachment; filename=' . str_replace(array(' ',','), array('',''), $row['doc_orig_name']));
						// GZIP decode the file (if is encoded)
						if ($gzipped) {
							list ($contents, $nothing) = gzip_decode_file($contents);
						}
						ob_clean();
						flush();
						print $contents;
					}

				}


				## Logging
				if ($row['project_id'] != "" && $row['project_id'] != "0") {
					// Get project id if file is existing project file
					define("PROJECT_ID", $row['project_id']);
				}
				Logging::logEvent($querylog,"redcap_sendit_recipients","MANAGE",$recipientId,"recipient_id = $recipientId","Download file (Send-It)");

				// Stop here now that file has been downloaded
				exit;

			}
			else
			{
				$error = $lang['sendit_40'];
			}
		}
		
		// Render the view
		$SendItController = new SendItController();
		$SendItController->render('SendIt/Download.php', $GLOBALS);
	}
	
	public static function createUniqueFilename($extension)
	{
		// explode the IP of the remote client into four parts
		$ipbits = explode(".", $_SERVER["REMOTE_ADDR"]);

		// Get both seconds and microseconds parts of the time
		list($usec, $sec) = explode(" ",microtime());

		// Fudge the time we just got to create two 16 bit words
		$usec = (integer) ($usec * 65536);
		$sec = ((integer) $sec) & 0xFFFF;

		// convert the remote client's IP into a 32 bit hex number, then tag on the time.
		// Result of this operation looks like this: xxxxxxxx-xxxx-xxxx
		$name = sprintf("%08x-%04x-%04x",($ipbits[0] << 24)
				| ($ipbits[1] << 16)
				| ($ipbits[2] << 8)
				| $ipbits[3], $sec, $usec);

		// add the extension and return the filename
		return $name.$extension;
	}
	
	public static function getFileExtension($filename)
	{
		$pos = strrpos($filename, '.');

		if ($pos === false)
			return false;
		else
			return substr($filename, $pos);
	}

}