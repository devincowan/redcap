<?php


// Only accept Post submission
if ($_SERVER['REQUEST_METHOD'] != 'POST') exit;

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Reference list of valid image files that can be used as inline image for Descriptive fields
$image_file_ext = array('png', 'gif', 'jpg', 'jpeg', 'bmp');

// Get file attributes
$doc_name = strtolower(str_replace("'", "", html_entity_decode(stripslashes($_FILES['myfile']['name']), ENT_QUOTES)));
$doc_size = $_FILES['myfile']['size'];
$tmp_name = $_FILES['myfile']['tmp_name'];

// Check if file is larger than max file upload limit
if (($doc_size/1024/1024) > maxUploadSize() || $_FILES['file']['error'] != UPLOAD_ERR_OK)
{
	// Give error response
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.alert('<?php echo "ERROR: CANNOT UPLOAD FILE!" ?>');
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Upload the file and access it via ZipArchive
$zip = new ZipArchive;
$res = $zip->open($tmp_name);
if ($res !== TRUE) {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['random_13']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Give error response if not a zip file
if (substr($doc_name, -4) != '.zip') {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['design_537']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Get instrument.csv
$instrumentDD = $zip->getFromName('instrument.csv');
if ($instrumentDD === false) {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['design_538']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Obtain OriginID, AuthorID, and InstrumentID (if available)
$OriginID = $zip->getFromName('OriginID.txt');
$AuthorID = $zip->getFromName('AuthorID.txt');
$InstrumentID = $zip->getFromName('InstrumentID.txt');


// Get correct table we're using, depending on if in production
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

## PROCESS THE "DATA DICTIONARY" FOR THE INSTRUMENT
// Store DD in the temp directory so we can read it
$dd_filename = APP_PATH_TEMP . date('YmdHis') . '_instrumentdd_' . $project_id . '_' . substr(sha1(rand()), 0, 6) . '.csv';
file_put_contents($dd_filename, $instrumentDD);
// Parse DD
$dd_array = Design::excel_to_array($dd_filename);
unlink($dd_filename);
// If DD returns false, then it's because of some unknown error
if ($dd_array === false || $dd_array == "") {
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($lang['random_13']) ?>',null,null,350);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Obtain any attachments in the "attachments" directory
$attachments_list = $attachments_index_list = array();
$attachments_dir = "attachments";
for ($i = 0; $i < $zip->numFiles; $i++) {
	// Set full filename and base filename
    $this_file = $zip->getNameIndex($i);
	$this_file_base = basename($this_file);
	// Make sure the file is in the attachments dir
	if (substr($this_file, 0, strlen($attachments_dir)) != $attachments_dir) continue;
	// Make sure the sub-dir is a valid field in the DD
	$this_field = basename(dirname($this_file));
	if (!in_array($this_field, $dd_array['A'])) continue;
	// Add file to array list
	$attachments_list[$this_field] = $this_file_base;
	$attachments_index_list[$this_field."/".$this_file_base] = $i;
}

// Find any variables that are duplicated in the DD
$duplicate_fields = array();
foreach (array_count_values($dd_array['A']) as $this_field=>$this_count) {
	if ($this_count < 2) continue;
	$duplicate_fields[] = $this_field;
}
if (!empty($duplicate_fields)) {
	$msg = $lang['design_541'] . " \"<b>" . implode("</b>\", \"<b>", $duplicate_fields) . "</b>\"" . $lang['period'];
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape($msg) ?>',null,null,450);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

// Make sure that all the form names are the same and that it is unique
$current_forms = ($status > 0) ? $Proj->forms_temp : $Proj->forms;
$unique_forms_new = array_count_values($dd_array['B']);
unset($unique_forms_new['']);
$unique_forms_new = array_keys($unique_forms_new);
// Set new unique form name
$this_form = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower($unique_forms_new[0])));
// Remove any double underscores, beginning numerals, and beginning/ending underscores
while (strpos($this_form, "__") !== false) 		$this_form = str_replace("__", "_", $this_form);
while (substr($this_form, 0, 1) == "_") 		$this_form = substr($this_form, 1);
while (substr($this_form, -1) == "_") 			$this_form = substr($this_form, 0, -1);
while (is_numeric(substr($this_form, 0, 1))) 	$this_form = substr($this_form, 1);
while (substr($this_form, 0, 1) == "_") 		$this_form = substr($this_form, 1);
// Cannot begin with numeral and cannot be blank
if (is_numeric(substr($this_form, 0, 1)) || $this_form == "") {
	$this_form = substr(preg_replace("/[0-9]/", "", md5($this_form)), 0, 4) . $this_form;
}
// Make sure it's less than 64 characters long
$this_form = substr($this_form, 0, 64);
while (substr($this_form, -1) == "_") $this_form = substr($this_form, 0, -1);

// Ensure uniqueness of the form
$form_exists = isset($current_forms[$this_form]);
while ($form_exists) {
	// Make sure no longer than 100 characters and append alphanums to end
	$this_form = substr(str_replace("__", "_", $this_form), 0, 94) . "_" . substr(sha1(rand()), 0, 6);
	// Does new form already exist?
	$form_exists = (isset($current_forms[$this_form]));
}
// Set all fields with single form name (just in case)
foreach ($dd_array['B'] as $key=>$this_field) {
	$dd_array['B'][$key] = $this_form;
}

// Array to capture renamed field names
$renamed_fields = array();
// Check for any variable name collisions and modify any if there are duplicates
$current_metadata = ($status == 0 ? $Proj->metadata : $Proj->metadata_temp);
foreach ($dd_array['A'] as $key=>$this_field) {
	// Set original field name
	$this_field_orig = $this_field;
	// If exists in project or is duplicated in the DD itself, then generate a new variable name
	$field_exists = isset($current_metadata[$this_field]);
	while ($field_exists) {
		// Make sure no longer than 100 characters and append alphanums to end
		$this_field = substr(str_replace("__", "_", $this_field), 0, 94) . "_" . substr(sha1(rand()), 0, 6);
		// Does new field exist in existing fields or new fields being added?
		$field_exists = (isset($current_metadata[$this_field]) || in_array($this_field, $dd_array['A']));
		// Add to array
		if (!$field_exists) {
			$renamed_fields[$this_field] = $this_field_orig;
		}
	}
	// Change field name in array
	$dd_array['A'][$key] = $this_field;
}

// Loop through all fields to change branching and calcs if field is used in those
if (!empty($renamed_fields)) {
	// Loop through calc fields
	foreach ($dd_array['F'] as $key=>$this_calc) {
		// Is field a calc field?
		if ($dd_array['D'][$key] != 'calc') continue;
		// Replace any renamed fields in equation (via looping)
		foreach ($renamed_fields as $this_field_new => $this_field_orig) {
			// If doesn't contain the field, then skip
			if (strpos($this_calc, "[$this_field_orig]") === false) continue;
			// Replace field
			$dd_array['F'][$key] = $this_calc = str_replace("[$this_field_orig]", "[$this_field_new]", $this_calc);
		}
	}
	// Loop through branching logic
	foreach ($dd_array['L'] as $key=>$this_branching) {
		// If has no branching, then skip
		if ($this_branching == '') continue;
		// Replace any renamed fields in equation (via looping)
		foreach ($renamed_fields as $this_field_new => $this_field_orig) {
			// If doesn't contain the field, then skip
			if (strpos($this_branching, "[$this_field_orig]") === false) continue;
			// Replace field
			$dd_array['L'][$key] = $this_branching = str_replace("[$this_field_orig]", "[$this_field_new]", $this_branching);
		}
	}
	// Loop through attachment list of fields to confirm the field names
	foreach ($attachments_list as $this_field=>$this_file) {
		// Confirm the field exists first
		$array_key = array_search($this_field, $dd_array['A']);
		// If false, then check if it was renamed
		if ($array_key === false) {
			// Confirm the field exists in the renamed list of fields
			$renamed_array_key = array_search($this_field, $renamed_fields);
			if ($renamed_array_key === false) continue;
			// Set the key as the new renamed field, removing old one
			$attachments_list[$renamed_array_key] = $attachments_list[$this_field];
			unset($attachments_list[$this_field]);
			// Also redo the zip archive index number for this file
			$attachments_index_list[$renamed_array_key."/".$this_file] = $attachments_index_list[$this_field."/".$this_file];
			unset($attachments_index_list[$this_field."/".$this_file]);
		}
	}
}

// PREVENT MATRIX GROUP NAME DUPLICATION: Rename any matric group names that already exist in project to prevent duplication
$matrix_group_name_fields = ($status > 0) ? $Proj->matrixGroupNamesTemp : $Proj->matrixGroupNames;
$matrix_group_names = array_keys($matrix_group_name_fields);
$matrix_group_names_transform = array();
// Loop through fields being imported to find matrix group names
foreach ($dd_array['P'] as $this_mgn) {
	// Get matrix group name, if exists for this field
	if ($this_mgn == '') continue;
	$this_mgn_orig = $this_mgn;
	// Does matrix group name already exist?
	$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
	$mgn_renamed = false;
	while ($mgn_exists) {
		// Rename it
		// Make sure no longer than 50 characters and append alphanums to end
		$this_mgn = substr($this_mgn, 0, 43) . "_" . substr(sha1(rand()), 0, 6);
		// Does new field exist in existing fields or new fields being added?
		$mgn_exists = (in_array($this_mgn, $matrix_group_names) && !isset($matrix_group_names_transform[$this_mgn]));
		$mgn_renamed = true;
	}
	// Add to transform array
	if ($mgn_renamed) {
		$matrix_group_names_transform[$this_mgn_orig] = $this_mgn;
	}
}
// Loop through fields being imported to rename matrix group names
foreach ($dd_array['P'] as $key=>$this_mgn) {
	if (isset($matrix_group_names_transform[$this_mgn])) {
		$dd_array['P'][$key] = $matrix_group_names_transform[$this_mgn];
	}
}

// Return warnings and errors from file (and fix any correctable errors)
list ($errors_array, $warnings_array, $dd_array) = MetaData::error_checking($dd_array, true);
// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");
// Save data dictionary in metadata table
$sql_errors = (empty($errors_array)) ? MetaData::save_metadata($dd_array, true) : 0;
if (!empty($errors_array) || count($sql_errors) > 0) {
	// ERRORS OCCURRED, so undo any changes made
	db_query("ROLLBACK");
	// Set back to previous value
	db_query("SET AUTOCOMMIT=1");
	// Display error messages
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_zip_instrument_fail').style.display = 'block';
	window.parent.window.simpleDialog('<?php echo js_escape(RCView::div(array('style'=>'font-weight:bold;font-size:14px;color:#C00000;margin-bottom:15px;'), $lang['random_13']). implode("</div><div style='margin:8px 0;'>", $errors_array) . ((SUPER_USER && count($sql_errors) > 0) ? "SQL errors (for super users only):<br>" . implode("</div><div style='margin:8px 0;'>", $sql_errors) : "")) ?>',null,null,500);
	</script>
	<?php
	// Delete temp file
	unlink($tmp_name);
	exit;
}

## ADD ANY ATTACHMENTS
// Loop through attachment list of fields
foreach ($attachments_list as $this_field=>$this_file) {
	// Extract file from zip
	$zip_index = $attachments_index_list[$this_field."/".$this_file];
	if (!is_numeric($zip_index)) continue;
	$this_filename = $zip->getNameIndex($zip_index);
	// Get file extension
	$this_file_ext = getFileExt($this_file);
	// If a URL file extension, then add as video_url attribute
	if (strtolower($this_file_ext) == 'url') {
		// Get file contents
		$this_url_file_content = trim(str_replace(array("[InternetShortcut]\r\nURL=","[InternetShortcut]\rURL=","[InternetShortcut]\nURL="),
								 array("","",""),
								 $zip->getFromIndex($zip_index)));
		// Now add video_url to the field
		$sql = "update $metadata_table set video_url = '".db_escape($this_url_file_content)."', video_display_inline = 0
				where project_id = " . PROJECT_ID . " and field_name = '$this_field'";
		db_query($sql);
	}
	// If an edoc, then upload edoc file to field
	else {
		// Copy to temp from zip file
		$fp = $zip->getStream($this_filename);
		if (!$fp) continue;
		$temp_name = APP_PATH_TEMP . date('YmdHis') . "_pid" . PROJECT_ID . "_zipattachment{$zip_index}_" . substr(sha1(rand()), 0, 6) . "." . $this_file_ext;
		$ofp = fopen($temp_name, 'w');
		while (!feof($fp)) fwrite($ofp, fread($fp, 8192));
		fclose($fp);
		fclose($ofp);
		// Set file attributes
		$mime_type = (in_array(strtolower($this_file_ext), $image_file_ext)) ? "image/".strtolower($this_file_ext) : "application/octet-stream";
		$file_attr = array('size'=>filesize($temp_name), 'type'=>$mime_type, 'name'=>$this_file, 'tmp_name'=>$temp_name);
		// Upload file to edocs directory
		$edoc_id = Files::uploadFile($file_attr);
		unlink($temp_name);
		if (!is_numeric($edoc_id)) continue;
		// Is the file an image? If so, set as inline image automatically
		$edoc_display_img = (in_array(strtolower($this_file_ext), $image_file_ext)) ? 1 : 0;
		// Now add edoc_id to the field
		$sql = "update $metadata_table set edoc_id = $edoc_id, edoc_display_img = $edoc_display_img
				where project_id = " . PROJECT_ID . " and field_name = '$this_field'";
		db_query($sql);
	}
}

// COMMIT CHANGES
db_query("COMMIT");
// Set back to previous value
db_query("SET AUTOCOMMIT=1");

// Add OriginID, AuthorID, and InstrumentID to db tables if we have them
if ($OriginID !== false && $OriginID != '') {
	// Add OriginID to table and increment its count
	$sql = "insert into redcap_instrument_zip_origins (server_name) values ('".db_escape($OriginID)."')
			on duplicate key update upload_count = upload_count + 1";
	db_query($sql);
}
if ($AuthorID !== false && $AuthorID != '') {
	// Get iza_id of AuthorID
	$sql = "select iza_id from redcap_instrument_zip_authors where author_name = '".db_escape($AuthorID)."'";
	$q = db_query($sql);
	if (db_num_rows($q) == 0) {
		$sql = "insert into redcap_instrument_zip_authors (author_name) values ('".db_escape($AuthorID)."')";
		db_query($sql);
		$iza_id = db_insert_id();
	} else {
		$iza_id = db_result($q, 0);
	}
	// Add InstrumentID to table and increment its count
	if ($InstrumentID !== false && $InstrumentID != '') {
		$sql = "insert into redcap_instrument_zip (iza_id, instrument_id) values ($iza_id, '".db_escape($InstrumentID)."')
				on duplicate key update upload_count = upload_count + 1";
		db_query($sql);
	}
}

// Do logging of file upload
Logging::logEvent("",$metadata_table,"MANAGE",$this_form,"form_name = '$this_form'","Create data collection instrument (via instrument ZIP file)");

// Delete temp file
unlink($tmp_name);

// Create text if there were any renamed fields (to inform the user)
$renamed_fields_text = "";
if (!empty($renamed_fields)) {
	$renamed_fields_text = RCView::div(array('style'=>'line-height:14px;margin-bottom:5px;'), RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['design_565']);
	foreach ($renamed_fields as $new_field=>$old_field) {
		$renamed_fields_text .= RCView::div(array('style'=>'margin:1px 0 1px 10px;line-height:12px;'), "- \"<b>$old_field</b>\" {$lang['design_566']} \"<b>$new_field</b>\"");
	}
	$renamed_fields_text = RCView::div(array('style'=>'height:100px;overflow-y:scroll;margin:20px 0 0;padding:5px;border:1px solid #ccc;'), $renamed_fields_text);
}

// Give response using javascript
?>
<script language="javascript" type="text/javascript">
window.parent.window.document.getElementById('div_zip_instrument_in_progress').style.display = 'none';
window.parent.window.document.getElementById('div_zip_instrument_success').style.display = 'block';
var renamed_fields_text = '<?php print js_escape($renamed_fields_text) ?>';
window.parent.window.reloadPageOnCloseZipPopup(renamed_fields_text);
if (renamed_fields_text.length == 0) {
	window.parent.window.setTimeout(function(){
		window.parent.window.location.href = window.parent.window.app_path_webroot+'Design/online_designer.php?pid='+window.parent.window.pid;
	},2500);
}
</script>
