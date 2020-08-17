<?php


// Config for non-project pages
require dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!SUPER_USER && !ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);

$usersInfo = User::getUserInfoByCriteria();

// Display table of users
if (!isset($_GET['download'])) 
{
	User::renderSponsorDashboard(true, array_keys($usersInfo));
}
// CSV download
else
{
	$userList = array();
	foreach ($usersInfo as $ui_id=>$row)
	{
		// CSV file export
		$userList[$ui_id] = array($row['username'],
							$row['user_firstname'],
							$row['user_lastname'],
							$row['user_email'],
							($row['user_suspended_time'] == '' ? $lang['control_center_149'] : $row['user_suspended_time']) ,
							($row['super_user'] ? $lang['design_100'] : ""),
							($row['user_sponsor'] == '' ? '' : $row['user_sponsor']),
							($row['user_inst_id'] == '' ? '' : $row['user_inst_id']),
							($row['user_comments'] == '' ? '' : $row['user_comments']),
							($row['user_expiration'] == '' ? $lang['control_center_149'] : $row['user_expiration']),
							$row['user_lastactivity'],
							$row['user_lastlogin']
					  );
		if (!$superusers_only_create_project) {
			$userList[$ui_id][] = ($row['allow_create_db'] ? $lang['design_100'] : "");
		}
	}
	// CSV download
	$filename = "UserList_".date("Y-m-d_Hi").".csv";
	// Open connection to create file in memory and write to it
	$fp = fopen('php://memory', "x+");
	$headers = array($lang['global_11'],
					$lang['global_41'],
					$lang['global_42'],
					$lang['control_center_56'],
					$lang['control_center_138'],
					$lang['control_center_57'],
					$lang['user_72'],
					$lang['control_center_236'],
					$lang['dataqueries_146'],
					$lang['rights_335'],
					$lang['control_center_148'],
					$lang['control_center_429']
			  );
	if (!$superusers_only_create_project) {
		$headers[] = $lang['control_center_4701'];
	}
	// Headers
	fputcsv($fp, $headers);
	// Loop and write each line to CSV
	foreach ($userList as $line) {
		fputcsv($fp, $line);
	}
	// Open file for reading and output to user
	fseek($fp, 0);
	// Output to file
	header('Pragma: anytextexeptno-cache', true);
	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=$filename");
	print addBOMtoUTF8(stream_get_contents($fp));
}
