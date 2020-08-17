<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

## MOVE TO PROD & SET TO INACTIVE/BACK TO PROD
// Set up status-specific language and actions
$status_change_btn   = $lang['edit_project_07'];
$status_change_text  = $lang['edit_project_08'];
$status_dialog_title = $lang['edit_project_09'];
$status_dialog_btn 	 = $lang['edit_project_166'];
$type = isset($_GET['type']) ? preg_replace("/[^0-9a-zA-Z_-]/", "", $_GET['type']) : '';
$user_email = isset($_GET['user_email']) ? $_GET['user_email'] : '';
$status_dialog_btn_action = "doChangeStatus(0,'{$type}','".RCView::escape($user_email)."');";
$status_dialog_text  = $lang['edit_project_11'];
switch ($status) {
	case 0: // Development
		break;
	case 1: // Production
		$status_change_btn   = $lang['edit_project_201'];
		$status_change_text  = $lang['edit_project_195'];
		$status_dialog_title = $lang['edit_project_196'];
		$status_dialog_btn 	 = $lang['edit_project_202'];
		$status_dialog_text  = $lang['edit_project_197'];
		break;
	case 2: // Inactive
		$status_change_btn   = $lang['edit_project_205'];
		break;
}

$otherFuncTable = '';


## Status Management
$otherFuncTable .= "<div class='round chklist' style='padding:15px 20px 5px;'>" .
					RCView::div(array('class' => 'chklisthdr'), $lang['edit_project_210']) .
					"<table class='proj-setup-table'>";

// Status icons
$textcurrent0 = $textcurrent1 = $textcurrent2 = '';
if ($status == '0') {
	$status0icon = 'far fa-dot-circle';
	$status1icon = 'far fa-circle';
	$status2icon = 'far fa-circle';
	$status0color = '';
	$status1color = 'text-muted-more';
	$status2color = 'text-muted-more';
	$textcurrent0 = RCView::div(array('class'=>'text-info fs15'), $lang['rev_history_05']);
} elseif ($status == '1') {
	$status0icon = 'far fa-check-circle';
	$status1icon = 'far fa-dot-circle';
	$status2icon = 'far fa-circle';
	$status0color = 'text-success';
	$status1color = '';
	$status2color = 'text-muted-more';
	$textcurrent1 = RCView::div(array('class'=>'text-info fs15'), $lang['rev_history_05']);
} else {
	$status0icon = 'far fa-check-circle';
	$status1icon = 'far fa-check-circle';
	$status2icon = 'far fa-dot-circle';
	$status0color = 'text-success';
	$status1color = 'text-success';
	$status2color = '';
	$textcurrent2 = RCView::div(array('class'=>'text-info fs15'), $lang['rev_history_05']);
}
$otherFuncTable .= RCView::div(array('class'=>'text-center my-4 fs14'),
                        RCView::div(array('class'=>'clearfix font-weight-bold w-75 d-inline-block'),
                        RCView::div(array('class'=>'float-left text-center '.$status0color, 'style'=>'width:20%;'),
                            "<i class='$status0icon fa-5x'></i><div class='mt-1'>{$lang['global_29']}</div>$textcurrent0"
                        ) .
                        RCView::div(array('class'=>'float-left text-center text-muted-more', 'style'=>'width:20%;'),
                            '<i class="fas fa-long-arrow-alt-right fa-5x"></i>'
                        ) .
                        RCView::div(array('class'=>'float-left text-center '.$status1color, 'style'=>'width:20%;'),
                            "<i class='$status1icon fa-5x'></i><div class='mt-1'>{$lang['global_30']}</div>$textcurrent1"
                        ) .
                        RCView::div(array('class'=>'float-left text-center text-muted-more', 'style'=>'width:20%;'),
                            '<i class="fas fa-long-arrow-alt-right fa-5x"></i>'
                        ) .
                        RCView::div(array('class'=>'float-left text-center '.$status2color, 'style'=>'width:20%;'),
                            "<i class='$status2icon fa-5x'></i><div class='mt-1'>{$lang['global_159']}</div>$textcurrent2"
                        )
                      )
);

if ($status > 0)
{
    // JS to execute when click button
	$movetoanalysis_js = ($status != '1' || SUPER_USER || $survey_pid_move_to_analysis_status == '') ? 'btnMoveToProd();' : "openSurveyDialogIframe('".Survey::getProjectStatusPublicSurveyLink('survey_pid_move_to_analysis_status')."');";
	// If Inactive/Archived, set back to production
	$otherFuncTable .= "<tr>
							<td valign='top'>
								<button class='btn btn-sm fs13 nowrap text-left btn-primaryrc' style='line-height:1.2;' onclick=\"$movetoanalysis_js\">
									".($status == '1' ? "" : "<i class=\"fas fa-arrow-left\"></i>")."
									$status_change_btn
									".($status == '1' ? "<i class=\"fas fa-arrow-right\"></i>" : "")."
								</button>
							</td>
							<td valign='top'>$status_change_text</td>
						</tr>";
}

// If in production, MOVE BACK TO DEVELOPMENT (super users only)
if ($super_user && $status == '1')
{
	// Set flag if using DTS. If so, don't allow to move back to dev because it will break DTS mapping
	$usingDTS = $dts_enabled ? '1' : '0';
	// Display table row
	$otherFuncTable .= "<tr>
								<td valign='top'>
									<button class='btn btn-sm btn-primaryrc fs13 nowrap text-left' style='line-height: 1.2;' onclick='MoveToDev($draft_mode,$usingDTS)'>
										<i class=\"fas fa-arrow-left\"></i> {$lang['edit_project_79']}
									</button>
								</td>
								<td valign='top'>
									{$lang['edit_project_80']} ";
	if ($draft_mode > 0) {
		$otherFuncTable .= "<span style='color:#C00000;'><i class='fas fa-exclamation-triangle'></i> {$lang['edit_project_81']}</span> ";
	}
	$otherFuncTable .= "		<b>{$lang['edit_project_77']}</b></td>
							</tr>";
}
// Display option to mark the project as "completed"
if ($completed_time == '')
{
	// JS to execute when click button
	$completedJS = (SUPER_USER || $survey_pid_mark_completed == '') ? 'markProjectAsCompleted();' : "openSurveyDialogIframe('".Survey::getProjectStatusPublicSurveyLink('survey_pid_mark_completed')."');";
    $otherFuncTable .= "<tr>
							<td valign='top'>
								<button class='btn btn-sm btn-info fs13 nowrap' onclick=\"$completedJS\">
									<span class='fa fa-archive'></span>
									{$lang['edit_project_203']}
								</button>
							</td>
							<td valign='top'>
								{$lang['edit_project_204']} 
								<a href='javascript:;' style='text-decoration: underline;' data-toggle=\"popover\" data-placement=\"bottom\" data-trigger=\"hover\" data-content=\"".htmlspecialchars($lang['edit_project_212'], ENT_QUOTES)."\" data-title=\"".htmlspecialchars($lang['edit_project_203'], ENT_QUOTES)."\">{$lang['scheduling_78']}</a>
				            </td>
						</tr>";
	$otherFuncTable .= "<script type='text/javascript'>
                            function markProjectAsCompleted() {
                                $('#completed_time_dialog').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
                                    '".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
                                    '".js_escape($lang['edit_project_203'])."': function() { doChangeStatus(1,'','') }
                                } });
                            }
                        </script>";
}




$otherFuncTable .= "</table>
                </div>
                <div class='round chklist' style='padding:15px 20px 5px;'>" .
                RCView::div(array('class' => 'chklisthdr delete-target'), $lang['edit_project_211']) .
                "<table class='proj-setup-table'>";

// Display REQUEST DELETE option
if ($status > 0 && !$super_user) {
	$db = new RedCapDB();
	$userInfo = $db->getUserInfoByUsername($userid);
	$ui_id = $userInfo->ui_id;
	$todo_type = 'delete project';
	// print $ui_id;
	$delBtnTxt = ($super_user || defined("AUTOMATE_ALL") ? 'control_center_105' : 'control_center_4532');
	$request_count = ToDoList::checkIfRequestExist($project_id, $ui_id, $todo_type);
	if($request_count > 0){
		$otherFuncTable .= "<tr id='row_delete_project'>
									<td valign='top'>
										<button class='btn btn-sm btn-danger fs13 nowrap'>
											<i class=\"fas fa-times\" style='vertical-align:middle;'></i>
											<span style='vertical-align:middle;margin:0 2px;'>{$lang[$delBtnTxt]}</span>
										</button>
									</td>
									<td valign='top'>
										<b style='display:block;color:#C00000;'>{$lang['edit_project_179']} <button class='jqbuttonmed nowrap' onclick=\"cancelRequest(pid,'delete project',".$ui_id.")\" class='cancel-delete-req-btn'>{$lang['global_128']}</button></b>
										{$lang['edit_project_50']}";
	}else{
		$otherFuncTable .= "<tr id='row_delete_project'>
			<td valign='top'>
			<button class='btn btn-sm btn-danger fs13 nowrap' onclick=\"delete_project(pid,this,".$super_user.",".$status.")\">
                <i class=\"fas fa-times\"></i>
                {$lang[$delBtnTxt]}
			</button>
			</td>
			<td valign='top'>
			{$lang['edit_project_50']}";
	}
	if (!$super_user && $status < 1) {
		$otherFuncTable .=  	" {$lang['edit_project_78']}";
	}
	$otherFuncTable .= "	</td>
							</tr>";
}

if ($status < 1 || UserRights::isSuperUserNotImpersonator())
{
	// Display option to DELETE the project (ONLY if in development)
	// $delBtnTxt = ($super_user ? 'control_center_105' : 'control_center_4532');
	$otherFuncTable .= "<tr id='row_delete_project'>
							<td valign='top'>
								<button class='btn btn-sm btn-danger fs13 nowrap' onclick=\"delete_project(pid,this,".$super_user.",".$status.")\">
									<i class=\"fas fa-times\"></i>
									{$lang['control_center_105']}
								</button>
							</td>
							<td valign='top'>
								{$lang['edit_project_50']}";
	if (!UserRights::isSuperUserNotImpersonator() && $status < 1) {
		$otherFuncTable .=  	" {$lang['edit_project_78']}";
	} elseif ($status > 0) {
		$otherFuncTable .=  	" <b>{$lang['edit_project_77']}</b>";
	}
	$otherFuncTable .= "	</td>
						</tr>";
	// Display option to ERASE all data in the project (ONLY if in development)
	$otherFuncTable .= "<tr id='row_erase'>
							<td valign='top'>
								<button class='btn btn-sm btn-rcred btn-rcred fs13 nowrap' onclick=\"
									$('#erase_dialog').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
										'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
										'".js_escape($lang['edit_project_147'])."': function() {
											showProgress(1);
											$.post(app_path_webroot+'ProjectGeneral/erase_project_data.php?pid='+pid, { action: 'erase_data' },
												function(data) {
													showProgress(0,0);
													$('#erase_dialog').dialog('close');
													if (data == '1') {
														simpleDialog('".js_escape($lang['edit_project_31'])."','".js_escape($lang['global_79'])."');
													} else {
														alert(woops);
													}
												}
											);
										}
									} });
								\">
									<i class=\"fas fa-backspace\"></i>
									{$lang['edit_project_147']}
								</button>
							</td>
							<td valign='top'>
								{$lang['edit_project_169']}";
	if (!UserRights::isSuperUserNotImpersonator() && $status < 1) {
		$otherFuncTable .=  	" {$lang['edit_project_78']}";
	} elseif ($status > 0) {
		$otherFuncTable .=  	" <b>{$lang['edit_project_77']}</b>";
	}
	$otherFuncTable .= "
							</td>
						</tr>";
}

// Clear the record list cache (admins only)
if (UserRights::isSuperUserNotImpersonator())
{
	$otherFuncTable .= "<tr>
							<td valign='top'>
								<button class='btn btn-sm btn-defaultrc fs13 nowrap' onclick=\"clearRecordCache()\">
									<i class=\"fas fa-broom\" style='padding-right:2px;'></i>{$lang['edit_project_213']}
								</button>
							</td>
							<td valign='top'>
								{$lang['edit_project_214']}
							</td>
						</tr>";
}

// DDP - Purge unused source data cache
if (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir()))
{
	$otherFuncTable .= "<tr id='row_ddp'>
							<td valign='top'>
								<button id='purgeDdpBtn' class='btn btn-sm btn-defaultrc fs13 nowrap' onclick=\"purgeDDPdata()\">
									<i class=\"fas fa-database\"></i>
									{$lang['edit_project_149']}
								</button>
							</td>
							<td valign='top'>
								{$lang['edit_project_198']}
							</td>
						</tr>";
}

$otherFuncTable .= "</table>
					</div>";


## Copy or back up project
if ($display_project_xml_backup_option || $allow_create_db)
{
	if ($display_project_xml_backup_option && $allow_create_db) {
		$copyBackupText = $lang['edit_project_161'];
	} elseif ($display_project_xml_backup_option && !$allow_create_db) {
		$copyBackupText = $lang['edit_project_162'];
	} else {
		$copyBackupText = $lang['edit_project_175'];
	}
	$otherFuncTable .= "<div class='round chklist' style='padding:15px 20px 0;'>" .
		RCView::div(array('class' => 'chklisthdr copy-bck-target'), ($allow_create_db ? $lang['edit_project_161'] : $lang['edit_project_162'])) .
		"<table class='proj-setup-table'>";
	if ($allow_create_db)
	{
		// COPY project
		$otherFuncTable .= "<tr id='row_copy'>
							<td valign='top'>
								<button class='btn btn-sm btn-rcgreen fs13 nowrap' onclick=\"window.location.href = app_path_webroot+'ProjectGeneral/copy_project_form.php?pid=$project_id'\">
									<i class=\"fas fa-copy\"></i>
									{$lang['edit_project_146']}
								</button>
								</td>
								<td valign='top'>
									<b>{$lang['edit_project_167']}</b>
									{$lang['edit_project_168']}
								</td>
							</tr>";
	}
	// ODM: Export whole project as ODM XML
	if ($display_project_xml_backup_option)
	{
		// Add checkbox options for metadata XML file
		$xmlOptions = "";
		$roles = UserRights::getRoles();
		if (!empty($roles)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='userroles' checked> {$lang['data_export_tool_239']}</div>";
		$dags = $Proj->getGroups();
		if (!empty($dags)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='dags' checked> {$lang['global_22']}</div>";
		$dq = new DataQuality();
		$dq_rules = $dq->getRules();
		foreach ($dq_rules as $key=>$attr) if (!is_numeric($key)) unset($dq_rules[$key]);
		if (!empty($dq_rules)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='dqrules' checked> {$lang['dataqueries_81']}</div>";
		$dashboards = RecordDashboard::getRecordDashboardsList();
		if (!empty($dashboards)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='recorddashboards' checked> {$lang['global_153']}</div>";
		$reports = DataExport::getReportNames(null, false, false, false);
		if (!empty($reports)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='reports' checked> {$lang['app_06']}</div>";
		$alert = new Alerts();
		$alerts = $alert->getAlertSettings();
		if (!empty($alerts)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='alerts' checked> {$lang['global_154']}</div>";
		$report_folders = DataExport::getReportFolders(PROJECT_ID);
		if (!empty($report_folders)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='reportfolders' checked> {$lang['reporting_54']}</div>";
		$DDP = new DynamicDataPull(PROJECT_ID, $Proj->project['realtime_webservice_type']);
		$ddpMappingComplete = ((($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())) && $DDP->isMappingSetUp());
		if ($ddpMappingComplete) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='ddpmapping' checked> {$lang['data_export_tool_241']}</div>";
		if (!empty($Proj->surveys)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='surveys' checked> {$lang['data_export_tool_240']}</div>";
		$sq = Survey::getProjectSurveyQueue(true);
		if (!empty($sq)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='sq' checked> {$lang['survey_505']}</div>";
		$surveyScheduler = new SurveyScheduler(PROJECT_ID);
		$surveyScheduler->setSchedules(true);
		$asi = $surveyScheduler->schedules;
		if (!empty($asi)) $xmlOptions .= "<div class='ml-2 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='asi' checked> {$lang['survey_1239']}</div>";
		if (!empty($asi)) $xmlOptions .= "<div class='ml-4 fs11'><input class='xml_options' style='position:relative;top:2px;' type='checkbox' value='asienable'> {$lang['survey_1240']}</div>";
		if ($xmlOptions != "") $xmlOptions = "<div class='mb-1 fs12' style='color:#A00000;'><b>{$lang['data_export_tool_238']}</b></div>$xmlOptions";
		// Set button options
		$odmDisabled = ($user_rights['data_export_tool'] < 1) ? "disabled" : "";
		$odmDisabledOnclick = ($user_rights['data_export_tool'] < 1) ? "onclick=\"simpleDialog('".js_escape($lang['edit_project_171'])."');\"" : "";
		$odmInstructions = ($user_rights['data_export_tool'] < 1) ? $lang['edit_project_172'] : (($longitudinal ? $lang['data_export_tool_202'] : $lang['data_export_tool_201'])." ".$lang['data_export_tool_203']);
		// Output row
		$otherFuncTable .= "<tr>
								<td valign='top' style='padding-top:25px;'>
									<div style='margin:5px 0 10px;'>
										<button class='btn btn-sm btn-defaultrc fs13 nowrap' onclick=\"window.location.href = app_path_webroot+'ProjectSetup/export_project_odm.php?pid=$project_id'+getOdmMetadataOptions()\">
											<i class=\"fas fa-file-code fs14\"></i>
											{$lang['data_export_tool_215']}
										</button>
									</div>
									<div style='margin:3px 0 10px;' $odmDisabledOnclick>
										<button $odmDisabled class='btn btn-sm btn-defaultrc fs13 nowrap' onclick=\"showExportFormatDialog('ALL',true);\">
											<i class=\"fas fa-file-code fs14\"></i>
											{$lang['data_export_tool_216']}
										</button>
									</div>
									<div style='margin:15px 0 5px;color:#666;'>$xmlOptions</div>
								</td>
								<td valign='top' style='padding-top:25px;'>
									<b>{$lang['data_export_tool_214']}</b>
									$odmInstructions
									<div style='color:#888;font-size:11px;line-height:13px;margin-top:15px;'>{$lang['edit_project_173']}</div>
								</td>
							</tr>";
	}
	$otherFuncTable .= "</table></div>";
}

// API help
if ($api_enabled)
{
	$h = ''; // will hold the HTML to display in API div (all JS is included inline at the bottom)
	$h .= RCView::div(array('class' => 'chklisthdr'), $lang['edit_project_52']);
	$apiHelpLink = RCView::a(array('id' => 'apiHelpBtnId', 'style' => 'text-decoration:underline;', 'href' => '#'),
		$lang['edit_project_142']);
	$h .= RCView::div(array('style' => 'margin:5px 0 0;'),
		$lang['system_config_114'] . ' ' . $apiHelpLink . $lang['period'] . RCView::br() . RCView::br() . $lang['system_config_189']);
	// Display option to erase all API tokens
	if ($super_user)
	{
		$db = new RedCapDB();
		$numtokens = $db->countAPITokensByProject($project_id);
		$numtokenstext = RCView::span(array('style'=>'color:#800000;line-height:22px;'),
			$lang['edit_project_108'] . $lang['colon'] . ' ' .
			RCView::span(array('id' => 'apiTokenCountId'), $numtokens)
		);
		// JS handler for this button is at the bottom
		if ($numtokens > 0) {
			$btn = RCView::button(array('id' => 'apiEraseBtnId', 'class' => 'jqbuttonmed'),
				str_replace(' ', RCView::SP, $lang['edit_project_106']));
			$h .= RCView::table(array('cellspacing' => '12', 'width' => '100%', 'style' => 'border-collapse: collapse; margin-top: 10px;'),
				RCView::tr(array('id' => 'row_token_erase'),
					RCView::td(array('valign' => 'top', 'style' => 'padding: 0px 15px 0px 5px;'), $btn) .
					RCView::td(array('valign' => 'top', 'style' => 'padding: 0px 5px 0px 0px;'),
						$lang['edit_project_107'] . ' ' .
						RCView::b($lang['edit_project_77']) . RCView::br() . $numtokenstext
					)));
		} else {
			$h .= $numtokenstext;
		}
	}
	$otherFuncTable .= RCView::div(array('class' => 'round chklist', 'style' => 'padding:15px 20px;'), $h);
}

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
loadJS('DeletePrompt.js');
// Tabs
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
?>

<!-- Invisible div for purging DDP data cache -->
<div id='purgeDDPdataDialog' title="<?php echo js_escape2(($status <= 1 ? $lang['edit_project_200'] : $lang['edit_project_149'])) ?>" class="simpleDialog">
	<?php echo ($status <= 1 ? RCView::div(array('style'=>'color:#C00000;'), $lang['edit_project_199']) : $lang['edit_project_151']) ?>
</div>

<!-- Invisible div for status change -->
<div id='status_dialog' title='<?php echo js_escape($status_dialog_title) ?>' style='display:none;'>
	<p style=''><?php echo $status_dialog_text ?></p>
</div>
<!--  Invisible div for archiving the project -->
<div id='completed_time_dialog' title='<?php echo js_escape($lang['edit_project_203']) ?>' style='display:none;'>
	<p style=''>
		<?php echo $lang['edit_project_206'] ?>
	</p>
</div>
<!--  Invisible div for erasing all data -->
<div id='erase_dialog' title='<?php echo js_escape($lang['edit_project_36']) ?>' style='display:none;'>
	<p style=''>
		<?php echo $lang['edit_project_37'] ?>
	</p>
</div>
<!--  Invisible div for erasing all API tokens -->
<div id='erase_api_dialog' title='<?php echo js_escape($lang['edit_project_109']) ?>' style='display:none;'>
	<p style=''>
		<?php echo $lang['edit_project_110'] ?>
	</p>
</div>

<script type='text/javascript'>
var langExporting = '<?php print js_escape($lang['report_builder_51']) ?>';
var langQuestionMark = '<?php print js_escape($lang['questionmark']) ?>';
var closeBtnTxt = '<?php print js_escape($lang['global_53']) ?>';
var exportBtnTxt = '<?php print js_escape($lang['report_builder_48']) ?>';
var exportBtnTxt2 = '<?php print js_escape($lang['data_export_tool_199']." ".$lang['data_export_tool_209']) ?>';
var langSaveValidate = '<?php print js_escape($lang['report_builder_52']) ?>';
var langIconSaveProgress = '<?php print js_escape($lang['report_builder_55']) ?>';
var langIconSaveProgress2 = '<?php print js_escape($lang['report_builder_56']) ?>';
var langCancel = '<?php print js_escape($lang['global_53']) ?>';
var langError = '<?php print js_escape($lang['global_01']) ?>';
var langExportFailed = '<?php print js_escape($lang['report_builder_129']) ?>';
var langExportWholeProject = '<?php print js_escape($lang['data_export_tool_208']) ?>';
var langDeletedSuccess = '<?php print js_escape($lang['design_719']) ?>';
var max_live_filters = <?php print DataExport::MAX_LIVE_FILTERS ?>;
var langIconSaveProgress = '<?php print js_escape($lang['report_builder_55']) ?>';
var langIconSaveProgress2 = '<?php print js_escape($lang['report_builder_56']) ?>';
var langIconSaveProgress3 = '<?php print js_escape($lang['report_builder_147']) ?>';
$(function() {
    $('[data-toggle="popover"]').popover();
	$("#apiHelpBtnId").click(function() {
		window.location.href='<?php echo APP_PATH_WEBROOT_PARENT; ?>api/help/';
	});
	$("#apiEraseBtnId").click(function() {
		if ($('#apiTokenCountId').html() == '0') {
			alert('There are no tokens to delete because no API tokens have been created yet.');
			return;
		}
		$('#erase_api_dialog').dialog(
			{ bgiframe: true, modal: true, width: 500,
				buttons: {
					Cancel: function() { $(this).dialog('close'); },
					'<?php echo js_escape($lang['edit_project_106']) ?>':
					function() {
						$.get(app_path_webroot + 'ControlCenter/user_api_ajax.php',
							{ action: 'deleteProjectTokens', api_pid: '<?php echo $project_id; ?>'},
							function(data) {
								alert(data);
								$.get(app_path_webroot + 'ControlCenter/user_api_ajax.php',
									{ action: 'countProjectTokens', api_pid: '<?php echo $project_id; ?>'},
									function(data) { $("#apiTokenCountId").html(data); }
								);
							}
						);
						$(this).dialog('close');
					}
				}
			}
		);
	});
});
function clearRecordCache()
{
    $.post(app_path_webroot+'index.php?pid='+pid+'&route=DataEntryController:clearRecordListCache', { }, function(data){
        if (data != '1') {
            alert(woops);
            return;
        }
        Swal.fire(
            '<?php echo js_escape($lang['setup_08']) ?>', '<?php echo js_escape($lang['edit_project_215']) ?>', 'success'
        );
    });
}
function btnMoveToProd() {
    $('#status_dialog').dialog({ bgiframe: true, modal: true, width: 650, buttons: {
            '<?php echo js_escape($lang['global_53']) ?>': function() { $(this).dialog('close'); },
            '<?php echo js_escape($status_dialog_btn) ?>': function() { <?php echo $status_dialog_btn_action ?> }
        } });
}
function MoveToDev(draft_mode,usingDTS) {
	if (usingDTS) {
		alert('<?php echo js_escape($lang['edit_project_84']) . '\n\n' . js_escape($lang['edit_project_85']) ?>');
		return;
	}
	var msg = '<?php echo js_escape($lang['edit_project_82']) ?>';
	if (draft_mode > 0) {
		msg += ' <?php echo js_escape($lang['edit_project_83']) ?>';
	}
	if (confirm(msg)) {
		$.post(app_path_webroot+'ProjectGeneral/change_project_status.php?pid='+pid, { moveToDev: 1 }, function(data){
			if (data=='1') {
				window.location.href = app_path_webroot+'ProjectSetup/index.php?msg=movetodev&pid='+pid;
			} else {
				alert(woops);
			}
		});
	}
}
// Purge DDP data (with confirmation popup)
function purgeDDPdata() {
	var purgeLangClose  = (status <= 1 ? '<?php echo js_escape($lang['calendar_popup_01']) ?>' : '<?php echo js_escape($lang['global_53']) ?>');
	var purgeLangRemove = (status <= 1 ? null : '<?php echo js_escape($lang['scheduling_57']) ?>');
	var purgeFuncRemove = (status <= 1 ? null : (function(){
		$.post(app_path_webroot+'DynamicDataPull/purge_cache.php?pid='+pid,{ },function(data){
			if (data != '1') {
				alert(woops);
			} else {
				$('#purgeDdpBtn').button('disable');
				simpleDialog('<?php echo js_escape($lang['edit_project_154']) ?>', '<?php echo js_escape($lang['setup_08']) ?>');
			}
		});
	}));
	simpleDialog(null,null,'purgeDDPdataDialog',500,null,purgeLangClose,purgeFuncRemove,purgeLangRemove);
}
</script>

<?php
// If the "delete project" request was CANCELLED, then give dialog noting this
if (SUPER_USER && isset($_GET['request_id']) && is_numeric($_GET['request_id']))
{
    if (ToDoList::checkIfRequestPendingById($_GET['request_id'])) {
		?>
        <script type='text/javascript'>
            $(function(){
                deleteProject(0, '1');
            });
        </script>
		<?php
    } else {
		?>
        <script type='text/javascript'>
            $(function(){
                simpleDialog('<?php print js_escape($lang['edit_project_193']) ?>','<?php print js_escape($lang['edit_project_189']) ?>');
            });
        </script>
		<?php
    }
}

// Tables
print $otherFuncTable;
loadJS('DataExport.js');
// ODM: Export whole project as ODM XML
if ($user_rights['data_export_tool'] > 0) {
	// Hidden dialog to choose export format
	print DataExport::renderExportOptionDialog();
}
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
