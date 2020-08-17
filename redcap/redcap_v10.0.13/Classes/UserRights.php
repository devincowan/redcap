<?php



/**
 * UserRights Class
 * Contains methods used with regard to user privileges
 */
class UserRights
{
	// Map pages to user_rights table values to determine rights for a given page (e.g., PAGE=>field from user_rights table).
	// Also maps Route from query string (&route=Class:Method), if exists.
	public $page_rights = array(
		// Routes that need to be allowlisted but are not mappable to a $user_rights element.
		// Their format will be "Class/Method"=>"" (the value should stay as an empty string).
		"DataEntryController:saveShowInstrumentsToggle"=>"",
		"DataEntryController:renderInstancesTable"=>"",
		"DataEntryController:assignRecordToDag"=>"",
		"DataEntryController:passwordVerify"=>"",
		"DataEntryController:openSurveyValuesChanged"=>"",
		"DataEntryController:getResponseContributors"=>"",
		"DataEntryController:buildRecordListCache"=>"",
		"DataEntryController:clearRecordListCache"=>"",
		"UserRightsController:impersonateUser"=>"",
		"PdfController:index"=>"",
		"DataAccessGroupsController:switchDag"=>"",
		// Data Entry
		"DataEntryController:renameRecord"=>"record_rename",
		"DataEntryController:deleteRecord"=>"record_delete",
		"DataEntryController:deleteEventInstance"=>"record_delete",
		"DataEntryController:recordExists"=>"",
		// Export & Reports
		"DataExport/data_export_tool.php"=>"data_export_tool",
		"DataExport/data_export_csv.php"=>"data_export_tool",
		"DataExport/file_export_zip.php"=>"data_export_tool",
		"DataExport/data_export_ajax.php"=>"data_export_tool",
		"DataExport/report_order_ajax.php"=>"reports",
		"DataExport/report_edit_ajax.php"=>"reports",
		"DataExport/report_delete_ajax.php"=>"reports",
		"DataExport/report_user_access_list.php"=>"reports",
		"DataExport/report_copy_ajax.php"=>"reports",
		"DataExport/report_filter_ajax.php"=>"reports",
		"ReportController:reportFoldersDialog"=>"reports",
		"ReportController:reportFolderCreate"=>"reports",
		"ReportController:reportFolderEdit"=>"reports",
		"ReportController:reportFolderDelete"=>"reports",
		"ReportController:reportFolderDisplayTable"=>"reports",
		"ReportController:reportFolderDisplayTableAssign"=>"reports",
		"ReportController:reportFolderDisplayDropdown"=>"reports",
		"ReportController:reportFolderAssign"=>"reports",
		"ReportController:reportFolderResort"=>"reports",
		"ReportController:reportSearch"=>"",
		// Import
		"DataImportController:index"=>"data_import_tool",
		"DataImportController:downloadTemplate"=>"data_import_tool",
		// Data Comparison Tool
		"DataComparisonController:index"=>"data_comparison_tool",
		// Logging
		"Logging/index.php"=>"data_logging",
		"Logging/csv_export.php"=>"data_logging",
		// File Repository
		"FileRepository/index.php"=>"file_repository",
		// User Rights
		"UserRights/index.php"=>"user_rights",
		"UserRights/search_user.php"=>"user_rights",
		"UserRights/assign_user.php"=>"user_rights",
		"UserRights/edit_user.php"=>"user_rights",
		"UserRights/user_account_exists.php"=>"user_rights",
		"UserRights/set_user_expiration.php"=>"user_rights",
		"UserRightsController:displayRightsRolesTable"=>"user_rights",
		// DAGs
		"DataAccessGroupsController:index"=>"data_access_groups",
		"DataAccessGroupsController:ajax"=>"data_access_groups",
		"DataAccessGroupsController:saveUserDAG"=>"data_access_groups",
		"DataAccessGroupsController:getDagSwitcherTable"=>"data_access_groups",
		// Graphical & Stats
		"Graphical/index.php"=>"graphical",
		"Graphical/pdf.php"=>"graphical",
		"DataExport/plot_chart.php"=>"graphical",
		"DataExport/stats_highlowmiss.php"=>"graphical",
		"Graphical/image_base64_download.php"=>"graphical",
		// Calendar
		"Calendar/index.php"=>"calendar",
		"Calendar/calendar_popup.php"=>"calendar",
		"Calendar/calendar_popup_ajax.php"=>"calendar",
		"DataEntryController:renderUpcomingCalEvents"=>"calendar",
        "Calendar/scheduling.php"=>"calendar",
        "Calendar/scheduling_ajax.php"=>"calendar",
		// Locking records
		"Locking/locking_customization.php"=>"lock_record_customize",
		"Locking/esign_locking_management.php"=>"lock_record",
		"DataEntryController:lockWholeRecordPdfRender"=>"lock_record_multiform",
		// DTS
		"DtsController:adjudication"=>"dts",
		// Invite survey participants
		"Surveys/add_participants.php"=>"participants",
		"Surveys/invite_participants.php"=>"participants",
		"Surveys/delete_participant.php"=>"participants",
		"Surveys/edit_participant.php"=>"participants",
		"Surveys/participant_export.php"=>"participants",
		"Surveys/shorturl.php"=>"participants",
		"Surveys/shorturl_custom.php"=>"participants",
		"Surveys/participant_list.php"=>"participants",
		"Surveys/participant_list_enable.php"=>"participants",
		"Surveys/view_sent_email.php"=>"participants",
		"Surveys/get_access_code.php"=>"participants",
		"Surveys/invite_participant_popup.php"=>"participants",
		"Surveys/invitation_log_export.php"=>"participants",
		"SurveyController:changeLinkExpiration"=>"participants",
		"SurveyController:renderUpcomingScheduledInvites"=>"participants",
        "SurveyController:enableCaptcha"=>"participants",
		// Data Quality
		"DataQuality/execute_ajax.php"=>"data_quality_execute",
		"DataQuality/edit_rule_ajax.php"=>"data_quality_design",
		// Randomization
		"Randomization/index.php"=>"random_setup",
		"Randomization/upload_allocation_file.php"=>"random_setup",
		"Randomization/download_allocation_file.php"=>"random_setup",
		"Randomization/download_allocation_file_template.php"=>"random_setup",
		"Randomization/check_randomization_field_data.php"=>"random_setup",
		"Randomization/delete_allocation_file.php"=>"random_setup",
		"Randomization/save_randomization_setup.php"=>"random_setup",
		"Randomization/dashboard.php"=>"random_dashboard",
		"Randomization/dashboard_all.php"=>"random_dashboard",
		"Randomization/randomize_record.php"=>"random_perform",
		// Setup & Design
		"ProjectGeneral/copy_project_form.php"=>"design",
		"ProjectGeneral/change_project_status.php"=>"design",
		"Design/define_events.php"=>"design",
		"Design/define_events_ajax.php"=>"design",
		"Design/designate_forms.php"=>"design",
		"Design/designate_forms_ajax.php"=>"design",
		"Design/data_dictionary_upload.php"=>"design",
		"Design/data_dictionary_download.php"=>"design",
		"Design/data_dictionary_snapshot.php"=>"design",
		"RepeatInstanceController:renderSetup"=>"design",
		"RepeatInstanceController:saveSetup"=>"design",
		"ProjectGeneral/edit_project_settings.php"=>"design",
		"ProjectGeneral/modify_project_setting_ajax.php"=>"design",
		"ProjectGeneral/delete_project.php"=>"design",
		"Design/delete_form.php"=>"design",
		"ProjectGeneral/erase_project_data.php"=>"design",
		"ProjectSetup/other_functionality.php"=>"design",
		"ProjectSetup/project_revision_history.php"=>"design",
		"IdentifierCheckController:index"=>"design",
		"Design/online_designer.php"=>"design",
		"SharedLibrary/index.php"=>"design",
		"SharedLibrary/receiver.php"=>"design",
		"ProjectSetup/checkmark_ajax.php"=>"design",
		"ProjectSetup/export_project_odm.php"=>"design",
		"Surveys/edit_info.php"=>"design",
		"Surveys/create_survey.php"=>"design",
		"Surveys/survey_online.php"=>"design",
		"Surveys/delete_survey.php"=>"design",
		"Design/draft_mode_review.php"=>"design",
		"Design/draft_mode_enter.php"=>"design",
		"Design/draft_mode_notified.php"=>"design",
		"Design/draft_mode_cancel.php"=>"design",
		"ExternalLinks/index.php"=>"design",
		"ExternalLinks/edit_resource_ajax.php"=>"design",
		"ExternalLinks/save_resource_users_ajax.php"=>"design",
		"Design/calculation_equation_validate.php"=>"design",
		"Design/branching_logic_builder.php"=>"design",
		"Design/survey_login_setup.php"=>"design",
		"Design/existing_choices.php"=>"design",
		"Surveys/automated_invitations_setup.php"=>"design",
		"Surveys/survey_queue_setup.php"=>"design",
		"Design/zip_instrument_download.php"=>"design",
		"Design/zip_instrument_upload.php"=>"design",
		"Design/copy_instrument.php"=>"design",
		"Surveys/twilio_check_request_inspector.php"=>"design",
		"Surveys/theme_view.php"=>"design",
		"Surveys/theme_save.php"=>"design",
		"Surveys/theme_manage.php"=>"design",
		"Surveys/copy_design_settings.php"=>"design",
		"Design/arm_upload.php"=>"design",
		"Design/arm_download.php"=>"design",
		"Design/event_upload.php"=>"design",
		"Design/event_download.php"=>"design",
		"Design/instrument_event_mapping_upload.php"=>"design",
		"Design/instrument_event_mapping_download.php"=>"design",
		"RecordDashboardController:save"=>"design",
		"RecordDashboardController:delete"=>"design",
		// Alerts & Notifications
        "AlertsController:setup"=>"design",
        "AlertsController:getEdocName"=>"design",
        "AlertsController:saveAlert"=>"design",
        "AlertsController:downloadAttachment"=>"design",
        "AlertsController:saveAttachment"=>"design",
        "AlertsController:deleteAttachment"=>"design",
        "AlertsController:copyAlert"=>"design",
        "AlertsController:deleteAlert"=>"design",
        "AlertsController:deleteAlertPermanent"=>"design",
        "AlertsController:displayRepeatingFormTextboxQueue"=>"design",
        "AlertsController:viewQueuedRecords"=>"design",
        "AlertsController:deleteQueuedRecord"=>"design",
        "AlertsController:previewAlertMessage"=>"design",
        "AlertsController:previewAlertMessageByRecordDialog"=>"design",
        "AlertsController:previewAlertMessageByRecord"=>"design",
        "AlertsController:addQueuedRecord"=>"design",
        "AlertsController:migrateEmailAlerts"=>"design",
		// Dynamic Data Pull (DDP)
		"DynamicDataPull/setup.php"=>"realtime_webservice_mapping",
		"DynamicDataPull/fetch.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/save.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/exclude.php"=>"realtime_webservice_adjudicate",
		"DynamicDataPull/purge_cache.php"=>"design",
		// DataMart
		"DataMartController:revisions"=>"",
		"DataMartController:getUser"=>"",
		"DataMartController:getSettings"=>"",
		"DataMartController:addRevision"=>"",
		"DataMartController:runRevision"=>"",
		"DataMartController:getRevisionProgress"=>"",
		"DataMartController:exportRevision"=>"",
		"DataMartController:importRevision"=>"",
        "DataMartController:sourceFields"=>"",
		"DataMartController:approveRevision"=>"design",
		"DataMartController:deleteRevision"=>"design",
		"DataMartController:index"=>"",
		// FHIR Mapping Helper
		"FhirMappingHelperController:fhirTest"=>"",
		"FhirMappingHelperController:exportCodes"=>"",
		"FhirMappingHelperController:getProjectInfo"=>"",
		"FhirMappingHelperController:fetchFhirResourceByMrn"=>"",
		"FhirMappingHelperController:fetchFhirResource"=>"",
		"FhirMappingHelperController:getUserInfo"=>"",
		"FhirMappingHelperController:getFhirMetadata"=>"",
		"FhirMappingHelperController:notifyAdmin"=>"",
		"FhirMappingHelperController:getSettings"=>"",
		"FhirMappingHelperController:index"=>"",
		// Mobile App page
		"MobileApp/index.php"=>"mobile_app",
		// Break the glass
		"GlassBreakerController:index"=>"",
		"GlassBreakerController:initialize"=>"",
		"GlassBreakerController:check"=>"",
		"GlassBreakerController:accept"=>"",
		"GlassBreakerController:cancel"=>"",
		"GlassBreakerController:getProtectedMrnList"=>"",
		"GlassBreakerController:clearProtectedMrnList"=>"",
	);

	// Double Data Entry (only): DDE Person will have no rights to certain pages that display data.
	// List the restricted pages in an array
	private $pagesRestrictedDDE = array(
		"Calendar/index.php", "DataExport/data_export_tool.php", "DataImportController:index",
		"DataComparisonController:index", "Logging/index.php", "FileRepository/index.php", "DataQuality/field_comment_log.php",
		"Locking/esign_locking_management.php", "Graphical/index.php", "DataQuality/index.php", "Reports/report.php"
	);

	// Constructor
	public function __construct($applyProjectPrivileges=false)
	{
		extract($GLOBALS);
		global $lang, $user_rights, $double_data_entry;
		// Automatically apply project-level user privileges
		if (!$applyProjectPrivileges) return;
		// Obtain the user's project-level user privileges
		$userAuthenticated = $this->checkPrivileges();
		if (!$userAuthenticated || ($userAuthenticated === '2' && !isset($_SESSION['impersonate_user'][PROJECT_ID]['impersonator'])))
		{
			if (!$GLOBALS['no_access']) {
				include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
				renderPageTitle();
			}
			$noAccessMsg = ($userAuthenticated === '2') ? $lang['config_04'] . "<br><br>" : "";
			print  "<div class='red'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'>
						<b>{$lang['global_05']}</b><br><br>
						$noAccessMsg {$lang['config_02']} <a href=\"mailto:{$GLOBALS['project_contact_email']}\">{$GLOBALS['project_contact_name']}</a> {$lang['config_03']}
					</div>";
			// Display special message if user has no access AND is a DDE user
			if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
				print RCView::div(array('class'=>'yellow', 'style'=>'margin-top:20px;'), RCView::b($lang['global_02'].$lang['colon'])." ".$lang['rights_219']);
			}
			// Display link to My Projects page
			if ($GLOBALS['no_access']) {
				print RCView::div(array('style'=>'margin-top:20px;'), RCView::a(array('href'=>APP_PATH_WEBROOT_FULL.'index.php?action=myprojects'), $lang['bottom_69']) );
			} else {
				// Show left-hand menu unless it's been flagged to hide everything to prevent user from doing anything else
				include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			}
			exit;
		}
	}
	
	/**
	 * Set SUPER USER privileges in $user_rights array. Returns true always.
	 */
	private function getSuperUserPrivileges()
	{
		global $data_resolution_enabled, $Proj, $DDP, $mobile_app_enabled, $api_enabled;
		// Manually set $user_rights array
		$user_rights = array('username'=>USERID, 'expiration'=>'', 'group_id'=>'', 'role_id'=>'',
							 'lock_record'=>2, 'lock_record_multiform'=>1, 'lock_record_customize'=>1,
							 'data_export_tool'=>1, 'data_import_tool'=>1, 'data_comparison_tool'=>1, 'data_logging'=>1, 'file_repository'=>1,
							 'user_rights'=>1, 'data_access_groups'=>1, 'design'=>1, 'calendar'=>1, 'reports'=>1, 'graphical'=>1,
							 'double_data'=>0, 'record_create'=>1, 'record_rename'=>1, 'record_delete'=>1, 'api_token'=>'', 'dts'=>1,
							 'participants'=>1, 'data_quality_design'=>1, 'data_quality_execute'=>1,
							 'data_quality_resolution'=>($data_resolution_enabled == '2' ? 3 : 0),
							 'api_export'=>1, 'api_import'=>1, 'mobile_app'=>(($mobile_app_enabled && $api_enabled) ? 1 : 0),
							 'mobile_app_download_data'=>(($mobile_app_enabled && $api_enabled) ? 1 : 0),
							 'random_setup'=>1, 'random_dashboard'=>1, 'random_perform'=>1,
							 'realtime_webservice_mapping'=>(is_object($DDP) && (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir()))),
							 'realtime_webservice_adjudicate'=>(is_object($DDP) && (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir()))),
							 'external_module_config'=>array()
							);

		// Set form-level rights
		foreach ($Proj->forms as $this_form=>$attr) {
			// If this form is used as a survey, give super user level 3 (survey response editing), else give level 1 for form-level edit rights
			$user_rights['forms'][$this_form] = (isset($attr['survey_id'])) ? '3' : '1';
		}

		// Put user_rights into global scope
		$GLOBALS['user_rights'] = $user_rights;

		// Return as true
		return true;
	}


	public static function addPrivileges($project_id, $rights)
	{
		$project_id = (int)$project_id;

		$cols_blank_defaults = array('expiration', 'data_entry');
		$keys = self::getApiUserPrivilegesAttr();

		$cols = $vals = array();
		foreach($keys as $k=>$v)
		{
			$cols[] = $backEndKey = is_numeric($k) ? $v : $k;
			$vals[] = ($rights[$v] == '' && !in_array($backEndKey, $cols_blank_defaults)) ? ($backEndKey == 'group_id' ? 'null' : 0) : checkNull($rights[$v]);
		}

		// If forms are missing for new user, then set all to 0
		if (!isset($rights['forms'])) {
			$formsRights = "";
			$Proj = new Project();
			foreach (array_keys($Proj->forms) as $this_form) {
				$formsRights .= "[$this_form,0]";
			}
			$vals[array_search('data_entry', $cols)] = checkNull($formsRights);
		}

		$sql = "INSERT INTO redcap_user_rights (project_id,	".implode(", ", $cols).") VALUES
				($project_id, ".implode(", ", $vals).")";
				
		$q = db_query($sql);

		return ($q && $q !== false);
	}


	public static function updatePrivileges($project_id, $rights)
	{
		$project_id = (int)$project_id;

		$cols_blank_defaults = array('expiration', 'data_entry');
		$keys = self::getApiUserPrivilegesAttr();

		$vals = array();
		foreach($keys as $k=>$v)
		{
			// If value was not sent, then do not update it
			if (!isset($rights[$v])) continue;
			// Set update value
			$backEndKey = is_numeric($k) ? $v : $k;
			$vals[] = "$backEndKey = " . (($rights[$v] == '' && !in_array($backEndKey, $cols_blank_defaults)) ? 0 : checkNull($rights[$v]));
		}

		$sql = "UPDATE redcap_user_rights SET ".implode(", ", $vals)."
				WHERE project_id = $project_id AND username = '".db_escape($rights['username'])."'";

		$q = db_query($sql);
		return ($q && $q !== false);
	}


	/**
	 * Return array of attributes to be imported/export for users via API User Import/Export
	 */
	public static function getApiUserPrivilegesAttr($returnEmailAndName=false)
	{
		$attrInfo = array('email', 'firstname', 'lastname');
		$attr = array('username', 'expiration', 'group_id'=>'data_access_group', 'design', 'user_rights', 'data_access_groups',
				'data_export_tool'=>'data_export', 'reports', 'graphical'=>'stats_and_charts',
				'participants'=>'manage_survey_participants', 'calendar', 'data_import_tool',
				'data_comparison_tool', 'data_logging'=>'logging', 'file_repository',
				'data_quality_design'=>'data_quality_create', 'data_quality_execute',
				'api_export', 'api_import', 'mobile_app', 'mobile_app_download_data',
				'record_create', 'record_rename', 'record_delete',
				'lock_record_customize'=>'lock_records_customization',
				'lock_record'=>'lock_records', 'lock_record_multiform'=>'lock_records_all_forms',
				'data_entry'=>'forms');
		if ($returnEmailAndName) {
			unset($attr[0]);
			$attr = array_merge(array('username'), $attrInfo, $attr);
		}
		return $attr;
	}

	/**
	 * GET USER PRIVILEGES
	 *
	 */
	public static function getPrivileges($project_id=null, $userid=null)
	{
		// Put rights in array
		$user_rights = array();
		// Set subquery
		$sqlsub = "";
		if ($project_id != null || $userid != null) {
			$sqlsub = "where";
			if ($project_id != null) {
				$sqlsub .= " r.project_id = $project_id";
			}
			if ($project_id != null && $userid != null) {
				$sqlsub .= " and";
			}
			if ($userid != null) {
				$sqlsub .= " r.username = '" . db_escape($userid) . "'";
			}
		}
		// Check if a user for this project
		$sql = "select r.*, u.* from redcap_user_rights r left outer join redcap_user_roles u
				on r.role_id = u.role_id $sqlsub order by r.project_id, r.username";
		$q = db_query($sql);
		// Set $user_rights array, which will carry all rights for current user.
		while ($row = db_fetch_array($q, MYSQLI_NUM))
		{
			// Get current project_id and user to use as array keys
			$this_project_id = $row[0];
			$this_user = strtolower($row[1]); // Deal with case-sentivity issues
			// Loop through fields using numerical indexes so we don't overwrite user values with NULLs if not in a role.
			foreach ($row as $this_field_num=>$this_value) {
				// Get name of field
				$this_field = db_field_name($q, $this_field_num);
				// If we hit the project_id again (from user_roles table) and it is null, then stop here so we don't overwrite
				// users values with NULLs since they are not in a role.
				if (isset($user_rights[$this_project_id][$this_user][$this_field]) && $user_rights[$this_project_id][$this_user][$this_field] != null && $this_value == null) continue;
				// Make sure username is lower case, for consistency
				if ($this_field == 'username') $this_value = strtolower($this_value);
				// External Modules config permissions: Decode the JSON
				if ($this_field == 'external_module_config') {
					$this_value = json_decode($this_value, true);
					if (!is_array($this_value)) $this_value = array();
				}
				// Add value to array
				$user_rights[$this_project_id][$this_user][$this_field] = $this_value;
			}
		}
		// Return array
		return $user_rights;
	}

	/**
	 * CHECK USER PRIVILEGES IN A GIVEN PROJECT
	 * Checks if user has rights to see this page
	 */
	public function checkPrivileges()
	{
		global $data_resolution_enabled, $data_locked, $status;

		// Initialize $user_rights as global variable as array
		global $user_rights;
		$user_rights = array();
		$this_project_id = PROJECT_ID;

		// If a SUPER USER, then manually set rights to full/max for all things
		if (SUPER_USER && !self::isImpersonatingUser()) {
			return $this->getSuperUserPrivileges();
		} elseif (SUPER_USER && self::isImpersonatingUser()) {
			$this_user = self::getUsernameImpersonating();
		} else {
			$this_user = USERID;
		}

		## NORMAL USERS
		// Check if a user for this project
		$user_rights_proj_user = $this->getPrivileges($this_project_id, $this_user);
		$user_rights = $user_rights_proj_user[$this_project_id][strtolower($this_user)];
		unset($user_rights_proj_user);
		// Kick out if not a user and not a Super User
		if (count($user_rights) < 1) {
			//Still show menu if a user from a child/linked project
			$GLOBALS['no_access'] = 1;
			return false;
		}

		// Check user's expiration date (if exists)
		if ($user_rights['expiration'] != "" && $user_rights['expiration'] <= TODAY)
		{
			$GLOBALS['no_access'] = 1;
			// Instead of returning 'false', return '2' specifically so we can note to user that the password has expired
			return '2';
		}

		// Data resolution workflow: disable rights if module is disabled
		if ($data_resolution_enabled != '2') $user_rights['data_quality_resolution'] = '0';

		// SET FORM-LEVEL RIGHTS: Loop through data entry listings and add each form as a new sub-array element
		$this->setFormLevelPrivileges();

		// If project has Data Locked while in Analysis/Cleanup status, then
		if ($status == '2') {
			// Whether data is locked or not, prevent from creating new records (not allowed for this status)
			$user_rights['record_create'] = '0';
			// Further limit user rights if Data Locked is enabled
			if ($data_locked == '1') {
				// Disable the user's ability to create, rename, or delete records
				$user_rights['record_rename'] = '0';
				$user_rights['record_delete'] = '0';
				// If user has API access, then ensure that api_import is disabled
				$user_rights['api_import'] = '0';
				// Prevent ability to import data via Data Import Tool
				$user_rights['data_import_tool'] = '0';
				// If project has Data Locked, then remove edit form-level privileges and set to read-only
				foreach ($user_rights['forms'] as $this_form=>$this_form_rights) {
					$user_rights['forms'][$this_form] = ($this_form_rights > 0 ? 2 : $this_form_rights);
				}
				// Disable locking privileges
				$user_rights['lock_record'] = '0';
				$user_rights['lock_record_multiform'] = '0';
			}
		}

		// Remove array elements no longer needed
		unset($user_rights['data_entry'], $user_rights['project_id']);

		// Chec page-level privileges: Return true if has access to page, else false.
		return $this->checkPageLevelPrivileges();
	}


	/**
	 * OBTAIN USER RIGHTS INFORMATION FOR ALL USERS IN THIS PROJECT
	 * Also includes users' first and last name and email address
	 * Return array with username as key (sorted by username)
	 */
	public static function getRightsAllUsers($enableDagLimiting=true)
	{
		global $Proj, $lang, $user_rights;
		// Pull all user/role info for this project
		$users = array();
		$group_sql = ($enableDagLimiting && $user_rights['group_id'] != "") ? "and u.group_id = '".$user_rights['group_id']."'" : "";
		$sql = "select u.*, i.user_firstname, i.user_lastname, trim(concat(i.user_firstname, ' ', i.user_lastname)) as user_fullname
				from redcap_user_rights u left outer join redcap_user_information i on i.username = u.username
				where u.project_id = " . PROJECT_ID . " $group_sql order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set username so we can set as key and remove from array values
			$username = $row['username'];
			unset($row['username']);
			// Add to array
			$users[$username] = $row;
		}
		// Return array
		return $users;
	}


	/**
	 * OBTAIN ALL USER ROLES INFORMATION FOR THIS PROJECT (INCLUDES SYSTEM-LEVEL ROLES)
	 * Return array with role_id as key (sorted with project-level roles first, then system-level roles)
	 */
	public static function getRoles()
	{
		// Pull all user/role info for this project
		$roles = array();
		$sql = "select * from redcap_user_roles where project_id = " . PROJECT_ID . "
				order by project_id desc, role_name";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Set role_id so we can set as key and remove from array values
			$role_id = $row['role_id'];
			unset($row['role_id']);
			// Add to array
			$roles[$role_id] = $row;
		}
		// Return array
		return $roles;
	}


	/**
	 * SET FORM-LEVEL PRIVILEGES
	 * Loop through data entry listings and add each form as a new sub-array element
	 * Does not return anything
	 */
	public function setFormLevelPrivileges()
	{
		global $user_rights;

		// User is NOT in a system-level role (i.e. user is either not in a role OR is in project-level role)
		$allForms = explode("][", substr(trim($user_rights['data_entry']), 1, -1));
		foreach ($allForms as $forminfo)
		{
			list($this_form, $this_form_rights) = explode(",", $forminfo, 2);
			$user_rights['forms'][$this_form] = $this_form_rights;
		}

		// AUTO FIX FORM-LEVEL RIGHTS: Double check to make sure that the form-level rights are all there
		$this->autoFixFormLevelPrivileges(PROJECT_ID);
	}


	/**
	 * AUTO FIX FORM-LEVEL PRIVILEGES (IF NEEDED)
	 * Double check to make sure that the form-level rights are all there (old bug would sometimes cause
	 * them to go missing, thus disrupting things).
	 * Does not return anything
	 */
	private function autoFixFormLevelPrivileges()
	{
		global $Proj, $user_rights;
		// Loop through all forms and check user rights for each
		foreach (array_keys($Proj->forms) as $this_form)
		{
			if (!isset($user_rights['forms'][$this_form])) {
				// Add to user_rights table (give user Full Edit rights to the form as default, if missing)
				if ($user_rights['role_id'] == '') {
					$sql = "update redcap_user_rights set data_entry = concat(data_entry,'[$this_form,1]')
							where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
				} else {
					$sql = "update redcap_user_roles set data_entry = concat(data_entry,'[$this_form,1]')
							where role_id = ".$user_rights['role_id'];
				}
				$q = db_query($sql);
				if (db_affected_rows() < 1) {
					// Must have a NULL as data_entry value, so fix it
					if ($user_rights['role_id'] == '') {
						$sql = "update redcap_user_rights set data_entry = '[$this_form,1]'
								where project_id = ".PROJECT_ID." and username = '" . USERID . "'";
					} else {
						$sql = "update redcap_user_roles set data_entry = '[$this_form,1]'
								where role_id = ".$user_rights['role_id'];
					}
					$q = db_query($sql);
				}
				// Also add to $user_rights array
				$user_rights['forms'][$this_form] = '1';
			}
		}
	}


	/**
	 * CHECK A USER'S PAGE-LEVEL USER PRIVILEGES
	 * Return true if they have access to the current page, else return false if they do not.
	 */
	private function checkPageLevelPrivileges()
	{
		global $user_rights, $double_data_entry, $Proj;

		// Check Data Entry page rights (edit/read-only/none), if we're on that page
		if (PAGE == 'DataEntry/index.php')
		{
			// If 'page' is not a valid form, then redirect to home page
			if (isset($_GET['page']) && !isset($Proj->forms[$_GET['page']])) {
				redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
			}
			// If user does not have rights to this form, then return false
			if (!isset($user_rights['forms'][$_GET['page']])) {
				return false;
			}
			// If user has no access to form, kick out; otherwise set as full access or disabled
			if (isset($user_rights['forms'][$_GET['page']])) {
				return ($user_rights['forms'][$_GET['page']] != "0");
			}
		}

		// DDE Person will have no rights to certain pages or routes that display data
		if ($double_data_entry && $user_rights['double_data'] != 0 && in_array(PAGE, $this->pagesRestrictedDDE)) {
			return false;
		}

		// Determine if user has rights to current page
		if (isset($this->page_rights[PAGE]) && isset($user_rights[$this->page_rights[PAGE]]))
		{
			// Does user have access to this page (>0)?
			return ($user_rights[$this->page_rights[PAGE]] > 0);
		}

		// If you got here, then you're on a page not dictated by rights in the $user_rights array, so allow access
		return true;
	}


	/**
	 * RENDER COMPREHENSIVE USER RIGHTS/ROLES TABLE
	 * Return true if they have access to the current page, else return false if they do not.
	 */
	public static function renderUserRightsRolesTable()
	{
		global  $user_rights, $lang, $Proj, $double_data_entry, $dts_enabled_global, $dts_enabled, $mobile_app_enabled,
				$api_enabled, $randomization, $enable_plotting, $data_resolution_enabled, $DDP, $scheduling;

		// Check if DAGs exist and retrieve as array
		$dags = $Proj->getGroups();

		// Set image variables
		$imgYes = RCView::img(array('src' => 'tick.png'));
		$imgNo = RCView::img(array('src' => 'cross.png'));
		$imgShield = RCView::img(array('src' => 'tick_shield.png'));

		// Set up array of all possible headers for the table (some columns will be hidden depending on project or system settings)
		$rightsHdrs = array(
			'role_name' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['rights_148']).RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_206']), 'enabled' => true, 'width'=>150, 'align'=>'left'),
			'username' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:13px;'), $lang['global_11'])." ".$lang['rights_150'].RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_174']), 'enabled' => true, 'width'=>250, 'align'=>'left'),
			'expiration' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['rights_95']).RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_209']), 'enabled' => true, 'width'=>80),
			'group_id' => array('hdr' => RCView::span(array('style'=>'font-weight:bold;font-size:12px;'), $lang['global_78']).
				($user_rights['group_id'] != '' ? '' : RCView::div(array('style'=>'padding-top:3px;color:#888;'), $lang['rights_210'])),
				'enabled' => !empty($dags), 'width'=>130),
			'design' => array('hdr' => RCView::b($lang['rights_135']), 'enabled' => true, 'width'=>60),
			'user_rights' => array('hdr' => RCView::b($lang['app_05']), 'enabled' => true, 'width'=>40),
			'data_access_groups' => array('hdr' => RCView::b($lang['global_22']), 'enabled' => true),
			'data_export_tool' => array('hdr' => RCView::b($lang['app_03']), 'enabled' => true, 'width'=>75),
			'reports' => array('hdr' => RCView::b($lang['rights_96']), 'enabled' => true),
			'graphical' => array('hdr' => RCView::b($lang['app_13']), 'enabled' => $enable_plotting > 0),
			'participants' => array('hdr' => RCView::b($lang['app_24']), 'enabled' => !empty($Proj->surveys), 'width'=>65),
			'calendar' => array('hdr' => RCView::b($lang['app_08'] . ($scheduling ? " ".$lang['rights_357'] : "")), 'enabled' => true, 'width'=>60),
			'data_import_tool' => array('hdr' => RCView::b($lang['app_01']), 'enabled' => true, 'width'=>60),
			'data_comparison_tool' => array('hdr' => RCView::b($lang['app_02']), 'enabled' => true, 'width'=>70),
			'data_logging' => array('hdr' => RCView::b($lang['app_07']), 'enabled' => true, 'width'=>45),
			'file_repository' => array('hdr' => RCView::b($lang['app_04']), 'enabled' => true, 'width'=>60),
			'double_data' => array('hdr' => RCView::b($lang['rights_50']), 'enabled' => $double_data_entry),
			'lock_record_customize' => array('hdr' => RCView::b($lang['app_11']), 'enabled' => true, 'width'=>80),
			'lock_record' => array('hdr' => RCView::b($lang['rights_97']), 'enabled' => true, 'width'=>70),
			'randomization' => array('hdr' => RCView::b($lang['app_21']), 'enabled' => $randomization, 'width'=>80),
			'data_quality_design' => array('hdr' => RCView::b($lang['dataqueries_38']), 'enabled' => true),
			'data_quality_execute' => array('hdr' => RCView::b($lang['dataqueries_39']), 'enabled' => true),
			'data_quality_resolution' => array('hdr' => RCView::b($lang['dataqueries_137']), 'enabled' => ($data_resolution_enabled == '2')),
			'api' => array('hdr' => RCView::b($lang['setup_77']), 'enabled' => $api_enabled, 'width'=>40),
			'mobile_app' => array('hdr' => RCView::b($lang['global_118']), 'enabled' => ($mobile_app_enabled && $api_enabled), 'width'=>40),
			'realtime_webservice_mapping' => array('hdr' => RCView::b(($DDP->isEnabledInSystemFhir() ? $lang['ws_210'] : $lang['ws_51'])." {$DDP->getSourceSystemName()}<div style='font-weight:normal;'>({$lang['ws_19']})</div>"), 'enabled' => (is_object($DDP) && (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())))),
			'realtime_webservice_adjudicate' => array('hdr' => RCView::b(($DDP->isEnabledInSystemFhir() ? $lang['ws_210'] : $lang['ws_51'])." {$DDP->getSourceSystemName()}<div style='font-weight:normal;'>({$lang['ws_20']})</div>"), 'enabled' => (is_object($DDP) && (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())))),
			'dts' => array('hdr' => RCView::b($lang['rights_132']), 'enabled' => $dts_enabled_global && $dts_enabled),
			'record_create' => array('hdr' => RCView::b($lang['rights_99']), 'enabled' => true, 'width'=>45),
			'record_rename' => array('hdr' => RCView::b($lang['rights_100']), 'enabled' => true, 'width'=>45),
			'record_delete' => array('hdr' => RCView::b($lang['rights_101']), 'enabled' => true, 'width'=>45)
		);

		// Get all user rights as array
		$rightsAllUsers = self::getRightsAllUsers();

		// Get all suspended users in project (so we can note which are currently suspended)
		$suspendedUsers = User::getSuspendedUsers();

		// Get all user roles as array
		$roles = self::getRoles();

		// Loop through $roles and add a sub-array of users to each role that are assigned to it
		foreach ($rightsAllUsers as $this_username=>$attr) {
			// If has role_id value, then add username to that role in $roles
			if (is_numeric($attr['role_id'])) {
				$roles[$attr['role_id']]['role_users_assigned'][] = $this_username;
			}
		}
		//print_array($rightsAllUsers);
		//print_array($roles);

		// Set default column width in table
		$defaultColWidth = 70;

		// Set table width (loop through headers and calculate)
		$tableColPadding = 14;
		$tableWidth = 0;

		// Set up the table headers
		$hdrs = array();
		foreach ($rightsHdrs as $this_colname=>$attr) {
			// If this column is not enabled, skip it
			if (!$attr['enabled']) continue;
			// Determine col width
			$this_width = (isset($attr['width'])) ? $attr['width'] : $defaultColWidth;
			// Increment the table width
			$tableWidth += ($this_width + $tableColPadding);
			// Determine col alignment
			$this_align = (isset($attr['align'])) ? $attr['align'] : 'center';
			// Add to $hdrs array to be displayed
			$hdrs[] = array($this_width, RCView::span(array('class'=>'wrap','style'=>'line-height:10px;'), $attr['hdr']), $this_align);
		}

		## ADD TABLE ROWS
		// Add rows of users/roles (start with users not in a role, then go role by role listing users in each role)
		$rows = array();
		$rowkey = 0;
		foreach ($rightsAllUsers as $this_username=>$row) {
			// If has role_id value, then skip. We'll handle users in roles later.
			if (is_numeric($row['role_id'])) continue;
			// Add to $rows array
			$rows[$rowkey] = array();
			// Loop through each column
			foreach ($rightsHdrs as $rightsKey => $r)
			{
				// If this column is not enabled, skip it
				if (!$r['enabled']) continue;
				// Initialize vars
				$cellContent = '';
				// Output column's content (depending on which column we're on)
				if ($rightsKey == 'username') {
					// Set icon if has API token
					$apiIcon = ($row['api_token'] == '' ? '' :
							RCView::span(array('class'=>'nowrap', 'style'=>'color:#A86700;font-size:11px;margin-left:8px;'),
								RCView::img(array('src'=>'coin.png', 'style'=>'vertical-align:middle;')) .
								RCView::span(array('style'=>'vertical-align:middle;'),
									$lang['control_center_333']
								)
							)
						);
					// Set text if user's account is suspended
					$suspendedText = (in_array(strtolower($this_username), $suspendedUsers))
									? RCView::span(array('class'=>'nowrap', 'style'=>'color:red;font-size:11px;margin-left:8px;'),
										$lang['rights_281']
									  )
									: "";
					$this_username_name = RCView::b(RCView::escape($this_username)) . ($row['user_fullname'] == '' ? '' : " ({$row['user_fullname']})");
					$cellContent = 	RCView::div(array('class'=>'userNameLinkDiv'),
										RCView::a(array('href'=>'javascript:;', 'style'=>'vertical-align:middle;font-size:12px;', 'title'=>$lang['rights_178'],
											'class'=>'userLinkInTable', 'inrole'=>'0', 'userid'=>$this_username), $this_username_name) .
										$suspendedText . $apiIcon
									);
				}
				elseif ($rightsKey == 'role_name') {
					$cellContent = RCView::div(array('style'=>'color:#999;'), "&mdash;");
				}
				elseif ($rightsKey == 'expiration') {
					$this_class = ($row['expiration'] == "" ? 'userRightsExpireN'
						: (str_replace("-","",$row['expiration']) < date('Ymd') ? 'userRightsExpired' : 'userRightsExpire'));
					$cellContent = 	RCView::div(array('class'=>'expireLinkDiv'),
										RCView::a(array('href'=>'javascript:;', 'class'=>$this_class, 'title'=>$lang['rights_201'],
											'userid'=>$this_username,
											'expire'=>($row['expiration'] == "" ? "" : DateTimeRC::format_ts_from_ymd($row['expiration']))),
											($row['expiration'] == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($row['expiration']))
										)
									);
				}
				elseif ($rightsKey == 'group_id') {
					// Display the DAG of this user
					if ($row['group_id'] == '') {
						$this_link_label = '&mdash;';
						$this_link_style = 'color:#999;';
					} else {
						$this_link_label = $dags[$row['group_id']];
						$this_link_style = 'color:#008000;';
					}
					if ($user_rights['group_id'] == '') {
						$cellContent = 	RCView::div(array('class'=>'dagNameLinkDiv'),
											RCView::a(array('href'=>'javascript:;', 'style'=>$this_link_style, 'title'=>$lang['rights_149'],
												'gid'=>$row['group_id'], 'uid'=>$this_username), $this_link_label)
										);
					} else {
						$cellContent = 	RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>$this_link_style), $this_link_label);
					}
				}
				elseif ($rightsKey == 'realtime_webservice_mapping') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'realtime_webservice_adjudicate') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'data_export_tool') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['rights_49'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['data_export_tool_182'];
					else $cellContent = $lang['rights_48'];
				}
				elseif ($rightsKey == 'data_quality_resolution') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['dataqueries_143'];
					elseif ($row[$rightsKey] == "4") $cellContent = $lang['dataqueries_289'];
					elseif ($row[$rightsKey] == "5") $cellContent = $lang['dataqueries_290'];
					elseif ($row[$rightsKey] == "2") $cellContent = $lang['dataqueries_138'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['dataqueries_139'];
				}
				elseif ($rightsKey == 'double_data') {
					$cellContent = ($row[$rightsKey] > 0) ? 'DDE Person #'.$row[$rightsKey] : $lang['rights_51'];
				}
				elseif ($rightsKey == 'lock_record_customize') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'lock_record') {
					$cellContent = ($row[$rightsKey] > 0) ? (($row[$rightsKey] == 1) ? $imgYes : $imgShield) : $imgNo;
				}
				elseif ($rightsKey == 'api') {
					// Set text
					if ($row['api_export'] == 1 && $row['api_import'] == 1)
						$cellContent = $lang['global_71'] . RCView::br() . $lang['global_72'];
					elseif ($row['api_export'] == 1) $cellContent = $lang['global_71'];
					elseif ($row['api_import'] == 1) $cellContent = $lang['global_72'];
					else $cellContent = $imgNo;

				}
				elseif ($rightsKey == 'randomization') {
					if ($row['random_setup'] == 1) $cellContent .= $lang['rights_142'] . RCView::br();
					if ($row['random_dashboard'] == 1) $cellContent .= $lang['rights_143'] . RCView::br();
					if ($row['random_perform'] == 1) $cellContent .= $lang['rights_144'];
					if ($cellContent == '') $cellContent = $imgNo;
				}
				else {
					$cellContent = ($row[$rightsKey] == 1) ? $imgYes : $imgNo;
				}
				// Render table cell for this column
				$rows[$rowkey][] = RCView::div(array('class'=>'wrap'), $cellContent);
			}
			// Increment rowkey
			$rowkey++;
		}
		// Now add roles
		foreach ($roles as $role_id=>$row) {
			// Add to $rows array
			$rows[$rowkey] = array();
			// Loop through each column
			foreach ($rightsHdrs as $rightsKey => $r)
			{
				// If this column is not enabled, skip it
				if (!$r['enabled']) continue;
				// Initialize vars
				$cellContent = '';
				// Output column's content (depending on which column we're on)
				if ($rightsKey == 'username') {
					if (empty($row['role_users_assigned'])) {
						$this_role_userlist = RCView::div(array('style'=>'color:#aaa;font-size:11px;'),
												(($rightsAllUsers[USERID]['group_id'] == '' || SUPER_USER) ? $lang['rights_151'] : $lang['rights_222'])
											  );
					} else {
						$these_username_names = array();
						$i = 0;
						foreach ($row['role_users_assigned'] as $this_user_assigned)
						{
							// Set icon if has API token
							$apiIcon = ($rightsAllUsers[$this_user_assigned]['api_token'] == '' ? '' :
									RCView::span(array('class'=>'nowrap', 'style'=>'color:#A86700;font-size:11px;margin-left:8px;'),
										RCView::img(array('src'=>'coin.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['control_center_333']
										)
									)
								);
							// Set text if user's account is suspended
							$suspendedText = (in_array(strtolower($this_user_assigned), $suspendedUsers))
											? RCView::span(array('class'=>'nowrap', 'style'=>'color:red;font-size:11px;margin-left:8px;'),
												$lang['rights_281']
											  )
											: "";
							$this_username_name = RCView::b(RCView::escape($this_user_assigned)) . ($rightsAllUsers[$this_user_assigned]['user_fullname'] == '' ? '' : " ({$rightsAllUsers[$this_user_assigned]['user_fullname']})");
							$these_username_names[] =
								RCView::div(array('class'=>'userNameLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
									RCView::a(array('href'=>'javascript:;', 'style'=>'vertical-align:middle;font-size:12px;', 'title'=>$lang['rights_217'],
										'class'=>'userLinkInTable', 'inrole'=>'1', 'userid'=>$this_user_assigned), $this_username_name) .
									$suspendedText . $apiIcon
								);
							$i++;
						}
						$this_role_userlist = implode("", $these_username_names);
					}
					$cellContent = 	RCView::div(array('style'=>'color:#800000;'),
										$this_role_userlist
									);
				}
				elseif ($rightsKey == 'role_name') {
					// Set different color for system-level roles
					$cellContent = RCView::a(array('href'=>'javascript:;', 'style'=>'color:#800000;font-weight:bold;font-size:12px;',
										'title'=>$lang['rights_152'], 'id'=>'rightsTableUserLinkId_' . $role_id),
										RCView::escape($row['role_name'])
									);
				}
				elseif ($rightsKey == 'expiration') {
					$these_rows = array();
					$i = 0;
					if(isset($row['role_users_assigned']))
					{
						foreach ($row['role_users_assigned'] as $this_user_assigned) {
							$this_expiration = $rightsAllUsers[$this_user_assigned]['expiration'];
							$this_class = ($this_expiration == ""
							? 'userRightsExpireN'
							: (str_replace("-","",$this_expiration) < date('Ymd')
								? 'userRightsExpired'
								: 'userRightsExpire'));
							$these_rows[] =
								RCView::div(array('class'=>'expireLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
									RCView::a(array('href'=>'javascript:;', 'class'=>$this_class, 'title'=>$lang['rights_201'],
									'userid'=>$this_user_assigned,
										'expire'=>($this_expiration == "" ? "" : DateTimeRC::format_ts_from_ymd($this_expiration))),
										($this_expiration == "" ? $lang['rights_171'] : DateTimeRC::format_ts_from_ymd($this_expiration))
									)
								);
							$i++;
						}
					}
					$cellContent = implode("", $these_rows);
				}
				elseif ($rightsKey == 'group_id') {
					// Display the DAGs of all users in this role
					$these_dagnames = array();
					$i = 0;
					if(isset($row['role_users_assigned']))
					{
						foreach ($row['role_users_assigned'] as $this_user_assigned) {
							$this_group_id = $rightsAllUsers[$this_user_assigned]['group_id'];
							if ($rightsAllUsers[$this_user_assigned]['group_id'] == '') {
								$this_link_label = '&mdash;';
								$this_link_style = 'color:#999;';
							} else {
								$this_link_label = $dags[$this_group_id];
								$this_link_style = 'color:#008000;';
							}
							if ($user_rights['group_id'] == '') {
								$these_dagnames[] = RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>($i==0 ? '' : 'border-top:1px solid #eee;')),
														RCView::a(array('href'=>'javascript:;', 'style'=>$this_link_style, 'title'=>$lang['rights_149'],
														'gid'=>$this_group_id, 'uid'=>$this_user_assigned), $this_link_label)
								);
							} else {
								$these_dagnames[] = RCView::div(array('class'=>'dagNameLinkDiv', 'style'=>$this_link_style.($i==0 ? '' : 'border-top:1px solid #eee;')),
								$this_link_label
								);
							}
							$i++;
						}
					}
					$cellContent = implode("", $these_dagnames);
				}
				elseif ($rightsKey == 'realtime_webservice_mapping') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'realtime_webservice_adjudicate') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'data_export_tool') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['rights_49'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['data_export_tool_182'];
					else $cellContent = $lang['rights_48'];
				}
				elseif ($rightsKey == 'data_quality_resolution') {
					if ($row[$rightsKey] == "0") $cellContent = $imgNo;
					elseif ($row[$rightsKey] == "1") $cellContent = $lang['dataqueries_143'];
					elseif ($row[$rightsKey] == "4") $cellContent = $lang['dataqueries_289'];
					elseif ($row[$rightsKey] == "5") $cellContent = $lang['dataqueries_290'];
					elseif ($row[$rightsKey] == "2") $cellContent = $lang['dataqueries_138'];
					elseif ($row[$rightsKey] == "3") $cellContent = $lang['dataqueries_139'];
				}
				elseif ($rightsKey == 'double_data') {
					$cellContent = ($row[$rightsKey] > 0) ? 'DDE Person #'.$row[$rightsKey] : $lang['rights_51'];
				}
				elseif ($rightsKey == 'lock_record_customize') {
					$cellContent = ($row[$rightsKey] > 0) ? $imgYes : $imgNo;
				}
				elseif ($rightsKey == 'lock_record') {
					$cellContent = ($row[$rightsKey] > 0) ? (($row[$rightsKey] == 1) ? $imgYes : $imgShield) : $imgNo;
				}
				elseif ($rightsKey == 'api') {
					if ($row['api_export'] == 1 && $row['api_import'] == 1)
						$cellContent = $lang['global_71'] . RCView::br() . $lang['global_72'];
					elseif ($row['api_export'] == 1) $cellContent = $lang['global_71'];
					elseif ($row['api_import'] == 1) $cellContent = $lang['global_72'];
					else $cellContent = $imgNo;
				}
				elseif ($rightsKey == 'randomization') {
					if ($row['random_setup'] == 1) $cellContent .= $lang['rights_142'] . RCView::br();
					if ($row['random_dashboard'] == 1) $cellContent .= $lang['rights_143'] . RCView::br();
					if ($row['random_perform'] == 1) $cellContent .= $lang['rights_144'];
					if ($cellContent == '') $cellContent = $imgNo;
				}
				else {
					$cellContent = ($row[$rightsKey] == 1) ? $imgYes : $imgNo;
				}
				// Render table cell for this column
				$rows[$rowkey][] = RCView::div(array('class'=>'wrap'), $cellContent);
			}
			// Increment rowkey
			$rowkey++;
		}
		
		// Set disabled attribute for input and button for adding new users if current user is in a DAG
		$addUserDisabled = ($user_rights['group_id'] == '') ? '' : 'disabled';

		// Create "add new user" text box
		$usernameTextboxJsFocus = "$('#new_username_assign').val('".js_escape($lang['rights_160'])."').css('color','#999');
									if ($(this).val() == '".js_escape($lang['rights_154'])."') {
									$(this).val(''); $(this).css('color','#000');
								  }";
		$usernameTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') {
									$(this).val('".js_escape($lang['rights_154'])."'); $(this).css('color','#999');
								  }";
		$usernameTextbox = RCView::text(array('id'=>'new_username', $addUserDisabled=>$addUserDisabled, 'class'=>'x-form-text x-form-field', 'maxlength'=>'255',
							'style'=>'margin-left:4px;width:200px;color:#999;font-size:13px;padding-top:0;','value'=>$lang['rights_154'],
							'onkeydown'=>"if(event.keyCode==13) $('#addUserBtn').click();",
							'onfocus'=>$usernameTextboxJsFocus,'onblur'=>$usernameTextboxJsBlur));

		// Create "assign new user" text box
		$usernameTextboxJsFocusAssign = "$('#new_username').val('".js_escape($lang['rights_154'])."').css('color','#999');
										 if ($(this).val() == '".js_escape($lang['rights_160'])."') {
											$(this).val(''); $(this).css('color','#000');
										  }";
		$usernameTextboxJsBlurAssign =  "$(this).val( trim($(this).val()) );
										  if ($(this).val() == '') {
											$(this).val('".js_escape($lang['rights_160'])."'); $(this).css('color','#999');
										  } else {
											userAccountExists($(this).val());
										  }";
		$usernameTextboxAssign = RCView::text(array('id'=>'new_username_assign', $addUserDisabled=>$addUserDisabled, 'class'=>'x-form-text x-form-field', 'maxlength'=>'255',
							'style'=>'margin-left:4px;width:200px;color:#999;font-size:13px;padding-top:0;','value'=>$lang['rights_160'],
							'onkeydown'=>"if(event.keyCode==13) { $('#assignUserBtn').click(); userAccountExists($(this).val()); }",
							'onfocus'=>$usernameTextboxJsFocusAssign,'onblur'=>$usernameTextboxJsBlurAssign));

		// Create "new role name" text box
		$userroleTextboxJsFocus = "if ($(this).val() == '".js_escape($lang['rights_155'])."') {
									$(this).val(''); $(this).css('color','#000');
								  }";
		$userroleTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') {
									$(this).val('".js_escape($lang['rights_155'])."'); $(this).css('color','#999');
								  }";
		$userroleTextbox = RCView::text(array('id'=>'new_rolename', 'class'=>'x-form-text x-form-field', 'maxlength'=>'150',
							'style'=>'margin-left:4px;width:200px;color:#999;font-size:13px;padding-top:0;font-weight:normal;','value'=>$lang['rights_155'],
							'onkeydown'=>"if(event.keyCode==13) $('#createRoleBtn').click();",
							'onfocus'=>$userroleTextboxJsFocus,'onblur'=>$userroleTextboxJsBlur));
		
		// Set html before the table
		$html = RCView::div(array('id'=>'addUsersRolesDiv', 'style'=>'margin:20px 0;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#eee;max-width:630px;'),
					// Add new user with custom rights
					RCView::div(array('style'=>($user_rights['group_id'] == '' ? 'color:#444;' : 'color:#aaa;')),					
						//If user is in DAG, only show info from that DAG and give note of that
						($user_rights['group_id'] == "" ? '' : 
							RCView::div(array('style'=>'color:#C00000;margin-bottom:10px;'), "{$lang['global_02']}{$lang['colon']} {$lang['rights_92']}")
						) .
						RCView::span(array('style'=>($user_rights['group_id'] == '' ? 'color:#000;' : 'color:#aaa;').'font-weight:bold;font-size:13px;margin-right:5px;'), $lang['rights_168']) .
						" " .$lang['rights_162']
					) .
					RCView::div(array('style'=>'margin:8px 0 0 29px;'),
						RCView::img(array('src'=>'user_add2.png', 'class'=>($user_rights['group_id'] == '' ? '' : 'opacity35'))) .
						$usernameTextbox .
						// Add User button
						RCView::button(array('id'=>'addUserBtn', $addUserDisabled=>$addUserDisabled, 'class'=>'jqbuttonmed'), $lang['rights_165'])
					) .
					// - OR -
					RCView::div(array('style'=>'margin:2px 0 1px 60px;color:#999;'),
						"&#8212; {$lang['global_46']} &#8212;"
					) .
					// Add new user - assign to role
					RCView::div(array('style'=>'margin:0 0 0 10px;'),
						RCView::img(array('src'=>'user_add2.png', 'class'=>($user_rights['group_id'] == '' ? '' : 'opacity35'))) .
						RCView::img(array('src'=>'vcard.png', 'class'=>($user_rights['group_id'] == '' ? '' : 'opacity35'))) .
						$usernameTextboxAssign .
						// Assign User button
						RCView::button(array('id'=>'assignUserBtn', $addUserDisabled=>$addUserDisabled, 'class'=>'jqbuttonmed', 'style'=>'margin-top:2px;'),
							RCView::span(array('style'=>'vertical-align:middle;'), $lang['rights_156']) .
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
						)
					) .
					// Create new user role
					RCView::div(array('style'=>'margin:20px 0 0;color:#444;'),
						RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), $lang['rights_170']) .
						" " .$lang['rights_169']
					) .
					RCView::div(array('style'=>'margin:8px 0 0 27px;font-weight:bold;color:#2C5178;'),
						RCView::img(array('src'=>'vcard_add.png', 'style'=>'')) .
						$userroleTextbox .
						RCView::button(array('id'=>'createRoleBtn', 'class'=>'jqbuttonmed'), $lang['rights_158'])
					) .
					RCView::div(array('style'=>'margin:2px 0 0 52px;font-size:11px;color:#888;'),
						$lang['rights_218']
					)
				);

		// Create DROP-DOWN OF USER ROLES to choose from
		$roleDropdownOptions = '';
		foreach ($roles as $role_id=>$attr) {
			$roleDropdownOptions .= RCView::li(array('id'=>"assignUserRoleId_$role_id"),
										RCView::a(array('href'=>'#'),
											RCView::img(array('src'=>'arrow_right.gif')) . RCView::escape($attr['role_name']))
									);
		}
		$html .= RCView::div(array('id'=>'assignUserDropdownDiv', 'style'=>'display:none;position:absolute;z-index:1000;'),
					RCView::div(array('id'=>'notify_email_role_option', 'style'=>'color:#555;font-size:11px;padding:0 4px 3px;border:1px solid #aaa;border-bottom:0;background-color:#eee;', 'ignore'=>'1'),
						"<img src='".APP_PATH_IMAGES."mail_small2.png' style='vertical-align:middle;position:relative;top:-2px;'> {$lang['rights_315']}
						&nbsp;<input type='checkbox' id='notify_email_role' name='notify_email_role' checked>"
					) .
					RCView::ul(array('id'=>'assignUserDropdown'), $roleDropdownOptions)
				);


		// TOOLTIP div when CLICK USERNAME IN TABLE
		$html .= RCView::div(array('id'=>'userClickTooltip', 'class'=>'tooltip4left','style'=>'position:absolute;padding-left:30px;'),
					RCView::div(array('style'=>'padding-bottom:5px;font-weight:bold;font-size:13px;'), $lang['rights_172']) .
					// Set custom rights button
					RCView::div(array('id'=>'tooltipBtnSetCustom', 'style'=>'clear:both;padding-bottom:2px;', 'onclick'=>"openAddUserPopup( $('#tooltipHiddenUsername').val());"),
						RCView::button(array('class'=>'jqbuttonmed'), $lang['rights_153'])
					) .
					// Remove from Role button
					RCView::div(array('id'=>'tooltipBtnRemoveRole', 'style'=>'padding-bottom:2px;', 'onclick'=>"assignUserRole( $('#tooltipHiddenUsername').val(),0)"),
						RCView::button(array('class'=>'jqbuttonmed'), $lang['rights_175'])
					) .
					// Assign User button
					RCView::div(array('id'=>'tooltipBtnAssignRole'),
						RCView::button(array('id'=>'assignUserBtn2', 'class'=>'jqbuttonmed'),
							RCView::span(array('style'=>'vertical-align:middle;'), $lang['rights_156']) .
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
						)
					) .
					// Re-assign User button
					RCView::div(array('id'=>'tooltipBtnReassignRole'),
						RCView::button(array('id'=>'assignUserBtn3', 'class'=>'jqbuttonmed nowrap'),
							RCView::span(array('style'=>'vertical-align:middle;'), $lang['rights_173']) .
							RCView::img(array('src'=>'arrow_state_grey_expanded.png', 'style'=>'margin-left:5px;vertical-align:middle;position:relative;top:-1px;'))
						)
					) .
					// Hidden input where username is store for the user just clicked, which opened this tooltip (so we know which was clicked)
					RCView::hidden(array('id'=>'tooltipHiddenUsername'))
				);

		// Return the html for displaying the table
		return $html . renderGrid("user_rights_roles_table", '', $tableWidth, "auto", $hdrs, $rows, true, true, false);
	}

	// Detect if a single user has User Rights privileges in *any* project (i.e. is a project owner) - includes roles that user is in
	public static function hasUserRightsPrivileges($user)
	{
		// Query to see if have User Rights privileges in at least one project (consider roles rights in this)
		$sql = "select 1 from redcap_user_rights u left join redcap_user_roles r
				on r.role_id = u.role_id where u.username = '".db_escape($user)."'
				and ((u.user_rights = 1 and r.user_rights is null) or r.user_rights = 1) limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

	// Detect if a single user's privileges have expired in a projecxt
	public static function hasUserRightsExpired($project_id, $user)
	{
		// Query to see if have User Rights privileges in at least one project (consider roles rights in this)
		$sql = "select 1 from redcap_user_rights where project_id = $project_id and username = '".db_escape($user)."' 
				and expiration is not null and expiration != '' and expiration <= '".TODAY."' limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}
	
	// External Modules: Display project menu link only to super users or to users with Design Setup 
	// rights *if* one or more modules are already enabled *or* if at least one module has been set as "discoverable" in the system
	public static function displayExternalModulesMenuLink()
	{
		global $user_rights, $status;
		// If Ext Mods not enabled, do not display
		if (!defined("APP_PATH_EXTMOD")) return false;
		// Always show the link to admins
		if (SUPER_USER) return true;
		// If project is not in dev or prod (archived/inactive, except for super users), do not display
		if ($status > 1) return false;
		// Check if project has any modules enabled or if any modules are discoverable
		$systemHasDiscoverableModules = (method_exists('\ExternalModules\ExternalModules', 'hasDiscoverableModules') 
										&& \ExternalModules\ExternalModules::hasDiscoverableModules());
		$enabledModules = \ExternalModules\ExternalModules::getEnabledModules(PROJECT_ID);		
		$projectHasModulesEnabled = !empty($enabledModules);
		// If the project doesn't have modules enabled AND system doesn't have any discoverable modules, then don't show
		if (!$projectHasModulesEnabled && !$systemHasDiscoverableModules) {
			return false;
		}
		// If user has Design/Setup rights AND project has modules enabled or modules are discoverable, then show
		if ($user_rights['design'] == '1') {
			return true;
		}
		// Determine if user has permission to configure at least one module in this project
		foreach ($enabledModules as $moduleDirectoryPrefix=>$moduleVersion) {
			$thisConfigUserPerm = \ExternalModules\ExternalModules::getSystemSetting($moduleDirectoryPrefix, \ExternalModules\ExternalModules::KEY_CONFIG_USER_PERMISSION);
			$userHasConfigPermissions = ($thisConfigUserPerm != '' && $thisConfigUserPerm != false);
			// User has permission to configure module
			if ($userHasConfigPermissions && in_array($moduleDirectoryPrefix, $user_rights['external_module_config'])) {
				return true;
			}
		}
		// Return false if we got this far
		return false;
	}
	
	// External Modules: Display checkbox for each enabled module in a project in the Edit User dialog on the User Rights page
	public static function getExternalModulesUserRightsCheckboxes()
	{
		// If Ext Mods not enabled, do not display
		if (!defined("APP_PATH_EXTMOD")) return false;
		if (!method_exists('\ExternalModules\ExternalModules', 'getModulesWithCustomUserRights')) return false;
		// Get array of all enabled modules with attributes
		return \ExternalModules\ExternalModules::getModulesWithCustomUserRights(PROJECT_ID);
	}

	// Render the Impersonate User drop-down for admins
	public static function renderImpersonateUserDropDown()
	{
		global $lang;
		if (!self::isSuperUserOrImpersonator()) return '';
		$selected = '';
		if (isset($_SESSION['impersonate_user'][PROJECT_ID])) {
			$selected = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
		}
		// Get the current user's username
		$currentUser = isset($_SESSION['impersonate_user'][PROJECT_ID]['impersonator']) ? $_SESSION['impersonate_user'][PROJECT_ID]['impersonator'] : USERID;
		// Remove the current user from this list of users so that they cannot choose themselves
		$options = UserRights::getUsersRoles();
		foreach ($options as $role=>$users) {
			foreach ($users as $key=>$val) {
				if ($key == $currentUser) {
					unset($options[$role][$key]);
				}
			}
			if (empty($options[$role])) {
				unset($options[$role]);
			}
		}
		if (count($options) == 1 && isset($options[$lang['rights_361']])) {
			$options = $options[$lang['rights_361']];
		}
		$blankValText = isset($_SESSION['impersonate_user'][PROJECT_ID]['impersonator']) ? $lang['rights_368'] : $lang['rights_363'];
		$options = array(''=>$blankValText)+$options;
		// Render drop-down
		$dd = RCView::select(array('id'=>'impersonate-user-select', 'class'=>'x-form-text x-form-field fs11 py-0 ml-1', 'style'=>'max-width:150px;'), $options, $selected);
		$div = 	RCView::div(array('class'=>'fs11 nowrap boldish', 'style'=>'margin: 5px 0 2px;'),
					'<span style="position:relative;top:1px;"><i class="fas fa-user-tie mr-1"></i>'.$lang['rights_362'].'</span>'.$dd
				);
		return $div;
	}

	// Get all roles and users in the project in an associative array with role name as array key
	public static function getUsersRoles()
	{
		global $lang;
		$all_users_roles = array();
		$roles = UserRights::getRoles();
		$proj_users = UserRights::getRightsAllUsers(false);
		foreach ($proj_users as $this_user=>$attr) {
			if ($this_user == '') continue;
			if (is_numeric($attr['role_id'])) {
				$attr['role_id'] = $roles[$attr['role_id']]['role_name'];
			} else {
				$attr['role_id'] = $lang['rights_361'];
			}
			$all_users_roles[$attr['role_id']][$this_user] = $this_user . ($attr['user_fullname'] == '' ? '' : " ({$attr['user_fullname']})");
		}
		natcaseksort($all_users_roles);
		foreach ($all_users_roles as &$these_users) {
			natcaseksort($these_users);
		}
		return $all_users_roles;
	}

	// Is the current user an admin (including possibly impersonating a non-super user)?
	public static function isSuperUserOrImpersonator()
	{
		return (SUPER_USER || self::isImpersonatingUser());
	}

	// Is the current user an admin and is NOT currently impersonating a non-super user?
	public static function isSuperUserNotImpersonator()
	{
		return (SUPER_USER && !self::isImpersonatingUser());
	}

	// Is the current user impersonating another user in this project?
	public static function isImpersonatingUser()
	{
		return (defined("PROJECT_ID") && isset($_SESSION['impersonate_user'][PROJECT_ID]));
	}

	// Get the name of the user being impersonated by an admin
	public static function getUsernameImpersonating()
	{
		return self::isImpersonatingUser() ? $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'] : '';
	}

	// Impersonate a user (admins only)
	public static function impersonateUser()
	{
		global $lang;
		if (!isset($_POST['user']) || !self::isSuperUserOrImpersonator()) {
			exit('0');
		}
		// Verify that user is a project user
		$proj_users = UserRights::getRightsAllUsers(false);
		if (!isset($proj_users[$_POST['user']]) && $_POST['user'] != '') exit('0');
		// Add to session or remove it if blank
		if ($_POST['user'] == '') {
			$msg =  $lang['rights_369'];
			$log = "(Admin only) Stop viewing project as user \"{$_SESSION['impersonate_user'][PROJECT_ID]['impersonating']}\"";
			unset($_SESSION['impersonate_user'][PROJECT_ID]);
		} else {
			$msg = $lang['rights_364'] . " \"" . RCView::b($_POST['user']) . "\"" . $lang['period'] . " " . $lang['rights_365'];
			$log = "(Admin only) View project as user \"{$_POST['user']}\"";
			$_SESSION['impersonate_user'][PROJECT_ID] = array('impersonator'=>USERID, 'impersonating'=>$_POST['user']);
		}
		// Log the event
		Logging::logEvent("","redcap_user_rights","MANAGE",$_POST['user'],"user = '{$_POST['user']}'", $log);
		// Return success
		print RCView::div(array('class'=>'green'),
			'<i class="fas fa-check"></i> ' . $msg
		);
	}

	// If impersonating another user in this project, display banner as reminder
	public static function renderImpersonatingUserBanner()
	{
		global $lang;
		if (!self::isImpersonatingUser()) return '';
		$impersonating = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
		$userInfo = User::getUserInfo($impersonating);
		$impersonatingName = trim($userInfo['user_firstname']." ".$userInfo['user_lastname']);
		if ($impersonatingName != '') $impersonatingName = " ($impersonatingName)";
		return "<div class='green fs13 py-2 pr-1' style='margin-left:-20px;max-width:100%;text-indent:-11px;padding-left:30px;'>
				<i class=\"fas fa-user-tie mr-2\"></i>{$lang['rights_366']} <b>\"$impersonating\"$impersonatingName</b>{$lang['rights_367']}</div>";
	}

}