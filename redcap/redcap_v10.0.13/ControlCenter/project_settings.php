<?php


include 'header.php';
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$changes_log = array();
	$sql_all = array();
	foreach ($_POST as $this_field=>$this_value) {
		// Save this individual field value
		$sql = "UPDATE redcap_config SET value = '".db_escape($this_value)."' WHERE field_name = '$this_field'";
		$q = db_query($sql);

		// Log changes (if change was made)
		if ($q && db_affected_rows() > 0) {
			$sql_all[] = $sql;
			$changes_log[] = "$this_field = '$this_value'";
		}
	}

	// Log any changes in log_event table
	if (count($changes_log) > 0) {
		Logging::logEvent(implode(";\n",$sql_all),"redcap_config","MANAGE","",implode(",\n",$changes_log),"Modify system configuration");
	}

	$changesSaved = true;
}

// Retrieve data to pre-fill in form
$element_data = array();

$q = db_query("select * from redcap_config");
while ($row = db_fetch_array($q)) {
		$element_data[$row['field_name']] = $row['value'];
}
?>

<?php
if ($changesSaved)
{
	// Show user message that values were changed
	print  "<div class='yellow' style='margin-bottom: 20px; text-align:center'>
			<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			{$lang['control_center_19']}
			</div>";
}
?>

<h4 style="margin-top: 0;"><i class="fas fa-pen-square"></i> <?php echo $lang['system_config_88'] ?></h4>

<form action='project_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">

<tr>
	<td class="cc_label"><?php echo $lang['system_config_90'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="project_language">
			<?php
			$languages = Language::getLanguageList();
			foreach ($languages as $language) {
				$selected = ($element_data['project_language'] == $language) ? "selected" : "";
				echo "<option value='$language' $selected>$language</option>";
			}
			?>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_107'] ?>
			<a href="<?php echo APP_PATH_WEBROOT ?>LanguageUpdater/" target='_blank' style='text-decoration:underline;'>Language File Creator/Updater</a>
			<?php echo $lang['system_config_108'] ?>
			<a href='https://community.projectredcap.org/articles/676/redcap-language-center.html' target='_blank' style='text-decoration:underline;'>REDCap Community Language Center</a>.
			<br/><br/><?php echo $lang['system_config_109']." ".dirname(APP_PATH_DOCROOT).DS."languages".DS ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['system_config_293'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_294'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="project_encoding">
			<option value='' <?php echo ($element_data['project_encoding'] == '') ? "selected" : "" ?>><?php echo $lang['system_config_295'] ?></option>
			<option value='japanese_sjis' <?php echo ($element_data['project_encoding'] == 'japanese_sjis') ? "selected" : "" ?>><?php echo $lang['system_config_296'] ?></option>
			<option value='chinese_utf8' <?php echo ($element_data['project_encoding'] == 'chinese_utf8') ? "selected" : "" ?>><?php echo $lang['system_config_627'] ?></option>
            <option value='chinese_utf8_traditional' <?php echo ($element_data['project_encoding'] == 'chinese_utf8_traditional') ? "selected" : "" ?>><?php echo $lang['system_config_628'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['system_config_298'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_129'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="display_project_logo_institution">
			<option value='0' <?php echo ($element_data['display_project_logo_institution'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_231'] ?></option>
			<option value='1' <?php echo ($element_data['display_project_logo_institution'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_230'] ?></option>
		</select><br/>
	</td>
</tr>
<tr>
	<td class="cc_label"><?php echo $lang['system_config_143'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="display_today_now_button">
			<option value='0' <?php echo ($element_data['display_today_now_button'] == 0) ? "selected" : "" ?>><?php echo $lang['design_99'] ?></option>
			<option value='1' <?php echo ($element_data['display_today_now_button'] == 1) ? "selected" : "" ?>><?php echo $lang['design_100'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_144'] ?>
		</div>
	</td>
</tr>
<tr>
    <td class="cc_label"><?php echo $lang['data_entry_456'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="file_upload_versioning_enabled">
            <option value='0' <?php echo ($element_data['file_upload_versioning_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['file_upload_versioning_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['data_entry_462'] ?>
        </div>
    </td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<?php include 'footer.php'; ?>