<?php


use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Clean action param
if (isset($_GET['action']) && !preg_match("/^[a-zA-Z0-9_]*$/", $_GET['action'])) {
	unset($_GET['action']);
}

$username = isset($_GET['username']) ? $_GET['username'] : '';
if($username)
{
	$user = User::getUserInfo($username);
	$folder_ids = isset($_GET['folder_ids']) ? explode(',', $_GET['folder_ids']) : array();
}
else
{
	$user = User::getUserInfo(USERID);
	$folder_ids = array();
}

// Parent/Child passthru
if (isset($_GET['parentchild']) && is_numeric($_GET['parentchild'])) {
	redirect(APP_PATH_WEBROOT . "DataEntry/parent_child.php?pid=" . $_GET['parentchild'] . (isset($_GET['record']) ? "&id=" . $_GET['record'] : ""));
}

// This file can ONLY be accessed via the main index.php that sits above the version folders
if (PAGE == "home.php") {
	redirect(APP_PATH_WEBROOT_PARENT . "index.php?action=myprojects");
}


// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();
// Display tabs (except if viewing FAQ in a new window)
$onHelpPageInNewWindow = (isset($_GET['action']) && $_GET['action'] == 'help' && isset($_GET['newwin']));
if (!$onHelpPageInNewWindow) {
    include APP_PATH_VIEWS . 'HomeTabs.php';
}

//If system is offline, give message to super users that system is currently offline
if ($system_offline && $super_user)
{
	print  "<div class='red mb-3'>
				{$lang['home_01']}
				<a href='".APP_PATH_WEBROOT."ControlCenter/general_settings.php'
					style='text-decoration:underline;'>{$lang['global_07']}</a>.
			</div>";
}

// PASSWORD RESET KEY VIA EMAIL: If table-based user was created BUT authentication is still set to Public, then display message to user.
if ($auth_meth == 'none' && isset($_GET['action']) && $_GET['action'] == 'passwordreset' && isset($_GET['u']) && isset($_GET['k']))
{
	print RCView::div(array('class'=>'yellow'), RCView::b($lang['home_57']).RCView::br().$lang['home_58']);	
	$objHtmlPage->PrintFooter();
	exit;
}


/**
 * CREATE NEW PROJECT
 * Give form to create new REDCap project, if user selected it
 */
if (isset($_GET['action']) && $_GET['action'] == 'create')
{
	print  "<div class='well'>";
	print  '<div style="font-size: 18px;border-bottom:1px solid #aaa;padding-bottom:2px;margin-bottom:20px;">
			<span class="fas fa-plus" aria-hidden="true"></span> '.$lang['home_03'].'
			</div>';
	print  "<p>{$lang['home_04']} ";
	// If only super users are allowed to create new projects, then normal users will have email request sent to contact person for approval
	if ($superusers_only_create_project && !$super_user) {
		print  " {$lang['home_05']}<br><br></p>";
		print  "<form name='createdb' action='".APP_PATH_WEBROOT."ProjectGeneral/notifications.php?type=request_new' method='post' enctype='multipart/form-data'>";
		$btn_text = $lang['home_49'];
	} else {
		print  "<br><br></p>";
		print  "<form name='createdb' action='".APP_PATH_WEBROOT."ProjectGeneral/create_project.php' method='post' enctype='multipart/form-data'>";
		$btn_text = $lang['home_50'];
	}

	// JS to execute when click button
	$createdb_js = (SUPER_USER || $survey_pid_create_project == '') ? 'document.createdb.submit();' : "openSurveyDialogIframe('".Survey::getProjectStatusPublicSurveyLink('survey_pid_create_project')."');";

	// Prepare a "certification" pop-up message when user clicks Create button if text has been set
	$certify_text_js = "if (checkForm()) { showProgress(1); $createdb_js }";
	if (trim($certify_text_create) != "" && (!$super_user || ($super_user && !isset($_GET['user_email']))))
	{
		print "<div id='certify_create' class='notranslate' title='Notice' style='display:none;text-align:left;'>".filter_tags(nl2br(label_decode($certify_text_create)))."</div>";
		$certify_text_js = "if (checkForm()) {
								$('#certify_create').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
									'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
									'".js_escape($lang['create_project_72'])."': function() {
										$(this).dialog('close');
										showProgress(1); $createdb_js
									}
								} });
							}";
	}

	//FORM
	print  "<table style='width:100%;table-layout:fixed;'>";

	// Include the page with the form
	include APP_PATH_DOCROOT . "ProjectGeneral/create_project_form.php";
	// Determine if we should show the DDP EHR data mart option
	$realtime_webservice_type = 'FHIR';
	$DDP = new DynamicDataPull(0, $realtime_webservice_type);
	$showEhrDataMartOption = ($fhir_data_mart_create_project && $DDP->isEnabledInSystemFhir() && FhirEhr::isDdpUserAllowlistedForDataMart(USERID));
	// Output table row for option to start from scratch or choose a project template
	print 	RCView::tr(array('valign'=>'top'),
				RCView::td(array('style'=>'padding-top:18px;padding-right:10px;font-weight:bold;'),
					$lang['create_project_75'] . RCView::br() . $lang['create_project_76']
				) .
				RCView::td(array('style'=>'padding-top:15px;'),
					// Blank slate
					RCView::div(array('style'=>'text-indent: -1.5em; margin-left: 1.5em;margin-bottom:2px;'),
						RCView::radio(array('name'=>'project_template_radio','id'=>'project_template_radio0','value'=>'0','checked'=>'checked')) .
                        RCView::label(array('style'=>'text-indent:0;margin-bottom:0;cursor:pointer;', 'for'=>'project_template_radio0'), $lang['create_project_67'])
					) .
					// CDISC ODM (XML file)
					RCView::div(array('style'=>'text-indent: -1.5em; margin-left: 1.5em;margin-bottom:2px;'),
						RCView::radio(array('name'=>'project_template_radio','id'=>'project_template_radio2','value'=>'2')) .
                        RCView::label(array('style'=>'text-indent:0;margin-bottom:0;cursor:pointer;', 'for'=>'project_template_radio2'), $lang['create_project_109']) .
						RCView::a(array('href'=>'javascript:;', 'class'=>"help", 'onclick'=>"simpleDialog('".js_escape($lang['data_import_tool_248']." ".$lang['data_import_tool_250'])."','".js_escape($lang['create_project_109'])."');"), '?') .
						RCView::div(array('id'=>'odm_file_upload', 'style'=>'margin:6px 0px 8px 22px;color:#800000;display:none;'),
							'<i class="far fa-file-code fs15" style="text-indent: 0;"></i> ' .
							$lang['create_project_110'] . RCView::SP . RCView::SP . 
							RCView::file(array('name'=>'odm', 'style'=>'display: inline;'))
						) .
						// Hidden input in case users must request projects and have already uploaded a file, in which
						// case, the file is stored and has an edoc_id value
						RCView::hidden(array('id'=>'odm_edoc_id', 'name'=>'odm_edoc_id', 'value'=>$_GET['odm_edoc_id'])) .
						RCView::div(array('id'=>'odm_edoc_id_msg', 'style'=>'margin:0px 0px 4px 22px;color:green;display:none;'),
							'<i class="fas fa-check fs15" style="text-indent: 0;"></i> ' .
							$lang['create_project_112']
						)
					) .
					// Template
					RCView::div(array('style'=>'text-indent: -1.5em; margin-left: 1.5em;margin-bottom:2px;'),
						RCView::radio(array('name'=>'project_template_radio','id'=>'project_template_radio1','value'=>'1')) .
                        RCView::label(array('style'=>'text-indent:0;margin-bottom:0;cursor:pointer;', 'for'=>'project_template_radio1'), $lang['create_project_68'])
					) .
					// DDP on FHIR: Data Mart
					(!$showEhrDataMartOption ? "" :
						RCView::div(array('style'=>'text-indent: -1.5em; margin-left: 1.5em;margin-bottom:2px;'),
							RCView::radio(array('name'=>'project_template_radio','id'=>'project_template_radio3','value'=>'3')) .
                            RCView::label(array('style'=>'text-indent:0;margin-bottom:0;cursor:pointer;', 'for'=>'project_template_radio3'), $lang['create_project_130'] . " " . DynamicDataPull::getSourceSystemName(true))
						) .
						RCView::div(array('id'=>'ddp_datamart_options', 'style'=>'display:none;margin-left: 1.5em;'),
							RCView::div(array('id'=>'datamart', 'style'=>'position:relative;min-height: 300px;margin-top: 20px;'),'')
						)
					)
				)
			);
	// Display table of project templates
	print 	RCView::tr(array('valign'=>'top'),
				RCView::td(array('colspan'=>'2','style'=>'padding-top:20px;padding-bottom:10px;'),
					ProjectTemplates::buildTemplateTable()
				)
			);

	// "Create Project"/Cancel buttons
	print  "<tr valign='top'>
				<td></td>
				<td style='padding:15px 0 15px 5px;'>
					<button type='button' class='btn btn-primaryrc' onclick=\"$certify_text_js; return false;\">$btn_text</button>
					&nbsp; &nbsp; 
					<button class='btn btn-defaultrc create-project-cancel-btn' onclick=\"window.location.href='{$_SERVER['PHP_SELF']}'; return false;\">{$lang['global_53']}</button>
				</td>
			</tr>";

	// End of table
	print  "</table>";

	// If Super User is filling out for normal user request, use javascript to pre-fill form with existing info
	if (isset($_GET['type']) && $superusers_only_create_project && $super_user)
	{
		print  "<input type='hidden' name='user_email' value='{$_GET['user_email']}'>
				<input type='hidden' name='username' value='{$_GET['username']}'>
				<script type='text/javascript'>
				$(function(){
				setTimeout(function(){
					$('#app_title').val(urldecode(getParameterByName('app_title')));
					$('#purpose').val('{$_GET['purpose']}');
					if ($('#purpose').val() == '1') {
						$('#purpose_other_span').css({'visibility':'visible'});
						$('#purpose_other_text').val(urldecode(getParameterByName('purpose_other')));
						$('#purpose_other_text').css('display','');
					}
					if ($('#purpose').val() == '2') {
						$('#purpose_other_span').css({'visibility':'visible'});
						$('#purpose_other_research').css('display','');
						$('#project_pi_irb_div').css('display','');
						$('#project_pi_firstname').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_firstname'], ENT_QUOTES))) . "');
						$('#project_pi_mi').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_mi'], ENT_QUOTES))) . "');
						$('#project_pi_lastname').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_lastname'], ENT_QUOTES))) . "');
						$('#project_pi_email').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_email'], ENT_QUOTES))) . "');
						$('#project_pi_alias').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_alias'], ENT_QUOTES))) . "');
						$('#project_pi_username').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_pi_username'], ENT_QUOTES))) . "');
						$('#project_irb_number').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_irb_number'], ENT_QUOTES))) . "');
						$('#project_grant_number').val('" . js_escape(filter_tags(html_entity_decode($_GET['project_grant_number'], ENT_QUOTES))) . "');
						var purposeOther = '".js_escape(filter_tags(html_entity_decode($_GET['purpose_other'], ENT_QUOTES)))."';
						var purposeArray = purposeOther.split(',');
						for (i = 0; i < purposeArray.length; i++) {
							document.getElementById('purpose_other['+purposeArray[i]+']').checked = true;
						}
					}
					$('#project_note').val(br2nl(urldecode(getParameterByName('project_note'))));
					$('#repeatforms_chk_div').css({'display':'block'});
					$('#datacollect_chk').prop('checked',true);
					$('#projecttype".($_GET['surveys_enabled'] == '1' ? '2' : ($_GET['surveys_enabled'] == '2' ? '0' : '1'))."').prop('checked',true);
					$('#repeatforms_chk".($_GET['repeatforms'] ? '2' : '1')."').prop('checked',true);
					if ({$_GET['scheduling']} == 1) $('#scheduling_chk').prop('checked',true);
					if ({$_GET['randomization']} == 1) $('#randomization_chk').prop('checked',true);
					setFieldsCreateForm();
					// If template was selected, select it
					if (isNumeric('{$_GET['template']}')) {
						$('#template_projects_list').fadeTo(0,1);
						$('#template_projects_list button, #template_projects_list input').prop('disabled',false);
						$('input[name=\"project_template_radio\"][value=\"1\"]').prop('checked',true);
						$('input[name=\"copyof\"][value=\"{$_GET['template']}\"]').prop('checked',true);
					}
					".(!(isset($_GET['odm_edoc_id']) && $_GET['odm_edoc_id_hash'] == Files::docIdHash($_GET['odm_edoc_id'])) ? "" :
						"// If uploaded ODM file, then select that option
						if (isNumeric('{$_GET['odm_edoc_id']}')) {
							$('input[name=\"project_template_radio\"][value=\"2\"]').prop('checked',true);
							$('#odm_edoc_id_msg').show();
						}"
					).
					// check for DataMart project creation
					($showEhrDataMartOption && ($data_mart_options=$_GET['datamart']) ?
					"$('input[name=\"project_template_radio\"][value=\"3\"]').prop('checked',true);
					showDataMartOptions();
					" : "").
				"},10);
				});
				</script>";
	}

	//Finish bigger div
	print  "</form>";
	print "</div>";

	// Call Data Mart JS if user has Data Mart privileges
	if ((isset($_GET['datamart']) && $_GET['type'] == 'request_new') || ($fhir_data_mart_create_project && FhirEhr::isDdpUserAllowlistedForDataMart(USERID)))
	{
		$browser_supported = !$isIE || vIE() > 10;
		$app_path_js = APP_PATH_JS; // path to the JS directory
		
		$blade = Renderer::getBlade();
		$blade->share('app_path_js', $app_path_js);
		
		// check if this is a project creation request
		$route = isset($_GET['request_id']) ? 'review' : 'create';
		print $blade->run('datamart.create-project', compact('browser_supported', 'route'));
	}
	?>

	<script type="text/javascript">
	/**
	 * get the selected project type
	 */
	var getProjectType = function() {
		var form = document.querySelector('form[name="createdb"]');
		var checkedProjectTypeRadio = form.querySelector('input[name="project_template_radio"]:checked');
		if(!checkedProjectTypeRadio) return false;
		return checkedProjectTypeRadio.value;
	}

	/**
	 * check if the form is valid (title, purpose).
	 * make more checks based on project type
	 */
	var checkForm = function() {
		// Check values before submission on Create/Edit Project form
		var form_valid = setFieldsCreateFormChk();
		// stop here if basic form info are not valid
		if(!form_valid) return false;
		
		var projectType = getProjectType();
		// make additional checks for project types
		switch (projectType) {
			case '3':
				// datamart
				if(!datamart_app) return false // do not validate if the datamart object is not found
				datamart_app.validate().then(function(valid){
					if(valid===true) {
						showProgress(1);
						<?=$createdb_js?>
						return true;
					}else {
						simpleDialog('Check the errors', 'Form not valid');
						return false;
					}
				})
				return false;
				break;
			default:
				return true;
		}
	}

	/** 
	 * hide the project list and display the DataMart options
	 */
	var showDataMartOptions = function() {

		$('#template_projects_list').hide();
		$('#ddp_datamart_options').fadeTo('fast',1);
		// save a global refrence to the datamart application
		window.datamart_app = launchDataMart('#datamart')
	};

	var setProjectOptions = function() {
		// Select data entry forms project type option
		$('#projecttype1').prop('checked',true);
		// Select classic project option
		$('#repeatforms_chk1').prop('checked',true);
		// Run function to set all values in place
		setFieldsCreateForm();

		// Disable the template list
		$('#template_projects_list').fadeTo(0,0.4);
		$('#template_projects_list button, #template_projects_list input').prop('disabled',true);
		// If choose to use a template, then enable the tempate drop-down
		$('input[name="project_template_radio"]').change(function(){
			$('#ddp_datamart_options').hide();
			var project_template_radio = $('input[name="project_template_radio"]:checked').val();
			if (project_template_radio == '1') {
				// Enable drop-down and description box
				$('#template_projects_list button, #template_projects_list input').prop('disabled',false);
				$('#template_projects_list').fadeTo('fast',1);
				$('#odm_file_upload').hide();
			} else if (project_template_radio == '2') {
				// ODM
				$('#odm_file_upload').show('fade',{ },'normal');
				$('#template_projects_list').fadeTo('fast',0.4);
			} else {
				// Disable the drop-down and reset its value
				$('input[name="copyof"]').prop('checked',false);
				$('#template_projects_list button, #template_projects_list input').prop('disabled',true);
				$('#odm_file_upload').hide();
				if (project_template_radio == '3') {
					showDataMartOptions();
				} else {
					$('#template_projects_list').fadeTo('fast',0.4);	
				}
			}
		});
		// Template table: If click row, have it select the radio
		$('#table-template_projects_list tr').click(function(){
			if (!$('input[name="project_template_radio"]').length || ($('input[name="project_template_radio"]').length && $('input[name="project_template_radio"]:checked').val() == '1')) {
				$(this).find('input[name="copyof"]').prop('checked',true);
			}
		});

		// Put focus in the project title text box
		$('#app_title').focus();
	};

	$(function(){
		setProjectOptions();
	});
	</script>
	<?php
}




/**
 * MY PROJECTS LIST
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'myprojects')
{			
	// UAD and USER SPONSOR counts
	$sponsorUserCount = ($user_sponsor_dashboard_enable ? User::getSponsorUserCount(defined('USERID') ? USERID : '') : 0);
	$displayUserSponsorText = ($sponsorUserCount > 0);
	$displayUadBox = (is_numeric($user_access_dashboard_enable) && $user_access_dashboard_enable > 0
						&& UserRights::hasUserRightsPrivileges(defined('USERID') ? USERID : ''));
	$displayUadBoxRed = ($displayUadBox && ($user_access_dashboard_enable > 1 
						&& ($user_access_dashboard_view == '' || substr($user_access_dashboard_view, 0, 7) != date('Y-m'))));
	$uadBoxGrayContent = $lang['rights_322'] . " " .
						RCView::a(array('href'=>APP_PATH_WEBROOT_PARENT."index.php?action=user_access_dashboard", 'style'=>'text-decoration:underline;color:#800000;'),
							$lang['rights_226']
						) . $lang['period'];
	
	$html = "";
	// Show custom homepage announcement text (optional)
	$homepage_announcement_html = "";
	if (trim($homepage_announcement) != "") {
		$homepage_announcement_html = RCView::div(array('style'=>'margin-bottom:10px;'), nl2br(decode_filter_tags($homepage_announcement)));
	}
	$html .=  "<div style='margin:0;padding:0;' class='d-none d-sm-block col-md-12'>
				{$lang['home_59']}
				<a href='javascript:;' style='text-decoration:underline;' onclick=\"$(this).remove();$('#myprojects-instructions').show('fade');\">{$lang['scheduling_78']}</a>
				<span id='myprojects-instructions' style='display:none;'>
					{$lang['home_60']} {$lang['home_07']} {$lang['home_63']}{$lang['home_09']}
					{$lang['home_45']} {$lang['home_46']} {$lang['home_47']}
					".(defined('SUPER_USER') && SUPER_USER ? " {$lang['home_44']} <img src='".APP_PATH_IMAGES."star_small2.png' style='vertical-align:middle;'>{$lang['period']}" : "")."
				</span>
				".(($user_access_dashboard_enable > 0 && !$displayUadBoxRed) ? $uadBoxGrayContent : "")."
			</div>";

	// UAD: Display time that user last accessed the project user access dashboard (if enabled and user has User Rights privileges in at least one project)
	if ($displayUadBox)
	{
		// If custom notification text is set, then use it instead of the stock text
		$user_access_dashboard_custom_notify_text = (trim($user_access_dashboard_custom_notification) == '') ? $lang['rights_242'] : filter_tags($user_access_dashboard_custom_notification);
		// Determine if user has accessed the page this month. If not, give red alert IF $user_access_dashboard_enable > 1.
		if ($displayUadBoxRed) {
			// RED WARNING
			if ($user_access_dashboard_view == '') {
				// Never accessed the page
				$user_access_dashboard_action_text = "{$lang['rights_244']} $user_access_dashboard_custom_notify_text";
			} else {
				// Has not accessed the page this month
				$user_access_dashboard_view_text = floor((strtotime(TODAY)-strtotime(substr($user_access_dashboard_view, 0, 10)))/86400);
				$user_access_dashboard_action_text = "{$lang['rights_241']} ".
					($user_access_dashboard_view_text <= 1 ? ($user_access_dashboard_view_text == 1 ? $lang['rights_257'] : $lang['rights_258']) : "$user_access_dashboard_view_text ".$lang['rights_256'])."{$lang['period']} $user_access_dashboard_custom_notify_text";
			}
			// Display red box
			$html .= 	RCView::div(array('class'=>'d-none d-sm-block col-md-12', 'style'=>'color:#800000;border:1px solid #C00000;background-color:#FFE1E1;font-size:11px;margin:10px 0 0;padding:5px 10px;'),
						RCView::table(array('style'=>'width:100%;table-layout:fixed;', 'cellspacing'=>'0'),
							RCView::tr(array(),
								RCView::td(array(),
									RCView::div(array('style'=>'line-height:14px;'),
										RCView::b($lang['rights_243'])." " .
										$user_access_dashboard_action_text
									)
								) .
								RCView::td(array('style'=>'text-align:right;padding:3px 0 1px;'.($sponsorUserCount > 0 ? 'width:200px;' : 'width:240px;')),
									$lang['setup_45'] .
									RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'style'=>'margin-left:8px;', 'onclick'=>"window.location.href='".APP_PATH_WEBROOT_PARENT."index.php?action=user_access_dashboard';"),
										$lang['rights_226']
									)
								)
							)
						)
					);
		}
	}
	
	print $homepage_announcement_html;
	
	// Right-hand links for UAD and Sponsor Dashboard (only viewable by sponsors)
	if ($sponsorUserCount > 0) 
	{
		print RCView::div(array('class'=>'d-none d-sm-block col-md-12 clearfix', 'style'=>'padding:0;'), 
				RCView::div(array('class'=>'col-md-10 float-left', 'style'=>'padding:0;'), $html) .
				RCView::div(array('class'=>'col-md-2 float-right', 'style'=>'border-left:1px solid #ddd;padding-right:0;font-size:12px;'),
					RCView::div(array('class'=>'nowrap'),
						RCView::b($lang['control_center_4645'])
					) .
					// UAD link
					(!($user_access_dashboard_enable > 0) ? '' :
						RCView::div(array('class'=>'nowrap', 'style'=>'color:#888;'),
							" - " . RCView::a(array('href'=>APP_PATH_WEBROOT_PARENT."index.php?action=user_access_dashboard", 'style'=>'font-size:11px;text-decoration:underline;color:#800000;'),
								$lang['rights_226']
							)
						)
					) .
					// Sponsor Dashboard link
					(!$user_sponsor_dashboard_enable ? '' :
						RCView::div(array('class'=>'nowrap', 'style'=>'color:#888;'),
							" - " . RCView::a(array('href'=>APP_PATH_WEBROOT_PARENT."index.php?action=user_sponsor_dashboard", 'class'=>'nowrap', 'style'=>'font-size:11px;text-decoration:underline;color:#008000;'),
								$lang['rights_330'] . " " . $lang['leftparen'] . $sponsorUserCount . " " . $lang['control_center_192'] . $lang['rightparen']
							)
						)
					)
				)
			  );
	} else {
		print $html;
	}
	
	print RCView::div(array('class'=>'d-none d-sm-block col-md-12', 'style'=>'padding:0;margin-bottom:0px;'), RCView::SP);

	$projects = new RenderProjectList ();
	$projects->renderprojects();

	// Check if user has any Archived projects. If so, show link to display them, if desired.
	print  "<div style='margin-top:15px;margin-left:5px;'>";
	$sql = "select count(1) from redcap_user_rights u, redcap_projects p where u.project_id = p.project_id and
			u.username = '".db_escape($userid)."' and p.completed_time is not null";
	$num_archived = db_result(db_query($sql), 0);
	if ($num_archived > 0) {
		if (!isset($_GET['show_completed'])) {
			print  "<a style='font-size:11px;color:#777;' href='index.php?action=myprojects&show_completed'><span class='fa fa-archive mr-1' aria-hidden='true'></span>{$lang['edit_project_208']}</a>";
		} else {
			print  "<a style='font-size:11px;color:#777;' href='index.php?action=myprojects'><span class='fa fa-archive mr-1' aria-hidden='true'></span>{$lang['edit_project_209']}</a>";
		}
	}
	print  "</div>";

	print RCView::div(array('class'=>'my-3'), RCView::SP);

	// TWO FACTOR TWILIO WITH NO PHONE NUMBERS LISTED
	// Prompt user to enter a phone number since they have none in their user account.
	if ($auth_meth != 'none' && $two_factor_auth_enabled && $two_factor_auth_twilio_enabled
		// If user has no phone numbers listed
		&& $user_phone_sms == '' && $user_phone == ''
		// If the user was forced to perform two-factor for *this* session, then prompt them. Don't bother them if they didn't just do two-factor.
		&& Authentication::enforceTwoFactorByIP())
	{
		// Get user info
		$user_info = User::getUserInfo(USERID);
		// If have no phone numbers, then display
		if ($user_info['two_factor_auth_twilio_prompt_phone'])
		{
			// Dialog div
			$twilio_prompt_phone_dialog = 	RCView::div(array('style'=>'font-size:14px;line-height:16px;'),
												$lang['system_config_488'] . " " .
												RCView::b($lang['system_config_491']) . " " .
												$lang['system_config_492'] .
												RCView::div(array('style'=>'margin-top:20px;font-size:13px;'),
													RCView::checkbox(array('id'=>'twilio_prompt_phone_dialog_checkbox', 'onclick'=>"neverShowPhonePromptAgain();")) .
													RCView::span(array('onclick'=>"var ob = $('#twilio_prompt_phone_dialog_checkbox'); ob.prop('checked', !ob.prop('checked')); neverShowPhonePromptAgain();"),
														$lang['system_config_490']
													) .
													RCView::span(array('id'=>'twilio_prompt_phone_dialog_saved', 'style'=>'margin-left:15px;font-size:13px;font-weight:bold;color:green;visibility:hidden;'),
														RCView::img(array('src'=>'tick.png')) .
														$lang['design_243']
													)
												)
											);
			?><script type="text/javascript">
			$(function(){
				simpleDialog('<?php print js_escape($twilio_prompt_phone_dialog) ?>','<?php print js_escape($lang['system_config_487']) ?>','twilio_prompt_phone_dialog',500,null,null,"window.location.href = app_path_webroot+'Profile/user_profile.php';",'<?php print js_escape($lang['system_config_489']) ?>')
				$('#twilio_prompt_phone_dialog').parent().find('div.ui-dialog-buttonpane button:eq(1)').css({'font-weight':'bold','color':'#222'});
			});
			function neverShowPhonePromptAgain() {
				$.post(app_path_webroot+'Authentication/two_factor_hide_phone_prompt.php',{ two_factor_auth_twilio_prompt_phone: ($('#twilio_prompt_phone_dialog_checkbox').prop('checked') ? '0' : '1') },function(data){
					if (data == '1') {
						$('#twilio_prompt_phone_dialog_saved').css('visibility','visible');
						setTimeout(function(){
							$('#twilio_prompt_phone_dialog_saved').css('visibility','hidden');
						},2000);
					} else {
						alert(woops);
					}
				});
			}
			</script><?php
		}
	}
}



/**
 * GIVE USER CONFIRMATION IF REQUESTED NEW PROJECT
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'requested_new' && $superusers_only_create_project)
{
	//print  "<br><div style='width:95%;border:1px solid #d0d0d0;padding:0px 15px 15px 15px;background-color:#f5f5f5;'>";
	print  "<h4 style='padding:3px; font-weight: bold;'>{$lang['home_12']}</h4>";
	print  "<p style='padding-bottom:50px;'>
				{$lang['home_13']} {$lang['home_14']} (<a href='mailto:$user_email' style='text-decoration:underline;'>$user_email</a>)
				{$lang['home_15']}
			</p>";
}



/**
 * GIVE USER CONFIRMATION IF REQUESTED TO COPY PROJECT
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'requested_copy' && $superusers_only_create_project)
{
	//print  "<br><div style='width:95%;border:1px solid #d0d0d0;padding:0px 15px 15px 15px;background-color:#f5f5f5;'>";
	print  "<h4 style='padding:3px; font-weight: bold;'>{$lang['home_12']}</h4>";
	print  "<p style='padding-bottom:50px;'>
				{$lang['home_16']}
				<b>" . strip_tags(html_entity_decode($_GET['app_title'], ENT_QUOTES)) . "</b>.
				{$lang['home_14']} (<a href='mailto:$user_email' style='text-decoration:underline;'>$user_email</a>)
				{$lang['home_15']}
			</p>";
}

/**
 * GIVE USER CONFIRMATION IF REQUESTED TO DELETE PROJECT
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'requested_delete')
{
	print  "<h4 style='padding:3px; font-weight: bold;'>{$lang['home_12']}</h4>";
	print  "<p style='padding-bottom:50px;'>
				{$lang['home_55']}
				<b>" . RCView::escape(ToDoList::getProjectTitle($_GET['pid'])) . "</b>{$lang['period']}
				{$lang['home_14']} (<a href='mailto:$user_email' style='text-decoration:underline;'>$user_email</a>)
				{$lang['home_56']}
			</p>";
}



/**
 * GIVE SUPER USER CONFIRMATION WHEN APPROVING NEW PROJECT
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'approved_new' && $superusers_only_create_project & $super_user)
{
	$project_link = "";
	if (isset($_GET['new_pid']) && is_numeric($_GET['new_pid'])) {
		$project_link = "{$lang['home_53']} <a href='".APP_PATH_WEBROOT."index.php?pid={$_GET['new_pid']}' style='text-decoration:underline;'>{$lang['home_54']}</a>{$lang['period']}";
	}
	print  "<h4 style='padding:3px; font-weight: bold;'>{$lang['home_17']}</h4>";
	print  "<p style='padding-bottom:50px;'>
				{$lang['home_18']} (<a href='mailto:{$_GET['user_email']}' style='text-decoration:underline;'>{$_GET['user_email']}</a>){$lang['period']}
				$project_link
			</p>";
}



/**
 * GIVE SUPER USER CONFIRMATION WHEN COPYING PROJECT
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'approved_copy' && $superusers_only_create_project & $super_user)
{
	print  "<h4 style='padding:3px; font-weight: bold;'>{$lang['home_17']}</h4>";
	print  "<p style='padding-bottom:50px;'>
				{$lang['home_19']} (<a href='mailto:{$_GET['user_email']}' style='text-decoration:underline;'>{$_GET['user_email']}</a>).
			</p>";
}



/**
 * GIVE SUPER USER CONFIRMATION WHEN MOVING PROJECT TO PRODUCTION (USER REQUESTED)
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'approved_movetoprod' && $superusers_only_move_to_prod & $super_user)
{
	print  "<h4 style='padding:3px; font-weight: bold;'>{$lang['home_17']}</h4>";
	print  "<p style='padding-bottom:50px;'>
				{$lang['home_43']} (<a href='mailto:{$_GET['user_email']}' style='text-decoration:underline;'>{$_GET['user_email']}</a>).
			</p>";
}



/**
 * TRAINING RESOURCES (VIDEOS, ETC.)
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'training')
{
	include APP_PATH_DOCROOT . "Home/training_resources.php";
}



/**
 * HELP & FAQ
 */
elseif (isset($_GET['action']) && $_GET['action'] == 'help')
{
	include APP_PATH_DOCROOT . "Help/index.php";
}



/**
 * PROJECT ACCESS SUMMARY OR USER SPONSOR DASHBOARD
 */
elseif (isset($_GET['action']) && in_array($_GET['action'], array('user_access_dashboard', 'user_sponsor_dashboard'), true))
{
	include APP_PATH_DOCROOT . "Home/" . $_GET['action'] . ".php";
}




/**
 * HOME PAGE WITH GENERAL INFO
 */
else
{
	include APP_PATH_DOCROOT . "Home/info.php";
}

// Check if need to report institutional stats to REDCap consortium
Stats::checkReportStats();

$objHtmlPage->PrintFooter();
