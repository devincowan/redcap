<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = '0';

// Remove illegal characters (if somehow posted bypassing javascript)
$user = preg_replace("/[^a-zA-Z0-9-.@_]/", "", $_POST['username']);
if (!isset($_POST['username']) || $user != $_POST['username']) exit($response);
$user = $_POST['username'];

// Set and format expiration date
$expire = DateTimeRC::format_ts_to_ymd(trim($_POST['expiration']));

// Set role_id to NULL in table
$sql = "update redcap_user_rights set expiration = ".checkNull($expire)."
		where project_id = $project_id and username = '".db_escape($user)."'";
if (db_query($sql)) {
	// Logging for user assignment
	Logging::logEvent($sql,"redcap_user_rights","update",$user,"user = '$user'","Edit user expiration");
	// Return success response
	$response = UserRights::renderUserRightsRolesTable();
}

print $response;
