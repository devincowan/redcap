<?php

/**
 * DataExport
 * This class is used for processes related to reports and the Data Export Tool.
 */
class DataExport
{
	// Set max number of results on a report page before it starts paging the results
	const NUM_RESULTS_PER_REPORT_PAGE = 1000;
	// Set max number of dynamic/live filter fields in the redcap_reports table
	const MAX_LIVE_FILTERS = 3;
	// Set max width of all dynamic/live filter drop-downs together on reports page
	const MAX_DYNAMIC_FILTER_WIDTH_TOTAL = 420;
	// Set margin-right of each dynamic/live filter drop-downs on reports page
	const RIGHT_MARGIN_DYNAMIC_FILTER = 5;
	// Set name of live filter DAG field name
	const LIVE_FILTER_DAG_FIELD = '__DATA_ACCESS_GROUPS__';
	// Set name of live filter event field name
	const LIVE_FILTER_EVENT_FIELD = '__EVENTS__';
	// Set value of live filter "blank value" for a given field
	const LIVE_FILTER_BLANK_VALUE = '[NULL]';

	// Display tabs on page
	public static function renderTabs()
	{
		global $lang, $user_rights, $redcap_version;
		// Get current URL relative to version folder
		$version_folder = "redcap_v{$redcap_version}/";
		$current_url = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], $version_folder) + strlen($version_folder));
		// Get query string parameters for the current page's URL
		$params = (strpos($current_url, ".php?") === false) ? array() : explode("&", parse_url($current_url, PHP_URL_QUERY));
		// Remove query string from $current_url
		list ($current_url, $query_string) = explode('?', $current_url, 2);
		// Format query string for the url to add 'pid'
		if (!empty($params)) {
			foreach ($params as $key=>$val) {
				// Remove the pid in the query string
				if ($val == "pid=".PROJECT_ID) unset($params[$key]);
			}
			$current_url .= "?" . implode("&", $params);
		}
		// If have report_id, then get report name
		if (isset($_GET['report_id'])) $report_name = self::getReportNames($_GET['report_id']);
		// Determine tabs to display
		$tabs = array();
		// Tab to build a new report
		if ($user_rights['reports']) {
			$tabs['DataExport/index.php?create=1&addedit=1'] =  '<i class="fas fa-plus"></i> ' .
																RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_14']);
		}
		// Tab to view list of existing reports
		$tabs['DataExport/index.php'] = '<i class="fas fa-file-export"></i> ' .
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_47']);
		// Other export options (zip, pdf, etc.) if user has some export rights
		if ($user_rights['data_export_tool'] > 0) {
			$tabs['DataExport/index.php?other_export_options=1'] = '<i class="fas fa-share-square"></i> ' .
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_export_tool_213']);
		}

		// Edit existing report
		if (isset($_GET['addedit']) && isset($_GET['report_id']) && is_numeric($_GET['report_id'])) {
			$tabs[$current_url] = 	'<i class="fas fa-pencil-alt"></i> ' .$lang['report_builder_05'] . $lang['colon'] . RCView::SP .
                                    RCView::span(array('style'=>'font-weight:normal;color:#800000;'), $report_name);
		}
		// View stats & charts for existing report
		elseif (isset($_GET['stats_charts']) && isset($_GET['report_id'])) {
			$tabs[$current_url] = 	'<i class="fas fa-chart-bar"></i> ' .
										$lang['report_builder_78'] . $lang['colon'] . RCView::SP .
										RCView::span(array('style'=>'font-weight:normal;color:#800000;'), $report_name);
		}
		// Tab for viewing single report
		elseif (!isset($_GET['addedit']) && !isset($_GET['stats_charts']) && isset($_GET['report_id'])) {
			$tabs[$current_url] = 	'<i class="fas fa-search"></i> ' .
									RCView::span(array('style'=>'vertical-align:middle;'),
										$lang['report_builder_44'] . $lang['colon'] . RCView::SP .
										RCView::span(array('style'=>'font-weight:normal;color:#800000;'), $report_name)
									);
		}
		// Render the tabs
		RCView::renderTabs($tabs);
	}


	// Output html to display options for additional export options (pdf, zip, etc.)
	public static function outputOtherExportOptions()
	{
		global $lang, $Proj, $user_rights, $display_project_xml_backup_option;
		ob_start();
		?>
		<!-- Other export options -->
		<div id="simple_export" class="export_box chklist shadow" style="margin-top:20px;">

			<?php
			// Export whole REDCap project (requires both Project Design rights and Data Export rights)
			if ($display_project_xml_backup_option && $user_rights['data_export_tool'] > 0 && $user_rights['design']) {
				?>
				<table cellspacing="0" width="100%">
					<tr>
						<td valign="top" style="padding:5px 10px 5px 30px;border-right:1px solid #eee;">
							<div style="margin-bottom:7px;">
                                <i class="fas fa-file-code fs14"></i>
								<b><?php echo $lang['data_export_tool_210'] ?></b>
							</div>
							<?php echo $lang['data_export_tool_202'] . " " . $lang['data_export_tool_203'] ?>
						</td>
						<td valign="top" style="padding-top:5px;width:70px;text-align:center;">
							<a href="javascript:;" onclick="showExportFormatDialog('ALL',true);"><img src="<?php echo APP_PATH_IMAGES ?>download_xml_project.gif"></a>
						</td>
					</tr>
			</table>
			<div class="spacer" style="border-color:#ccc;"></div>
			<?php } ?>

			<?php if (Files::hasZipArchive() && Files::hasFileUploadFields()) { ?>
			<!-- Uploaded files zip export -->
			<table cellspacing="0" width="100%">
				<tr>
					<td valign="top" style="padding-left:30px;padding-right:10px;border-right:1px solid #eee;">
						<div style="margin-bottom:7px;">
                            <i class="fas fa-file-archive"></i>
							<b><?php echo $lang['data_export_tool_151'] ?></b>
						</div>
						<?php echo $lang['data_export_tool_153'] ?><br><br>
						<i><?php echo $lang['data_export_tool_152'] ?></i>
					</td>
					<td valign="top" style="padding-top:5px;width:70px;text-align:center;">
						<?php if (Files::hasUploadedFiles()) { ?>
							<a target="_blank" href="<?php echo APP_PATH_WEBROOT . "DataExport/file_export_zip.php?pid=".PROJECT_ID ?>" title="<?php echo js_escape2($lang['data_export_tool_150']) ?>"
						<?php } else { ?>
							<a href="javascript:;" onclick="simpleDialog('<?php echo js_escape($lang['data_export_tool_154']) ?>','<?php echo js_escape($lang['global_03']) ?>');" title="<?php echo js_escape2($lang['data_export_tool_150']) ?>"
						<?php } ?>
						><img src="<?php echo APP_PATH_IMAGES ?>download_zip.gif"></a>
					</td>
				</tr>
			</table>
			<div class="spacer" style="border-color:#ccc;"></div>
			<?php } ?>

			<!-- PDF data export -->
			<table cellspacing="0" width="100%">
				<tr>
					<td valign="top" style="padding-left:30px;padding-right:10px;border-right:1px solid #eee;">
						<div style="margin-bottom:7px;">
                            <i class="fas fa-file-pdf fs14"></i>
							<b><?php echo $lang['data_export_tool_171'] ?></b>
						</div>
						<?php echo $lang['data_export_tool_123'] ?>
						<?php echo $lang['data_entry_426'] ?><br><br>
						<i><?php echo $lang['data_export_tool_124'] ?></i>
					</td>
					<td valign="top" style="padding-top:5px;width:70px;text-align:center;">
						<a href="<?php echo APP_PATH_WEBROOT . "index.php?route=PdfController:index&pid=".PROJECT_ID."&allrecords" ?>" title="<?php echo js_escape2($lang['data_export_tool_149']) ?>"
						><img src="<?php echo APP_PATH_IMAGES ?>download_pdf.gif"></a>
						<br><br>						
						<a href="<?php echo APP_PATH_WEBROOT . "index.php?route=PdfController:index&pid=".PROJECT_ID."&allrecords&compact=1" ?>" title="<?php echo js_escape2($lang['data_export_tool_149']." ".$lang['data_entry_425']) ?>"
						><img src="<?php echo APP_PATH_IMAGES ?>download_pdf_compact.gif"></a>
					</td>
				</tr>
			</table>

			<?php

			// SAVE AND RETURN CODES: Determine if any surveys exist with Save and Return Later feature enabled
			$saveAndReturnEnabled = false;
			if (!empty($Proj->surveys))
			{
				// If first instrument is a survey and has Save & Return enabled, then ALWAYS should option to download
				// the return codes file because the Public Survey might have return codes.
				if (is_numeric($Proj->firstFormSurveyId) && $Proj->surveys[$Proj->firstFormSurveyId]['save_and_return']) {
					$saveAndReturnEnabled = true;
				} else {
					// Loop through all surveys to check if using return codes
					foreach ($Proj->surveys as $attr) {
						// If using survey login, then do not count this survey as having return codes
						if ($attr['save_and_return'] && !(Survey::surveyLoginEnabled() && ($survey_auth_apply_all_surveys || $attr['survey_auth_enabled_single']))) {
							$saveAndReturnEnabled = true;
						}
					}
				}
			}
			if ($saveAndReturnEnabled) { ?>
			<div class="spacer" style="border-color:#ccc;"></div>
			<!-- Survey return codes, if any surveys exist with Save & Return enabled -->
			<table cellspacing="0" width="100%">
				<tr>
					<td valign="top" style="padding-left:30px;padding-right:10px;border-right:1px solid #eee;">
						<div style="margin-bottom:7px;">
                            <i class="fas fa-redo"></i>
							<b><?php echo $lang['data_export_tool_125'] ?></b>
						</div>
						<?php echo $lang['data_export_tool_126'] ?><br><br>
						<i><?php echo $lang['data_export_tool_127'] ?></i>
					</td>
					<td valign="top" style="padding-top:5px;width:70px;text-align:center;">
						<a href="<?php echo APP_PATH_WEBROOT ?>DataExport/data_export_csv.php?pid=<?php echo PROJECT_ID ?>&type=return_codes" title="<?php print js_escape2($lang['data_export_tool_189']) ?>"><img src="<?php echo APP_PATH_IMAGES ?>download_return_codes.gif"></a>
					</td>
				</tr>
			</table>
			<?php } ?>

		</div>
		<?php
		// Return html
		return ob_get_clean();
	}


	// Display all charts and statistics on page and use AJAX to load the charts
	public static function outputStatsCharts($report_id=null,
											// The parameters below are ONLY used for $report_id == 'SELECTED'
											$selectedInstruments=array(), $selectedEvents=array())
	{
		global $lang, $Proj, $longitudinal, $user_rights, $enable_plotting;

		// Must have Graphical rights for this
		if (!$user_rights['graphical'] || !$enable_plotting) return $lang['global_01'];

		// Check if mycrypt is loaded because it is required
		if (!openssl_loaded()) {
			return 	RCView::div(array('class'=>'red'),
						RCView::b($lang['global_01'] . $lang['colon']) . " " . $lang['global_140']
					);
		}

		// Get report name
		$report_name = self::getReportNames($report_id, !$user_rights['reports']);
		// If report name is NULL, then user doesn't have Report Builder rights AND doesn't have access to this report
		if ($report_name === null) {
			return 	RCView::div(array('class'=>'red'),
						$lang['global_01'] . $lang['colon'] . " " . $lang['data_export_tool_180']
					);
		}

		// Get report attributes
		$report = self::getReports($report_id, $selectedInstruments, $selectedEvents);
		// Obtain any dynamic filters selected from query string params
		list ($liveFilterLogic, $liveFilterGroupId, $liveFilterEventId) = self::buildReportDynamicFilterLogic($report_id);
		// Get num results returned for this report (using filtering mechanisms)
		list ($includeRecordsEvents, $num_results_returned) = self::doReport($report_id, 'report', 'html', false, false, false, false, false, false,
																false, false, false, false, false, array(), array(), true,
																false, false, true, true, $liveFilterLogic, $liveFilterGroupId, $liveFilterEventId);
		// If there are no filters, then set $includeRecordsEvents as empty array for faster processing
		if ($liveFilterLogic == '' && $liveFilterGroupId == '' && $liveFilterEventId == '' 
			&& $report['limiter_logic'] == '' && empty($report['limiter_dags']) && empty($report['limiter_events'])) {
			$includeRecordsEvents = array();
		}
		
		$report_description = $report['description'];

		// Set flag if there are no records returned for a filter (so we can distinguish this from a full data set with no filters)
		$hasFilterWithNoRecords = (empty($includeRecordsEvents)
								  && ($report['limiter_logic'].$liveFilterLogic != '' || !empty($report['limiter_dags']) || !empty($report['limiter_events'])));

		// For ALL fields, give option to select specific form and yield only its fields
		if ($report_id == 'ALL') {
			// If there is only one form in this project, then set it automatically
			if (count($Proj->forms) == 1) $_GET['page'] = $Proj->firstForm;
			// Set fields
			if (isset($Proj->forms[$_GET['page']])) {
				$report['fields'] = array_keys($Proj->forms[$_GET['page']]['fields']);
			} else {
				$report['fields'] = array();
			}
		}


		// Obtain the fields to chart (they may be a subset of the fields in the report because not all fields
		// can be listed in the graphical view based on data type
		$fields = DataExport::getFieldsToChart(PROJECT_ID, "", $report['fields']);

		// Get all HTML for charts and stats
		ob_start();
		DataExport::renderCharts(PROJECT_ID, DataExport::getRecordCountByForm(), $fields, "", $includeRecordsEvents, $hasFilterWithNoRecords);
		$charts_html = ob_get_clean();
		// Build dynamic filter options (if any)
		$dynamic_filters = self::displayReportDynamicFilterOptions($report_id);
        // Check report edit permission
        $report_edit_access = SUPER_USER;
        if (!SUPER_USER && is_numeric($report_id)) {
            $reports_edit_access = DataExport::getReportsEditAccess(USERID, $user_rights['role_id'], $user_rights['group_id'], $report_id);
            $report_edit_access = in_array($report_id, $reports_edit_access);
        }
		// Set html to return
		$html = "";
		// Action buttons
		$html .= RCView::div(array('style'=>'margin: 10px 0px '.($dynamic_filters == '' ? '20' : '5').'px;'),
					RCView::div(array('class'=>'d-print-none', 'style'=>'float:left;width:350px;padding-bottom:5px;'),
						RCView::div(array('style'=>'font-weight:bold;'),
							$lang['custom_reports_02'] .
							RCView::span(array('style'=>'margin-left:5px;color:#800000;font-size:15px;'), $num_results_returned)
						) .
						RCView::div(array('style'=>''),
							$lang['custom_reports_03'] .
							RCView::span(array('style'=>'margin-left:5px;'), Records::getCountRecordEventPairs()) .
							(!$longitudinal ? "" :
								RCView::div(array('style'=>'margin-top:3px;color:#888;font-size:11px;font-family:tahoma,arial;'),
									$lang['custom_reports_09']
								)
							)
						)
					) .
					RCView::div(array('class'=>'d-print-none', 'style'=>'float:left;'),
						// Buttons: Stats, Export, Print, Edit
						RCView::div(array(),
							// View Report button
							RCView::button(array('class'=>'report_btn jqbuttonmed', 'style'=>'margin:0;color:#008000;font-size:12px;', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id=$report_id'+getInstrumentsListFromURL()+getLiveFilterUrl();"),
                                '<i class="fas fa-search"></i> ' .$lang['report_builder_44']
							) .
							RCView::SP .
							// Export Data button
							($user_rights['data_export_tool'] == '0' ? '' :
								RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"showExportFormatDialog('$report_id');", 'style'=>'color:#000066;font-size:12px;'),
                                    '<i class="fas fa-file-download"></i> ' .$lang['report_builder_48']
								)
							) .
							RCView::SP .
							// Print link
							RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.print();", 'style'=>'font-size:12px;'),
								RCView::img(array('src'=>'printer.png', 'style'=>'vertical-align:middle;')) .
								RCView::span(array('style'=>'vertical-align:middle;'),
									$lang['custom_reports_13']
								)
							) .
                            (($report_id == 'ALL' || $report_id == 'SELECTED' || !$user_rights['reports'] || (is_numeric($report_id) && !$report_edit_access)) ? '' :
								RCView::SP .
								// Edit report link
								RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id=$report_id&addedit=1';", 'style'=>'font-size:12px;'),
									'<i class="fas fa-pencil-alt fs10"></i> ' .$lang['custom_reports_14']
								)
							)
						) .
						// Build dynamic filter options (if any)
						$dynamic_filters
					) .
					RCView::div(array('class'=>'clear'), '')
				);
		// Report title
		$html .= RCView::div(array('id'=>'this_report_title', 'style'=>'padding:5px 3px;color:#800000;font-size:18px;font-weight:bold;'),
					$report_name
				 );
		// Report description (if has one)
		if ($report_description != '') {
			$html .= RCView::div(array('id'=>'this_report_description', 'style'=>'padding:5px 3px 5px;line-height:15px;'),
						filter_tags($report_description)
					 );
		}
		// Charts and stats
		$html .= $charts_html;
		// Return HTML
		return $html;
	}


	// Display list of all usernames who have access to a given report (by report_id)
	public static function displayReportAccessUsernames($post, $access_type)
	{
		global $Proj, $lang;
		// Set access type (view vs edit)
		if ($access_type != 'edit') $access_type = 'view';
		// Get list of users
		if ($access_type == 'view') {
			$user_list = self::getReportAccessUsernames($post);
		} else {
			$user_list = self::getReportEditAccessUsernames($post);
		}
		// Get all roles in the project
		$roles = UserRights::getRoles();
		$hasRoles = !empty($roles);
		// Get all roles in the project
		$dags = $Proj->getGroups();
		$hasDags = !empty($dags);

		// Loop through users and create table rows
		$rows = RCView::tr(array(),
					RCView::td(array('class'=>'header', 'style'=>'width:250px;'),
						$lang['global_17']
					) .
					(!$hasRoles ? '' :
						RCView::td(array('class'=>'header'),
							$lang['global_115']
						)
					) .
					(!$hasDags ? '' :
						RCView::td(array('class'=>'header'),
							$lang['global_78']
						)
					)
				);
		foreach ($user_list as $user=>$attr) {
			// Add user
			$rows .= RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'style'=>'width:250px;padding:5px 10px;color:#800000;font-size:13px;font-weight:normal;'),
							$attr['name']
						) .
						(!$hasRoles ? '' :
							RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;'),
								(is_numeric($attr['role_id']) ? $roles[$attr['role_id']]['role_name'] : '')
							)
						) .
						(!$hasDags ? '' :
							RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;'),
								(is_numeric($attr['group_id']) ? $dags[$attr['group_id']] : '')
							)
						)
					);
		}
		// No users with access
		if (empty($user_list)) {
			$rows .= RCView::tr(array(),
						RCView::td(array('colspan'=>(1+($hasRoles ? 1 : 0)+($hasDags ? 1 : 0)), 'class'=>'data', 'style'=>'width:250px;padding:5px 10px;color:#800000;font-size:13px;'),
							$lang['report_builder_110']
						)
					);
		}
		// Output table
		$html =	RCView::div(array('style'=>'margin:0 0 15px;'),
					$lang['report_builder_109']
				) .
				RCView::table(array('class'=>'form_border', 'style'=>"width:100%;table-layout:fixed;"),
					$rows
				);
		// Return html
		return $html;
	}


	// Return array of all usernames who have access to a given report (by report_id)
	public static function getReportAccessUsernames($post)
	{
		// Get list of ALL users in project
		$all_users = User::getProjectUsernames(array(), true);
		// Get username list
		if ($post['user_access_radio'] == 'ALL') {
			// ALL USERS
			return $all_users;
		} else {
			// SELECTED USERS
			$selected_users = array();
			// User access rights
			$user_access_users = $user_access_roles = $user_access_dags = array();
			if (isset($post['user_access_users'])) {
				$user_access_users = $post['user_access_users'];
				if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
			}
			if (isset($post['user_access_roles'])) {
				$user_access_roles = $post['user_access_roles'];
				if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
			}
			if (isset($post['user_access_dags'])) {
				$user_access_dags = $post['user_access_dags'];
				if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
			}
			$user_sql = prep_implode($user_access_users);
			if ($user_sql == '') $user_sql = "''";
			$role_sql = prep_implode($user_access_roles);
			if ($role_sql == '') $role_sql = "''";
			$dag_sql = prep_implode($user_access_dags);
			if ($dag_sql == '') $dag_sql = "''";
			// Query tables
			$sql = "select u.username, r.role_id, g.group_id from redcap_user_rights u
					left join redcap_user_roles r on r.role_id = u.role_id
					left join redcap_data_access_groups g on g.group_id = u.group_id
					where u.project_id = ".PROJECT_ID." and
					(u.username in ($user_sql) or r.role_id in ($role_sql) or g.group_id in ($dag_sql))
					order by u.username";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Add to array
				$selected_users[$row['username']] = array('name'=>$all_users[$row['username']], 'role_id'=>$row['role_id'], 'group_id'=>$row['group_id']);
			}
			return $selected_users;
		}
	}


	// Return array of all usernames who have EDIT access to a given report (by report_id)
	public static function getReportEditAccessUsernames($post)
	{
		// Get list of ALL users in project
		$all_users = User::getProjectUsernames(array(), true);
		// Get username list
		if ($post['user_edit_access_radio'] == 'ALL') {
			// ALL USERS
			return $all_users;
		} else {
			// SELECTED USERS
			$selected_users = array();
			// User access rights
			$user_access_users = $user_access_roles = $user_access_dags = array();
			if (isset($post['user_edit_access_users'])) {
				$user_access_users = $post['user_edit_access_users'];
				if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
			}
			if (isset($post['user_edit_access_roles'])) {
				$user_access_roles = $post['user_edit_access_roles'];
				if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
			}
			if (isset($post['user_edit_access_dags'])) {
				$user_access_dags = $post['user_edit_access_dags'];
				if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
			}
			$user_sql = prep_implode($user_access_users);
			if ($user_sql == '') $user_sql = "''";
			$role_sql = prep_implode($user_access_roles);
			if ($role_sql == '') $role_sql = "''";
			$dag_sql = prep_implode($user_access_dags);
			if ($dag_sql == '') $dag_sql = "''";
			// Query tables
			$sql = "select u.username, r.role_id, g.group_id from redcap_user_rights u
					left join redcap_user_roles r on r.role_id = u.role_id
					left join redcap_data_access_groups g on g.group_id = u.group_id
					where u.project_id = ".PROJECT_ID." and
					(u.username in ($user_sql) or r.role_id in ($role_sql) or g.group_id in ($dag_sql))
					order by u.username";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Add to array
				$selected_users[$row['username']] = array('name'=>$all_users[$row['username']], 'role_id'=>$row['role_id'], 'group_id'=>$row['group_id']);
			}
			return $selected_users;
		}
	}


	// Output html table for users to create or modify reports
	public static function outputCreateReportTable($report_id=null)
	{
		global $lang, $Proj, $surveys_enabled, $user_rights, $longitudinal, $missingDataCodes;
		// Get report_id
		$report_id = ($report_id == null ? 0 : $report_id);
		// Get report attributes
		$report = self::getReports($report_id);
		// Create array of all field validation types and their attributes
		$allValTypes = getValTypes();
		// Set counter for number of fields in report + number of limiters used
		$field_counter = $limiter_counter = 1;
		// Get all field drop-down options
		$rc_field_dropdown_options = Form::getFieldDropdownOptions();
		$rc_field_labels = Form::getFieldDropdownOptions(false, false, false, false, null, false, false);
		$rc_field_dropdown_options_orderby = Form::getFieldDropdownOptions(true);
		$rc_field_dropdown_options_live_filter = Form::getFieldDropdownOptions(true, true, $Proj->hasGroups(), $longitudinal, null, true, true, true);
		// Get all forms as drop-down list
		$addFormFieldsDropDownOptions = array(''=>'-- '.$lang['report_builder_101'].' --');
		foreach ($Proj->forms as $key=>$attr) {
			$addFormFieldsDropDownOptions[$key] = $attr['menu'];
		}
		// Get list of User Roles
		$role_dropdown_options = array();
		foreach (UserRights::getRoles() as $role_id=>$attr) {
			$role_dropdown_options[$role_id] = $attr['role_name'];
		}
		// Get list of all DAGs, events, users, and records
		$dag_dropdown_options = $Proj->getGroups();
		$user_dropdown_options = User::getProjectUsernames(array(), true);
		$event_dropdown_options = $event_dropdown_options_with_all = array();
		if ($Proj->longitudinal) {
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				$event_dropdown_options[$this_event_id] = $attr['name_ext'];
			}
			$event_dropdown_options_with_all = array(''=>$lang['dataqueries_136']) + $event_dropdown_options;
		}
		$user_access_radio_custom_checked = ($report['user_access'] != 'ALL') ? 'checked' : '';
		$user_access_radio_all_checked = ($report['user_access'] == 'ALL') ? 'checked' : '';
		$user_edit_access_radio_custom_checked = ($report['user_edit_access'] != 'ALL') ? 'checked' : '';
		$user_edit_access_radio_all_checked = ($report['user_edit_access'] == 'ALL') ? 'checked' : '';
		if ($report['user_access'] == 'ALL') {
			// If ALL is selected, then remove custom options
			$report['user_access_users'] = $report['user_access_roles'] = $report['user_access_dags'] = array();
		}
		if ($report['user_edit_access'] == 'ALL') {
			// If ALL is selected, then remove custom options
			$report['user_edit_access_roles'] = $report['user_edit_access_dags'] = array();			
			// Make sure that the current user gets automatically selected to prevent lockout
			$report['user_edit_access_users'] = array(USERID);
		}
		// Add blank values onto the end of some attributes to create empty row for user to enter a new field, filter, etc.
		$report['fields'][] = "";
		$report['limiter_fields'][] = array('field_name'=>'', 'limiter_group_operator'=>'AND', 'limiter_event_id'=>'',
											'limiter_operator' =>'', 'limiter_value'=>'');
		// Instructions
		print   RCView::div(array('style'=>'max-width:800px;margin:5px 0 20px;'),
					$lang['report_builder_118']
				);
		// If creating new report from SELECTED forms/events (Rule B), then display note that all fields/events are pre-selected
		if ($report_id == '0' && isset($_GET['instruments'])) {
			print   RCView::div(array('class'=>'yellow', 'style'=>'max-width:780px;margin:5px 0 20px;'),
						RCView::img(array('src'=>'exclamation_orange.png')) .
						$lang['report_builder_141']
					);
		}
		// Initialize table rows
		print  "<div style='max-width:800px;'>
				 <form id='create_report_form'>
					<table id='create_report_table' class='form_border' style='width:100%;'>";
		// Report title & description
		print   RCView::tr(array(),
					RCView::td(array('class'=>'header nowrap text-left', 'style'=>'padding-left:10px;text-align:center;color:#800000;height:50px;width:150px;font-size:14px;'),
						$lang['report_builder_16']
					) .
					RCView::td(array('class'=>'header', 'colspan'=>3, 'style'=>'height:50px;padding:5px 10px;'),
						RCView::text(array('name'=>'__TITLE__', 'value'=>htmlspecialchars($report['title'], ENT_QUOTES), 'class'=>'x-form-text x-form-field', 'maxlength'=>60, 'onkeydown'=>'if(event.keyCode == 13) return false;', 'style'=>'height: 28px;padding: 4px 6px 3px;font-size:16px;width:99%;'))
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'header nowrap text-left', 'style'=>'padding-left:10px;text-align:center;color:#800000;width:150px;font-size:12px;font-weight:normal;'),
						RCView::b($lang['global_20']).RCView::SP.$lang['survey_251'].$lang['colon'].
						RCView::div(array('class'=>'wrap', 'style'=>'color:#888;font-size:11px;line-height:12px;margin-top:5px;'), $lang['report_builder_150'])
					) .
					RCView::td(array('class'=>'header', 'colspan'=>3, 'style'=>'padding:5px 10px;'),
						RCView::textarea(array('name'=>'description', 'id'=>'description', 'class'=>'x-form-field notesbox', 
							'style'=>'height:45px;font-size:12px;width:99%;'), $report['description']) .
						"<div style='line-height:11px;'>
							<div class='float-left' style='color:#888;font-size:11px;line-height:12px;font-weight:normal;'>{$lang['survey_613']}</div>
							<div class='float-right'><a href='javascript:;' tabindex='-1' class='expandLink' onclick=\"growTextarea('description')\">{$lang['form_renderer_19']}</a>&nbsp;&nbsp;</div>
						</div>"
					)
				);

		## USER ACCESS
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'),
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#e0e0e0;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 1"
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						RCView::div(array('style'=>''),
							'<i class="fas fa-user-plus"></i> ' .
							$lang['extres_35'] . $lang['colon'] . " " .
							RCView::span(array('style'=>'font-weight:normal;'), $lang['report_builder_154'])
						)
					)
				);
		// View access
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'style'=>'padding-top:6px;padding-bottom:6px;padding-left:10px;'),
						RCView::div(array('style'=>'color:#016f01;margin: 3px 0px 7px 0px;'),
							'<i class="far fa-eye"></i>&nbsp; ' . $lang['report_builder_153'] . " " .
							RCView::span(array('style'=>'font-weight:normal;margin-left:2px;'), $lang['report_builder_133']) .
							RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['global_58'], 'onclick'=>"simpleDialog('".js_escape($lang['report_builder_134'])."','".js_escape($lang['report_builder_135'])."',null,600);"), '?')
						) .
						// All users
						RCView::div(array('style'=>'float:left;'),
							RCView::radio(array('name'=>'user_access_radio', 'style'=>'top:3px;position:relative;', 'onchange'=>"displayUserAccessOptions()", 'value'=>'ALL', $user_access_radio_all_checked=>$user_access_radio_all_checked))
						) .
						RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
							$lang['control_center_182']
						) .
						RCView::div(array('style'=>'float:left;color:#888;font-weight:normal;margin:2px 20px 0 25px;'),
							"&ndash; " . $lang['global_46'] . " &ndash;"
						) .
						// Custom user access
						RCView::div(array('style'=>'float:left;'),
							RCView::radio(array('name'=>'user_access_radio', 'style'=>'top:3px;position:relative;', 'onchange'=>"displayUserAccessOptions()", 'value'=>'SELECTED', $user_access_radio_custom_checked=>$user_access_radio_custom_checked))
						) .
						RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
							RCView::div(array('style'=>'margin-bottom:10px;'),
								$lang['report_builder_62'] .
								RCView::span(array('id'=>'selected_users_note1', 'style'=>($report['user_access'] == 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#800000;font-size:11px;font-weight:normal;'),
									$lang['report_builder_105']
								) .
								RCView::span(array('id'=>'selected_users_note2', 'style'=>($report['user_access'] != 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#888;font-size:11px;font-weight:normal;'),
									$lang['report_builder_66']
								)
							) .
							RCView::div(array('id'=>'selected_users_div', 'style'=>($report['user_access'] == 'ALL' ? 'display:none;' : '')),
								// Select Users
								RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
									$lang['extres_28'] .
									RCView::div(array('style'=>'margin-left:3px;'),
										RCView::select(array('id'=>'user_access_users', 'name'=>'user_access_users', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
											$user_dropdown_options, $report['user_access_users'], 200)
									)
								) .
								// Select User Roles
								(empty($role_dropdown_options) ? '' :
									RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
										$lang['report_builder_61'] .
										RCView::div(array('style'=>'margin-left:3px;'),
											RCView::select(array('id'=>'user_access_roles', 'name'=>'user_access_roles', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
												$role_dropdown_options, $report['user_access_roles'], 200)
										)
									)
								) .
								// Select DAGs
								(empty($dag_dropdown_options) ? '' :
									RCView::div(array('style'=>'float:left;font-weight:normal;vertical-align:top;'),
										$lang['extres_52'] .
										RCView::div(array('style'=>'margin-left:3px;'),
											RCView::select(array('id'=>'user_access_dags', 'name'=>'user_access_dags', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
												$dag_dropdown_options, $report['user_access_dags'], 200)
										)
									)
								) .
								// Get list of users who would have access given the selections made
								RCView::div(array('style'=>'clear:both;padding:5px 0 0 3px;font-size:11px;font-weight:normal;color:#222;'),
									$lang['report_builder_111'] .
									RCView::button(array('class'=>'jqbuttonsm', 'style'=>'margin-left:7px;font-size:11px;', 'onclick'=>"getUserAccessList('view');return false;"),
										$lang['report_builder_107']
									)
								)
							)
						)
					)
				);
		// Edit access
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'style'=>'padding-top:6px;padding-bottom:6px;padding-left:10px;'),
						RCView::div(array('style'=>'color:#313196;margin: 3px 0px 7px 0px;'),
							'<i class="fas fa-edit"></i>&nbsp; ' . $lang['report_builder_152'] . " " .
							RCView::span(array('style'=>'font-weight:normal;margin-left:2px;'), $lang['report_builder_155'])
						) .
						// All users
						RCView::div(array('style'=>'float:left;'),
							RCView::radio(array('name'=>'user_edit_access_radio', 'style'=>'top:3px;position:relative;', 'onchange'=>"displayUserAccessEditOptions()", 'value'=>'ALL', $user_edit_access_radio_all_checked=>$user_edit_access_radio_all_checked))
						) .
						RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
							$lang['control_center_182']
						) .
						RCView::div(array('style'=>'float:left;color:#888;font-weight:normal;margin:2px 20px 0 25px;'),
							"&ndash; " . $lang['global_46'] . " &ndash;"
						) .
						// Custom user access
						RCView::div(array('style'=>'float:left;'),
							RCView::radio(array('name'=>'user_edit_access_radio', 'style'=>'top:3px;position:relative;', 'onchange'=>"displayUserAccessEditOptions()", 'value'=>'SELECTED', $user_edit_access_radio_custom_checked=>$user_edit_access_radio_custom_checked))
						) .
						RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
							RCView::div(array('style'=>'margin-bottom:10px;'),
								$lang['report_builder_62'] .
								RCView::span(array('id'=>'selected_users_edit_note1', 'style'=>($report['user_edit_access'] == 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#800000;font-size:11px;font-weight:normal;'),
									$lang['report_builder_105']
								) .
								RCView::span(array('id'=>'selected_users_edit_note2', 'style'=>($report['user_edit_access'] != 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#888;font-size:11px;font-weight:normal;'),
									$lang['report_builder_66']
								)
							) .
							RCView::div(array('id'=>'selected_users_edit_div', 'style'=>($report['user_edit_access'] == 'ALL' ? 'display:none;' : '')),
								// Select Users
								RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
									$lang['extres_28'] .
									RCView::div(array('style'=>'margin-left:3px;'),
										RCView::select(array('id'=>'user_edit_access_users', 'name'=>'user_edit_access_users', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
											$user_dropdown_options, $report['user_edit_access_users'], 200)
									)
								) .
								// Select User Roles
								(empty($role_dropdown_options) ? '' :
									RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
										$lang['report_builder_61'] .
										RCView::div(array('style'=>'margin-left:3px;'),
											RCView::select(array('id'=>'user_edit_access_roles', 'name'=>'user_edit_access_roles', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
												$role_dropdown_options, $report['user_edit_access_roles'], 200)
										)
									)
								) .
								// Select DAGs
								(empty($dag_dropdown_options) ? '' :
									RCView::div(array('style'=>'float:left;font-weight:normal;vertical-align:top;'),
										$lang['extres_52'] .
										RCView::div(array('style'=>'margin-left:3px;'),
											RCView::select(array('id'=>'user_edit_access_dags', 'name'=>'user_edit_access_dags', 'onchange'=>"clearMultiSelect(this);", 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'),
												$dag_dropdown_options, $report['user_edit_access_dags'], 200)
										)
									)
								) .
								// Get list of users who would have access given the selections made
								RCView::div(array('style'=>'clear:both;padding:5px 0 0 3px;font-size:11px;font-weight:normal;color:#222;'),
									$lang['report_builder_111'] .
									RCView::button(array('class'=>'jqbuttonsm', 'style'=>'margin-left:7px;font-size:11px;', 'onclick'=>"getUserAccessList('edit');return false;"),
										$lang['report_builder_107']
									)
								)
							)
						)
					)
				);

		## FIELDS USED IN REPORT
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'),
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#e0e0e0;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 2"
						)
					)
				);
		// "Fields" section header
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						RCView::div(array('style'=>'float:left;'),
							'<i class="fas fa-tags" id="dragndrop_tooltip_trigger" title="'.js_escape($lang['report_builder_67']).'"></i> ' .
							$lang['report_builder_29']
						) .
						// Quick Add button
						RCView::div(array('style'=>'float:left;margin-left:40px;'),
							RCView::button(array('class'=>'jqbuttonsm', 'style'=>'color:green;font-size:11px !important;', 'onclick'=>"openQuickAddDialog(); return false;"),
								'<i class="fas fa-plus fs10"></i> ' .$lang['report_builder_136']
							)
						) .
						// Drop-down to add all fields from a given form
						RCView::div(array('style'=>'float:right;margin:0 20px 0 0;font-size:11px;color:#222;font-weight:normal;'),
							$lang['report_builder_102'] .
							RCView::select(array('id'=>'add_form_field_dropdown', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:240px;margin-left:6px;font-size:11px;',
								'onchange'=>"addFormFieldsToReport(this.value);"), $addFormFieldsDropDownOptions, '')
						) .
						RCView::div(array('class'=>'clear'), '')
					)
				);
		// Invisible drop-down used as the single field drop-down used for all the fields for Step 2
		print   RCView::tr(array('class'=>'hide'),
					RCView::td(array('colspan'=>4, 'id'=>'field-dropdown-row'),
						RCView::span(array('id'=>'field-dropdown-container'),
							// Invisible ropdown/text box used as the basis from choosing fields
							self::outputFieldDropdown($rc_field_dropdown_options) . 
							self::outputFieldAutoSuggest()
						)
					)
				);
		// Fill rows of fields (only for existing reports)
		foreach ($report['fields'] as $this_field)
		{
			print   RCView::tr(array('class'=>'field_row'),
						// "Field X"
						RCView::td(array('class'=>'labelrc '.($this_field != '' ? 'dragHandle' : ''), 'style'=>'width:120px;'),
							RCView::div(array('style'=>'line-height:20px;'),
								RCView::span(array('style'=>'margin-left:25px;'), $lang['graphical_view_23'] . " ") .
								RCView::span(array('class'=>'field_num'), $field_counter++)
							)
						) .
						// Dropdown/text box
						RCView::td(array('class'=>'labelrc', 'colspan'=>2),
							RCView::div(array('class'=>'field-auto-suggest-div', 'style'=>''),
								''
							) .
							RCView::div(array('class'=>'field-dropdown-div', 'style'=>''),
								RCView::div(array('class'=>'nowrap', 'style'=>'float:left;'),
									// Disabled input (visible) - only for display purposes
									RCView::span(array('onclick'=>"editReportField($(this),false);"),
										RCView::text(array('class'=>'x-form-text x-form-field field-dropdown', 'placeholder'=>$lang['report_builder_30'],
														   'value'=>$rc_field_labels[$this_field]))
									) .
									// Real input (hidden)
									RCView::hidden(array('class'=>'field-hidden', 'name'=>'field[]', 'value'=>$this_field)) .
									// Buttons to switch auto-complete/drop-down mode
									RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"editReportField($(this),false);return false;", 'style'=>'display:none;font-size:11px;'),
										RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
									) .
									RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm field-dropdown-a', 'onclick'=>"editReportField($(this),true);return false;", 'style'=>'font-size:11px;'),
										RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
									)
								) .
								RCView::div(array('class'=>'fn'),
									RCView::span(array('class'=>'fna'), $lang['design_493']) .
									RCView::span(array('class'=>'fnb'),
										($this_field == '' ? '' : $Proj->forms[$Proj->metadata[$this_field]['form_name']]['menu'])
									)
								) .
								RCView::div(array('class'=>'clear'), '')
							)
						) .
						// Delete
						RCView::td(array('class'=>'labelrc', 'style'=>'text-align:center;width:25px;'),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteReportField($(this));", 'style'=>($this_field == '' ? 'display:none;' : '')),
                                    '<i class="fas fa-times opacity75 fs15" style="color:#A00000;" title="'.js_escape($lang['design_170']).'"></i>'
							)
						)
					);
		}

		## ADDITIONAL FIELDS (OUTPUT DAG NAMES, OUTPUT SURVEY FIELDS)
		$dags = $Proj->getUniqueGroupNames();
		if (!empty($dags)) {
			$outputDagChecked = ($report['output_dags']) ? 'checked' : '';
			$exportDagSurveyFieldsOptions .=  RCView::div(array('style'=>'margin:0 0 4px 20px;text-indent:-18px;'),
												RCView::checkbox(array('name'=>'output_dags', $outputDagChecked=>$outputDagChecked)) .
												$lang['data_export_tool_178']
											  );
		}
		if ($surveys_enabled) {
			$outputSurveyFieldsChecked = ($report['output_survey_fields']) ? 'checked' : '';
			$exportDagSurveyFieldsOptions .=  RCView::div(array('style'=>'margin:0 0 4px 20px;text-indent:-18px;'),
												RCView::checkbox(array('name'=>'output_survey_fields', $outputSurveyFieldsChecked=>$outputSurveyFieldsChecked)) .
												$lang['data_export_tool_179']
											  );
		}
		$combineCheckboxValuesChecked = ($report['combine_checkbox_values']) ? 'checked' : '';
		$exportDagSurveyFieldsOptions .= RCView::div(array('style'=>'margin:0 0 4px 20px;text-indent:-18px;'),
											RCView::checkbox(array('name'=>'combine_checkbox_values', $combineCheckboxValuesChecked=>$combineCheckboxValuesChecked)) .
											$lang['report_builder_149']
										 );
		if (!empty($missingDataCodes)) {
			$outputMissingDataCodesChecked = ($report['output_missing_data_codes']) ? 'checked' : '';
			$exportDagSurveyFieldsOptions .=  RCView::div(array('style'=>'margin:0 0 4px 20px;text-indent:-18px;'),
				RCView::checkbox(array('name'=>'output_missing_data_codes', $outputMissingDataCodesChecked=>$outputMissingDataCodesChecked)) .
				$lang['missing_data_14']
			);
		}
		$removeLineBreaksInValuesChecked = ($report['remove_line_breaks_in_values']) ? 'checked' : '';
		$exportDagSurveyFieldsOptions .=  RCView::div(array('style'=>'margin:0 0 4px 20px;text-indent:-18px;'),
			RCView::checkbox(array('name'=>'remove_line_breaks_in_values', $removeLineBreaksInValuesChecked=>$removeLineBreaksInValuesChecked)) .
			$lang['report_builder_156']
		);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
						'style'=>'background:#fff;border-left:0;border-right:0;height:5px;'), '')
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						RCView::div(array('style'=>'float:left;'),
							'<i class="fas fa-tag"></i> ' .
							$lang['report_builder_148'] . " " .
							RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>'font-weight:normal;padding:8px;'),
						$exportDagSurveyFieldsOptions
					)
				);
		
		
		
		
		

		## LIMTERS
		// "Limiters" section header
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'),
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#e0e0e0;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 3"
						)
					)
				);
		// Longitudinal only (or if has repeating instruments): Allow user to set filter type (record-level filtering or event-level filtering)
		$filter_type_options = "";
		if ($Proj->longitudinal || $Proj->hasRepeatingForms()) 
		{
			if ($Proj->longitudinal && $Proj->hasRepeatingForms()) {
				$filter_type_text = $lang['data_export_tool_222'];
			} elseif (!$Proj->longitudinal && $Proj->hasRepeatingForms()) {
				$filter_type_text = $lang['data_export_tool_223'];
			} else {
				$filter_type_text = $lang['data_export_tool_191'];
			}
			$filter_type_event_checked = ($report['filter_type'] != 'EVENT') ? "checked" : "";
			$filter_type_options = 	RCView::div(array('style'=>'font-size:13px;margin:8px 0 20px;'),
										RCView::checkbox(array('name'=>'filter_type', 'style'=>'margin-right:2px;', $filter_type_event_checked=>$filter_type_event_checked)) .
										$filter_type_text .
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['form_renderer_02'], 'onclick'=>"simpleDialog(null,null,'eventLevelFilter_dialog',600);"), '?')
									);
		}
		// Limiters header
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>2, 'valign'=>'bottom', 'style'=>'border-right:0;'),
						$filter_type_options .
						'<i class="fas fa-filter"></i> ' .
						$lang['report_builder_35'] . " " .
						RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
					) .
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>2, 'valign'=>'bottom', 'style'=>'border-left:0;'),
						// Help link
						RCView::div(array('id'=>'how_to_filters_link', 'style'=>'text-align:right;margin:2px 5px 6px 0;'.($Proj->longitudinal ? 'margin-bottom:15px;' : '').($report['advanced_logic'] != '' ? 'display:none;' : '')),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"simpleDialog(null,null,'filter_help',600);fitDialog($('#filter_help'));", 'style'=>'vertical-align:middle;font-weight:normal;color:#3E72A8;'),
                                '<i class="fas fa-question-circle"></i> ' .$lang['report_builder_119']
							)
						) .
						// "Operator / Value" text
						RCView::div(array('id'=>'oper_value_hdr', 'style'=>($report['advanced_logic'] != '' ? 'display:none;' : '')),
							$lang['report_builder_19']
						)
					)
				);
		// Fill rows of limiter fields (only for existing reports)
		$limiter_group_operator_options = array("OR"=>$lang['global_46'], "AND"=>$lang['global_87']);
		$limiter_field_num = 0;
		foreach ($report['limiter_fields'] as $attr)
		{
			// If doing a new "AND" group, then display extra row (but if not, then keep hidden via CSS)
			$display_limiter_and_row = ($limiter_field_num > 0 && $attr['limiter_group_operator'] == 'AND');
			// Render "AND" row
			print   RCView::tr(array('class'=>'limiter_and_row'.($report['advanced_logic'] != '' ? ' hidden' : ''), 'style'=>($display_limiter_and_row ? '' : 'display:none;')),
						RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'style'=>'padding:8px 60px;background:#ddd;'),
							RCView::select(array('lgo'=>$limiter_counter, 'class'=>'lgoc x-form-text x-form-field', 'style'=>'color:#800000;',
								'onchange'=>"displaylimiterGroupOperRow($(this));"), $limiter_group_operator_options, $attr['limiter_group_operator'])
						)
					 );
			// Render row
			print   RCView::tr(array('class'=>'limiter_row'.($report['advanced_logic'] != '' ? ' hidden' : '')),
						// Label
						RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
							// AND/OR limiter operator dropdown
							RCView::span(array('style'=>'margin:0;'.(($limiter_field_num == 0 || $attr['limiter_group_operator'] == 'AND') ? 'visibility:hidden;' : '')),
								RCView::select(array('name'=>'limiter_group_operator[]', 'lgo'=>$limiter_counter, 'class'=>'lgoo x-form-text x-form-field', 'style'=>'font-size:11px;padding: 0 0 0 2px;',
									'onchange'=>"displaylimiterGroupOperRow($(this));"), $limiter_group_operator_options, $attr['limiter_group_operator'])
							) .
							// "Filter X"
							RCView::span(array('style'=>'margin-left:10px;'),
								$lang['report_builder_31'] . " " .
								RCView::span(array('class'=>'limiter_num'), $limiter_counter++)
							)
						) .
						RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
							// Text box auto suggest
							RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($attr['field_name'] != '' ? 'display:none;' : '')),
								self::outputFieldAutoSuggest() .
								RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm limiter-dropdown-a', 'onclick'=>"showLimiterFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'),
									RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
								)
							) .
							// Drop-down list
							RCView::div(array('class'=>'limiter-dropdown-div nowrap', 'style'=>($attr['field_name'] == '' ? 'display:none;' : '')),
								self::outputLimiterDropdown($rc_field_dropdown_options, $attr['field_name']) .
								RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showLimiterFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'),
									RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
								)
							) .
							// Event drop-down
							(!$Proj->longitudinal ? '' :
								RCView::div(array('style'=>'margin-top:4px;'),
									RCView::span(array('style'=>'font-weight:normal;margin:0 8px 0 3px;color:#444;'), $lang['global_107'] ) .
									self::outputEventDropdown($event_dropdown_options_with_all, $attr['limiter_event_id'])
								)
							)
						) .
						RCView::td(array('class'=>'labelrc nowrap', 'valign'=>'top'),
							// Operator drop-down list (>, <, =, etc.)
							self::outputLimiterOperatorDropdown($attr['field_name'], $attr['limiter_operator'], $allValTypes) .
							// Value text box OR drop-down list (if multiple choice)
							self::outputLimiterValueTextboxOrDropdown($attr['field_name'], $attr['limiter_value'])
						) .
						// Delete
						RCView::td(array('class'=>'labelrc', 'style'=>'text-align:center;width:25px;'),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteLimiterField($(this));", 'style'=>($attr['field_name'] == '' ? 'display:none;' : '')),
                                '<i class="fas fa-times opacity75 fs15" style="color:#A00000;" title="'.js_escape($lang['design_170']).'"></i>'
							)
						)
					);
			$limiter_field_num++;
		}
		## ADVANCED LOGIC TEXTBOX
		print   RCView::tr(array('id'=>'adv_logic_row_link', 'style'=>($report['advanced_logic'] != '' ? 'display:none;' : '')),
					RCView::td(array('colspan'=>'4', 'class'=>'labelrc', 'style'=>'padding:10px;color:#444;font-weight:normal;'),
						RCView::img(array('src'=>'arrow_circle_double_gray.gif')) .
						$lang['report_builder_92'] . RCView::SP . RCView::SP .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-weight:normal;', 'onclick'=>"showAdvancedLogicRow(true,false)"),
							$lang['report_builder_90']
						)
					)
				);
		print   RCView::tr(array('id'=>'adv_logic_row', 'style'=>($report['advanced_logic'] == '' ? 'display:none;' : '')),
					// Label
					RCView::td(array('colspan'=>'4', 'class'=>'labelrc', 'style'=>'padding:10px 10px 0;'),
						// AND/OR limiter operator dropdown
						RCView::div(array('style'=>'margin:0 0 4px;'),
							RCView::div(array('style'=>'float:left;'),
								$lang['report_builder_93']
							) .
							RCView::div(array('style'=>'margin:0 30px;float:right;'),
								RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-size:11px;font-weight:normal;', 'onclick'=>"helpPopup('ss69');"),
									$lang['dataqueries_79']
								)
							) .
							RCView::div(array('style'=>'float:right;font-size:11px;color:#666;font-weight:normal;'),
								'(e.g., [age] > 30 and [sex] = "1")'
							) .
							RCView::div(array('class'=>'clear'), '')
						) .
						// Logic textbox
						RCView::textarea(array('id'=> 'advanced_logic', 'name'=>'advanced_logic', 'class'=>'x-form-field notesbox', 'style'=>'width:95%;height:75px;resize:auto;', 'onkeydown' => 'logicSuggestSearchTip(this, event);', 'onblur'=>"logicHideSearchTip(this); check_advanced_logic();"), $report['advanced_logic']) .
						logicAdd("advanced_logic").
						RCView::div(array('style'=>'border: 0; font-weight: bold; text-align: left; vertical-align: middle; height: 20px;', 'id'=>'advanced_logic_Ok'), '&nbsp;')

					)
				);
		print   RCView::tr(array('id'=>'adv_logic_row_link2', 'style'=>($report['advanced_logic'] == '' ? 'display:none;' : '')),
					RCView::td(array('colspan'=>'4', 'class'=>'labelrc', 'style'=>'padding:10px;color:#444;font-weight:normal;'),
						RCView::img(array('src'=>'arrow_circle_double_gray.gif')) .
						$lang['report_builder_92'] . RCView::SP . RCView::SP .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-weight:normal;', 'onclick'=>"showAdvancedLogicRow(false)"),
							$lang['report_builder_91']
						)
					)
				);

		## ADDITIONAL FILTERS (only if has events and/or DAGs)
		if ($Proj->longitudinal || !empty($dag_dropdown_options))
		{
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
							'style'=>'background:#fff;border-left:0;border-right:0;height:5px;'), '')
					);
			// "Additional filters" section header
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
							RCView::div(array('style'=>'float:left;'),
								'<i class="fas fa-filter"></i> ' .
								$lang['report_builder_36'] . " " .
								RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
							) .
							RCView::div(array('style'=>'float:right;margin:0 20px 0 0;font-size:11px;color:#555;font-weight:normal;'),
								$lang['report_builder_106']
							) .
							RCView::div(array('class'=>'clear'), '')
						)
					);
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>''),
							// FILTER EVENTS
							RCView::div(array('style'=>(!$Proj->longitudinal ? 'display:none;' : '').'float:left;margin-bottom:5px;'),
								RCView::span(array('style'=>'margin:0 10px 0 20px;vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
									$lang['report_builder_38']
								) .
								RCView::select(array('multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:80px;',
								'id'=>'filter_events', 'name'=>'filter_events'), $event_dropdown_options, $report['limiter_events'], 200)
							) .
							// FILTER DAGS
							RCView::div(array('style'=>(empty($dag_dropdown_options) ? 'display:none;' : '').'float:left;margin-bottom:5px;'),
								RCView::span(array('style'=>'margin:0 10px 0 '.($Proj->longitudinal ? '50px;' : '20px;').'vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
									$lang['report_builder_39']
								) .
								RCView::select(array('multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:80px;',
								'id'=>'filter_dags', 'name'=>'filter_dags'), $dag_dropdown_options, $report['limiter_dags'], 200)
							)
						)
					);
		}

		## LIVE FILTERS
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
						'style'=>'background:#fff;border-left:0;border-right:0;height:5px;'), '')
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						RCView::div(array('style'=>'float:left;'),
							'<i class="fas fa-bolt"></i> ' .
							$lang['report_builder_142'] . " " .
							RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
						) .
						RCView::div(array('style'=>'width:520px;float:right;margin:0 20px 0 0;font-size:11px;color:#555;font-weight:normal;'),
							$lang['report_builder_151']
						) .
						RCView::div(array('class'=>'clear'), '')
					)
				);
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;text-align:right;'),
						RCView::span(array('style'=>'padding-right:16px;'), $lang['report_builder_144'] . " 1")
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>'3'),
						RCView::div(array('class'=>'livefilter-dropdown-div nowrap'),
							self::outputLiveFilterDropdown($rc_field_dropdown_options_live_filter, $report['dynamic_filter1'])
						)
					)
				);
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;text-align:right;'),
						RCView::span(array('style'=>'padding-right:16px;'), $lang['report_builder_144'] . " 2")
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>'3'),
						RCView::div(array('class'=>'livefilter-dropdown-div nowrap'),
							self::outputLiveFilterDropdown($rc_field_dropdown_options_live_filter, $report['dynamic_filter2'])
						)
					)
				);
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;text-align:right;'),
						RCView::span(array('style'=>'padding-right:16px;'), $lang['report_builder_144'] . " 3")
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>'3'),
						RCView::div(array('class'=>'livefilter-dropdown-div nowrap'),
							self::outputLiveFilterDropdown($rc_field_dropdown_options_live_filter, $report['dynamic_filter3'])
						)
					)
				);

		## SORTING FIELDS USED IN REPORT
		// "Sorting" section header
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom',
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'),
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#e0e0e0;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 4"
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						'<i class="fas fa-sort-alpha-down fs15"></i> ' .
						$lang['report_builder_20'] . " " .
						RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
					)
				);
		// SORT FIELD 1
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
						$lang['report_builder_25']
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
						RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($report['orderby_field1'] != '' ? 'display:none;' : '')),
							self::outputFieldAutoSuggest() .
							RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm sort-dropdown-a', 'onclick'=>"showSortFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'),
								RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
							)
						) .
						RCView::div(array('class'=>'sort-dropdown-div nowrap', 'style'=>($report['orderby_field1'] == '' ? 'display:none;' : '')),
							self::outputSortingDropdown($rc_field_dropdown_options_orderby, $report['orderby_field1']) .
							RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showSortFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'),
								RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
							)
						)
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>2),
						self::outputSortAscDescDropdown($report['orderby_sort1'])
					)
				);
		// SORT FIELD 2
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
						$lang['report_builder_26']
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
						RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($report['orderby_field2'] != '' ? 'display:none;' : '')),
							self::outputFieldAutoSuggest() .
							RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm sort-dropdown-a', 'onclick'=>"showSortFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'),
								RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
							)
						) .
						RCView::div(array('class'=>'sort-dropdown-div nowrap', 'style'=>($report['orderby_field2'] == '' ? 'display:none;' : '')),
							self::outputSortingDropdown($rc_field_dropdown_options_orderby, $report['orderby_field2']) .
							RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showSortFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'),
								RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
							)
						)
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>2),
						self::outputSortAscDescDropdown($report['orderby_sort2'])
					)
				);
		// SORT FIELD 3
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
						$lang['report_builder_26']
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
						RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($report['orderby_field3'] != '' ? 'display:none;' : '')),
							self::outputFieldAutoSuggest() .
							RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm sort-dropdown-a', 'onclick'=>"showSortFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'),
								RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
							)
						) .
						RCView::div(array('class'=>'sort-dropdown-div nowrap', 'style'=>($report['orderby_field3'] == '' ? 'display:none;' : '')),
							self::outputSortingDropdown($rc_field_dropdown_options_orderby, $report['orderby_field3']) .
							RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showSortFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'),
								RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
							)
						)
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>2),
						self::outputSortAscDescDropdown($report['orderby_sort3'])
					)
				);

		// Set table html
		print     "</table>
					</form>" .
					RCView::div(array('style'=>'text-align:center;margin:30px 0 50px;'),
						RCView::button(array('class'=>'btn btn-primaryrc', 'style'=>'font-size:15px !important;', 'onclick'=>"saveReport($report_id);"),
							$lang['report_builder_27']
						) .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;margin-left:20px;font-size:13px;', 'onclick'=>'history.go(-1);return false;'),
							$lang['global_53']
						)
					) .
				"</div>";
	}


	// Output the limiter value text box OR drop-down list (if multiple choice)
	public static function outputLimiterValueTextboxOrDropdown($field, $limiter_value="")
	{
		global $Proj, $missingDataCodes, $lang;
		// For last field ("add new limiter"), disable the element
		$disabled = ($field == "") ? "disabled" : "";
		if ($field != '' && ($Proj->isMultipleChoice($field) || $Proj->metadata[$field]['element_type'] == 'sql')) {
			// Build enum options
			$enum = $Proj->metadata[$field]['element_enum'];
			$options = ($Proj->metadata[$field]['element_type'] == 'sql') ? parseEnum(getSqlFieldEnum($enum)) : parseEnum($enum);
			// If has missing data codes, then add as choices too
			if (!empty($missingDataCodes)) {
			    $action_tags = explode(" ", $Proj->metadata[$field]['misc']);
				if (!in_array('@NOMISSING', $action_tags)) {
				    $options = $options+array($lang['missing_data_04'] . $lang['colon']=>$missingDataCodes);
                }
            }
			// Remove any HTML tags
			foreach ($options as $key=>$option) {
			    if (is_array($option)) {
					foreach ($option as $key2=>$option2) {
						$options[$key][$key2] = strip_tags($option2);
					}
                } else {
					$options[$key] = strip_tags($option);
				}
			}
			// Make sure it has a blank option at the beginning (EXCEPT checkboxes and form status fields)
			if (!$Proj->isFormStatus($field) && $Proj->metadata[$field]['element_type'] != 'checkbox') {
				$options = array(''=>'') + $options;
			}
			// Multiple choice drop-down
			return RCView::select(array('name'=>'limiter_value[]', $disabled=>$disabled, 'class'=>'x-form-text x-form-field limiter-value', 'style'=>'max-width:150px;'), $options, $limiter_value, 200);
		}
		// Text field
		else {
			// If field has validation, then add its validation as onblur
			$val_type = (isset($field) && $field && $Proj->metadata[$field]['element_type'] == 'text') ? $Proj->metadata[$field]['element_validation_type'] : '';
			$onblur = "";
			if ($val_type != '') {
				// Convert legacy validation types
				if ($val_type == 'int') $val_type = 'integer';
				elseif ($val_type == 'float') $val_type = 'number';
				// Add onblur
				$onblur = "if(applyValdtn(this)) redcap_validate(this,'{$Proj->metadata[$field]['element_validation_min']}','{$Proj->metadata[$field]['element_validation_max']}','hard','$val_type',1)";
			}
			// If an MDY or DMY date/time field, then convert value
			if ($limiter_value != '') {
				if (substr($val_type, 0, 4) == 'date' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_dmy')) {
					// Convert to MDY or DMY format
					$limiter_value = DateTimeRC::datetimeConvert($limiter_value, 'ymd', substr($val_type, -3));
				}
			}
			// Adjust text box size for date/time fields
			if (strpos($val_type, 'datetime_seconds') === 0) {
				$style = 'max-width:130px;';
			} elseif (strpos($val_type, 'datetime') === 0) {
				$style = 'max-width:113px;';
			} elseif (strpos($val_type, 'date') === 0) {
				$style = 'max-width:80px;';
			} else {
				$style = 'max-width:150px;';
			}
			// Build date/time format text for date/time fields
			$dformat = MetaData::getDateFormatDisplay($val_type);
			$dformat_span = ($dformat == '') ? '' : RCView::span(array('class'=>'df', 'style'=>'padding-left:4px;'), $dformat);
			// Return text field
			return 	RCView::text(array('name'=>'limiter_value[]', $disabled=>$disabled, 'onblur'=>$onblur, 'class'=>$val_type.' x-form-text x-form-field limiter-value',
									  'maxlength'=>255, 'style'=>$style, 'value'=>htmlspecialchars($limiter_value, ENT_QUOTES))) .
					$dformat_span;
		}
	}


	// Output html of text field with auto-suggest feature
	public static function outputFieldAutoSuggest()
	{
		global $lang;
		// Output the html
		return RCView::text(array('class'=>'x-form-text x-form-field field-auto-suggest', 'style'=>'width:260px;',
					'onblur'=>'asblur(this)', 'onkeydown'=>'asdown(event)', 'placeholder'=>$lang['report_builder_30']));
	}


	// Output html of field drop-down displaying all project fields
	public static function outputFieldDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'width:100%;max-width:260px;',
					'id'=>'field-dropdown', 'onblur'=>"resetRow1($(this));", 'onchange'=>($selectedField == "" ? "rprtft='dropdown';addNewReportRow($(this));" : "")), $options, $selectedField, 200);
	}


	// Output html of event drop-down displaying all project fields
	public static function outputEventDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field event-dropdown', 'style'=>'width:240px;max-width:240px;',
					'name'=>'limiter_event[]'), $options, $selectedField, 200);
	}


	// Output html of limiter drop-down displaying all project fields
	public static function outputLimiterDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field limiter-dropdown', 'style'=>'width:100%;max-width:260px;',
					'name'=>'limiter[]', 'onchange'=>($selectedField == "" ? "rprtft='dropdown';addNewLimiterRow($(this));" : "")."fetchLimiterOperVal($(this));"),
					$options, $selectedField, 200);
	}


	// Output html of sorting drop-down displaying all project fields
	public static function outputSortingDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field sort-dropdown', 'style'=>'width:100%;max-width:260px;',
					'name'=>'sort[]'), $options, $selectedField, 200);
	}


	// Output html of Live Filter drop-down displaying MC fields + DAG option
	public static function outputLiveFilterDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field livefilter-dropdown', 'style'=>'width:100%;max-width:260px;',
					'name'=>'livefilter[]'), $options, $selectedField, 200);
	}


	// Output array of ALL possible limiter operators
	public static function getLimiterOperators()
	{
		global $lang;
		// List of ALL possible options
		return array('E'=>'=', 'NE'=>'not =', 'LT'=>'< ', 'LTE'=>'< =', 'GT'=>'>', 'GTE'=>'> =',
					 'CONTAINS'=>$lang['report_builder_34'], 'NOT_CONTAIN'=>$lang['report_builder_88'], 'STARTS_WITH'=>$lang['report_builder_79'],
					 'ENDS_WITH'=>$lang['report_builder_86'], 'CHECKED'=>$lang['report_builder_64'], 'UNCHECKED'=>$lang['report_builder_65']);
	}


	// Output html of limiter operator drop-down displaying all valid operators
	public static function outputLimiterOperatorDropdown($field, $selectedField="", $allValTypes)
	{
		global $lang, $Proj;
		// Set options based upon field type
		$field_type = isset($field) && $field ? $Proj->metadata[$field]['element_type'] : '';
		$val_type = isset($field) && $field ? $Proj->metadata[$field]['element_validation_type'] : '';
		if ($val_type == 'int') $val_type = 'integer';
		elseif ($val_type == 'float') $val_type = 'number';
		$data_type = $val_type ? $allValTypes[$val_type]['data_type'] : '';
		if ($Proj->isCheckbox($field)) {
			// Checkbox
			$options_this_field = array('CHECKED', 'UNCHECKED');
		} elseif ($Proj->isMultipleChoice($field) || $field_type == 'sql') {
			// MC fields (excluding checkboxes)
			$options_this_field = array('E', 'NE');
		} elseif ( in_array($field_type, array('slider', 'calc'))
				|| in_array($data_type, array('integer', 'number', 'date', 'datetime', 'datetime_seconds'))) {
				// || in_array($data_type, array('integer', 'number', 'number_comma_decimal', 'date', 'datetime', 'datetime_seconds'))) {
			// Date/times and numbers/integers (including sliders, calcs)
			$options_this_field = array('E', 'NE', 'LT', 'LTE', 'GT', 'GTE');
		} else {
			// Free-form text
			$options_this_field = array('E', 'NE', 'CONTAINS', 'NOT_CONTAIN', 'STARTS_WITH', 'ENDS_WITH');
		}
		// List of ALL possible options
		$all_options = self::getLimiterOperators();
		// Loop through all options to build field-specific drop-down list
		$options = array();
		foreach ($all_options as $key=>$val) {
			if (in_array($key, $options_this_field)) $options[$key] = $val;
		}
		// For last field ("add new limiter"), disable the element
		$disabled = ($field == "") ? "disabled" : "";
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field limiter-operator', $disabled=>$disabled,
					'name'=>'limiter_operator[]'), $options, $selectedField, 200);
	}


	// Output html of sorting drop-down displaying option as ascending or descending
	public static function outputSortAscDescDropdown($selectedField="ASC")
	{
		global $lang;
		// Set options
		$options = array('ASC'=>$lang['report_builder_22'], 'DESC'=>$lang['report_builder_23']);
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field sort-ascdesc', 'style'=>'',
					'name'=>'sortascdesc[]', 'onchange'=>""), $options, $selectedField, 200);
	}


	// Get auto suggest JavaScript string for all project fields
	public static function getAutoSuggestJsString()
	{
		global $Proj;
		// Build an array of listing all REDCap fields' variable name + field label
		$rc_fields = array();
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Skip descriptive fields
			if ($attr1['element_type'] == 'descriptive') continue;
			// Add to fields array
			$rc_fields[] = "'$this_field " . json_encode_rc(str_replace("'", '', strip_tags($attr1['element_label']))) . "'";
		}
		return "[ " . implode(", ", $rc_fields) . " ]";
	}


	// Checks for errors in the report order of all reports (in case their numbering gets off)
	public static function checkReportOrder()
	{
		// Do a quick compare of the field_order by using Arithmetic Series (not 100% reliable, but highly reliable and quick)
		// and make sure it begins with 1 and ends with field order equal to the total field count.
		$sql = "select sum(report_order) as actual, round(count(1)*(count(1)+1)/2) as ideal,
				min(report_order) as min, max(report_order) as max, count(1) as report_count
				from redcap_reports where project_id = " . PROJECT_ID;
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		db_free_result($q);
		if ( ($row['actual'] != $row['ideal']) || ($row['min'] != '1') || ($row['max'] != $row['report_count']) )
		{
			return self::fixReportOrder();
		}
	}


	// Fixes the report order of all reports (if somehow their numbering gets off)
	public static function fixReportOrder()
	{
		// Set all report_orders to null
		$sql = "select @n := 0";
		db_query($sql);
		// Reset field_order of all fields, beginning with "1"
		$sql = "update redcap_reports
				set report_order = @n := @n + 1 where project_id = ".PROJECT_ID."
				order by report_order, report_id";
		db_query($sql);
		// Return boolean on success
		return true;
	}


	// Return all reports (unless one is specified explicitly) as an array of their attributes
	public static function getReports(	$report_id=null,
										// The parameters below are ONLY used for $report_id == 'SELECTED'
										$selectedInstruments=array(), $selectedEvents=array())
	{
		global $Proj, $lang, $double_data_entry, $user_rights;

		// Get REDCap validation types
		$valTypes = getValTypes();

		// Array to place report attributes
		$reports = array();
		// If report_id is 0 (report doesn't exist), then return field defaults from tables
		if ($report_id === 0 || $report_id == 'ALL' || $report_id == 'SELECTED') {
			// Add to reports array
			$reports[$report_id] = getTableColumns('redcap_reports');
			// Pre-fill empty slots for limiters and fields
			$reports[$report_id]['fields'] = array();
			$reports[$report_id]['limiter_fields'] = array();
			$reports[$report_id]['limiter_dags'] = array();
			$reports[$report_id]['limiter_events'] = array();
			$reports[$report_id]['limiter_logic'] = "";
			$reports[$report_id]['user_access_users'] = array();
			$reports[$report_id]['user_access_roles'] = array();
			$reports[$report_id]['user_access_dags'] = array();
			$reports[$report_id]['user_edit_access_users'] = array();
			$reports[$report_id]['user_edit_access_roles'] = array();
			$reports[$report_id]['user_edit_access_dags'] = array();
			$reports[$report_id]['output_dags'] = 0;
			$reports[$report_id]['output_survey_fields'] = 0;
			$reports[$report_id]['output_missing_data_codes'] = 0;
			$reports[$report_id]['remove_line_breaks_in_values'] = 1;
			$reports[$report_id]['filter_type'] = 'RECORD';
			// Set additional settings for pre-defined reports
			if ($report_id === 'ALL') {
				// All data
				$reports[$report_id]['title'] = $lang['report_builder_80']." ".$lang['report_builder_84'];
				$reports[$report_id]['fields'] = array_keys($Proj->metadata);
			} elseif ($report_id === 'SELECTED') {
				// Selected instruments/events
				$reports[$report_id]['title'] = $lang['report_builder_81'] . ($Proj->longitudinal ? " ".$lang['report_builder_82'] : " ") . $lang['report_builder_83'];
				// If using "selected instrument/events" option for pre-defined report, get fields
				if (is_array($selectedInstruments) && !empty($selectedInstruments)) {
					// Make sure record ID is the pre-added as the first field
					$fields = array($Proj->table_pk);
					foreach ($selectedInstruments as $val) {
						if (isset($Proj->forms[$val])) {
							$fields = array_merge($fields, array_keys($Proj->forms[$val]['fields']));
						}
					}
					$reports[$report_id]['fields'] = array_unique($fields);
				}
				// If using "selected instrument/events" option for pre-defined report, get event_id's
				if (is_array($selectedEvents) && !empty($selectedEvents)) {
					$reports[$report_id]['limiter_events'] = $selectedEvents;
				}
			} else {
				// For "new" (to-be created) reports, set Record ID field as first field and first sorting field in report
				$reports[$report_id]['fields'] = array($Proj->table_pk);
				$reports[$report_id]['orderby_field1'] = $Proj->table_pk;
				$reports[$report_id]['orderby_sort1'] = 'ASC';
				## Report B
				// If instruments were passed in query string for SELECTED instruments, then auto-load all fields from those forms
				if (isset($_GET['instruments'])) {
					foreach (explode(",", $_GET['instruments']) as $this_form) {
						if (!isset($Proj->forms[$this_form])) continue;
						foreach (array_keys($Proj->forms[$this_form]['fields']) as $this_field) {
							// Skip record ID field and descriptive fields
							if ($this_field == $Proj->table_pk || $Proj->metadata[$this_field]['element_type'] == 'descriptive') continue;
							$reports[$report_id]['fields'][] = $this_field;
						}
					}
				}
				// If event_id's were passed in query string for SELECTED events, then preselect events in Additional Filters
				if ($Proj->longitudinal && isset($_GET['events'])) {
					foreach (explode(",", $_GET['events']) as $this_event_id) {
						if (!isset($Proj->eventInfo[$this_event_id])) continue;
						$reports[$report_id]['limiter_events'][] = $this_event_id;
					}
					// If ALL events have been selected, then just set as empty array (in case other events get created later + this does same thing)
					if (count($reports[$report_id]['limiter_events']) == count($Proj->eventInfo)) {
						$reports[$report_id]['limiter_events'] = array();
					}
				}
			}
			// DDE: If user is DDE person 1 or 2, then limit to ONLY their records
			if ($double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) {
				if ($reports[$report_id]['limiter_logic'] == '') {
					$reports[$report_id]['limiter_logic'] = "ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
				} else {
					$reports[$report_id]['limiter_logic'] = "({$reports[$report_id]['limiter_logic']}) and ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
				}
			}
			// Return array
			return $reports[$report_id];
		}

		// Get main attributes
		$sql = "select * from redcap_reports where project_id = ".PROJECT_ID;
		if (is_numeric($report_id)) $sql .= " and report_id = $report_id";
		$sql .= " order by report_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add to reports array
			$reports[$row['report_id']] = $row;
			// Pre-fill empty slots for limiters and fields
			$reports[$row['report_id']]['fields'] = array();
			$reports[$row['report_id']]['limiter_fields'] = array();
			$reports[$row['report_id']]['limiter_dags'] = array();
			$reports[$row['report_id']]['limiter_events'] = array();
			$reports[$row['report_id']]['limiter_logic'] = "";
			$reports[$row['report_id']]['user_access_users'] = array();
			$reports[$row['report_id']]['user_access_roles'] = array();
			$reports[$row['report_id']]['user_access_dags'] = array();
			$reports[$row['report_id']]['user_edit_access_users'] = array();
			$reports[$row['report_id']]['user_edit_access_roles'] = array();
			$reports[$row['report_id']]['user_edit_access_dags'] = array();
		}
		// If no reports, then return empty array
		if (empty($reports)) return array();

		// Get list of fields in report
		$sql = "select * from redcap_reports_fields where report_id in (" . prep_implode(array_keys($reports)) . ")
				order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If field does not (or no longer) exists in project, then skip it
			if (!isset($Proj->metadata[$row['field_name']])) continue;
			// It is limiter if has limiter_operator
			if ($row['limiter_operator'] != '') {
				// Just in case checkbox limiters that got grandfathered in from pre-6.0 have E or NE, convert to CHECKED or UNCHECKED.
				if ($Proj->isCheckbox($row['field_name']) && in_array($row['limiter_operator'], array('E', 'NE'))) {
					$row['limiter_operator'] = ($row['limiter_operator'] == 'E') ? 'CHECKED' : 'UNCHECKED';
				}
				// If field is a date/time field, then make sure the date/time is in correct format and not missing leading zeroes
				$thisValType = $Proj->metadata[$row['field_name']]['element_validation_type'];
				if ($thisValType != '' && $row['limiter_value'] != '' && in_array($valTypes[$thisValType]['data_type'], array('date', 'datetime', 'datetime_seconds'))) {
					// Separate value into date/time components
					list ($thisDate, $thisTime) = explode(" ", $row['limiter_value'], 2);
					// Fix date
					if (strlen($thisDate) < 10) {
						list ($y, $m, $d) = explode("-", $thisDate, 3);
						$thisDate = sprintf("%04d-%02d-%02d", $y, $m, $d);
					}
					// Fix time
					if ($thisTime != '') {
						if (substr_count($thisTime, ":") == 2) {
							// H:M:S
							list ($h, $m, $s) = explode(":", $thisTime, 3);
							$thisTime = sprintf("%02d:%02d:%02d", $h, $m, $s);
						} else {
							// H:M
							list ($h, $m) = explode(":", $thisTime, 2);
							$thisTime = sprintf("%02d:%02d", $h, $m);
						}
					}
					// Re-combine components
					$row['limiter_value'] = trim("$thisDate $thisTime");
				}
				// Limiter field
				$reports[$row['report_id']]['limiter_fields'][] = array(
						'field_name'=>$row['field_name'],
						'limiter_group_operator'=>$row['limiter_group_operator'],
						'limiter_event_id'=>$row['limiter_event_id'],
						'limiter_operator'=>$row['limiter_operator'],
						'limiter_value'=>$row['limiter_value']);
			} else {
				// Report field
				$reports[$row['report_id']]['fields'][] = $row['field_name'];
			}
		}
		// Get event filters
		$sql = "select * from redcap_reports_filter_events where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['limiter_events'][] = $row['event_id'];
		}
		// Get DAG filters
		$sql = "select * from redcap_reports_filter_dags where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['limiter_dags'][] = $row['group_id'];
		}
		// Get user access - users
		$sql = "select * from redcap_reports_access_users where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['user_access_users'][] = $row['username'];
		}
		// Get user access - roles
		$sql = "select * from redcap_reports_access_roles where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['user_access_roles'][] = $row['role_id'];
		}
		// Get user access - DAGs
		$sql = "select * from redcap_reports_access_dags where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['user_access_dags'][] = $row['group_id'];
		}
		// Get user edit access - users
		$sql = "select * from redcap_reports_edit_access_users where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['user_edit_access_users'][] = $row['username'];
		}
		// Get user edit access - roles
		$sql = "select * from redcap_reports_edit_access_roles where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['user_edit_access_roles'][] = $row['role_id'];
		}
		// Get user edit access - DAGs
		$sql = "select * from redcap_reports_edit_access_dags where report_id in (" . prep_implode(array_keys($reports)) . ")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']]['user_edit_access_dags'][] = $row['group_id'];
		}
		// Loop through all reports and build the filter logic into a single string
		foreach ($reports as $this_report_id=>$rattr)
		{
			// Advanced logic
			if ($rattr['advanced_logic'] != '') {
				$reports[$this_report_id]['limiter_logic'] = $rattr['advanced_logic'];
			}
			// Simple logic
			elseif (!empty($rattr['limiter_fields'])) {
				foreach ($rattr['limiter_fields'] as $i=>$attr) {
					// Translate the limiter item into logic
					$reports[$this_report_id]['limiter_logic'] .= ($attr['limiter_group_operator'] == 'AND' ? ($i == 0 ? "(" : ") AND (") : " OR ") .
																  self::translateLimiterItem($attr);
				}
				// Finish with ending parenthesis
				$reports[$this_report_id]['limiter_logic'] .= ")";
			}

			// DDE: If user is DDE person 1 or 2, then limit to ONLY their records by appending ends_with() onto limiter_logic
			if ($double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) {
				if ($reports[$this_report_id]['limiter_logic'] == '') {
					$reports[$this_report_id]['limiter_logic'] = "ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
				} else {
					$reports[$this_report_id]['limiter_logic'] = "({$reports[$this_report_id]['limiter_logic']}) and ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
				}
			}

			// Double check to make sure that it truly has SELECTED user access
			if ($rattr['user_access'] == 'SELECTED' && empty($rattr['user_access_users']) && empty($rattr['user_access_roles']) && empty($rattr['user_access_dags'])) {
				$reports[$this_report_id]['user_access'] = 'ALL';
			}
			if ($rattr['user_edit_access'] == 'SELECTED' && empty($rattr['user_edit_access_users']) && empty($rattr['user_edit_access_roles']) && empty($rattr['user_edit_access_dags'])) {
				$reports[$this_report_id]['user_edit_access'] = 'ALL';
			}

			// Make sure that Order By fields are NOT checkboxes (because that doesn't make sense)
			if ($Proj->isCheckbox($reports[$this_report_id]['orderby_field3'])) {
				$reports[$this_report_id]['orderby_field3'] = $reports[$this_report_id]['orderby_sort3'] = '';
			}
			if ($Proj->isCheckbox($reports[$this_report_id]['orderby_field2'])) {
				$reports[$this_report_id]['orderby_field2'] = $reports[$this_report_id]['orderby_field3'];
				$reports[$this_report_id]['orderby_sort2'] = $reports[$this_report_id]['orderby_sort3'];
				$reports[$this_report_id]['orderby_field3'] = $reports[$this_report_id]['orderby_sort3'] = '';
			}
			if ($Proj->isCheckbox($reports[$this_report_id]['orderby_field1'])) {
				$reports[$this_report_id]['orderby_field1'] = $reports[$this_report_id]['orderby_field2'];
				$reports[$this_report_id]['orderby_sort1'] = $reports[$this_report_id]['orderby_sort2'];
				$reports[$this_report_id]['orderby_field2'] = $reports[$this_report_id]['orderby_field3'];
				$reports[$this_report_id]['orderby_sort2'] = $reports[$this_report_id]['orderby_sort3'];
				$reports[$this_report_id]['orderby_field3'] = $reports[$this_report_id]['orderby_sort3'] = '';
			}
		}
		// Return array of report(s) attributes
		if ($report_id == null) {
			return $reports;
		} else {
			return $reports[$report_id];
		}
	}


	// Translate a single limiter item's attributes into its appropriate logic
	public static function translateLimiterItem($attr)
	{
		global $Proj;
		// If longitudinal, then get unique event name to prepend to field in logic
		$event_name = ($Proj->longitudinal && is_numeric($attr['limiter_event_id'])) ? "[".$Proj->getUniqueEventNames($attr['limiter_event_id'])."]" : "";
		// If is "contains", "not contain", "starts_with", or "ends_with"
		if (in_array($attr['limiter_operator'], array('CONTAINS', 'NOT_CONTAIN', 'STARTS_WITH', 'ENDS_WITH'))) {
			return strtolower($attr['limiter_operator'])."({$event_name}[{$attr['field_name']}], \"" . str_replace('"', "\\\"", $attr['limiter_value']) . "\")";
		}
		// If is "checked" or "unchecked"
		elseif ($attr['limiter_operator'] == 'CHECKED' || $attr['limiter_operator'] == 'UNCHECKED') {
			$checkVal = ($attr['limiter_operator'] == 'CHECKED') ? "1" : "0";
			return "{$event_name}[{$attr['field_name']}({$attr['limiter_value']})] = \"$checkVal\"";
		}
		// All mathematical operators
		else {
			// If value is numerical and using >, >=, <, or <=, then don't surround in double quotes
			$quotes = (is_numeric($attr['limiter_value']) && $attr['limiter_operator'] != 'E' && $attr['limiter_operator'] != 'NE') ? '' : '"';
			return "{$event_name}[{$attr['field_name']}] " . self::translateLimiterOperator($attr['limiter_operator']) .
						" $quotes" . str_replace('"', "\\\"", $attr['limiter_value']) . $quotes;
		}
	}


	// Translate backend limiter operator (LT, GTE, E) into mathematical operator (<, >=, =)
	public static function translateLimiterOperator($backend_value)
	{
		$all_options = array('E'=>'=', 'NE'=>'!=', 'LT'=>'<', 'LTE'=>'<=', 'GT'=>'>', 'GTE'=>'>=');
		return (isset($all_options[$backend_value]) ? $all_options[$backend_value] : 'E');
	}


	// Delete a report
	public static function deleteReport($report_id)
	{
		// Delete report
		$sql = "delete from redcap_reports where project_id = ".PROJECT_ID." and report_id = $report_id";
		$q = db_query($sql);
		if (!$q) return false;
		// Fix ordering of reports (if needed) now that this report has been removed
		self::checkReportOrder();
		// Logging
		Logging::logEvent($sql, "redcap_projects", "MANAGE", $report_id, "report_id = $report_id", "Delete report");
		// Return success
		return true;
	}


	// Copy the report and return the new report_id
	public static function copyReport($report_id)
	{
		// Set up all actions as a transaction to ensure everything is done here
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");
		$errors = 0;
		// List of all db tables relating to reports, excluding redcap_reports
		$tables = array('redcap_reports_access_dags', 'redcap_reports_access_roles', 'redcap_reports_access_users', 'redcap_reports_fields',
						'redcap_reports_filter_dags', 'redcap_reports_filter_events');
		// First, add row to redcap_reports and get new report id
		$table = getTableColumns('redcap_reports');
		// Get report attributes
		$report = self::getReports($report_id);
		// Remove report_id from arrays to prevent query issues
		unset($report['report_id'], $table['report_id']);
		// Append "(copy)" to title to differeniate it from original
		$report['title'] .= " (copy)";
		// Increment the report order so we can add new report directly after original
		$report['report_order']++;
		// Move all report orders up one to make room for new one
		$sql = "update redcap_reports set report_order = report_order + 1 where project_id = ".PROJECT_ID."
				and report_order >= ".$report['report_order']." order by report_order desc";
		if (!db_query($sql)) $errors++;
		// Loop through report attributes and add to $table to input into query
		foreach ($report as $key=>$val) {
			if (!array_key_exists($key, $table)) continue;
			$table[$key] = $val;
		}
		// Insert into reports table
		$sqlr = "insert into redcap_reports (".implode(', ', array_keys($table)).") values (".prep_implode($table, true, true).")";
		$q = db_query($sqlr);
		if (!$q) return false;
		$new_report_id = db_insert_id();
		// Now loop through all other report tables and add
		foreach ($tables as $table_name) {
			// Get columns/defaults for table
			$table = getTableColumns($table_name);
			// Remove report_id from $table_cols since we're manually adding it to the query
			unset($table['report_id']);
			// Convert columns to comma-delimited string to input into query
			$table_cols = implode(', ', array_keys($table));
			// Insert into table
			$sql = "insert into $table_name select $new_report_id, $table_cols from $table_name where report_id = $report_id";
			if (!db_query($sql)) $errors++;
		}
		// If errors, do not commit
		$commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
		db_query($commit);
		// Set back to initial value
		db_query("SET AUTOCOMMIT=1");
		if ($errors == 0) {
			// Just in case, make sure that all report orders are correct
			self::checkReportOrder();
			// Logging
			Logging::logEvent($sqlr, "redcap_projects", "MANAGE", $new_report_id, "report_id = $new_report_id\nCopied from report_id = $report_id", "Copy report");
		}
		// Return report_id of new report, else FALSE if errors occurred
		return ($errors == 0) ? $new_report_id : false;
	}


	// Get report names. Returns array with report_id as key and title as value
	public static function getReportNames($report_id=null, $applyUserAccess=false, $fixOrdering=true, $useFolderOrdering=true)
	{
		global $lang, $Proj, $user_rights;

		// Return pre-defined language for pre-defined reports (ALL, SELECTED)
		if ($report_id == 'ALL') {
			// All data
			return $lang['report_builder_80']." ".$lang['report_builder_84'];
		} elseif ($report_id == 'SELECTED') {
			// Selected instruments/events
			return $lang['report_builder_81'] . ($Proj->longitudinal ? " ".$lang['report_builder_82'] : "") . " " . $lang['report_builder_83'];
		}

		// Builder SQL to pull report_id and title in proper order
		if (!$applyUserAccess) {
			$sql = "select distinct r.report_id, r.title, r.report_order, af.folder_id, af.name as folder, af.position,
					r.dynamic_filter1, r.dynamic_filter2, r.dynamic_filter3
					from redcap_reports r 
					left join redcap_reports_folders_items ai on ai.report_id = r.report_id
					left join redcap_reports_folders af on af.folder_id = ai.folder_id
					where r.project_id = ".PROJECT_ID;
		} else {
			// Apply user access rights
			$sql = "select distinct r.report_id, r.title, r.report_order, af.folder_id, af.name as folder, af.position,
					r.dynamic_filter1, r.dynamic_filter2, r.dynamic_filter3
					from redcap_reports r
					left join redcap_reports_access_users au on au.report_id = r.report_id
					left join redcap_reports_access_roles ar on ar.report_id = r.report_id
					left join redcap_reports_access_dags ad on ad.report_id = r.report_id
					left join redcap_reports_folders_items ai on ai.report_id = r.report_id
					left join redcap_reports_folders af on af.folder_id = ai.folder_id
					where r.project_id = ".PROJECT_ID;
			// Array for WHERE components
			$sql_where_array = array();
			// Include reports with ALL access
			$sql_where_array[] = "r.user_access = 'ALL'";
			// Username check
            if (UserRights::isImpersonatingUser()) {
				$sql_where_array[] = "au.username = '".db_escape(UserRights::getUsernameImpersonating())."'";
			} else {
				$sql_where_array[] = "au.username = '".db_escape(USERID)."'";
			}
			// DAG check
			if (is_numeric($user_rights['group_id'])) $sql_where_array[] = "ad.group_id = ".$user_rights['group_id'];
			// Role check
			if (is_numeric($user_rights['role_id'])) $sql_where_array[] = "ar.role_id = ".$user_rights['role_id'];
			// Append WHERE to query
			$sql .= " and (" . implode(" or ", $sql_where_array) . ")";
		}
		$sql .= is_numeric($report_id) ? " and r.report_id = $report_id" : ($useFolderOrdering ? " order by af.position, r.report_order" : " order by r.report_order");
		$q = db_query($sql);

		// Add reports to array
		$reports = array();
		$reportsOutOfOrder = false;
		$counter = 1;
		while ($row = db_fetch_assoc($q)) {
			// Are any live filters the record ID field?
			$liveFilterRecordId = "";			
			for ($i = 1; $i <= self::MAX_LIVE_FILTERS; $i++) {
				if ($row['dynamic_filter'.$i] == $Proj->table_pk) {
					$liveFilterRecordId = $i;
				}
			}
			// Add to array
			$reports[] = array('report_id'=>$row['report_id'], 'title'=>$row['title'], 'folder_id'=>$row['folder_id'], 'folder'=>$row['folder'], 
							   'collapsed'=>0, 'liveFilterRecordId'=>$liveFilterRecordId);
			// Check report order
			if (is_numeric($row['position'])) {
				$fixOrdering = $reportsOutOfOrder = false;
			} elseif ($fixOrdering && $counter++ != $row['report_order'] && !$reportsOutOfOrder) {
				$reportsOutOfOrder = true;
			}
		}
		/* 
		// If report order is off, fix it
		if ($fixOrdering && $reportsOutOfOrder && self::fixReportOrder()) {
			// Since they're fixed, call this method recursively so that it outputs the fixed report order
			return self::getReportNames($report_id, $applyUserAccess, false);
		}
		*/
		// Return reports array
		if (is_numeric($report_id)) return $reports[0]['title'];
		else return $reports;
	}
	
	// Get list of all reports to which the user has EDIT ACCESS
	public static function getReportsEditAccess($username, $role_id, $dag_id, $report_id=null)
	{
		$reports_edit_access = array();
		$reports = self::getReports($report_id);
		if (is_numeric($report_id)) $reports = array($report_id=>$reports);
		foreach ($reports as $report_id=>$attr) 
		{
			if (SUPER_USER ||
				$attr['user_edit_access'] == 'ALL' ||
				in_array($username, $attr['user_edit_access_users']) ||
				in_array($role_id, $attr['user_edit_access_roles']) ||
				in_array($dag_id, $attr['user_edit_access_dags'])
			) {
				$reports_edit_access[] = $report_id;
			}
		}
		return $reports_edit_access;
	}


	// Get html table listing all reports
	public static function renderReportList()
	{
		global $Proj, $lang, $longitudinal, $user_rights, $enable_plotting;
		// Determine if user has API export rights
		$hasAPIrights = ($user_rights['api_export']);
		// Build drop-down of events
		$event_dropdown = "";
		if ($longitudinal) {
			$event_dropdown_options = array();
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				$event_dropdown_options[$this_event_id] = $attr['name_ext'];
			}
			// Multi-select list
			$event_dropdown = 	RCView::select(array('id'=>'export_selected_events', 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:400px;width:100%;font-size:11px;padding-right:15px;height:80px;', 'onmouseup'=>"
									var num_selected = $(this).find('option:selected').length;
									if (num_selected > 1) {
										$(this).find('option[value=\'\']').prop('selected', false);
									} else if (num_selected < 1) {
										$(this).find('option[value=\'\']').prop('selected', true);
									}
								"),
								(array(''=>'-- '.$lang['dataqueries_136'].' --') + $event_dropdown_options), '', 200);
		}
		// Build drop-down of instruments
		$instruments_options = array();
		foreach ($Proj->forms as $form=>$attr) {
			$instruments_options[$form] = strip_tags(label_decode($attr['menu']));
		}
		// Multi-select list
		$instrument_dropdown = 	RCView::select(array('id'=>'export_selected_instruments', 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:400px;width:100%;font-size:11px;padding-right:15px;height:80px;', 'onmouseup'=>"
									var num_selected = $(this).find('option:selected').length;
									if (num_selected > 1) {
										$(this).find('option[value=\'\']').prop('selected', false);
									} else if (num_selected < 1) {
										$(this).find('option[value=\'\']').prop('selected', true);
									}
								"),
								(array(''=>'-- '.$lang['report_builder_85'].' --') + $instruments_options), '', 200);
		// Get list of all reports to which the user has EDIT ACCESS
		$reports_edit_access = self::getReportsEditAccess(USERID, $user_rights['role_id'], $user_rights['group_id']);
		// Get list of reports to display as table (only apply user access filter if don't have Add/Edit Reports rights)
		$report_names1 = self::getReportNames(null, !$user_rights['reports'], true, false);
		//print_array(self::getReports());
		$report_names = array();
		foreach ($report_names1 as $attr) {
			$report_names[$attr['report_id']] = $attr['title'];
		}
		// Add pre-defined reports
		$predefined_reports = array('ALL'=>RCView::b($lang['report_builder_80'])." ".$lang['report_builder_84'],
									'SELECTED'=>RCView::b($lang['report_builder_81'] . ($longitudinal ? " ".$lang['report_builder_82'].RCView::br() : " ")) . $lang['report_builder_83']);
		// Loop through each report to render as a row
		$rows = array();
		$row_num = $item_num = 0; // loop counter
		foreach (($predefined_reports+$report_names) as $report_id=>$report_name)
		{
			// Determine if a pre-defined rule
			$isPredefined = !is_numeric($report_id);
			// First column
			$rows[$item_num][] = RCView::span(array('style'=>'display:none;'), $report_id);
			// Report order number
			$rows[$item_num][] = !$isPredefined ? ($row_num+1) : RCView::span(array('style'=>'color:#C00000;'), $report_id == 'ALL' ? 'A' : 'B');
			// Report title
			$rows[$item_num][] = RCView::div(array('class'=>'wrap', 'style'=>($isPredefined ? 'font-size:13px;padding:10px 0;' : 'font-size:12px;')),
									($isPredefined
										? $report_name
										: RCView::escape($report_name)
									)
								);
			// View/export options
			$rows[$item_num][] = // If the "Selected instruments/events" pre-defined rule, then give other button to open multi-select boxes
								($report_id != 'SELECTED' ? '' :
									RCView::span(array('style'=>'display:block;'),
										RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:11px;', 'onclick'=>"
											$(this).parent().hide();
											$('.rprt_selected_hidden').css('display','block');
										"),
											'<i class="fas fa-mouse-pointer"></i> ' .$lang['data_export_tool_174']
										)
									) .
									RCView::div(array('class'=>'rprt_selected_hidden wrap', 'style'=>'margin-top:5px;'),
										($longitudinal ? $lang['data_export_tool_175'] : $lang['data_export_tool_176'])
									) .
									RCView::div(array('class'=>'nowrap rprt_selected_hidden', 'style'=>'margin:8px 0;'),
										// Instrument drop-down
										RCView::div(array('style'=>'width:100%;'),
											RCView::div(array('style'=>'font-weight:bold;'),
												$lang['global_110']
											) .
											$instrument_dropdown
										) .
										(!$longitudinal ? '' :
											RCView::div(array('style'=>'width:100%;margin:10px 10px 7px 3px;color:#888;'),
												$lang['global_87']
											) .
											// Event drop-down
											RCView::div(array('style'=>'width:100%;'),
												RCView::div(array('style'=>'font-weight:bold;'),
													$lang['global_45']
												) .
												$event_dropdown
											)
										) .
										RCView::div(array('class'=>'clear'), '')
									)
								) .
								RCView::span(array('class'=>'rprt_btns' . ($report_id == 'SELECTED' ? ' rprt_selected_hidden' : '')),
									// View Report
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#008000;font-size:11px;', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id=$report_id'+getSelectedInstrumentList();"),
										'<i class="fas fa-search"></i> ' .$lang['report_builder_44']
									) .
									// Data Export
									($user_rights['data_export_tool'] == '0' ? '' :
										RCView::button(array('class'=>'data_export_btn jqbuttonmed', 'onclick'=>"showExportFormatDialog('$report_id');", 'style'=>'color:#000066;margin:0 0 0 5px;font-size:11px;'),
											'<i class="fas fa-file-download"></i> ' .$lang['report_builder_48']
										)
									) .
									// View Stats & Charts
									(!$user_rights['graphical'] || !$enable_plotting ? '' :
										RCView::button(array('class'=>'data_export_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id=$report_id&stats_charts=1'+getSelectedInstrumentList();", 'style'=>'color:#800000;margin:0 0 0 5px;font-size:11px;'),
											'<i class="fas fa-chart-bar"></i> ' .$lang['report_builder_78']
										)
									)
								) .
								// For selected instrument pre-defined rule only, note on how to use multi-select
								($report_id != 'SELECTED' ? '' :
									RCView::div(array('class'=>'wrap rprt_selected_hidden', 'style'=>'padding:1px 0 5px 3px;line-height:11px;font-size:11px;font-weight:normal;color:#888;'),
										// Allow user to start building a new report based upon these selections
										(!$user_rights['reports'] ? '' :
											RCView::div(array('style'=>'padding:9px 0 6px 15px;color:#555;'), "&ndash; OR &ndash;") .
											RCView::div(array('style'=>'padding:2px 0;color:#333;'),
												RCView::button(array('class'=>'data_export_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&create=1&addedit=1'+getSelectedInstrumentList(true);", 'style'=>'color:green;font-size:12px;'),
													'+ ' . $lang['report_builder_139']
												) .
												$lang['report_builder_140']
											)
										)
									)
								);
			// Management options (if user has add/edit reports privileges)
			if ($user_rights['reports']) {
				// If this is a pre-defined report OR if user does NOT have edit access to it, then do not display buttons
				if ($isPredefined || (!in_array($report_id, $reports_edit_access) && !SUPER_USER)) {
					$rows[$item_num][] = '';					
				} else {
					$rows[$item_num][] = 
						//Edit
						RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin-right:2px;font-size:11px;padding: 1px 6px;', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id=$report_id&addedit=1';"),
							'<i class="fas fa-pencil-alt"></i> ' .$lang['global_27']
						) .
						// Copy
						RCView::button(array('id'=>'repcopyid_'.$report_id, 'class'=>'jqbuttonmed', 'style'=>'margin-right:2px;font-size:11px;padding: 1px 6px;', 'onclick'=>"copyReport($report_id,true);"),
							'<i class="far fa-copy"></i> ' .$lang['report_builder_46']
						) .
						// Delete
						RCView::button(array('id'=>'repdelid_'.$report_id, 'class'=>'jqbuttonmed', 'style'=>'font-size:11px;padding: 1px 6px;', 'onclick'=>"deleteReport($report_id,true);"),
							'<i class="fas fa-times"></i> ' .$lang['global_19']
						);
				}
			}
			// If user has API export rights, then display report_id
			if ($hasAPIrights) {
				$rows[$item_num][] = (!is_numeric($report_id)) ? '' : RCView::div(array('style'=>'font-size:11px;color:#777;'), $report_id);
			}
			// Increment row counter
			if (!$isPredefined) $row_num++;
			$item_num++;
		}
		// Add last row as "add new report" button
		if ($user_rights['reports']) {
			$rows[$item_num] = array('', '',
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:green;margin:8px 0;', 'onclick'=>"window.location.href = app_path_webroot+'DataExport/index.php?create=1&addedit=1&pid='+pid;"),
										'<i class="fas fa-plus fs11"></i> ' . $lang['report_builder_14']
									), '', '', '');
		}
		// Set table headers and attributes
		$viewExportOptionsWidthReduce = $viewAPIWidthAdd = 0;
		$viewExportOptionsWidthReduce += ($user_rights['data_export_tool'] > 0) ? 0 : 80;
		$viewExportOptionsWidthReduce += ($user_rights['graphical'] && $enable_plotting > 0) ? 0 : 80;
		$col_widths_headers = array();
		$col_widths_headers[] = array(18, "", "center");
		$col_widths_headers[] = array(18, "", "center");
		$col_widths_headers[] = array(250, $lang['report_builder_42']);
		$col_widths_headers[] = array(330-$viewExportOptionsWidthReduce, $lang['report_builder_43']);
		if ($user_rights['reports']) {
			$viewReportMgmtWidthReduce = 0;
			$col_widths_headers[] = array(184, $lang['report_builder_45']);
		} else {
			$viewReportMgmtWidthReduce = 192;
		}
		// If user has API export rights, then display report_id
		if ($hasAPIrights) {
			$viewAPIWidthAdd = 95;
			$col_widths_headers[] = array(83,
										RCView::div(array('class'=>'nowrap', 'style'=>'text-align:center;font-size:10px;color:#777;'),
											RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_125']) .
											RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:5px;', 'onclick'=>"simpleDialog(null,null,'api_report_dialog');"),
												'<i class="fas fa-question-circle fs11"></i>'
											) .
											RCView::br() .
											$lang['define_events_66']
										),
										"center");
		}
		// Set table title
		$table_title = RCView::div(array('style'=>'color:#333;font-size:13px;padding:5px;'), $lang['report_builder_47']);
		// Hidden help dialog for API report export
		$hidden_dialog_api_report = RCView::div(array('id'=>'api_report_dialog', 'class'=>'simpleDialog', 'title'=>$lang['report_builder_126']),
										$lang['report_builder_127'] . " " .
										RCView::a(array('href'=>APP_PATH_WEBROOT_PARENT.'api/help/', 'style'=>'text-decoration:underline;'),
											$lang['control_center_445']
										) .
										$lang['period']
									);
		// Render the table
		return $hidden_dialog_api_report .
			   renderGrid("report_list", $table_title, 862+$viewAPIWidthAdd-$viewExportOptionsWidthReduce-$viewReportMgmtWidthReduce, 'auto', $col_widths_headers, $rows, true, false, false);
	}


	// Render the dialog for user to choose export options
	public static function renderExportOptionDialog()
	{
		global $lang, $Proj, $surveys_enabled, $user_rights, $date_shift_max, $table_pk_label;

		// Options to remove DAG field and survey-related fields
		$dags = $Proj->getUniqueGroupNames();
		$exportDagOption = "";
		$exportSurveyFieldsOptions = "";
		if (!empty($dags) && $user_rights['group_id'] == "") {
			$exportDagOption = 	RCView::div(array('style'=>'margin-left:1.5em;text-indent:-1.5em;'),
									RCView::checkbox(array('name'=>'export_groups','checked'=>'checked')) .
									$lang['data_export_tool_138']
								);
		}
		if ($surveys_enabled) {
			$exportSurveyFieldsOptions = RCView::div(array('style'=>'margin-left:1.5em;text-indent:-1.5em;'),
											RCView::checkbox(array('name'=>'export_survey_fields','checked'=>'checked')) .
											$lang['data_export_tool_139']
										 );;
		}
		$exportDagSurveyFieldsOptions = $exportDagOption . ($exportDagOption == '' ? '' : RCView::br()) . $exportSurveyFieldsOptions;

		// Options to remove missing data codes
		// UI elements commented as not implemented in getData		
			// $exportMissingDataCodesOptions = "";
			// $exportMissingDataCodesOptions = RCView::checkbox(array('name'=>'output_md_codes')) .
								   // $lang['missing_data_05'];
			
			
						
		
		
		// De-Identification Options box
		if ($user_rights['data_export_tool'] == '2') {
			// FULL DE-ID: User has limited rights, so check off everything and disable options
			$deid_msg = "<font color=red>{$lang['data_export_tool_87']}</font>";
			$deid_disable2 = "onclick=\"this.checked=false;\"";
			$deid_identifier_disable = $deid_disable = "checked onclick=\"this.checked=true;\"";
			$deid_disable_date2 =  "onclick=\"
									var thisfld = this.getAttribute('id');
									var thisfldId = thisfld;
									if (thisfld == 'deid-dates-remove'){
										var thatfld = document.getElementById('deid-dates-shift');
										thisfld = document.getElementById('deid-dates-remove');
									} else {
										var thatfld = document.getElementById('deid-dates-remove');
										thisfld = document.getElementById('deid-dates-shift');
									};
									if (thisfld.checked==true) {
										thatfld.checked=false;
										thisfld.checked=true;
										if (thisfldId == 'deid-dates-remove'){
											$('#deid-surveytimestamps-shift').prop('disabled',true).prop('checked',false);
										} else {
											$('#deid-surveytimestamps-shift').prop('disabled',false).prop('checked',true);
										}
									} else {
										thisfld.checked=false;
										thatfld.checked=true;
										if (thisfldId == 'deid-dates-remove'){
											$('#deid-surveytimestamps-shift').prop('disabled',false).prop('checked',true);
										} else {
											$('#deid-surveytimestamps-shift').prop('disabled',true).prop('checked',false);
										}
									}\"";
			$deid_disable_date = "checked $deid_disable_date2";
			$deid_deselect = "";
			// Determine if id field is an Identifier. If so, auto-check it
			$deid_hashid = ($Proj->table_pk_phi) ? $deid_disable : $deid_disable2;
		} else {
			// User has full export rights OR remove identifier fields rights
			$deid_identifier_disable = ($user_rights['data_export_tool'] == '3') ? "checked onclick=\"this.checked=true;\"" : "";
			$deid_msg = ($user_rights['data_export_tool'] == '3') ? "<font color=red>{$lang['data_export_tool_185']}</font>" : "";
			$deid_disable = $deid_disable2 = $deid_hashid = "";
			$deid_disable_date = "onclick=\"$('#deid-surveytimestamps-shift').prop('disabled', !this.checked);\"";
			$deid_disable_date2 =  "onclick=\"
									var shiftfld = document.getElementById('deid-dates-shift');
									if (this.checked == true) {
										shiftfld.checked = false;
										shiftfld.disabled = true;
										$('#deid-surveytimestamps-shift').prop('disabled',true).prop('checked',false);
									} else {
										shiftfld.disabled = false;
										$('#deid-surveytimestamps-shift').prop('disabled',false);
									}\"";
			$deid_deselect =   "<a href='javascript:;' style='margin-top:12px;display:block;font-size:8pt;text-decoration:underline;' onclick=\"
									".($user_rights['data_export_tool'] == '3' ? "" : "document.getElementById('deid-remove-identifiers').checked = false;")."
									document.getElementById('deid-hashid').checked = false;
									document.getElementById('deid-remove-text').checked = false;
									document.getElementById('deid-remove-notes').checked = false;
									document.getElementById('deid-dates-remove').checked = false;
									document.getElementById('deid-dates-shift').checked = false;
									document.getElementById('deid-dates-shift').disabled = false;
									$('#deid-surveytimestamps-shift').prop('disabled', true);
									$('#deid-surveytimestamps-shift').prop('checked', false);
								\">{$lang['data_export_tool_88']}</a>";
		}
		$date_shift_dialog_content =   "<b>{$lang['date_shift_02']}</b><br>
										{$lang['date_shift_03']} $date_shift_max {$lang['date_shift_04']}<br><br>
										{$lang['date_shift_05']} $date_shift_max {$lang['date_shift_06']}
										$table_pk_label {$lang['date_shift_07']}<br><br>
										<b>{$lang['date_shift_08']}</b><br>{$lang['date_shift_09']}";
		$deid_option_box = "{$lang['data_export_tool_91']} $deid_msg
							<div style='font-size:11px;'>
								<div style='margin-top:10px;font-weight:bold;'>{$lang['data_export_tool_92']}</div>
								<div style='margin-left:1.5em;text-indent:-1.5em;line-height: 12px;'>
									<input type='checkbox' $deid_identifier_disable id='deid-remove-identifiers' name='deid-remove-identifiers'>
									{$lang['data_export_tool_182']}
									<span style='margin-left:3px;color:#777;font-size:10px;'>{$lang['data_export_tool_94']}</span>
								</div>
								<div style='margin-left:1.5em;text-indent:-1.5em;line-height: 12px;'>
									<input type='checkbox' $deid_hashid id='deid-hashid' name='deid-hashid'> {$lang['data_export_tool_173']}
									<span style='margin-left:3px;color:#777;font-size:10px;'>{$lang['data_export_tool_96']}</span>
								</div>

								<div style='margin-top:12px;font-weight:bold;'>{$lang['data_export_tool_97']}</div>
								<div style='margin-left:1.5em;text-indent:-1.5em;line-height: 12px;'>
									<input type='checkbox' $deid_disable id='deid-remove-text' name='deid-remove-text'>
									{$lang['data_export_tool_98']}
									<span style='margin-left:3px;color:#777;font-size:10px;'>{$lang['data_export_tool_99']}</span>
								</div>
								<div style='margin-left:1.5em;text-indent:-1.5em;line-height: 12px;'>
									<input type='checkbox' $deid_disable id='deid-remove-notes' name='deid-remove-notes'>
									{$lang['data_export_tool_100']}
								</div>

								<div style='margin-top:12px;font-weight:bold;'>{$lang['data_export_tool_129']}</div>
								<div style='margin-left:1.5em;text-indent:-1.5em;line-height: 12px;'>
									<input type='checkbox' $deid_disable_date2 id='deid-dates-remove' name='deid-dates-remove'>
									{$lang['data_export_tool_128']}
								</div>
								<div style='padding:5px 0 4px;color:#777;line-height: 12px;'>
									&mdash; {$lang['global_46']} &mdash;
								</div>
								<div style='margin-left:1.5em;text-indent:-1.5em;line-height: 12px;'>
									<input type='checkbox' $deid_disable_date id='deid-dates-shift' name='deid-dates-shift'>
									{$lang['data_export_tool_103']} $date_shift_max {$lang['data_export_tool_104']}<br>
									<span style='color:#777;font-size:10px;'>{$lang['data_export_tool_105']}</span>
									<a href='javascript:;' style='margin-left:5px;font-size:8pt;text-decoration:underline;' onclick=\"
										simpleDialog('".js_escape($date_shift_dialog_content)."','".js_escape($lang['date_shift_01'])."');
									\">{$lang['data_export_tool_106']}</a>
								</div>
								".(($surveys_enabled && !empty($Proj->surveys)) ?
									"<div style='margin-left:4em;text-indent:-1.4em;padding-top:6px;line-height: 12px;'>
										<input type='checkbox' $deid_disable id='deid-surveytimestamps-shift' name='deid-surveytimestamps-shift' ".($user_rights['data_export_tool'] == 1 ? "disabled" : "").">
										{$lang['data_export_tool_143']} $date_shift_max {$lang['data_export_tool_104']}<br>
										<span style='color:#777;font-size:10px;'>{$lang['data_export_tool_105']}</span>
									</div>"
									: ""
								)."
								$deid_deselect
							</div>";
		
		// Defaults for CSV delimiter and decimal character
		$defaultCsvDelimiter = UIState::getUIStateValue('', 'export_dialog', 'csvDelimiter');
		if ($defaultCsvDelimiter == '') $defaultCsvDelimiter = ",";
		$decimalCharacter = UIState::getUIStateValue('', 'export_dialog', 'decimalCharacter');
		$decimalCharacterOptions = array(''=>$lang['data_export_tool_234'], '.'=>$lang['data_export_tool_236'], ','=>$lang['data_export_tool_237']);
		
		// Return the html
		return 	RCView::div(array('class'=>'simpleDialog', 'id'=>'exportFormatDialog'),
					// Don't show instructions at top of dialog for Whole Project XML export
					RCView::div(array('style'=>((isset($_GET['other_export_options']) || PAGE == 'ProjectSetup/other_functionality.php') ? 'display:none;' : '')),
						$lang['report_builder_59']
					) .
					RCView::form(array('id'=>'exportFormatForm'),
						RCView::table(array('cellspacing'=>0, 'style'=>'width:100%;table-layout:fixed;'),
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'style'=>'width:360px;padding-right:20px;'),
									// REDCap whole project export
									RCView::fieldset(array('id'=>'export_whole_project_fieldset', 'style'=>'margin:15px 0;border:1px solid #bbb;background-color:#d9ebf5;'),
										RCView::legend(array('style'=>'padding:0 3px;margin-left:15px;color:#800000;font-weight:bold;font-size:15px;'),
											$lang['data_export_tool_199']." ".$lang['data_export_tool_209']
										) .
										RCView::div(array('style'=>'padding:15px 5px 15px 25px;'),
											// Hidden radio element
											RCView::radio(array('name'=>'export_format', 'value'=>'odm_project', 'style'=>'display:none;')) .
											// Explanation
											RCView::div(array('style'=>'float:left;'),
												RCView::img(array('src'=>'odm_redcap.gif', 'style'=>'vertical-align:middle;'))
											) .
											RCView::div(array('style'=>'float:left;margin-left:15px;max-width:240px;line-height:14px;'),
												RCView::span(array('style'=>'font-weight:bold;font-size:13px;'),
													$lang['data_export_tool_200']
												) .
												RCView::br() .
												RCView::br() .
												RCView::span(array('style'=>''),
													($Proj->longitudinal ? $lang['data_export_tool_202'] : $lang['data_export_tool_201'])
												) .
												RCView::br() .
												RCView::br() .
												RCView::span(array('style'=>''),
													$lang['data_export_tool_203']
												)
											) .
											RCView::div(array('class'=>'clear'), '') .
											// Display choice "Include all uploaded files and signatures?" if exist in project
											(!Files::hasFileUploadFields() ? "" :
												RCView::div(array('style'=>'margin-top:15px;'),
													RCView::checkbox(array('name'=>'odm_include_files')) .
													RCView::b($lang['data_export_tool_219']) .
													RCView::div(array('style'=>'margin-left:22px;margin-top:2px;font-size:11px;line-height:12px;'),
														$lang['data_export_tool_218']
													)
												)
											)
										)
									) .
									// Data export
									// Step 1: Choose export format
									RCView::fieldset(array('id'=>'export_format_fieldset', 'style'=>'margin:15px 0;border:1px solid #bbb;background-color:#eee;'),
										RCView::legend(array('style'=>'padding:0 3px;margin-left:15px;color:#800000;font-weight:bold;font-size:15px;'),
											$lang['report_builder_114']
										) .
										RCView::table(array('id'=>'export_choices_table', 'cellspacing'=>0, 'style'=>'margin-top:6px;width:100%;table-layout:fixed;'),
											// CSV Raw
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:1px 15px 5px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'csvraw', 'style'=>'vertical-align:middle;margin-right:22px;')) .
													RCView::img(array('src'=>'excelicon.gif', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:10px;'),
														$lang['data_export_tool_172'] .
														" " . $lang['report_builder_49']
													)
												)
											) .
											// CSV Labels
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:5px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'csvlabels', 'style'=>'vertical-align:middle;margin-right:22px;')) .
													RCView::img(array('src'=>'excelicon.gif', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:10px;'),
														$lang['data_export_tool_172'] .
														" " . $lang['report_builder_50']
													)
												)
											) .
											// SPSS
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:8px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'spss', 'style'=>'vertical-align:middle;margin-right:26px;')) .
													RCView::img(array('src'=>'spsslogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:14px;'),
														$lang['data_export_tool_07']
													)
												)
											) .
											// SAS
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:8px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'sas', 'style'=>'vertical-align:middle;margin-right:26px;')) .
													RCView::img(array('src'=>'saslogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:4px;'),
														$lang['data_export_tool_11']
													)
												)
											) .
											// R
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:9px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'r', 'style'=>'vertical-align:middle;margin-right:24px;')) .
													RCView::img(array('src'=>'rlogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:18px;'),
														$lang['data_export_tool_09']
													)
												)
											) .
											// Stata
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:9px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'stata', 'style'=>'vertical-align:middle;margin-right:24px;')) .
													RCView::img(array('src'=>'statalogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:8px;'),
														$lang['data_export_tool_187']
													)
												)
											) .
											// ODM
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:2px 5px 5px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'odm', 'style'=>'vertical-align:middle;margin-right:22px;')) .
													RCView::img(array('src'=>'odm.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:15px;'),
														$lang['data_export_tool_197']
													)
												)
											)
										)
									) .
									// Step 2: Archive files in File Repository? FOR NOW, ALWAYS DO THIS!!!!!!!!
									RCView::fieldset(array('style'=>'display:none;margin:15px 0;padding-left:8px;border:1px solid #bbb;background-color:#eee;'),
										RCView::legend(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'),
											$lang['report_builder_54']
										) .
										RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
											RCView::div(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"$(this).find('input:first').prop('checked', true);"),
												RCView::radio(array('name'=>'export_options_archive', 'value'=>'1', 'checked'=>'checked')) .
												$lang['report_builder_57']
											) .
											RCView::div(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"$(this).find('input:first').prop('checked', true);"),
												RCView::radio(array('name'=>'export_options_archive', 'value'=>'0')) .
												$lang['report_builder_58']
											)
										)
									)
								) .
								RCView::td(array('valign'=>'top', 'style'=>'width:320px;padding-right:20px;'),
									// De-ID Options
									RCView::fieldset(array('style'=>'margin:15px 0;padding-left:8px;border:1px solid #ddd;background-color:#f9f9f9;'),
										RCView::legend(array('style'=>'margin-left:5px;color:#800000;font-weight:bold;font-size:13px;'),
											$lang['data_export_tool_89'] .
											RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['survey_251'])
										) .
										RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
											$deid_option_box
										)
									)
								) .
								RCView::td(array('valign'=>'top'),
									
									// Missing Data Options
									// commented as not implemented in getData
									// (sizeof($missingDataCodes) >0 ? :
										// RCView::fieldset(array('style'=>'margin:15px 0;padding-left:8px;border:1px solid #ddd;background-color:#f9f9f9;'),
											// RCView::legend(array('style'=>'margin-left:5px;color:#800000;font-weight:bold;font-size:13px;'),
												// $lang['missing_data_04'] .
												// RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['missing_data_05'])
											// ) .
											// RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
												// $exportMissingDataCodesOptions
											// )
										// )
									// ) .
									// Export DAGs and/or Survey Fields
									($exportDagSurveyFieldsOptions == '' ? '' :
										RCView::fieldset(array('id'=>'export_dialog_dags_survey_fields_options', 'style'=>'margin:15px 0 0;padding-left:8px;border:1px solid #ddd;background-color:#f9f9f9;'),
											RCView::legend(array('style'=>'margin-left:5px;color:#800000;font-weight:bold;font-size:13px;'),
												$lang['data_export_tool_140']
											) .
											RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
												$exportDagSurveyFieldsOptions
											)
										)
									) .
									// Note about Live Filters (if some have been selected)
									(PAGE != 'DataExport/index.php' ? '' :
										RCView::fieldset(array('id'=>'export_dialog_live_filter_option', 'class'=>'darkgreen', 'style'=>'font-size:12px;margin:15px 0 0;padding-left:8px;'),
											RCView::legend(array('style'=>'margin-left:5px;color:#333;font-weight:bold;font-size:13px;'),
												$lang['custom_reports_16']
											) .
											RCView::div(array('style'=>'padding:0px 2px;'),
												$lang['custom_reports_17'] .
												RCView::div(array('style'=>'margin-left:1.5em;text-indent:-1.5em;margin-top:7px;margin-bottom:2px;font-weight:bold;'),
													RCView::checkbox(array('name'=>'live_filters_apply', 'checked'=>'checked')) .
													$lang['custom_reports_18']
												)
											)
										)
									) .
									// Data formatting options
									RCView::fieldset(array('id'=>'export_dialog_data_format_options', 'class'=>'yellow', 'style'=>'font-size:11px;margin:15px 0 0;padding-left:8px;border:1px solid #e4cc5d;background-color:#fdfbf4;'),
										RCView::legend(array('style'=>'margin-left:5px;color:#800000;font-weight:bold;font-size:13px;'),
											$lang['data_export_tool_224']
										) .
										// CSV Delimiter option
										RCView::div(array('style'=>'padding:3px 2px 5px;'),
											RCView::div(array('style'=>'font-size:12px;font-weight:bold;'),
												$lang['data_export_tool_233']
											) . 
											$lang['data_export_tool_225'] . RCView::br() . 
											RCView::select(array('name'=>'csvDelimiter', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;margin-top:3px;'), 
												self::getCsvDelimiters(), $defaultCsvDelimiter)
										) .
										// Decimal character option
										RCView::div(array('style'=>'padding:8px 2px;font-size:11px;'),
											RCView::div(array('style'=>'font-size:12px;font-weight:bold;'),
												$lang['data_export_tool_232']
											) .
											$lang['data_export_tool_226'] . RCView::br() . 
											RCView::select(array('name'=>'decimalCharacter', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;margin-top:3px;'), 
												$decimalCharacterOptions, $decimalCharacter)
										) .
										// Note about these settings being remembered
										RCView::div(array('style'=>'color:#C00000;padding:5px 2px;'),
											$lang['data_export_tool_231']
										)
									)
								)
							)
						)
					)
				 );
	}
	
	// Obtain array of CSV delimiters for export file formatting
	public static function getCsvDelimiters()
	{
		global $lang;
		return array(","=>", ".$lang['user_86']." ".$lang['data_export_tool_235'], "tab"=>$lang['data_export_tool_227'], 
					 ";"=>"; ".$lang['data_export_tool_228'], "|"=>"| ".$lang['data_export_tool_229'], 
					 "^"=>"^ ".$lang['data_export_tool_230']);
	}
	
	// Is value a valid CSV delimiter?
	public static function isValidCsvDelimiter($val)
	{
		if ($val == "\t") $val = "tab";
		$delimiters = self::getCsvDelimiters();
		return isset($delimiters[$val]);
	}

	// Obtain any dynamic filters selected from query string params
	public static function buildReportDynamicFilterLogic($report_id)
	{
		global $Proj, $lang, $user_rights, $missingDataCodes;
		// Validate report_id
		if (!is_numeric($report_id)) return "";
		// Get report attributes
		$report = self::getReports($report_id);
		if (empty($report)) return "";
		// Loop through fields
		$dynamic_filters_logic = array();
		$dynamic_filters_group_id = $dynamic_filters_event_id = "";
		for ($i = 1; $i <= DataExport::MAX_LIVE_FILTERS; $i++) {
			// Get field name
			$field = $report['dynamic_filter'.$i];
			// If we do not have a dynamic field set here or if the field no longer exists in the project, then return blank string
			if (!(isset($field) && $field != '' && ($field == self::LIVE_FILTER_EVENT_FIELD || $field == self::LIVE_FILTER_DAG_FIELD || isset($Proj->metadata[$field])))) continue;
			if (!isset($_GET['lf'.$i]) || $_GET['lf'.$i] == '') continue;
			// Rights to view data from field? Must have form rights for fields, and if a DAG field, then must not be in a DAG.
			if (isset($Proj->metadata[$field]) && $field != $Proj->table_pk && $user_rights['forms'][$Proj->metadata[$field]['form_name']] == '0') {
				unset($_GET['lf'.$i]);
				continue;
			} elseif ($field == self::LIVE_FILTER_DAG_FIELD && is_numeric($user_rights['group_id'])) {
				unset($_GET['lf'.$i]);
				continue;
			}
			// Get field choices
			if ($field == self::LIVE_FILTER_EVENT_FIELD) {
				// Add blank choice at beginning
				$choices = array(''=>"[".$lang['global_45']."]");
				// Add event names
				foreach ($Proj->eventInfo as $this_event_id=>$eattr) {
					$choices[$this_event_id] = $eattr['name_ext'];
				}
				// Validate the value
				if (isset($choices[$_GET['lf'.$i]])) {
					$dynamic_filters_event_id = $_GET['lf'.$i];
				}
			} elseif ($field == self::LIVE_FILTER_DAG_FIELD) {
				$choices = $Proj->getGroups();
				// Add blank choice at beginning
				$choices = array(''=>"[".$lang['global_78']."]") + $choices;
				// Validate the value
				if (isset($choices[$_GET['lf'.$i]])) {
					$dynamic_filters_group_id = $_GET['lf'.$i];
				}
			} elseif ($field == $Proj->table_pk) {
				$choices = Records::getRecordList($Proj->project_id, $user_rights['group_id'], true);
				// Add blank choice at beginning
				$choices = array(''=>"[ ".strip_tags(label_decode($Proj->metadata[$field]['element_label']))." ]") + $choices;	
				// Validate the value
				if (isset($choices[$_GET['lf'.$i]])) {
					$value = (self::LIVE_FILTER_BLANK_VALUE == $_GET['lf'.$i]) ? '' : $_GET['lf'.$i];
					$dynamic_filters_logic[] = "[$field] = '$value'";
				}
				
			} else {
				$choices = parseEnum($Proj->metadata[$field]['element_enum']);
				// Add blank choice at beginning + NULL choice
				$choices = array(''=>"[ ".strip_tags(label_decode($Proj->metadata[$field]['element_label']))." ]")
						 + parseEnum($Proj->metadata[$field]['element_enum'])
						 + array(self::LIVE_FILTER_BLANK_VALUE=>$lang['report_builder_145']);
				// Validate the value
				if (isset($choices[$_GET['lf'.$i]]) || isset($missingDataCodes[$_GET['lf'.$i]])) {
					$value = (self::LIVE_FILTER_BLANK_VALUE == $_GET['lf'.$i]) ? '' : $_GET['lf'.$i];
					$dynamic_filters_logic[] = "[$field] = '$value'";
				}
			}
		}
		// Return logic and DAG group_id
		return array(implode(" and ", $dynamic_filters_logic), $dynamic_filters_group_id, $dynamic_filters_event_id);
	}


	// Build a report's dynamic filter options to display on page (if any)
	public static function displayReportDynamicFilterOptions($report_id)
	{
		global $lang;
		// Validate report_id
		if (!is_numeric($report_id)) return "";
		// Get report attributes
		$report = self::getReports($report_id);
		if (empty($report)) return "";
		// HTML to return
		$dynamic_filters = "";
		// Loop through each dynamic field and add
		$liveFiltersSelected = false;
		for ($i = 1; $i <= self::MAX_LIVE_FILTERS; $i++) {
			$dynamic_filters .= self::getReportDynamicFilterOption($report, $i);
			// Has at least one live filter been selected
			if (isset($_GET['lf'.$i]) && $_GET['lf'.$i] != '') $liveFiltersSelected = true;
		}
		// Set "reset" link
		$resetLink = "";
		if ($liveFiltersSelected) {
			$resetLink = RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:3px;text-decoration:underline;font-size:11px;', 'onclick'=>"resetLiveFilters();"), $lang['setup_53']);
		}
		// Add container for HTML
		if ($dynamic_filters != "") {
			$dynamic_filters =	RCView::div(array('style'=>'margin-top:10px;'),
									RCView::span(array('style'=>'margin-right:10px;font-weight:bold;'), $lang['custom_reports_15']) .
									$dynamic_filters . $resetLink
								);
		}
		// Return HTML
		return $dynamic_filters;
	}


	// Obtain a report's dynamic filter options as an array (if any)
	public static function getReportDynamicFilterOption($report=array(), $dynamic_field_num=1)
	{
		global $Proj, $lang, $user_rights, $missingDataCodes;
		// Get field name
		$field = $report['dynamic_filter'.$dynamic_field_num];
		// Rights to view data from field? Must have form rights for fields, and if a DAG field, then must not be in a DAG.
		$hasFieldRights = true;
		if (isset($Proj->metadata[$field]) && $user_rights['forms'][$Proj->metadata[$field]['form_name']] == '0') {
			$hasFieldRights = false;
		} elseif ($field == self::LIVE_FILTER_DAG_FIELD && is_numeric($user_rights['group_id'])) {
			$hasFieldRights = false;
		}
		// If we do not have a dynamic field set here or if the field no longer exists in the project, then return blank string
		if (!(isset($field) && $field != '' && ($field == self::LIVE_FILTER_EVENT_FIELD || $field == self::LIVE_FILTER_DAG_FIELD || isset($Proj->metadata[$field])))) return "";
		// Get field choices
		if ($field == self::LIVE_FILTER_EVENT_FIELD) {
			// Add blank choice at beginning
			$choices = array(''=>"[ ".$lang['global_45']." ]");
			// Add event names
			foreach ($Proj->eventInfo as $this_event_id=>$eattr) {
				$choices[$this_event_id] = strip_tags(label_decode($eattr['name_ext']));
			}
		} elseif ($field == self::LIVE_FILTER_DAG_FIELD) {
			$choices = $Proj->getGroups();
			// Add blank choice at beginning
			$choices = array(''=>"[ ".$lang['global_78']." ]") + $choices;		
		} elseif ($field == $Proj->table_pk) {
			$choices = Records::getRecordList($Proj->project_id, $user_rights['group_id'], true);
			// Add blank choice at beginning
			$choices = array(''=>"[ ".strip_tags(label_decode($Proj->metadata[$field]['element_label']))." ]") + $choices;	
		} else {
			// Add blank choice at beginning + NULL choice
			$choices = array(''=>"[ ".strip_tags(label_decode($Proj->metadata[$field]['element_label']))." ]")
					 + parseEnum(strip_tags(label_decode($Proj->metadata[$field]['element_enum'])))
					 + array(self::LIVE_FILTER_BLANK_VALUE=>$lang['report_builder_145']);
			// Add missing data codes, if applicable
            if (!empty($missingDataCodes) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$field]['misc'])) {
				$choices = $choices+array($lang['missing_data_04'] . $lang['colon']=>$missingDataCodes);
            }
		}
		// Set bgcolor and color based on whether it is selected (red=selected)
		$color = $disabled = $select_value = "";
		$bg = "background:#DDD;";
		if ($hasFieldRights) {
			// Validate the value
			$select_value = (isset($choices[$_GET['lf'.$dynamic_field_num]]) || isset($missingDataCodes[$_GET['lf'.$dynamic_field_num]])) ? $_GET['lf'.$dynamic_field_num] : "";
			$color = 'color:'.($select_value == "" ? "#444444" : "#C00000").';';
			$bg = 'background:'.($select_value == "" ? "" : "#E5E5E5").';';
		} else {
			$disabled = "disabled";
		}
		// Build the drop-down
		return 	RCView::select(array('id'=>'lf'.$dynamic_field_num, $disabled=>$disabled,
					'style'=>$bg.$color.'max-width:'.self::getReportDynamicFilterMaxWidth($report).'px;margin-right:'.self::RIGHT_MARGIN_DYNAMIC_FILTER.'px;font-size:11px;padding-right:0;height:19px;padding-bottom:1px;',
					'onchange'=>"loadReportNewPage(0);"),
					$choices, $select_value
				);
	}


	// Determine the max-width (in pixels) of each dynamic filter drop-down
	public static function getReportDynamicFilterMaxWidth($report=array())
	{
		global $Proj;
		// Loop through each dynamic field and add
		$dynFilterCount = 0;
		for ($i = 1; $i <= self::MAX_LIVE_FILTERS; $i++) {
			// Get field name
			$field = $report['dynamic_filter'.$i];
			// If we do not have a dynamic field set here or if the field no longer exists in the project, then return blank string
			if (!(isset($field) && $field != '' && ($field == self::LIVE_FILTER_EVENT_FIELD || $field == self::LIVE_FILTER_DAG_FIELD || isset($Proj->metadata[$field])))) continue;
			// Increment counter
			$dynFilterCount++;
		}
		if ($dynFilterCount == 0) return null;
		// Calculate max width of each
		return floor(self::MAX_DYNAMIC_FILTER_WIDTH_TOTAL/$dynFilterCount - self::RIGHT_MARGIN_DYNAMIC_FILTER);
	}


	// Output a specific report in a specified output format (html, csvlabels, csvraw, spss, sas, r, stata)
	public static function doReport($report_id='0', $outputType='report', $outputFormat='html', $apiExportLabels=false, $apiExportHeadersAsLabels=false,
									$outputDags=false, $outputSurveyFields=false, $removeIdentifierFields=false, $hashRecordID=false,
									$removeUnvalidatedTextFields=false, $removeNotesFields=false,
									$removeDateFields=false, $dateShiftDates=false, $dateShiftSurveyTimestamps=false,
									$selectedInstruments=array(), $selectedEvents=array(), $returnIncludeRecordEventArray=false,
									$outputCheckboxLabel=false, $includeOdmMetadata=false, $storeInFileRepository=true,
									$replaceFileUploadDocId=true, $liveFilterLogic="", $liveFilterGroupId="", $liveFilterEventId="", 
									$isDeveloper=false, $csvDelimiter=",", $decimalCharacter='')
	{
		global $Proj, $user_rights, $isAjax, $lang, $missingDataCodes;

		// Check report_id
		if (!is_numeric($report_id) && $report_id != 'ALL' && $report_id != 'SELECTED') {
			exit($isAjax ? '0' : 'ERROR');
		}

		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);

		// Determine if this is API report export
		$isAPI = (PAGE == 'api/index.php' || PAGE == 'API/index.php');

		// Set flag to ALWAYS archive exported files in File Repository
		$archiveFiles = true;

		// Get report attributes
		$report = self::getReports($report_id, $selectedInstruments, $selectedEvents);
		if (empty($report)) {
			if ($isAPI) {
				exit(RestUtility::sendResponse(400, 'The value of the parameter "report_id" is not valid'));
			} else {
				exit($isAjax ? '0' : 'ERROR');
			}
		}

		// Check user rights: Does user have access to this report? (exclude super users in this check)
		if ((defined('SUPER_USER') && !SUPER_USER) || !defined('SUPER_USER')) {
			// If user has Add/Edit Report rights then let them view this report, OR if they have explicit rights to this report
			if (self::getReportNames($report_id, !$user_rights['reports']) == null) {
				// User does NOT have access to this report AND also does not have Add/Edit Report rights
				if ($isAPI) {
					exit(RestUtility::sendResponse(403, "User \"".USERID."\" does not have access to this report."));
				} else {
					exit($isAjax ? '0' : 'ERROR');
				}
			}
		}

		// Determine if a report or an export
		$outputType = ($outputType == 'report') ? 'report' : 'export';
		if ($outputType != 'report') $returnIncludeRecordEventArray = false;
		// Determine whether to output a stats syntax file
		$stats_packages = array('r', 'spss', 'stata', 'sas');
		$outputSyntaxFile = (in_array($outputFormat, $stats_packages));
		if ($outputSyntaxFile) $csvDelimiter = ","; // Always force CSV with comma delimiter for stats syntax files

		// If CSV, determine whether to output a stats syntax file
		$outputAsLabels = ($outputFormat == 'csvlabels');
		$outputHeadersAsLabels = ($outputFormat == 'csvlabels');

		// List of fields to export
		$fields = $report['fields'];
		// If removing any fields due to DE-IDENTIFICATION, loop through them and remove them
		$allFieldsRemoved = false;
		if ($removeIdentifierFields || $removeUnvalidatedTextFields || $removeNotesFields || $removeDateFields)
		{
			// If using Report B with ALL instruments, then add all fields to $fields since it will be empty
			if ($report_id == 'SELECTED' && empty($fields)) {
				$fields = array_keys($Proj->metadata);
			}
			// Loop through fields in report
			foreach ($fields as $key=>$this_field) {
				// Skip record ID field
				if ($this_field == $Proj->table_pk) continue;
				// Get field type and validation type
				$this_field_type = $Proj->metadata[$this_field]['element_type'];
				$this_val_type = $Proj->metadata[$this_field]['element_validation_type'];
				$this_phi = $Proj->metadata[$this_field]['field_phi'];
				// Check if needs to be removed
				if (   ($this_phi && $removeIdentifierFields)
					|| ($this_field_type == 'text' && $this_val_type == '' && $removeUnvalidatedTextFields)
					|| ($this_field_type == 'textarea' && $removeNotesFields)
					|| ($this_field_type == 'text' && $removeDateFields && substr($this_val_type, 0, 4) == 'date'))
				{
					// Remove the field from $fields
					unset($fields[$key]);
				}
			}
			if (empty($fields)) {
				$allFieldsRemoved = true;
			}
		}

		// List of events to export, and if a live filter is being used, then it will override any event limiters from the report's attributes.
		$events = is_numeric($liveFilterEventId) ? $liveFilterEventId : $report['limiter_events'];

		// Limit to user's DAG (if user is in a DAG), and if not in a DAG, then limit to the DAG filter
		$userInDAG = (isset($user_rights['group_id']) && is_numeric($user_rights['group_id']));
		if ($userInDAG) {
			$dags = $user_rights['group_id'];
		} else {
			// If user is not in a DAG, then use the report's DAG limiters (if any), and if
			// a live filter is being used, then it will override any DAG limiters from the report's attributes.
			$dags = is_numeric($liveFilterGroupId) ? $liveFilterGroupId : $report['limiter_dags'];
		}
		// If project does not have Missing Data Codes, then disable $outputMissingDataCodes
		$outputMissingDataCodes = !empty($missingDataCodes);
		$removeLineBreaksInValues = ($outputType != 'report');
		// Set options to include DAG names and/or survey fields (exclude ALL and SELECTED pre-defined reports)
		if (is_numeric($report_id)) {
			$outputDags = ($report['output_dags'] == '1');
			$outputSurveyFields = ($report['output_survey_fields'] == '1');
			$outputMissingDataCodes = ($report['output_missing_data_codes'] == '1');
			$removeLineBreaksInValues = ($report['remove_line_breaks_in_values'] == '1' && $outputType == 'export');
		}
		// For pre-defined reports, if viewing as report, then ALWAYS default to displaying the DAGs and survey fields
		elseif (!is_numeric($report_id) && $outputType == 'report') {
			$outputDags = $outputSurveyFields = true;
		}
		// Determine if we need to remove line breaks from all data values
		if ($outputType == 'export' && $outputSyntaxFile) {
			// Remove line breaks when exporting to stats packages (regardless of report definition)
			$removeLineBreaksInValues = true;
		}
		// If user is in a DAG, then do not output the DAG name field
		if ($userInDAG) $outputDags = false;
		// If project has no surveys, then disable $outputSurveyFields
		if (empty($Proj->surveys)) $outputSurveyFields = false;
		// If we're removing identifier fields, then also remove Survey Identifier (if outputting survey fields)
		$outputSurveyIdentifier = ($outputSurveyFields && !$removeIdentifierFields);

		// File names for archived file
		$today_hm = date("Y-m-d_Hi");
		$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($Proj->project['app_title'], ENT_QUOTES)))), 0, 20);
		if (is_numeric($report_id)) {
			// Include report name in filenames generated
			$projTitleShort .= "-" . substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($report['title'], ENT_QUOTES)))), 0, 20);
		}
		if ($outputFormat == 'r' || $outputFormat == 'csvraw') {
			// CSV with header row
			$csv_filename = $projTitleShort . "_DATA_" .$today_hm. ".csv";
		} elseif ($outputFormat == 'odm' && !$includeOdmMetadata) {
			// ODM
			$csv_filename = $projTitleShort ."_CDISC_ODM_" .$today_hm. ".xml";
		} elseif ($outputFormat == 'odm' && $includeOdmMetadata) {
			// REDCap project (ODM)
			$csv_filename = $projTitleShort ."_" .$today_hm. ".REDCap.xml";
		} elseif ($outputFormat == 'csvlabels') {
			// CSV labels
			$csv_filename = $projTitleShort ."_DATA_LABELS_" .$today_hm. ".csv";
		} else {
			// CSV without header row
			$csv_filename = $projTitleShort . "_DATA_NOHDRS_" .$today_hm. ".csv";
		}

		// Build sort array of sort fields and their attribute (ASC, DESC)
		$sortArray = array();
		if ($report['orderby_field1'] != '') $sortArray[$report['orderby_field1']] = $report['orderby_sort1'];
		if ($report['orderby_field2'] != '') $sortArray[$report['orderby_field2']] = $report['orderby_sort2'];
		if ($report['orderby_field3'] != '') $sortArray[$report['orderby_field3']] = $report['orderby_sort3'];
		// If the only sort field is record ID field, then remove it (because it will sort by record ID and event on its own)
		if (count($sortArray) == 1 && isset($sortArray[$Proj->table_pk]) && $sortArray[$Proj->table_pk] == 'ASC') {
			unset($sortArray[$Proj->table_pk]);
		}

		## BUILD AND STORE CSV FILE
		// Set output format (CSV or HTML or API format)
		if ($isAPI || $isDeveloper) {
			// For API report export, return in desired format
			$returnDataFormat = $outputFormat;
			$outputAsLabels = $apiExportLabels;
			$outputHeadersAsLabels = $apiExportHeadersAsLabels;
		} elseif ($outputType == 'report') {
			// For webpage report, return html
			$returnDataFormat = 'html';
		} elseif ($outputFormat == 'odm') {
			// ODM export
			$returnDataFormat = 'odm';
		} else {
			// CSV export
			$returnDataFormat = 'csv';
		}

		// If a live filter is being used, then append it to our existing limiter logic from the report's attributes
		if ($liveFilterLogic != "") {
			if ($report['limiter_logic'] != '') {
				$report['limiter_logic'] = "({$report['limiter_logic']}) and ";
			}
			$report['limiter_logic'] .= $liveFilterLogic;
		}

		// Check syntax of logic string: If there is an issue in the logic, then return false and stop processing
		if ($outputType == 'report' && $report['limiter_logic'] != '' && !LogicTester::isValid($report['limiter_logic'])) {
			return array(RCView::div(array('class'=>'red'),
						RCView::img(array('src'=>'exclamation.png')) .
						RCView::b($lang['global_01'].$lang['colon']) . " " . $lang['report_builder_132'].
						(!SUPER_USER ? '' : RCView::br() . RCView::br() . RCView::b("FILTER LOGIC: ") . $report['limiter_logic'])), 0);
		}

		// Do not replace double quotes with single quotes for CSV data export
        $replaceDoubleQuotes = in_array($outputFormat, array('csvraw', 'csvlabels')) ? false : true;
		
		// For report A and B, if we're viewing specific pages, then treat specially for efficiency
		$records = array();
		if (PAGE == 'DataExport/report_ajax.php' && ($report_id == 'ALL' || ($report_id == 'SELECTED' && empty($report['limiter_events'])))) {
			global $pagingDropdownRecordList;
			$limitOffset = 0;
			$limit = null;
			if (!isset($_GET['pagenum'])) $_GET['pagenum'] = 1;
			if (is_numeric($_GET['pagenum'])) {
				$limitOffset = ($_GET['pagenum'] - 1) * DataExport::NUM_RESULTS_PER_REPORT_PAGE;
				$limit = DataExport::NUM_RESULTS_PER_REPORT_PAGE;
				list ($records, $pagingDropdownRecordList) = Records::getRecordListOfTotalRowsReturned($limit, $limitOffset, $dags);
			}
		}
		
		// If all the fields were removed to to de-id, then ensure that nothing is returned by 
		// setting $records with a single blank record to force it.
		if ($allFieldsRemoved) $records = array('');

		// Retrieve CSV data file (for exports) or report HTML content (for reports)
		$data_content = Records::getData($Proj->project_id, $returnDataFormat, $records, $fields, $events, $dags, $report['combine_checkbox_values'], $outputDags, $outputSurveyFields,
										 $report['limiter_logic'], $outputAsLabels, $outputHeadersAsLabels, $hashRecordID, $dateShiftDates,
										 $dateShiftSurveyTimestamps, $sortArray, $removeLineBreaksInValues, $replaceFileUploadDocId, $returnIncludeRecordEventArray,
										 true, $outputSurveyIdentifier, $outputCheckboxLabel, $report['filter_type'], $includeOdmMetadata, 
										 false, false, false, $replaceDoubleQuotes, null, 0, false, $csvDelimiter, $decimalCharacter, false, 0, array(), !$outputMissingDataCodes);
		
		## Logging (for exports only)
		if ($outputType != 'report' || $isAPI || $isDeveloper) {
			// Set data_values as JSON-encoded
			$data_values = array('report_id'=>$report_id,
								 'export_format'=>(substr($outputFormat, 0, 3) == 'csv' ? 'CSV' : strtoupper($outputFormat)),
								 'rawOrLabel'=>(($outputAsLabels === true || $outputAsLabels == '1') ? 'label' : 'raw'));
			if ($outputDags) $data_values['export_data_access_group'] = 'Yes';
			if ($outputSurveyFields) $data_values['export_survey_fields'] = 'Yes';
			if ($dateShiftDates) $data_values['date_shifted'] = 'Yes';
			if ($outputMissingDataCodes) $data_values['export_missing_data_codes'] = 'Yes';
			if (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] == '2') $data_values['deidentified'] = 'Yes';
			if (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] == '3') $data_values['removed_identifiers'] = 'Yes';
			$data_values['fields'] = (empty($fields)) ? array_keys($Proj->metadata) : $fields;
			// Log it
			Logging::logEvent("","redcap_data","data_export","",json_encode($data_values),"Export data" . ($isAPI ? " (API)" : ""));
		}

		// IF OUTPUTTING A REPORT, RETURN THE CONTENT HERE
		if ($outputType == 'report' || $isAPI || $isDeveloper || !$storeInFileRepository) {
			return $data_content;
		}
		
		// Check if repeating fields are in the output headers
		$hasRepeatInstrumentField = $hasRepeatInstanceField = false;
		if ($outputSyntaxFile && $Proj->hasRepeatingFormsEvents()) {
			list ($headers_array, $nothing) = explode("\n", $data_content, 2);
			$headers_array = explode(",", $headers_array);
			$hasRepeatInstrumentField = in_array('redcap_repeat_instrument', $headers_array);
			$hasRepeatInstanceField = in_array('redcap_repeat_instance', $headers_array);
		}

		// For SAS, SPSS, and Stata, remove the CSV file's header row
		if ((in_array($outputFormat, array('spss', 'stata', 'sas')))) {
			// Remove header row
			list ($headers, $data_content) = explode("\n", $data_content, 2);
		}
		// Store the data file
		$data_edoc_id = self::storeExportFile($csv_filename, $data_content, $archiveFiles, $dateShiftDates);
		if ($data_edoc_id === false) return false;

		## BUILD AND STORE SYNTAX FILE (if applicable)
		// If exporting to a stats package, then also generate the associate syntax file for that package
		$syntax_edoc_id = null;
		if ($outputSyntaxFile) {
			// Generate syntax file
			$syntax_file_contents = self::getStatsPackageSyntax($outputFormat, $fields, $csv_filename, $outputDags, $outputSurveyFields, 
																$removeIdentifierFields, $hasRepeatInstrumentField, $hasRepeatInstanceField,
																$report['combine_checkbox_values'], $outputMissingDataCodes);
			// Set the filename of the syntax file
			if ($outputFormat == 'spss') {
				$stats_package_filename = $projTitleShort ."_" . strtoupper($outputFormat) . "_$today_hm.sps";
			} elseif ($outputFormat == 'stata') {
				$stats_package_filename = $projTitleShort ."_" . strtoupper($outputFormat) . "_$today_hm.do";
			} else {
				$stats_package_filename = $projTitleShort ."_" . strtoupper($outputFormat) . "_$today_hm.$outputFormat";
			}
			// Store the syntax file
			$syntax_edoc_id = self::storeExportFile($stats_package_filename, $syntax_file_contents, $archiveFiles, $dateShiftDates);
			if ($syntax_edoc_id === false) return false;
		}

		// Return the edoc_id's of the CSV data file
		return array($data_edoc_id, $syntax_edoc_id);
	}


	// Build and return the stats package syntax file
	public static function getStatsPackageSyntax($stats_package, $fields, $data_file_name, $exportDags=false, $exportSurveyFields=false, 
												 $do_remove_identifiers=false, $hasRepeatInstrumentField=false, $hasRepeatInstanceField=false,
												 $combine_checkbox_values=false, $outputMissingDataCodes=false)
	{
		global $Proj, $user_rights, $missingDataCodes;

		// Use arrays for string replacement
		$orig = array("'", "\"", "\r\n", "\r", "\n", "&lt;", "<=");
		$repl = array("", "", " ", " ", " ", "<", "< =");
		$repl_sas_choices = array("''", "", " ", " ", " ", "<", "< =");

		// If DAGs exist, get unique group name and label IF user specified
		$dagLabels = $Proj->getGroups();
		$exportDags = ($exportDags && !empty($dagLabels) && (!isset($user_rights['group_id']) || (isset($user_rights['group_id']) && $user_rights['group_id'] == "")));
		if ($exportDags) {
			$dagUniqueNames = $Proj->getUniqueGroupNames();
			// Create enum for DAGs with unique name as coded value
			$dagEnumArray = array();
			foreach (array_combine($dagUniqueNames, $dagLabels) as $group_id=>$group_label) {
				$dagEnumArray[] = "$group_id, " . str_replace($orig, $repl, label_decode($group_label));
			}
			$dagEnum = implode(" \\n ", $dagEnumArray);
		}
		
		// Get any cached choices for ontology fields
		$ontologyFieldChoices = Form::getWebServiceCacheValuesBulk($Proj->project_id, $fields);

		# Initializing the syntax file strings
		$spss_string = "FILE HANDLE data1 NAME='data_place_holder_name' LRECL=90000.\n";
		$spss_string .= "DATA LIST FREE" . "\n\t";
		$spss_string .= "FILE = data1\n\t/";
		$sas_string = "DATA " . $Proj->project['project_name'] . ";\nINPUT ";
		$sas_format_string = "data redcap;\n\tset redcap;\n";
		$stata_string = "version 13\nclear\n\n"; // Explicitly set Stata version number (13 as minimum) for better compatibility
		$R_string = "#Clear existing data and graphics\nrm(list=ls())\n";
		$R_string .= "graphics.off()\n";
		$R_string .= "#Load Hmisc library\nlibrary(Hmisc)\n";
		$R_label_string = "#Setting Labels\n";
		$R_units_string = "\n#Setting Units\n" ;
		$R_factors_string = "\n\n#Setting Factors(will create new variable for factors)";
		$R_levels_string = "";
		$value_labels_spss = "VALUE LABELS ";

		// Collect fields into meta_array
		$meta_array = array();

		// If there are missing data codes, add their variables here
        if (!$outputMissingDataCodes) $missingDataCodes = array();
		$hasMissingDataCodes = ($outputMissingDataCodes && !empty($missingDataCodes));

		// Loop through fields
		foreach ($fields as $field)
		{
			// Set field attributes
			$row = $Proj->metadata[$field];

			// Skip any descriptive fields (because they cannot have data and should be excluded)
			if ($Proj->metadata[$field]['element_type'] == 'descriptive') continue;

			// Create object for each field we loop through
			$ob = new stdClass();
			foreach ($row as $col=>$val) {
				$col = strtoupper($col);
				$ob->$col = $val;
			}			

			// Set values for this loop
			$this_form = $Proj->metadata[$ob->FIELD_NAME]['form_name'];
			
			// If $combine_checkbox_values=true, then force checkbox fields as Text fields
			if ($combine_checkbox_values && $ob->ELEMENT_TYPE == 'checkbox') {
				$ob->ELEMENT_TYPE = $row['element_type'] = 'text';				
			}
			
			// If an ontology field, modify attributes to convert it to a multiple choice field to load better into stats packages
			if (isset($ontologyFieldChoices[$field])) 
			{
				$ob->ELEMENT_TYPE = 'select';
				$enum = array();
				foreach ($ontologyFieldChoices[$field] as $val=>$label) {
					$enum[] = "$val, $label";
				}
				$ob->ELEMENT_ENUM = implode(" \\n ", $enum);
			}

			// If surveys exist, as timestamp and identifier fields
			if ($exportSurveyFields && (!isset($prev_form) || $prev_form != $this_form || ($prev_form == $this_form && $prev_field == $Proj->table_pk))
                && $ob->FIELD_NAME != $Proj->table_pk && isset($Proj->forms[$this_form]['survey_id']))
			{
				// Alter $meta_array
				$ob2 = new stdClass();
				$ob2->ELEMENT_TYPE = 'text';
				$ob2->FIELD_NAME = $this_form.'_timestamp';
				$ob2->ELEMENT_LABEL = 'Survey Timestamp';
				$ob2->ELEMENT_ENUM = '';
				$ob2->FIELD_UNITS = '';
				$ob2->ELEMENT_VALIDATION_TYPE = '';
				$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
			}


			if ($ob->ELEMENT_TYPE != 'checkbox') {
				// Ensure label is not too long
				if ($stats_package == 'sas')
				{
					$ob->ELEMENT_LABEL = truncateTextMiddle($ob->ELEMENT_LABEL, 60);
					$new_elementenum = array();
					foreach (parseEnum($ob->ELEMENT_ENUM) as $this_value=>$this_label) {
						// Ensure label is not too long
						$this_label = truncateTextMiddle($this_label, 25, 10);
						$new_elementenum[] = "$this_value, $this_label";
					}
					// For non-checkboxes, add to $meta_array
					$ob->ELEMENT_ENUM = implode(" \\n ", $new_elementenum);
				}
				$meta_array[$ob->FIELD_NAME] = (Object)$ob;
			} else {
				// For checkboxes, loop through each choice to add to $meta_array
				$orig_fieldname = $ob->FIELD_NAME;
				$orig_fieldlabel = $ob->ELEMENT_LABEL;
				$orig_elementenum = parseEnum($ob->ELEMENT_ENUM);
				// If there are missing data codes, then add them as checkbox choices
                if ($hasMissingDataCodes) {
					$orig_elementenum = $orig_elementenum + $missingDataCodes;
				}
                // Loop through checkbox choices
				foreach ($orig_elementenum as $this_value=>$this_label) {
					unset($ob);
					// $ob = $meta_set->FetchObject();
					$ob = new stdClass();
					$this_label = str_replace(array("'","\""),array("",""),$this_label);
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$this_value = (Project::getExtendedCheckboxCodeFormatted($this_value));
					// Convert each checkbox choice to a advcheckbox field (because advcheckbox has equivalent processing we need)
					// Append triple underscore + coded value
					$ob->FIELD_NAME = $orig_fieldname . '___' . $this_value;
					$ob->ELEMENT_ENUM = "0, Unchecked \\n 1, Checked";
					$ob->ELEMENT_TYPE = "advcheckbox";
					// Ensure label is not too long
					if ($stats_package == 'sas') $this_label = truncateTextMiddle($this_label, 25, 10);
					if ($stats_package == 'sas') $orig_fieldlabel = truncateTextMiddle($orig_fieldlabel, 60);
					// Set new label
					$ob->ELEMENT_LABEL = "$orig_fieldlabel (choice=$this_label)";
					$meta_array[$ob->FIELD_NAME] = (Object)$ob;
				}
			}


			if ($ob->FIELD_NAME == $Proj->table_pk)
			{
				// If project has multiple Events (i.e. Longitudinal), add new column for Event name
				if ($Proj->longitudinal)
				{
					// Put unique event names and labels into array to convert to enum format
					$evtEnumArray = array();
					$evtLabels = array();
					foreach ($Proj->eventInfo as $event_id=>$attr) {
						$evtLabels[$event_id] = label_decode($attr['name_ext']);
					}
					foreach ($evtLabels as $event_id=>$event_label) {
						$evtEnumArray[] = $Proj->getUniqueEventNames($event_id) . ", " . str_replace($orig, $repl, $event_label);
					}
					$evtEnum = implode(" \\n ", $evtEnumArray);
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'select';
					$ob2->FIELD_NAME = 'redcap_event_name';
					$ob2->ELEMENT_LABEL = 'Event Name';
					$ob2->ELEMENT_ENUM = $evtEnum;
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}
				
				// Repeating forms/events
				if ($hasRepeatInstanceField || $hasRepeatInstrumentField) 
				{					
					// Add redcap_repeat_instrument
					if ($hasRepeatInstrumentField) {
						// Create enum for all repeating forms
						$RepeatingFormsEvents = $Proj->getRepeatingFormsEvents();
						// Create enum for forms with unique name as coded value
						$formEnumArray = array();
						foreach ($RepeatingFormsEvents as $these_forms) {
							if (!is_array($these_forms)) continue;
							foreach (array_keys($these_forms) as $this_form) {
								$this_form_full = "$this_form, " . str_replace($orig, $repl, strip_tags(label_decode($Proj->forms[$this_form]['menu'])));
								if (in_array($this_form_full, $formEnumArray)) continue;
								$formEnumArray[] = $this_form_full;
							}
						}
						$formEnum = implode(" \\n ", $formEnumArray);
						// Alter $meta_array
						$ob2 = new stdClass();
						$ob2->ELEMENT_TYPE = 'select';
						$ob2->FIELD_NAME = 'redcap_repeat_instrument';
						$ob2->ELEMENT_LABEL = 'Repeat Instrument';
						$ob2->ELEMENT_ENUM = $formEnum;
						$ob2->FIELD_UNITS = '';
						$ob2->ELEMENT_VALIDATION_TYPE = '';
						$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
						// Add pseudo-field to array
						$field_names_prepend[] = $ob2->FIELD_NAME;
					}
					
					// Add redcap_repeat_instance
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'text';
					$ob2->FIELD_NAME = 'redcap_repeat_instance';
					$ob2->ELEMENT_LABEL = 'Repeat Instance';
					$ob2->ELEMENT_ENUM = '';
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = 'int';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}
				
				// If project has DAGs, add new column for group name
				if ($exportDags)
				{
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'select';
					$ob2->FIELD_NAME = 'redcap_data_access_group';
					$ob2->ELEMENT_LABEL = 'Data Access Group';
					$ob2->ELEMENT_ENUM = $dagEnum;
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}

				// Add survey identifier (unless we've set it to remove all identifiers - treat survey identifier same as field identifier)
				if ($exportSurveyFields && !$do_remove_identifiers) {
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'text';
					$ob2->FIELD_NAME = 'redcap_survey_identifier';
					$ob2->ELEMENT_LABEL = 'Survey Identifier';
					$ob2->ELEMENT_ENUM = '';
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}

				// If surveys exist, as timestamp and identifier fields
				if ($exportSurveyFields && (!isset($prev_form) || $prev_form != $this_form || ($prev_form == $this_form && $prev_field == $Proj->table_pk))
					&& $ob->FIELD_NAME != $Proj->table_pk && isset($Proj->forms[$this_form]['survey_id']))
				{
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'text';
					$ob2->FIELD_NAME = $this_form.'_timestamp';
					$ob2->ELEMENT_LABEL = 'Survey Timestamp';
					$ob2->ELEMENT_ENUM = '';
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
				}
			}

			// Set values for next loop
			$prev_form = $this_form;
			$prev_field = $ob->FIELD_NAME;
		}

		// Now reset field_names array
		$field_names = array_keys($meta_array);


		// $spss_data_type_array = "";
		$spss_format_dates   = "";
		$spss_variable_label = "VARIABLE LABEL ";
		$spss_variable_level = array();
		$sas_label_section = "\ndata redcap;\n\tset redcap;\n";
		$sas_value_label = "\nproc format;\n";
		$sas_input = "input\n";
		$sas_informat = "";
		$sas_format = "";
		$stata_insheet = "import delimited ";
		$stata_var_label = "";
		$stata_inf_label = "";
		$stata_value_label = "";
		$stata_date_format = "";

		// If there are missing data codes, add their variables here
		$sas_mis_inval = $sas_mis_val = $sas_mis_valstr = "";
        if ($hasMissingDataCodes) {
            // Convert missing codes to letters for numerical fields
            $count = 0;
			$missingDataCodesLettersKey = $missingDataCodesLettersLabel = $missingDataCodes2 = array();
            foreach ($missingDataCodes as $key=>$val) {
				if ($count >= 0 && $count < 26) {
					$append_text = chr(97+$count);
				} else {
					$append_text = chr(96+floor($count/26)) . chr(97+($count%26));
				}
				$missingDataCodes2[] = "'$key'='".str_replace($orig, $repl, $val)."'";
				$missingDataCodesLettersKey[] = "'$key'=.".$append_text;
				$missingDataCodesLettersLabel[] = ".".$append_text."='".str_replace($orig, $repl, $val)."'";
				$count++;
			}
			$sas_mis_inval = "%let mis_inval=".implode(" ", $missingDataCodesLettersKey).";";
			$sas_mis_val = "%let mis_val=".implode(" ", $missingDataCodesLettersLabel).";";
			$sas_mis_valstr = "%let mis_valstr=".implode(" ", $missingDataCodes2).";";
			$sas_value_label .= "\t$sas_mis_inval\n\t$sas_mis_val\n\t$sas_mis_valstr\n\n";
			// Set variables to be used in syntax
			$sas_mis_inval = " &mis_inval";
			$sas_mis_val = " &mis_val";
			$sas_mis_valstr = " &mis_valstr";
			unset($missingDataCodes2, $missingDataCodesLettersKey, $missingDataCodesLettersLabel);
        }
		$first_label = true;
		$large_name_counter = 0;
		$large_name = false;
		$sas_mc_formats = array();

		// Obtain all validation types to get the data format of each field (so we can export each truly as a data type rather than
		// being tied to their validation name).
		$valTypes = getValTypes();

		//print_array($meta_array);print_array($field_names);exit;


		// Loop through all metadata fields
		for ($x = 0; $x <= count($field_names) + 1; $x++)
		{

			if (($x % 5)== 0 && $x != 0) {
				$spss_string .=  "\n\t";
			}
			$large_name = false;

			// Set field object for this loop
			$ob = isset($field_names[$x]) ? $meta_array[$field_names[$x]] : null;

			if($ob == null)
			{
				continue;
			}

			$hasMissingDataCodesThis = ($hasMissingDataCodes && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$ob->FIELD_NAME]['misc']));

			// Remove any . or - in the field name (as a result of checkbox raw values containing . or -)
			// $ob->FIELD_NAME = str_replace(array("-", "."), array("_", "_"), (string)$ob->FIELD_NAME);

			// Convert "sql" field types to "select" field types so that their Select Choices come out correctly in the syntax files.
			if ($ob->ELEMENT_TYPE == "sql")
			{
				// Change to select
				$ob->ELEMENT_TYPE = "select";
				// Now populate it's choices by running the query
				$ob->ELEMENT_ENUM = getSqlFieldEnum($ob->ELEMENT_ENUM);
			}
			elseif ($ob->ELEMENT_TYPE == "yesno")
			{
				$ob->ELEMENT_ENUM = YN_ENUM;
			}
			elseif ($ob->ELEMENT_TYPE == "truefalse")
			{
				$ob->ELEMENT_ENUM = TF_ENUM;
			}

			//Remove any offending characters from label (do slightly different for SAS)
			$ob->ELEMENT_LABEL = strip_tags2(str_replace($orig, ($stats_package == 'sas' ? $repl_sas_choices : $repl), label_decode(html_entity_decode($ob->ELEMENT_LABEL, ENT_QUOTES))));

			if ($field_names[$x] != "") {
				if (strlen($field_names[$x]) >= 31) {
					$short_name = substr($field_names[$x],0,20) . "_v_" . $large_name_counter;
					$sas_label_section .= "\tlabel " . $short_name ."='" . $ob->ELEMENT_LABEL . "';\n";
					$stata_var_label .= "label variable " . $short_name . ' "' . $ob->ELEMENT_LABEL . '"' . "\n";
					$stata_insheet .= $short_name . " ";
					$large_name_counter++;
					$large_name = true;
				}
				if (!$large_name) {
					$sas_label_section .= "\tlabel " . $field_names[$x] ."='" . $ob->ELEMENT_LABEL . "';\n";
					$stata_var_label .= "label variable " . $field_names[$x] . ' "' . $ob->ELEMENT_LABEL . '"' . "\n";
					$stata_insheet .= $field_names[$x] . " ";
				}
				$spss_variable_label .= $field_names[$x] . " '" . $ob->ELEMENT_LABEL . "'\n\t/" ;
				$R_label_string .= "\nlabel(data$" . $field_names[$x] . ")=" . '"' . $ob->ELEMENT_LABEL . '"';
				if (($ob->FIELD_UNITS != Null) || ($ob->FIELD_UNITS != "")) {
					$R_units_string .= "\nunits(data$" . $field_names[$x] . ")=" . '"' .  $ob->FIELD_UNITS . '"';
				}
			}

			# Checking for single element enum (i.e. if it is coded with a number or letter)
			$single_element_enum = true;
			if (substr_count(((string)$ob->ELEMENT_ENUM),",") > 0) {
				$single_element_enum = false;
			}

			# Select value labels are created
			if (($ob->ELEMENT_TYPE == "yesno" || $ob->ELEMENT_TYPE == "truefalse" || $ob->ELEMENT_TYPE == "select"
				|| $ob->ELEMENT_TYPE == "advcheckbox" || $ob->ELEMENT_TYPE == "radio") && !preg_match("/\+\+SQL\+\+/",(string)$ob->ELEMENT_ENUM))
			{
				// Replace illegal characters from the Choice Labels (do slightly different for SAS)
				$ob->ELEMENT_ENUM = str_replace($orig, ($stats_package == 'sas' ? $repl_sas_choices : $repl), label_decode($ob->ELEMENT_ENUM));

				// In case we have any duplicate codes in our choices, then merge the labels together for any duplicates
				$this_field_choices = array();
				foreach (parseEnum($ob->ELEMENT_ENUM) as $key=>$labl) {
					$this_field_choices[] = "$key, $labl";
				}
				$ob->ELEMENT_ENUM = implode(" \\n ", $this_field_choices);

				//Place $ in front of SAS value if using non-numeric coded values for dropdowns/radios
				$sas_val_enum_num = ""; //default
                $sas_this_value = $sas_this_invalue = "";
				$numericChoices = true;
				foreach (array_keys(parseEnum($ob->ELEMENT_ENUM)) as $key) {
					// If at least one key is not numeric, then stop looping because we have all we need.
					if (!is_numeric($key)) {
						$sas_val_enum_num = "$";
						$numericChoices = false;
						break;
					}
				}
				if ($first_label) {
					if (!$single_element_enum) {
						$value_labels_spss .=  "\n" . (string)$ob->FIELD_NAME . " ";
					}
					$R_factors_string .= "\ndata$" . (string)$ob->FIELD_NAME . ".factor = factor(data$" . (string)$ob->FIELD_NAME . ",levels=c(";
					$R_levels_string .=  "\nlevels(data$" . (string)$ob->FIELD_NAME . ".factor)=c(";
					$first_label = false;
					if (!$large_name && !$single_element_enum) {
						if ($numericChoices) {
							$stata_inf_label .= "\nlabel values " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_\n";
							$stata_value_label = "label define " . (string)$ob->FIELD_NAME . "_ ";
						}
						$sas_mc_formats[] = $ob->FIELD_NAME;
						$sas_this_value .= "\tvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
						$sas_this_invalue .= "\tinvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
						$sas_format_string .= "\n\tformat " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_.;\n";
					} else if ($large_name && !$single_element_enum) {
						if ($numericChoices) {
							$stata_value_label .= "label define " . $short_name . "_ ";
							$stata_inf_label .= "\nlabel values " . $short_name . " " . $short_name . "_\n";
						}
						$sas_mc_formats[] = $short_name;
						$sas_this_value .= "\tvalue $sas_val_enum_num" . $short_name . "_ ";
						$sas_this_invalue .= "\tinvalue $sas_val_enum_num" . $short_name . "_ ";
						$sas_format_string .= "\n\tformat " . $short_name . " " . $short_name . "_.;\n";
					}
				} else if(!$first_label) {
					if (!$single_element_enum) {
						$value_labels_spss .= "\n/" . (string)$ob->FIELD_NAME . " ";
						if (!$large_name) {
							$sas_mc_formats[] = $ob->FIELD_NAME;
							$sas_this_value .= "\tvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
							$sas_this_invalue .= "\tinvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
							$sas_format_string .= "\tformat " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_.;\n";
							if ($numericChoices) {
								$stata_value_label .= "\nlabel define " . (string)$ob->FIELD_NAME . "_ ";
								$stata_inf_label .= "label values " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_\n";
							}
						}
					}
					$R_factors_string .= "data$" . (string)$ob->FIELD_NAME . ".factor = factor(data$" . (string)$ob->FIELD_NAME . ",levels=c(";
					$R_levels_string .=  "levels(data$" . (string)$ob->FIELD_NAME . ".factor)=c(";
					if ($large_name && !$single_element_enum) {
						$sas_mc_formats[] = $short_name;
						$sas_this_value .= "\tvalue $sas_val_enum_num" . $short_name . "_ ";
						$sas_this_invalue .= "\tinvalue $sas_val_enum_num" . $short_name . "_ ";
						$sas_format_string .= "\tformat " . $short_name . " " . $short_name . "_.;\n";
						if ($numericChoices) {
							$stata_value_label .= "\nlabel define " . $short_name . "_ "; //LS inserted this line 24-Feb-2012
							$stata_inf_label .= "label values " . $short_name . " " . $short_name . "_\n";
						}
					}
				}

				// Collect SAS values for MC fields for format applied near end

				$first_new_line_explode_array = explode("\\n",(string)$ob->ELEMENT_ENUM);

				// Loop through multiple choice options
				$select_is_text = false;
				$select_determining_array = array();
				for ($counter = 0;$counter < count($first_new_line_explode_array);$counter++) {
					if (!$single_element_enum) {

						// SAS: Add line break after 2 multiple choice options
						if (($counter % 2) == 0 && $counter != 0) {
							$sas_this_value   .= "\n\t\t";
							$sas_this_invalue   .= "\n\t\t";
							$value_labels_spss .= "\n\t";
						}

						$second_comma_explode = explode(",",$first_new_line_explode_array[$counter],2);
						if (trim($second_comma_explode[0]) == '') continue;
						$value_labels_spss .= "'" . trim($second_comma_explode[0]) . "' ";
						$value_labels_spss .= "'" . trim($second_comma_explode[1]) . "' ";
						if (!is_numeric(trim($second_comma_explode[0])) && is_numeric(substr(trim($second_comma_explode[0]), 0, 1))) {
							// if enum raw value is not a number BUT begins with a number, add quotes around it for SAS only (parsing issue)
							$sas_this_value .= "'" . trim($second_comma_explode[0]) . "'=";
							$sas_this_invalue .= "'" . trim($second_comma_explode[0]) . "'=";
						} else {
							$sas_this_value .= trim($second_comma_explode[0]) . "=";
							$sas_this_invalue .= trim($second_comma_explode[0]) . "=";
						}
						$sas_this_value .= "'" . trim($second_comma_explode[1]) . "' ";
						$sas_this_invalue .= "'" . trim($second_comma_explode[1]) . "' ";
						if ($numericChoices) {
							$stata_value_label .= trim($second_comma_explode[0]) . " ";
							$stata_value_label .= "\"" . trim($second_comma_explode[1]) . "\" ";
						}
						$select_determining_array[] = $second_comma_explode[0];
						$R_factors_string .= '"' . trim($second_comma_explode[0]) . '",';
						$R_levels_string .= '"' . trim($second_comma_explode[1]) . '",';
					} else {
						$select_determining_array[] = $second_comma_explode[0];
						$R_factors_string .= '"' . trim($first_new_line_explode_array[$counter]) . '",';
						$R_levels_string .= '"' . trim($first_new_line_explode_array[$counter]) . '",';
					}
				}
				$R_factors_string = rtrim($R_factors_string,",");
				$R_factors_string .= "))\n";   //pharris 09/28/05
				$R_levels_string = rtrim($R_levels_string,",");
				$R_levels_string .=  ")\n";

				if (!$single_element_enum) {
					foreach ($select_determining_array as $value) {
						if (preg_match("/([A-Za-z])/",$value)) {
							$select_is_text = true;
						}
					}
				} else {
					foreach ($first_new_line_explode_array as $value) {
						if (preg_match("/([A-Za-z])/",$value)) {
							$select_is_text = true;
						}
					}
				}

				if (!$single_element_enum) {
					$sas_this_value = rtrim($sas_this_value," ");
					$sas_this_invalue = rtrim($sas_this_invalue," ");
					// Missing data codes
					if ($hasMissingDataCodesThis && !isset(Project::$reserved_field_names[$ob->FIELD_NAME])) {
						if (!$Proj->isFormStatus($ob->FIELD_NAME)) {
						    $sas_this_value .= $numericChoices ? "\n\t\t&mis_val" : "\n\t\t&mis_valstr";
						} else {
							$sas_this_invalue = "";
                        }
						if (!$select_is_text && !$Proj->isFormStatus($ob->FIELD_NAME)) {
							$sas_this_invalue = "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [best32.]";
						}
					}
					if (trim($sas_this_value) == '') {
						$sas_this_value = "";
					} else {
						$sas_this_value .= ";\n";
                    }
					if (trim($sas_this_invalue) == '' || $select_is_text) {
						$sas_this_invalue = "";
					} else {
						$sas_this_invalue .= ";\n";
                    }
					// Do not add invalue/value if choice codes are all numeric and not utilizing Missing Data Codes
					if ($hasMissingDataCodesThis || (!$hasMissingDataCodesThis && !$numericChoices)) {
						$sas_value_label .= $sas_this_invalue;
					}
					$sas_value_label .= $sas_this_value;
				}

			} else if (preg_match("/\+\+SQL\+\+/",(string)$ob->ELEMENT_ENUM)) {
				$select_is_text = true;
			}

			################################################################################
			################################################################################

			# If the ELEMENT_VALIDATION_TYPE is a float the data is define as a Number
			if ($ob->ELEMENT_VALIDATION_TYPE == "float" || $ob->ELEMENT_TYPE == "calc"
				// Also check if the data type of the validation type is "number"
			|| (isset($valTypes[$ob->ELEMENT_VALIDATION_TYPE]) && $valTypes[$ob->ELEMENT_VALIDATION_TYPE]['data_type'] == 'number'))
			{
			    if ($hasMissingDataCodesThis) {
					$sas_value_label .= "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [best32.];\n";
					$sas_value_label .= "\tvalue " . $ob->FIELD_NAME . "_ &mis_val other = [best32.];\n";
                }
				$spss_string  .= $ob->FIELD_NAME . " (F8.2) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best32") . ". ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best12") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . "\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " .  $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best32") . ". ;\n";
					$sas_format .= "\tformat " .  $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best12") . ". ;\n";
					$sas_input .= "\t" .  $short_name . "\n";
				}
				// $spss_data_type_array[$x] = "NUMBER";
				$spss_variable_level[] = $ob->FIELD_NAME . " (SCALE)";

			} elseif ($ob->ELEMENT_TYPE == "slider" || $ob->ELEMENT_VALIDATION_TYPE == "int") {
				if ($hasMissingDataCodesThis) {
					$sas_value_label .= "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [best32.];\n";
					$sas_value_label .= "\tvalue " . $ob->FIELD_NAME . "_ &mis_val other = [best32.];\n";
				}
				$spss_string  .= $ob->FIELD_NAME . " (F8) ";
				if(!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best32") . ". ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best12") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . "\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " .  $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best32") . ". ;\n";
					$sas_format .= "\tformat " .  $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "best12") . ". ;\n";
					$sas_input .= "\t" .  $short_name . "\n";
				}
				// $spss_data_type_array[$x] = "NUMBER";
				$spss_variable_level[] = $ob->FIELD_NAME . " (SCALE)";

			# If the ELEMENT_VALIDATION_TYPE is a DATE treat the data as a date
			} elseif ($ob->ELEMENT_VALIDATION_TYPE == "date" || $ob->ELEMENT_VALIDATION_TYPE == "date_ymd"
				|| $ob->ELEMENT_VALIDATION_TYPE == "date_mdy" || $ob->ELEMENT_VALIDATION_TYPE == "date_dmy") {
				if ($hasMissingDataCodesThis) {
					$sas_value_label .= "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [yymmdd10.];\n";
					$sas_value_label .= "\tvalue " . $ob->FIELD_NAME . "_ &mis_val other = [date9.];\n";
				}
				$spss_string  .= $ob->FIELD_NAME . " (SDATE10) ";
				$spss_format_dates .= "FORMATS " . $ob->FIELD_NAME . "(ADATE10).\n";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "yymmdd10") . ". ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "yymmdd10") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "\ntostring " . $ob->FIELD_NAME . ", replace";
					$stata_date_format .= "\ngen _date_ = date(" .  $ob->FIELD_NAME . ",\"YMD\")\n";
					$stata_date_format .= "drop " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "rename _date_ " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "format " . $ob->FIELD_NAME . " %dM_d,_CY\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "yymmdd10") . ". ;\n";
					$sas_format .= "\tformat " . $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "yymmdd10") . ". ;\n";
					$sas_input .= "\t" . $short_name . "\n";
					$stata_date_format .= "\ntostring " . $short_name . ", replace";
					$stata_date_format .= "\ngen _date_ = date(" .   $short_name . ",\"YMD\")\n";
					$stata_date_format .= "drop " .  $short_name . "\n";
					$stata_date_format .= "rename _date_ " .  $short_name . "\n";
					$stata_date_format .= "format " . $short_name . " %dM_d,_CY\n";
				}

			# If the ELEMENT_VALIDATION_TYPE is a DATETIME treat the data as a datetime
			} elseif ($ob->ELEMENT_VALIDATION_TYPE == "datetime" || $ob->ELEMENT_VALIDATION_TYPE == "datetime_ymd"
				|| $ob->ELEMENT_VALIDATION_TYPE == "datetime_mdy" || $ob->ELEMENT_VALIDATION_TYPE == "datetime_dmy") {
				if ($hasMissingDataCodesThis) {
					// $sas_value_label .= "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [ymddttm16.];\n";
					// $sas_value_label .= "\tvalue " . $ob->FIELD_NAME . "_ &mis_val other = [datetime16.];\n";
					$sas_value_label .= "\tvalue $" . $ob->FIELD_NAME . "_ &mis_valstr;\n";
				}
				$spss_string .= $ob->FIELD_NAME . " (A500) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$500. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " $" . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "500") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . " \$\n";
					$stata_date_format .= "\ntostring " . $ob->FIELD_NAME . ", replace";
					$stata_date_format .= "\ngen double _temp_ = Clock(" .  $ob->FIELD_NAME . ",\"YMDhm\")\n";
					$stata_date_format .= "drop " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "rename _temp_ " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "format " . $ob->FIELD_NAME . " %tCMonth_dd,_CCYY_HH:MM\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$500. ;\n";
					$sas_format .= "\tformat " . $short_name . " $" . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "500") . ". ;\n";
					$sas_input .= "\t" . $short_name . " \$\n";
					$stata_date_format .= "\ntostring " . $short_name . ", replace";
					$stata_date_format .= "\ngen double _temp_ = Clock(" .   $short_name . ",\"YMDhm\")\n";
					$stata_date_format .= "drop " .  $short_name . "\n";
					$stata_date_format .= "rename _temp_ " .  $short_name . "\n";
					$stata_date_format .= "format " . $short_name . " %tCMonth_dd,_CCYY_HH:MM\n";
				}

			# If the ELEMENT_VALIDATION_TYPE is a DATETIME /W SECONDS treat the data as a datetime w/ seconds
			} elseif ($ob->ELEMENT_VALIDATION_TYPE == "datetime_seconds" || $ob->ELEMENT_VALIDATION_TYPE == "datetime_seconds_ymd"
				|| $ob->ELEMENT_VALIDATION_TYPE == "datetime_seconds_mdy" || $ob->ELEMENT_VALIDATION_TYPE == "datetime_seconds_dmy") {
				if ($hasMissingDataCodesThis) {
					// $sas_value_label .= "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [ymddttm19.];\n";
					// $sas_value_label .= "\tvalue " . $ob->FIELD_NAME . "_ &mis_val other = [datetime19.];\n";
					$sas_value_label .= "\tvalue $" . $ob->FIELD_NAME . "_ &mis_valstr;\n";
				}
				$spss_string .= $ob->FIELD_NAME . " (A500) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$500. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " $" . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "500") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . " \$\n";
					$stata_date_format .= "\ntostring " . $ob->FIELD_NAME . ", replace";
					$stata_date_format .= "\ngen double _temp_ = Clock(" .  $ob->FIELD_NAME . ",\"YMDhms\")\n";
					$stata_date_format .= "drop " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "rename _temp_ " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "format " . $ob->FIELD_NAME . " %tCMonth_dd,_CCYY_HH:MM:SS\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$500. ;\n";
					$sas_format .= "\tformat " . $short_name . " $" . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "500") . ". ;\n";
					$sas_input .= "\t" . $short_name . " \$\n";
					$stata_date_format .= "\ntostring " . $short_name . ", replace";
					$stata_date_format .= "\ngen double _temp_ = Clock(" .   $short_name . ",\"YMDhms\")\n";
					$stata_date_format .= "drop " .  $short_name . "\n";
					$stata_date_format .= "rename _temp_ " .  $short_name . "\n";
					$stata_date_format .= "format " . $short_name . " %tCMonth_dd,_CCYY_HH:MM:SS\n";
				}

			# If the ELEMENT_VALIDATION_TYPE is TIME (military)
			} elseif ($ob->ELEMENT_VALIDATION_TYPE == "time") {
				if ($hasMissingDataCodesThis) {
					$sas_value_label .= "\tinvalue " . $ob->FIELD_NAME . "_ &mis_inval other = [time5.];\n";
					$sas_value_label .= "\tvalue " . $ob->FIELD_NAME . "_ &mis_val other = [time5.];\n";
				}
				$spss_string .= $ob->FIELD_NAME . " (TIME5) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "time5") . ". ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "time5") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . "\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "time5") . ". ;\n";
					$sas_format .= "\tformat " . $short_name . " " . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "time5") . ". ;\n";
					$sas_input .= "\t" . $short_name . "\n";
				}
			# If the object type is select then the variable $select_is_text is checked to
			# see if it is a TEXT or a NUMBER and treated accordanly.
			} elseif($ob->ELEMENT_TYPE == "yesno" || $ob->ELEMENT_TYPE == "truefalse" || $ob->ELEMENT_TYPE == "select"
				|| $ob->ELEMENT_TYPE == "advcheckbox" || $ob->ELEMENT_TYPE == "radio") {
				if ($select_is_text) {
					$temp_trim = rtrim("varchar(500)",")");
					# Divides the string to get the number of caracters
					$temp_explode_number = explode("(",$temp_trim);
					$spss_string  .= $ob->FIELD_NAME . " (A" . $temp_explode_number[1] . ") ";
					if (!$large_name) {
						$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$". $temp_explode_number[1] .". ;\n";
						$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$". $ob->FIELD_NAME ."_. ;\n";
						$sas_input .= "\t" . $ob->FIELD_NAME . " \$\n";
					} elseif($large_name) {
						$sas_informat .= "\tinformat " . $short_name . " \$". $temp_explode_number[1] .". ;\n";
						$sas_format .= "\tformat " . $short_name . " \$". $short_name ."_. ;\n";
						$sas_input .= "\t" . $short_name . " \$\n";
					}
				} else {
					$spss_string .= $ob->FIELD_NAME . " (F3) ";
					if (!$large_name) {
					    if ($Proj->isFormStatus($ob->FIELD_NAME) || !$hasMissingDataCodesThis) {
							$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
							$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
                        } else {
					        $sas_informat .= "\tinformat " . $ob->FIELD_NAME . " " . $ob->FIELD_NAME . "_. ;\n";
							$sas_format .= "\tformat " . $ob->FIELD_NAME . " " . $ob->FIELD_NAME . "_. ;\n";
                        }
						$sas_input .= "\t" . $ob->FIELD_NAME . "\n";
					} elseif ($large_name) {
						if ($Proj->isFormStatus($ob->FIELD_NAME) || !$hasMissingDataCodesThis) {
							$sas_informat .= "\tinformat " . $short_name . " best32. ;\n";
							$sas_format .= "\tformat " . $short_name . " best12. ;\n";
						} else {
							$sas_informat .= "\tinformat " . $short_name . " " . $ob->FIELD_NAME . "_. ;\n";
							$sas_format .= "\tformat " . $short_name . " " . $ob->FIELD_NAME . "_. ;\n";
						}
						$sas_input .= "\t" . $short_name . "\n";
					}
				}


			# If the object type is text a treat the data like a text and look for the length
			# that is specified in the database
			} elseif ($ob->ELEMENT_TYPE == "text" || $ob->ELEMENT_TYPE == "calc" || $ob->ELEMENT_TYPE == "file") {
				if ($hasMissingDataCodesThis && $ob->FIELD_NAME != $Proj->table_pk) {
					$sas_value_label .= "\tvalue $" . $ob->FIELD_NAME . "_ &mis_valstr;\n";
				}
				$spss_string .= $ob->FIELD_NAME . " (A1000) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$500. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " " . ($Proj->table_pk == $ob->FIELD_NAME || !$hasMissingDataCodesThis ? "\$500" : "$".$ob->FIELD_NAME) . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . " \$\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$500. ;\n";
					$sas_format .= "\tformat " . $short_name . " " . ($Proj->table_pk == $ob->FIELD_NAME || !$hasMissingDataCodesThis ? "\$500" : "$".$ob->FIELD_NAME) . ". ;\n";
					$sas_input .= "\t" . $short_name . " \$\n";
				}


			# If the object type is textarea a treat the data like a text and specify a large
			# string size.
			} elseif ($ob->ELEMENT_TYPE == "textarea") {
				if ($hasMissingDataCodesThis) {
					$sas_value_label .= "\tvalue $" . $ob->FIELD_NAME . "_ &mis_valstr;\n";
				}
				$spss_string .= $ob->FIELD_NAME . " (A30000) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$5000. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " $" . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "5000") . ". ;\n";
					$sas_input .= "\t" . $ob->FIELD_NAME . " \$\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$5000. ;\n";
					$sas_format .= "\tformat " . $short_name . " $" . ($hasMissingDataCodesThis ? $ob->FIELD_NAME : "5000") . ". ;\n";
					$sas_input .= "\t" . $short_name . " \$\n";
				}
			}

		}

		//Finish up syntax files
		$spss_string = rtrim($spss_string);
		$spss_string .= ".\n";
		$spss_string .= "\nVARIABLE LEVEL " . implode("\n\t/", $spss_variable_level) . ".\n";
		$spss_string .= "\n" . substr_replace($spss_variable_label,".",-3) . "\n\n";
		$spss_string .= rtrim($value_labels_spss) ;
		$spss_string .= ".\n\n$spss_format_dates\nSET LOCALE=en_us.\nEXECUTE.\n";

		$spss_string = str_replace("data_place_holder_name",$data_file_name,$spss_string);

		$sas_value_label .= "\n\trun;\n";
		$sas_read_string = "/* Edit the following line to reflect the full path to your CSV file */\n"
                         .  "%let csv_file = '$data_file_name';\n\nOPTIONS nofmterr;\n"
                         .  $sas_value_label;
		$sas_read_string .= "\ndata work.redcap; %let _EFIERR_ = 0;\n";
		$sas_read_string .= "infile &csv_file  delimiter = ',' MISSOVER DSD lrecl=32767 firstobs=1 ;\n";
		$sas_read_string .= "\n" . $sas_informat ;
		$sas_read_string .= "\n" . $sas_format;
		$sas_read_string .= "\n" . $sas_input;
		$sas_read_string .= ";\n";
		$sas_read_string .= "if _ERROR_ then call symput('_EFIERR_',\"1\");\n";
		$sas_read_string .= "run;\n\nproc contents;run;\n";
		$sas_read_string .= $sas_label_section;
		foreach ($sas_mc_formats as $val) {
			$sas_read_string .= "\tformat {$val} {$val}_.;\n";
        }
		$sas_read_string .= "run;\n";
		$sas_read_string .= "\nproc contents data=redcap;";
		$sas_read_string .= "\nproc print data=redcap;";
		$sas_read_string .= "\nrun;";

		$stata_order = "order " . substr($stata_insheet, 17);
		$stata_insheet .= "using " . "\"" . $data_file_name . "\", varnames(nonames)";

		$stata_string .= $stata_insheet . "\n\n";
		$stata_string .= "label data " . "\"" . $data_file_name  . "\"" . "\n\n";
		$stata_string .= $stata_value_label . "\n";
		$stata_string .= $stata_inf_label. "\n\n";
		$stata_string .= $stata_date_format . "\n";
		$stata_string .= $stata_var_label . "\n";
		$stata_string .= $stata_order . "\n";
		$stata_string .= "set more off\ndescribe\n";

		$R_string .= "#Read Data\ndata=read.csv('" . $data_file_name . "')\n";
		$R_string .= $R_label_string;
		$R_string .= $R_units_string;
		$R_string .= $R_factors_string;
		$R_string .= $R_levels_string;

		// Return syntax based on package
		if ($stats_package == 'stata') {
			return strip_tags($stata_string);
		} elseif ($stats_package == 'r') {
			return strip_tags($R_string);
		} elseif ($stats_package == 'sas') {
			return strip_tags($sas_read_string);
		} elseif ($stats_package == 'spss') {
			return strip_tags($spss_string);
		} else {
			return '';
		}
	}


	// Get download icon's HTML for a specific export type (e.g, spss, csvraw)
	public static function getDownloadIcon($exportFormat, $dateShifted=false, $includeOdmMetadata=false)
	{
		switch ($exportFormat) {
			case 'spss':
				$icon = 'download_spss.gif';
				break;
			case 'r':
				$icon = 'download_r.gif';
				break;
			case 'sas':
				$icon = 'download_sas.gif';
				break;
			case 'stata':
				$icon = 'download_stata.gif';
				break;
			case 'csvlabels':
				$icon = ($dateShifted) ? 'download_csvexcel_labels_ds.gif' : 'download_csvexcel_labels.gif';
				break;
			case 'csvraw':
				$icon = ($dateShifted) ? 'download_csvexcel_raw_ds.gif' : 'download_csvexcel_raw.gif';
				break;
			case 'odm':
				if ($includeOdmMetadata) {
					$icon = ($dateShifted) ? 'download_xml_project_ds.gif' : 'download_xml_project.gif';
				} else {
					$icon = ($dateShifted) ? 'download_xml_ds.gif' : 'download_xml.gif';
				}
				break;
			default:
				$icon = ($dateShifted) ? 'download_csvdata_ds.gif' : 'download_csvdata.gif';
		}
		// Return image html
		return RCView::img(array('src'=>$icon));
	}


	// Store the export file after getting the docs_id from redcap_docs
	public static function storeExportFile($original_filename, &$file_content, $archiveFile=false, $dateShiftDates=false)
	{
		global $edoc_storage_option;

		## Create the stored name of the file as it wll be stored in the file system
		$stored_name = date('YmdHis') . "_pid" . PROJECT_ID . "_" . generateRandomHash(6) . getFileExt($original_filename, true);
		$file_extension = getFileExt($original_filename);
		switch (strtolower($file_extension)) {
			case 'csv':
				$mime_type = 'application/csv';
				break;
			case 'xml':
				$mime_type = 'application/xml';
				break;
			default:
				$mime_type = 'application/octet-stream';
		}

		// If file is UTF-8 encoded, then add BOM
		// Do NOT use addBOMtoUTF8() on Stata syntax file (.do) because BOM causes issues in syntax file
		if (strtolower($file_extension) != 'do') {
			$file_content = addBOMtoUTF8($file_content);
		}

		// If Gzip enabled, then gzip the file and append filename with .gz extension
		list ($file_content, $stored_name, $gzipped) = gzip_encode_file($file_content, $stored_name);

		// Get file size in bytes
		$docs_size = strlen($file_content);

		// Add file to file system
		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// Store locally
			$fp = fopen(EDOC_PATH . $stored_name, 'w');
			if ($fp !== false && fwrite($fp, $file_content) !== false) {
				// Close connection
				fclose($fp);
			} else {
				// Send error response
				return false;
			}
		// Add file to S3
		} elseif ($edoc_storage_option == '2') {
			$s3 = Files::s3client();
			$result = $s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_name, 'Body'=>$file_content, 'ACL'=>'private'));
			if (!$result) {
				// Send error response
				return false;
			}
		// Add file to Azure
		} elseif ($edoc_storage_option == '4') {
			$blobClient = Files::azureBlobClient();
			$result = $blobClient->createBlockBlob($GLOBALS['azure_container'], $stored_name, $file_content);
			if (!$result) {
				// Send error response
				return false;
			}
		} else {
			// Store using WebDAV
			if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
			$wdc = new WebdavClient();
			$wdc->set_server($webdav_hostname);
			$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
			$wdc->set_user($webdav_username);
			$wdc->set_pass($webdav_password);
			$wdc->set_protocol(1); // use HTTP/1.1
			$wdc->set_debug(false); // enable debugging?
			if (!$wdc->open()) {
				// Send error response
				return false;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->put($webdav_path . $stored_name, $file_content);
			$wdc->close();
		}
		## Add file info to edocs_metadata table
		// If not archiving file in File Repository, then set to be deleted in 1 hour
		$delete_time = ($archiveFile ? "" : NOW);
		// Add to table
		$sql = "insert into redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id,
				stored_date, delete_date, gzipped) values ('" . db_escape($stored_name) . "', '$mime_type', '" . db_escape($original_filename) . "',
				'" . db_escape($docs_size) . "', '" . db_escape($file_extension) . "', " . PROJECT_ID . ", '" . NOW . "', " . checkNull($delete_time) . ", $gzipped)";
		if (!db_query($sql)) {
			// Send error response
			return false;
		}
		// Get edoc_id
		$edoc_id = db_insert_id();
		## Add to doc_to_edoc table
		// Set flag if data is date shifted
		$dateShiftFlag = ($dateShiftDates ? "DATE_SHIFT" : "");
		// Set "comment" in docs table
		if (strtolower($file_extension) == 'csv') {
			$docs_comment = "Data export file created by " . USERID . " on " . date("Y-m-d-H-i-s");
		} else {
			if ($file_extension == 'sps') {
				$stats_package_name = 'Spss';
			} elseif ($file_extension == 'do') {
				$stats_package_name = 'Stata';
			} else {
				$stats_package_name = camelCase($file_extension);
			}
			$docs_comment = "$stats_package_name syntax file created by " . USERID . " on " . date("Y-m-d-H-i-s");
		}
		// Archive in redcap_docs table
		$sql = "INSERT INTO redcap_docs (project_id, docs_name, docs_file, docs_date, docs_size, docs_comment, docs_type,
				docs_rights, export_file, temp) VALUES (" . PROJECT_ID . ", '" . db_escape($original_filename) . "', NULL, '" . TODAY . "',
				'$docs_size', '" . db_escape($docs_comment). "', '$mime_type', " . checkNull($dateShiftFlag) . ", 1,
				" . checkNull($archiveFile ? "0" : "1") . ")";
		if (db_query($sql)) {
			$docs_id = db_insert_id();
			// Add to redcap_docs_to_edocs also
			$sql = "insert into redcap_docs_to_edocs (docs_id, doc_id) values ($docs_id, $edoc_id)";
			db_query($sql);
		} else {
			// Could not store in table, so remove from edocs_metadata also
			db_query("delete from redcap_edocs_metadata where doc_id = $edoc_id");
			return false;
		}
		// Return successful response of docs_id from redcap_docs table
		return $docs_id;
	}


	// Return array list of all fields in current project that should be removed due to De-Identified data export rights
	public static function deidFieldsToRemove($fields=array(), $removeOnlyIdentifiers=false,
											  $removeDateFields=true, $removeRecordIdIfIdentifier=true)
	{
		global $Proj;
		// Put all fields to remove in an array
		$fieldsToRemove = array();
		// If $fields is empty, assume ALL fields
		if (empty($fields) || !is_array($fields)) {
			$fields = array_keys($Proj->metadata);
		}
		// Loop through fields
		foreach ($fields as $field) {
			// Get field type and validation type
			$this_field_type = $Proj->metadata[$field]['element_type'];
			$this_val_type = $Proj->metadata[$field]['element_validation_type'];
			$this_phi = $Proj->metadata[$field]['field_phi'];
			// Skip record ID field (if flag is set to FALSE)
			if (!$removeRecordIdIfIdentifier && $field == $Proj->table_pk) continue;
			// Check if needs to be removed
			if (// Identifier field
				($this_phi == '1')
				// Unvalidated text field (freeform text)
				|| 	(!$removeOnlyIdentifiers && $this_field_type == 'text' && $this_val_type == '')
				// Notes field
				|| 	(!$removeOnlyIdentifiers && $this_field_type == 'textarea')
				// Date/time field (if flag is set to TRUE)
				|| 	(!$removeOnlyIdentifiers && $removeDateFields && $this_field_type == 'text' && substr($this_val_type, 0, 4) == 'date'))
			{
				// Remove the field from $fields
				$fieldsToRemove[] = $field;
			}
		}
		// Return array of fields to remove
		return $fieldsToRemove;
	}


	// Return docs_id of associated data file (either raw or label) for a specified stats package.
	// Pass an array of rows from redcap_docs + the stats package in all caps (SPSS, R, SAS, STATA)
	public static function getDataFileDocId($stats_package, $export_files_info, $get_labels_file=false)
	{
		global $app_name;
		if ($get_labels_file) {
			// Get the labels data file
			$search_phrase = "_DATA_LABELS_20";
			$search_phrase_legacy_prefix = "DATA_LABELS_".strtoupper($app_name)."_";
		} elseif ($stats_package == 'R') {
			// Get the raw data file with headers
			$search_phrase = "_DATA_20";
			$search_phrase_legacy_prefix = "DATA_WH".strtoupper($app_name)."_";
		} elseif ($stats_package == 'XML_PROJECT') {
			// Get the XML project file
			$search_phrase = ".REDCap.xml";
			$search_phrase_legacy_prefix = "DATA_WH".strtoupper($app_name)."_";
		} elseif ($stats_package == 'XML') {
			// Get the XML file
			$search_phrase = "_CDISC_ODM_20";
			$search_phrase_legacy_prefix = "DATA_WH".strtoupper($app_name)."_";
		} else {
			// Get the raw data file WITHOUT headers
			$search_phrase = "_DATA_NOHDRS_20";
			$search_phrase_legacy_prefix = "DATA_".strtoupper($app_name)."_";
		}
		// Loop through the array of files
		foreach ($export_files_info as $this_file) {
			// Ignore other stats syntax files
			if ($this_file['docs_type'] != 'DATA' && $this_file['docs_type'] != 'XML') continue;
			// If did not find correct data file, keep looping till we get it
			if (strpos($this_file['docs_name'], $search_phrase_legacy_prefix) === 0
				|| strpos($this_file['docs_name'], $search_phrase) !== false) {
				// Found it, so return the docs_id
				return $this_file['docs_id'];
			}
		}
		// Could not find it for some reason
		return '';
	}

	// Return html to render the TITLE of left-hand menu panel for Reports
	public static function outputReportPanelTitle()
	{
		global $lang, $isIE, $user_rights;
		$reportsList = '';
		//Build menu item for each separate report
		$menu_id = 'projMenuReports';
		$reportsListCollapsed = UIState::getMenuCollapseState(PROJECT_ID, $menu_id);
		$imgCollapsed = $reportsListCollapsed ? "toggle-expand.png" : "toggle-collapse.png";
		$reportsList .= "<div style='float:left;margin:2px 0;'>{$lang['app_06']}</div>
							<div class='opacity65 projMenuToggle' id='$menu_id'>"
							. RCView::a(array('href'=>'javascript:;'),
								RCView::img(array('src'=>$imgCollapsed, 'class'=>($isIE ? 'opacity65' : '')))
							  ) . "
						   </div>";
		if ($user_rights['reports']) {
			$reportsList .= "<div class='opacity65' id='menuLnkEditReports' style='float:right;margin-right:5px;'>"
								. RCView::i(array('class'=>'fas fa-pencil-alt fs10', 'style'=>'color:#000066;margin-right:2px;'), '')
								. RCView::a(array('href'=>APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID,'style'=>'font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'), $lang['global_27'])
							. "</div>";
			// Report Folders button
			$reportsList .= "<div class='opacity65' id='menuLnkProjectFolders' style='float:right;margin-right:13px;'>"
								. RCView::i(array('class'=>'fas fa-folder-open fs10', 'style'=>'color:#014101;margin-right:2px;'), '')
								. RCView::a(array('onclick'=>"openReportFolders();", 'href'=>'javascript:;', 'style'=>'font-size:11px;text-decoration:underline;color:#014101;font-weight:normal;'), $lang['control_center_4516'])
							. "</div>";
		}
		// Search
		$reportsList .= "<div id='searchReportsDiv' style='float:right;margin-right:5px;display:none;'>"
							. RCView::text(array('id'=>'searchReports', 'class'=>'x-form-text x-form-field', 'style'=>'padding:1px 5px;', 'placeholder'=>$lang['reporting_60']))
							. RCView::a(array('onclick'=>"closeSearchReports();", 'href'=>'javascript:;', 'style'=>'margin-right:5px;text-decoration:underline;font-weight:normal;'), 
								RCView::i(array('class'=>'fas fa-times', 'style'=>'font-size:13px;top:2px;margin-left:3px;'), '')
							  )
						. "</div>";
		$reportsList .= "<div class='opacity65' id='menuLnkSearchReports' style='float:right;margin-right:13px;'>"
							. RCView::a(array('onclick'=>"openSearchReports();", 'href'=>'javascript:;', 'style'=>'font-size:11px;text-decoration:underline;font-weight:normal;'), 
								RCView::i(array('class'=>'fas fa-search fs10', 'style'=>'margin-right:2px;'), '') .
								$lang['control_center_439']
							  )
						. "</div>";
		// Setup dialog
		$reportsList .= RCView::div(array('id'=>'report_folders_popup', 'class'=>'simpleDialog', 'title'=>"<div style='color:#008000;'><span class='fas fa-folder-open' style='margin-right:4px;'></span> {$lang['reporting_54']}</div>"), '');
		// Return values
		return array($reportsList, $reportsListCollapsed);
	}

	// Return html to render the left-hand menu panel for Reports
	public static function outputReportPanel()
	{
		global $lang;
		$reportsList = '';
		$reportsMenuList = self::getReportNames(null, !UserRights::isSuperUserNotImpersonator());
		if (!empty($reportsMenuList)) {
			$reportsList .= "<div class='menubox'>";
			// Loop through each report
			$i = 1;
			$folder = null;
			$viewingRecord = ((PAGE == 'DataEntry/index.php' || PAGE == 'DataEntry/record_home.php') && isset($_GET['id']));
			foreach ($reportsMenuList as $attr) {
				$this_report_id = $attr['report_id'];
				// Report link
				$reportLink = APP_PATH_WEBROOT . "DataExport/index.php?pid=".PROJECT_ID."&report_id=$this_report_id";
				$reportLinkRecordLink = "";
				// Get collapsed state of this folder for this specific user
				$attr['collapsed'] = ($attr['folder_id'] != '' && UIState::getUIStateValue(PROJECT_ID, 'rpc', $attr['folder_id']) == '1') ? '1' : '0';				
				// If the record ID field is a Live Filter, then add extra link to auto-load that Live Filter
				// if ($attr['liveFilterRecordId'] != '' && $viewingRecord) {
					// $reportLinkRecord = $reportLink . "&lf" . $attr['liveFilterRecordId'] . "=" . $_GET['id'];
					// $reportLinkRecordLink = " <button class='btn btn-success btn-xs' style='font-size:11px;padding:0 3px;' onclick=\"window.location.href='$reportLinkRecord';\">{$lang['reporting_53']}</button>";
				// }
				// Report Folders
				if ($folder != $attr['folder_id']) {
					$faClass = $attr['collapsed'] ? "fa-plus-square" : "fa-minus-square";
					$reportsList .= "<div onclick='updateReportPanel({$attr['folder_id']},{$attr['collapsed']});' class='hangf'><i class='far $faClass' style='text-indent:0;margin-right:4px;'></i>".RCView::escape($attr['folder'])."</div>";
					$i = 1;
				}
				$num = "<span class='reportnum'>".$i++.")</span>";
				if (!$attr['collapsed']) {
					if ($attr['folder'] != "") {
						$margin = " style='margin-left:20px;'";
					} else {
						$margin = "";
					}
					$this_report_name = $attr['title'];
					$reportsList .= "<div class='hangr'$margin>
										$num
										<a href='$reportLink'>".RCView::escape($this_report_name)."</a>
										$reportLinkRecordLink
									 </div>";
				}
				// Set for next loop
				$folder = $attr['folder_id'];
			}
			$reportsList .= "</div>";
		}
		return $reportsList;
	}


	// Hidden dialog for help with filters and AND/OR logic
	public static function renderFilterHelpDialog()
	{
		global $lang;
		return 	RCView::div(array('class'=>'simpleDialog', 'title'=>$lang['report_builder_119'], 'id'=>'filter_help'),
					$lang['report_builder_120'] . RCView::br() . RCView::br() .
					$lang['report_builder_122'] . RCView::br() . RCView::br() .
					$lang['report_builder_121'] . RCView::br() . RCView::br() .
					$lang['report_builder_123'] . RCView::br() . RCView::br() .
					$lang['report_builder_124']
				);

	}

	// Collapse or uncollapse a Report Folder on the left-hand menu panel of Reports
	public static function collapseReportFolder($folder_id, $collapse=0)
	{
		$folder_id = (int)$folder_id;
		if ($collapse != '1' && $collapse != '0') $collapse = '0';
		// If we're collapsing it, then set value=1, otherwise remove it from UIState
		if ($collapse) {
			UIState::saveUIStateValue(PROJECT_ID, 'rpc', $folder_id, '1');
		} else {
			UIState::removeUIStateValue(PROJECT_ID, 'rpc', $folder_id);
		}
	}
	
	// Obtain array of all Report Folders for a given user
	public static function getReportFolders($project_id)
	{
		$sql = "select folder_id, name from redcap_reports_folders
				where project_id = $project_id order by position";
		$q = db_query($sql);
		$folders = array();
		while ($row = db_fetch_assoc($q)) {
			$folders[$row['folder_id']] = $row['name'];
		}
		return $folders;
	}
	
	// Resort report folders via drag and drop
	public function reportFolderResort($data)
	{
		$ids = explode(",", str_replace('&', ',', str_replace('rf[]=', '', $data)));
		foreach ($ids as $key=>$id) {
			if (!is_numeric($id)) unset($ids[$key]);
		}
		$sql = "
		  SELECT folder_id
		  FROM redcap_reports_folders
		  WHERE project_id = ".PROJECT_ID."
		  ORDER BY FIELD(folder_id, ".prep_implode($ids).")
		";
		$q = db_query($sql);
		if ($q !== false)
		{			
			$sql = "UPDATE redcap_reports_folders
					SET position = NULL
					WHERE project_id = ".PROJECT_ID." and folder_id in (".prep_implode($ids).")";
			db_query($sql);
		
			$position = 1;
			while($row = db_fetch_assoc($q))
			{
				$sql = "
				  UPDATE redcap_reports_folders
				  SET position = $position
				  WHERE folder_id = {$row['folder_id']}
				";
				//print_r($sql);
				db_query($sql);
				$position++;
			}
			// Logging
			Logging::logEvent("", "redcap_reports_folders", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Re-sort report folders");
			exit('1');
		}
		exit('0');
	}
	
	// Output HTML for table of Report Folders
	public function outputReportFoldersTable()
	{
		global $lang;
		
		$folders = self::getReportFolders(PROJECT_ID);

		if (count($folders) > 0)
		{
			$folderTableRows = "";
			foreach ($folders as $folder_id=>$folder_name) {
				$folderTableRows .= RCView::tr(array('id'=>'rf_'.$folder_id),
										RCView::td(array('class'=>'rf_td', 'style'=>'width:330px;'), 
											RCView::span(array('id'=>'rft_'.$folder_id),
												$folder_name
											) .
											RCView::span(array('id'=>'rfi_'.$folder_id, 'class'=>'hidden'),
												RCView::input(array('id'=>'rfiv_'.$folder_id, 'value'=>$folder_name, 'onkeypress'=>"if(event&&event.keyCode==13){editFolderSave($folder_id)}")) .
												RCView::button(array('class'=>'btn btn-xs btn-outline-success', 'onclick'=>"editFolderSave($folder_id);"), $lang['folders_11'])
											)
										) .
										RCView::td(array('class'=>'rf_td text-center', 'style'=>'width:30px;'), 
											RCView::a(array('href'=>'javascript:;', 'onclick'=>"editFolder($folder_id);"), RCView::fa('fas fa-pencil-alt'))
										) .
										RCView::td(array('class'=>'rf_td text-center', 'style'=>'width:30px;'), 
											RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteFolder($folder_id);"), RCView::fa('fas fa-times', 'font-size:15px;'))
										)
									);
			}
			$folderTable = 	RCView::table(array('id'=>'report_folders_list', 'class'=>'form_border', 'style'=>'width:100%;'),
								$folderTableRows
							);
		}
		else
		{
			$folderTable = RCView::p(array('style'=>'color:#777;margin:15px 5px;font-weight:normal;'), $lang['folders_13']);
		}
		
		return $folderTable;
	}
	
	// Obtain array of all reports assigned to a specific Report Folder
	public static function getReportsAssignedToFolder($folder_id)
	{
		$sql = "select r.report_id, r.title 
				from redcap_reports_folders_items i, redcap_reports r
				where i.folder_id = '".db_escape($folder_id)."' and r.report_id = i.report_id
				order by r.report_order";
		$q = db_query($sql);
		$reports = array();
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']] = strip_tags(label_decode($row['title']));
		}
		return $reports;
	}
	
	// Obtain array of all reports assigned to a ANOTHER Report Folder (i.e. a folder other than the one provided)
	public static function getReportsAssignedToOtherFolder($folder_id)
	{
		$sql = "select r.report_id, r.title 
				from redcap_reports_folders_items i, redcap_reports r
				where i.folder_id != '".db_escape($folder_id)."' and r.report_id = i.report_id
				order by r.report_order";
		$q = db_query($sql);
		$reports = array();
		while ($row = db_fetch_assoc($q)) {
			$reports[$row['report_id']] = strip_tags(label_decode($row['title']));
		}
		return $reports;
	}
	
	// Output HTML for assigment table of reports to Report Folders
	public function outputReportFoldersTableAssign($folder_id, $hide_assigned=0)
	{
		global $lang;
		
		$_SESSION['hide_assigned_rf'] = isset($hide_assigned) ? (int)$hide_assigned : 0;
		
		$reportsAssignedThisFolder = self::getReportsAssignedToFolder($folder_id);
		$reportsAssignedOtherFolder = self::getReportsAssignedToOtherFolder($folder_id);
		$reports = self::getReports();
		
		$report_ids = array();
		$folderTable = $folderTableRows = "";
		if (count($reports) > 0)
		{
			// Add row for every report in the project
			foreach ($reports as $attr) 
			{
				// If hiding assigned and this one is assigned, then skip
				if ($hide_assigned && $reportsAssignedOtherFolder[$attr['report_id']]) continue;
				// Add row
				$report_ids[] = $attr['report_id'];
				$checked = isset($reportsAssignedThisFolder[$attr['report_id']]) ? "checked" : "";
				$folderTableRows .= RCView::tr(array('id'=>'report_tr_'.$attr['report_id']),
										RCView::td(array('class'=>'data fldrplist1'), 
											RCView::checkbox(array('id'=>'rid_'.$attr['report_id'], $checked=>$checked, 'onclick'=>"rfAssignSingle($folder_id,{$attr['report_id']},this.checked);"))
										) .
										RCView::td(array('class'=>'data fldrplist2', 'style'=>''), 
											strip_tags(label_decode($attr['title'])) .
											RCView::div(array(
												'id'=>"report_saved_".$attr['report_id'],
												'class'=>'fldrsvsts'
											), $lang['design_243'])
										)
									);
			}
			// Add header
			$ids = implode(',', $report_ids);
			$folderTableRowsHdr = RCView::tr(array(),
									RCView::td(array('class'=>'header text-center', 'style'=>'width:20px;'), 
										RCView::checkbox(array('id'=>'checkAll', 'onclick'=>"checkAllReportFolders($folder_id, '$ids');"))
									) .
									RCView::td(array('class'=>'header'), 
										$lang['reporting_58']
									)
								);
			// Add to table
			$folderTable = 	RCView::table(array('class'=>'form_border', 'style'=>'width:100%;'),
								$folderTableRowsHdr . 
								$folderTableRows
							);
		}
		
		return $folderTable;
	}
	
	public function reportFolderAssign()
	{
		$folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
		if (empty($folder_id)) exit;		
		// Check single
		if (!isset($_POST['checkAll'])) {
			$report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
			if (empty($report_id)) exit;	
			if ($_POST['checked'] == '1') {
				$sql = "replace into redcap_reports_folders_items (folder_id, report_id) values
						('".db_escape($folder_id)."', '".db_escape($report_id)."')";
			} else {
				$sql = "delete from redcap_reports_folders_items
						where folder_id = '".db_escape($folder_id)."' and report_id = '".db_escape($report_id)."'";
			}
			if (db_query($sql)) {
				// Logging
				Logging::logEvent($sql, "redcap_reports_folders_items", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Assign/unassign report(s) to report folder");
				return '1';
			}
			return '0';
		}
		// Check all
		else {
			$ids = explode(',', $_POST['ids']);
			if (count($ids) > 0)
			{
				$checkAll = (isset($_POST['checkAll']) && $_POST['checkAll'] == 'true');
				// Add all to table
				if ($checkAll) {
					foreach ($ids as $report_id) {
						$report_id = (int)$report_id;
						if (!is_numeric($report_id) || empty($report_id)) continue;	
						$sql = "replace into redcap_reports_folders_items (folder_id, report_id) values
								('".db_escape($folder_id)."', '".db_escape($report_id)."')";
						if (!db_query($sql)) exit('0');
					}
				} else {
					// Remove all from table
					$sql = "delete from redcap_reports_folders_items
							where folder_id = '".db_escape($folder_id)."' and report_id in (".prep_implode($ids).")";
					if (!db_query($sql)) exit('0');					
				}
			}
			// Logging
			Logging::logEvent($sql, "redcap_reports_folders_items", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Assign/unassign report(s) to report folder");
			return '1';
		}
	}
	
	// Output HTML for drop-down list options of Report Folders
	public function outputReportFoldersDropdown()
	{
		global $lang;
		
		$folders = self::getReportFolders(PROJECT_ID);
		
		$folderOptions = array(''=>'--- '.$lang['folders_24'].' ---')+$folders; 
		
		return RCView::select(array('id'=>'folder_id', 'onchange'=>"updateReportFolderTableAssign(this.value);", 'class'=>'x-form-text x-form-field', 
									'style'=>'margin-top:8px;max-width:200px;'), $folderOptions, '');
	}
	
	// Output HTML for setting up Report Folders
	public function outputReportFoldersDialog()
	{
		global $lang;

		$popup_content_left_td = 
			RCView::td(array('style'=>'vertical-align:top'),
				RCView::div(array('class'=>'addFieldMatrixRowHdr', 'style'=>'width:400px; float:left;'),
					RCView::table(array('class'=>'form_border', 'style'=>'width:97%;'),
						RCView::tr(array(),
							RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'color:#008000;padding:0;background:#fff;border:0;'),
								RCView::div(array('style'=>'position:relative;top:13px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:8px 8px;'),
									$lang['folders_28']
								)
							)
						  ) .
						  RCView::tr(array(),
							RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'padding:5px;'),
								RCView::div(array('style'=>'color:#444;float:left;font-weight:normal;margin-top:10px;'), $lang['reporting_56']) .
								RCView::div(array('style'=>'float:right;margin-top:7px;'),
									RCView::input(array(
										'placeholder' => $lang['folders_17'],
										'id'          => 'folderName',
										'type'        => 'text',
										'maxlength'   => 64,
										'class'	  => 'x-form-text x-form-field',
										'style'   => 'width:150px;',
										'onkeypress'  => 'return checkReportFolderNameSubmit(event);'
									)) . '&nbsp;' .
									RCView::button(array(
										'id'      => 'addFolder',
										'class'	  => 'btn btn-xs btn-success',
										'style'   => 'font-size:14px;',
										'onclick' => 'newReportFolder();'
									), $lang['folders_18'])
								)
							)
						  )
					) .
					// List of folders as table
					RCView::div(array('id'=>'folders', 'style'=>'width:97%; height:320px; overflow-x:auto;'), 
						self::outputReportFoldersTable()
					)
				)
			);

		$checkbox_array = array('id'=>'hide_assigned_rf', 'onclick'=>'hideAssignedReportFolders();');
		if (isset($_SESSION['hide_assigned_rf']) && $_SESSION['hide_assigned_rf'] == '1') {
			$checkbox_array['checked'] = 'checked';
		}

		$popup_content_right_td = RCView::td(array('style'=>'vertical-align:top'),
			RCView::div(array('class'=>'addFieldMatrixRowHdr', 'style'=>'float:left; margin-left:25px;width:440px;'),
				RCView::table(array('class'=>'form_border', 'style'=>'width:97%;'),
					RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'color:#008000;padding:0;background:#fff;border:0;'),
							RCView::div(array('style'=>'position:relative;top:13px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:8px 8px;'),
								$lang['reporting_55']
							)
						)
					  ) .
					  RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'padding:5px;'),
							// Folder drop-down list
							RCView::table(array(),
								RCView::tr(array(),
									RCView::td(array('style'=>'padding-right:15px;'), 
										RCView::div(array('id'=>'select_folders'), 
											self::outputReportFoldersDropdown()
										)
									) .
									RCView::td(array('class'=>'nowrap', 'style'=>'padding-top:7px'),
										RCView::checkbox($checkbox_array) .
										RCView::span(array('style'=>'font-size:11px; color:#000;font-weight:normal;'), $lang['reporting_57'])
									)
								)
							)
						)
					  )
				) .
				// List of projects
				RCView::div(array('id'=>'report_folders_assign', 'style'=>'width:97%; height:320px; overflow-x:auto;'), '&nbsp;')
			)
		);

		$popup_content = RCView::div(array('style'=>''),
							$lang['reporting_59']
						) .
						RCView::table(array(),
							RCView::tr(array(), $popup_content_left_td . $popup_content_right_td)
						) . 
						'<script type="text/javascript">
						var langProjFolder05 = "'.js_escape2($lang['folders_14']).'";
						var langDelFolder = "'.js_escape2($lang['folders_16']).'";
						var langCancel = "'.js_escape2($lang['global_53']).'";
						var langDelete = "'.js_escape2($lang['design_170']).'";
						</script>';
		
		return $popup_content;
	}
	
	// Create new Report Folder
	public function reportFolderCreate()
	{
		if (!isset($_POST['folder_name']) || trim($_POST['folder_name']) == '') exit('0');
		$sql = "select max(position) from redcap_reports_folders where project_id = ".PROJECT_ID;
		$q = db_query($sql);
		$position = db_result($q, 0);
		if ($position == null) {
			$position = 1;
		} else {
			$position++;
		}
		$sql = "insert into redcap_reports_folders (project_id, name, position) values
				(".PROJECT_ID.", '".db_escape($_POST['folder_name'])."', $position)";
		if (db_query($sql)) {
			$folder_id = db_insert_id();
			// Logging
			Logging::logEvent($sql, "redcap_reports_folders", "MANAGE", $folder_id, "folder_id = ".$folder_id, "Create report folder");
			return '1';
		}
		return '0';
	}
	
	// Edit Report Folder
	public function reportFolderEdit()
	{
		if (!is_numeric($_POST['folder_id']) || !isset($_POST['folder_name']) || trim($_POST['folder_name']) == '') exit('0');
		$sql = "update redcap_reports_folders
				set name = '".db_escape($_POST['folder_name'])."'
				where project_id = ".PROJECT_ID." and folder_id = '".db_escape($_POST['folder_id'])."'";
		if (db_query($sql)) {
			// Logging
			Logging::logEvent($sql, "redcap_reports_folders", "MANAGE", $_POST['folder_id'], "folder_id = ".$_POST['folder_id'], "Edit report folder name");
			return '1';
		}
		return '0';
	}	
	
	// Delete Report Folder
	public function reportFolderDelete()
	{
		if (!isset($_POST['folder_id']) || !is_numeric($_POST['folder_id'])) exit('0');
		$sql = "delete from redcap_reports_folders
				where project_id = ".PROJECT_ID." and folder_id = '".db_escape($_POST['folder_id'])."'";
		if (db_query($sql)) {
			// Logging
			Logging::logEvent($sql, "redcap_reports_folders", "MANAGE", $_POST['folder_id'], "folder_id = ".$_POST['folder_id'], "Delete report folder");
			return '1';
		}
		return '0';
	}
	
	// Search for a report
	public function reportSearch($term='')
	{
		// Santize search term passed in query string
		$search_term = trim(html_entity_decode(urldecode($term), ENT_QUOTES));

		// Return nothing if search term is blank
		if ($search_term == '') exit('[]');

		// If search term contains a space, then assum multiple search terms that will be searched for independently
		if (strpos($search_term, " ") !== false) {
			$search_terms = explode(" ", $search_term);
		} else {
			$search_terms = array($search_term);
		}
		$search_terms = array_unique($search_terms);

		// Set the subquery for all search terms used
		$subsqla = array();
		foreach ($search_terms as $key=>$this_term) {
			// Trim and set to lower case
			$search_terms[$key] = $this_term = trim(strtolower($this_term));
			if ($this_term == '') {
				unset($search_terms[$key]);
			} else {
				$subsqla[] = "title like '%".db_escape($this_term)."%'";
			}
		}
		$subsql = implode(" or ", $subsqla);
		
		// Obtain all report_id's that the user can view
		$reports = self::getReportNames(null, true);
		$report_ids = array();
		foreach ($reports as $attr) {
			$report_ids[] = $attr['report_id'];
		}
		if (empty($report_ids)) exit('[]');
		
		// Calculate score on how well the search terms matched
		$userMatchScore = $results = array();
		$key = 0;
		// Query table
		$sql = "select report_id, title
				from redcap_reports
				where report_id in (".prep_implode($report_ids).") and ($subsql)
				order by trim(title)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{		
			// Set title
			$label = trim(strip_tags(label_decode($row['title'])));
			// Calculate search match score.
			$userMatchScore[$key] = 0;
			// If title matches EXACTLY, do a +100 on score.
			if (strtolower($search_term) == strtolower($label)) $userMatchScore[$key] = $userMatchScore[$key]+100;
			// Loop through each search term for this person
			foreach ($search_terms as $this_term) {
				// Set length of this search string
				$this_term_len = strlen($this_term);
				// For partial matches on username, first name, or last name (or email, if applicable), give +1 point for each letter
				if (stripos($label, $this_term) !== false) $userMatchScore[$key] = $userMatchScore[$key]+$this_term_len;
				// Wrap any occurrence of search term in label with bold tags
				$label = str_ireplace($this_term, RCView::b($this_term), $label);
			}
			// Add to arrays
			$results[$key] = array('value'=>$row['report_id'], 'label'=>$label);
			// Increment key
			$key++;
		}

		// Sort results by score
		$count_results = count($results);
		if ($count_results > 0) {
			// Sort
			array_multisort($userMatchScore, SORT_NUMERIC, SORT_DESC, $results);
			// Limit only to X users to return
			$limit_results = 20;
			if ($count_results > $limit_results) {
				$results = array_slice($results, 0, $limit_results);
			}
		}

		// Return JSON
		return json_encode($results);
	}

	// Obtain descriptive stats for this form as an array
	public static function getDescriptiveStats($fields, $totalrecs, $form="", $includeRecordsEvents=array(), $hasFilterWithNoRecords=false)
	{
		global $Proj, $user_rights, $table_pk, $longitudinal, $missingDataCodes;
		// Set array to discern what are categorical fields
		$mc_field_types = array("radio", "select", "dropdown", "yesno", "truefalse", "checkbox", "sql");

		// Determine if this is being displayed for a survey
		$isSurveyPage = (isset($_GET['s']) && defined("NOAUTH") && PAGE == 'surveys/index.php');

		// If $includeRecordsEvents is passed and not empty, then it will be the record/event allowlist
		$checkIncludeRecordsEvents = (!empty($includeRecordsEvents));

		// Get any repeating forms/events
		$RepeatingFormsEvents = $Proj->getRepeatingFormsEvents();

		// Surveys only: Determine if check diversity feature is enabled
		if ($isSurveyPage)
		{
			// Get the survey_id
			$survey_id = $Proj->forms[$form]['survey_id'];
			// Check if feature is enabled
			$check_diversity_view_results = $Proj->surveys[$survey_id]['check_diversity_view_results'];
			// Get the respondents data to determine if some STATS TABLES should be hidden due to lack of diversity
			if ($check_diversity_view_results && isset($_POST['__response_id__']))
			{
				// Get this response's record and event_id (event_id will be the first event in the arm)
				$sql = "select r.record, e.event_id, r.instance from redcap_surveys_response r, redcap_surveys_participants p, redcap_events_metadata e
						where p.participant_id = r.participant_id and r.response_id = {$_POST['__response_id__']} and r.completion_time is not null
						and p.event_id = e.event_id order by e.day_offset, e.descrip limit 1";
				$q = db_query($sql);
				if (db_num_rows($q) > 0)
				{
					// Get record and event_id
					$record = db_result($q, 0, 'record');
					$event_id = db_result($q, 0, 'event_id');
					$instance = db_result($q, 0, 'instance');
					$instance_sql = ($instance == '1') ? "and instance is null" : "and instance = '$instance'";
					// Now get the response data
					$sql = "select field_name, value from redcap_data where project_id = " . PROJECT_ID . "
							and record = '" . db_escape($record) . "' and event_id = $event_id and value != '' $instance_sql";
					$q = db_query($sql);
					$respondent_data = array();
					while ($row = db_fetch_assoc($q))
					{
						// Put data in array
						if ($Proj->metadata[$row['field_name']]['element_type'] == 'checkbox') {
							$respondent_data[$row['field_name']][] = $row['value'];
						} else {
							$respondent_data[$row['field_name']] = $row['value'];
						}
					}
				}
			}
		}

		// Get data types of all field validations
		$validationDataTypes = array();
		foreach (getValTypes() as $valType=>$valAttr)
		{
			$validationDataTypes[$valType] = $valAttr['data_type'];
		}

		// Loop through all fields on this form
		$fieldStats = array();
		foreach ($fields as $key=>$field_name)
		{
			// Ignore record ID field since it doesn't make sense to include it on this page
			if ($field_name == $table_pk) {
				unset($fields[$key]);
				continue;
			}
			// Get field attributes
			$field_attr = $Proj->metadata[$field_name];
			// Ignore descriptive fields
			if ($field_attr['element_type'] == 'descriptive') continue;
			// Add field to array
			$fieldStats[$field_name] = array('count'=>0, 'getstats'=>0,
				'missing'=>($hasFilterWithNoRecords ? 0 : $totalrecs[$Proj->metadata[$field_name]['form_name']]));
			// Only return all data for numerical-type fields
			if ($field_attr['element_validation_type'] == 'float' || $field_attr['element_validation_type'] == 'int'
				|| $field_attr['element_type'] == 'calc' || $field_attr['element_type'] == 'slider'
				|| (isset($validationDataTypes[$field_attr['element_validation_type']]))
				&& ($validationDataTypes[$field_attr['element_validation_type']] == 'number_comma_decimal' || $validationDataTypes[$field_attr['element_validation_type']] == 'number'))
			{
				$fieldStats[$field_name]['getstats'] = 1;
			}
		}

		## Get all form data
		$data = array();
		$includeFormsCount = array();
		$includeFormsCountPre = array();
		// If we're using a DAG filter but those DAGs in the filter have no records, then skip this
		if (!$hasFilterWithNoRecords) {
			// Limit records pulled only to those in user's Data Access Group
			$group_sql = "";
			if ($user_rights['group_id'] != "") {
				$group_sql  = "and record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])).")";
			}
			// Query to pull all existing data for this form and place into $data array
			$sql = "select distinct record, event_id, field_name, value, instance from redcap_data where project_id = ".PROJECT_ID."
					and record != '' and field_name in ('" . implode("', '", array_keys($fieldStats)) . "') $group_sql 
					and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Ignore blank values
				if ($row['value'] == '') continue;
				if (isset($missingDataCodes[$row['value']])) continue;
				// If we have a record/event allowlist, then check the record/event
				if ($checkIncludeRecordsEvents) {
					if ($row['instance'] == '') $row['instance'] = '1';
					// If a repeating form or event
					$thisForm = $Proj->metadata[$row['field_name']]['form_name'];
					$isRepeatingEvent = $Proj->isRepeatingEvent($row['event_id']);
					$isRepeatingForm = (!$isRepeatingEvent && $Proj->isRepeatingForm($row['event_id'], $thisForm));
					if ($isRepeatingEvent || $isRepeatingForm) {
						if ($isRepeatingEvent) {
							// Repeating event (no repeating instrument = blank)
							$repeat_instrument = "";
						} else {
							// Repeating form
							$repeat_instrument = $Proj->metadata[$row['field_name']]['form_name'];
						}
						if (!isset($includeRecordsEvents[$row['record']][$row['event_id']][$row['instance']."-".$repeat_instrument])) {
							continue;
						}
					}
					// Non-repeating
                    elseif (!isset($includeRecordsEvents[$row['record']][$row['event_id']])) {
						continue;
					}
				}
				// If longitudinal, then make sure field belongs to a form that is designated for an event
				if ($longitudinal && !in_array($Proj->metadata[$row['field_name']]['form_name'], $Proj->eventsForms[$row['event_id']])) continue;
				// Put data in array
				$field_type = $Proj->metadata[$row['field_name']]['element_type'];
				if ($field_type == 'checkbox') {
					$data[$row['field_name']][$row['instance'].'|'.$row['event_id'].'|'.$row['record']][] = $row['value'];
				} else {
					if (!in_array($field_type, $mc_field_types)) {
						// Non-multiple choice: Replace data with "x" (to save memory instead of carrying all that data around in an array)
						if (!is_numeric($row['value']) && !is_numeric_comma($row['value'])) $row['value'] = "x";
					}
					$data[$row['field_name']][$row['instance'].'|'.$row['event_id'].'|'.$row['record']] = $row['value'];
				}
				$includeFormsCountPre[$Proj->metadata[$row['field_name']]['form_name']][$row['instance'].'|'.$row['event_id'].'|'.$row['record']] = true;
			}
			if(isset($res))
			{
				db_free_result($res);
			}
		}

		foreach ($includeFormsCountPre as $this_form=>$records)
		{
			$includeFormsCount[$this_form] = count($records);
		}
		unset($includeFormsCountPre);

		// If we have a record/event allowlist, then count number of record/event pairs in the array (to use to calculate Missing)
		if ($checkIncludeRecordsEvents) {
			// Loop through all fields with no data and make sure the "missing" count is set correctly.
			// Fields that DO have data will have their "missing" count set in the next block of foreach($data...).
			$includeRecordsEventsNumBaseCount = 0;
			$fields_no_data = array_diff(array_keys($fieldStats), array_keys($data));
			if (!empty($fields_no_data)) {
				// Get record/event count to use for any non-repeating forms
				$sql = "select distinct record, event_id from redcap_data where project_id = ".PROJECT_ID." 
						and field_name = '$table_pk' and record in (".prep_implode(array_keys($includeRecordsEvents)).")
						and instance is null and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q)) {
					if (!isset($includeRecordsEvents[$row['record']][$row['event_id']][1])) {
						continue;
					}
					$includeRecordsEventsNumBaseCount++;
				}
			}
			foreach ($fields_no_data as $field_name) {
				$fieldStats[$field_name]['missing'] = $includeRecordsEventsNumBaseCount;
			}
			// Loop through $includeRecordsEvents to build total possible counts of possible data points (then we'll subtract the actual values to find the missing count)
			$totalrecsIncludeForms = array();
			foreach ($includeRecordsEvents as $this_record=>$these_events) {
				foreach ($these_events as $this_event_id=>$these_forms_instances) {
					foreach (array_keys($these_forms_instances) as $this_instance_form) {
						if ($this_instance_form == "-") {
							$isRepeatingForm = false;
						} else {
							list ($this_instance, $this_repeat_instrument) = explode("-", $this_instance_form, 2);
							$isRepeatingForm = ($this_repeat_instrument != "");
						}
						if ($isRepeatingForm) {
							// Single repeating instrument
							if (isset($includeFormsCount[$this_repeat_instrument])) {
								$totalrecsIncludeForms[$this_repeat_instrument]++;
							} else {
								$totalrecsIncludeForms[$this_repeat_instrument] = 1;
							}
						} else {
							// Loop through all forms on this repeating event or non-repeating event/instrument
							foreach ($Proj->eventsForms[$this_event_id] as $this_event_instrument) {
								if (isset($includeFormsCount[$this_event_instrument])) {
									$totalrecsIncludeForms[$this_event_instrument]++;
								} else {
									$totalrecsIncludeForms[$this_event_instrument] = 1;
								}
							}
						}
					}
				}
			}
		}

		// Now that we have all data, loop through it and determine missing value count and stats
		foreach ($data as $field_name=>$records)
		{
			// Ignore record ID field
			if ($field_name == $table_pk) continue;
			// Get field type
			$field_type = $Proj->metadata[$field_name]['element_type'];
			$isNumberCommaDecimal = ($validationDataTypes[$Proj->metadata[$field_name]['element_validation_type']] == 'number_comma_decimal');
			// Is the field multiple choice?
			$isMCfield = in_array($field_type, $mc_field_types);
			// Set choices array for mc fields
			$element_enum_norm = ($Proj->metadata[$field_name]['element_type'] == 'sql') ? getSqlFieldEnum($Proj->metadata[$field_name]['element_enum']) :$Proj->metadata[$field_name]['element_enum'];
			$choices = ($isMCfield) ? parseEnum($element_enum_norm) : array();
			// Set total count and missing value count (do these before checking that all are numerical)
			$num_records = count($records);
			//$fieldStats[$field_name]['missing'] = ($checkIncludeRecordsEvents ? ($totalrecsIncludeForms[$Proj->metadata[$field_name]['form_name']] - $includeFormsCount[$Proj->metadata[$field_name]['form_name']]) : ($totalrecs[$Proj->metadata[$field_name]['form_name']] - $num_records));
			if ($checkIncludeRecordsEvents) {
				$fieldStats[$field_name]['missing'] = $totalrecsIncludeForms[$Proj->metadata[$field_name]['form_name']] - $num_records;
			} else {
				$fieldStats[$field_name]['missing'] = $totalrecs[$Proj->metadata[$field_name]['form_name']] - $num_records;
			}
			if ($fieldStats[$field_name]['missing'] < 0) $fieldStats[$field_name]['missing'] = 0;
			$fieldStats[$field_name]['count'] = $num_records;
			// Remove any non-valide choices/values (exclude free-form text)
			if ($fieldStats[$field_name]['getstats'] || $isMCfield)
			{
				// Loop through all records for this field
				foreach ($records as $key=>$val)
				{
					if (is_array($val)) {
						// Checkbox
						foreach ($val as $key2=>$val2) {
							if ($isMCfield && !isset($choices[$val2])) {
								unset($records[$key][$key2]);
							}
						}
					} else {
						// Non-checkbox
						if ($isMCfield && !isset($choices[$val])) {
							unset($records[$key]);
						}
						// If a number field with commas decimal
                        elseif ($isNumberCommaDecimal) {
							$records[$key] = str_replace(",", ".", $val);
						}
					}
				}
			}
			// Now reindex the array
			sort($records);
			// If free-form text, the skip the rest of this loop
			if (!$fieldStats[$field_name]['getstats'] && !$isMCfield) {
				continue;
			}
			// Unique
			if ($Proj->isCheckbox($field_name)) {
				// For checkboxes, all values are sub-arrays, so add them to $unique_choices first and then count
				$unique_choices = array();
				// Get list of valid choice options
				$field_valid_choices = parseEnum($Proj->metadata[$field_name]['element_enum']);
				foreach ($records as $these_choices) {
					foreach ($these_choices as $this_choice) {
						// make sure this is a valid choice still
						if (isset($field_valid_choices[$this_choice])) {
							$unique_choices[$this_choice] = true;
						}
					}
				}
				$fieldStats[$field_name]['unique'] = count($unique_choices);
				unset($unique_choices);
			} else {
				// Non-checkboxes
				$fieldStats[$field_name]['unique'] = count(array_unique($records));
			}
			// Numerical fields
			if ($fieldStats[$field_name]['getstats'])
			{
				// Sum
				$fieldStats[$field_name]['sum'] = User::number_format_user(round(array_sum($records), 2), 2);
				// Min
				$fieldStats[$field_name]['min'] = User::number_format_user(round(min($records), 2), 2);
				// Max
				$fieldStats[$field_name]['max'] = User::number_format_user(round(max($records), 2), 2);
				// Mean
				$fieldStats[$field_name]['mean'] = User::number_format_user(round(array_sum($records) / count($records), 2), 2);
				// StDev
				$fieldStats[$field_name]['stdev'] = User::number_format_user(round(stdev($records), 2), 2);
				// Q1 (.25 percentile)
				$fieldStats[$field_name]['perc25'] = User::number_format_user(round(percentile($records, 25), 2), 2);
				// Median (.50 percentile)
				$fieldStats[$field_name]['median'] = User::number_format_user(round(median($records), 2), 2);
				// Q3 (.75 percentile)
				$fieldStats[$field_name]['perc75'] = User::number_format_user(round(percentile($records, 75), 2), 2);
				// Lowest values
				for ($i = 0; $i < 5; $i++) {
					if (isset($records[$i])) {
						$fieldStats[$field_name]['low'][$i] = ($isNumberCommaDecimal ? str_replace(".", ",", $records[$i]) : $records[$i]);
					}
				}
				// Lowest values
				for ($i = $fieldStats[$field_name]['count']-5; $i < $fieldStats[$field_name]['count']; $i++) {
					if (isset($records[$i])) {
						$fieldStats[$field_name]['high'][$i] = ($isNumberCommaDecimal ? str_replace(".", ",", $records[$i]) : $records[$i]);
					}
				}
				// .05 percentile
				$fieldStats[$field_name]['perc05'] = User::number_format_user(round(percentile($records, 5), 2), 2);
				// .10 percentile
				$fieldStats[$field_name]['perc10'] = User::number_format_user(round(percentile($records, 10), 2), 2);
				// .90 percentile
				$fieldStats[$field_name]['perc90'] = User::number_format_user(round(percentile($records, 90), 2), 2);
				// .95 percentile
				$fieldStats[$field_name]['perc95'] = User::number_format_user(round(percentile($records, 95), 2), 2);
			}
			// Categorical fields: Get counts/frequency
            elseif ($isMCfield)
			{
				// Initialize the enum data array with 0s
				$enum_counts = array();
				foreach (array_keys($choices) as $this_code)
				{
					$enum_counts[$this_code] = 0;
				}
				// Now loop through all data and count each category
				foreach ($records as $this_value)
				{
					// Make sure it's a real category before incrementing the count
					if (is_array($this_value)) {
						// Checkbox
						foreach ($this_value as $this_value2) {
							if (isset($enum_counts[$this_value2])) {
								$enum_counts[$this_value2]++;
							}
						}
					} else {
						// Non-checkbox
						if (isset($enum_counts[$this_value])) {
							$enum_counts[$this_value]++;
						}
					}
				}
				// Display each categories count and frequency (%)
				$enum_freq = array();
				$enum_total_count = $fieldStats[$field_name]['count'];
				foreach ($enum_counts as $this_code=>$this_count)
				{
					$enum_freq[] = "<span style='color:#C00000;'>{$choices[$this_code]}</span>
									($this_count, " . User::number_format_user(round($this_count/$enum_total_count*100, 1), 1) . "%)";
				}
				// Set the string for the count/frequency
				$fieldStats[$field_name]['freq'] = $enum_freq;

				// SURVEYS ONLY: If this is a survey field with the "check diversity" feature enabled, then check for diversity
				if (!isset($fieldStats[$field_name]['hide']) && isset($check_diversity_view_results) && $check_diversity_view_results)
				{
					// Make sure that there is diversity in the choices selected (i.e. that a single choice doesn't have ALL the responses)
					foreach ($enum_counts as $this_choice=>$this_count)
					{
						// If a single choice has all responses in it, then we are lacking diversity, so don't show chart
						if ($this_count == $enum_total_count)
						{
							$fieldStats[$field_name]['hide'] = true;
						}
						// Now, if an individual response exists for this field, then make sure that a single choice doesn't
						// have ALL responses with the EXCEPTION of the participant's response.
						if (isset($respondent_data[$field_name]) && $this_count == ($enum_total_count - 1))
						{
							if (($field_type == 'checkbox' && !in_array($this_choice, $respondent_data[$field_name]))
								||  ($field_type != 'checkbox' && $respondent_data[$field_name] != $this_choice))
							{
								$fieldStats[$field_name]['hide'] = true;
							}
						}
					}
				}
			}

			// Remove from array to clear up memory
			unset($data[$field_name]);
		}

		// Return the array
		return $fieldStats;
	}

	// Individual Plots (Google Chart Tools)
	public static function chartData($fields, $group_id="", $includeRecordsEvents=array(), $hasFilterWithNoRecords=false)
	{
		global $Proj, $missingDataCodes;

		// Get any repeating forms/events
		$RepeatingFormsEvents = $Proj->getRepeatingFormsEvents();

		// Determine if this is being displayed for a survey
		$isSurveyPage = (isset($_GET['s']) && defined("NOAUTH") && isset($_POST['isSurveyPage']) && $_POST['isSurveyPage']);

		// Get first field in the list that was sent (this is the current field we're displaying)
		if(strstr($fields, ','))
		{
			list ($field, $fields) = explode(",", $fields, 2);
		}
		// no comma, must be the last field
		else
		{
			$field = $fields;
			$fields = '';
		}

		// Obtain field attributes
		if (!isset($Proj->metadata[$field])) return '[]';
		$field_type = $Proj->metadata[$field]['element_type'];

		// First get the form that has this field
		$form = $Proj->metadata[$field]['form_name'];

		// If $includeRecordsEvents is passed and not empty, then it will be the record/event allowlist
		$checkIncludeRecordsEvents = (!empty($includeRecordsEvents));

		// SURVEYS ONLY: See if the "lacking diversity" feature is enabled, and set flag to prevent bar chart from displaying data
		if ($isSurveyPage)
		{
			// Get the survey_id
			$survey_id = $Proj->forms[$form]['survey_id'];
			// Check if feature is enabled
			$check_diversity_view_results = $Proj->surveys[$survey_id]['check_diversity_view_results'];
		}

		// Determine plot type
		$plotType = ($field_type != "text" && $field_type != "calc" && $field_type != "slider") ? "BarChart" : "BoxPlot";

		// Load defaults for the bar charts
		if ($plotType == "BarChart")
		{
			// Initialize the enum data array with 0s
			$choices = $Proj->metadata[$field]['element_type'] == 'sql' ? parseEnum(getSqlFieldEnum($Proj->metadata[$field]['element_enum'])) : parseEnum($Proj->metadata[$field]['element_enum']);
			$data = array();
			foreach (array_keys($choices) as $this_code)
			{
				$data[$this_code] = 0;
			}
		}

		// Limit records pulled only to those in user's Data Access Group
		if ($group_id == "") {
			$group_sql = "";
		} else {
			$group_sql = "and record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $group_id)) . ")";
		}

		// Query to pull all existing data (pull differently if a "checkbox" field)
		$sql = "select distinct record, event_id, value, instance from redcap_data where project_id = " . PROJECT_ID . "
                and field_name = '$field' $group_sql and event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
		// If there is a filter being used in which no records are being returned, then force the query to return 0 rows.
		if ($hasFilterWithNoRecords) $sql .= " and 1 = 2";
		// Execute the query
		$res = db_query($sql);
		if (!$res) return '[]';

		## If need to return a single record's data, then retrieve it to send back in JSON data
		// If this is a survey participant viewing their data
		$this_record_data = array();
		$raw_data_single = '';
		if (isset($_GET['s']) && isset($_GET['__results']) && isset($_POST['results_code_hash']))
		{
			// Check results code hash
			if (DataExport::checkResultsCodeHash($_GET['__results'], $_POST['results_code_hash']))
			{
				// Obtain name of record and event_id
				$sql = "select r.record, e.event_id
                        from redcap_surveys_participants p, redcap_surveys_response r, redcap_events_metadata e
                        where r.participant_id = p.participant_id and p.hash = '" . db_escape($_GET['s']) . "'
                        and r.results_code = '" . db_escape($_GET['__results']) . "'
                        and e.event_id = p.event_id order by e.day_offset, e.descrip limit 1";
				$q = db_query($sql);
				if (db_num_rows($q) > 0)
				{
					$record   = db_result($q, 0, 'record');
					$event_id = db_result($q, 0, 'event_id');
				}
			}
		}
		// Get the record and event_id from Post
        elseif (isset($_GET['record']) && isset($_GET['event_id']) && is_numeric($_GET['event_id']))
		{
			$record = $_GET['record'];
			$event_id = $_GET['event_id'];
		}
		// If record/event_id have been set, get the record's data
		if (isset($record) && isset($event_id))
		{
			// Obtain data for this field for this record-event
			$sql = "select value from redcap_data where project_id = " . PROJECT_ID . " and
                    record = '" . db_escape($record) . "' and event_id = $event_id
                    and field_name = '$field' and value != '' order by instance limit 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				if ($plotType == "BarChart") {
					$this_label = $choices[$row['value']];
					$this_record_data[$this_label] = true;
					$respondent_choice = $row['value'];
				} elseif ($plotType == "BoxPlot" && is_numeric($row['value'])) {
					$raw_data_single = $row['value'];
				}
			}
		}

		// Default
		$show_chart = true;

		// If this is a text field with non-numerical validation, then definitely don't show a chart
		$hasNumberValidation = ($Proj->metadata[$field]['element_type'] == 'slider' || $Proj->metadata[$field]['element_type'] == 'calc');
		if ($Proj->metadata[$field]['element_type'] == 'text' && $Proj->metadata[$field]['element_validation_type'] != '') {
			// Create array of validation types with "number" data type
			$valtypes = getValTypes();
			$numbervaltypes = array('int', 'float');
			foreach ($valtypes as $valtype=>$attr) {
				if ($attr['data_type'] == 'number' || $attr['data_type'] == 'number_comma_decimal' || $attr['data_type'] == 'integer') {
					$numbervaltypes[] = $valtype;
				}
			}
			// Has non-numerical validation?
			$hasNumberValidation = in_array($Proj->metadata[$field]['element_validation_type'], $numbervaltypes);
			if (!$hasNumberValidation) {
				$show_chart = false;
			}
		}

		// Create the raw data array in JSON format
		$raw_data = array();

		// Bar Chart
		if ($plotType == "BarChart")
		{
			// Loop through all stored data
			while ($ret = db_fetch_assoc($res))
			{
				if ($ret['value'] == '') continue;
				if (isset($missingDataCodes[$ret['value']])) continue;
				// If we have a record/event allowlist, then check the record/event
				if ($checkIncludeRecordsEvents) {
					if ($ret['instance'] == '') $ret['instance'] = '1';
					// If a repeating form or event
					$isRepeatingEvent = $Proj->isRepeatingEvent($ret['event_id']);
					$isRepeatingForm = (!$isRepeatingEvent && $Proj->isRepeatingForm($ret['event_id'], $Proj->metadata[$field]['form_name']));
					$repeat_instrument = "";
					if ($isRepeatingForm || $isRepeatingEvent) {
						if ($isRepeatingForm) $repeat_instrument = $Proj->metadata[$field]['form_name'];
						if (!isset($includeRecordsEvents[$ret['record']][$ret['event_id']][$ret['instance']."-".$repeat_instrument])) {
							continue;
						}
					}
					// Non-repeating
                    elseif (!isset($includeRecordsEvents[$ret['record']][$ret['event_id']])) {
						continue;
					}
				}
				if (isset($choices[$ret['value']]))
				{
					$data[$ret['value']]++;
				}
			}
			db_free_result($res);
			// Get total count of all valuess
			$total_counts = array_sum($data);
			// SURVEYS ONLY: If this is a survey field with the "check diversity" feature enabled, then check for diversity
			if (isset($check_diversity_view_results) && $check_diversity_view_results)
			{
				// Make sure that there is diversity in the choices selected (i.e. that a single choice doesn't have ALL the responses)
				foreach ($data as $this_choice=>$this_count)
				{
					// If a single choice has all responses in it, then we are lacking diversity, so don't show chart
					if ($show_chart && $this_count == $total_counts)
					{
						$show_chart = false;
					}
					// Now, if an individual response is being overlaid onto the plots, then make sure that a single choice doesn't
					// have ALL responses with the EXCEPTION of the participant's response.
					if ($show_chart && isset($respondent_choice) && $respondent_choice != $this_choice && $this_count == ($total_counts - 1))
					{
						$show_chart = false;
					}
				}
				// If we should not show the chart's data, then set all data to 0's
				if (!$show_chart)
				{
					foreach ($data as $this_choice=>$this_count)
					{
						$data[$this_choice] = 0;
					}
				}
			}
			// If there is no data, then don't show chart
			if ($show_chart && $total_counts == 0)
			{
				$show_chart = false;
			}
			// Minimum value is always 0 for bar charts
			$val_min = 0;
			// If showing chart, then format the data to send
			if ($show_chart)
			{
				// Loop and add data to array
				foreach (array_combine($choices, $data) as $this_label=>$this_value)
				{
					// Get maximum value
					if (!isset($val_max) || (isset($val_max) && $this_value > $val_max))
					{
						$val_max = $this_value;
					}
					// If we're adding a single respondent's data, then add as third element and subtract one from aggregate (to prevent counting it twice)
					if ($show_chart && isset($this_record_data[$this_label])) {
						$respondent_value = 1;
						// For Pie Charts, do not subtract the respondent's data from the total because Pie Charts can't stack like Bar Charts can
						if ($_POST['charttype'] != 'PieChart') {
							$this_value--;
						}
					} else {
						$respondent_value = 0;
					}
					// Clean the label and escape any double quotes
					$this_label = str_replace(array("\r\n", "\n", "\t"), array(" ", " ", " "), strip_tags(label_decode($this_label)));
					// If the respondent selected this choice (or is the choice of the selected record), then put asterisks around it, etc.
					if ($respondent_value) {
						$this_label  = "*" . $this_label . "* ";
						$this_label .= ($isSurveyPage ? $lang['graphical_view_75'] : $lang['graphical_view_76'] . " $record" . $lang['data_entry_163']);
					}
					// Add to array
					$raw_data[] = "[".json_encode($this_label).",$this_value,$respondent_value]";
				}
			}
		}
		// Box plot
		else
		{
			// Add values to array to calculate median
			$median_array = array();
			// Loop through all stored data
			while ($ret = db_fetch_assoc($res))
			{
				if (isset($missingDataCodes[$ret['value']])) continue;
				// If we have a record/event allowlist, then check the record/event
				if ($checkIncludeRecordsEvents) {
					if ($ret['instance'] == '') $ret['instance'] = '1';
					// If a repeating form or event
					$isRepeatingEvent = $Proj->isRepeatingEvent($ret['event_id']);
					$isRepeatingForm = (!$isRepeatingEvent && $Proj->isRepeatingForm($ret['event_id'], $Proj->metadata[$field]['form_name']));
					$repeat_instrument = "";
					if ($isRepeatingForm || $isRepeatingEvent) {
						if ($isRepeatingForm) $repeat_instrument = $Proj->metadata[$field]['form_name'];
						if (!isset($includeRecordsEvents[$ret['record']][$ret['event_id']][$ret['instance']."-".$repeat_instrument])) {
							continue;
						}
					}
					// Non-repeating
                    elseif (!isset($includeRecordsEvents[$ret['record']][$ret['event_id']])) {
						continue;
					}
				}
				$is_numeric = ($hasNumberValidation && is_numeric($ret['value']));
				if (!$is_numeric && $hasNumberValidation && is_numeric_comma($ret['value'])) $is_numeric_comma = true;
				if ($is_numeric || $is_numeric_comma)
				{
					if ($is_numeric_comma) {
						$ret['value'] = str_replace(",", ".", $ret['value']);
					}
					// Multiply by 1 just in case it somehow has a leading zero
					$this_value = $ret['value']*1;
					// Get minimum value
					if (!isset($val_min) || (isset($val_min) && $this_value < $val_min))
					{
						$val_min = $this_value;
					}
					// Get maximum value
					if (!isset($val_max) || (isset($val_max) && $this_value > $val_max))
					{
						$val_max = $this_value;
					}
					// Add to median array
					$median_array[] = $this_value;
					// Add to raw data array - set first value of pair as random number between 0 and 1
					// (Do not return a value if we're going to display the data point as separate - prevents duplication)
					if (!($raw_data_single == $this_value && isset($record) && isset($event_id) && $record == $ret['record'] && $event_id == $ret['event_id']))
					{
						if ($ret['instance'] == '') $ret['instance'] = 1;
						$raw_data[] = "[$this_value,".(rand(10, 90)/100).",\"".removeDDEending($ret['record'])."\",{$ret['event_id']},{$ret['instance']}]";
					}
				}
			}
			// Calculate median
			if (!empty($median_array))
			{
				$val_median = median($median_array);
			}
			// For sliders, manually set min/max as 0/100
			if ($field_type == 'slider')
			{
				$val_min = 0;
				$val_max = 100;
			}
		}

		// Set min/max if not already defined
		if (!isset($val_min)) 	 $val_min = 0;
		if (!isset($val_max)) 	 $val_max = 0;
		if (!isset($val_median)) $val_median = '""';

		// Send back JSON
		return  '{"field":"' . $field . '","form":"' . $form . '","plottype":"' . $plotType . '","min":' . $val_min . ',"max":' . $val_max . ',"median":' . $val_median . ','
			. '"nextfields":"' . $fields . '","data":[' . implode(',', $raw_data) . '],"respondentData":"' . $raw_data_single . '",'
			. '"showChart":' . ($show_chart ? 1 : 0) . '}';

	}

	// Calculate Total Records in Project (numbers may differ from form to form for longitudinal projects)
	public static function getRecordCountByForm()
	{
		global $Proj, $table_pk, $user_rights, $longitudinal;
		// Gather form names
		$forms_count = array();
		foreach (array_keys($Proj->forms) as $this_form) {
			$forms_count[$this_form] = 0;
			$forms_count_field[] = $this_form."_complete";
		}
		// Get form status values for ALL records/events
		$record_data = Records::getData('json', array(), array_merge(array($table_pk), $forms_count_field), array(), array($user_rights['group_id']));
		$record_data = json_decode($record_data, true);
		// Loop through data to tally values for form status fields
		$this_event_id = $Proj->firstEventId;
		foreach ($record_data as $fields) {
			if ($longitudinal && isset($fields['redcap_event_name'])) {
				$this_event_id = $Proj->getEventIdUsingUniqueEventName($fields['redcap_event_name']);
			}
			foreach ($forms_count_field as $this_field) {
				if (isset($fields[$this_field]) && $fields[$this_field] != '') {
					$this_form = substr($this_field, 0, -9);
					// If form is not designated for this event, then skip
					if ($longitudinal && !in_array($this_form, $Proj->eventsForms[$this_event_id])) {
						continue;
					}
					// If this is a repeating instance, make sure this form exists on a repeating form/event
					if (isset($fields['redcap_repeat_instance']) && $fields['redcap_repeat_instance'] != '' && !$Proj->isRepeatingFormOrEvent($this_event_id, $this_form)) {
						continue;
					}
					// Add to array
					$forms_count[$this_form]++;
				}
			}
		}
		// Return array of forms with records count for each
		return $forms_count;
	}

	// Obtain the fields to chart
	public static function getFieldsToChart($project_id, $form="", $field_list=array())
	{
		global $table_pk;

		if (!is_numeric($project_id)) return false;

		// If $field_list was provided, then ignore $form
		if (empty($field_list) && $form != "") {
			$sqlsub = "and form_name = '".db_escape($form)."'";
			$use_field_list = false;
		} else {
			$sqlsub = "and field_name in (".prep_implode($field_list).")";
			$use_field_list = true;
		}

		// Query to get fields
		$fields = array();
		$sql = "select field_name from redcap_metadata where project_id = $project_id $sqlsub
                and field_name != '$table_pk' and element_type != 'file'
                and element_type != 'descriptive' order by field_order";
		$qrs = db_query($sql);
		while ($rs = db_fetch_assoc($qrs))
		{
			$fields[$rs['field_name']] = true;
		}

		// If was provided with explicit list of fields in array, then preserve their order
		if ($use_field_list) {
			foreach ($field_list as $key=>$this_field) {
				if (!isset($fields[$this_field])) {
					// Remove field from array
					unset($field_list[$key]);
				}
			}
			$fields = array_values($field_list);
		} else {
			$fields = array_keys($fields);
		}

		// Return array
		return $fields;
	}

	// Render charts
	public static function renderCharts($project_id, $totalrecs, $fields, $form="", $includeRecordsEvents=array(), $hasFilterWithNoRecords=false)
	{
		global $Proj, $lang, $user_rights, $enable_plotting, $table_pk, $table_pk_label, $view_results, $longitudinal, $double_data_entry;

		// Determine if this is the survey page
		$isSurveyPage = (PAGE == 'surveys/index.php');

		// Determine if we should display the Google Chart Tools plots
		$displayGCTplots = ((!$isSurveyPage && $enable_plotting == '2') || ($isSurveyPage && ($view_results == '1' || $view_results == '3')));

		// Determine if we should display the Stats tables
		$displayStatsTables = ((!$isSurveyPage && $enable_plotting == '2') || ($isSurveyPage && ($view_results == '2' || $view_results == '3')));

		// Set array to discern what are categorical fields
		$mc_field_types = array("radio", "select", "dropdown", "yesno", "truefalse", "checkbox", "sql");

		// Get results code hash, if applicable
		$results_code_hash = ($isSurveyPage && isset($_POST['results_code_hash'])) ? preg_replace("/[^0-9a-zA-Z_-]/", "", $_POST['results_code_hash']) : '';

		// Create array of validation types to reference later
		$valtypes = getValTypes();

		// Call the Google Chart Tools javascript
		if ($displayGCTplots)
		{
			print "<script type='text/javascript' src='" . APP_PATH_JS . "StatsAndCharts.js'></script>";
			// Create array for storing the names of all fields with plots displayed on the page
			$fieldsDisplayed = array();
		}

		// Ensure that the user has form-level access to each field's form. If they don't, then remove the field.
		if ($form == '' && !empty($fields)) {
			// Loop through fields
			foreach ($fields as $key=>$this_field) {
				$this_form = $Proj->metadata[$this_field]['form_name'];
				if ($user_rights['forms'][$Proj->metadata[$this_field]['form_name']] == '0') {
					unset($fields[$key]);
				}
			}
		}

		// Obtain the descriptive stats (i.e. new expanded stats)
		if ($displayStatsTables)
		{
			$descripStats = DataExport::getDescriptiveStats($fields, $totalrecs, $form, $includeRecordsEvents, $hasFilterWithNoRecords);
		}

		// Add includeRecordsEvents as a JS variable that we can use in AJAX requests on this page if the data is limited
		// using filters in the report. This allows us to be more efficient than to rebuilt $includeRecordsEvents with each AJAX request.
		print "<script type='text/javascript'>var hasFilterWithNoRecords = ".($hasFilterWithNoRecords ? 1 : 0)."; "
			. "var includeRecordsEvents = '".js_escape(encrypt(serialize($includeRecordsEvents)))."';</script>";

		## Build array with number of non-missing records for each field on this form
		//Limit records pulled only to those in user's Data Access Group
		if ($user_rights['group_id'] == "") {
			$group_sql  = "";
		} else {
			$group_sql  = "and d.record in (" . prep_implode(Records::getRecordListSingleDag($project_id, $user_rights['group_id'])). ")";
		}

		// Query to calculate the found values for checkboxes (must deal with them differently)
		$chkbox_found = array();
		// First check if any checkboxes exist on this form (if not, skip a query for performance)
		$formHasCheckboxes = false;
		foreach ($fields as $this_field) {
			if (!$Proj->isCheckbox($this_field)) continue;
			$formHasCheckboxes = true;
			break;
		}
		if ($formHasCheckboxes)
		{
			$sql = "select x.field_name, count(1) as count from (select d.field_name, concat(d.event_id,'-',d.record,'-',d.field_name) as new1
                    from redcap_data d, redcap_metadata m where m.project_id = $project_id and m.project_id = d.project_id and
                    d.field_name = m.field_name and m.element_type = 'checkbox' $group_sql group by new1) as x group by x.field_name";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$chkbox_found[$row['field_name']] = $row['count'];
			}
		}

		?>
        <!-- Invisible div "template" to insert into a plot's div if plot shouldn't be displayed -->
        <div id="no_show_plot_div" style="display:none;">
            <p style="color:#777;font-size:11px;">
				<?php echo ($isSurveyPage ? $lang['survey_202'] : $lang['survey_206']) ?>
            </p>
        </div>
		<?php


		// Options to show/hide plots and stats tables (GCT only)
		if ((!$isSurveyPage && $enable_plotting == '2') || ($isSurveyPage && $view_results == '3'))
		{
			// Create drop-down options of all forms
			$formDropdownOptions = array(''=>$lang['graphical_view_44']);
			foreach ($Proj->forms as $this_form=>$attr) {
				// Don't show if user has None rights to this form
				if (isset($user_rights['forms'][$this_form]) && $user_rights['forms'][$this_form] > 0) {
					$formDropdownOptions[$this_form] = $attr['menu'];
				}
			}
			// DDE: If user is DDE person 1 or 2, then limit to ONLY their records
			$dde_filter = "";
			if ($double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) {
				$dde_filter = "ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
			}
			// Create drop-down options for the records in this report
			$allRecordsEvents = Records::getData('array', array_keys($includeRecordsEvents), $table_pk, array(), $user_rights['group_id'], false, false, false, $dde_filter);
			$allRecordsEventsOptions = array(''=>$lang['data_entry_91']);
			foreach ($allRecordsEvents as $this_record=>$eattr) {
				foreach (array_keys($eattr) as $this_event_id) {
					$allRecordsEventsOptions[$this_event_id.'[__EVTID__]'.$this_record] = removeDDEending($this_record) . ($longitudinal ? " - ".$Proj->eventInfo[$this_event_id]['name_ext'] : '');
				}
			}
			// Get number of forms in this project
			$numForms = count($Proj->forms);
			// For ALL report, if project has just one form, then set it manually rather than forcing user to select it
			if ($_GET['report_id'] == 'ALL' && $numForms == 1) {
				$_GET['page'] = $Proj->firstForm;
			}
			// Set disabled attribute for record drop-down
			$recordDropdownDisabled = ($_GET['report_id'] == 'ALL' && $numForms > 1 && (!isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] == ''))) ? 'disabled' : '';
			// Display table of display options
			print 	RCView::div(array('id'=>"showPlotsStatsOptions", 'style'=>"margin:15px 0 25px;max-width:720px;"),
				RCView::table(array('class'=>'form_border', 'style'=>"width:100%;"),
					// Header
					($isSurveyPage ? '' :
						RCView::tr(array(),
							RCView::td(array('class'=>'header', 'colspan'=>'2'),
								$lang['graphical_view_61']
							)
						)
					) .
					// Display list of forms ONLY for report_id=ALL
					(!($_GET['report_id'] == 'ALL' && $numForms > 1) ? '' :
						RCView::tr(array(),
							RCView::td(array('class'=>'labelrc', 'style'=>"padding:10px 8px;"),
								$lang['graphical_view_43']
							) .
							RCView::td(array('class'=>'labelrc', 'style'=>"padding:10px 8px;"),
								RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'', 'onchange'=>"
                                            showProgress(1);
                                            window.location.href = app_path_webroot + page + '?pid='+pid+'&report_id=ALL&stats_charts=1&page='+this.value;
                                        "), $formDropdownOptions, $_GET['page'])
							)
						)
					) .
					// Display list of records (but not on surveys)
					($isSurveyPage ? '' :
						RCView::tr(array(),
							RCView::td(array('class'=>'labelrc', 'style'=>"padding:10px 8px;font-weight:normal;"),
								$lang['graphical_view_60']
							) .
							RCView::td(array('class'=>'labelrc', 'style'=>"padding:10px 8px;"),
								RCView::select(array($recordDropdownDisabled=>$recordDropdownDisabled, 'class'=>'x-form-text x-form-field', 'style'=>'', 'onchange'=>"
                                                showProgress(1);
                                                var recevturl = '';
                                                if (this.value.length > 0) {
                                                    var recevt = this.value.split('[__EVTID__]');
                                                    recevturl = '&record='+recevt[1]+'&event_id='+recevt[0];
                                                }
                                                window.location.href = app_path_webroot + page + '?pid='+pid+'&report_id='+getParameterByName('report_id') + '&stats_charts=1'
                                                    + (getParameterByName('page') == '' ? '' : '&page='+getParameterByName('page'))
                                                    + (getParameterByName('instruments') == '' ? '' : '&instruments='+getParameterByName('instruments'))
                                                    + recevturl;
                                            "), $allRecordsEventsOptions, (isset($_GET['event_id']) ? $_GET['event_id'] : '') . '[__EVTID__]' . (isset($_GET['record']) ? $_GET['record'] : ''))
							)
						)
					) .
					// Viewing options: Show plots and/or stats
					RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'colspan'=>'2', 'style'=>"padding:10px 8px;"),
							$lang['graphical_view_65'] .
							RCView::button(array('class'=>'jqbuttonmed', 'disabled'=>'disabled', 'style'=>'color:#800000;margin-left:10px;font-weight:normal;', 'onclick'=>"showPlotsStats(3,this);"), $lang['graphical_view_66']) .
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#008000;margin-left:10px;font-weight:normal;', 'onclick'=>"showPlotsStats(1,this);"), $lang['graphical_view_67']) .
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#000080;margin-left:10px;font-weight:normal;', 'onclick'=>"showPlotsStats(2,this);"), $lang['graphical_view_68'])
						)
					)
				)
			);
			// If displaying NOTHING because form hasn't yet been selected on report_id=ALL, then display message.
			if ($_GET['report_id'] == 'ALL' && (!isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] == ''))) {
				print 	RCView::div(array('style'=>"max-width:700px;margin:15px 0 25px;color:#C00000;"),
					$lang['report_builder_104']
				);
			}
		}

		// Loop through all fields on this form
		$s = 0;
		foreach ($fields as $field_name)
		{
			// Skip record_id field
			if ($field_name == $table_pk) continue;
			// Set field attributes
			$field_attr = array(); //reset from previous loop
			$field_id = $Proj->metadata[$field_name]['field_order'];
			$field_form = $Proj->metadata[$field_name]['form_name'];
			$element_label = strip_tags(label_decode($Proj->metadata[$field_name]['element_label']));
			$validation_type = $Proj->metadata[$field_name]['element_validation_type'];
			$element_type = $Proj->metadata[$field_name]['element_type'];
			$element_enum = $Proj->metadata[$field_name]['element_enum'];
			// Set plot variables
			$missing_id = "dc_missing_$field_id";
			$spin_missing_id = "dc_spin_missing_$field_id";
			$high_id =  "dc_high_$field_id";
			$spin_high_id = "dc_spin_high_$field_id";
			$low_id = "dc_low_$field_id";
			$spin_low_id = "dc_spin_low_$field_id";
			$refresh_plot_id = "dc_refresh_$field_id";
			$spinner_plot_id = "dc_spin_refresh_$field_id";
			$img_plot_id = "dc_img_refresh_$field_id";


			// SURVEYS ONLY: Ignore the Form Status field
			if ($isSurveyPage && $field_name == $form."_complete") continue;

			// Check if this field is plottable (i.e. not free-form text)
			$isPlottable = (in_array($element_type, $mc_field_types) || $validation_type == 'float' || $validation_type == 'int'
				|| (isset($validation_type) && ($valtypes[$validation_type]['data_type'] == 'number_comma_decimal' || $valtypes[$validation_type]['data_type'] == 'number')) || $element_type == 'calc' || $element_type == 'slider');

			// Set low/high delimiter
			$lowHighDelimiter = ($valtypes[$validation_type]['data_type'] == 'number_comma_decimal') ? ";" : ",";

			// Determine if we should display the plot
			$will_plot = (!(($element_type == 'text' || $element_type == 'textarea') && $validation_type == ''));

			// Graphical page: Show it for GCT (because we'll show stats table).
			// Survey page: If field is not plottable, only show the field if we're showing the stats table.
			if (!$will_plot && (!$displayGCTplots || ($isSurveyPage && $displayGCTplots && !$displayStatsTables && !$isPlottable)))
			{
				continue;
			}

			//Don't show plot if a multiple choice values are non-numerical (e.g., A | B | C)
			// if (in_array($element_type, $mc_field_types) && trim($element_enum) != "")
			// {
			// if (!is_numeric(substr(trim($element_enum), 0, 1))) {
			// $will_plot = false;
			// }
			// }

			//Determine type of plot to display
			$plot_type = 'BarChartDesc';
			if ($will_plot && $element_type != 'checkbox' && $element_type != 'truefalse' && $element_type != 'yesno' && $element_type != 'select' && $element_type != 'radio' && $element_type != 'advcheckbox') {
				$plot_type = 'BoxPlotDesc';
			}

			// Set "Refresh" link's action
			$pie_chart = "";
			if ($displayGCTplots && !isset($field_attr['hide'])) {
				$refreshPlot = "showSpinner('$field_name');renderCharts('$field_name',$('#chart-select-$field_name').val(),'$results_code_hash');";
				// Give option for bar charts to be viewed as pie charts (exclude checkboxes from being viewed as pie charts
				// because the percentages add up to higher than 100% due to multiple responses per record - Google Charts just won't work with this)
				if ($will_plot && $plot_type == 'BarChartDesc' && $element_type != 'checkbox')
				{
					if (!$isSurveyPage) $pie_chart .= " | ";
					$pie_chart .=  "<select id='chart-select-$field_name' style='font-size:11px;' onchange=\"showSpinner('$field_name');renderCharts('$field_name',this.value,'$results_code_hash');return false;\">
                                        <option value='BarChart' selected>{$lang['graphical_view_49']}</option>
                                        <option value='PieChart'>{$lang['graphical_view_50']}</option>
                                    </select>";
				}
			}

			?>
            <div class="spacer"></div>

            <p class="dc_para">
                <!-- Field label -->
                <b class="dc_header notranslate"><?php print $element_label ?></b> &nbsp;<i style="color:#777;"><?php if (!$isSurveyPage) print "($field_name)" ?></i>
				<?php
				// Refresh link
				if ($will_plot && $displayGCTplots && !isset($field_attr['hide'])) {
					?>
                    <a href="javascript:;" class="dc_a d-print-none" style="margin:0 3px 0 10px;" id="<?php echo "refresh-link-".$field_name ?>" onclick="<?php echo $refreshPlot ?>return false;"><?php echo $lang['graphical_view_35'] ?></a>
					<?php
				}
				// Display option to view as Pie Chart (for GCT Bar Charts only)
				echo RCView::span(array('class'=>'d-print-none'), $pie_chart);
				?>
            </p>

			<?php

			// Display the plot div
			if ($will_plot || $displayGCTplots || $displayStatsTables)
			{
				// Google Chart Tools (via ajax)
				if ($displayGCTplots || $displayStatsTables)
				{
					## DESCRIPTIVE STATS TABLE FOR THIS FIELD
					if ($displayStatsTables)
					{
						// Set this field's statistical values
						$field_attr = $descripStats[$field_name];

						// MISSING value: If we're viewing the project Graphical page and using GCT, show Missing value as link to retrieve missing values
						// Set missing percent value
						$field_attr['missing_perc'] = round($field_attr['missing']/($field_attr['count']+$field_attr['missing'])*100,1);
						if ($field_attr['missing_perc'] < 0 || is_nan($field_attr['missing_perc'])) $field_attr['missing_perc'] = 0;
						// Now set the label for missing and missing percent
						$missing_label = $field_attr['missing'] . " (" . User::number_format_user($field_attr['missing_perc'], 1) . "%)";
						// Display the missing label
						if ($isSurveyPage || $field_attr['missing'] == 0) {
							$field_attr['missing'] = $missing_label;
						} else {
							$field_attr['missing'] = "<a title=\"".js_escape2($lang['graphical_view_71'])."\" href='javascript:;' class='dc_a' onclick=\"ToggleDataCleanerDiv(table_pk_label,'".js_escape("<b>{$lang['graphical_view_36']}{$lang['colon']}</b>")." ','$missing_id','$spin_missing_id','$field_name','miss','$field_form','{$user_rights['group_id']}');\">$missing_label</a> ";
						}
						// Determine if we can show the table
						if (!isset($field_attr['hide']))
						{
							?>
                            <div style="padding:10px 0;" class="descrip_stats_table" id="stats-<?php echo $field_name ?>">
								<?php if ($field_attr['getstats']) { ?>
                                    <!-- Numerical stats table -->
                                    <table class="expStatsReport">
                                        <tr style='font-weight:bold;font-size:12px;'>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_69'] ?><br>(N)</td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_24'] ?><span class='em-ast'>*</span></td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_51'] ?></td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_25'] ?></td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_26'] ?></td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_27'] ?></td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_29'] ?></td>
                                            <td rowspan="2" style="background-color:#eee;"><?php echo $lang['graphical_view_74'] ?></td>
                                            <td colspan="7" style="background-color:#ddd;"><?php echo $lang['graphical_view_53'] ?></td>
                                        </tr>
                                        <tr style='font-weight:bold;font-size:12px;'>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.05', 2) ?></td>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.10', 2) ?></td>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.25', 2) ?></td>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.50', 2) ?><div style="font-weight:normal;"><?php echo $lang['graphical_view_28'] ?></div></td>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.75', 2) ?></td>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.90', 2) ?></td>
                                            <td style="background-color:#eee;"><?php echo User::number_format_user('0.95', 2) ?></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo User::number_format_user($field_attr['count']) ?></td>
                                            <td><?php echo $field_attr['missing'] ?></td>
                                            <td><?php echo $field_attr['unique'] ?></td>
                                            <td><?php echo $field_attr['min'] ?></td>
                                            <td><?php echo $field_attr['max'] ?></td>
                                            <td><?php echo $field_attr['mean'] ?></td>
                                            <td><?php echo $field_attr['stdev'] ?></td>
                                            <td><?php echo $field_attr['sum'] ?></td>
                                            <td><?php echo $field_attr['perc05'] ?></td>
                                            <td><?php echo $field_attr['perc10'] ?></td>
                                            <td><?php echo $field_attr['perc25'] ?></td>
                                            <td><?php echo $field_attr['median'] ?></td>
                                            <td><?php echo $field_attr['perc75'] ?></td>
                                            <td><?php echo $field_attr['perc90'] ?></td>
                                            <td><?php echo $field_attr['perc95'] ?></td>
                                        </tr>
                                    </table>
                                    <br>
                                    <!-- Invisible section for MISSING values to be loaded -->
                                    <img class="dc_img_spinner" id="<?php echo $spin_missing_id ?>" src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif">
                                    <div style="max-width:700px;display:none;" id="<?php echo $missing_id?>"></div>
									<?php if (!empty($field_attr['low'])) { ?>
                                        <!-- Lowest values -->
                                        <div style="padding:5px 0;">
											<?php echo "<b>{$lang['graphical_view_37']}{$lang['colon']}</b> " . implode("$lowHighDelimiter ", $field_attr['low']); ?>
                                        </div>
									<?php } ?>
									<?php if (!empty($field_attr['high'])) { ?>
                                        <!-- Highest values -->
                                        <div>
											<?php echo "<b>{$lang['graphical_view_38']}{$lang['colon']}</b> " . implode("$lowHighDelimiter ", $field_attr['high']); ?>
                                        </div>
									<?php } ?>
								<?php } else { ?>
                                    <!-- Categorical/text field stats table -->
                                    <table class="expStatsReport">
                                        <tr style='font-weight:bold;font-size:12px;'>
                                            <td style="background-color:#eee;"><?php echo $lang['graphical_view_69'] ?><br>(N)</td>
                                            <td style="background-color:#eee;"><?php echo $lang['graphical_view_24'] ?><span class='em-ast'>*</span></td>
											<?php if (isset($field_attr['unique'])) { ?>
                                                <td style="background-color:#eee;"><?php echo $lang['graphical_view_51'] ?></td>
											<?php } ?>
                                        </tr>
                                        <tr>
                                            <td><?php echo User::number_format_user($field_attr['count']) ?></td>
                                            <td><?php echo $field_attr['missing'] ?></td>
											<?php if (isset($field_attr['unique'])) { ?>
                                                <td><?php echo $field_attr['unique'] ?></td>
											<?php } ?>
                                        </tr>
                                    </table>
                                    <br>
                                    <!-- Invisible section for MISSING values to be loaded -->
                                    <img class="dc_img_spinner" id="<?php echo $spin_missing_id ?>" src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif">
                                    <div style="max-width:700px;display:none;" id="<?php print $missing_id?>"></div>
									<?php if (isset($field_attr['freq'])) { ?>
                                        <!-- Categorical counts/frequencies -->
                                        <div style="padding:5px 0;max-width:700px;">
											<?php echo "<b>{$lang['graphical_view_52']}</b> " . implode(", ", $field_attr['freq']); ?>
                                        </div>
									<?php } ?>
								<?php } ?>
                            </div>
							<?php
						}
					}

					## PLOT THIS FIELD
					if ($will_plot && $displayGCTplots && !isset($field_attr['hide']))
					{
						// Add field name to array for making the ajax call
						$fieldsDisplayed[] = $field_name;
						// Plot it
						print "<div id='plot-$field_name' class='gct_plot'><img style='vertical-align: middle;' src='" . APP_PATH_IMAGES. "progress.gif' title='Loading...' alt='Loading...'></div>";
						// Download button
						print  "<div id='plot-download-btn-$field_name' class='plot-download-div'>
                                    <button class='jqbuttonmed' onclick=\"downloadImgViaDataUrl('plot-$field_name','$field_name');\">
                                        <img src='" . APP_PATH_IMAGES . "arrow_down_sm.png'>
                                        <span>{$lang['graphical_view_77']}</span>
                                    </button>
                                </div>";
					}
				}
			}
			// HIDE FIELD: For questions with no data, give notice as to why no plot is being displayed.
			// Do not display this notice if has no unique stat value (i.e. it is a free-form text field)
			if (isset($field_attr['hide']) || (!$will_plot && $displayGCTplots && $isPlottable))
			{
				print "<div class='gct_plot' style='color:#777;font-size:11px;margin:20px 0;'>";
				print ($isSurveyPage ? $lang['survey_202'] : $lang['survey_206']);
				print "</div>";
			}
			// Increment the counter by amount of microseconds to pace requests to R/Apache server
			$s += (isset($plot_pace) ? $plot_pace : 1) * 1000;
		}

		// Build javascript that loads the plots
		if ($displayGCTplots)
		{
			?>
            <!-- Javascript -->
            <script type="text/javascript">
                // Comma-delimited list of all field plots on page
                var fields = '<?php echo implode(',', $fieldsDisplayed) ?>';
                // Determine if we're on the survey page
                var isSurveyPage = <?php echo ($isSurveyPage ? 'true' : 'false') ?>;
                // Begin the daisy-chained AJAX requests to load each plot one at a time
                $(function(){
                    renderCharts(fields,'','<?php echo $results_code_hash ?>');
                });
            </script>
			<?php
		}

	}

	// Make sure that the results code hash belongs to __results in the survey URL
	public static function checkResultsCodeHash($__results, $results_code_hash)
	{
		// Return boolean if the submitted hash matches the expected hash (set both as upper case just in case of case different)
		return (strtoupper(DataExport::getResultsCodeHash($__results)) == strtoupper($results_code_hash));
	}

	// Generate the results code hash that belongs to __results in the survey URL
	public static function getResultsCodeHash($__results=null)
	{
		global $__SALT__;
		if (empty($__results)) return false;
		// Use the project-level $__SALT__ variable to salt the md5 hash
		// Use the 10th thru 16th character of the md5 as the true results_code_hash
		return substr(md5($__SALT__ . $__results), 10, 6);
	}

	public static function validate_appname($app){
		$exist = db_result(db_query("select count(1) from redcap_projects where project_name = '$app'"), 0);
		return $exist;
	}

	# Presumes the current mysql connections
	public static function validate_formname($app,$form){
		if (!DataExport::validate_appname($app)) {
			return false;
		}
		$q = db_query("select 1 from redcap_metadata where form_name = '$form' and project_id = " . PROJECT_ID . " limit 1");
		return db_num_rows($q);
	}

	public static function validate_fieldname($app,$form,$field) {
		if (!DataExport::validate_formname($app,$form)) {
			return false;
		}
		$q = db_query("select 1 from redcap_metadata where field_name = '$field' and form_name = '$form' and project_id = " . PROJECT_ID . " limit 1");
		return db_num_rows($q);
	}

	## Retrieve project data in Raw CSV format
	## NOTE: $chkd_flds and $parent_chkd_flds are comma-delimited lists of fields that we're exporting
	## with each field surrounded by single quotes (gets used in query).
	public static function fetchDataCsv($chkd_flds="",$parent_chkd_flds="",$getReturnCodes=false,$do_hash=false,$do_remove_identifiers=false,
						  $useStandardCodes=false,$useStandardCodeDataConversion=false,$standardId=-1,$standardCodeLookup=array(),
						  $useFieldNames=true,$exportDags=true,$exportSurveyFields=true)
	{
		// Global variables needed
		global  $Proj, $longitudinal, $project_id, $user_rights, $is_child, $is_child_of, $project_id_parent,
				$do_date_shift, $date_shift_max, $do_surveytimestamp_shift, $table_pk, $salt, $__SALT__;

		// Get DAGs with group_id and unique name, if exist
		$dags = array();
		$dags_labels = array();
		if (is_object($Proj) && !empty($Proj)) {
			$dags = $Proj->getUniqueGroupNames();
			foreach ($Proj->getGroups() as $group_id=>$group_name) {
				$dags_labels[$group_id] = label_decode($group_name);
			}
		}

		// Set extra set of reserved field names for survey timestamps
		$extra_reserved_field_names = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp");

		// If surveys exist, get timestamp and identifier of all responses and place in array
		$timestamp_identifiers = array();
		if ($exportSurveyFields)
		{
			$sql = "select r.record, r.completion_time, p.participant_identifier, s.form_name, p.event_id
					from redcap_surveys s, redcap_surveys_response r, redcap_surveys_participants p, redcap_events_metadata a
					where p.participant_id = r.participant_id and s.project_id = $project_id and s.survey_id = p.survey_id
					and p.event_id = a.event_id and r.first_submit_time is not null order by r.record, r.completion_time";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Replace double quotes with single quotes
				$row['participant_identifier'] = str_replace("\"", "'", label_decode($row['participant_identifier']));
				// If response exists but is not completed, note this in the export
				if ($row['completion_time'] == "") $row['completion_time'] = "[not completed]";
				// Add to array
				$timestamp_identifiers[$row['record']][$row['event_id']][$row['form_name']] = array('ts'=>$row['completion_time'], 'id'=>$row['participant_identifier']);
			}
		}
		// If returning the survey Return Codes, obtain them and put in array
		$returnCodes = array();
		if ($getReturnCodes)
		{
			$sql = "select s.survey_id, r.record, e.event_id, r.return_code, r.response_id, s.form_name, r.completion_time
					from redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r, redcap_events_arms a, redcap_events_metadata e
					where s.project_id = $project_id and s.survey_id = p.survey_id and p.participant_id = r.participant_id
					and a.project_id = s.project_id and a.arm_id = e.arm_id and s.save_and_return = 1
					and r.first_submit_time is not null";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Skip this return code (leave blank) if response if complete and participants cannot edit completed responses
				if ($row['completion_time'] != "" && !$Proj->surveys[$row['survey_id']]['edit_completed_response']) continue;
				// If this response doesn't have a return code, then create it on the fly
				if ($row['return_code'] == "") {
					$row['return_code'] = Survey::getUniqueReturnCode($row['survey_id'], $row['response_id']);
				}
				// Add to array
				$returnCodes[$row['record']][$row['event_id']][$row['form_name']] = strtoupper($row['return_code']);
			}
		}


		## RETRIEVE HEADERS FOR CSV FILES AND SET DEFAULT VALUES FOR EACH DATA ROW
		//Create headers as first row of CSV and get default values for each row. Only need headers when exporting to R (SAS, SPSS, & STATA do not need them)
		$headersArray = array('','');
		$headersLabelsArray = array();
		$field_defaults = array();
		$field_defaults_labels = array();
		$field_type = array();
		$field_val_type = array();
		$field_names = array();
		$field_phi = array();
		$chkbox_choices = array();
		$mc_choices = array();
		$mc_field_types = array("radio", "select", "yesno", "truefalse"); // Don't include "checkbox" because it gets dealt with on its own
		$prev_form = "";
		$prev_field = "";
		//Build query
		$sql = "select meta.field_name, meta.element_label, meta.element_enum, meta.element_type, meta.form_name, meta.element_validation_type, meta.field_phi
				from redcap_metadata meta where meta.project_id = $project_id and meta.field_name in ($chkd_flds) and meta.element_type != 'descriptive' order by meta.field_order";
		$q = db_query($sql);
		while($row = db_fetch_array($q))
		{
			// If starting a new form and form is a survey, then add survey timestamp field here
			if ((($prev_form != $row['form_name'] && $row['field_name'] != $table_pk)
					|| ($prev_form == $row['form_name'] && $prev_field == $table_pk))
				&& isset($Proj->forms[$row['form_name']]['survey_id']))
			{
				// If returning the survey Return Codes and survey has Save&Return enabled, add return_code header
				if ($getReturnCodes && $Proj->surveys[$Proj->forms[$row['form_name']]['survey_id']]['save_and_return'])
				{
					$returnCodeFieldname = $row['form_name'].'_return_code';
					$headersArray[0] .= "$returnCodeFieldname,";
					$headersArray[1] .= ',';
					$field_defaults[$returnCodeFieldname] = '"",';
					$field_defaults_labels[$returnCodeFieldname] = '"",';
				}
				// Add timestamp and identifier, if any surveys exist
				if ($exportSurveyFields)
				{
					// Add timestamp
					$timestampFieldname = $row['form_name'].'_timestamp';
					$headersArray[0] .= "$timestampFieldname,";
					$headersArray[1] .= ',';
					$field_defaults[$timestampFieldname] = '"",';
					$field_defaults_labels[$timestampFieldname] = '"",';
				}
			}

			//Get field_name as column header
			$lookupValue = isset($standardCodeLookup[$row['field_name']]) ? $standardCodeLookup[$row['field_name']] : '';
			if ($row['element_type'] != "checkbox")
			{
				// REGULAR FIELD (NON-CHECKBOX)
				// Set headers
				if ($useFieldNames) {
					$headersArray[0] .= $row['field_name'] . ',';
					if (trim($lookupValue) != '' && $lookupValue != $row['field_name']) {
						$headersArray[1] .=  $lookupValue . ',';
					}else {
						$headersArray[1] .=  $row['field_name']. ',';
					}
				}else {
					if (trim($lookupValue) != '') {
						$headersArray[0] .=  $lookupValue . ',';
					}else {
						$headersArray[0] .=  $row['field_name'] . ',';
					}
				}
				// Set header labels
				$headersLabelsArray[$row['field_name']] = $row['element_label'];
				// For multiple choice questions, store codes/labels in array for later use
				if (in_array($row['element_type'], $mc_field_types))
				{
					if ($row['element_type'] == "yesno") {
						$mc_choices[$row['field_name']] = parseEnum("1, Yes \\n 0, No");
					} elseif ($row['element_type'] == "truefalse") {
						$mc_choices[$row['field_name']] = parseEnum("1, True \\n 0, False");
					} else {
						foreach (parseEnum($row['element_enum']) as $this_value=>$this_label)
						{
							// Replace characters that were converted during post (they will have ampersand in them)
							if (strpos($this_label, "&") !== false) {
								$this_label = html_entity_decode($this_label, ENT_QUOTES);
							}
							//Replace double quotes with single quotes
							$this_label = str_replace("\"", "'", $this_label);
							//Replace line breaks with two spaces
							$this_label = str_replace("\r\n", "  ", $this_label);
							//Add to array
							$mc_choices[$row['field_name']][$this_value] = $this_label;
						}
					}
				}
			}
			else
			{
				// CHECKBOX FIELDS: Loop through checkbox elements and append string to variable name
				foreach (parseEnum($row['element_enum']) as $this_value=>$this_label)
				{
					// Add multiple choice values to array for later use
					$chkbox_choices[$row['field_name']][$this_value] = '0,';
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$this_value = (Project::getExtendedCheckboxCodeFormatted($this_value));
					// Headers: Append triple underscore + coded value
					$checkboxLookupValue = DataExport::evalDataConversion($row['field_name'], $row['element_type'], $this_value, $row['data_conversion']);
					if($useFieldNames) {
						$headersArray[0] .= $row['field_name'] . '___' . $this_value. ',';
						if(trim($checkboxLookupValue) != '') {
							$headersArray[1] .=  $checkboxLookupValue . ',';
						}else {
							$headersArray[1] .=  ',';
						}
					} else {
						if(trim($checkboxLookupValue) != '') {
							$headersArray[0] .=  $checkboxLookupValue . ',';
						}else {
							$headersArray[0] .=  $row['field_name'] . '___' . $this_value. ',';
						}
					}
					// Set header labels
					$headersLabelsArray[$row['field_name'] . '___' . $this_value] = $row['element_label'] . " (choice='$this_label')";
				}
			}
			//Get field type of each field to vary the handling of each
			$field_type[$row['field_name']] = $row['element_type'];
			//Set default row values
			switch ($row['element_type']) {
				case "textarea":
				case "text":
					//Get validation type and put into array
					$field_val_type[$row['field_name']] = $row['element_validation_type'];
					switch ($row['element_validation_type']) {
						//Numbers and dates do not need quotes around them
						case "float":
						case "int":
						case "date":
							$field_defaults[$row['field_name']] = ',';
							$field_defaults_labels[$row['field_name']] = ',';
							break;
						//Put quotes around normal text strings
						default:
							$field_defaults[$row['field_name']] = '"",';
							$field_defaults_labels[$row['field_name']] = '"",';
					}
					break;
				case "select":
					if ($row['field_name'] == $row['form_name'] . "_complete") {
						$field_defaults[$row['field_name']] = '0,'; //Form Status gets default of 0
						$field_defaults_labels[$row['field_name']] = '"Incomplete",';
					} else {
						$field_defaults[$row['field_name']] = ','; //Regular dropdowns get null default
						$field_defaults_labels[$row['field_name']] = ',';
					}
					break;
				case "checkbox":
					foreach ($chkbox_choices[$row['field_name']] as $this_value=>$this_label) {
						if ($useStandardCodeDataConversion) {
							$field_defaults[$row['field_name']][$this_value] = DataExport::evalCheckboxDataConversion(0, $row['data_conversion2']).',';
						} else {
							$field_defaults[$row['field_name']][$this_value] = '0,';
						}
						$field_defaults_labels[$row['field_name']][$this_value] = '"Unchecked",';
					}
					break;
				default:
					$field_defaults[$row['field_name']] = ',';
					$field_defaults_labels[$row['field_name']] = ',';
			}

			// Store all field names into array to use for Syntax File code
			if (!isset($chkbox_choices[$row['field_name']])) {
				// Add non-checkbox fields to array
				$field_names[] = $row['field_name'];
			} else {
				// If field is a checkbox, then expand to create variables for each choice
				foreach ($chkbox_choices[$row['field_name']] as $this_value=>$this_label) {
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$this_value = (Project::getExtendedCheckboxCodeFormatted($this_value));
					// Append triple underscore + coded value
					$field_names[] = $row['field_name'] . '___' . $this_value;
				}
			}

			//Store all fields that are Identifiers into array
			if ($row['field_phi']) {
				$field_phi[] = $row['field_name'];
			}

			//Add extra columns (if needed) if we're on the first field
			if ($row['field_name'] == $table_pk)
			{
				// Add event name, if longitudinal
				if ($longitudinal)
				{
					$headersArray[0] .= 'redcap_event_name,';
					$headersArray[1] .= ',';
					$field_defaults['redcap_event_name'] = '"",';
					$field_defaults_labels['redcap_event_name'] = '"",';
				}
				// Add DAG name, if project has DAGs and user is not in a DAG
				if ($exportDags)
				{
					$headersArray[0] .= 'redcap_data_access_group,';
					$headersArray[1] .= ',';
					$field_defaults['redcap_data_access_group'] = '"",';
					$field_defaults_labels['redcap_data_access_group'] = '"",';
				}
				// Add survey identifier (unless we've set it to remove all identifiers - treat survey identifier same as field identifier)
				if ($exportSurveyFields && !$do_remove_identifiers) {
					$headersArray[0] .= 'redcap_survey_identifier,';
					$headersArray[1] .= ',';
					$field_defaults['redcap_survey_identifier'] = '"",';
					$field_defaults_labels['redcap_survey_identifier'] = '"",';
				}
			}

			// Set values for next loop
			$prev_form = $row['form_name'];
			$prev_field = $row['field_name'];
		}


		// CREATE ARRAY OF FIELD DEFAULTS SPECIFIC TO EVERY EVENT (BASED ON FORM-EVENT DESIGNATION)
		$field_defaults_events = array();
		$field_defaults_labels_events = array();
		// CLASSIC: Just add $field_defaults array as only array element
		if (!$longitudinal) {
			$field_defaults_events[$Proj->firstEventId] = $field_defaults;
			$field_defaults_labels_events[$Proj->firstEventId] = $field_defaults_labels;
		}
		// LONGITUDINAL: Loop through each event and set defaults based on form-event mapping
		else {
			// Loop through each event
			foreach (array_keys($Proj->eventInfo) as $event_id) {
				// Get $designated_forms from $Proj->eventsForms
				$designated_forms = (isset($Proj->eventsForms[$event_id])) ? $Proj->eventsForms[$event_id] : array();
				// Loop through each default field value and either keep or remove for this event
				foreach ($field_defaults as $field=>$raw_value) {
					// Get default label value
					$label_value = $field_defaults_labels[$field];
					// Check if a checkbox OR a form status field (these are the only 2 we care about because they are the only ones with default values)
					$field_form = $Proj->metadata[$field]['form_name'];
					if ($Proj->isCheckbox($field) || $field == $field_form."_complete") {
						// Is field's form designated for the current event_id?
						if (!in_array($field_form, $designated_forms)) {
							// Set both raw and label value as blank (appended with comma for delimiting purposes)
							if (is_array($raw_value)) {
								// Loop through all checkbox choices and set each individual value
								foreach (array_keys($raw_value) as $code) {
									$raw_value[$code] = $label_value[$code] = ",";
								}
							} else {
								$raw_value = $label_value = ",";
							}
						}
					}
					// Add to field defaults event array
					$field_defaults_events[$event_id][$field] = $raw_value;
					$field_defaults_labels_events[$event_id][$field] = $label_value;
				}
			}
		}


		## BUILD CSV STRING OF HEADERS
		$headers = substr($headersArray[0],0,-1) . "\n";


		## BUILD CSV STRING OF HEADER LABELS
		$headers_labels = '';
		//Use for replacing strings
		$orig = array("\"", "\r\n", "\r");
		$repl = array("'", "  ","");
		foreach (explode(",", $headersArray[0]) as $this_field)
		{
			if (trim($this_field) != '')
			{
				if (isset($headersLabelsArray[$this_field])) {
					$this_label = str_replace($orig, $repl, strip_tags(label_decode($headersLabelsArray[$this_field])));
				} elseif (isset(Project::$reserved_field_names[$this_field])) {
					$this_label = str_replace($orig, $repl, strip_tags(label_decode(Project::$reserved_field_names[$this_field])));
				} elseif (in_array($this_field, $extra_reserved_field_names)) {
					$this_label = 'Survey Timestamp';
				} else {
					$this_label = "[???????]";
				}
				$headers_labels .= '"' . $this_label . '",';
			}
		}
		$headers_labels = substr($headers_labels, 0, -1) . "\n";


		###########################################################################
		## RETRIEVE DATA
		//Set defaults
		$data_csv = "";
		$data_csv_labels = "";
		$record_id = "";
		$event_id  = "";
		$group_id  = "";
		$form = "";
		$id = 0;
		// Set array to keep track of which records are in a DAG
		$recordDags = array();
		//Check if any Events have been set up for this project. If so, add new column to list Event in CSV file.
		$event_names = array();
		$event_labels = array();
		if ($longitudinal) {
			$event_names = $Proj->getUniqueEventNames();
			foreach ($Proj->eventInfo as $event_id=>$attr) {
				$event_labels[$event_id] = label_decode($attr['name_ext']);
			}
		}
		//Build query for pulling the data and for building code for syntax files
		if ($user_rights['group_id'] == "") {
			$group_sql  = "";
			// If DAGS exist, also pull group_id's from data table
			if ($exportDags) {
				$chkd_flds .= ", '__GROUPID__'";
			}
		} else {
			$group_sql  = "AND record IN (" . prep_implode(Records::getRecordListSingleDag($project_id, $user_rights['group_id'])) . ")";
		}
		// Pull data as normal
		if (!$longitudinal) {
			$data_sql = "select d.*, '' as data_conversion, '' as data_conversion2 from redcap_data d
						 where d.project_id = $project_id and d.field_name in ($chkd_flds)
						 and d.event_id = {$Proj->firstEventId}
						 and d.record != '' $group_sql order by abs(d.record), d.record, d.event_id";
		} else {
			$data_sql = "select d.*, '' as data_conversion, '' as data_conversion2
						 from redcap_data d, redcap_events_metadata e, redcap_events_arms a
						 where d.project_id = $project_id and d.project_id = a.project_id
						 and a.arm_id = e.arm_id and e.event_id = d.event_id
						 and d.field_name in ($chkd_flds) and d.record != '' $group_sql
						 order by abs(d.record), d.record, a.arm_num, e.day_offset, e.descrip";
		}
		//Log this data export event
		if (!$is_child) {
			//Normal
			$log_display = $chkd_flds;
		} else {
			//If parent/child linking exists
			$log_display = ($chkd_flds == "") ? $parent_chkd_flds : "$parent_chkd_flds, $chkd_flds";
		}

		## PRE-LOAD DEFAULT VALUES FOR ALL FIELDS AS PLACEHOLDERS
		$firstDataEventId = $Proj->firstEventId;
		if ($longitudinal) {
			// LONGITUDINAL: Since we don't know what event_id will come out first from the data table, get it before we start looping in the data (not ideal but works)
			$q = db_query("$data_sql limit 1");
			if (db_num_rows($q) > 0) $firstDataEventId = db_result($q, 0, "event_id");
		}
		// Set default answers for first row
		$this_row_answers = $field_defaults_events[$firstDataEventId];
		$this_row_answers_labels = $field_defaults_labels_events[$firstDataEventId];

		## QUERY FOR DATA
		$q = db_query($data_sql);
		// If an error occurs for the query, output the error and stop here.
		if (db_error() != "") exit("<b>MySQL error " . db_errno() . ":</b><br>" . db_error() . "<br><br><b>Failed query:</b><br>$data_sql");
		//Loop through each answer, then render each line after all collected
		while ($row = db_fetch_assoc($q))
		{
			// Trim record, just in case spaces exist at beginning or end
			$row['record'] = trim($row['record']);
			// Check if need to start new line of data for next record
			if ($record_id !== $row['record'] || $event_id != ($is_child ? $Proj->firstEventId : $row['event_id']))
			{
				//Get date shifted day amount for this record
				if ($do_date_shift) {
					$days_to_shift = Records::get_shift_days($row['record'], $date_shift_max, $__SALT__);
				}
				//Render this row's answers
				if ($id != 0)
				{
					// HASH ID: If the record id is an Identifier and the user has de-id rights access, do an MD5 hash on the record id
					// Also, make sure record name field is not blank (can somehow happen - due to old bug?) by manually setting it here using $record_id.
					if ((($user_rights['data_export_tool'] != '1' && in_array($table_pk, $field_phi)) || $do_hash)) {
						$this_row_answers[$table_pk] = $this_row_answers_labels[$table_pk] = md5($salt . $record_id . $__SALT__) . ',';
					} else {
						$this_row_answers[$table_pk] = $this_row_answers_labels[$table_pk] = '"' . $record_id . '",';
					}
					//If Events exist, add Event Name
					if ($longitudinal) {
						$this_row_answers['redcap_event_name'] = '"' . $event_names[$event_id] . '",';
						$this_row_answers_labels['redcap_event_name'] = '"' . str_replace("\"", "'", $event_labels[$event_id]) . '",';
					}
					// If DAGs exist, add unique DAG name
					if ($exportDags) {
						$this_row_answers['redcap_data_access_group'] = '"' . $dags[$recordDags[$record_id]] . '",';
						$this_row_answers_labels['redcap_data_access_group'] = '"' . str_replace("\"", "'", $dags_labels[$recordDags[$record_id]]) . '",';
					}
					// If we're requesting the return codes, add them here
					if ($getReturnCodes && isset($returnCodes[$record_id][$event_id])) {
						foreach ($returnCodes[$record_id][$event_id] as $this_form=>$this_return_code) {
							if (isset($this_row_answers[$this_form.'_return_code'])) {
								$this_row_answers[$this_form.'_return_code'] = $this_row_answers_labels[$this_form.'_return_code']  = '"' . $this_return_code . '",';
							}
						}
					}
					// If project has any surveys, add the survey completion timestamp
					if ($exportSurveyFields && isset($timestamp_identifiers[$record_id][$event_id])) {
						//Get date shifted day amount for this record
						if ($do_surveytimestamp_shift) {
							$days_to_shift_survey_ts = Records::get_shift_days($record_id, $date_shift_max, $__SALT__);
						}
						// Add the survey completion timestamp for each survey
						foreach ($timestamp_identifiers[$record_id][$event_id] as $this_form=>$attr) {
							if (isset($this_row_answers[$this_form.'_timestamp'])) {
								// If user set option to date-shift the survey timestamp, then shift it
								if ($do_surveytimestamp_shift) {
									$attr['ts'] = Records::shift_date_format($attr['ts'], $days_to_shift_survey_ts);
								}
								// Add timestamp to arrays
								$this_row_answers[$this_form.'_timestamp'] = $this_row_answers_labels[$this_form.'_timestamp'] = '"' . $attr['ts'] . '",';
							}
						}
					}
					// If project has any surveys, add the identifier (if exists)
					if ($exportSurveyFields && !$do_remove_identifiers && isset($timestamp_identifiers[$record_id][$Proj->getFirstEventIdInArmByEventId($event_id)][$Proj->firstForm])) {
						$this_row_answers['redcap_survey_identifier'] = $this_row_answers_labels['redcap_survey_identifier'] = '"' . $timestamp_identifiers[$record_id][$Proj->getFirstEventIdInArmByEventId($event_id)][$Proj->firstForm]['id'] . '",';
					}
					// Render row
					$data_csv .= DataExport::render_row($this_row_answers);
					$data_csv_labels .= DataExport::render_row($this_row_answers_labels);
					// Set default answers for next row of data (specific for current event_id)
					$nextRowEventId = ($is_child ? $Proj->firstEventId : $row['event_id']);
					$this_row_answers = $field_defaults_events[$nextRowEventId];
					$this_row_answers_labels = $field_defaults_labels_events[$nextRowEventId];
				}
				$id++;
			}
			// Set values for next loop
			$record_id = $row['record'];
			$event_id  = $is_child ? $Proj->firstEventId : $row['event_id'];
			// Output to array for this row of data. Format if a text field.
			$this_field_type = ($row['field_name'] == '__GROUPID__') ? 'group_id' : $field_type[$row['field_name']];
			switch ($this_field_type)
			{
				// DAG group_id
				case "group_id":
					$group_id = $row['value'];
					if (isset($dags[$group_id])) {
						$recordDags[$record_id] = $group_id;
					}
					break;
				// Text/notes field
				case "textarea":
				case "text":
					// Replace characters that were converted during post (they will have ampersand in them)
					if (strpos($row['value'], "&") !== false) {
						$row['value'] = html_entity_decode($row['value'], ENT_QUOTES);
					}
					//Replace double quotes with single quotes
					$row['value'] = str_replace("\"", "'", $row['value']);
					//Replace line breaks with two spaces
					$row['value'] = str_replace("\r\n", "  ", $row['value']);
					// Save this answer in array
					switch ($field_val_type[$row['field_name']])
					{
						// Numbers do not need quotes around them
						case "float":
						case "int":
							if (trim($row['data_conversion']) != "") {
								$this_row_answers[$row['field_name']] = DataExport::evalDataConversion($row['field_name'], $field_type[$row['field_name']], $row['value'], $row['data_conversion']). ',';
							} else {
								$this_row_answers[$row['field_name']] = $row['value'] . ',';
							}
							$this_row_answers_labels[$row['field_name']] = $row['value'] . ',';
							break;
						//Reformat dates from YYYY-MM-DD format to MM/DD/YYYY format
						case "date":
						case "date_ymd":
						case "date_mdy":
						case "date_dmy":
							// Render date
							$dformat = "";
							if($useStandardCodeDataConversion && trim($row['data_conversion']) != "") {
								$dformat = trim($row['data_conversion']);
							}
							// Don't do date shifting
							if (!$do_date_shift) {

								$this_row_answers_labels[$row['field_name']] = '"' . $row['value'] . '",';
								if ($dformat == "") {
									$this_row_answers[$row['field_name']] = '"' . $row['value'] . '",';;
								}
								// else {
								// $this_row_answers[$row['field_name']] = '"' . DateTimeRC::format_date($row['value'], $dformat) . '",';
								// }
								//Do date shifting
							} else {
								$this_row_answers[$row['field_name']] = '"' . Records::shift_date_format($row['value'], $days_to_shift) . '",';
								$this_row_answers_labels[$row['field_name']] = '"' . Records::shift_date_format($row['value'], $days_to_shift) . '",';
							}
							break;
						//Reformat datetimes from YYYY-MM-DD format to MM/DD/YYYY format
						case "datetime":
						case "datetime_ymd":
						case "datetime_mdy":
						case "datetime_dmy":
						case "datetime_seconds":
						case "datetime_seconds_ymd":
						case "datetime_seconds_mdy":
						case "datetime_seconds_dmy":
							if (trim($row['value']) != '')
							{
								// Don't do date shifting
								if (!$do_date_shift) {
									$this_row_answers[$row['field_name']] = $this_row_answers_labels[$row['field_name']] = '"' . $row['value'] . '",';
									// Do date shifting
								} else {
									$this_row_answers[$row['field_name']] = $this_row_answers_labels[$row['field_name']] = '"' . Records::shift_date_format($row['value'], $days_to_shift) . '",';
								}
							}
							break;
						case "time":
							//Render time
							// Do labels first before data conversion is applied, if applied
							$this_row_answers_labels[$row['field_name']] = '"' . $row['value'] . '",';
							if ($useStandardCodeDataConversion && trim($row['data_conversion']) != "") {
								$dformat = trim($row['data_conversion']);
								$row['value'] = DateTimeRC::format_time($row['value'], $dformat);
							}
							$this_row_answers[$row['field_name']] = '"' . $row['value'] . '",';
							break;
						//Put quotes around normal text strings
						default:
							$this_row_answers[$row['field_name']] = $this_row_answers_labels[$row['field_name']] = '"' . trim($row['value']) . '",';
					}
					break;
				case "checkbox":
					// Make sure that the data value exists as a coded value for the checkbox. If so, export as 1 (for checked).
					if (isset($this_row_answers[$row['field_name']][$row['value']])) {
						if ($useStandardCodeDataConversion) {
							$this_row_answers[$row['field_name']][$row['value']] = DataExport::evalCheckboxDataConversion(1, $row['data_conversion2']).',';
						} else {
							$this_row_answers[$row['field_name']][$row['value']] = '1,';
						}
						$this_row_answers_labels[$row['field_name']][$row['value']] = '"Checked",';
					}
					break;
				case "file":
					$this_row_answers[$row['field_name']] = $this_row_answers_labels[$row['field_name']] = '"[document]",';
					break;
				default:
					// For multiple choice questions (excluding checkboxes), add choice labels to answers_labels
					if (in_array($this_field_type, $mc_field_types)) {
						//
						// Get multiple choice option label
						$this_mc_label = $mc_choices[$row['field_name']][$row['value']];
						// Render MC option label
						$this_row_answers_labels[$row['field_name']] = '"' . $this_mc_label . '",';
					} else {
						if (!is_numeric($row['value'])) {
							$this_row_answers_labels[$row['field_name']] = '"' . $row['value'] . '"' . ',';
						} else {
							$this_row_answers_labels[$row['field_name']] = $row['value'] . ',';
						}
					}
					if (!is_numeric($row['value'])) {
						$row['value'] = '"' . $row['value'] . '"';
					}
					// Standards Mapping data conversion
					if (strpos($row['data_conversion'], "&") !== false) {
						//quotes can be inserted into data conversion for select and radio types
						$row['data_conversion'] = html_entity_decode($row['data_conversion'], ENT_QUOTES);
					}
					//Save this answer in array
					if (trim($row['data_conversion']) != "") {
						$this_row_answers[$row['field_name']] = DataExport::evalDataConversion($row['field_name'], $field_type[$row['field_name']], $row['value'], $row['data_conversion']) . ',';
					} else {
						$this_row_answers[$row['field_name']] = $row['value'] . ',';
					}
			}
		}
		//Render the last row's answers
		if (db_num_rows($q) > 0)
		{
			// HASH ID: If the record id is an Identifier and the user has de-id rights access, do an MD5 hash on the record id
			// Also, make sure record name field is not blank (can somehow happen - due to old bug?) by manually setting it here using $record_id.
			if ((($user_rights['data_export_tool'] != '1' && in_array($table_pk, $field_phi)) || $do_hash)) {
				$this_row_answers[$table_pk] = $this_row_answers_labels[$table_pk] = md5($salt . $record_id . $__SALT__) . ',';
			} else {
				$this_row_answers[$table_pk] = $this_row_answers_labels[$table_pk] = '"' . $record_id . '",';
			}
			//If Events exist, add Event Name
			if ($longitudinal) {
				$this_row_answers['redcap_event_name'] = '"' . $event_names[$event_id] . '",';
				$this_row_answers_labels['redcap_event_name'] = '"' . str_replace("\"", "'", $event_labels[$event_id]) . '",';
			}
			// If DAGs exist, add unique DAG name
			if ($exportDags) {
				$this_row_answers['redcap_data_access_group'] = '"' . $dags[$recordDags[$record_id]] . '",';
				$this_row_answers_labels['redcap_data_access_group'] = '"' . str_replace("\"", "'", $dags_labels[$recordDags[$record_id]]) . '",';
			}
			// If we're requesting the return codes, add them here
			if ($getReturnCodes && isset($returnCodes[$record_id][$event_id])) {
				foreach ($returnCodes[$record_id][$event_id] as $this_form=>$this_return_code) {
					if (isset($this_row_answers[$this_form.'_return_code'])) {
						$this_row_answers[$this_form.'_return_code'] = $this_row_answers_labels[$this_form.'_return_code']  = '"' . $this_return_code . '",';
					}
				}
			}
			// If project has any surveys, add the survey completion timestamp
			if ($exportSurveyFields && isset($timestamp_identifiers[$record_id][$event_id])) {
				//Get date shifted day amount for this record
				if ($do_surveytimestamp_shift) {
					$days_to_shift_survey_ts = Records::get_shift_days($record_id, $date_shift_max, $__SALT__);
				}
				// Add the survey completion timestamp for each survey
				foreach ($timestamp_identifiers[$record_id][$event_id] as $this_form=>$attr) {
					if (isset($this_row_answers[$this_form.'_timestamp'])) {
						// If user set option to date-shift the survey timestamp, then shift it
						if ($do_surveytimestamp_shift) {
							$attr['ts'] = Records::shift_date_format($attr['ts'], $days_to_shift_survey_ts);
						}
						// Add timestamp to arrays
						$this_row_answers[$this_form.'_timestamp'] = $this_row_answers_labels[$this_form.'_timestamp'] = '"' . $attr['ts'] . '",';
					}
				}
			}
			// If project has any surveys, add the identifier (if exists)
			if ($exportSurveyFields && !$do_remove_identifiers && isset($timestamp_identifiers[$record_id][$Proj->getFirstEventIdInArmByEventId($event_id)][$Proj->firstForm])) {
				$this_row_answers['redcap_survey_identifier'] = $this_row_answers_labels['redcap_survey_identifier'] = '"' . $timestamp_identifiers[$record_id][$Proj->getFirstEventIdInArmByEventId($event_id)][$Proj->firstForm]['id'] . '",';
			}
			// Render last row
			$data_csv .= DataExport::render_row($this_row_answers);
			$data_csv_labels .= DataExport::render_row($this_row_answers_labels);
		}

		return array($headers, $headers_labels, $data_csv, $data_csv_labels, $field_names);
	}

	//Function for rendering each row of data after collecting in array
	public static function render_row($this_row_answers) {
		$this_line = "";
		foreach ($this_row_answers as $this_answer) {
			if (!is_array($this_answer)) {
				$this_line .= $this_answer;
			} else {
				//Loop through Checkbox choices
				foreach ($this_answer as $chkbox_choice) {
					$this_line .= $chkbox_choice;
				}
			}
		}
		return substr($this_line,0,-1) . "\n";
	}

	public static function evalDataConversion($field_name, $field_type, $field_value, $formula)
    {
		$retVal = "";
		global $is_data_conversion_error;
		global $is_data_conversion_error_msg;
		global $useStandardCodeDataConversion;
		if($useStandardCodeDataConversion && trim($formula) != "") {
			if(($field_type == 'text' || $field_type == 'calc') && is_numeric($field_value)) {
				$actualFormula = str_replace("[".$field_name."]",$field_value,$formula);

				if(preg_match('/[\[]\$]/',$actualFormula) == 0) {
					//eval('$result = '.$actualFormula.';');
					if(is_numeric($result)) {
						$retVal = $result;
					}else {
						$is_data_conversion_error = true;
						$is_data_conversion_error_msg .= "<p>The following data conversion formula produced an invalid result";
					}
				}else {
					$is_data_conversion_error = true;
					$is_data_conversion_error_msg .= "<p>The following data conversion formula contains invalid characters and cannot be executed";
				}
				if($is_data_conversion_error) {
					$is_data_conversion_error_msg .= "<br/>field:&nbsp;&nbsp;$field_name";
					$is_data_conversion_error_msg .= "<br/>value:&nbsp;&nbsp;$field_value";
					$is_data_conversion_error_msg .= "<br/>formula:&nbsp;&nbsp;$formula";
					$is_data_conversion_error_msg .= "<br/>operation:&nbsp;&nbsp;$actualFormula";
				}
			}else if($field_type == 'select' || $field_type == 'radio' || $field_type == 'checkbox') {
				$formulaArray = explode("\\n",$formula);
				$found = false;
				foreach($formulaArray as $enum) {
					$equalPosition = strpos($enum,'=');
					if($equalPosition !== false) {
						$checkVal = substr($enum,0,$equalPosition);
						if($checkVal == $field_value) {
							$retVal = substr($enum,$equalPosition+1);
							$found = true;
						}
					}
					if($found) {
						break;
					}
				}
			}
			if($is_data_conversion_error) {
				$retVal = "!error";
			}
		}else {
			$retVal = $field_value;
		}

		return $retVal;
	}

	public static function evalCheckboxDataConversion($field_value, $formula)
    {
		$retVal = $field_value;
		$arr = explode("\\n",$formula);
		if($field_value == 1 && strpos($arr[0],'checked=') == 0) {
			$retVal = substr($arr[0], strpos($arr[0],'=')+1);
		}else if($field_value == 0 && strpos($arr[1],'unchecked=') == 0) {
			$retVal = substr($arr[1], strpos($arr[1],'=')+1);
		}
		return $retVal;
	}
}
