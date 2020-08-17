<?php


// include config file
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Make sure we have the action
if (!isset($_GET['action'])) exit('');
// Get the sponsor's users
$sponsorUsers = User::getSponsorUserAttributes(USERID);
// Make sure this user is either an admin or a sponsor
if (!SUPER_USER && empty($sponsorUsers)) exit('');
if (!(isset($_GET['user']) && is_numeric($_GET['user']))) exit('');
// Get user info
$userInfo = User::getUserInfoByUiid($_GET['user']);

// Return list of projects for the user
if ($_GET['action'] == 'project_list')
{
	$projects = User::getProjectsByUser($userInfo['username']);
	$phtml = "";
	foreach ($projects as $pid=>$ptitle) {
		$phtml .= RCView::div(array('style'=>'border-top:1px solid #ddd;padding:2px 0;'), 
					RCView::a(array('href'=>APP_PATH_WEBROOT."index.php?pid=$pid", "target"=>"_blank"), $ptitle)
				  );
	}
	if ($phtml == "") {
		$phtml .= RCView::div(array('class'=>'yellow'), $lang['control_center_4637']);
	} else {
		$phtml = RCView::div(array('style'=>'font-weight:bold;margin-bottom:3px;'), $lang['pub_032']." ".$lang['leftparen'].count($projects).$lang['rightparen'].$lang['colon']) . 
				 RCView::div(array('style'=>'border-bottom:1px solid #ddd;padding-bottom:2px;'), $phtml);
	}
	print 	RCView::p(array('style'=>'margin-top:0;'), $lang['global_17'] . " \"" . RCView::b($userInfo['username']) . "\" " . $lang['control_center_4636']) . 
			RCView::div(array(), $phtml);
}

// Return last time passwords were fetched
elseif ($_GET['action'] == 'last_password_reset')
{
	// Determine last time that their password was reset (if any)
	$lastPassReset = Authentication::getUserLastPasswordResetTime($userInfo['username']);
	if ($lastPassReset == '') {
		// Display "never"
		$lastPassReset = $lang['index_37'];
	} else {
		// Prepend with hidden span for YMD sorting
		$lastPassReset = '<span class="hidden">'.$lastPassReset.'</span>'.DateTimeRC::format_ts_from_ymd($lastPassReset);
	}
	print $lastPassReset;
}

// Error
else
{
	exit('');
}