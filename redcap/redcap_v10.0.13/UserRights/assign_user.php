<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Always display the User Rights table as the last thing this script performs (this allows us to also do this if
// using the custom user verification script).
register_shutdown_function('shutDownAssignUser');
function shutDownAssignUser() {
	print UserRights::renderUserRightsRolesTable();
}

// Remove illegal characters (if somehow posted bypassing javascript)
$user = preg_replace("/[^a-zA-Z0-9-'\s\.@_]/", "", $_POST['username']);
if (!isset($_POST['username']) || !is_numeric($_POST['role_id']) || $user != $_POST['username']) exit('');
$user = $_POST['username'];
$role_id = $_POST['role_id'];

//If the person using this application is in a Data Access Group, do not allow them to add a new user or edit user from another group.
if ($user_rights['group_id'] != "") {
	//If we are not editing someone in our group, redirect back to previous page
	$is_in_group = db_result(db_query("select count(1) from redcap_user_rights where project_id = $project_id
									   and username = '".db_escape($user)."' and group_id = '".$user_rights['group_id']."'"),0);
	if ($is_in_group == 0) {
		//User not in our group, so give error
		exit('<b>ERROR: User cannot be assigned because they do not belong to your Data Access Group!</b>');
	}
}

// Don't allow Table-based auth users to be added if don't already exist in redcap_auth. They must be created in Control Center first.
if ($auth_meth == "table" && !Authentication::isTableUser($user))
{
	print  "<div class='red' style='margin:20px 0;'>
				<img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_03']}:</b><br><br>
				{$lang['rights_104']} \"<b>$user</b>\" {$lang['rights_105']} ";
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


// Get all roles
$roles = UserRights::getRoles();


// REMOVE USER FROM ROLE
if ($role_id == '0')
{
	// Get user's current role_id
	$old_role_id = db_result(db_query("select role_id from redcap_user_rights where project_id = $project_id and username = '".db_escape($user)."'"), 0);
	// Get role name of old role
	$this_role_rights = $roles[$old_role_id];
	$role_name = $this_role_rights['role_name'];
	// Set role_id to NULL and give the user the exact same rights as the role they were removed from in order to maintain continuity of privileges
	unset($this_role_rights['role_name'], $this_role_rights['project_id']);
	$sqla = array();
	foreach ($this_role_rights as $key=>$val) $sqla[] = "$key = ".checkNull($val);
	$sql = "update redcap_user_rights set role_id = null, " . implode(", ", $sqla) . "
			where project_id = $project_id and username = '".db_escape($user)."'";
	if (db_query($sql)) {
		// Double data entry: If user's old role was DDE 1 or 2, then set their double_data to NULL to prevent conflict
		if ($double_data_entry) {
			$sql1 = "select 1 from redcap_user_roles where double_data is not null and role_id = $old_role_id";
			if (db_num_rows(db_query($sql1))) {
				$sql2 = "update redcap_user_rights set double_data = null
						 where project_id = $project_id and username = '".db_escape($user)."'";
				db_query($sql2);
			}
		}
		// Logging for user assignment
		Logging::logEvent($sql,"redcap_user_rights","update",$user,"user = '$user',\nrole = '$role_name'","Remove user from role");
		print	RCView::div(array('class'=>'userSaveMsg darkgreen', 'style'=>'text-align:center;'),
					RCView::img(array('src'=>'tick.png')) .
					" {$lang['global_17']} \"<b>".RCView::escape($user)."</b>\" {$lang['rights_176']}"
				);
	}
}

// ASSIGN USER TO ROLE
else
{
	## CUSTOM USERNAME VERIFICATION SCRIPT (FOR EXTERNAL AUTHENTICATION)
	// If custom PHP script is specified in Control Center, call the custom validation function.
	// If a message is returned, then output the message in a red div and do an EXIT().
	if (!Authentication::isTableUser($user)) {
		Hooks::call('redcap_custom_verify_username', array($user));
	}

	// Assign user to role
	$sql = "insert into redcap_user_rights (project_id, username, role_id) values ($project_id, '".db_escape($user)."', ".checkNull($role_id).")
			on duplicate key update role_id = $role_id";
	if (db_query($sql)) {
		// Get role name of this role
		$role_name = $roles[$role_id]['role_name'];
		// Logging (if user was created)
		if (db_affected_rows() === 1) {
			Logging::logEvent($sql,"redcap_user_rights","insert",$user,"user = '$user'","Add user");
			// Email the user, if applicable
			if (isset($_POST['notify_email_role']) && $_POST['notify_email_role']) {
				//First need to get the email address of the user we're emailing
				$sql = "select user_firstname, user_lastname, user_email from redcap_user_information
						where username = '".db_escape($user)."' and user_email is not null";
				$q = db_query($sql);
				if (db_num_rows($q)) {
					$row = db_fetch_array($q);
					$email = new Message ();
					$emailContents = "
						<html><body style='font-family:arial,helvetica;font-size:10pt;'>
						{$lang['global_21']}<br /><br />
						{$lang['rights_88']} \"<a href=\"".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/index.php?pid=".PROJECT_ID."\">".strip_tags(str_replace("<br>", " ", label_decode($app_title)))."</a>\"{$lang['period']}
						{$lang['rights_89']} \"$user\", {$lang['rights_90']}<br /><br />
						".APP_PATH_WEBROOT_FULL."
						</body>
						</html>";
					$email->setTo($row['user_email']);
					$email->setFrom($user_email);
					$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
					$email->setSubject($lang['rights_122']);
					$email->setBody($emailContents);
					$email->send();
				}
			}
		}
		// Logging for user assignment
		Logging::logEvent($sql,"redcap_user_rights","insert",$user,"user = '$user',\nrole = '$role_name'","Assign user to role");
		print	RCView::div(array('class'=>'userSaveMsg darkgreen', 'style'=>'text-align:center;'),
					RCView::img(array('src'=>'tick.png')) .
					" {$lang['global_17']} \"<b>".RCView::escape($user)."</b>\"
					{$lang['rights_166']} \"<b>".RCView::escape($role_name)."</b>\"{$lang['period']}"
				);
	}
}
