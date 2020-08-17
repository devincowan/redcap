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

// Set values if they are invalid
if (!is_numeric($element_data['realtime_webservice_stop_fetch_inactivity_days']) || $element_data['realtime_webservice_stop_fetch_inactivity_days'] < 1) {
	$element_data['realtime_webservice_stop_fetch_inactivity_days'] = 7;
}
if (!is_numeric($element_data['realtime_webservice_data_fetch_interval']) || $element_data['realtime_webservice_data_fetch_interval'] < 1) {
	$element_data['realtime_webservice_data_fetch_interval'] = 24;
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

<h4 style="margin-top: 0;"><?php echo '<i class="fas fa-database"></i> ' . "{$lang['ws_63']} - {$lang['ws_240']}" ?></h4>

<?php
print RCView::p(array('style'=>''), $lang['ws_37']);
print RCView::p(array('style'=>''), $lang['ws_64']);
print RCView::div(array('style'=>'margin-bottom:5px;'),
		RCView::a(array('target'=>"_blank", 'href'=>APP_PATH_WEBROOT."DynamicDataPull/info.php", 'style'=>'text-decoration:underline;'), '<i class="fas fa-info-circle mr-1"></i>'.$lang['ws_98'])
	  );
print RCView::div(array('style'=>'margin-bottom:5px;'),
		RCView::a(array('target'=>"_blank", 'href'=>APP_PATH_WEBROOT."Resources/misc/redcap_ddp_technical_doc.pdf", 'style'=>'color:#800000;text-decoration:underline;'), '<i class="fas fa-file-pdf mr-2"></i>'.$lang['ws_65'])
	  );
print RCView::div(array('style'=>'margin-bottom:30px;'),
		RCView::a(array('target'=>"_blank", 'href'=>APP_PATH_WEBROOT."Resources/misc/redcap_ddp_demo_files.zip", 'style'=>'color:#826204;text-decoration:underline;'), '<i class="fas fa-file-archive mr-2"></i>' .$lang['ws_186'])
	  );
?>

<form action='ddp_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0; width: 100%;">


<tr>
	<td class="cc_label">
		<?php echo $lang['ws_66'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_95'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="realtime_webservice_global_enabled">
			<option value='0' <?php echo ($element_data['realtime_webservice_global_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['realtime_webservice_global_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['ws_96'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_67'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_68'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field' style='width:150px;' type='text' name='realtime_webservice_source_system_custom_name' value='<?php echo htmlspecialchars($element_data['realtime_webservice_source_system_custom_name'], ENT_QUOTES) ?>' /><br/>
		<div class="cc_info">
			e.g., Epic, Cerner, EMR, EDW
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_72'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_79'] ?>
			<div class='requiredlabel'>* <?php echo $lang['data_entry_39'] ?></div>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' style='width:300px;' type='text' id='realtime_webservice_url_metadata' name='realtime_webservice_url_metadata' value='<?php echo htmlspecialchars($element_data['realtime_webservice_url_metadata'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
		<button class="jqbuttonmed" onclick="setupTestUrl( $('#realtime_webservice_url_metadata') );return false;"><?php echo $lang['edit_project_138'] ?></button><br>
		<div class="cc_info">
			<?php echo $lang['ws_92'] ?>
		</div>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['ws_97'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['ws_73'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_79'] ?>
			<div class='requiredlabel'>* <?php echo $lang['data_entry_39'] ?></div>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' style='width:300px;' type='text' id='realtime_webservice_url_data' name='realtime_webservice_url_data' value='<?php echo htmlspecialchars($element_data['realtime_webservice_url_data'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
		<button class="jqbuttonmed" onclick="setupTestUrl( $('#realtime_webservice_url_data') );return false;"><?php echo $lang['edit_project_138'] ?></button><br>
		<div class="cc_info">
			<?php echo $lang['ws_93'] ?>
		</div>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['ws_97'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['ws_74'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_79'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' style='width:300px;' type='text' id='realtime_webservice_url_user_access' name='realtime_webservice_url_user_access' value='<?php echo htmlspecialchars($element_data['realtime_webservice_url_user_access'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
		<button class="jqbuttonmed" onclick="setupTestUrl( $('#realtime_webservice_url_user_access') );return false;"><?php echo $lang['edit_project_138'] ?></button><br>
		<div class="cc_info">
			<?php echo $lang['ws_94'] ?>
		</div>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['ws_97'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_69'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_70'] ?>
		</div>
	</td>
	<td class="cc_data">
		<textarea style='height:60px;' class='x-form-field notesbox' name='realtime_webservice_custom_text' id='realtime_webservice_custom_text'><?php echo $element_data['realtime_webservice_custom_text'] ?></textarea>
		<div id='realtime_webservice_custom_text-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('realtime_webservice_custom_text')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_195'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['ws_71'] . RCView::br() . RCView::span(array('style'=>'color:#C00000;'), "\"{$lang['ws_50']}\"") ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_75'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="realtime_webservice_display_info_project_setup">
			<option value='0' <?php echo ($element_data['realtime_webservice_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_77'] ?></option>
			<option value='1' <?php echo ($element_data['realtime_webservice_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_76'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['ws_78'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_80'] ?>
		<div class="cc_info" style="color:#C00000;">
			<?php echo $lang['ws_99'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="realtime_webservice_user_rights_super_users_only">
			<option value='0' <?php echo ($element_data['realtime_webservice_user_rights_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_81'] ?></option>
			<option value='1' <?php echo ($element_data['realtime_webservice_user_rights_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_82'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['ws_83'] ?>
		</div>
	</td>
</tr>


<tr>
	<td class="cc_label">
		<?php echo $lang['ws_84'] ?>
	</td>
	<td class="cc_data">
		<span class="cc_info" style="font-weight:bold;color:#000;">
			<?php echo $lang['ws_91'] ?>
		</span>
		<input class='x-form-text x-form-field' type='text' style='width:35px;' maxlength='3' onblur="redcap_validate(this,'1','999','hard','int');"  name='realtime_webservice_data_fetch_interval' value='<?php echo htmlspecialchars($element_data['realtime_webservice_data_fetch_interval'], ENT_QUOTES) ?>' />
		<span class="cc_info" style="font-weight:bold;color:#000;">
			<?php echo $lang['control_center_406'] ?>
		</span>
		<span class="cc_info" style="margin-left:20px;">
			<?php echo $lang['ws_88'] ?>
		</span>
		<div class="cc_info">
			<?php echo $lang['ws_90'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_85'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_87'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field' type='text' style='width:35px;' maxlength='3' onblur="redcap_validate(this,'1','100','hard','int');" name='realtime_webservice_stop_fetch_inactivity_days' value='<?php echo htmlspecialchars($element_data['realtime_webservice_stop_fetch_inactivity_days'], ENT_QUOTES) ?>' />
		<span class="cc_info" style="font-weight:bold;color:#000;">
			<?php echo $lang['scheduling_25'] ?>
		</span>
		<span class="cc_info" style="margin-left:20px;">
			<?php echo $lang['ws_89'] ?>
		</span>
		<div class="cc_info">
			<?php echo $lang['ws_86'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_252'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_255'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:360px;" name="realtime_webservice_convert_timestamp_from_gmt">
			<option value='0' <?php echo ($element_data['realtime_webservice_convert_timestamp_from_gmt'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_254'] ?></option>
			<option value='1' <?php echo ($element_data['realtime_webservice_convert_timestamp_from_gmt'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_253'] ?></option>
		</select>
		<div class="cc_info" style="color:#C00000;">
			<?php echo $lang['ws_256'] ?>
		</div>
	</td>
</tr>



</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<script type="text/javascript">
// Function to test the URL via web request and give popup message if failed/succeeded
function validateUrl(ob) {
	ob = $(ob);
	ob.val( trim(ob.val()) );
	var url = ob.val();
	if (url.length == 0) return;
	// Get or set the object's id
	if (ob.attr('id') == null) {
		var input_id = "input-"+Math.floor(Math.random()*10000000000000000);
		ob.attr('id', input_id);
	} else {
		var input_id = ob.attr('id');
	}
	// Disallow localhost
	var localhost_array = new Array('localhost', 'http://localhost', 'https://localhost', 'localhost/', 'http://localhost/', 'https://localhost/');
	if (in_array(url, localhost_array)) {
		simpleDialog('<?php echo js_escape($lang['edit_project_126']) ?>','<?php echo js_escape($lang['global_01']) ?>',null,null,"$('#"+input_id+"').focus();");
		return;
	}
	// Validate URL
	if (!isUrl(url)) {
		if (url.substr(0,4).toLowerCase() != 'http' && isUrl('http://'+url)) {
			// Prepend 'http' to beginning
			ob.val('http://'+url);
			// Now test it again
			validateUrl(ob);
		} else {
			// Error msg
			simpleDialog('<?php echo js_escape($lang['edit_project_126']) ?>','<?php echo js_escape($lang['global_01']) ?>',null,null,"$('#"+input_id+"').focus();");
		}
	}
}
// Perform the setup for testUrl()
function setupTestUrl(ob) {
	if (ob.val() == '') {
		ob.focus();
		return false;
	}
	// Get or set the object's id
	if (ob.attr('id') == null) {
		var input_id = "input-"+Math.floor(Math.random()*10000000000000000);
		ob.attr('id', input_id);
	} else {
		var input_id = ob.attr('id');
	}
	// Test it
	testUrl(ob.val(),'post',"$('#"+input_id+"').focus();");
}
</script>


<?php include 'footer.php'; ?>