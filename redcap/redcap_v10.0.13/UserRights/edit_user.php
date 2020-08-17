<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Get list of all roles in project
$roles = UserRights::getRoles();



## ADD/EDIT/DELETE USER
if (isset($_POST['submit-action']))
{
	// Initialize $context_msg
	$context_msg = '';

	// Set and trim username/role name
	$user = trim(strip_tags(html_entity_decode($_POST['user'], ENT_QUOTES)));

	// Set submit-action flag and remove from Post to prevent issues
	$submit_action = $_POST['submit-action'];
	unset($_POST['submit-action']);

	/// Set context_msg
	if ($user != '' && $_POST['role_name'] == '') {
		// User
		$context_msg_update = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><img src='".APP_PATH_IMAGES."tick.png'> ".$lang['global_17']." \"<b>".RCView::escape($user)."</b>\" {$lang['rights_05']}</div>";
		$context_msg_insert = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><img src='".APP_PATH_IMAGES."tick.png'> ".$lang['global_17']." \"<b>".RCView::escape($user)."</b>\" {$lang['rights_06']}</div>";
		$context_msg_delete = "<div class='userSaveMsg red' style='max-width:600px;text-align:center;'><img src='".APP_PATH_IMAGES."exclamation.png'> ".$lang['global_17']." \"<b>".RCView::escape($user)."</b>\" {$lang['rights_07']}</div>";
	} else {
		// Role
		if ($user == '0') {
			// New role
			$role_name = strip_tags(html_entity_decode($_POST['role_name'], ENT_QUOTES));
		} elseif (isset($_POST['role_name_edit'])) {
			// Edit role name or Copy role
			$role_name = html_entity_decode($_POST['role_name_edit'], ENT_QUOTES);
			// Logging (if renaming role)
			if ($submit_action == "edit_role" && $_POST['role_name_edit'] != $roles[$user]['role_name']) {
				Logging::logEvent('',"redcap_user_rights","update",$user,"role = '$role_name',\nold role = '{$roles[$user]['role_name']}'","Rename role");
			}
		} else {
			$role_name = $roles[$user]['role_name'];
		}
		$context_msg_update = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><img src='".APP_PATH_IMAGES."tick.png'> ".$lang['global_115']." \"<b>".RCView::escape($role_name)."</b>\" {$lang['rights_05']}</div>";
		$context_msg_insert = "<div class='userSaveMsg darkgreen' style='max-width:600px;text-align:center;'><img src='".APP_PATH_IMAGES."tick.png'> ".$lang['global_115']." \"<b>".RCView::escape($role_name)."</b>\" {$lang['rights_06']}</div>";
		$context_msg_delete = "<div class='userSaveMsg red' style='max-width:600px;text-align:center;'><img src='".APP_PATH_IMAGES."cross.png'> ".$lang['global_115']." \"<b>".RCView::escape($role_name)."</b>\" {$lang['rights_07']}</div>";
	}

	//Switch all checkboxes from 'on' to '1'
	foreach ($_POST as $key => $value) {
		if ($value == 'on') $_POST[$key] = 1;
	}
	// Set and format expiration date
	if (isset($_POST['expiration'])) {
		$_POST['expiration'] = DateTimeRC::format_ts_to_ymd(trim($_POST['expiration']));
	}
	//Fix values for unchecked check boxes
	if ($_POST['data_export_tool'] == '') 		$_POST['data_export_tool'] = 0;
	if ($_POST['data_import_tool'] == '') 		$_POST['data_import_tool'] = 0;
	if ($_POST['data_comparison_tool'] == '') 	$_POST['data_comparison_tool'] = 0;
	if ($_POST['data_logging'] == '') 			$_POST['data_logging'] = 0;
	if ($_POST['file_repository'] == '') 		$_POST['file_repository'] = 0;
	if ($_POST['double_data'] == '') 			$_POST['double_data'] = 0;
	if ($_POST['user_rights'] == '') 			$_POST['user_rights'] = 0;
	if ($_POST['data_access_groups'] == '') 	$_POST['data_access_groups'] = 0;
	if ($_POST['lock_record'] == '') 			$_POST['lock_record'] = 0;
	if ($_POST['lock_record_multiform'] == '') 	$_POST['lock_record_multiform'] = 0;
	if ($_POST['lock_record_customize'] == '') 	$_POST['lock_record_customize'] = 0;
	if ($_POST['design'] == '') 				$_POST['design'] = 0;
	if ($_POST['graphical'] == '') 				$_POST['graphical'] = 0;
	if ($_POST['reports'] == '') 				$_POST['reports'] = 0;
	if ($_POST['calendar'] == '') 				$_POST['calendar'] = 0;
	if ($_POST['record_create'] == '') 			$_POST['record_create'] = 0;
	if ($_POST['record_rename'] == '') 			$_POST['record_rename'] = 0;
	if ($_POST['record_delete'] == '') 			$_POST['record_delete'] = 0;
	if ($_POST['participants'] == '') 			$_POST['participants'] = 0;
	if ($_POST['data_quality_design'] == '') 	$_POST['data_quality_design'] = 0;
	if ($_POST['data_quality_execute'] == '') 	$_POST['data_quality_execute'] = 0;
	if ($_POST['data_quality_resolution'] == '') $_POST['data_quality_resolution'] = 0;
	if ($_POST['api_export'] == '') $_POST['api_export'] = 0;
	if ($_POST['api_import'] == '') $_POST['api_import'] = 0;
	if ($_POST['mobile_app'] == '') $_POST['mobile_app'] = 0;
	if ($_POST['mobile_app_download_data'] == '') $_POST['mobile_app_download_data'] = 0;
	if ($_POST['expiration'] == '') 			$_POST['expiration'] = 'NULL'; else $_POST['expiration'] = "'".$_POST['expiration']."'";
	if (!isset($_POST['dts']) || (isset($_POST['dts']) && $_POST['dts'] == ''))	$_POST['dts'] = 0;
	if ($_POST['random_setup'] == '') 			$_POST['random_setup'] = 0;
	if ($_POST['random_dashboard'] == '') 		$_POST['random_dashboard'] = 0;
	if ($_POST['random_perform'] == '') 		$_POST['random_perform'] = 0;
	if ($_POST['realtime_webservice_mapping'] == '') $_POST['realtime_webservice_mapping'] = 0;
	if ($_POST['realtime_webservice_adjudicate'] == '') $_POST['realtime_webservice_adjudicate'] = 0;
	if (SUPER_USER && isset($_POST['external_module_config']) && !empty($_POST['external_module_config']) && is_array($_POST['external_module_config'])) {
		$_POST['external_module_config'] = json_encode($_POST['external_module_config']);
	} elseif (SUPER_USER && !isset($_POST['external_module_config'])) {
		$_POST['external_module_config'] = '';
	} else {
		unset($_POST['external_module_config']);
	}
		
	// print_array($_POST);exit;

	// Delete role
	if ($submit_action == "delete_role") {
		// Delete user from project rights table
		$sql = "DELETE FROM redcap_user_roles WHERE project_id = $project_id and role_id = '".db_escape($user)."'";
		if (db_query($sql))
		{
			/*
			// For ALL users in role, set role_id to NULL and give the user the exact same rights as the role deleted in order to maintain continuity of privileges
			$this_role_rights = $roles[$user];
			$this_role_users = $this_role_rights['role_users_assigned'];
			// Set role_id to NULL and give the user the exact same rights as the role they were removed from in order to maintain continuity of privileges
			unset($this_role_rights['role_name'], $this_role_rights['project_id'], $this_role_rights['role_users_assigned']);
			// Loop through each user that was in the role
			$sql_all = $sqla = array();
			foreach ($this_role_rights as $key=>$val) $sqla[] = "$key = ".checkNull($val);
			foreach ($this_role_users as $this_role_user) {
				$sql_all[] = $sql = "update redcap_user_rights set role_id = null, " . implode(", ", $sqla) . "
									 where project_id = $project_id and username = '".db_escape($this_role_user)."'";
				db_query($sql);
			}
			*/
			// Set context message
			$context_msg = $context_msg_delete;
			// Logging
			Logging::logEvent(implode(";\n", $sql_all),"redcap_user_rights","delete",$user,"role = '$role_name'","Delete role");
		}

	// Copy role
	} elseif ($submit_action == "copy_role") {
		$sql = "select * from redcap_user_roles where project_id = $project_id and role_id = '".db_escape($user)."'";
		$q = db_query($sql);
		if ($q) {
			$row = db_fetch_assoc($q);
			// Remove project_id, role_name, and role_id from $row since we don't need them
			unset($row['project_id'], $row['role_id'], $row['role_name']);
			// Loop through $row values and escape them for query
			foreach ($row as &$val) $val = checkNull($val);
			// Set the field names and corresponding values for query
			$role_fields = implode(", ", array_keys($row));
			$role_values = implode(", ", $row);
			$sql = "insert into redcap_user_roles (project_id, role_name, $role_fields) values ($project_id, '".db_escape($role_name)."', $role_values)";
			db_query($sql);
			// Get role_id
			$role_id = db_insert_id();
			// Set context message
			$context_msg = $context_msg_insert;
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"role = '$role_name'","Copy role");
			// Add hidden input on the page to denote which role was just copied
			print RCView::text(array('id'=>'copy_role_success', 'value'=>$role_id));
		}

	// Delete user
	} elseif ($submit_action == "delete_user") {

		// Delete user from project rights table
		$sql = "DELETE FROM redcap_user_rights WHERE project_id = $project_id and username = '".db_escape($user)."'";
		if (db_query($sql))
		{
			// Also delete from project bookmarks users table as well
			$sql2 = "DELETE FROM redcap_external_links_users WHERE username = '".db_escape($user)."' and ext_id in
					(" . implode(",", array_keys($ExtRes->getResources())) . ")";
			db_query($sql2);
			// Also delete from redcap_reports_access_users table
			$sql3 = "DELETE FROM redcap_reports_access_users WHERE username = '".db_escape($user)."' and report_id in
					(select report_id from redcap_reports where project_id = $project_id)";
			db_query($sql3);
			
			// Remove from any linked conversations in Messenger.
			Messenger::removeUserFromLinkedProjectConversation($project_id, $user);
			
			// Set context message
			$context_msg = $context_msg_delete;
			// Logging
			Logging::logEvent("$sql;\n$sql2;\n$sql3","redcap_user_rights","delete",$user,"user = '".db_escape($user)."'","Delete user");
		}

	// Edit existing role
	} elseif ($submit_action == "edit_role") {

		//Update project rights table
		$set_values =  "role_name = '".db_escape($role_name)."', data_export_tool = '{$_POST['data_export_tool']}', data_import_tool = '{$_POST['data_import_tool']}',
						data_comparison_tool = '{$_POST['data_comparison_tool']}', data_logging = '{$_POST['data_logging']}',
						file_repository = '{$_POST['file_repository']}', double_data = '{$_POST['double_data']}',
						user_rights = '{$_POST['user_rights']}', data_access_groups = '{$_POST['data_access_groups']}',
						lock_record = '{$_POST['lock_record']}', lock_record_multiform = '{$_POST['lock_record_multiform']}',
						lock_record_customize = '{$_POST['lock_record_customize']}', design = '{$_POST['design']}',
						record_create = '{$_POST['record_create']}',
						record_rename = '{$_POST['record_rename']}', record_delete = '{$_POST['record_delete']}',
						graphical = '{$_POST['graphical']}', calendar = '{$_POST['calendar']}', reports = '{$_POST['reports']}',
						dts = '{$_POST['dts']}', participants = '{$_POST['participants']}',
						data_quality_design = '{$_POST['data_quality_design']}', data_quality_execute = '{$_POST['data_quality_execute']}',
						data_quality_resolution = '{$_POST['data_quality_resolution']}',
						api_export = '{$_POST['api_export']}', api_import = '{$_POST['api_import']}', mobile_app = '{$_POST['mobile_app']}',
						mobile_app_download_data = '{$_POST['mobile_app_download_data']}',
						random_setup = '{$_POST['random_setup']}', random_dashboard = '{$_POST['random_dashboard']}', random_perform = '{$_POST['random_perform']}',
						realtime_webservice_mapping = '{$_POST['realtime_webservice_mapping']}', realtime_webservice_adjudicate = '{$_POST['realtime_webservice_adjudicate']}',
						" . (isset($_POST['external_module_config']) ? "external_module_config = ".checkNull($_POST['external_module_config'])."," : "") . "
						data_entry = '";
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// Process each form's radio button value
			$this_field = "form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// If set survey responses to be editable, then set to value 3
			$editresp_chkbox_name = "form-editresp-" . $form_name;
			if ($this_value == '1' && isset($_POST[$editresp_chkbox_name]) && $_POST[$editresp_chkbox_name])
			{
				$this_value = 3;
			}
			// Set value for this form
			$set_values .= "[$form_name,$this_value]";
		}
		$set_values .= "'";
		$sql = "UPDATE redcap_user_roles SET $set_values WHERE role_id = '".db_escape($user)."' and project_id = $project_id";
		if (db_query($sql)) {
			//Set context message
			$context_msg = $context_msg_update;
			//Logging
			Logging::logEvent($sql,"redcap_user_rights","update",$user,"role = '$role_name'","Edit role");
		}



	// Edit existing user
	} elseif ($submit_action == "edit_user") {

		//Update project rights table
		$set_values =  "data_export_tool = '{$_POST['data_export_tool']}', data_import_tool = '{$_POST['data_import_tool']}',
						data_comparison_tool = '{$_POST['data_comparison_tool']}', data_logging = '{$_POST['data_logging']}',
						file_repository = '{$_POST['file_repository']}', double_data = '{$_POST['double_data']}',
						user_rights = '{$_POST['user_rights']}', data_access_groups = '{$_POST['data_access_groups']}',
						lock_record = '{$_POST['lock_record']}', lock_record_multiform = '{$_POST['lock_record_multiform']}',
						lock_record_customize = '{$_POST['lock_record_customize']}', design = '{$_POST['design']}',
						expiration = {$_POST['expiration']} , record_create = '{$_POST['record_create']}',
						record_rename = '{$_POST['record_rename']}', record_delete = '{$_POST['record_delete']}',
						graphical = '{$_POST['graphical']}', calendar = '{$_POST['calendar']}', reports = '{$_POST['reports']}',
						dts = '{$_POST['dts']}', participants = '{$_POST['participants']}',
						data_quality_design = '{$_POST['data_quality_design']}', data_quality_execute = '{$_POST['data_quality_execute']}',
						data_quality_resolution = '{$_POST['data_quality_resolution']}',
						api_export = '{$_POST['api_export']}', api_import = '{$_POST['api_import']}', mobile_app = '{$_POST['mobile_app']}',
						mobile_app_download_data = '{$_POST['mobile_app_download_data']}',
						random_setup = '{$_POST['random_setup']}', random_dashboard = '{$_POST['random_dashboard']}', random_perform = '{$_POST['random_perform']}',
						realtime_webservice_mapping = '{$_POST['realtime_webservice_mapping']}', realtime_webservice_adjudicate = '{$_POST['realtime_webservice_adjudicate']}',
						" . (isset($_POST['external_module_config']) ? "external_module_config = ".checkNull($_POST['external_module_config'])."," : "") . "
						data_entry = '";
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// Process each form's radio button value
			$this_field = "form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// If set survey responses to be editable, then set to value 3
			$editresp_chkbox_name = "form-editresp-" . $form_name;
			if ($this_value == '1' && isset($_POST[$editresp_chkbox_name]) && $_POST[$editresp_chkbox_name])
			{
				$this_value = 3;
			}
			// Set value for this form
			$set_values .= "[$form_name,$this_value]";
		}
		$set_values .= "'";
		$sql = "UPDATE redcap_user_rights SET $set_values WHERE username = '".db_escape($user)."' and project_id = $project_id";
		if (db_query($sql)) {
			//Set context message
			$context_msg = $context_msg_update;
			//Logging
			Logging::logEvent($sql,"redcap_user_rights","update",$user,"user = '".db_escape($user)."'","Edit user");
		}


	// Add new role
	} elseif ($submit_action == "add_role") {

		//Insert user into user rights table
		$fields = "project_id, role_name, data_export_tool, data_import_tool, data_comparison_tool, data_logging, file_repository, double_data, " .
				  "user_rights, design, lock_record, lock_record_multiform, lock_record_customize, data_access_groups, graphical, reports, calendar, " .
				  "record_create, record_rename, record_delete, dts, participants, data_quality_design, data_quality_execute, data_quality_resolution,
				  api_export, api_import, mobile_app, mobile_app_download_data,
				  random_setup, random_dashboard, random_perform, realtime_webservice_mapping, realtime_webservice_adjudicate, external_module_config,
				  data_entry";
		$values =  "$project_id, '".db_escape($role_name)."', '{$_POST['data_export_tool']}', '{$_POST['data_import_tool']}', '{$_POST['data_comparison_tool']}',
					'{$_POST['data_logging']}', '{$_POST['file_repository']}', '{$_POST['double_data']}', '{$_POST['user_rights']}',
					'{$_POST['design']}', '{$_POST['lock_record']}', '{$_POST['lock_record_multiform']}',
					'{$_POST['lock_record_customize']}', '{$_POST['data_access_groups']}', '{$_POST['graphical']}', '{$_POST['reports']}',
					'{$_POST['calendar']}', '{$_POST['record_create']}', '{$_POST['record_rename']}', '{$_POST['record_delete']}',
					'{$_POST['dts']}', '{$_POST['participants']}', '{$_POST['data_quality_design']}', '{$_POST['data_quality_execute']}', '{$_POST['data_quality_resolution']}',
					'{$_POST['api_export']}', '{$_POST['api_import']}', '{$_POST['mobile_app']}', '{$_POST['mobile_app_download_data']}', '{$_POST['random_setup']}', '{$_POST['random_dashboard']}',
					'{$_POST['random_perform']}', '{$_POST['realtime_webservice_mapping']}', '{$_POST['realtime_webservice_adjudicate']}', ".checkNull($_POST['external_module_config']).", '";
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// Process each form's radio button value
			$this_field = "form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// If set survey responses to be editable, then set to value 3
			$editresp_chkbox_name = "form-editresp-" . $form_name;
			if ($this_value == '1' && isset($_POST[$editresp_chkbox_name]) && $_POST[$editresp_chkbox_name])
			{
				$this_value = 3;
			}
			$values .= "[$form_name,$this_value]";
		}
		$values .= "'";
		// Insert user into user_rights table
		$sql = "INSERT INTO redcap_user_roles ($fields) VALUES ($values)";
		if (db_query($sql)) {
			// Set context message
			$context_msg = $context_msg_insert;
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"role = '$role_name'","Add role");
		}


	// Add new user
	} elseif ($submit_action == "add_user") {

		//Insert user into user rights table
		$fields = "project_id, username, data_export_tool, data_import_tool, data_comparison_tool, data_logging, file_repository, double_data, " .
				  "user_rights, design, expiration, lock_record, lock_record_multiform, lock_record_customize, data_access_groups, graphical, reports, calendar, " .
				  "record_create, record_rename, record_delete, dts, participants, data_quality_design, data_quality_execute, data_quality_resolution,
				  api_export, api_import, mobile_app, mobile_app_download_data,
				  random_setup, random_dashboard, random_perform, realtime_webservice_mapping, realtime_webservice_adjudicate, external_module_config,
				  data_entry";
		$values =  "$project_id, '".db_escape($user)."', '{$_POST['data_export_tool']}', '{$_POST['data_import_tool']}', '{$_POST['data_comparison_tool']}',
					'{$_POST['data_logging']}', '{$_POST['file_repository']}', '{$_POST['double_data']}', '{$_POST['user_rights']}',
					'{$_POST['design']}', {$_POST['expiration']}, '{$_POST['lock_record']}', '{$_POST['lock_record_multiform']}',
					'{$_POST['lock_record_customize']}', '{$_POST['data_access_groups']}', '{$_POST['graphical']}', '{$_POST['reports']}',
					'{$_POST['calendar']}', '{$_POST['record_create']}', '{$_POST['record_rename']}', '{$_POST['record_delete']}',
					'{$_POST['dts']}', '{$_POST['participants']}', '{$_POST['data_quality_design']}', '{$_POST['data_quality_execute']}', '{$_POST['data_quality_resolution']}',
					'{$_POST['api_export']}', '{$_POST['api_import']}', '{$_POST['mobile_app']}', '{$_POST['mobile_app_download_data']}', '{$_POST['random_setup']}', '{$_POST['random_dashboard']}',
					'{$_POST['random_perform']}', '{$_POST['realtime_webservice_mapping']}', '{$_POST['realtime_webservice_adjudicate']}', ".checkNull($_POST['external_module_config']).", '";
		foreach (array_keys($Proj->forms) as $form_name)
		{
			// Process each form's radio button value
			$this_field = "form-" . $form_name;
			$this_value = ($_POST[$this_field] == '') ? 0 : $_POST[$this_field];
			// If set survey responses to be editable, then set to value 3
			$editresp_chkbox_name = "form-editresp-" . $form_name;
			if ($this_value == '1' && isset($_POST[$editresp_chkbox_name]) && $_POST[$editresp_chkbox_name])
			{
				$this_value = 3;
			}
			$values .= "[$form_name,$this_value]";
		}
		$values .= "'";
		// Insert user into user_rights table
		$sql = "INSERT INTO redcap_user_rights ($fields) VALUES ($values)";
		if (db_query($sql)) {
			// Set context message
			$context_msg = $context_msg_insert;
			// Logging
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"user = '".db_escape($user)."'","Add user");
		}

	}

	//If checkbox was checked to notify new user of their access, send an email (but don't send if one has just been sent)
	if (isset($_POST['notify_email']) && $_POST['notify_email'] && $submit_action == "add_user")
	{
		$email = new Message ();
		$emailContents = "
			<html><body style='font-family:arial,helvetica;font-size:10pt;'>
			{$lang['global_21']}<br /><br />
			{$lang['rights_88']} \"<a href=\"".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid=".PROJECT_ID."\">".strip_tags(str_replace("<br>", " ", label_decode($app_title)))."</a>\"{$lang['period']}
			{$lang['rights_89']} \"$user\", {$lang['rights_90']}<br /><br />
			".APP_PATH_WEBROOT_FULL."
			</body>
			</html>";
		//First need to get the email address of the user we're emailing
		$q = db_query("select user_firstname, user_lastname, user_email from redcap_user_information where username = '".db_escape($user)."'");
		$row = db_fetch_array($q);
		$email->setTo($row['user_email']);
		$email->setFrom($user_email);
		$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
		$email->setSubject($lang['rights_122']);
		$email->setBody($emailContents);
		if (!$email->send()) {
			print  "<br><div style='font-size:12px;background-color:#F5F5F5;border:1px solid #C0C0C0;padding:10px;'>
					<div style='font-weight:bold;border-bottom:1px solid #aaaaaa;color:#800000;'>
					<img src='".APP_PATH_IMAGES."exclamation.png' style='position:relative;top:3px;'>
					{$lang['rights_80']}
					</div><br>
					{$lang['global_37']} <span style='color:#666;'>$user_firstname $user_lastname &#60;$user_email&#62;</span><br>
					{$lang['global_38']} <span style='color:#666;'>".$row['user_firstname']." ".$row['user_lastname']." &#60;".$row['user_email']."&#62;</span><br>
					{$lang['rights_83']} <span style='color:#666;'>{$lang['rights_91']}</span><br><br>
					$emailContents<br>
					</div><br>";
		}
	}

	// Return html to redisplay the user/role table
	print $context_msg;
	print UserRights::renderUserRightsRolesTable();
	exit;
}







// Check if $user is a role or a username
$isRole = false;
$role_id = $role_name = null;
if (isset($_POST['username']) && $_POST['username'] != '' && $_POST['role_id'] == '') {
	## NEW/EXISTING USER
	// Remove illegal characters (if somehow posted bypassing javascript)
	$user = preg_replace("/[^a-zA-Z0-9-'\s\.@_]/", "", $_POST['username']);
	if (!isset($_POST['username']) || $user != $_POST['username']) exit('');
	$user = $_POST['username'];
} elseif (isset($_POST['role_id']) && is_numeric($_POST['role_id']) && $_POST['role_id'] == '0') {
	## ADDING NEW ROLE
	$isRole = true;
	$role_id = '0';
	$role_name = strip_tags(html_entity_decode($_POST['username'], ENT_QUOTES));
} elseif ((is_numeric($_POST['username']) && isset($roles[$_POST['username']])) || ($_POST['username'] == '' && is_numeric($_POST['role_id']))) {
	## EXISTING ROLE
	$isRole = true;
	if (is_numeric($_POST['role_id'])) {
		$role_id = $_POST['role_id'];
		$role_name = $roles[$_POST['role_id']]['role_name'];
	} else {
		$role_id = $_POST['username'];
		$role_name = $roles[$_POST['username']]['role_name'];
	}
}


if (!$isRole)
{
	//If the person using this application is in a Data Access Group, do not allow them to add a new user or edit user from another group.
	if ($user_rights['group_id'] != "") {
		//If we are not editing someone in our group, redirect back to previous page
		$is_in_group = db_result(db_query("select count(1) from redcap_user_rights where project_id = $project_id
										   and username = '".db_escape($user)."' and group_id = '".$user_rights['group_id']."'"),0);
		if ($is_in_group == 0) {
			//User not in our group, so give error
			exit('');
		}
	}

	// Don't allow Table-based auth users to be added if don't already exist in redcap_auth. They must be created in Control Center first.
	$this_user_rights = UserRights::getPrivileges($project_id, $user);
	$isAlreadyUserinProject = isset($this_user_rights[$project_id][$user]);
	if ($auth_meth == "table" && !$isAlreadyUserinProject && !Authentication::isTableUser($user))
	{
        print  "<div class='red'>
                <img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_03']}:</b><br><br>
                {$lang['rights_104']} \"<b>".RCView::escape($user)."</b>\" {$lang['rights_105']} ";
        if (!$super_user) {
            print  $lang['rights_146'];
        } else {
            print  "{$lang['rights_107']}
                <a href='".APP_PATH_WEBROOT."ControlCenter/create_user.php' target='_blank'
                    style='text-decoration:underline;'>{$lang['rights_108']}</a>
                {$lang['rights_109']}";
        }
        print  "</div>";
        exit;
	}

}



if ($isRole) {
	// Query for role
	$q = db_query("select * from redcap_user_roles where project_id = $project_id and role_id = '".db_escape($role_id)."' limit 1");
} else {
	// Query for user
	$q = db_query("select * from redcap_user_rights where project_id = $project_id and username = '".db_escape($user)."' limit 1");
}
// Set flag if a new user
$new_user = (!db_num_rows($q));




if (!$new_user)
{
	// User Messaging: Warning if user being deleted is part of conversations associated with this project
	$userMsgConvs = "";
	if ($user_messaging_enabled && !$isRole && $user != USERID)
	{
		// See if they're on any project conversations
		$convList = Messenger::getUserConvTitlesForProject($user, PROJECT_ID);
		if (!empty($convList)) {
			$userMsgConvs = "<hr>" . $lang['messaging_02'] . 
							"<br><br><b>" . $lang['messaging_03'] . "</b><br> - \"" . 
							implode("\"<br> - \"", $convList) . "\"";
		}
	}
	
	## EXISTING USER/ROLE
	// Set DELETE user/role javascript
	$deleteUserJs = "var delUserAction = function(){
						$('form#user_rights_form input[name=\'submit-action\']').val('".($isRole ? 'delete_role' : 'delete_user')."');
						saveUserFormAjax();
					};
					simpleDialog('".js_escape($isRole ? $lang['rights_192'] :
						(($user == USERID ? $lang['rights_194'] : $lang['rights_193']).(!MobileApp::userHasInitializedProjectInApp($user, PROJECT_ID) ? '' :
						RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['rights_313'])).$userMsgConvs)).
						"','".js_escape(($isRole ? $lang['rights_190'] : $lang['rights_191']).$lang['questionmark'])."',null,550,null,'".js_escape($lang['global_53'])."',delUserAction,'".js_escape($isRole ? $lang['rights_190'] : $lang['rights_191'])."');";
	// Existing user/role
	if ($isRole) {
		$context_msg =  RCView::img(array('src'=>'vcard_edit.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), " {$lang['rights_157']} \"<b>".RCView::escape($role_name)."</b>\"");
		$submit_action = "edit_role";
		// To check if we can DELETE a role, get all user rights as array. Add a sub-array of users to each role that are assigned to it.
		$roleHasUsers = $roleHasUsersOtherDAG = false;
		foreach (UserRights::getRightsAllUsers(false) as $this_user=>$attr) {
			// Set flag if the user in this loop is assigned to a role
			if (is_numeric($attr['role_id']) && $attr['role_id'] == $role_id) {
				// Yes, at least one user is in this role
				$roleHasUsers = true;
				// If the user loading this page is in a DAG *and* users from another DAG or users not in a DAG are assigned to this role, set the flag to TRUE
				if ($user_rights['group_id'] != "" && (!is_numeric($attr['group_id'])
					|| (is_numeric($attr['group_id']) && $attr['group_id'] != $user_rights['group_id']))) {
					$roleHasUsersOtherDAG = true;
				}
			}
		}
		if ($roleHasUsers) {
			// Prevent user from deleting the role since it has users in it
			$deleteUserJs = "simpleDialog('".js_escape($lang['rights_164'])."','".js_escape($lang['rights_205'])."');";
		}
		// If the user loading this page is in a DAG *and* users from another DAG are assinged to this role, then prevent user from editing it
		if ($roleHasUsersOtherDAG) {
			// STOP HERE and prevent user from editing this role
			print 	RCView::div(array('class'=>'yellow', 'style'=>''),
						RCView::img(array('src'=>'exclamation_orange.png')) .
						RCView::b($lang['global_03'] . $lang['colon'] . " " . $lang['rights_223'] . " \"".RCView::escape($role_name)."\"") .
						RCView::div(array('style'=>'margin-top:10px;'), $lang['rights_224'])
					);
			exit;
		}

	} else {
		$context_msg =  RCView::img(array('src'=>'user_edit2.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), " {$lang['rights_09']} \"<b>".RCView::escape($user)."</b>\"");
		$submit_action = "edit_user";
	}
	$submit_buttons =  "add_user_dialog_btns =
						[{ text: '".js_escape($isRole ? $lang['rights_190'] : $lang['rights_191'])."', click: function() {
							$deleteUserJs
						}},
						".
						// Copy role button
						(!$isRole ? '' : "
							{ text: '".js_escape($lang['rights_211'])."', click: function() {
								copyRoleName('".htmlspecialchars(RCView::escape($role_name), ENT_QUOTES)."')
							}},"
						)."
						{ text: '".js_escape($lang['global_53'])."', click: function() {
							$('#editUserPopup').dialog('destroy');
						}},
						{text: '".js_escape($lang['report_builder_28'])."', click: function() {
							saveUserFormAjax();
						}}];";
	$submit_text = $lang['report_builder_28'];
	$context_msg_color = "blue";
	//Get variable for pre-filling checkboxes
	$this_user = db_fetch_assoc($q);
	$data_export_tool = $this_user['data_export_tool'];
	$data_import_tool = $this_user['data_import_tool'];
	$data_comparison_tool = $this_user['data_comparison_tool'];
	$data_logging = $this_user['data_logging'];
	$file_repository = $this_user['file_repository'];
	$double_data = $this_user['double_data'];
	$user_rights1 = $this_user['user_rights'];
	$expiration = $this_user['expiration'];
	$group_id = $this_user['group_id'];
	$lock_record = $this_user['lock_record'];
	$lock_record_multiform = $this_user['lock_record_multiform'];
	$lock_record_customize = $this_user['lock_record_customize'];
	$data_access_groups = $this_user['data_access_groups'];
	$graphical = $this_user['graphical'];
	$reports1 = $this_user['reports'];
	$chbx_email_newuser = "";
	$design = $this_user['design'];
	$dts = $this_user['dts'];
	$calendar = $this_user['calendar'];
	$record_create = $this_user['record_create'];
	$record_rename = $this_user['record_rename'];
	$record_delete = $this_user['record_delete'];
	$participants = $this_user['participants'];
	$data_quality_design = $this_user['data_quality_design'];
	$data_quality_execute = $this_user['data_quality_execute'];
	$data_quality_resolution = $this_user['data_quality_resolution'];
	$api_export = $this_user['api_export'];
	$api_import = $this_user['api_import'];
	$mobile_app = $this_user['mobile_app'];
	$mobile_app_download_data = $this_user['mobile_app_download_data'];
	$random_setup = $this_user['random_setup'];
	$random_dashboard = $this_user['random_dashboard'];
	$random_perform = $this_user['random_perform'];
	$realtime_webservice_mapping = $this_user['realtime_webservice_mapping'];
	$realtime_webservice_adjudicate = $this_user['realtime_webservice_adjudicate'];
	//Loop through data entry forms and parse their values
	$dataEntryArr = explode("][", substr(trim($this_user['data_entry']), 1, -1));
	foreach ($dataEntryArr as $keyval)
	{
		list($key, $value) = explode(",", $keyval, 2);
		$this_user["form-".$key] = $value;
	}
	unset($this_user['data_entry']);


}

// New user/role
else
{
	if ($isRole) {
		// New role
		$context_msg =  RCView::img(array('src'=>'vcard_add.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), " {$lang['rights_159']} \"<b>".RCView::escape($role_name)."</b>\"");
		$submit_action = "add_role";
	} else {
		// New user
		$context_msg =  RCView::img(array('src'=>'user_add2.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), " {$lang['rights_11']} \"<b>".RCView::escape($user)."</b>\"");
		$submit_action = "add_user";

		## CUSTOM USERNAME VERIFICATION SCRIPT (FOR EXTERNAL AUTHENTICATION)
		// If custom PHP script is specified in Control Center, call the custom validation function.
		// If a message is returned, then output the message in a red div and do an EXIT().
		if (!Authentication::isTableUser($user)) {
			Hooks::call('redcap_custom_verify_username', array($user));
		}
	}
	$submit_buttons =  "add_user_dialog_btns =
						[{ text: '".js_escape($lang['global_53'])."', click: function() {
							$('#editUserPopup').dialog('destroy');
						}},
						{text: '".js_escape($isRole ? $lang['rights_158'] : $lang['rights_187'])."', click: function() {
							saveUserFormAjax();
						}}];";
	$submit_text = ($isRole ? $lang['rights_158'] : $lang['rights_187']);
	$context_msg_color = "darkgreen";
	//Set variables to default for new user
	$data_export_tool = 2;
	$data_import_tool = 0;
	$data_comparison_tool = 0;
	$data_logging = 0;
	$file_repository = 1;
	$double_data = 0;
	$user_rights1 = 0;
	$expiration = '';
	$group_id = '';
	$lock_record = 0;
	$lock_record_multiform = 0;
	$lock_record_customize = 0;
	$data_access_groups = 0;
	$graphical = 1;
	$reports1 = 1;
	$design = 0;
	$dts = 0;
	$calendar = 1;
	$record_create = 1;
	$record_rename = 0;
	$record_delete = 0;
	$participants = 1;
	$data_quality_design = 0;
	$data_quality_execute = 0;
	$data_quality_resolution = 1;
	$api_export = 0;
	$api_import = 0;
	$mobile_app = 0;
	$mobile_app_download_data = 0;
	$random_setup = 0;
	$random_dashboard = 0;
	$random_perform = ($randomization ? 1 : 0);
	$realtime_webservice_mapping = 0;
	$realtime_webservice_adjudicate = 0;
	//If we already have this new user's email address on file, provide the ability to notify them of their project access via email
	$chbx_email_newuser = db_result(db_query("select user_email from redcap_user_information where username = '".db_escape($user)."' and username != ''"),0);
	if ($chbx_email_newuser != "") {
		$chbx_email_newuser =  "<div style='margin:20px 0 0;position: relative;top:6px;z-index:106;color:#505050;width:160px;font-weight:bold;font-family:Verdana,Arial;font-size:11px;text-align:center;background:#eee;padding:2px;border:1px solid #bbb;border-bottom-width: 0px;'>
									{$lang['rights_202']}
								</div>
								<div style='position: relative;border:1px solid #bbb;background:#eee;padding:10px 14px;'>
									<img src='".APP_PATH_IMAGES."email.png'>&nbsp;&nbsp;{$lang['rights_112']}
									&nbsp;<input type='checkbox' name='notify_email' checked>
								</div>";
	}
}

// Instructions
print 	RCView::div(array('style'=>'margin-bottom:10px;'),
			"{$lang['rights_44']} \"$submit_text\" {$lang['rights_45']}"
		);


// Show message if adding/editing user
print 	RCView::div(array('class'=>$context_msg_color,'style'=>'max-width:1000px;text-align:center;'),
			// "Adding new user" msg
			$context_msg
		);
		
// Display note about editing a super user's rights
if (!$isRole && User::isSuperUser($user)) {
	print 	RCView::div(array('class'=>"yellow",'style'=>'max-width:1000px;'),
				// "User is super user" msg
				$lang['rights_353']
			);
}

// Display add/edit user/role form
print  "<form id='user_rights_form' name='user_rights_form' method='post' action='".APP_PATH_WEBROOT."UserRights/index.php?pid=$project_id'>";

// Hide dialog attributes inside hidden divs that will be using by JavaScript (i.e. dialog title)
print 	RCView::div(array('class'=>'hidden'),
			RCView::div(array('id'=>'dialog_title'), $context_msg) .
			// Submit action (add/edit user/role)
			RCView::hidden(array('name'=>'submit-action', 'value'=>$submit_action)) .
			// Submit buttons
			RCView::div(array('id'=>'submit-buttons'),
				$submit_buttons
			)
		);

// Begin table
print  "<table cellpadding=0 cellspacing=15 align='center' width=100%>
		<tr><td valign='top' style='width:350px;'>
			<div align='left' style='width:100%'>
			<div style='position: relative;top:6px;z-index:106;color:#505050;width:140px;font-weight:bold;font-family:Verdana,Arial;font-size:11px;text-align:center;background:#F2F2F2;padding:2px;border:1px solid #808080;border-bottom-width: 0px;'>
				{$lang['rights_198']}
			</div>
			<div style='width:325px;background:#F2F2F2;font-size:12px;padding:5px;border:1px solid #808080;position:relative;'>
			<br>
			<table id='user-rights-left-col' style='font-size:12px;'>";

if ($isRole) {
	// Edit nole name
	if (!$new_user) {
		print  "<tr>
					<td valign='top' colspan='2' style='padding-bottom:5px;'>
						<img src='".APP_PATH_IMAGES."vcard.png' >
						&nbsp;&nbsp;{$lang['rights_199']}
						<input type='text' value=\"".RCView::escape($role_name)."\" class='x-form-text x-form-field' style='margin:0 0 5px 8px;width:150px;' name='role_name_edit' onblur=\"$(this).val($(this).val().trim()); if ($(this).val()=='') simpleDialog('".js_escape($lang['rights_358'])."',null,null,null,function(){ $('input[name=role_name_edit]').focus(); },'Close')\">
					</td>
				</tr>";
	}
} else {
	// Expiration Date (users only)
	print  "<tr>
				<td valign='top' style='padding-bottom:5px;'>
					<i class=\"far fa-calendar-times\"></i>&nbsp;&nbsp;{$lang['rights_54']}
					<div style='font-family:Verdana,Arial;font-size:10px;color:#777;margin-left:20px;'><i>{$lang['rights_55']}</i></div>
				</td>
				<td valign='top' style='padding-top:5px;'>
					<!-- hidden input to get focus on dialog open -->
					<input type='text' class='ui-helper-hidden-accessible'>
					<input type='text' value='".DateTimeRC::format_ts_from_ymd($expiration)."' class='x-form-text x-form-field' style='width:70px;' maxlength='10' id='expiration' name='expiration' onchange=\"redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);\" onkeydown='if(event.keyCode == 13) return false;'>
					<span class='df' style='padding-left:5px;'>(".DateTimeRC::get_user_format_label().")</span>
				</td>
			</tr>";
}


print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #888;padding:4px 0 8px;color:#800000;font-size:11px;'>
				{$lang['rights_299']}
			</td>
		</tr>";

// Project Setup/Design
print "<tr><td valign='top'><i class=\"fas fa-tasks\"></i>&nbsp;&nbsp;{$lang['rights_135']}</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='design' ";
if ($design == 1) print "checked";
print " onclick=\"$('.ext_mod_user_right_item input.no-require-perm').prop('checked',$(this).prop('checked'));\"> </td></tr>";

//User Rights
print "<tr><td valign='top'><i class=\"fas fa-user\"></i>&nbsp;&nbsp;{$lang['app_05']}</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='user_rights' ";
if ($user_rights1 == 1) print "checked";
print "> </td></tr>";

//Data Access Groups
print "<tr><td valign='top' style='padding-bottom:10px;'><i class=\"fas fa-users\"></i>&nbsp;&nbsp;{$lang['global_22']}</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='data_access_groups' ";
if ($data_access_groups == 1) print "checked";
print "> </td></tr>";

print  "<tr>
			<td valign='top' colspan='2' style='line-height:12px;color:#800000;border-top:1px solid #888;padding-top:4px;padding-bottom:7px;font-size:11px;'>
				{$lang['rights_301']}
			</td>
		</tr>";

//Data Export rights
print "<tr><td valign='top' style='width:180px;'>
			<i class=\"fas fa-file-export\"></i>&nbsp;&nbsp;{$lang['data_export_tool_186']}
			<div style='line-height:12px;padding:4px 4px 4px 22px;text-indent:-8px;font-size:11px;color:#999;font-family:tahoma;'>
				* {$lang['data_export_tool_181']}
			</div>
		</td>
		<td style='padding-top:2px;' valign='top' style='font-family:Verdana,Arial;font-size:11px;color:#808080;'>";
print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_export_tool' value='0' "; if ($data_export_tool == 0) print "checked"; print "> {$lang['rights_47']}</div>";
print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_export_tool' value='2' "; if ($data_export_tool == 2) print "checked"; print "> {$lang['rights_48']}*</div>";
print "<div style='line-height:13px;margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_export_tool' value='3' "; if ($data_export_tool == 3) print "checked"; print "> {$lang['data_export_tool_182']}</div>";
print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_export_tool' value='1' "; if ($data_export_tool == 1) print "checked"; print "> {$lang['rights_49']}</div>";
print "</td></tr>";

// Reports & Report Builder
print "<tr>
		<td valign='top'>
			<i class=\"fas fa-search\"></i>&nbsp;&nbsp;{$lang['rights_356']}
			<div style='line-height:12px;padding:0px 0px 4px 22px;text-indent:-8px;font-size:11px;color:#999;font-family:tahoma;'>
				&nbsp; {$lang['report_builder_130']}
			</div>
		</td>
		<td valign='top' style='padding-top:2px;'> <input type='checkbox' name='reports' ";
if ($reports1 == 1) print "checked";
print  ">
		</td>
	</tr>";

//Graphical Data View & Stats
if ($enable_plotting > 0) {
	print "<tr><td valign='top' style='padding-bottom:5px;'><img src='".APP_PATH_IMAGES."chart_bar.png'>&nbsp;&nbsp;{$lang['report_builder_78']}</td><td valign='top' style='padding-top:2px;padding-bottom:5px;'> <input type='checkbox' name='graphical' ";
	if ($graphical == 1) print "checked";
	print "> </td></tr>";
} else {
	print "<input type='hidden' name='graphical' value='$graphical'>";
}


print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #888;padding:4px 0 8px;color:#800000;font-size:11px;'>
				{$lang['rights_300']}
			</td>
		</tr>";

//Invite Participants rights
if ($surveys_enabled)
{
	print "<tr><td valign='top'><div style='text-indent: -32px;margin-left: 32px;'><i class=\"fas fa-chalkboard-teacher\" style='margin-right:2px;text-indent: -3px;'></i> ".$lang['app_24']."</div></td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='participants' ";
	if ($participants == 1) print "checked";
	print "> </td></tr>";
} else {
	print "<input type='hidden' name='participants' value='$participants'>";
}

//Calendar rights
print "<tr><td valign='top'><i class=\"far fa-calendar-alt\"></i>&nbsp;&nbsp;{$lang['app_08']}";
if ($scheduling) {
    print " ".$lang['rights_357'];
}
print "</td><td valign='top' style='padding-top:2px;'> <input type='checkbox' name='calendar' ";
if ($calendar == 1) print "checked";
print "> </td></tr>";

//Only show if a Double Data Entry project
if ($double_data_entry) {
	print "<tr><td valign='top'><i class=\"fas fa-users\"></i>&nbsp;&nbsp;{$lang['rights_50']} </td><td valign='top' style='padding-top:2px;font-family:Verdana,Arial;font-size:11px;color:#808080;'>
			<input type='radio' name='double_data' value='0' "; if ($double_data == 0) print "checked";
	print "> {$lang['rights_51']}<br>";
	//If data entry person #1 or #2 are already designated, do not allow user to designate another person as #1 or #2.
	$sql = "(select 1 from redcap_user_roles where double_data = '1' and project_id = $project_id " . ($isRole ? "and role_id != $role_id" : "") . " limit 1)
			union
			(select 1 from redcap_user_rights where double_data = '1' and project_id = $project_id and role_id is null " . ($isRole ? "" : "and username != '".db_escape($user)."'") . " limit 1)";
	$q1 = db_query($sql);
	if (!db_num_rows($q1)) {
		print "<input type='radio' name='double_data' value='1' ";
		if ($double_data == 1) print "checked";
		print "> {$lang['rights_52']} #1<br>";
	}
	$sql = "(select 1 from redcap_user_roles where double_data = '2' and project_id = $project_id " . ($isRole ? "and role_id != $role_id" : "") . " limit 1)
			union
			(select 1 from redcap_user_rights where double_data = '2' and project_id = $project_id and role_id is null " . ($isRole ? "" : "and username != '".db_escape($user)."'") . " limit 1)";
	$q2 = db_query($sql);
	if (!db_num_rows($q2)) {
		print "<input type='radio' name='double_data' value='2' "; if ($double_data == 2) print "checked";
		print "> {$lang['rights_52']} #2</td></tr>";
	}
} else {
	//Leave double_data as hidden field if not a Double Data Entry project
	print "<input type='hidden' name='double_data' value='$double_data'>";
}

print "<tr><td valign='top'><i class=\"fas fa-file-import\"></i>&nbsp;&nbsp;{$lang['app_01']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='data_import_tool' ";	if ($data_import_tool == 1) print "checked";
print "> </td></tr>
	<tr><td valign='top'><i class=\"fas fa-not-equal\"></i>&nbsp;&nbsp;{$lang['app_02']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='data_comparison_tool' ";	if ($data_comparison_tool == 1) print "checked";
print "> </td></tr>
	<tr><td valign='top'><i class=\"fas fa-receipt\" style='margin-left:2px;margin-right:2px;'></i>&nbsp;&nbsp;{$lang['app_07']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='data_logging' ";	if ($data_logging == 1) print "checked";
print "> </td></tr>
	<tr><td valign='top'><i class=\"fas fa-folder-open\"></i>&nbsp;&nbsp;{$lang['app_04']} </td><td style='padding-top:2px;' valign='top'> <input type='checkbox' name='file_repository' "; if ($file_repository == 1) print "checked";
print "> </td></tr>";

// Randomization
if ($randomization) {
	$randHelp = RCView::a(array('id' => 'randHelpLinkId', 'href' => 'javascript:;', 'onclick'=>"simpleDialog(null,null,'randHelpDialogId');", 'style' => 'font-family:tahoma;font-size:10px;text-decoration:underline;'), $lang['rights_145']);
	print  "<tr><td valign='top'>
				<i class=\"fas fa-random\"></i>&nbsp;&nbsp;{$lang['app_21']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;font-family:tahoma;'>$randHelp</div>
			</td>
			<td valign='top' style='padding-top:2px;'> <input type='checkbox' name='random_setup' ";
	if ($random_setup == 1) print "checked";
	print  "> {$lang['rights_142']}<br/>
			<input type='checkbox' name='random_dashboard' ";
	if ($random_dashboard == 1) print "checked";
	print "> {$lang['rights_143']}<br/>
			<input type='checkbox' name='random_perform' ";
	if ($random_perform == 1) print "checked";
	print  "> {$lang['rights_144']}</td></tr>";
}
else {
	print RCView::hidden(array('name' => 'random_setup', 'value' => $random_setup));
	print RCView::hidden(array('name' => 'random_dashboard', 'value' => $random_dashboard));
	print RCView::hidden(array('name' => 'random_perform', 'value' => $random_perform));
}

// Data Quality (design & execute rights are separate)
print  "<tr>
			<td valign='top'>
				<i class=\"fas fa-clipboard-check\"></i>&nbsp;&nbsp;{$lang['app_20']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;font-family:tahoma;'>
					<a href='javascript:;' style='font-family:tahoma;font-size:10px;text-decoration:underline;' onclick=\"
						$('#explainDataQuality').dialog({ bgiframe: true, title: '".js_escape($lang['dataqueries_100'])."', modal:true, width:550, buttons:{Close:function(){\$(this).dialog('close');}}});
					\">{$lang['dataqueries_100']}</a>
				</div>
			</td>
			<td valign='top' style='padding-top:2px;'>
				<input type='checkbox' name='data_quality_design' ".($data_quality_design == 1 ? "checked" : "").">
				{$lang['dataqueries_40']}<br>
				<input type='checkbox' name='data_quality_execute' ".($data_quality_execute == 1 ? "checked" : "").">
				{$lang['dataqueries_41']}</td>
		</tr>";

// Data Quality resolution
if ($data_resolution_enabled == '2') {

	print "<tr><td valign='top' style='width:180px;'>
				<i class='fas fa-comments'></i>&nbsp;&nbsp;{$lang['dataqueries_137']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;font-family:tahoma;'>
					<a href='javascript:;' style='font-family:tahoma;font-size:10px;text-decoration:underline;' onclick=\"
						$('#explainDRW').dialog({ bgiframe: true, title: '".js_escape($lang['dataqueries_155'])."', modal:true, width:550, buttons:{Close:function(){\$(this).dialog('close');}}});
					\">{$lang['dataqueries_155']}</a>
				</div>
			</td>
			<td style='padding-top:2px;' valign='top' style='font-family:Verdana,Arial;font-size:11px;color:#808080;'>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='0' "; if ($data_quality_resolution == '0') print "checked"; print "> {$lang['rights_47']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='1' "; if ($data_quality_resolution == '1') print "checked"; print "> {$lang['dataqueries_143']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='4' "; if ($data_quality_resolution == '4') print "checked"; print "> {$lang['dataqueries_289']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='2' "; if ($data_quality_resolution == '2') print "checked"; print "> {$lang['dataqueries_138']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='5' "; if ($data_quality_resolution == '5') print "checked"; print "> {$lang['dataqueries_290']}</div>";
	print "<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='data_quality_resolution' value='3' "; if ($data_quality_resolution == '3') print "checked"; print "> {$lang['dataqueries_139']}</div>";
	print "</td></tr>";
} else {
	print "<input type='hidden' name='data_quality_resolution' value='$data_quality_resolution'>";
}

// API
if ($api_enabled) {
	$apiHelp = RCView::a(array('id' => 'apiHelpLinkId', 'href' => 'javascript:;', 'onclick'=>"simpleDialog(null,null,'apiHelpDialogId');", 'style' => 'font-family:tahoma;font-size:10px;text-decoration:underline;'), $lang['rights_141']);
	print  "<tr><td valign='top'>
				<i class=\"fas fa-laptop-code\"></i>&nbsp;&nbsp;{$lang['setup_77']}
				<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;font-family:tahoma;'>$apiHelp</div>
			</td>
			<td valign='top' style='padding-top:2px;'> <input type='checkbox' name='api_export' ";
	if ($api_export == 1) print "checked";
	print  "> {$lang['rights_139']}<br/>
			<input type='checkbox' name='api_import' ";
	if ($api_import == 1) print "checked";
	print "> {$lang['rights_314']}</td></tr>";
}
else {
	print RCView::hidden(array('name' => 'api_export', 'value' => $api_export));
	print RCView::hidden(array('name' => 'api_import', 'value' => $api_import));
}

// DDP (only if enabled for whole system AND this project)
if (is_object($DDP) && (($DDP->isEnabledInSystem() && $DDP->isEnabledInProject()) || ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir())))
{
	$user_rights_super_users_only = $DDP->isEnabledInProjectFhir() ? $fhir_user_rights_super_users_only : $realtime_webservice_user_rights_super_users_only;
	?>
	<tr>
		<td valign="top" style="padding-top:8px;">
			<div style="margin-left:1.4em;text-indent:-1.4em;line-height: 13px;">
                <i class="fas fa-database" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo ($DDP->isEnabledInProjectFhir() ? $lang['ws_210'] : $lang['ws_51']) . " " . $DDP->getSourceSystemName() ?>
			</div>
		</td>
		<td valign="top" style="padding-top:8px;">
			<div style="margin-left:1.4em;text-indent:-1.4em;">
				<!-- Mapping rights -->
				<input type="checkbox" name="realtime_webservice_mapping" <?php if ($realtime_webservice_mapping == 1) echo 'checked' ?>
					<?php if (!$super_user && $user_rights_super_users_only) echo 'disabled'; ?>>
				<?php if (!$super_user && $user_rights_super_users_only) { ?>
					<input type="hidden" name="realtime_webservice_mapping" value="<?php echo $realtime_webservice_mapping ?>">
				<?php } ?>
				<?php echo $lang['ws_19'] ?>
			</div>
			<div style="margin-left:1.4em;text-indent:-1.4em;">
				<!-- Adjudication rights -->
				<input type="checkbox" name="realtime_webservice_adjudicate" <?php if ($realtime_webservice_adjudicate == 1) echo 'checked' ?>
					<?php if (!$super_user && $user_rights_super_users_only) echo 'disabled'; ?>>
				<?php if (!$super_user && $user_rights_super_users_only) { ?>
					<input type="hidden" name="realtime_webservice_adjudicate" value="<?php echo $realtime_webservice_adjudicate ?>">
				<?php } ?>
				<?php echo $lang['ws_20'] ?>
			</div>
		</td>
	</tr>
	<tr>
		<td valign="top" colspan="2" style="padding:0 0 10px 24px;font-family:tahoma;color:#800000;font-size:10px;">
			<div style="line-height:8px;<?php if ($realtime_webservice_user_rights_super_users_only) { ?>float:left;margin-right:40px;<?php } ?>">
				<a style='text-decoration:underline;font-size:10px;font-family:tahoma;' href='javascript:;' onclick="simpleDialog(null,null,'explainDDP'); return false;"><?php echo ($DDP->isEnabledInProjectFhir() ? $lang['ws_290'] : $lang['ws_36']) ?></a>
			</div>
			<?php if ($realtime_webservice_user_rights_super_users_only) { ?>
				<div style="float:left;">
					<?php echo $lang['rights_134'] ?>
				</div>
			<?php } ?>
			<div class="clear"></div>
		</td>
	</tr>
	<?php
} else {
	// Hide input fields to maintain values if setting is disabled at project level
	?>
	<input type="hidden" name="realtime_webservice_mapping" value="<?php echo $realtime_webservice_mapping ?>">
	<input type="hidden" name="realtime_webservice_adjudicate" value="<?php echo $realtime_webservice_adjudicate ?>">
	<?php
}

// DTS (only if enabled for whole system AND this project) - do NOT allow this for ROLES
if (!$isRole && $dts_enabled_global && $dts_enabled)
{
	?>
	<tr>
		<td valign="top">
			<div style="margin-left:1.4em;text-indent:-1.4em;">
                <i class="fas fa-database" style="text-indent: 0;"></i>&nbsp;&nbsp;<?php echo $lang["rights_132"] ?>
			</div>
		</td>
		<td valign="top" style="padding-top:2px;">
			<?php if ($super_user) { ?>
				<div style="margin-left:1.4em;text-indent:-1.4em;">
					<input type="checkbox" name="dts" <?php if ($dts == 1) echo 'checked' ?>>
					<span style="font-family:tahoma;color:#800000;font-size:10px;"><?php echo $lang['rights_134'] ?></span>
				</div>
			<?php } else { ?>
				<div style="margin-left:1.4em;text-indent:-1.4em;">
					<input type="checkbox" <?php if ($dts == 1) echo 'checked' ?> disabled="disabled">
					<input type="hidden" name="dts" value="<?php echo $dts ?>">
					<span style="font-family:tahoma;color:#800000;font-size:10px;"><?php echo $lang['rights_134'] ?></span>
				</div>
			<?php } ?>
		</td>
	</tr>
	<?php
}

// Mobile App
if ($mobile_app_enabled) {
	$appHelp = RCView::a(array('id' => 'apiHelpLinkId', 'href' => 'javascript:;', 'onclick'=>"simpleDialog(null,null,'appHelpDialogId',600);", 'style' => 'font-family:tahoma;font-size:10px;text-decoration:underline;'), $lang['rights_308']);
	print  "<tr>
				<td valign='top' colspan='2' style='border-top:1px solid #888;padding:4px 0 8px;color:#800000;font-size:11px;'>
					{$lang['rights_309']}
				</td>
			</tr>
			<tr>
				<td valign='top'>
					<i class=\"fas fa-tablet-alt\"></i>&nbsp;&nbsp;{$lang['global_118']}
					<div style='padding:0px 0 0px 18px;font-size:11px;color:#777;font-family:tahoma;'>$appHelp</div>
				</td>
				<td valign='top' style='padding-top:2px;'>
					<input type='checkbox' name='mobile_app' style='float:left;' onclick=\"if ($(this).prop('checked')) simpleDialog(null,null,'mobileAppEnableConfirm',600,function(){
						$('#user_rights_form input[name=mobile_app]').prop('checked', false);
					},'".js_escape($lang['global_53'])."',function(){
						$('#user_rights_form input[name=mobile_app]').prop('checked', true);
					},'".js_escape($lang['rights_305'])."');\" ".($mobile_app == 1 ? 'checked' : '').">
					<div style='width: 100px;padding: 1px 0 0 8px;float:left;line-height:12px;font-size:11px;color:#999;font-family:tahoma;'>
						{$lang['rights_307']}
					</div>
				</td>
			</tr>
			<tr>
				<td valign='top' style='line-height: 11px;font-size:11px;padding:10px 3px 10px 22px;'>
					{$lang['rights_306']}
				</td>
				<td valign='top' style='padding-top:12px;'>
					<div style='margin-left:1.4em;text-indent:-1.4em;'> <input type='checkbox' name='mobile_app_download_data' "; if ($mobile_app_download_data == '1'){ print "checked"; } print "></div>
				</td>
			</tr>";
}
else {
	print RCView::hidden(array('name' => 'mobile_app', 'value' => $mobile_app));
}

// Create/Rename/Delete Records
print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #888;padding:4px 0 8px;color:#800000;font-size:11px;'>
				{$lang['rights_119']}
				&nbsp;&nbsp;
				<a style='text-decoration:underline;font-size:10px;font-family:tahoma;' href='javascript:;' onclick='userRightsRecordsExplain(); return false;'>{$lang['rights_123']}</a>
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<i class=\"fas fa-plus-square\"></i>&nbsp;&nbsp;{$lang['rights_99']}
			</td>
			<td valign='top' style='padding-top:2px;'>
				<input type='checkbox' name='record_create' " . ($record_create == 1 ? "checked" : "") . ">
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<i class=\"fas fa-exchange-alt\"></i>&nbsp;{$lang['rights_100']}
			</td>
			<td valign='top' style='padding-top:2px;'>
				<input type='checkbox' name='record_rename' " . ($record_rename == 1 ? "checked" : "") . ">
			</td>
		</tr>
		<tr>
			<td valign='top' style='padding:2px 0 10px;'>
				<i class=\"fas fa-minus-square\"></i>&nbsp;&nbsp;{$lang['rights_101']}
			</td>
			<td valign='top' style='padding:2px 0 10px;'>
				<input type='checkbox' name='record_delete' " . ($record_delete == 1 ? "checked" : "") . ">
			</td>
		</tr>";

// Lock Record
print  "<tr>
			<td valign='top' colspan='2' style='border-top:1px solid #888;padding:4px 0 8px;color:#800000;font-size:11px;'>
				{$lang['rights_130']}
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'>
				    <i class=\"fas fa-lock\" style='text-indent:0;'></i>&nbsp;&nbsp;{$lang['app_11']}
				</div>
			</td>
			<td valign='top' style='padding-top:6px;'>
				<input type='checkbox' name='lock_record_customize' "; if ($lock_record_customize == 1){print "checked";} print ">
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><i class=\"fas fa-unlock-alt\" style='text-indent:0;'></i>&nbsp;&nbsp;{$lang['rights_97']} {$lang['rights_371']}</div>
				<div style='line-height:12px;padding:4px 0 4px 22px;font-size:11px;color:#777;font-family:tahoma;'>
					{$lang['rights_113']}
					<div style='padding:7px 0 4px;'>
						<i class='fas fa-film'></i>
						<a onclick=\"popupvid('locking02.mp4')\" style='color:#3E72A8;font-size:11px;font-family:tahoma;' href='javascript:;'>{$lang['rights_131']}</a>
					</div>
				</div>
			</td>
			<td valign='top' style='padding-top:2px;'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='lock_record' value='0' " . ($lock_record == '0' ? "checked" : "") . " onclick=\"document.user_rights_form.lock_record_multiform.checked=false;\"> {$lang['global_23']}</div>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><input type='radio' name='lock_record' value='1' " . ($lock_record == '1' ? "checked" : "") . "> {$lang['rights_115']}</div>
				<div style='line-height:13px;margin-left:1.4em;text-indent:-1.4em;'>
					<input type='radio' name='lock_record' value='2' " . ($lock_record == '2' ? "checked" : "") . " onclick=\"
						if (this.checked) {
							setTimeout(function(){
								simpleDialog('" . js_escape($lang['rights_136']) . "','" . js_escape($lang['global_03']) . "');
							},50);
						}
					\"> {$lang['rights_116']}<br>
					<a style='text-decoration:underline;font-size:10px;font-family:tahoma;' href='javascript:;' onclick='esignExplainLink(); return false;'>{$lang['rights_117']}</a>
				</div>
			</td>
		</tr>
		<tr>
			<td valign='top'>
				<div style='margin-left:1.4em;text-indent:-1.4em;'><i class=\"fas fa-unlock-alt\" style='text-indent:0;'></i>&nbsp;&nbsp;{$lang['rights_370']}</div>
			</td>
			<td valign='top' style='padding-top:2px;'>
				<div style='margin-left:1.4em;text-indent:-1.4em;margin-top:4px;'> <input type='checkbox' name='lock_record_multiform' "; if ($lock_record_multiform == '1'){ print "checked"; } print "></div>
			</td>
		</tr>
		<tr>
			<td colspan='2' valign='top' class='py-2 pl-2 pr-0 fs11' style='color:#800000;line-height:1.1;'>
				{$lang['rights_372']}
			</td>
		</tr>";

print "</td>
	</tr>";

print  "</table>";

print "</td><td valign='top'>";





// Show all FORMS for setting rights level for each
print "<div align='left' style='width:350px;'>
		<div style='position: relative;top:6px;z-index:106;color:#800000;width:130px;font-weight:bold;font-family:Verdana,Arial;font-size:11px;text-align:center;background:#F2F2F2;padding:2px;border:1px solid #FFA3A3;border-bottom-width: 0px;'>
			{$lang['rights_59']}
		</div>
		<div style='background:#F2F2F2;font-size:12px;border:1px solid #FFA3A3;position:relative;'>
		<table id='form_rights' cellpadding=0 cellspacing=0 style='width:100%;font-size:11px;color:#800000;font-family:Verdana,Arial;'>
		<tr>
			<td valign='top' colspan='3' style='padding:10px 12px 8px;line-height:12px;'>
				<i>{$lang['rights_221']}</i>
			</td>
		</tr>
		<tr>
			<td valign='top' style='border-right:1px solid #FFA3A3;'>&nbsp;</td>
			<td valign='top' style='font-size:10px;text-align:left;width:205px;'>
				<div style='float:left;padding:2px 8px;white-space:normal;width:45px;line-height: 12px;'>{$lang['rights_47']}</div>
				<div style='float:left;padding:2px 8px;white-space:normal;width:40px;line-height: 12px;'>{$lang['rights_61']}</div>
				<div style='float:left;padding:2px 8px;white-space:normal;width:44px;line-height: 12px;'>{$lang['rights_138']}</div>";
if ($enable_edit_survey_response && !empty($Proj->surveys))
{
	print 		"<div style='float:left;padding:2px 8px;white-space:normal;width:70px;line-height: 12px;'>{$lang['rights_137']}</div>";
}
print  "		<div style='clear:both;'></div>
			</td>
		</tr>";

// Loop through all forms
foreach ($Proj->forms as $form_name=>$form_attr)
{
	// If editng a super user that does not have any form-level rights (because it didn't get added automatically), then set default to full for each form
	if (!isset($this_user["form-".$form_name])) {
		$this_user["form-".$form_name] = ($enable_edit_survey_response && isset($form_attr['survey_id']) ? "3" : "1");
	}
	// Add row
	print  "<tr>
				<td valign='middle' class='derights1'>
					".RCView::escape($form_attr['menu'])."
					" . (isset($form_attr['survey_id']) ? " &nbsp;<span style='color:#666;font-size:10px;font-family:tahoma;'>({$lang['global_59']})</span>" : "") . "
				</td>
				<td valign='middle' class='nobr derights2'>
					<input type='radio' onclick=\"$(this).parent().find('input[type=checkbox]').prop('checked',false);\" name='form-" . $form_name . "' value='0' ";
	if ($this_user["form-".$form_name] == "0") print "checked";
	print 			">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='radio' name='form-" . $form_name . "' value='2' ";
	if ($this_user["form-".$form_name] == "2") print "checked";
	print 			">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='radio' name='form-" . $form_name . "' value='1' ";
	if (($this_user["form-".$form_name] == "1" || $this_user["form-".$form_name] == "3") || $new_user) print "checked";
	print 			">";
	// If this form is used as a survey, render checkbox for setting edit/delete response rights (value=3)
	if ($enable_edit_survey_response && isset($form_attr['survey_id']))
	{
		print 		"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='checkbox' id='form-editresp-" . $form_name . "' name='form-editresp-" . $form_name . "' ";
		if ($this_user["form-".$form_name] == "3") print "checked";
		print 		">";
	}
	print "		</td>
			</tr>";
}

print "</table>
	</div>";
	
// External Modules: Display checkbox for each enabled module in a project in the Edit User dialog on the User Rights page
$modules = UserRights::getExternalModulesUserRightsCheckboxes();
if (!empty($modules)) 
{
	// Loop through modules to build checkbox HTML
	$moduleCheckboxes = $moduleAsteriskText = "";
	$countModsNotReqConfig = $countModsNoConfig = 0;
	$this_external_module_config = json_decode($this_user['external_module_config'], true);
	foreach ($modules as $module_prefix=>$attr) {
		$hasReqConfigRightsSaved = in_array($module_prefix, $this_external_module_config);
		$reqConfigRights = ($attr['require-config-perm'] == '1');
		$hasProjectConfig = ($attr['has-project-config'] == '1');
		$checked = ($hasProjectConfig && ((!$reqConfigRights && $design) || ($reqConfigRights && $hasReqConfigRightsSaved) || (SUPER_USER && !$isRole && USERID == $user))) ? "checked" : "";
		$disabled = (!SUPER_USER || !$reqConfigRights || !$hasProjectConfig) ? "disabled" : "";
		$reqConfigClass = $reqConfigRights ? "" : "no-require-perm";
		$disabledAsterisk = $reqConfigRights ? "" : "<span class='em-ast'>*</span>";
		$noConfigAsterisk = $hasProjectConfig ? "" : "<span class='em-ast'>**</span>";
		if (!$hasProjectConfig) {
			$reqConfigClass = $disabledAsterisk = "";
			$countModsNoConfig++;
		}
		if ($disabledAsterisk != "") $countModsNotReqConfig++;
		$moduleCheckboxes .= "<div class='ext_mod_user_right_item'>
								<input type='checkbox' class='$reqConfigClass' name='external_module_config[]' value='$module_prefix' $checked $disabled style='top:2px;position:relative;'>
								{$attr['name']}{$disabledAsterisk}{$noConfigAsterisk}
							  </div>";
	}
	// Display asterisk text
	if ($countModsNotReqConfig > 0) {
		$moduleAsteriskText .= "<div style='margin-top:10px;color:#c20808;font-size:10px;line-height:11px;'>".$lang['rights_327']."</div>";
	}
	if ($countModsNoConfig > 0) {
		$asteriskMargin = ($countModsNotReqConfig > 0) ? "2" : "10";
		$moduleAsteriskText .= "<div style='margin-top:{$asteriskMargin}px;color:#c20808;font-size:10px;line-height:11px;'>".$lang['rights_329']."</div>";
	}
	// Output box of modules as HTML
	print  "<div style='margin:20px 0 0;position: relative;top:6px;z-index:106;color:rgb(76, 92, 146);width:310px;font-weight:bold;font-family:Verdana,Arial;font-size:11px;text-align:center;background:#eee;padding:2px;border:1px solid #8495d0;border-bottom-width: 0px;'>
				{$lang['rights_326']}
			</div>
			<div style='position: relative;border:1px solid #8495d0;background:#eee;padding:10px 14px;'>
				<div style='font-size:11px;line-height:12px;margin-bottom:5px;'>{$lang['rights_328']}</div>
				$moduleCheckboxes
				$moduleAsteriskText
			</div>";
}

print "$chbx_email_newuser
	</div>";
print "</td></tr>
	</table>
	</div>
	<input type='hidden' name='user' value='".js_escape($isRole ? $role_id : htmlspecialchars($user, ENT_QUOTES))."'>
	<input type='hidden' name='role_name' value='".RCView::escape($role_name)."'>
	</form>";