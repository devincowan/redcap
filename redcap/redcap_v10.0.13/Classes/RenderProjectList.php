<?php

/**
 * RENDER PROJECT LIST
 * Display all REDCap projects in table format
 */
class RenderProjectList
{
	static function getTRData($project, $dts_rights=array())
	{
		global  $display_nonauth_projects, $auth_meth_global, $dts_enabled_global, $google_translate_enabled,
				$isIE, $lang, $rc_connection, $realtime_webservice_global_enabled, $two_factor_auth_enabled;
		
		// Are we viewing the list from the Control Center?
		$isControlCenter = (strpos(PAGE_FULL, "/ControlCenter/") !== false);

		$all_proj_ids = array();

		// Store project_id in array to use in AJAX call on pageload
		$all_proj_ids[] = $project['project_id'];

		//Determine if we need to show if a production project's drafted changes are in review
		$in_review = '';
		if($project['draft_mode'] == '2')
		{
			$in_review = "<br><span class='aGridsub' onclick=\"window.location.href='" . APP_PATH_WEBROOT . "Design/project_modifications.php?pid={$project['project_id']}';return false;\">({$lang['control_center_104']})</span>";
		}

		//Determine if we need to show Super User functionality (edit db, delete db)
		$settings_link = '';
		$edit_project_settings_url = APP_PATH_WEBROOT . 'ControlCenter/edit_project.php?project=' . $project['project_id'];
		if ($isControlCenter)
		{
			$settings_link = '<div class="aGridsub" style="padding-left:5px;margin-top:-5px;"><a style="color:#000;font-family:Tahoma;font-size:10px;" href="' . $edit_project_settings_url . '">' . $lang['control_center_106'] . '</a> | <a style="font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="revHist(' . $project['project_id'] . ')">' . $lang['app_18'] . '</a> | ' . ($project['date_deleted'] == '' ? '<a style="color:#800000;font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="delete_project(' . $project['project_id'] . ',this)">' . $lang['control_center_105'] . '</a>'	: '<a style="color:green;font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="undelete_project(' . $project['project_id'] . ',this)">' . $lang['control_center_375'] . '</a><br /><img src="' . APP_PATH_IMAGES . 'bullet_delete.png"><span style="color:red;">' . $lang['control_center_380'] . ' ' . DateTimeRC::format_ts_from_ymd(date('Y-m-d H:i:s', strtotime($project['date_deleted']) + 3600 * 24 * Project::DELETE_PROJECT_DAY_LAG)) . '</span><br /><span style="color:#666;margin:0 3px 0 12px;">' . $lang['global_46'] . '</span><a style="text-decoration:underline;color:red;font-family:Tahoma;font-size:10px;" href="javascript:;" onclick="delete_project(' . $project['project_id'] . ',this,1,1,1)">' . $lang['control_center_381'] . '</a>') . '</div>';
		}

		// Project Templates: Build array of all templates so we can put a star by their title for super users only
		$templates = (defined('SUPER_USER') && SUPER_USER) ? ProjectTemplates::getTemplateList() : array();

		// DTS Adjudication notification (only on myProjects page)
		$dtsLink = '';

		// Determine if DTS is enabled globally and also for this user on this project
		if ($dts_enabled_global && isset($dts_rights[$project['project_id']]))
		{
			// Instantiate new DtsController object
			$dts = new DtsController();
			// Get count of items that needed adjudication
			$recommendationCount = $dts->getPendingCountByProjectId($project['project_id']);
			// Render a link if items exist
			if($recommendationCount > 0)
			{
				$dtsLink = '<div class="aGridsub" style="padding:0 5px;text-align:right;"><a title="' . $lang['home_28'] . '" href="' . APP_PATH_WEBROOT . 'index.php?pid=' . $project['project_id'] . '&route=DtsController:adjudication" style="text-decoration:underline;color:green; font-family:Tahoma; font-size:10px;"><img src="' . APP_PATH_IMAGES . 'tick_small_circle.png">' . $lang['home_28'] . '</a></div>';
			}
			else
			{
				$dtsLink = '<div class="aGridsub" style="color:#aaa;padding:0 5px;text-align:right;">' . $lang['home_29'] . '</div>';
			}
		}
		
		// If project is a template, then display a star next to title (for super users only)
		$templateIcon = (isset($templates[$project['project_id']]))	? ($templates[$project['project_id']]['enabled'] ? RCView::img(array('src'=>'star_small2.png', 'style'=>'margin-left:5px;')) : RCView::img(array('src'=>'star_small_empty2.png','style'=>'margin-left:5px;'))) : '';
		
		// Project Notes: If has some text in its project notes, then display icon to mouse over to display the notes
		$project_note = '';

		if ($project['project_note'] != '')
		{
			$project_note_info = htmlspecialchars($project['project_note'], ENT_QUOTES);
			$project_note = "<span class='aGridsub tooltip'><i class=\"fs11 far fa-sticky-note pnpimg\" style='color:#000088;' onclick='return false;'></i><span class='tooltiptext'>$project_note_info</span></span>";
		}

		// Exempt from Two Factor authentication
		$twoFactorExemptIcon = '';

		if ($isControlCenter && $project['two_factor_exempt_project'] && $two_factor_auth_enabled)
		{
			$twoFactorExemptIcon = '<img style="margin-left:5px;" src="' . APP_PATH_IMAGES . 'smartphone_key.png" title="' . js_escape2($lang['system_config_512']) . '"><img style="vertical-align:middle;position:relative;left:-7px;margin-right:-7px;" src="' . APP_PATH_IMAGES . 'cross.png" title="' . js_escape2($lang['system_config_512']) . '">';
		}

		// Is project marked as Completed?
		$completedNote = ($project['completed_time'] != '') ? " <span class='browseProjPid fs12 text-danger'>{$lang['edit_project_207']}</span>" : "";
		
		// Display the PID number in the Control Center's Browse Projects page
		
		$pidNum = ($isControlCenter || !SUPER_USER) ? "{$project['project_id']}" : "<a class=\"fs12\" href=\"{$edit_project_settings_url}\">{$project["project_id"]}</a>";
		
		// Note if the project is offline
		$offline_msg = $project['online'] ? "" : " <span class='browseProjPid' style='font-size:11px;'>{$lang['project_settings_04']}</span>";
		
		// Title as link
		if ($project['status'] < 1 && $project['design_rights'])
		{
			// Send to setup page if in development still
			$title = '<div class="projtitle' . '"' . '><a href="' . APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . $project['project_id'] . '" class="aGrid">' . $project['app_title'] . $offline_msg . $completedNote . $templateIcon . $project_note . $twoFactorExemptIcon . $in_review . $settings_link . $dtsLink . '</a></div>';
		}
		else
		{
			$title = '<div class="projtitle' . '"' . '><a href="' . APP_PATH_WEBROOT . 'index.php?pid=' . $project['project_id'] . '" class="aGrid">' . $project['app_title'] . $offline_msg . $completedNote . $templateIcon . $project_note . $twoFactorExemptIcon . $in_review . $settings_link . $dtsLink . '</a></div>';
		}

		// Status
		if ($project['date_deleted'] != '')
		{
			// If project is "deleted", display cross icon
			$iconstatus = '<span class="fas fa-times" style="color:#C00000;font-size:14px;" aria-hidden="true" title="' . js_escape2($lang['global_106']) . '"></span>';
		}
		elseif ($project['completed_time'] != '')
        {
            // If project is marked as "completed"
			$iconstatus = '<span class="fa fa-archive fs11" style="color:#C00000;font-size:14px;" aria-hidden="true" title="' . js_escape2($lang['edit_project_207']) . '"></span>';
        }
		else
		{
			// If typical project, display icon based upon status value
			switch($project['status'])
			{
                case 0: // Development
                    $iconstatus = '<span class="fas fa-wrench" style="color:#444;font-size:14px;" aria-hidden="true" title="' . js_escape2($lang['global_29']) . '"></span>';
                    break;
                case 1: // Production
                    $iconstatus = '<span class="far fa-check-square" style="color:#00A000;font-size:14px;" aria-hidden="true" title="' . js_escape2($lang['global_30']) . '"></span>';
                    break;
                case 2: // Inactive
                    $iconstatus = '<span class="fas fa-minus-circle" style="color:#A00000;font-size:14px;" aria-hidden="true" title="' . js_escape2($lang['global_159']) . '"></span>';
                    break;
                }
		}

		// Project type (classic or longitudinal)
		$icontype = ($project['longitudinal']) ? '<i class="fas fa-layer-group opacity75" title="'.js_escape2($lang['create_project_51']).'"></i>' : '<i class="fas fa-square opacity75" style="font-size:10px;" title="'.js_escape2($lang['create_project_49']).'"></i>';

		// Append $iconstatus with an invisible span containing the value (for ability to sort)
		$icontype .= RCView::span(array('class'=>'hidden'), $project['longitudinal']);

		$fieldsCount = RCView::a(array( // Link to Online Designer or Codebook (depending on user rights)
			"href" => APP_PATH_WEBROOT . ($project['design_rights'] ? "Design/online_designer.php" : "Design/data_dictionary_codebook.php") . "?pid={$project['project_id']}",
		), "<span class='pid-cntf-{$project['project_id']}'><span class='pid-cnt'>{$lang['data_entry_64']}</span></span>");
		$recordsCount = RCView::a(array( // Link to Record Status Dashboard
			"href" => APP_PATH_WEBROOT . "DataEntry/record_status_dashboard.php?pid={$project['project_id']}",
		), "<span class='pid-cntr-{$project['project_id']}'><span class='pid-cnt'>{$lang['data_entry_64']}</span></span>");
		$instrumentsCount = "<span class='pid-cnti-{$project['project_id']}'><span class='pid-cnt'>{$lang['data_entry_64']}</span></span>";

		$row_data = array ( $title );
		if (SUPER_USER) array_push($row_data, $pidNum);
		array_push($row_data, 
			$recordsCount, $fieldsCount, $instrumentsCount, $icontype, $iconstatus
		);

		return array($row_data, $all_proj_ids);
	}

	// $opacity should be 0-1
	static function projectBGStyle($color, $opacity)
	{
		// get decimal values
		$r = hexdec($color[0] . $color[1]);
		$g = hexdec($color[2] . $color[3]);
		$b = hexdec($color[4] . $color[5]);

		// ms opacity must be in range 0-255
		$ms_opacity = dechex(255 * $opacity);

		// special ms format with alpha value first
		$ms_color = $ms_opacity . sprintf('%02X%02X%02X', $r, $g, $b);

		// specific styles and ordering for ie8 on win7
		return "background-color:rgb($r, $g, $b); background:transparent\9; background-color:rgba($r, $g, $b, $opacity); -ms-filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#$ms_color, endColorstr=#$ms_color); filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#$ms_color, endColorstr=#$ms_color);";
	}

	// $diff should be 0-1
	static function bgColor($color, $diff)
	{
		// get decimal values
		$r = hexdec($color[0] . $color[1]);
		$g = hexdec($color[2] . $color[3]);
		$b = hexdec($color[4] . $color[5]);

		// calculate new color values
		$r *= $diff;
		$g *= $diff;
		$b *= $diff;

		// ship it back in hex
		return sprintf('%02X%02X%02X', $r, $g, $b);
	}

	static function bgGradientStyles($bg1, $bg2)
	{
		return "background:-webkit-linear-gradient(#$bg1, #$bg2); background:-o-linear-gradient(#$bg1, #$bg2); background:-moz-linear-gradient(#$bg1, #$bg2); background:linear-gradient(#$bg1, #$bg2); filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#$bg1, endColorstr=#$bg2); -ms-filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#$bg1, endColorstr=#$bg2);";
	}

	static function getTRs($projects, $dts_rights)
	{
		$row_data = array();
		$all_proj_ids = array();

		// projects are in folders
		if(ProjectFolders::inFolders($projects))
		{
			foreach($projects as $folder_id => $f)
			{
				$bg2 = RenderProjectList::bgColor($f['background'], ProjectFolders::FOLDER_GRADIENT);
				$background = RenderProjectList::bgGradientStyles($f['background'], $bg2);
				$num_projects_folder = (count($f['projects'])-(isset($_GET['show_completed']) ? 0 : $f['archived']));
				if ($num_projects_folder == 0) continue;

				$row_data[] = array(
					$folder_id,
					$f['name'] . RCView::span(array('class'=>'fldcntnum opacity65'), '(' . $num_projects_folder . ')'),
					$f['collapsed'],
					array($f['foreground'], $background, $f['background'])
				);

				foreach($f['projects'] as $p)
				{
					// If project is completed, do not show it unless the "Show Completed" link was clicked
					if ($p['completed_time'] != '' && !isset($_GET['show_completed']))
					{
						continue;
					}

					list($data, $ids) = RenderProjectList::getTRData($p, $dts_rights);

					$row_data[] = $data;

					foreach($ids as $id)
					{
						$all_proj_ids[] = $id;
					}
				}
			}

			return array($row_data, $all_proj_ids);
		}

		// projects are not in folders
		foreach($projects as $p)
		{
			// If project is archived, do not show it unless the "Show Archived" link was clicked
			if($p['hidden'] == '1' && !isset($_GET['show_completed']))
			{
				continue;
			}

			list($data, $ids) = RenderProjectList::getTRData($p, array());

			$row_data[] = $data;

			foreach($ids as $id)
			{
				$all_proj_ids[] = $id;
			}
		}

		return array($row_data, $all_proj_ids);
	}


	// Display the project list
	function renderprojects($section = "")
	{
		global  $display_nonauth_projects, $auth_meth_global, $dts_enabled_global, $google_translate_enabled,
				$isIE, $lang, $rc_connection, $realtime_webservice_global_enabled, $two_factor_auth_enabled;

		// Reset session flag for Folder checkbox to hide already-assigned projects
		unset($_SESSION['hide_assigned']);
		// Place all project info into array
		$proj = array();
		// Are we viewing the list from the Control Center?
		$isControlCenter = (strpos(PAGE_FULL, "/ControlCenter/") !== false);

		// First get projects list from User Info and User Rights tables
		if ($isControlCenter && isset($_GET['title']) && $_GET['title'] != "") {
			// Search title by keyword			
			$likes = $scores = $scoresCumul = $keywordsCumul = array();
			$keywords = explode(" ", $_GET['title']);
			foreach ($keywords as &$thisKeyword) {
				$thisKeyword = trim($thisKeyword);
				$keywordsCumul[] = $thisKeyword;
				$likes[] = "p.app_title like '%".db_escape($thisKeyword)."%'";
				$scores[] = "if(locate('".db_escape($thisKeyword)."',p.app_title)=0,0,1)";
				$keywordsCumulCount = count($keywordsCumul);
				if ($keywordsCumulCount > 1) {
					$scoresCumul[] = "if(locate('".db_escape(implode(" ", $keywordsCumul))."',p.app_title)=0,0,".($keywordsCumulCount*10).")";					
				}
			}
			$scoreExact = "if(locate('".db_escape(implode(" ", $keywords))."',p.app_title)=0,0,1000)";
			$scorePartial = empty($scoresCumul) ? "0" : implode("+", $scoresCumul);
			$scoreIndKeyword = implode("+", $scores);
			$score = "($scoreExact + $scorePartial + $scoreIndKeyword)";
			$like = "(" . implode(" or ", $likes) . ")";
			$tables = (isset($_GET['userid']) && $_GET['userid'] != "") ? "redcap_user_rights u, redcap_projects p" : "redcap_projects p";
			$joins = (isset($_GET['userid']) && $_GET['userid'] != "") ? "and u.project_id = p.project_id and u.username = '".db_escape($_GET['userid'])."'" : "";
			$sql = "select $score as score, p.online_offline, p.project_id, p.two_factor_exempt_project, p.project_note, p.project_name, 
					p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms, p.completed_time
					from $tables where $like $joins order by score desc, p.project_id";
		// Get projects list from User Info and User Rights tables
		} elseif ($isControlCenter && isset($_GET['userid']) && $_GET['userid'] != "") {
			// Show just one user's (not current user, since we are super user in Control Center)
			$sql = "select p.online_offline, p.project_id, p.two_factor_exempt_project, p.project_note, p.project_name, 
					p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms, p.completed_time
					from redcap_user_rights u, redcap_projects p
					where u.project_id = p.project_id and u.username = '".db_escape($_GET['userid'])."' order by p.project_id";
		} elseif ($isControlCenter && isset($_GET['view_all'])) {
			// Show all projects
			$sql = "select p.online_offline, p.project_id, p.two_factor_exempt_project, p.project_note, p.project_name, 
					p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms, p.completed_time
					from redcap_projects p order by p.project_id";
		} elseif ($isControlCenter && (!isset($_GET['userid']) || $_GET['userid'] == "")) {
			// Show no projects (default)
			$sql = "select 1 from redcap_projects limit 0";
		} else {
			// Show current user's (ignore "deleted" projects)
			$sql = "select p.online_offline, p.project_id, p.two_factor_exempt_project, p.project_note, p.project_name, p.completed_time,
					p.app_title, p.status, p.draft_mode, p.surveys_enabled, p.date_deleted, p.repeatforms, u.design, if(h.project_id is null,0,1) as hidden
					from redcap_user_rights u, redcap_projects p
                    left join redcap_projects_user_hidden h on p.project_id = h.project_id and h.ui_id = '" . db_escape(defined('UI_ID') ? UI_ID : '') . "'
					where u.project_id = p.project_id and u.username = '" . db_escape(defined('USERID') ? USERID : '') . "'
					and p.date_deleted is null order by p.project_id";
		}

		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$proj[$row['project_name']]['project_id'] = $row['project_id'];
			$proj[$row['project_name']]['project_note'] = strip_tags(str_replace(array("\r\n","\r","\n","\t"), array(" "," "," "," "), br2nl(label_decode(trim($row['project_note'])))));
			if (strlen($proj[$row['project_name']]['project_note']) > 126) {
				$proj[$row['project_name']]['project_note'] = substr($proj[$row['project_name']]['project_note'], 0, 124)."...";
			}
			$proj[$row['project_name']]['longitudinal'] = $row['repeatforms'];
			$proj[$row['project_name']]['status'] = $row['status'];
			$proj[$row['project_name']]['two_factor_exempt_project'] = $row['two_factor_exempt_project'];
			$proj[$row['project_name']]['date_deleted'] = $row['date_deleted'];
			$proj[$row['project_name']]['draft_mode'] = $row['draft_mode'];
			$proj[$row['project_name']]['surveys_enabled'] = $row['surveys_enabled'];
			$proj[$row['project_name']]['completed_time'] = $row['completed_time'];
			$proj[$row['project_name']]['app_title'] = strip_tags(str_replace(array("<br>","<br/>","<br />"), array(" "," "," "), html_entity_decode($row['app_title'], ENT_QUOTES)));
			if (isset($_GET['no_counts'])) {
				$proj[$row['project_name']]['count'] = "";
				$proj[$row['project_name']]['field_num'] = "";
			} else {
				$proj[$row['project_name']]['count'] = 0;
				$proj[$row['project_name']]['field_num'] = 0;
			}
			$proj[$row['project_name']]['online'] = ($row['online_offline'] == '1');
			$proj[$row['project_name']]['design_rights'] = (SUPER_USER || (isset($row['design']) && $row['design'] == '1')) ? '1' : '0';
			// Get user level "hidden" status
			$proj[$row['project_name']]['hidden'] = (isset($row['hidden']) ? $row['hidden'] : 0);
		}

		if (!$isControlCenter || ($isControlCenter && isset($_GET['userid']) && $_GET['userid'] != "" && (!isset($_GET['title']) || $_GET['title'] == "")))
		{
			$userid = isset($_GET['userid']) ? $_GET['userid'] : USERID;
			$proj = ProjectFolders::projectsInFolders(User::getUserInfo($userid), $proj);
		}

		// Add count of archived projects in each folder
//		foreach ($proj as $this_folder_id=>$attr) {
//			$this_folder_archived = 0;
//			foreach ($attr['projects'] as $this_app_name=>$pattr) {
//				if ($pattr['status'] == '3') $this_folder_archived++;
//			}
//			$proj[$this_folder_id]['archived'] = $this_folder_archived;
//		}
		
		$proj_ids = ProjectFolders::projectIDs($proj);
		$proj_ids_list = count($proj_ids) ? implode(',', $proj_ids) : 0;
		
		$suppressProjectFolders = ($isControlCenter && isset($_GET['userid']) && $_GET['userid'] != "" && isset($_GET['title']) && $_GET['title'] != "");


		## DTS: If enabled globally, build list of projects to check to see if adjudication is needed
		$dts_rights = array();
		if ($dts_enabled_global)
		{
			// Get projects with DTS enabled
			if (!$isControlCenter) {
				// Where normal user has DTS rights
				$sql = "select p.project_id from redcap_user_rights u, redcap_projects p where u.username = '" . db_escape(USERID) . "' and
						p.project_id = u.project_id and p.dts_enabled = 1 and
						p.project_id in ($proj_ids_list)";
				// Don't query using DTS user rights on project if a super user because they might not have those rights in
				// the user_rights table, although once they access the project, they are automatically given those rights
				// because super users get maximum rights for everything once they're inside a project.
				if (!SUPER_USER) {
					$sql .= " and u.dts = 1";
				}
			} else {
				// Super user in Control Center
				$sql = "select project_id from redcap_projects where dts_enabled = 1
						and project_id in ($proj_ids_list)";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				$dts_rights[$row['project_id']] = true;
			}
		}

		list($row_data, $all_proj_ids) = RenderProjectList::getTRs($proj, $dts_rights);

		// If user has access to zero projects
		$filter_projects_style = '';
		if (empty($row_data)) {
			$empty_row = array($isControlCenter ? $lang['home_37'] : $lang['home_38'], "", "", "", "", "");
			if ($isControlCenter || SUPER_USER) {
				$empty_row[] = "";
			}
			$row_data[] = $empty_row;
			// Hide the "filter projects" if no projects are showing
			$filter_projects_style = 'visibility:hidden;';
		}

		// Set table title name
		$tableHeader = $isControlCenter ? RCView::tt("control_center_134") : RCView::tt("home_22");
		$organizeBtn = $isControlCenter ? "" :
						RCView::button(array(
								'onclick' => 'organizeProjects();',
								'class'   => 'btn btn-defaultrc btn-xs',
								'style'   => 'margin-left:50px;color:#008000;'
							),
							RCView::fa('fas fa-folder-open') . " " .
							RCView::tt("control_center_4516")
						);
		$collapseAllBtn = $isControlCenter ? "" :
						RCView::button(array(
								"onclick" => "toggleFolderCollapse('all');",
								"class" => "btn btn-defaultrc btn-xs",
							),
							RCView::fa("fas fa-folder-minus") . " " .
							RCView::tt("home_64")
						);

		// Set "My Projects" column header's project search input
		$searchProjTextboxJsFocus = "if ($(this).val() == '".js_escape($lang['control_center_440'])."') {
									$(this).val(''); $(this).css('color','#000');
								  }";
		$searchProjTextboxJsBlur = "$(this).val( trim($(this).val()) );
								  if ($(this).val() == '') {
									$(this).val('".js_escape($lang['control_center_440'])."'); $(this).css('color','#999');
								  }; persistProjectSearch();";
		// Project search persistence
		$projectSearchPersist = $isControlCenter ? false : UIState::getUIStateValue(null, "my_projects", "search_persist") == 1;
		$projectSearchContent = $isControlCenter ? null : UIState::getUIStateValue(null, "my_projects", "search_string");
		$projectSearchContent = $projectSearchPersist && !empty($projectSearchContent) ? $projectSearchContent : RCView::tt("control_center_440", null);
		$projectSearchPersistOn = RCView::fa("fas fa-save fa-stack-1x", "top:3px;");
		$projectSearchPersistOff = RCView::fa("fas fa-save fa-stack-1x", "top:3px;") . RCView::fa("fas fa-slash fa-stack-1x", "top:3px;color:red;");
		$tableTitle = 	RCView::div(array('style'=>''),
							RCView::div(array('style'=>'font-size:13px;float:left;margin:2px 0 0 3px;'),
								$tableHeader . $organizeBtn . $collapseAllBtn
							) .
							// Note that the selected user's project folders are not being displayed
							(!$suppressProjectFolders ? '' :
								RCView::div(array('class'=>'wrap', 'style'=>'line-height:12px;font-weight:normal;color:green;font-size:11px;float:left;width:220px;margin:2px 0 0 25px;'),
									$lang['control_center_4651']
								)
							) . 
							// Search text box
							RCView::div(array('style'=>'float:right;margin:0 10px 0 0;'),
								// Search box
								RCView::text(array(
										'id' => 'proj_search',
										'class' => 'x-form-text x-form-field',
										'style' => 'width:180px;color:#999;font-size:13px;padding: 3px 5px;'.$filter_projects_style, 
										'value' => $projectSearchContent,
										'onfocus' => $searchProjTextboxJsFocus,
										'onblur' => $searchProjTextboxJsBlur
									)
								) .
								// Clear button
								RCView::button(array(
										"onclick" => "clearProjectSearch();",
										"class" => "btn btn-defaultrc btn-xs fa-stack fs11",
										"title" => $lang['dataqueries_86'],
										"style" => $filter_projects_style
									), 
									RCView::fa("fas fa-times fa-stack-1x", "top:3px;color:darkred;")
								) . ($isControlCenter ? "" :
								// Persistence toggle
								RCView::button(array(
										"id" => "persistProjectSearchToggle",
										"onclick" => "toggleProjectSearchPersist();",
										"class" => "btn btn-defaultrc btn-xs fa-stack fs11",
										"title" => $lang['global_161'],
										"style" => $filter_projects_style
									), $projectSearchPersist ? $projectSearchPersistOn : $projectSearchPersistOff
								))
							) .
							// Control Center only: Link to show Hidden projects
							(!($isControlCenter && !isset($_GET['show_completed']) && isset($_GET['userid'])) ? '' :
								RCView::div(array('style'=>'float:right;margin:3px 40px 0 0;'),
									RCView::a(array('style'=>'font-weight:normal;font-size:11px;color:#666;', 'href'=>$_SERVER['REQUEST_URI']."&show_completed"),
										'<span class="fa fa-archive" aria-hidden="true"></span> ' .$lang['edit_project_208'])
								)
							) .
							RCView::div(array('class'=>'clear'), '')
						);

		// Render table
		$width_pid = SUPER_USER ? 50 : 0; // PID
		$width_records = 52; // Records
		$width_fields = 48; // Fields
		$width_instr = 78; // Instruments
		$width_type = 38; // Type
		$width_status = 44; // Status
		$width_columns = $width_pid + $width_records + $width_fields + $width_instr + $width_type + $width_status;
		$width_title = ($isControlCenter ? 621 : 835) - $width_columns;
		$col_widths_headers[] = array($width_title, RCView::tt("home_30"));
		if (SUPER_USER) {
			$col_widths_headers[] = array($width_pid, RCView::tt("home_65"), "center", "int");
		}
		$col_widths_headers[] = array($width_records, RCView::tt("home_31"), "center", "int");
		$col_widths_headers[] = array($width_fields, RCView::tt("home_32"), "center", "int");
		$col_widths_headers[] = array($width_instr, RCView::tt("global_110"), "center", "int");
		$col_widths_headers[] = array($width_type, RCView::tt("home_39"), "center");
		$col_widths_headers[] = array($width_status, RCView::tt("home_33"), "center");

		// Build popup content
		$user = User::getUserInfo(USERID);



		$popup_content_left_td = RCView::td(array('style'=>'vertical-align:top'),
			RCView::div(array('class'=>'addFieldMatrixRowHdr', 'style'=>'width:400px; float:left;'),
				RCView::table(array('class'=>'form_border', 'style'=>'width:97%;'),
					RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'padding:0;background:#fff;border:0;'),
							RCView::div(array('style'=>'position:relative;top:13px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:8px 8px;'),
								$lang['folders_28']
							)
						)
					  ) .
					  RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'padding:5px;'),
							RCView::div(array('style'=>'color:#444;float:left;font-weight:normal;margin-top:10px;'), $lang['folders_12']) .
							RCView::div(array('style'=>'float:right;margin-top:7px;'),
								RCView::input(array(
									'placeholder' => $lang['folders_17'],
									'id'          => 'folderName',
									'type'        => 'text',
									'maxlength'   => 64,
									'class'	  => 'x-form-text x-form-field',
									'style'   => 'width:150px;',
									'onkeypress'  => 'return checkFolderNameSubmit(event);'
								)) . '&nbsp;' .
								RCView::button(array(
									'id'      => 'addFolder',
									'class'	  => 'jqbuttonmed',
									'style'   => 'border-color:#999;font-weight:bold;font-size:13px;',
									'onclick' => 'newFolder();'
								), $lang['folders_18'])
							)
						)
					  )
				) .
				// List of projects
				RCView::div(array('id'=>'folders', 'style'=>'width:97%; height:320px; overflow-x:auto;'), '&nbsp;')
			)
		);

		$checkbox_array = array('id'=>'hide_assigned', 'onclick'=>'hideAssigned();');
		if(isset($_SESSION['hide_assigned']) && $_SESSION['hide_assigned'] == 1)
		{
			$checkbox_array['checked'] = 'checked';
		}

		$popup_content_right_td = RCView::td(array('style'=>'vertical-align:top'),
			RCView::div(array('class'=>'addFieldMatrixRowHdr', 'style'=>'float:left; margin-left:25px;width:440px;'),
				RCView::table(array('class'=>'form_border', 'style'=>'width:97%;'),
					RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'padding:0;background:#fff;border:0;'),
							RCView::div(array('style'=>'position:relative;top:13px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:8px 8px;'),
								$lang['folders_27']
							)
						)
					  ) .
					  RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>3, 'style'=>'padding:5px;'),
							// Project drop-down list
							RCView::table(array(),
								RCView::tr(array(),
									RCView::td(array('style'=>'padding-right:15px;'), RCView::div(array('id'=>'select_folders'), '&nbsp;')) .
									RCView::td(array(), RCView::div(array("style" => "margin-top:6px;"), 
											RCView::text(array(
												"id" => "clear-assign-projects-filter",
												"placeholder" => RCView::tt("control_center_440", null),
												"onchange" => "applyAssignProjectsFilter();",
												"onkeyup" => "applyAssignProjectsFilter();",
											)) .
											RCView::button(array(
													"onclick" => "clearAssignProjectsFilter();",
													"class" => "btn btn-defaultrc btn-xs fs11",
													"style" => "margin-top:-3px;",
												), RCView::fa("fas fa-times", "color:darkred;")
											)
										)
									)
								) .
								RCView::tr(array(),
									RCView::td(array(),
										""
									) .
									RCView::td(array('class'=>'nowrap', 'style'=>'padding-top:3px'), 
										RCView::checkbox($checkbox_array) .
										RCView::span(array('style'=>'font-size:11px; color:#000;font-weight:normal;'), $lang['folders_19'])
									)
								)
							)
						)
					  )
				) .
				// List of projects
				RCView::div(array('id'=>'projects', 'style'=>'width:97%; height:320px; overflow-x:auto;'), '&nbsp;')
			)
		);

		$popup_content = RCView::div(array('style'=>''),
							$lang['folders_26'] . " " . RCView::b($lang['folders_32'])
						) .
						RCView::table(array(),
							RCView::tr(array(), $popup_content_left_td . $popup_content_right_td)
						);
		loadJS('Libraries/spectrum.js');
		loadJS('ProjectFolders.js');
		?>
		<style type="text/css">
		.pnpimg { vertical-align:middle;position:relative;top:-2px; }
		</style>
		<link rel='stylesheet' href='<?php echo APP_PATH_CSS ?>spectrum.css' />
		<link rel='stylesheet' href='<?php echo APP_PATH_CSS ?>project_list_tooltip.css' />
		<script type="text/javascript">
		// Project search persist
		var projectSearchPersist = <?php echo json_encode($projectSearchPersist); ?>;
		var projectSearchPersistOn = <?php echo json_encode($projectSearchPersistOn); ?>;
		var projectSearchPersistOff = <?php echo json_encode($projectSearchPersistOff); ?>;
		var projectSearchEmpty = <?php echo json_encode(RCView::tt("control_center_440", null)); ?>;
		var projectSearchContent = <?php echo json_encode($projectSearchContent); ?>;
		$(restoreProjectSearch);
		// Set var for all pid's listed on the page
		var visiblePids = '<?php echo implode(",", $all_proj_ids) ?>';
		var langDelFolder = '<?php echo js_escape($lang['folders_16']); ?>';
		var langProjFolder01 = '<?php echo js_escape($lang['folders_20']); ?>';
		var langProjFolder02 = '<?php echo js_escape($popup_content); ?>';
		var langProjFolder03 = '<?php echo js_escape($lang['folders_23']); ?>';
		var langProjFolder04 = '<?php echo js_escape($lang['global_53']); ?>';
		var langProjFolder05 = '<?php echo js_escape($lang['folders_14']); ?>';
		// Remove extraneous table rows
		function removeExtraRows() {
			// Remove project folder rows
			$('#table-proj_table tr[id^=fold]').remove();
			$('#table-proj_table td').css('background','#f7f7f7');
			// Remove duplicate project rows
			var ps_ids = new Array();var i=0;
			$('#table-proj_table tr').each(function(){
				var ps_id = $(this).attr('ps_id');
				if (ps_id != null) {
					if (in_array(ps_id, ps_ids)) {
						$(this).remove();
					} else {
						ps_ids[i++] = ps_id;
					}
				}
			});
		}
		var removedNonNumeralsFromCounts = false;
		function removeNonNumeralsFromCounts(){
            if (removedNonNumeralsFromCounts) return;
            $('#table-proj_table .pid-cnt-h').each(function(){
                $(this).parent().html( $(this).html().replace(/\D/g,'') );
            });
            removedNonNumeralsFromCounts = true;
        }
		$(function(){
			if (super_user) {
				$('#proj_table .hDiv table th:eq(0)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',0,'string');
				});
				$('#proj_table .hDiv table th:eq(1)').click(function(){
					removeExtraRows();
					removeNonNumeralsFromCounts();
					SortTable('table-proj_table',1,'int');
				});
				$('#proj_table .hDiv table th:eq(2)').click(function(){
					removeExtraRows();
					removeNonNumeralsFromCounts();
					SortTable('table-proj_table',2,'int');
				});
				$('#proj_table .hDiv table th:eq(3)').click(function(){
					removeExtraRows();
					removeNonNumeralsFromCounts();
					SortTable('table-proj_table',3,'int');
				});
				$('#proj_table .hDiv table th:eq(4)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',4,'int');
				});
				$('#proj_table .hDiv table th:eq(5)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',5,'string');
				});
				$('#proj_table .hDiv table th:eq(6)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',6,'string');
				});
			}
			else {
				$('#proj_table .hDiv table th:eq(0)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',0,'string');
				});
				$('#proj_table .hDiv table th:eq(1)').click(function(){
					removeExtraRows();
					removeNonNumeralsFromCounts();
					SortTable('table-proj_table',1,'int');
				});
				$('#proj_table .hDiv table th:eq(2)').click(function(){
					removeExtraRows();
					removeNonNumeralsFromCounts();
					SortTable('table-proj_table',2,'int');
				});
				$('#proj_table .hDiv table th:eq(3)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',3,'int');
				});
				$('#proj_table .hDiv table th:eq(4)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',4,'string');
				});
				$('#proj_table .hDiv table th:eq(5)').click(function(){
					removeExtraRows();
					SortTable('table-proj_table',5,'string');
				});
			}
		});
		</script>
		<?php

		// Display table
		self::renderProjectsGrid("proj_table", $tableTitle, 'auto', 'auto', $col_widths_headers, $row_data);

		// Hidden tooltip div for Project Notes
		print RCView::div(array('class'=>'tooltip4', 'style'=>'font-size:11px;', 'id'=>'pntooltip'), '');

		//Display any Public projects (using "none" auth) if flag is set in config table
		if ($display_nonauth_projects && $auth_meth_global != "none" && !$isControlCenter) {

			// Get all public dbs that the user does not already have access to (to prevent duplication in lists)
			$sql = "select project_id, project_name, app_title from redcap_projects where auth_meth = 'none'
					and status in (1, 0) and project_id not in
					(0,".pre_query("select project_id from redcap_user_rights where username = '" . db_escape(defined('USERID') ? USERID : '') . "'").")
					order by trim(app_title)";
			$q = db_query($sql, $rc_connection);

			// Only show this section if at least one public project exists
			if (db_num_rows($q) > 0)
			{
				print  "<div class='d-none d-sm-block'>";
				print  "<p style='margin-top:40px;'>{$lang['home_34']}";
				//Give extra note to super user
				if (defined('SUPER_USER') ? SUPER_USER : '') {
					print  "<i>{$lang['home_35']}</i>";
				}
				print  "</p>";

				$pubList = array();
				while ($attr = db_fetch_assoc($q)) {
					//Title
					$pubList[] = array('<a title="'.htmlspecialchars(js_escape2($lang['control_center_432']), ENT_QUOTES).'" href="' . APP_PATH_WEBROOT . 'index.php?pid=' . $attr['project_id'] . '" class="aGrid">'.RCView::escape($attr['app_title']).'</a>');
				}

				$col_widths_headers = array(
										array(850, "<b>{$lang['home_36']}</b>")
									);
				renderGrid("proj_table_pub", "", 860, 'auto', $col_widths_headers, $pubList);
				print "</div>";
			}

		}

	}


	static function renderProjectsGrid($id, $title, $width_px='auto', $height_px='auto', $col_widths_headers=array(), &$row_data=array(), $show_headers=true, $enable_header_sort=false, $outputToPage=true)
	{
		global $lang;
		## SETTINGS
		// $col_widths_headers = array(  array($width_px, $header_text, $alignment, $data_type), ... );
		// $data_type = 'string','int','date'
		// $row_data = array(  array($col1, $col2, ...), ... );

		$collapse_ids = array();

		// Are we viewing the list from the Control Center?
		$isControlCenter = (strpos(PAGE_FULL, "/ControlCenter/") !== false);

		// Set dimensions and settings
		$width = is_numeric($width_px) ? "width: " . $width_px . "px;" : "width: 100%;";
		$height = ($height_px == 'auto') ? "" : "height: " . $height_px . "px; overflow-y: auto;";
		if (trim($id) == "") {
			$id = substr(sha1(rand()), 0, 8);
		}
		$table_id_js = "table-$id";
		$table_id = "id=\"$table_id_js\"";
		$id = "id=\"$id\"";

		// Check column values
		$row_settings = array();
		foreach ($col_widths_headers as $this_key=>$this_col)
		{
			$this_width  = is_numeric($this_col[0]) ? $this_col[0] . "px" : "100%";
			$this_header = $this_col[1];
			$this_align  = isset($this_col[2]) ? $this_col[2] : "left";
			$this_type   = isset($this_col[3]) ? $this_col[3] : "string";

			// Re-assign checked values
			$col_widths_headers[$this_key] = array($this_width, $this_header, $this_align, $this_type);

			// Add width and alignment to other array (used when looping through each row)
			$row_settings[] = array('width'=>$this_width, 'align'=>$this_align);
		}
		$min_width = "min-width:" . ($isControlCenter ? "642px;" : "862px;");
		// Render grid
		$grid = '<div class="flexigrid d-none d-sm-block" ' . $id . ' style="' . $width . $height . $min_width . '"><div class="mDiv"><div class="ftitle" ' . ((trim($title) != '') ? '' : 'style="display:none;"') . '>' . $title . '</div></div><div class="hDiv"' . ($show_headers ? '' : 'style="display:none;"') . '><div><table cellspacing="0"><tr>';
		$gridMobile =  '<div class="list-group d-block d-sm-none" style="margin-top:60px;">
							<a href="#" class="list-group-item" style="font-size:15px;font-weight:bold;background-color:#D7D7D7;border-color:#ccc;color:#000;">'.($isControlCenter ? $lang['control_center_134'] : $lang['home_22']).'</a>';

		foreach ($col_widths_headers as $col_key=>$this_col)
		{
			$grid .= '<th' . ($this_col[2] == 'left' ? '' : ' align="' . $this_col[2] . '"') . '><div style="' . ($this_col[2] == 'left' ? '' : 'text-align:'.$this_col[2] . ';') . 'width:' . $this_col[0] . ';">' . $this_col[1] . '</div></th>';
		}

		$grid .= '</tr></table></div></div><div class="bDiv"><table ' . $table_id . ' style="width:100%;" cellspacing="0">';

		$expand = RCView::img(array('src'=>'toggle-expand.png'));
		$collapse = RCView::img(array('src'=>'toggle-collapse.png'));
		$unorg_icon = RCView::img(array('src'=>'folder-grey.png', 'class'=>'opacity50'));

		// gather collapsed folder ids
		foreach ($row_data as $row_key => $this_row)
		{
			if(count($this_row) == 4 && $this_row[2] == 1)
			{
				$collapse_ids[] = $this_row[0];
			}
		}

		$user = User::getUserInfo(USERID);
		$user_folder_count = count(ProjectFolders::getAll($user));

		$project_bg = '';
		$row_class = 'myprojstripe';

		$dom = new DOMDocument;

		$i = 0;

		foreach ($row_data as $row_key => $this_row)
		{
			$count = count($this_row);

			// folder
			if($count == 4)
			{
				$folder_id = $this_row[0];

				// hide unorganized '0' folder if no other folders
				if($folder_id == 0 && $user_folder_count == 0)
				{
					continue;
				}

				$project_bg = RenderProjectList::projectBGStyle($this_row[3][2], 0.2);
				$ps_id = substr(md5($this_row[0]), 0, 8);

				$grid .= "<tr ps_id='$ps_id' class='nohover' id='fold_$folder_id' style='".($folder_id == '0' ? "" : "cursor:pointer;")."color:#" . $this_row[3][0] . ';' . $this_row[3][1] . "'>"
					   . "<td onclick='toggleFolderCollapse($folder_id);' colspan='7' class='fldrrwparent' style='" . $this_row[3][1] . "'>";

				$foldIcon = "<span class='fldrrwtoggle' id='fold_$folder_id'>";
				$foldIconM = "<span class='fldrrwtogglem' id='foldm_$folder_id'>";
				if (in_array($folder_id, $collapse_ids)) {
					$foldIcon .= "<span id='col_$folder_id' style='display:none'>$collapse</span><span id='exp_$folder_id'>$expand</span>";
					$foldIconM .= "<span id='colm_$folder_id' style='display:none'>$collapse</span><span id='expm_$folder_id'>$expand</span>";
				} else {
					$foldIcon .= "<span id='col_$folder_id'>".($folder_id == '0' ? $unorg_icon : $collapse)."</span><span id='exp_$folder_id' style='display:none'>$expand</span>";
					$foldIconM .= "<span id='colm_$folder_id'>".($folder_id == '0' ? $unorg_icon : $collapse)."</span><span id='expm_$folder_id' style='display:none'>$expand</span>";
				}
				$foldIcon .= "</span>";
				$foldIconM .= "</span>";

				$grid .= $foldIcon . "<div class='fldrrw'>{$this_row[1]}</div></td></tr>";

				$gridMobile .= '<a href="javascript:;" onclick="toggleFolderCollapse('.$folder_id.');" class="list-group-item" style="font-weight:bold;'.($folder_id == '0' ? "" : "cursor:pointer;")."color:#" . $this_row[3][0] . ';' . $this_row[3][1] . '">
									' . $foldIconM . $this_row[1] . '
								</a>';
			}

			// project
			if($count == 7 || $count == 6)
			{
				// hide tr AND ignore quicksearch uncollapsing with custom 'qs' attribute
				$isCollapsed = in_array($folder_id, $collapse_ids);
				$hide = $isCollapsed ?  "ps='collapsed'" : "ps='expanded'";
				$row_style = $isCollapsed ?  "display:none;" : "";
				$ps_id = substr(md5($this_row[0]), 0, 8);
				$row_class = ($folder_id == '0' && $row_class == 'myprojstripe') ? "" : "myprojstripe";
				$rowId = "f_" . $folder_id . '_' . $row_key;
				$rowIdM = "fm_" . $folder_id . '_' . $row_key; // This is never used!

				$grid .= "<tr ps_id='" . $ps_id . "' class='$row_class' {$hide} style='$row_style' id='$rowId'>";

				// Extra link href from HTML link
				if (function_exists('mb_convert_encoding')) {
					$this_row[0] = mb_convert_encoding($this_row[0], 'HTML-ENTITIES', 'UTF-8');
				}
				$dom->loadHTML($this_row[0]);
				$projectName = "";
				foreach ($dom->getElementsByTagName('a') as $node) {
					// Find the HREF and link label from the HTML in the row
					foreach ($node->getElementsByTagName('div') as $node2) {
						$node2->parentNode->removeChild($node2);
					}
					$projectName = $node->nodeValue;
					break;
				}
				if (!empty($this_row[4])) {
					$borderColor = $folder_id > 0 ? "border-color:#fff;" : "";
					$gridMobile .= '<a href="'.$node->getAttribute('href').'" id="'.$rowId.'" class="list-group-item myprojmitem '.$row_class.'" style="'.$row_style.$borderColor.$project_bg.'">
										'.strip_tags($projectName).'
										<span style="float:right;padding:0 3px;">'.$this_row[5].'</span>
										<span style="float:right;padding:0 3px;">'.$this_row[4].'</span>
									</a>';
					$i++;
				} else {
					$gridMobile .= '<a href="#" class="list-group-item myprojmitem" style="color:#777;">
										'.strip_tags($this_row[0]).'
									</a>';
				}

				foreach ($this_row as $col_key => $this_col)
				{
					$grid .= "<td style='$project_bg'" . ($row_settings[$col_key]['align'] == 'left' ? '' : ' align="' . $row_settings[$col_key]['align'] . '"') . '><div ';

					if ($row_settings[$col_key]['align'] == 'center')
					{
						$grid .= 'class="fc" ';
					}
					elseif ($row_settings[$col_key]['align'] == 'right')
					{
						$grid .= 'class="fr" ';
					}
					$pad_left = 5;
					$pad_left += (!$col_key && !$isControlCenter && $user_folder_count) ? 25 : 0;
					$grid .= 'style="width:' . $row_settings[$col_key]['width'] . '; padding-left:' . $pad_left . 'px;">' . $this_col . '</div></td>';
				}

				$grid .= '</tr>';
			}

			// Delete last row to clear up memory as we go
			unset($row_data[$row_key]);
		}

		$grid .= '</table></div></div>';
		$gridMobile .= '</div>';

		// Render grid (or return as html string)
		if ($outputToPage)
		{
			print $grid . $gridMobile;
		}
		else
		{
			return $grid . $gridMobile;
		}
	}
}
