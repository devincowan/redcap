<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Make sure server has ZipArchive ability (i.e. is on PHP 5.2.0+)
if (!Files::hasZipArchive()) {
	exit('ERROR: ZipArchive is not installed. It must be installed to use this feature.');
}

// Make sure project has File Upload fields
if (!Files::hasFileUploadFields()) exit($lang['data_export_tool_161']);

// Set the target zip file to be saved in the temp dir (set timestamp in filename as 1 hour from now so that it gets deleted automatically in 1 hour)
$inOneHour = date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y")));
$target_zip = APP_PATH_TEMP . "{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
$zip_parent_folder = "Files_".substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20)."_".date("Y-m-d_Hi");
$download_filename = "$zip_parent_folder.zip";
// Exit/return msg and onclick if nothing to download
$exitOnclick = "try{ window.open('', '_self', ''); }catch(e){} try{ window.close(); }catch(e){} try{ window.top.close(); }catch(e){} try{ open(window.location, '_self').close(); }catch(e){} try{ self.close(); }catch(e){}";
$exitMsg = isset($_GET['id']) ? $lang['data_export_tool_221'] : $lang['data_export_tool_159'];
$exitBtn = "<button onclick=\"$exitOnclick\">{$lang['data_export_tool_160']}</button><br><br>$exitMsg";

// Create array of unique event names (for reference later)
$unique_events = $Proj->getUniqueEventNames();

# Get current file fields (we don't want to include file fields that were deleted from the dictionary)
$file_fields = array();
foreach ($Proj->metadata as $field=>$attr) {
	// Add to array if a "file" field
	if ($attr['element_type'] != 'file') continue;
	// If user has de-id rights AND this field is an Identifier field, then do NOT include it in the ZIP
	if ($user_rights['data_export_tool'] == '2' && $attr['field_phi'] == '1') continue;
	// Add to array
	$file_fields[] = $field;
}
if (empty($file_fields)) exit($exitBtn);

# Get doc_id's data for file fields (if user is in a DAG, limit to ONLY their DAG's records' files)
$docs = array();
$hasRepeatingData = false;
$this_record = (isset($_GET['id']) ? trim(urldecode($_GET['id'])) : null);
$file_data = Records::getData('array', $this_record, $file_fields, null, $user_rights['group_id']);
foreach ($file_data as $id=>&$attr) {
	foreach($attr as $event_id=>&$battr) {
		if ($event_id == 'repeat_instances') {
			$hasRepeatingData = true;
			foreach($battr as $event_id=>&$cattr) {
				foreach($cattr as $repeat_instrument=>&$dattr) {
					foreach($dattr as $instance=>&$eattr) {
						foreach ($eattr as $field_name=>$doc_id) {
							if ($doc_id == '') continue;
							$docs[$doc_id] = array('record'=>$id, 'field_name'=>$field_name, 'instrument'=>$repeat_instrument, 'instance'=>$instance);
							if ($longitudinal) $docs[$doc_id]['event_id'] = $event_id;
						}
					}
				}
			}
		} else {
			foreach ($battr as $field_name=>$doc_id) {
				if ($doc_id == '') continue;
				$docs[$doc_id] = array('record'=>$id, 'field_name'=>$field_name);
				if ($longitudinal) $docs[$doc_id]['event_id'] = $event_id;
			}
		}
	}
	unset($file_data[$id]);
}
unset($file_data, $attr, $battr, $cattr, $dattr, $eattr);
if (empty($docs)) exit($exitBtn);


# Get file details from edocs table
$list = "'".implode("','",array_map('prep',array_keys($docs)))."'";
$sql = "SELECT distinct a.doc_id, a.stored_name, a.file_extension, a.doc_name, a.stored_date, a.file_extension
		FROM redcap_edocs_metadata a WHERE a.doc_id in ($list)";
$q = db_query($sql);
if (db_num_rows($q) == 0) exit("ERROR: The specified document Ids ($list) were not found.");
while ($line = db_fetch_assoc($q)) {
	$id = $line['doc_id'];
	unset ($line['doc_id']);
	foreach ($line as $k=>$v) {
		$docs[$id][$k]=$v;
	}
}

## CREATE OUTPUT ZIP FILE AND INDEX
if (is_file($target_zip)) unlink($target_zip);

// Create ZipArchive object
$zip = new ZipArchive;

// Start writing to zip file
if ($zip->open($target_zip, ZipArchive::CREATE) === TRUE)
{
	// Add each file to archive
	$toc = array();

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

	// Loop through files
	foreach ($docs as $key=>&$params)
	{
		// Set name of file to be placed in zip file
		$name = $params['record']
			  . ($longitudinal ? '_'.$unique_events[$params['event_id']] : '');
		if (isset($params['instrument']) && $params['instrument'] != '') {
			$name .= '_'.$params['instrument'];
		}
		if (isset($params['instance'])) {
			$name .= '_'.$params['instance'];
		}
		$name .= '_'.$params['field_name'].'.'.$params['file_extension'];
		// If not using local storage for edocs, then obtain file contents before adding to zip
		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL: Add from "edocs" folder (use default or custom path for storage)
			if (file_exists(EDOC_PATH . $params['stored_name'])) {
				// Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
				$zip->addFile(EDOC_PATH . $params['stored_name'], "$zip_parent_folder/documents/$name");
			}
		} elseif ($edoc_storage_option == '2') {
			// S3
			// Open connection to create file in memory and write to it
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$params['stored_name'], 'SaveAs'=>APP_PATH_TEMP . $params['stored_name']));
				// Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
				if (file_exists(APP_PATH_TEMP . $params['stored_name'])) {
					// Get file's contents from temp directory and add file contents to zip file
					$zip->addFromString("$zip_parent_folder/documents/$name", file_get_contents(APP_PATH_TEMP . $params['stored_name']));
					// Now remove file from temp directory
					unlink(APP_PATH_TEMP . $params['stored_name']);
				}
			} catch (Aws\S3\Exception\S3Exception $e) {
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = Files::azureBlobClient();
			$blob = $blobClient->getBlob($GLOBALS['azure_container'], $params['stored_name']);
			$data = stream_get_contents($blob->getContentStream());
			$zip->addFromString("$zip_parent_folder/documents/$name", $data);
			unset($data);
		} else {
			// WebDAV
			$contents = '';
			$wdc->get($webdav_path . $params['stored_name'], $contents); //$contents is produced by webdav class
			// Add file contents to zip file
			if ($contents == null) $contents = '';
			$zip->addFromString("$zip_parent_folder/documents/$name", $contents);
		}
		// Write table row
		$this_row =  "<tr><td>".RCView::escape($params['record'])."</td>";
		if ($longitudinal) {
			$this_row .= "<td>".RCView::escape($Proj->eventInfo[$params['event_id']]['name_ext'])."</td>";
		}
		if ($hasRepeatingData) {
			if (isset($params['instrument'])) {
				$this_row .= "<td>".RCView::escape($Proj->forms[$params['instrument']]['menu'])."</td>";
				$this_row .= "<td>".$params['instance']."</td>";
			} else {
				$this_row .= "<td></td><td></td>";
			}
		}
		$this_row .= "<td>{$params['field_name']}</td><td>{$params['doc_name']}</td>" .
					 "<td><a href=\"documents/$name\" target=\"_blank\">$name</a></td><td>".DateTimeRC::format_ts_from_ymd($params['stored_date'])."</td></tr>";
		$toc[] = $this_row;
		unset($docs[$key], $params);
	}

	// Set HTML for index.html file
	$html = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">
<html>
	<head>
		<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\">
		<title>REDCap {$lang['data_export_tool_162']}</title>
		<style type=\"text/css\">
		html,body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,form,fieldset,input,p,blockquote,th,td{margin:0;padding:0;}
		img,body,html{border:0;}
		address,caption,cite,code,dfn,th,var{font-style:normal;font-weight:normal;}
		caption,th {text-align:left;}
		h1,h2,h3,h4,h5,h6{font-size:100%;}
		body {
			font-family: Arial, Verdana, Helvetica, sans-serif;
			font-size: 13px;
			padding:10px;
			-webkit-text-size-adjust:none;
		}
		table { text-align:left;border-collapse:collapse;border-width:0;margin:15px 0 0; }
		td, th { text-align:left; padding:3px 5px; }
		th {background-color: #eee; font-weight:bold; }
		div { max-width: 900px; }
		</style>
	</head>
	<body>
	<div>
		<div style='font-size:14px;'>
			{$lang['data_export_tool_163']}
			" . ($this_record === null ? $lang['data_export_tool_164'] : "{$lang['data_export_tool_165']} \"<b>".RCView::escape($this_record)."</b>\"") . "
			{$lang['data_export_tool_166']}
		</div>
		<div style='margin:3px 0;font-size:16px;'>
			\"<a href='".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/index.php?pid=$project_id' target='_blank' style='font-size:16px;font-weight:bold;'>".RCView::escape(strip_tags(html_entity_decode($app_title, ENT_QUOTES)))."</a>\"
			<span style='margin-left:5px;color:#888;font-size:12px;'>({$lang['data_export_tool_167']} " . DateTimeRC::format_ts_from_ymd(NOW) . ")</span>
		</div>
		<table border=1 cellspacing=0>
			<thead><tr><th>{$lang['global_49']}</th>".($longitudinal ? "<th>{$lang['global_10']}</th>" : "").
			($hasRepeatingData ? "<th>{$lang['global_138']}</th><th>{$lang['global_139']}</th>" : "")."
			<th>{$lang['design_484']}</th>
			<th>{$lang['data_export_tool_168']}</th><th>{$lang['data_export_tool_169']}</th><th>{$lang['data_export_tool_170']}</th></tr></thead>
			<tbody>
			".implode('',$toc)."
			</tbody>
		</table>
	</div>
	</body>
</html>";
	// Set text for Instructions.txt file
	$readme = "Extract the main folder in this ZIP file to your local computer.
You may double-click the index.html file inside the extracted
folder to view a listing of the files using your web browser, or
you may view the files directly by looking in the \"documents\" folder.";
	// Add index.html to zip file
	$zip->addFromString("$zip_parent_folder/index.html", $html);
	// Add Instructions.txt to zip file
	$zip->addFromString("Instructions.txt", $readme);
	// Done adding to zip file
	$zip->close();
}
## ERROR
else
{
	exit("ERROR: Unable to create ZIP archive at $target_zip");
}

// Logging
$log_descrip = "Download ZIP of uploaded files " . (isset($_GET['id']) ? "(single record)" : "(all records)");
$log_pk = (isset($_GET['id']) ? $_GET['id'] : $project_id);
$log_data_values = (isset($_GET['id']) ? "record = '{$_GET['id']}'" : "project_id = $project_id");
Logging::logEvent("", "redcap_edocs_metadata", "MANAGE", $log_pk, $log_data_values, $log_descrip);

// Download file and then delete it from the server
header('Pragma: anytextexeptno-cache', true);
header('Content-Type: application/octet-stream"');
header('Content-Disposition: attachment; filename="'.$download_filename.'"');
header('Content-Length: ' . filesize($target_zip));
ob_end_flush();
readfile_chunked($target_zip);
unlink($target_zip);
