<?php

class DataAccessGroups
{
	// Get array of users in current user's DAG, if in a DAG
	public static function getDagUsers($project_id, $group_id)
	{
		$dag_users_array = array();
		if ($group_id != "") {
			$sql = "select u.username from redcap_data_access_groups g, redcap_user_rights u where g.group_id = $group_id
                    and g.group_id = u.group_id and u.project_id = g.project_id and g.project_id = $project_id";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				$dag_users_array[] = $row['username'];
			}
		}
		return $dag_users_array;
	}


	// Render main DAG page
	public static function renderPage()
	{
		extract($GLOBALS);

		// Detect if using Randomization with DAG as a strata
		// If so, then disable deleting of DAGs
		$randomizationDagStrata = '0';
		if ($randomization && Randomization::setupStatus()) {
			$randAttr = Randomization::getRandomizationAttributes();
			$randomizationDagStrata = ($randAttr['group_by'] == 'DAG') ? '1' : '0';
		}

		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

		print  "<div style='text-align:right;max-width:900px;'>
			<i class=\"fas fa-film\"></i>
			<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=data_access_groups02.mp4&referer=".SERVER_NAME."&title=Data Access Groups','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">".$lang['data_access_groups_07']."</a>
		</div>";

		print  "<p style='max-width:900px;'>{$lang['data_access_groups_01']} {$lang['data_access_groups_21']}
                <a href='javascript:;' style='text-decoration: underline;' onclick=\"$('#dag-instructions').show();$(this).remove();\">{$lang['data_export_tool_08b']}</a>
                </p>";

		//Data Access Groups (only show to users that are NOT in a group)
		if ($user_rights['group_id'] == "") {
			print  "<div id='dag-instructions' style='display:none;'><p style='max-width:900px;'>{$lang['data_access_groups_02']} {$lang['data_access_groups_ajax_40']}</p>
            <p style='max-width:900px;'>{$lang['data_access_groups_22']}</p></div>
			<div id='group_table'>";
			DataAccessGroups::ajax();
			print  "</div>";
		} else {
			//User does not have permission to be here because user is in a data access group.
			print  "<div class='red' style='margin-top:30px;'>
				<b>{$lang['data_access_groups_03']}</b><br>{$lang['data_access_groups_04']}
			</div>";
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}

		// DAG Switcher table
		$dagSwitcher = new DAGSwitcher();
		print "<div id='dag-switcher-config-container-parent'>" . $dagSwitcher->renderDAGPageTableContainer() . "</div>";

		// JavaScript
		loadJS('DataAccessGroups.js');
		addLangToJS(array('data_access_groups_ajax_38', 'rights_179', 'data_access_groups_ajax_17', 'rights_319', 'rights_318', 'rights_184', 'questionmark', 'rights_185', 'global_53', 'global_19'));
		?>
		<script type="text/javascript">
            var randomizationDagStrata = '<?=$randomizationDagStrata?>';
		</script>
		<?php

		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

	}


	// DAG ajax requests on main DAG page
	public static function ajax()
	{
		extract($GLOBALS);

		if ($user_rights['group_id'] != "") exit("ERROR!");

		//If action is provided in AJAX request, perform action.
		if (isset($_GET['action'])) {
			switch ($_GET['action']) {
				case "delete":
					//Before deleting, make sure no users are in the group. If there are, don't delete.
					if (!is_numeric($_GET['item'])) exit('ERROR!');
					$_GET['item'] = (int)$_GET['item'];
					$gcount = db_result(db_query("select count(1) from redcap_user_rights where project_id = $project_id and group_id = {$_GET['item']}"), 0);

					$sql2 = "select count(1) from redcap_user_roles where project_id = $project_id and group_id = " . $_GET['item'];
					$query2 = db_query($sql2);
					$gcount2 = $query2 !== false ? db_result($query2, 0) : 0;

					// Are there any records in this project?
					$recordsInDag = Records::getRecordList($project_id, $_GET['item']);
					$numRecordsInDag = count($recordsInDag);

					if ($numRecordsInDag == 0 && $gcount + $gcount2 == 0) {
						// Get group name
						$group_name = $Proj->getGroups($_GET['item']);
						// Delete from DAG table
						$sql = "delete from redcap_data_access_groups where group_id = " . $_GET['item'];
						$q = db_query($sql);
						// Also delete any instances of records being attributed to the DAG in the data table
						$sql2 = "delete from redcap_data where project_id = $project_id and field_name = '__GROUPID__'
						and value = '" . db_escape($_GET['item']) . "'";
						$q = db_query($sql2);
						// Logging
						if ($q) Logging::logEvent("$sql;\n$sql2", "redcap_data_access_groups", "MANAGE", $_GET['item'], "group_id = " . $_GET['item'], "Delete data access group");
						print  "<div class='red dagMsg hidden' style='max-width:700px;text-align:center;'>
						<img src='" . APP_PATH_IMAGES . "cross.png'>
						{$lang['global_78']} \"<b>$group_name</b>\" {$lang['data_access_groups_ajax_28']}
						</div>";
					} elseif ($numRecordsInDag > 0) {
						// Can't delete DAG because it has records
						print  "<div class='red dagMsg hidden' style='max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_access_groups_ajax_43']}
						</div>";
					} else {
						// Can't delete DAG because it has users
						print  "<div class='red dagMsg hidden' style='max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
						{$lang['data_access_groups_ajax_35']}
						</div>";
					}
					## What happens to the associated records that belong to a group that is deleted?
					break;
				case "add":
					$new_group_name = strip_tags(html_entity_decode(trim($_GET['item']), ENT_QUOTES));
					if ($new_group_name != "") {
						$sql = "insert into redcap_data_access_groups (project_id, group_name) values ($project_id, '" . db_escape($new_group_name) . "')";
						$q = db_query($sql);
						// Logging
						if ($q) {
							$dag_id = db_insert_id();
							Logging::logEvent($sql, "redcap_data_access_groups", "MANAGE", $dag_id, "group_id = $dag_id", "Create data access group");
						}
						print  "<div class='darkgreen dagMsg hidden' style='max-width:700px;text-align:center;'>
						<img src='" . APP_PATH_IMAGES . "tick.png'>
						{$lang['global_78']} \"<b>$new_group_name</b>\" {$lang['data_access_groups_ajax_29']}</div>";
					}
					break;
				case "rename":
					$group_id = substr($_GET['group_id'], 4);
					if (!is_numeric($group_id)) exit('ERROR!');
					//exit(rawurldecode($_GET['item']));
					$new_group_name = trim(strip_tags(rawurldecode(urldecode($_GET['item']))));
					if ($new_group_name != "") {
						$sql = "update redcap_data_access_groups set group_name = '" . db_escape($new_group_name) . "' where group_id = $group_id";
						$q = db_query($sql);
						// Logging
						if ($q) Logging::logEvent($sql, "redcap_data_access_groups", "MANAGE", $group_id, "group_id = " . $group_id, "Rename data access group");
					}
					exit($new_group_name);
					break;
				case "add_user":
					if (!is_numeric($_GET['group_id']) && $_GET['group_id'] != '') exit('ERROR!');
					if ($_GET['group_id'] == "") {
						$assigned_msg = $lang['data_access_groups_ajax_31'];
						$_GET['group_id'] = "NULL";
						$logging_msg = "Remove user from data access group";
						// Get group name for user BEFORE we unassign them
						$this_user_rights = UserRights::getPrivileges($project_id, $_GET['user']);
						$this_user_rights = $this_user_rights[$project_id][strtolower($_GET['user'])];
						$group_name = $Proj->getGroups($this_user_rights['group_id']);
					} else {
						// Get group name
						$group_name = $Proj->getGroups($_GET['group_id']);
						$assigned_msg = "{$lang['data_access_groups_ajax_30']} \"<b>$group_name</b>\"{$lang['exclamationpoint']}";
						$logging_msg = "Assign user to data access group";
					}
					$sql = "update redcap_user_rights set group_id = {$_GET['group_id']} where username = '" . db_escape($_GET['user']) . "' and project_id = $project_id";
					$q = db_query($sql);
					// Logging
					$group_names = gettype($group_name) == 'array' ? implode(',', $group_name) : $group_name;
					if ($q) Logging::logEvent($sql, "redcap_user_rights", "MANAGE", $_GET['user'], "user = '{$_GET['user']}',\ngroup = '" . $group_names . "'", $logging_msg);
					print  "<div class='darkgreen dagMsg hidden'  style='max-width:700px;text-align:center;'>
					<img src='" . APP_PATH_IMAGES . "tick.png'>
					{$lang['global_17']} \"<b>" . remBr(RCView::escape($_GET['user'])) . "</b>\" $assigned_msg
					</div>";
					// If flag is set to display the User Rights table, then return its html and stop here
					if (isset($_GET['return_user_rights_table']) && $_GET['return_user_rights_table']) {
						exit(UserRights::renderUserRightsRolesTable());
					}
					break;
				case "select_group":
					$group_id = db_result(db_query("select group_id from redcap_user_rights where username = '" . db_escape($_GET['user']) . "' and project_id = $project_id"), 0);
					exit($group_id);
					break;
				case "select_role":
					$group_id = db_result(db_query("select group_id from redcap_user_roles where role_id = '" . db_escape($_GET['role_id']) . "' and project_id = $project_id"), 0);
					exit($group_id);
					break;
			}

		}

		// Reset groups in case were just modified above
		$Proj->resetGroups();

		// Render groups table and options to designated users/roles to groups
		print self::renderDataAccessGroupsTable();
	}



	/**
	 * RENDER DATA ACCESS GROUPS TABLE
	 * Return html for table to be displayed
	 */
	public static function renderDataAccessGroupsTable()
	{
		global  $user_rights, $lang, $Proj, $table_pk;

		// Add DAGs to array
		$groups = $Proj->getGroups();

		## DAG RECORD COUNT
		// Determine which records are in which group
		$recordsInDags = array();
		$recordDag = Records::getRecordListAllDags(PROJECT_ID, true);
		// Get count of all records in each group
		foreach ($recordDag as $record=>$group_id)
		{
			if (!isset($recordsInDags[$group_id])) {
				$recordsInDags[$group_id] = 1;
			} else {
				$recordsInDags[$group_id]++;
			}
			unset($recordDag[$record]);
		}
		unset($recordDag);

		// Get array of project users
		$projectUsers = User::getProjectUsernames(array(), true);

		// Get array of group users with first/last names appended
		$groupUsers = array();
		foreach ($Proj->getGroupUsers(null, true) as $this_group_id=>$these_users) {
			foreach ($these_users as $this_user) {
				// Put username+first/last in individual's group
				$groupUsers[$this_group_id][] = RCView::escape($projectUsers[$this_user]);
			}
		}

		// Now remove current user from $projectUsers so they don't get added to the Select User drop-down
		unset($projectUsers[USERID]);

		// Set html before the table
		$html = RCView::div(array('style'=>'margin:20px 0;font-size:12px;font-weight:normal;padding:10px;border:1px solid #ccc;background-color:#f1eeee;max-width:900px;'),
			// Create new DAG
			RCView::div(array('style'=>'color:#444;'),
				RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), '<i class="fas fa-plus"></i> ' .$lang['rights_182']) .
				" " .$lang['rights_183']
			) .
			RCView::div(array('style'=>'margin:8px 0 0 29px;'),
				RCView::text(array('size'=>30, 'maxlength'=>100, 'id'=>'new_group', 'class'=>'x-form-text x-form-field',
					'style'=>'color:#999;margin-left:4px;font-size:13px;padding-top:0;',
					'onclick'=>"if(this.value=='".js_escape($lang['rights_179'])."'){this.value='';this.style.color='#000';}",
					'onfocus'=>"if(this.value=='".js_escape($lang['rights_179'])."'){this.value='';this.style.color='#000';}",
					'onblur'=>"if(this.value==''){this.value='".js_escape($lang['rights_179'])."';this.style.color='#999';}",
					'onkeydown'=>"if(event.keyCode==13) add_group();",
					'value'=>$lang['rights_179']
				)) .
				// Add Group button
				RCView::button(array('id'=>'new_group_button', 'class'=>'btn btn-xs fs13 btn-rcgreen', 'onclick'=>'add_group();'), '<i class="fas fa-plus"></i> ' .$lang['rights_180']) .
				// Hidden progress img
				RCView::span(array('id'=>'progress_img', 'style'=>'visibility:hidden;'),
					RCView::img(array('src'=>'progress_circle.gif'))
				)
			) .
			// Assign user to DAG
			RCView::div(array('style'=>'color:#444;margin-top:20px;'),
				RCView::span(array('style'=>'font-weight:bold;font-size:13px;color:#000;margin-right:5px;'), '<i class="fas fa-user-tag mr-1"></i>' . $lang['data_access_groups_ajax_32']) .
				" " .$lang['data_access_groups_ajax_36']." " .$lang['data_access_groups_23']
			) .
			RCView::div(array('style'=>'margin:8px 0 0 29px;'),
				$lang['data_access_groups_ajax_12'] .
				// Drop-down of users (do NOT display users that are in a role because that would be confusing since their role's DAG assignment overrides their individual DAG assignment)
				RCView::select(array('id'=>'group_users', 'onchange'=>'select_group(this.value);', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 5px 0 7px;'),
					array(''=>"-- {$lang['data_access_groups_ajax_13']} --")+$projectUsers, '') .
				$lang['data_access_groups_ajax_14'] .
				RCView::select(array('id'=>'groups', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 10px 0 6px;'),
					(array(''=>"[{$lang['data_access_groups_ajax_16']}]") + $groups), '') .
				RCView::button(array('id'=>'user_group_button', 'class'=>'btn btn-xs fs13 btn-primaryrc', 'onclick'=>"add_user_to_group();"),
					'<i class="fas fa-user-tag mr-1"></i>' . $lang['rights_181']
                ) .
				// Hidden progress img
				RCView::span(array('id'=>'progress_img_user', 'style'=>'visibility:hidden;'),
					RCView::img(array('src'=>'progress_circle.gif'))
				)
			)
		);

		// Set table hdrs
		$hdrs = array(
			array(270, 	RCView::div(array('class'=>'wrap','style'=>'font-size:13px;font-weight:bold;'),
				$lang['global_22'])),
			array(230, 	RCView::div(array('class'=>'wrap','style'=>'font-weight:bold;'),
				$lang['data_access_groups_ajax_08']
			)),
			array(65, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;font-weight:bold;line-height:13px;'), $lang['data_access_groups_ajax_25']), 'center', 'int'),
			array(155, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;line-height:13px;'),
				"{$lang['data_access_groups_ajax_18']}
				<a href='javascript:;' onclick=\"simpleDialog('".js_escape($lang['data_access_groups_ajax_19'])."','".js_escape($lang['data_access_groups_ajax_18'])."');\"><img title=\"".js_escape2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png'></a><br>
				{$lang['define_events_66']}")),
			array(60, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;line-height:13px;'), $lang['data_access_groups_ajax_41']."<a style='margin-left:2px;' href='javascript:;' onclick=\"simpleDialog('".js_escape($lang['data_access_groups_ajax_42'])."','".js_escape($lang['data_access_groups_ajax_41'])."',null,600);\"><img title=\"".js_escape2($lang['form_renderer_02'])."\" src='".APP_PATH_IMAGES."help.png'></a>"), 'center'),
			array(40, RCView::div(array('class'=>'wrap','style'=>'font-size:11px;line-height:13px;'), $lang['data_access_groups_ajax_09']), 'center')
		);

		// Loop through each group and render as row
		$rows = array();
		foreach ($groups as $group_id=>$group_name)
		{
			// Set values for row
			$rows[] = array(
				RCView::span(array('id'=>"gid_{$group_id}", 'class'=>'wrap editText', 'title'=>$lang['data_access_groups_06'], 'style'=>'cursor:pointer;cursor:hand;display:block;color:#000066;font-weight:bold;font-size:12px;'), $group_name),
				RCView::div(array('class'=>'wrap'), "<div style='line-height:1.2;'>".implode(",</div><div style='line-height:1.2;'>", isset($groupUsers[$group_id]) ? $groupUsers[$group_id] : array())."</div>"),
				(isset($recordsInDags[$group_id]) ? $recordsInDags[$group_id] : 0),
				RCView::span(array('id'=>"ugid_{$group_id}", 'class'=>'wrap', 'style'=>'color:#777;'), $Proj->getUniqueGroupNames($group_id)),
				RCView::span(array('style'=>'color:#777;'), $group_id),
				RCView::a(array('href'=>'javascript:;'),
					RCView::img(array('src'=>'cross.png', 'onclick'=>"del_msg('$group_id','".js_escape($group_name)."')"))
				)
			);
		}
		// Add last row of unassigned users
		$rows[] = array(
			RCView::span(array('style'=>'color:#800000;font-size:12px;'), $lang['data_access_groups_ajax_24']),
			RCView::div(array('class'=>'wrap'), "<div style='line-height:1.2;'>".implode(",</div><div style='line-height:1.2;'>", $groupUsers[0]) . (empty($groupUsers[0]) ? "" : RCView::div(array('style'=>'color:#C00000;'), $lang['data_access_groups_ajax_26']))."</div>"),
			isset($recordsInDags[0]) ? $recordsInDags[0] : '',
			"",
			"",
			""
		);

		// Return the html for displaying the table
		return $html . renderGrid("dags_table", isset($title) ? $title : '', 900, "auto", $hdrs, $rows, true, false, false);
	}
}