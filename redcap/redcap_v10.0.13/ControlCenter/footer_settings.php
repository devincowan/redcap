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

<h4 style="margin-top: 0;"><i class="fas fa-level-down-alt"></i> <?php echo $lang['system_config_83'] ?></h4>

<form action='footer_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0;">
<tr  id="footer_links-tr" sq_id="footer_links">
	<td class="cc_label"><?php echo $lang['system_config_84'] ?></td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='footer_links' name='footer_links'><?php echo htmlspecialchars($element_data['footer_links'], ENT_QUOTES) ?></textarea><br/>
		<div id='footer_links-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('footer_links')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_85'] ?>
		</div>
	</td>
</tr>
<tr  id="footer_text-tr" sq_id="footer_text">
	<td class="cc_label"><?php echo $lang['system_config_86'] ?></td>
	<td class="cc_data">
		<textarea class='x-form-field notesbox' id='footer_text' name='footer_text'><?php echo htmlspecialchars($element_data['footer_text'], ENT_QUOTES) ?></textarea><br/>
		<div id='footer_text-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('footer_text')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_87'] ?>
		</div>
	</td>
</tr>
</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<?php include 'footer.php'; ?>