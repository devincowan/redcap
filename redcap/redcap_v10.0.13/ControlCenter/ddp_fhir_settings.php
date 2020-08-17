<?php


use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;


// If auto-finding FHIR token/authorize URLs
if (isset($_POST['url'])) 
{
	// Config for non-project pages
	require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
	// Call the URL
	$headers = array("Accept: application/json");
	$response = http_get($_POST['url'], 10, "", $headers);
	$metadata = json_decode($response, true);
	if (!is_array($metadata)) exit('0');
	// Get authorize endpoint URL and token endpoint URL
	$authorizeUrl = $tokenUrl = "";
	foreach ($metadata['rest'][0]['security']['extension'][0]['extension'] as $attr) {
		if ($attr['url'] == 'authorize') {
			$authorizeUrl = $attr['valueUri'];
		} elseif ($attr['url'] == 'token') {
			$tokenUrl = $attr['valueUri'];
		}
	}
	if ($authorizeUrl == "" || $tokenUrl == "") exit('0');
	// Return URLs
	exit("$authorizeUrl\n$tokenUrl");
}

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


// CREATE A BLADE TEMPLATING MANAGER
$blade = Renderer::getBlade();
$blade->share('lang', $lang); // set the lang variable available for all views
$blade->share('form_data', $element_data); // the the options available for all views

// Set values if they are invalid
if (!is_numeric($element_data['fhir_stop_fetch_inactivity_days']) || $element_data['fhir_stop_fetch_inactivity_days'] < 1) {
	$element_data['fhir_stop_fetch_inactivity_days'] = 7;
}
if (!is_numeric($element_data['fhir_data_fetch_interval']) || $element_data['fhir_data_fetch_interval'] < 1) {
	$element_data['fhir_data_fetch_interval'] = 24;
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

<div style="font-size:18px;">
	<div class="float-left" style="margin-top:10px;"><i class="fas fa-fire"></i> <?php echo $lang['ws_262'] ?></div>
	<div class="float-right" style="margin-right:30px;">
		<?php echo RCView::img(array('src'=>'ehr_fhir.png')) ?>
	</div>
</div>
<div class="clear"></div>

<?php
print RCView::p(array('style'=>''), $lang['ws_207'] . " " . $lang['ws_297']);
print RCView::p(array('style'=>''), $lang['ws_317']);
print RCView::div(array('style'=>'margin-bottom:5px;'),
		RCView::a(array('target'=>"_blank", 'href'=>APP_PATH_WEBROOT."Resources/misc/redcap_fhir_overview.pdf", 'style'=>'color:#A00000;text-decoration:underline;'), '<i class="fas fa-file-pdf mr-2"></i>'.$lang['ws_296'])
	  );
print RCView::div(array('style'=>'margin-bottom:5px;'),
		RCView::a(array('target'=>"_blank", 'href'=>APP_PATH_WEBROOT."DynamicDataPull/info.php?type=fhir", 'style'=>'text-decoration:underline;'), '<i class="fas fa-info-circle mr-1"></i>'.$lang['ws_266'])
	  );
print RCView::div(array('style'=>'margin-bottom:30px;'),
		RCView::a(array('target'=>"_blank", 'href'=>APP_PATH_WEBROOT."Resources/misc/redcap_fhir_setup.zip", 'style'=>'color:#826204;text-decoration:underline;'), '<i class="fas fa-file-archive mr-2"></i>' .$lang['ws_236'])
	  );
?>

<style type="text/css">
    #cdis-diff {display:none;}
    #cdis-diff table {background-color: #fff;}
    #cdis-diff td {padding:7px 10px;}
    #cdis-diff ul {margin:0px;margin-block-start:0em;margin-block-end:0em;padding-inline-start:10px;}
</style>

<form action='ddp_fhir_settings.php' enctype='multipart/form-data' target='_self' method='post' name='form' id='form'>
<?php
// Go ahead and manually add the CSRF token even though jQuery will automatically add it after DOM loads.
// (This is done in case the page is very long and user submits form before the DOM has finished loading.)
print "<input type='hidden' name='redcap_csrf_token' value='".System::getCsrfToken()."'>";
?>
<table style="border: 1px solid #ccc; background-color: #f0f0f0; width: 100%;">


<tr>
    <td class="cc_label" style="border-top:1px solid #ccc;color:#C00000;" colspan="2">
        <?php echo $lang['ws_267'] ?>
    </td>
</tr>
<tr>
	<td class="cc_label">
        <i class="fas fa-database"></i>
		<?php echo $lang['ws_265'] ?>
        <div class="cc_info">
            <?php echo $lang['ws_288'] ?>
        </div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_ddp_enabled">
			<option value='0' <?php echo ($element_data['fhir_ddp_enabled'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
			<option value='1' <?php echo ($element_data['fhir_ddp_enabled'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['ws_216'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label" style="padding-bottom:20px;">
        <i class="fas fa-shopping-cart"></i>
        <?php echo $lang['global_155'] ?>
        <div class="cc_info">
            <?php echo $lang['ws_295'] ?>
        </div>
    </td>
    <td class="cc_data" style="padding-bottom:20px;">
        <select class="x-form-text x-form-field" style="" name="fhir_data_mart_create_project">
            <option value='0' <?php echo ($element_data['fhir_data_mart_create_project'] == 0) ? "selected" : "" ?>><?php echo $lang['global_23'] ?></option>
            <option value='1' <?php echo ($element_data['fhir_data_mart_create_project'] == 1) ? "selected" : "" ?>><?php echo $lang['system_config_27'] ?></option>
        </select>
        <div class="cc_info" style="color:#C00000;">
            <?php echo $lang['ws_243'] ?>
        </div>
    </td>
</tr>
<tr>
    <td class="cc_data" style="padding-top:2px;" colspan="2">
        <div class="boldish" style="color:#C00000;">
            <i class="fas fa-lightbulb"></i> <?php echo $lang['ws_294'] ?>
            <button class="btn btn-xs btn-rcred ml-2" onclick="simpleDialog(null,null,'cdis-diff',1000);fitDialog($('#cdis-diff'));return false;"><?php echo $lang['global_84'] ?></button>
        </div>
        <!-- CDP vs Data Mart dialog -->
        <div id="cdis-diff" class="mt-2 simpleDialog" title="<?=js_escape2($lang['ws_294'])?>">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">
                        </th>
                        <th scope="col" class="boldish clearfix">
                            <div class="float-left fs15 mt-1" style="color:#000066;">
                                <i class="fas fa-database"></i>
                                <?php echo $lang['ws_265'] ?>
                            </div>
                            <div class="float-right">
                                <button class="btn btn-xs invisible"><?=$lang['scheduling_35']?></button>
                            </div>
                        </th>
                        <th scope="col" class="boldish clearfix">
                            <div class="float-left fs15 mt-1" style="color:#A00000;">
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $lang['global_155'] ?>
                            </div>
                            <div class="float-right">
                                <button class="btn btn-xs btn-defaultrc" onclick="$('#cdis-diff button').hide();$('#cdis-diff td, #cdis-diff th').css({'padding-bottom':'10px','vertical-align':'top','font-family':'arial'});printDiv('cdis-diff');return false;"><?=$lang['scheduling_35']?></button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="boldish" scope="row">
                            Most common uses
                        </th>
                        <td>
                            <ul>
                                <li>Real-time data collection</li>
                                <li>Prospective clinical studies/trials</li>
                                <li>Longitudinal and/or multi-arm studies</li>
                            </ul>
                        </td>
                        <td>
                            <ul>
                                <li>Registries</li>
                                <li>Prospective or retrospective clinical studies/trials</li>
                                <li>Searching for specific lab values or diagnosis codes for a cohort of patients over a set time period</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th class="boldish" scope="row">
                            Data mapping to EHR fields
                        </th>
                        <td>
                            <ul>
                                <li>Field mapping must be set up prior to data pull by a user with CDP Setup/Mapping privileges in the project.
                                    This is completed via the CDP mapping page (accessed via the Project Setup page).</li>
                                <li>Mapping can be adjusted at any time in a CDP project, and it can be complex when mapping EHR fields to REDCap fields
                                    (allows for one-to-many, many-to-one, or many-to-many mapping).</li>
                                <li>Temporal data (e.g., vital signs and labs) must have an accompanying date or date/time field (e.g., visit date) for determining the
                                    window of time in which to pull data (using the ± day offset). Temporal data can be mapped to fields in a classic project,
                                    to events in a longitudinal project, or to repeating instruments/events.</li>
                                <li>All values for Allergies, Medications, and Problem List will be merged together for each category and each saved in its own
                                    a Notes/Paragraph field (if mapped).</li>
                            </ul>
                        </td>
                        <td>
                            <ul>
                                <li>Mapping is not required since the project structure/instruments are pre-defined when the project is created. Demographics is created
                                    as a single data collection form, and the following forms are created as repeating instruments: Vital Signs, Labs,
                                    Allergies, Medications, and Problem List. Each data value on the repeating instruments are represented as a separate repeating instance
                                    of the form.</li>
                                <li>User defines the data pull configuration when creating the project - e.g., chooses specific MRNs, date range, and data fields from the EHR.</li>
                                <li>Project-level settings control whether or not users in the project can 1) fetch data just one time or as often as they wish, and
                                    2) modify the data pull configuration or not. These settings may be changed only by a REDCap administrator.</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th class="boldish" scope="row">
                            Activation process
                        </th>
                        <td>
                            <ul>
                                <li>The local institution may have a formal process to evaluate the users/project prior
                                    to approval (recommended) - e.g., check IRB status, check users' EHR access.</li>
                                <li>REDCap administrator must enable CDP for the project on the project's Project Setup page.</li>
                            </ul>
                        </td>
                        <td>
                            <ul>
                                <li>The local institution may have a formal process to evaluate the users/project prior
                                    to approval (recommended) - e.g., check IRB status, check users' EHR access.</li>
                                <li>Project is first created by a user, but each revision of the data pull configuration will go through an audit process
                                    and approved by a REDCap administrator via the To-Do List (if the project-level setting has been enabled to allow configuration changes).</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th class="boldish" scope="row">
                            User privileges
                        </th>
                        <td>
                            <ul>
                                <li>Project users can set up field mapping and adjudicate data from the EHR if they have project-level rights to do so. In order to adjudicate data
                                    from the EHR, users must have access to the EHR and must have launched at least one patient in the REDCap window inside the
                                    EHR user interface.</li>
                                <li>REDCap administrator and team can optionally create a User Access Web Service to further control user access during adjudication
                                    (info documented on this page).</li>
                            </ul>
                        </td>
                        <td>
                            <ul>
                                <li>A user's REDCap account must be given Data Mart privileges by a REDCap administrator on the Browse Users page in the Control Center,
                                    after which the user will be able to create a Data Mart project and pull EHR data.
                                    (Note: This is not a project-level user right but a REDCap user account privilege.) Also,
                                    there is no optional User Access Web Service as there is with CDP to further control user access for pulling data.</li>
                                <li>In order to pull data from the EHR, users must have access to the EHR and must have launched at least one patient in the
                                    REDCap window inside the EHR user interface.</li>
                                <li>Users with Project Setup/Design rights in a Data Mart project will be able to request changes to the data pull configuration
                                    (if needed and if the project-level setting has been enabled).</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th class="boldish" scope="row">
                            Usage
                        </th>
                        <td>
                            <ul>
                                <li>Users must launch a patient in the REDCap window inside the EHR user interface, and will be able to add the patient to any
                                    CDP-enabled REDCap project to which they have access. Once the patient is in a project, the user can manually pull data from the EHR for the patient.</li>
                                <li>Data pulled from the EHR is not saved immediately in the project but is stored temporarily in a cache, in which users must first review/adjudicate
                                    all data values before being saved in the project.</li>
                                <li>Once a patient has been added to a project, CDP will automatically (via a cron job) continue to look for any new data added
                                    to the EHR for up to X days, in which X
                                    is the value of the setting "Time of inactivity after which REDCap will stop checking for new data" (info documented on this page).</li>
                            </ul>
                        </td>
                        <td>
                            <ul>
                                <li>Data Mart will only pull data from the EHR when a user with appropriate privileges clicks the "Fetch clinical data" button.
                                    There is no cron job to pull any new data at other times automatically.</li>
                                <li>To pull new data values in the EHR,
                                    a user must manually click the Fetch button again (assuming the project-level setting is enabled to allow more than one data pull).</li>
                                <li>Extra instruments or events may be added to the Data Mart Project, but if any of the pre-defined fields or instruments are modified,
                                    it may prevent the data pull from working successfully thereafter.</li>
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </td>
</tr>


<tr>
    <td class="cc_label" style="border-top:1px solid #ccc;color:#C00000;padding-top:2px;" colspan="2"></td>
</tr>
<tr>
	<td class="cc_label">
		<?php echo $lang['ws_214'] ?>
		<div class="cc_info" style="margin-bottom:20px;">
			<?php echo $lang['ws_235'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field' style='width:150px;' type='text' name='fhir_source_system_custom_name' value='<?php echo htmlspecialchars($element_data['fhir_source_system_custom_name'], ENT_QUOTES) ?>' /><br/>
		<div class="cc_info">
			e.g., Epic, Cerner, EMR, EDW
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label" style="font-weight:normal;border-top:1px solid #ccc;" colspan="2">
		<div style="margin-bottom:10px;font-weight:bold;color:#C00000;"><?php echo $lang['ws_237'] ?></div>
		<div style="margin-bottom:10px;"><?php echo $lang['ws_238'] ?></div>
		<b><?php echo $lang['ws_239'] ?></b>
		<input id="redirectURL" value="<?php echo APP_PATH_WEBROOT_FULL ?>ehr.php" onclick="this.select();" readonly="readonly" class="staticInput" style="width:80%;max-width:400px;margin-bottom:5px;margin-right:5px;">
		<button class="btn btn-defaultrc btn-xs btn-clipboard" title="Copy to clipboard" onclick="return false;" data-clipboard-target="#redirectURL" style="padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>
	</td>
</tr>

<tr>
	<td class="cc_label" style="border-top:1px solid #ccc;color:#C00000;" colspan="2">
		<?php echo $lang['ws_234'] ?>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_219'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_220'] ?>
		</div>
	</td>
	<td class="cc_data">
		<table style="width:100%;">
			<tr>
				<td style='color:#800000;padding-bottom:5px;font-weight:bold;' class='nowrap'><?php print $lang['ws_221'] ?></td>
				<td style='padding-bottom:5px;'>
					<input class='x-form-text x-form-field' style='width:320px;' autocomplete='new-password' type='text' name='fhir_client_id' value='<?php echo htmlspecialchars($element_data['fhir_client_id'], ENT_QUOTES) ?>' />
				</td>
			</tr>
			<tr>
				<td style='color:#800000;font-weight:bold;' class='nowrap'><?php print $lang['ws_222'] ?> &nbsp;</td>
				<td>
					<input class='x-form-text x-form-field' style='width:220px;' autocomplete='new-password' type='password' name='fhir_client_secret' value='<?php echo htmlspecialchars($element_data['fhir_client_secret'], ENT_QUOTES) ?>' />
					<a href="javascript:;" class="cclink" style="text-decoration:underline;font-size:7pt;margin-left:5px;" onclick="$(this).remove();showPasswordField('fhir_client_secret');"><?php print $lang['ws_223'] ?></a>
				</td>
			</tr>
		</table>
		<div class="cc_info" style="margin-top:15px;">
			<?php echo $lang['ws_232'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_311'] ?>
        <div class="cc_info">
            <span><?php echo $lang['ws_315'] ?></span>
        </div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:380px;" name="fhir_standalone_authentication_flow">
            <option value="" <?php echo ($element_data['fhir_standalone_authentication_flow'] == '') ? "selected" : "" ?>><?php echo $lang['ws_316'] ?></option>
            <option value="<?php echo FhirEhr::AUTHENTICATION_FLOW_STANDARD?>" <?php echo ($element_data['fhir_standalone_authentication_flow'] == FhirEhr::AUTHENTICATION_FLOW_STANDARD) ? "selected" : "" ?>><?php echo $lang['ws_321'] ?></option>
			<option value="<?php echo FhirEhr::AUTHENTICATION_FLOW_CLIENT_CREDENTIALS?>" <?php echo ($element_data['fhir_standalone_authentication_flow'] == FhirEhr::AUTHENTICATION_FLOW_CLIENT_CREDENTIALS) ? "selected" : "" ?>><?php echo $lang['ws_322'] ?></option>
		</select>
        <div class="cc_info">
            <span><?php echo $lang['ws_323'] ?></span>
        </div>
	</td>
</tr>

<tr>
	<td class="cc_label" colspan="2" style="padding:20px 10px;">
		<?php echo $lang['ws_224'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_225']."<br>".$lang['ws_260'] ?>
		</div>
		
		<table style="max-width: 92%;margin-left: 35px;">
			<tr>
				<td style='color:#800000;padding-bottom:10px;padding-top:5px;' class='nowrap'><?php print $lang['ws_228'] ?></td>
				<td style='padding-bottom:10px;padding-top:5px;'>
					<input class='x-form-text x-form-field ' style='width:450px;' type='text' id='fhir_endpoint_base_url' name='fhir_endpoint_base_url' value='<?php echo htmlspecialchars($element_data['fhir_endpoint_base_url'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
				</td>
			</tr>
			<tr>
				<td colspan='2'>
					<div class="cc_info">
						<?php echo $lang['ws_229'] ?> &nbsp;
						<button class="jqbuttonmed" style="color:#0101bb;font-size: 11px;top: 3px;" onclick="autoFindFhirUrls();return false;"><?php echo $lang['ws_231'] ?></button>
					</div>
				</td>
			</tr>
			<tr>
				<td style='color:#800000;padding-bottom:5px;padding-top:10px;' class='nowrap'><?php print $lang['ws_226'] ?></td>
				<td style='padding-bottom:5px;padding-top:10px;'>
					<input class='x-form-text x-form-field ' style='width:450px;' type='text' id='fhir_endpoint_token_url' name='fhir_endpoint_token_url' value='<?php echo htmlspecialchars($element_data['fhir_endpoint_token_url'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
				</td>
			</tr>
			<tr>
				<td style='color:#800000;padding-bottom:5px;' class='nowrap'><?php print $lang['ws_227'] ?></td>
				<td style='padding-bottom:5px;'>
					<input class='x-form-text x-form-field ' style='width:450px;' type='text' id='fhir_endpoint_authorize_url' name='fhir_endpoint_authorize_url' value='<?php echo htmlspecialchars($element_data['fhir_endpoint_authorize_url'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
				</td>
			</tr>
		</table>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_217'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_218'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field' style='width:350px;' type='text' name='fhir_ehr_mrn_identifier' value='<?php echo htmlspecialchars($element_data['fhir_ehr_mrn_identifier'], ENT_QUOTES) ?>' /><br/>
		<div class="cc_info">
			e.g., urn:oid:1.2.840.114350.1.13.478.3.7.5.737384.14<br>
			e.g., urn:oid:1.1.1.1.1.1
			<?php print $blade->run('control-center.cdis.string-identifier-helper') ?>
		</div>
        <div class="cc_info" style="color:#0101bb;margin-top:15px;">
            Note for Epic customers: This is the HL7 Root item in the Epic ID Type Record (IIT) specified
            in the Patient ID Type field of the Integration Configuration Record (FDI).
        </div>
	</td>
</tr>

<?php
	function renderBreakTheGlassSettings($blade)
	{

		$ehr_user_types = array(
			GlassBreaker::USER_INTERNAL,
			GlassBreaker::USER_EXTERNAL,
			GlassBreaker::USER_EXTERNALKEY,
			GlassBreaker::USER_CID,
			GlassBreaker::USER_NAME,
			GlassBreaker::USER_SYSTEMLOGIN,
			GlassBreaker::USER_ALIAS,
			GlassBreaker::USER_IIT,
		);
		print $blade->run('control-center.glass-breaker.settings', compact('ehr_user_types'));
	};
	renderBreakTheGlassSettings($blade);
?>


<tr>
	<td class="cc_label" style="border-top:1px solid #ccc;color:#C00000;" colspan="2">
		<?php echo $lang['ws_233'] ?>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_74'] ?>
		<div class="cc_info">
			<?php echo $lang['ws_274'] . " " . $lang['ws_230'] ?>
		</div>
	</td>
	<td class="cc_data">
		<input class='x-form-text x-form-field ' style='width:300px;' type='text' id='fhir_url_user_access' name='fhir_url_user_access' value='<?php echo htmlspecialchars($element_data['fhir_url_user_access'], ENT_QUOTES) ?>' onblur="validateUrl(this);">
		<button class="jqbuttonmed" onclick="setupTestUrl( $('#fhir_url_user_access') );return false;"><?php echo $lang['edit_project_138'] ?></button><br>
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
			<?php echo $lang['ws_269'] ?>
		</div>
	</td>
	<td class="cc_data">
		<textarea style='height:60px;' class='x-form-field notesbox' name='fhir_custom_text' id='fhir_custom_text'><?php echo $element_data['fhir_custom_text'] ?></textarea>
		<div id='fhir_custom_text-expand' style='text-align:right;'>
			<a href='javascript:;' style='font-weight:normal;text-decoration:none;color:#999;font-family:tahoma;font-size:10px;'
				onclick="growTextarea('fhir_custom_text')"><?php echo $lang['form_renderer_19'] ?></a>&nbsp;
		</div>
		<div class="cc_info">
			<?php echo $lang['system_config_195'] ?>
		</div>
		<div class="cc_info">
			<?php echo $lang['ws_71'] . RCView::br() . RCView::span(array('style'=>'color:#C00000;'), "\"{$lang['ws_268']}\"") ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_270'] ?>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_display_info_project_setup">
			<option value='0' <?php echo ($element_data['fhir_display_info_project_setup'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_272'] ?></option>
			<option value='1' <?php echo ($element_data['fhir_display_info_project_setup'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_271'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['ws_273'] ?>
		</div>
	</td>
</tr>

<tr>
	<td class="cc_label">
		<?php echo $lang['ws_275'] ?>
		<div class="cc_info" style="color:#C00000;">
			<?php echo $lang['ws_99'] ?>
		</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_user_rights_super_users_only">
			<option value='0' <?php echo ($element_data['fhir_user_rights_super_users_only'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_276'] ?></option>
			<option value='1' <?php echo ($element_data['fhir_user_rights_super_users_only'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_277'] ?></option>
		</select>
		<div class="cc_info">
			<?php echo $lang['ws_278'] ?>
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
		<input class='x-form-text x-form-field' type='text' style='width:35px;' maxlength='3' onblur="redcap_validate(this,'1','999','hard','int');"  name='fhir_data_fetch_interval' value='<?php echo htmlspecialchars($element_data['fhir_data_fetch_interval'], ENT_QUOTES) ?>' />
		<span class="cc_info" style="font-weight:bold;color:#000;">
			<?php echo $lang['control_center_406'] ?>
		</span>
		<span class="cc_info" style="margin-left:20px;">
			<?php echo $lang['ws_88'] ?>
		</span>
		<div class="cc_info">
			<?php echo $lang['ws_279'] ?>
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
		<input class='x-form-text x-form-field' type='text' style='width:35px;' maxlength='3' onblur="redcap_validate(this,'1','100','hard','int');" name='fhir_stop_fetch_inactivity_days' value='<?php echo htmlspecialchars($element_data['fhir_stop_fetch_inactivity_days'], ENT_QUOTES) ?>' />
		<span class="cc_info" style="font-weight:bold;color:#000;">
			<?php echo $lang['scheduling_25'] ?>
		</span>
		<span class="cc_info" style="margin-left:20px;">
			<?php echo $lang['ws_89'] ?>
		</span>
		<div class="cc_info">
			<?php echo $lang['ws_280'] ?>
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
		<select class="x-form-text x-form-field" style="max-width:360px;" name="fhir_convert_timestamp_from_gmt">
			<option value='0' <?php echo ($element_data['fhir_convert_timestamp_from_gmt'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_254'] ?></option>
			<option value='1' <?php echo ($element_data['fhir_convert_timestamp_from_gmt'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_253'] ?></option>
		</select>
		<div class="cc_info" style="color:#C00000;">
			<?php echo $lang['ws_256'] ?>
		</div>
	</td>
</tr>

<tr>
    <td class="cc_label">
        <?php echo $lang['ws_299'] ?>
        <div class="cc_info">
            <?php echo $lang['ws_302'] ?>
        </div>
    </td>
    <td class="cc_data">
        <select class="x-form-text x-form-field" style="max-width:360px;" name="fhir_include_email_address">
            <option value='0' <?php echo ($element_data['fhir_include_email_address'] == 0) ? "selected" : "" ?>><?php echo $lang['ws_301'] ?></option>
            <option value='1' <?php echo ($element_data['fhir_include_email_address'] == 1) ? "selected" : "" ?>><?php echo $lang['ws_300'] ?></option>
        </select>
    </td>
</tr>

</table><br/>
<div style="text-align: center;"><input type='submit' name='' value='Save Changes' /></div><br/>
</form>

<?php loadJS('Libraries/clipboard.js'); ?>
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
// Auto-find the FHIR authorize and token URLs using base URL
var foundFhirUrls = false;
var metaurl, tokenUrl, authorizeUrl;
function autoFindFhirUrls() {
	foundFhirUrls = false;
	$('#fhir_endpoint_base_url').val().trim();
	var url = $('#fhir_endpoint_base_url').val().replace(/\/$/, "");
	if (url == '') {
		simpleDialog('Please enter a value for the base URL endpoint first', 'ERROR');
		return;
	}
	var k = 0;
	// Start "working..." progress bar
	showProgress(1,0);
	// Loop through URL and sub-URLs till we find the right metadata path
	while (k < 25 && foundFhirUrls === false) {		
		if (url == '' || url == 'https:/' || url == 'http:/' || url == 'https:' || url == 'http:') {
			break;
		}
		// Do ajax request to test the URL
		var thisAjax = $.ajax({
			url: '<?php echo PAGE_FULL ?>',
			type: 'POST',
			data: { url: url+"/metadata", redcap_csrf_token: redcap_csrf_token },
			async: false,
			success:
				function(data){
					if (data != '0') foundFhirUrls = data;
					metaurl = url+"/metadata";
				}
		});
		// Prep for the next loop
		url = dirname(url);
		k++;
	}
	showProgress(0,0);
	if (foundFhirUrls !== false) {
		var urls = foundFhirUrls.split("\n");
		authorizeUrl = urls[0];
		tokenUrl = urls[1];
		simpleDialog("The FHIR URLs below for your Authorize endpoint and Token endpoint were found from the FHIR Conformance Statement (<i>"+metaurl+"</i>). "
			+ "You may copy these URLs into their corresponding text boxes on this page."
			+ "<div style='font-size:13px;padding:20px 0 5px;color:green;'>Token endpoint: &nbsp;<b>"+tokenUrl+"</b></div>"
			+ "<div style='font-size:13px;padding:5px 0;color:green;'>Authorize endpoint: &nbsp;<b>"+authorizeUrl+"</b></div>",
			"<img src='"+app_path_images+"tick.png' style='vertical-align:middle;'> <span style='color:green;vertical-align:middle;'>Success!</span>",null,600,null,'Close',function(){
				$('#fhir_endpoint_authorize_url').val(authorizeUrl).effect('highlight',{},3000);
				$('#fhir_endpoint_token_url').val(tokenUrl).effect('highlight',{},3000);
			},'Copy');
	} else {
		simpleDialog("The FHIR Conformance Statement that contains the values of the URLs for your FHIR Authorize endpoint and FHIR Token endpoint could not found under your FHIR base URL nor under any higher-level directories. "
			+ "You should consult your EHR's technical team to determine these two FHIR endpoints. The DDP on FHIR function cannot work successfully without these URLs being set.", "<img src='"+app_path_images+"cross.png' style='vertical-align:middle;'> <span style='color:#C00000;vertical-align:middle;'>Failed to find FHIR Conformance Statement</span>");
	}
}
// Copy the public survey URL to the user's clipboard
function copyUrlToClipboard(ob) {
	// Create progress element that says "Copied!" when clicked
	var rndm = Math.random()+"";
	var copyid = 'clip'+rndm.replace('.','');
	var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
	$(ob).after(clipSaveHtml);
	$('#'+copyid).toggle('fade','fast');
	setTimeout(function(){
		$('#'+copyid).toggle('fade','fast',function(){
			$('#'+copyid).remove();
		});
	},2000);
}
// Copy-to-clipboard action
var clipboard = new Clipboard('.btn-clipboard');
$(function(){
	// Copy-to-clipboard action
	$('.btn-clipboard').click(function(){
		copyUrlToClipboard(this);
	});
});
</script>


<?php include 'footer.php'; ?>