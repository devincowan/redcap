<?php


include 'header.php';
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

// Twilio setting is dependent upon another Twilio setting
?>
<script type="text/javascript">
function setTwilioDisplayInfo() {
	if ($('select[name="twilio_enabled_by_super_users_only"]').val() == '0') {
		$('select[name="twilio_display_info_project_setup"]').val('0').prop('disabled', true);
		$('#twilio_display_info_project_setup-tr').fadeTo(0, 0.6);
	} else {
		$('select[name="twilio_display_info_project_setup"]').prop('disabled', false);
		$('#twilio_display_info_project_setup-tr').fadeTo(0, 1);
	}
}
$(function(){
	setTwilioDisplayInfo();
});
</script>
<?php

$changesSaved = false;

// If project default values were changed, update redcap_config table with new values
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// Twilio setting is dependent upon another Twilio setting
	if ($_POST['twilio_enabled_by_super_users_only'] == '0') $_POST['twilio_display_info_project_setup'] = 0;

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

<h4 style="margin-top: 0;"><i class="fas fa-cubes"></i> <?php echo $lang['control_center_4604'] ?></h4>

<form action='modules_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0; width: 100%;">


<!-- External Modules settings -->
<tr>
    <td colspan="2">
        <h4 style="font-size:14px;padding:10px;color:#800000;"><?php echo $lang['system_config_642'] ?></h4>
    </td>
</tr>

<!-- Enable/disable the user activation request button for EMs -->
<tr>
    <td class="cc_label"><i class="fas fa-user-check"></i> <?php echo $lang['system_config_643'] ?></td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:350px;" name="external_modules_allow_activation_user_request">
            <option value='0' <?php echo ($element_data['external_modules_allow_activation_user_request'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_645'] ?></option>
            <option value='1' <?php echo ($element_data['external_modules_allow_activation_user_request'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_644'] ?></option>
        </select>
        <div class="cc_info">
			<?php echo $lang['system_config_646'] ?>
        </div>
    </td>
</tr>


<!-- Various modules/services -->
<tr>
	<td colspan="2">
        <hr size=1>
	<h4 style="font-size:14px;padding:10px;color:#800000;"><?php echo $lang['system_config_570'] ?></h4>
	</td>
</tr>

<!-- Enable/disable the use of surveys in projects -->
<tr>
	<td class="cc_label"><i class='fas fa-chalkboard-teacher'></i> <?php echo $lang['system_config_237'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_projecttype_singlesurveyforms">
			<option value='0' <?php echo ($element_data['enable_projecttype_singlesurveyforms'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_projecttype_singlesurveyforms'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
	</td>
</tr>

<!-- reCAPTCHA -->
<tr>
    <td class="cc_label">
        <i class="fas fa-redo"></i> <?php echo $lang['survey_1245'] ?>
        <div class="cc_info">
            <?php echo $lang['survey_1248'] ?>
        </div>
    </td>
    <td class="cc_data">
        <div>
            <?php echo $lang['survey_1246'] ?>
            <input type="text" class="x-form-text x-form-field" style="margin-left:15px;width:95%;max-width:280px;" name="google_recaptcha_site_key"  value="<?php echo htmlspecialchars($element_data['google_recaptcha_site_key'], ENT_QUOTES) ?>">
        </div>
        <div class="mt-2 mb-4">
            <?php echo $lang['survey_1247'] ?>
            <input type="password" class="x-form-text x-form-field" style="width:95%;max-width:200px;" name="google_recaptcha_secret_key" id="google_recaptcha_secret_key"  value="<?php echo htmlspecialchars($element_data['google_recaptcha_secret_key'], ENT_QUOTES) ?>">
            <a href="javascript:;" style="margin-left:5px;text-decoration:underline;font-size:11px;font-weight:normal;" onclick="$(this).remove();showSecret('#google_recaptcha_secret_key');"><?php echo $lang['system_config_258'] ?></a>
        </div>
        <a href="https://www.google.com/recaptcha/admin#list" target="_blank" style="text-decoration:underline;color:#A00000;"><?php echo $lang['survey_1249'] ?></a>
    </td>
</tr>


<tr  id="enable_url_shortener-tr" sq_id="enable_url_shortener">
	<td class="cc_label">
        <i class="fas fa-link"></i> <?php echo $lang['system_config_132'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_url_shortener">
			<option value='0' <?php echo ($element_data['enable_url_shortener'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_url_shortener'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $GLOBALS['enable_url_shortener_redcap'] ? $lang['system_config_640'] : $lang['system_config_583'] ?>
		</div>
	</td>
</tr>

<!-- Randomization -->
<tr>
	<td class="cc_label"><i class="fas fa-random"></i> <?php echo $lang['app_21'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="randomization_global">
			<option value='0' <?php echo ($element_data['randomization_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['randomization_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_225'] ?>
		</div>
	</td>
</tr>

<!-- Shared Library -->
<tr  id="shared_library_enabled-tr" sq_id="shared_library_enabled">
	<td class="cc_label"><i class="fas fa-book-reader"></i> REDCap Shared Library</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="shared_library_enabled">
			<option value='0' <?php echo ($element_data['shared_library_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['shared_library_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_110'] ?>
			<a href="<?php echo SHARED_LIB_PATH ?>" style='text-decoration:underline;' target='_blank'>REDCap Shared Library</a>
			<?php echo $lang['system_config_111'] ?>
		</div>
	</td>
</tr>


<!-- REDCap Messenger -->
<tr >
	<td class="cc_label">
        <i class="fas fa-comment-alt"></i> <?php echo $lang['messaging_09'] ?>
		<div style="width:105px;margin-top:5px;background-color:#40699E;padding:3px 5px;"><img style="width:90px;" src="<?php echo APP_PATH_IMAGES ?>messenger_logo.png"></div> 
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="user_messaging_enabled">
			<option value='0' <?php echo ($element_data['user_messaging_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['user_messaging_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>		
		<select class="x-form-text x-form-field" style="margin-left:5px;" name="user_messaging_prevent_admin_messaging">
			<option value='0' <?php echo ($element_data['user_messaging_prevent_admin_messaging'] == 0) ? "selected" : "" ?>><?php echo $lang['system_config_607'] ?></option>
			<option value='1' <?php echo ($element_data['user_messaging_prevent_admin_messaging'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_608'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['messaging_21'] ?>
		</div>
	</td>
</tr>

<!-- API -->
<tr >
	<td class="cc_label"><i class="fas fa-laptop-code"></i> REDCap API</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="api_enabled">
			<option value='0' <?php echo ($element_data['api_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['api_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_114'] ?>
			<a href='<?php echo APP_PATH_WEBROOT_FULL ?>api/help/' style='text-decoration:underline;' target='_blank'>REDCap API help page</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>


<!-- REDCap Mobile App -->
<tr>
	<td class="cc_label"><img src="<?php echo APP_PATH_IMAGES ?>phone_tablet.png"> <?php echo $lang['global_118'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="mobile_app_enabled">
			<option value='0' <?php echo ($element_data['mobile_app_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['mobile_app_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_330'] ?>
		</div>
	</td>
</tr>

<!-- External Modules alternate module directory paths (optional) -->
<tr>
	<td class="cc_label">
		<i class="fas fa-cube"></i> <?php echo $lang['global_142'] . $lang['colon'] . "<br>" . $lang['system_config_571'] ?>
		<div class="cc_info">
			<?php echo $lang['system_config_577'] ?>
		</div>	
	</td>
	<td class="cc_data">
		<input type="text" class="x-form-text x-form-field" style="width:95%;max-width:350px;" name="external_module_alt_paths"  value="<?php echo htmlspecialchars($element_data['external_module_alt_paths'], ENT_QUOTES) ?>">
		<div class="cc_info">
			e.g., /var/www/redcap/modules_staging/|/var/www/redcap/modules_internal/
		</div>
		<div class="cc_info">
			e.g., C:\xampp\htdocs\redcap\modules2\
		</div>
		
	</td>
</tr>

<!-- File Version History for File Upload fields -->
<tr>
    <td class="cc_label">
        <i class="fas fa-history" style="text-indent: 0;"></i> <i class="fas fa-file-upload" style="text-indent: 0;margin:0 1px 0 2px;"></i> <?php echo $lang['data_entry_456'] ?>
        <div class="cc_info">
            <?php echo $lang['data_entry_464'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="" name="file_upload_versioning_global_enabled">
            <option value='0' <?php echo ($element_data['file_upload_versioning_global_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['file_upload_versioning_global_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select><br/>
        <div class="cc_info">
            <?php echo $lang['data_entry_463'] ?>
        </div>
    </td>
</tr>

<!-- Embedded videos for Descriptive fields -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fab fa-youtube" style="text-indent: 0;"></i> <?php echo $lang['system_config_392'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_field_attachment_video_url">
			<option value='0' <?php echo ($element_data['enable_field_attachment_video_url'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_field_attachment_video_url'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_393'] ?>
		</div>
	</td>
</tr>


<!-- Allow text-to-speech service in surveys -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fas fa-volume-up" style="text-indent:0;"></i> <?php echo $lang['system_config_394'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_survey_text_to_speech">
			<option value='0' <?php echo ($element_data['enable_survey_text_to_speech'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_survey_text_to_speech'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_534'] ?>
		</div>
	</td>
</tr>


<!-- Allow auto-suggest functionality for ontology search on forms/surveys -->
<tr>
	<td class="cc_label">
		<div class="hang">
			<img src="<?php echo APP_PATH_IMAGES ?>search_field.png" style="margin-right:2px;"> <?php echo $lang['system_config_397'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_ontology_auto_suggest">
			<option value='0' <?php echo ($element_data['enable_ontology_auto_suggest'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_ontology_auto_suggest'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div style="font-weight:bold;margin:8px 0 5px;">
			<?php echo $lang['design_592'] ?>
			<input type="text" class="x-form-text x-form-field" style="width:250px;margin-left:6px;" name="bioportal_api_token" value="<?php echo $bioportal_api_token ?>">
		</div>
		<div class="cc_info">
			<?php
			echo $lang['system_config_398'] . " <b>" . BioPortal::getApiUrl() . "</b>" . $lang['period'] . " " .
				 $lang['system_config_399']
			?>
			<a href="<?php echo BioPortal::$SIGNUP_URL ?>" target="_blank" style="text-decoration:underline;"><?php echo $lang['system_config_400'] ?></a><?php echo $lang['period'] . " " . $lang['system_config_401'] ?>
		</div>
	</td>
</tr>


<!-- Data Entry Trigger enable -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fas fa-hand-point-right" style="text-indent: 0;"></i>
			<?php echo $lang['edit_project_136'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['edit_project_137'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="data_entry_trigger_enabled">
			<option value='0' <?php echo ($element_data['data_entry_trigger_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['data_entry_trigger_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['edit_project_160'] ?>
			<a href="javascript:;" onclick="simpleDialog(null,null,'dataEntryTriggerDialog',650);" class="nowrap" style="text-decoration:underline;"><?php echo $lang['edit_project_127'] ?></a>
		</div>
	</td>
</tr>


<!-- Project XML Export enable -->
<tr>
	<td class="cc_label">
		<div class="hang">
            <i class="fas fa-file-code fs14 mr-1" style="text-indent: 0;"></i>
			<?php echo $lang['system_config_547'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="display_project_xml_backup_option">
			<option value='0' <?php echo ($element_data['display_project_xml_backup_option'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['display_project_xml_backup_option'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_548'] ?>
		</div>
	</td>
</tr>

<tr  id="dts_enabled_global-tr" sq_id="dts_enabled_global">
	<td class="cc_label"><i class="fas fa-database"></i> <?php echo $lang['rights_132'] ?></td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="dts_enabled_global">
			<option value='0' <?php echo ($element_data['dts_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['dts_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_124'] ?>
		</div>
	</td>
</tr>


<!-- CATs -->
<tr>
	<td colspan="2">
		<hr size=1>
		<div style="margin:8px;">
			<div class="float-left"><a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>assessmentcenter.gif"></a></div>
            <div class="float-right"><a href='http://www.neuroqol.org/' style='text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>neuroqol.gif"></a></div>
            <div class="float-right"><a href='http://www.nihpromis.org/' style='margin:0 20px 0 80px;text-decoration:underline;' target='_blank'><img src="<?php echo APP_PATH_IMAGES ?>promis.png"></a></div>
        </div>
	</td>
</tr>
<tr >
	<td class="cc_label">
        <i class="fas fa-flag"></i>
		<?php echo $lang['system_config_388'] ?>
		<div class="cc_info">
			<?php
			echo "{$lang['system_config_389']} <a href='http://www.nihpromis.org/' style='text-decoration:underline;' target='_blank'>{$lang['system_config_314']}</a>
				  {$lang['global_43']} <a href='http://www.neuroqol.org/' style='text-decoration:underline;' target='_blank'>{$lang['system_config_390']}</a>{$lang['system_config_391']}
				  {$lang['system_config_316']} <a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'>Assessment Center API</a>{$lang['period']}";
			?>
		</div>
	</td>
	<td class="cc_data">
		<table cellspacing=0 width=100%>
			<tr>
				<td valign="top">
					<select class="x-form-text x-form-field" style="" name="promis_enabled">
						<option value='0' <?php echo ($element_data['promis_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
						<option value='1' <?php echo ($element_data['promis_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
					</select>
				</td>
				<td style="padding-left:15px;">
					<div style="margin:0 0 3px;">
						<?php echo $lang['system_config_317'] ?>
						&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('<?php echo $promis_api_base_url ?>','post','');return false;"><?php echo $lang['edit_project_138'] ?></button>
					</div>
					<div style="margin:3px 0;font-size:11px;color:#777;line-height:11px;">
						<?php echo $lang['system_config_318'] . " " . RCView::span(array('style'=>'color:#C00000;'), $promis_api_base_url) ?>
					</div>
				</td>
			</tr>
		</table>
		<div class="cc_info" style="color:#800000;margin-top:20px;">
			<?php echo "{$lang['system_config_315']} <a href='https://www.assessmentcenter.net/' style='text-decoration:underline;' target='_blank'>Assessment Center</a>{$lang['period']}
						{$lang['system_config_322']}" ?>
		</div>
	</td>
</tr>




<!-- Twilio -->
<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
		<img src="<?php echo APP_PATH_IMAGES ?>twilio.png">
		<?php echo $lang['survey_1284'] ?>
	</h3>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_847'] ?>
		<div class="cc_info">
			<?php echo $lang['survey_848'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="twilio_enabled_global">
			<option value='0' <?php echo ($element_data['twilio_enabled_global'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_enabled_global'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<span style="margin-left:12px;">
			<?php echo $lang['system_config_317'] ?>
			&nbsp;&nbsp;<button class="jqbuttonmed" onclick="testUrl('https://api.twilio.com','post','');return false;"><?php echo $lang['edit_project_138'] ?></button>
		</span>
		<div class="cc_info">
			<?php echo $lang['survey_712'] ?>
			<b>https://api.twilio.com</b><?php echo $lang['period'] ?>
			<?php echo $lang['survey_853']." ".$lang['survey_713'] ?>
			<a href='https://www.twilio.com' style='text-decoration:underline;' target='_blank'>https://www.twilio.com</a><?php echo $lang['period'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['survey_908'] ?>
	</td>
	<td class="cc_data">
		<select onchange="setTwilioDisplayInfo()" class="x-form-text x-form-field" style="" name="twilio_enabled_by_super_users_only">
			<option value='0' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_909'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_enabled_by_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_910'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_911'] ?>
		</div>
	</td>
</tr>

<tr id="twilio_display_info_project_setup-tr">
	<td class="cc_label">
		<?php echo $lang['survey_849'] ?>
		<div class="cc_info" style="color:#800000;">
			<?php echo $lang['survey_912'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="twilio_display_info_project_setup">
			<option value='0' <?php echo ($element_data['twilio_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['survey_850'] ?></option>
			<option value='1' <?php echo ($element_data['twilio_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['survey_851'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_852'] ?>
		</div>
	</td>
</tr>

<tr>
	<td colspan="2">
	<hr size=1>
	<h3 style="font-size:14px;padding:0 10px;color:#800000;">
        <i class="fas fa-chart-bar fs15"></i>
		<?php echo $lang['system_config_172'] ?>
	</h3>
	</td>
</tr>
<tr  id="enable_plotting-tr" sq_id="enable_plotting">
	<td class="cc_label">
		<?php echo $lang['system_config_175'] ?>
		<div class="cc_info" style="font-weight:normal;"><?php echo $lang['system_config_323'] ?></div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_plotting">
			<option value='0' <?php echo ($element_data['enable_plotting'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='2' <?php echo ($element_data['enable_plotting'] == 2) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info" style="color:#800000;font-weight:normal;"><?php echo $lang['system_config_174'] ?></div>
	</td>
</tr>
<tr  id="enable_plotting_survey_results-tr" sq_id="enable_plotting_survey_results">
	<td class="cc_label">
		<?php echo $lang['system_config_176'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="enable_plotting_survey_results">
			<option value='0' <?php echo ($element_data['enable_plotting_survey_results'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['enable_plotting_survey_results'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select><br/>
		<div class="cc_info">
			<?php echo $lang['system_config_171'] ?>
		</div>
	</td>
</tr>


<!-- e-Consent Framework for PDF Auto-Archiver -->
<tr>
	<td colspan="2">
		<hr size=1>
		<h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-file-pdf fs15"></i> <?php echo $lang['survey_1206'] ?></h3>
		<div style="padding:0 10px;"><?php echo $lang['survey_1207'] ?></div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1202'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" name="pdf_econsent_system_enabled" style="max-width:360px;">
			<option value='0' <?php echo ($element_data['pdf_econsent_system_enabled'] == '0') ? "selected" : "" ?>><?php echo $lang['survey_1205'] ?></option>
			<option value='1' <?php echo ($element_data['pdf_econsent_system_enabled'] == '1') ? "selected" : "" ?>><?php echo $lang['survey_1204'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['survey_1203'] ?>
		</div>
	</td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['survey_1218'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" name="pdf_econsent_system_ip" style="max-width:360px;">
			<option value='0' <?php echo ($element_data['pdf_econsent_system_ip'] == '0') ? "selected" : "" ?>><?php echo $lang['survey_1219'] ?></option>
			<option value='1' <?php echo ($element_data['pdf_econsent_system_ip'] == '1') ? "selected" : "" ?>><?php echo $lang['survey_1220'] ?></option>
		</select>
	</td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['system_config_625'] ?>
        <div class="cc_info">
            <?php echo $lang['system_config_626'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea style='height:65px;' class='x-form-field notesbox' id='pdf_econsent_system_custom_text' name='pdf_econsent_system_custom_text'><?php echo $element_data['pdf_econsent_system_custom_text'] ?></textarea>
        <div id='pdf_econsent_system_custom_text-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('pdf_econsent_system_custom_text')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info">
            <?php echo $lang['system_config_195'] ?>
        </div>
    </td>
</tr>


<!-- Alerts & Notifications -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-bell fs15"></i> <?php echo $lang['global_154'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['alerts_01'] ?></div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <div style="padding:15px 0 0 10px;color:#C00000;font-weight:bold;"><?php echo $lang['alerts_207'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_03'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_email_variables" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_email_variables'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_email_variables'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_07'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_02'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_email_freeform" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_email_freeform'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_email_freeform'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_08'] ?>
        </div>
    </td>
</tr>
<!-- Domain allowlist for user email addresses -->
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_09'] ?>
        <div class="cc_info">
            <?php echo $lang['alerts_10'] ?>
        </div>
    </td>
    <td class="cc_data">
        <textarea class='x-form-field notesbox' id='alerts_email_freeform_domain_allowlist' name='alerts_email_freeform_domain_allowlist'><?php echo $element_data['alerts_email_freeform_domain_allowlist'] ?></textarea><br/>
        <div id='alerts_email_freeform_domain_allowlist-expand' style='text-align:right;'>
            <a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
               onclick="growTextarea('alerts_email_freeform_domain_allowlist')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
        </div>
        <div class="cc_info">
            <?php echo $lang['system_config_234'] ?>
        </div>
        <div class="cc_info" style="padding:2px;border:1px solid #ccc;width:200px;">
            vanderbilt.edu<br>
            mc.vanderbilt.edu<br>
            mmc.edu<br>
        </div>
    </td>
</tr>

<tr>
    <td colspan="2">
        <div style="padding:15px 0 0 10px;color:#C00000;font-weight:bold;"><?php echo $lang['alerts_208'] ?></div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_209'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_phone_variables" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_phone_variables'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_phone_variables'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_211'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['alerts_210'] ?>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" name="alerts_allow_phone_freeform" style="max-width:360px;">
            <option value='0' <?php echo ($element_data['alerts_allow_phone_freeform'] == '0') ? "selected" : "" ?>><?php echo $lang['alerts_06'] ?></option>
            <option value='1' <?php echo ($element_data['alerts_allow_phone_freeform'] == '1') ? "selected" : "" ?>><?php echo $lang['alerts_05'] ?></option>
        </select>
        <div class="cc_info">
            <?php echo $lang['alerts_212'] ?>
        </div>
    </td>
</tr>


<!-- File Upload password verification + Duplicate File External Storage -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-key"></i> <i class="fas fa-cloud-upload-alt"></i> &nbsp;<?php echo $lang['data_entry_445'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['data_entry_446'] ?></div>
        <?php if (version_compare(PHP_VERSION, '5.6.0', '<')) { ?>
            <div class="yellow" style="margin:10px;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $lang['survey_1208'] . " " . PHP_VERSION . " " . $lang['survey_1209'] ?>
            </div>
        <?php } ?>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['data_entry_447'] ?>
        <span class="font-weight-normal"><?php echo $lang['data_entry_449'] ?></span>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="<?php if (version_compare(PHP_VERSION, '5.6.0', '<')) print "color:#999;"; ?>" name="file_upload_vault_filesystem_type" <?php if (version_compare(PHP_VERSION, '5.6.0', '<')) print "disabled"; ?>>
            <option value='' <?php echo ($element_data['file_upload_vault_filesystem_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='SFTP' <?php echo ($element_data['file_upload_vault_filesystem_type'] == 'SFTP') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> SFTP</option>
            <option value='WEBDAV' <?php echo ($element_data['file_upload_vault_filesystem_type'] == 'WEBDAV') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> WebDAV</option>
        </select>
        <div class="cc_info">
            <?php echo $lang['data_entry_448'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1196'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_host' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_host'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['survey_1197'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1198'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_username' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_username'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1199'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='password' name='file_upload_vault_filesystem_password' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_password'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1200'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_path' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /redcap/files/</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1201'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='file_upload_vault_filesystem_private_key_path' value='<?php echo htmlspecialchars($element_data['file_upload_vault_filesystem_private_key_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /credentials/redcap_sftp.pem</span>
    </td>
</tr>

<!-- Record-level locking PDF to External Storage -->
<tr>
    <td colspan="2">
        <hr size=1>
        <h3 style="font-size:14px;padding:0 10px;color:#800000;"><i class="fas fa-file-pdf fs15"></i> <i class="fas fa-cloud-upload-alt"></i> &nbsp;<?php echo $lang['data_entry_485'] ?></h3>
        <div style="padding:0 10px;"><?php echo $lang['data_entry_486'] ?></div>
        <?php if (version_compare(PHP_VERSION, '5.6.0', '<')) { ?>
            <div class="yellow" style="margin:10px;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $lang['survey_1208'] . " " . PHP_VERSION . " " . $lang['survey_1209'] ?>
            </div>
        <?php } ?>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['data_entry_447'] ?>
        <span class="font-weight-normal"><?php echo $lang['data_entry_449'] ?></span>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="<?php if (version_compare(PHP_VERSION, '5.6.0', '<')) print "color:#999;"; ?>" name="record_locking_pdf_vault_filesystem_type" <?php if (version_compare(PHP_VERSION, '5.6.0', '<')) print "disabled"; ?>>
            <option value='' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == '') ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='SFTP' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == 'SFTP') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> SFTP</option>
            <option value='WEBDAV' <?php echo ($element_data['record_locking_pdf_vault_filesystem_type'] == 'WEBDAV') ? "selected" : "" ?>><?php echo $lang['data_entry_450'] ?> WebDAV</option>
        </select>
        <div class="cc_info">
            <?php echo $lang['data_entry_448'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1196'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:300px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_host' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_host'], ENT_QUOTES) ?>'  /><br/>
        <div class="cc_info">
            <?php echo $lang['survey_1197'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1198'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_username' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_username'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1199'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:150px;' autocomplete='new-password' type='password' name='record_locking_pdf_vault_filesystem_password' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_password'], ENT_QUOTES) ?>'  /><br/>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1200'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_path' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /redcap/files/</span>
    </td>
</tr>
<tr>
    <td class="cc_label">
        <?php echo $lang['survey_1201'] ?>
    </td>
    <td class="cc_data">
        <input class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password' type='text' name='record_locking_pdf_vault_filesystem_private_key_path' value='<?php echo htmlspecialchars($element_data['record_locking_pdf_vault_filesystem_private_key_path'], ENT_QUOTES) ?>'  />
        <span class="cc_info" style="margin-left:6px;">e.g., /credentials/redcap_sftp.pem</span>
    </td>
</tr>

</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<?php
// Data Entry Trigger explanation - hidden dialog
print RCView::simpleDialog($lang['edit_project_160']."<br><br>".$lang['edit_project_128'] .
	RCView::div(array('style'=>'padding:12px 0 2px;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('project_id')." - ".$lang['edit_project_129']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('username')." - ".$lang['edit_project_157']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('instrument')." - ".$lang['edit_project_130']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('record')." - ".$lang['edit_project_131'].$lang['period']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_event_name')." - ".$lang['edit_project_132']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_data_access_group')." - ".$lang['edit_project_133']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('[instrument]_complete')." - ".$lang['edit_project_134']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_repeat_instance')." - ".$lang['edit_project_181']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_repeat_instrument')." - ".$lang['edit_project_182']).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('redcap_url')." - ".$lang['edit_project_144']."<br>i.e., ".APP_PATH_WEBROOT_FULL).
	RCView::div(array('style'=>'padding:2px 0;text-indent:-2em;margin-left:2em;'), "&bull; ".RCView::b('project_url')." - ".$lang['edit_project_145']."<br>i.e., ".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/index.php?pid=XXXX").
	RCView::div(array('style'=>'padding:20px 0 5px;color:#C00000;'), $lang['global_02'].$lang['colon'].' '.$lang['edit_project_135'])
	,$lang['edit_project_122'],'dataEntryTriggerDialog');

include 'footer.php'; ?>