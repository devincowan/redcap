<?php



/**
 * A simple controller for AJAX requests related to the REDCap API.
 */

// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// If user is not a super user, go back to Home page
if (!$super_user) { redirect(APP_PATH_WEBROOT); }

if (isset($_GET['api_pid']) && !is_numeric($_GET['api_pid'])) exit;

function getAPICreateLink($id, $username, $project_id) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'add.png',
				$lang['control_center_253'], $_SERVER['PHP_SELF'],
				array('action' => 'createToken', 'api_pid' => $project_id, 'api_username' => $username));
}
function getAPICreateLink_s($id, $username) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'add.png',
				$lang['control_center_4506'], $_SERVER['PHP_SELF'],
				array('action' => 'createToken_s', 'api_username' => $username));
}
function getAPIDelLink($id, $username, $project_id) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'cross.png',
				$lang['control_center_247'], $_SERVER['PHP_SELF'],
				array('action' => 'deleteToken', 'api_pid' => $project_id, 'api_username' => $username));
}
function getAPIDelLink_s($id, $username) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'cross.png',
				$lang['control_center_4524'], $_SERVER['PHP_SELF'],
				array('action' => 'deleteToken_s', 'api_username' => $username));
}
function getAPIRegenLink($id, $username, $project_id) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'arrow_refresh.png',
				$lang['control_center_249'], $_SERVER['PHP_SELF'],
				array('action' => 'regenToken', 'api_pid' => $project_id, 'api_username' => $username));
}
function getAPIRegenLink_s($id, $username) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'arrow_refresh.png',
				$lang['control_center_4525'], $_SERVER['PHP_SELF'],
				array('action' => 'regenToken_s', 'api_username' => $username));
}
function getAPIViewLink($id, $username, $project_id) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'magnifier.png',
				$lang['control_center_322'], $_SERVER['PHP_SELF'],
				array('action' => 'viewToken', 'api_pid' => $project_id, 'api_username' => $username));
}
function getAPIViewLink_s($id, $username) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'magnifier.png',
				$lang['control_center_4503'], $_SERVER['PHP_SELF'],
				array('action' => 'viewToken_s', 'api_username' => $username));
}
function getAPIReassignLink($id, $username, $project_id) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'arrow_user.gif',
				$lang['control_center_4443'], $_SERVER['PHP_SELF'],
				array('action' => 'reassignToken', 'api_pid' => $project_id, 'api_username' => $username));
}
function getAPIReassignLink_s($id, $username) {
	global $lang;
	return RCView::iconLink($id, APP_PATH_IMAGES . 'arrow_user.gif',
				$lang['control_center_4511'], $_SERVER['PHP_SELF'],
				array('action' => 'reassignToken_s', 'api_username' => $username));
}
function getAPIRightsDisplay($rights) {
	global $lang;
	$text = "";
	//if ($rights->api_export && $rights->api_import) return $lang['global_74'];
	if ($rights->api_export) $text .= $lang['global_71'] . RCView::br();
	if ($rights->api_import) $text .= $lang['global_72'] . RCView::br();
	if ($rights->mobile_app) $text .= $lang['mobile_app_52'] . RCView::br();
	if (!$rights->api_export && !$rights->api_import && !$rights->mobile_app) $text = $lang['global_75'];
	return $text;
}
function tsToCallDate($ts) {
	return substr($ts, 4, 2) . '/' . substr($ts, 6, 2) . '/' . substr($ts, 0, 4);
}

$db = new RedCapDB();

$ajaxData = "Invalid AJAX call!"; // holds the data that will be returned by the AJAX call

// super

if(isset($_GET['action']) && $_GET['action'] == 'createToken_s' || $_GET['action'] == 'regenToken_s' || $_GET['action'] == 'viewToken_s')
{
	$username = isset($_GET['api_username']) ? $_GET['api_username'] : '';

	if(!$username)
	{
		$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_256'], 'dialogAJAXId');
	}
	elseif($_GET['action'] == 'createToken_s' && $db->getUserSuperToken($username))
	{
		$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_258'], 'dialogAJAXId');
	}
	elseif($_GET['action'] == 'viewToken_s')
	{
		$userInfo = $db->getUserInfoByUsername($username);

		$ajaxData =	RCView::div(array('id'=>'api_token_dialog', 'style'=>'padding:5px;'),
			"$lang[control_center_4514] <b>$username</b>$lang[period]" .
			// Don't show text that user will be emailed if the user is the current user
			($userid == $userInfo->username ? '' : " $lang[control_center_327] <b>$username</b> $lang[control_center_328]") .
			"<br><br>$lang[control_center_4515]$lang[colon]" .
			RCView::div(array('style'=>'font-size:18px; font-weight:bold; color:#347235; margin:5px 0;'),
			$db->getUserSuperToken($username)));

		// If the user is the current user, then don't email self AND don't log this event
		if ($userid != $userInfo->username)
		{
			// Log the action
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $username, "user = '" . $username . "'", "View Super API Token of another user");

			// If don't have an email, then can't send it
			if ($userInfo->user_email != '')
			{
				// Now email the user to let them know someone just viewed their token
				$email = new Message();
				$email->setFrom($homepage_contact_email);
				$email->setFromName($GLOBALS['homepage_contact']);
				$email->setTo($userInfo->user_email);
				$email->setSubject('[REDCap] '.$lang['control_center_4528']);
				$msg =  $lang['control_center_330'] . " $user_firstname $user_lastname (<b>$userid</b>, $user_email) $lang[control_center_4527]
						<b>$username</b>$lang[period]";
				$email->setBody($msg, true);
				$email->send();
			}
		}
	}
	else
	{
		$result = $db->setAPITokenSuper($username);
		if(!$result)
		{
			$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_259'], 'dialogAJAXId');
		}
		else
		{
			// Logging
			Logging::logEvent("", "redcap_user_information", "MANAGE", $username, "user = '" . $username . "'", "Create Super API Token for user");

			// Get user info
			$userInfo = $db->getUserInfoByUsername($username);

			// Send email (if specified)
			if (isset($_GET['api_send_email']) && $_GET['api_send_email'])
			{
				$email = new Message();
				$email->setFrom($homepage_contact_email);
				$email->setFromName($GLOBALS['homepage_contact']);
				$email->setTo($userInfo->user_email);
				$email->setSubject('[REDCap] '.$lang['control_center_4510']);
				$msg = $lang['control_center_4500'] . " \"<b>$username</b>\"" . $lang['period']." ".$lang['control_center_4520'] .
						" " . RCView::a(array('href'=>APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/Profile/user_profile.php"), $lang['config_functions_50']) . ' ' . $lang['global_14'] .  $lang['period'];
				$msg .= "<br><br>\n";
				$email->setBody($msg, true);
				if (!$email->send()) {
					$ajaxData = RCView::errorBox($lang['control_center_4510'] . ' (' . $lang['global_69'] . ')', 'dialogAJAXId');
				}
				else
				{
					$ajaxData = RCView::confBox($lang['control_center_4510'] . ' ' . $lang['data_entry_67'] .
						' ' . RCView::b(RCView::escape($userInfo->username)) . ' ' .
							' (' . $lang['global_68'] . ')', 'dialogAJAXId');
				}
			}
			else
			{
				$ajaxData = RCView::confBox($lang['control_center_4510'] . ' ' . $lang['data_entry_67'] .
					' ' . RCView::b(RCView::escape($userInfo->username)), 'dialogAJAXId');
			}
		}
	}
}
elseif(isset($_GET['action']) && $_GET['action'] == 'tokensByUser_s')
{
	$username = isset($_GET['username']) ? $_GET['username'] : '';
	$superToken = $username ? $db->getUserSuperToken($username) : '';

	$usernames = User::getUsernames(array(), false, false);
	$userOpts = array('' => $lang['control_center_278']);
	foreach ($usernames as $u) $userOpts[$u] = $u;

	$rows = array();

	// NOTE: JS handler for this select box is on user_api_tokens.php
	$s = '';
	$s .= RCView::b($lang['control_center_4498']);
	$s .= RCView::SP . RCView::SP . RCView::SP;
	$s .= RCView::select(array('name' => 'api_username_s', 'id' => 'apiUserSelId_s', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:350px;'), $userOpts);
	$rows[] = $s;

	if($username)
	{
		$hdr = array();
		$hdr[] = $lang['global_76'];
		$hdr[] = $lang['global_67'];

		$rows[] = $hdr;

		$row = array();
		$jsId = "userAPICallDateId_" . $username . '_s';
		$lastUsed = RCView::span(array('id' => $jsId), RCView::font(array('style' => 'font-size: smaller; color: gray;'), $lang['dashboard_39'] . '...'));

		$c = '';

		if($superToken)
		{
			// handlers for these links are in user_api_tokens.php
			$c .= getAPIDelLink_s("sApiDelId", $username);
			$c .= RCView::SP . RCView::SP;
			$c .= getAPIRegenLink_s("sApiRegenId", $username);
			$c .= RCView::SP . RCView::SP;
			$c .= getAPIReassignLink_s("sApiReassignId", $username);
			$c .= RCView::SP . RCView::SP;
			$c .= getAPIViewLink_s("sApiViewId", $username);
		}
		else
		{
			// Make sure this user has "create project" permissions first
			$userInfo = User::getUserInfo($username);
			if ($userInfo['allow_create_db']) {
				// Display the 'add' icon
				$c .= getAPICreateLink_s("sApiCreateId", $username);
			} else {
				// Display message that user needs "create project" permissions
				$lastUsed = RCView::div(array('class'=>'wrap', 'style'=>'font-size:12px;color:#C00000;'), $lang['api_128']);
				$c = '';
			}
		}

		$row[] = $lastUsed;
		$row[] = $c;
		$rows[] = $row;
	}

	$widths = array(360, 106);
	$ajaxData = RCView::simpleGrid($rows, $widths);
}
elseif(isset($_GET['action']) && $_GET['action'] == 'getAPIDateForUserJS_s')
{
	$ajaxData = '1;';

	$username = isset($_GET['username']) ? $_GET['username'] : '';

	if($username)
	{
		$jsId = 'userAPICallDateId_' . $username . '_s';
		$ts = $db->getLastAPICallDateSuper($username);
		$ajaxData .= "\$(\"#$jsId\").html(\"$ts\");";
	}
}
elseif(isset($_GET['action']) && $_GET['action'] == 'deleteToken_s')
{
	$username = isset($_GET['api_username']) ? $_GET['api_username'] : '';

	if(!$username)
	{
		$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_256'], 'dialogAJAXId');
	}
	else
	{
		$result = $db->deleteAPITokenSuper($username);
		if(!$result)
		{
			$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_259'], 'dialogAJAXId');
		}
		else
		{
			Logging::logEvent("", "redcap_user_information", "MANAGE", $username, "user = '" . $username . "'", "Delete Super API Token for user");

			// notify the user about the deletion
			$userInfo = $db->getUserInfoByUsername($username);

			$email = new Message();
			$email->setFrom($homepage_contact_email);
			$email->setFromName($GLOBALS['homepage_contact']);
			$email->setTo($userInfo->user_email);
			$email->setSubject('[REDCap] '.$lang['control_center_4505']);
			$msg = $lang['control_center_4499'] . " \"<b>$username</b>\" " . $lang['global_15'] . APP_PATH_WEBROOT_FULL . ' ' . $lang['period'];
			$email->setBody($msg, true);
			$email->send();
			$ajaxData = RCView::confBox($lang['control_center_4505'] . ' ' . $lang['data_entry_67'] .
				' ' . RCView::b(RCView::escape($userInfo->username)), 'dialogAJAXId');
		}
	}
}
elseif(isset($_GET['action']) && $_GET['action'] == 'getAPIRights_s')
{
	$h = $lang['control_center_4508'];
	$h .= RCView::br() . RCView::br();

	$h .= RCView::hidden(array('id' => 'rightsUsername', 'value' => $_GET['api_username']));
	// If the super user is creating their own API token, then don't pre-check the "notify via email" checkbox
	$notifyUserCheckbox = ($_GET['api_username'] == USERID) ? "" : "checked";
	// Display checkboxes and box with "send email" option
	$h .= 	RCView::div(array('class'=>'chklist','style'=>'margin:4px 0 10px;float:left;padding:10px;'),
				RCView::checkbox(array('name' => 'api_send_email', 'id' => 'api_send_email', $notifyUserCheckbox => $notifyUserCheckbox)) . RCView::SP . RCView::SP .
				RCView::img(array('src'=>'email.png')) .
				$lang['control_center_337']
			);
	$ajaxData = RCView::div(array('style'=>'padding:10px 5px 5px;line-height:1.4em !important;'), $h);
}
elseif ($_GET['action'] == 'reassignToken_s') {
	if (empty($_GET['api_username'])) {
		$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_256'], 'dialogAJAXId');
	}
	else {
		// If we're only returning the drop-down list of users to choose from for reassignment
		if (isset($_GET['showDropDownOnly'])) {
			// Create option list of all users
			$ajaxData = '<option value="">-- '.$lang['control_center_22'].' --</option>';
			foreach (User::getUsernames(array($_GET['api_username']), false) as $this_user) {
				$ajaxData .= '<option value="'.$this_user.'">'.$this_user.'</option>';
			}
		} elseif (isset($_GET['new_user'])) {
			## Reassign the token

			// Get the token
			$token = $db->getUserSuperToken($_GET['api_username']);
			// Remove token from old user
			$sql = "update redcap_user_information set api_token = null where username = '" . db_escape($_GET['api_username']) . "'";
			db_query($sql);
			// Reassign token to new user
			$sql = "update redcap_user_information set api_token = '" . db_escape($token) . "' where username = '" . db_escape($_GET['new_user']) . "'";
			if (!db_query($sql)) {
				$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_259'], 'dialogAJAXId');
			}
			else
			{
				Logging::logEvent("", "redcap_user_information", "MANAGE", $_GET['api_username'], "user = '" . $_GET['api_username'] . "'", "Reassign Super API Token to other user");
				$userInfo = $db->getUserInfoByUsername($_GET['api_username']);

				// Send email to both users
				$email = new Message();
				$email->setFrom($homepage_contact_email);
				$email->setFromName($GLOBALS['homepage_contact']);
				$email->setSubject('[REDCap] '.$lang['control_center_4518']);
				$email->setTo($userInfo->user_email);
				$msg = "{$lang['control_center_4519']} \"<b>{$_GET['api_username']}</b>\" {$lang['control_center_4452']}
					   \"<b>{$_GET['new_user']}</b>\"{$lang['period']}";
				$email->setBody($msg, true);
				$email->send();

				// Second email
				$userInfo = $db->getUserInfoByUsername($_GET['new_user']);
				$email->setTo($userInfo->user_email);
				$msg = "{$lang['control_center_4521']} \"<b>{$_GET['new_user']}</b>\" {$lang['control_center_4454']}
					   \"<b>{$_GET['api_username']}</b>\"{$lang['period']}";
				$email->setBody($msg, true);
				$email->send();

				// Return response content
				$ajaxData = RCView::confBox($lang['control_center_4447'] .
							' "' . RCView::b(RCView::escape($_GET['new_user'])) . '" ' . $lang['control_center_4449'] .
							' "' . RCView::b(RCView::escape($_GET['api_username'])) . '" ' . $lang['global_51'] . ' ' .
							$lang['period'] . RCView::br() . RCView::br() . $lang['control_center_4456'], 'dialogAJAXId');
			}
		}
	}
}

// end super

elseif ($_GET['action'] == 'tokensByUser') {
	$allUserMode = ($_GET['username'] == "-1");
	$rights = $allUserMode ? $db->getAPITokens() : $db->getUserRights($_GET['username']);
	$usernames = $db->getUsernamesWithProjects();
	$userOpts = array('' => $lang['control_center_278'], '-1' => $lang['control_center_268']);
	foreach ($usernames as $u) $userOpts[$u->username] = $u->username;
	$rows = array();
	// NOTE: JS handler for this select box is on user_api_tokens.php
	$s = '';
	$s .= RCView::b($lang['control_center_266'] . ' ' . $lang['global_17'] . $lang['colon']);
	$s .= RCView::SP . RCView::SP . RCView::SP;
	$s .= RCView::select(array('name' => 'api_username', 'id' => 'apiUserSelId', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:350px;'), $userOpts);
	$rows[] = $s;
	$hdr = array();
	if ($allUserMode) $hdr[] = $lang['global_17'];
	$hdr[] = $lang['global_65'];
	$hdr[] = $lang['global_76'];
	$hdr[] = $lang['global_73'];
	$hdr[] = $lang['global_67'];
	// only add the column headers if we have data to display
	if (count($rights) > 0) $rows[] = $hdr;
	foreach ($rights as $r) {
		$row = array();
		if ($allUserMode) $row[] = RCView::escape($r->username);
		$row[] = RCView::escape($r->app_title);
		$jsId = "userAPICallDateId_" . $r->username . "_" . $r->project_id;
		$row[] = RCView::span(array('id' => $jsId), RCView::font(array('style' => 'font-size: smaller; color: gray;'), $lang['dashboard_39'] . '...'));
		$row[] = getAPIRightsDisplay($r);
		$cnt = count($rows);
		$c = '';
		// NOTE: JS handlers for these links are on user_api_tokens.php
		if (empty($r->api_token)) {
			$c .= getAPICreateLink("apiCreateId$cnt", $r->username, $r->project_id);
		}
		else {
			$c .= getAPIDelLink("apiDelId$cnt", $r->username, $r->project_id);
			$c .= RCView::SP . RCView::SP;
			$c .= getAPIRegenLink("apiRegenId$cnt", $r->username, $r->project_id);
			$c .= RCView::SP . RCView::SP;
			// Icon to reassign token to other user
			$c .= getAPIReassignLink("apiReassignId$cnt", $r->username, $r->project_id);
			$c .= RCView::SP . RCView::SP;
			// Icon to open dialog to view the token
			$c .= getAPIViewLink("apiViewId$cnt", $r->username, $r->project_id);
		}
		$row[] = $c;
		$rows[] = $row;
	}
	$widths = $allUserMode ? array(70, 290, 120, 80, 106) : array(360, 120, 80, 106);
	$ajaxData = RCView::simpleGrid($rows, $widths);
}
elseif ($_GET['action'] == 'tokensByProj') {
	$allProjMode = ($_GET['project_id'] == "-1");
	$rights = $allProjMode ? $db->getAPITokens(true) : $db->getProjectRights($_GET['project_id']);
	$projOpts = array('' => $lang['control_center_279'], '-1' => $lang['control_center_269']);
	$projects = $allProjMode ? $db->getProjects() : array($db->getProject($_GET['project_id']));

	foreach ($projects as $p)
	{
		if(isset($p->project_id))
		{
			$projOpts[$p->project_id] = $p->app_title;
		}
	}

	$app_title = $allProjMode ? $lang['control_center_269'] : (count($rights) ? current($rights)->app_title : '');
	$rows = array();
	// Define table header
	$s = '';
	if (isset($_GET['controlCenterView']) && $_GET['controlCenterView']) {
		$s .= RCView::b($lang['control_center_266'] . ' ' . $lang['global_65'] . $lang['colon']);
		$s .= RCView::SP . RCView::SP . RCView::SP;
		$s .= RCView::select(array('name' => 'api_pid', 'id' => 'apiProjSelId', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:350px;'), $projOpts, '', 70);
	} else {
		$s .= RCView::b($lang['control_center_339']);
		$s .= RCView::select(array('name' => 'api_pid', 'id' => 'apiProjSelId', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:5px;max-width:350px;'), $projOpts, '', 70);
	}
	$rows[] = $s;
	$hdr = array();
	if ($allProjMode) $hdr[] = $lang['global_65'];
	$hdr[] = $lang['global_17'];
	$hdr[] = $lang['global_76'];
	$hdr[] = $lang['global_73'];
	$hdr[] = $lang['global_67'];
	// only add the column headers if we have data to display
	if (count($rights) > 0) $rows[] = $hdr;
	foreach ($rights as $r) {
		$row = array();
		if ($allProjMode) $row[] = RCView::escape($r->app_title);
		$row[] = RCView::escape($r->username);
		$jsId = "projAPICallDateId_" . $r->username . "_" . $r->project_id;
		$row[] = RCView::span(array('id' => $jsId), RCView::font(array('style' => 'font-size: smaller; color: gray;'), $lang['dashboard_39'] . '...'));
		$row[] = getAPIRightsDisplay($r);
		$cnt = count($rows);
		$c = '';
		// NOTE: JS handlers for these links are on user_api_tokens.php
		if (empty($r->api_token)) {
			$c .= getAPICreateLink("apiCreateId$cnt", $r->username, $r->project_id);
		}
		else {
			$c .= getAPIDelLink("apiDelId$cnt", $r->username, $r->project_id);
			$c .= RCView::SP . RCView::SP;
			$c .= getAPIRegenLink("apiRegenId$cnt", $r->username, $r->project_id);
			$c .= RCView::SP . RCView::SP;
			// Icon to reassign token to other user
			$c .= getAPIReassignLink("apiReassignId$cnt", $r->username, $r->project_id);
			$c .= RCView::SP . RCView::SP;
			// Icon to open dialog to view the token
			$c .= getAPIViewLink("apiViewId$cnt", $r->username, $r->project_id);
		}
		$row[] = $c;
		$rows[] = $row;
	}
	$widths = $allProjMode ? array(240, 70, 120, 80, 106) : array(310, 120, 80, 106);
	$ajaxData = RCView::simpleGrid($rows, $widths);
}
elseif ($_GET['action'] == 'getAPIDateForUserJS') {
	$allUserMode = $_GET['username'] == "-1" ? true : false;
	$rights = $allUserMode ? $db->getAPITokens() : $db->getUserRights($_GET['username']);
	$ajaxData = "1;";
	$callDates = array();
	if (count($rights) > 0)
		$callDates = $allUserMode ? $db->getLastAPICallDates() : $db->getLastAPICallDates($_GET['username']);
	foreach ($rights as $r) {
		$jsId = "userAPICallDateId_" . $r->username . "_" . $r->project_id;
		if (empty($callDates[$r->username][$r->project_id]))
			$ajaxData .= "\$(\"#$jsId\").html(\"" . $lang['index_37'] . "\");";
		else {
			$ts = $callDates[$r->username][$r->project_id]->LastTS;
			$ajaxData .= "\$(\"#$jsId\").html(\"" . DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($ts)) . "\");";
		}
	}
}
elseif ($_GET['action'] == 'getAPIDateForProjJS') {
	$allProjMode = $_GET['project_id'] == "-1" ? true : false;
	$rights = $allProjMode ? $db->getAPITokens(true) : $db->getProjectRights($_GET['project_id']);
	$ajaxData = "1;";
	$callDates = array();
	if (count($rights) > 0)
		$callDates = $allProjMode ? $db->getLastAPICallDates() : $db->getLastAPICallDates(null, $_GET['project_id']);
	foreach ($rights as $r) {
		$jsId = "projAPICallDateId_" . str_replace(array("@", "."), array("\\\\@", "\\\\."), $r->username) . "_" . $r->project_id;
		if (empty($callDates[$r->username][$r->project_id]))
			$ajaxData .= "\$(\"#$jsId\").html(\"" . $lang['index_37'] . "\");";
		else {
			$ts = $callDates[$r->username][$r->project_id]->LastTS;
			$ajaxData .= "\$(\"#$jsId\").html(\"" . DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($ts)) . "\");";
		}
	}
}
elseif ($_GET['action'] == 'getAPIRights') {
	$rights = UserRights::getPrivileges($_GET['api_pid'], $_GET['api_username']);
	$rights = $rights[$_GET['api_pid']][strtolower($_GET['api_username'])];
	$projInfo = $db->getProject($_GET['api_pid']);
	$h = RCView::b($lang['control_center_274'] . RCView::SP . RCView::escape('"' . $_GET['api_username'] . '"')) . RCView::br();
	$h .= $lang['control_center_275'] . ' "' . RCView::b(RCView::escape($projInfo->app_title)) . '"';
	$h .= $lang['period'] . ' ' . $lang['control_center_4557'];
	$h .= RCView::br() . RCView::br();
	// Text
	$i = RCView::b($lang['app_05']);
	$i .= RCView::br();
	// Export checkbox
	$attrs = array('name' => 'api_export', 'id' => 'api_export');
	if ($rights['api_export']) $attrs['checked'] = 'checked';
	if ($rights['role_id'] != '') $attrs['disabled'] = 'disabled';
	$i  .= RCView::checkbox($attrs);
	$i .= $lang['rights_139'];
	$i .= RCView::br();
	// Import checkbox
	$attrs = array('name' => 'api_import', 'id' => 'api_import');
	if ($rights['api_import']) $attrs['checked'] = 'checked';
	if ($rights['role_id'] != '') $attrs['disabled'] = 'disabled';
	$i .= RCView::checkbox($attrs);
	$i .= $lang['rights_314'];
	$i .= RCView::br();
	// Mobile App checkbox
	$attrs = array('name' => 'mobile_app', 'id' => 'mobile_app');
	if ($rights['mobile_app']) $attrs['checked'] = 'checked';
	if ($rights['role_id'] != '') $attrs['disabled'] = 'disabled';
	$i .= RCView::checkbox($attrs);
	$i .= $lang['global_118'];
	// Hidden usename field
	$i .= RCView::hidden(array('id' => 'rightsUsername', 'value' => $_GET['api_username']));
	// If user is in a role, display note that user's API rights cannot be changed
	if ($rights['role_id'] != '') $i .= RCView::div(array('style'=>'margin-top:10px;color:#C00000;line-height:11px;font-size:11px;'), $lang['rights_220']);
	// If the super user is creating their own API token, then don't pre-check the "notify via email" checkbox
	$notifyUserCheckbox = ($_GET['api_username'] == USERID) ? "" : "checked";
	// Display checkboxes and box with "send email" option
	$h .= 	RCView::div(array('style'=>'vertical-align:top;'),
				RCView::div(array('style'=>'float:left;width:170px;'), $i) .
				RCView::div(array('class'=>'chklist','style'=>'margin:4px 0 10px;float:right;padding:10px;'),
					RCView::checkbox(array('name' => 'api_send_email', 'id' => 'api_send_email', $notifyUserCheckbox => $notifyUserCheckbox)) . RCView::SP . RCView::SP .
					RCView::img(array('src'=>'email.png')) .
					$lang['control_center_337']
				)
			);
	$ajaxData = RCView::div(array('style'=>'padding:10px 5px 5px;line-height:1.4em !important;'), $h);
}
elseif ($_GET['action'] == 'createToken' || $_GET['action'] == 'regenToken' || $_GET['action'] == 'viewToken') {
	if (empty($_GET['api_username'])) {
		$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_256'], 'dialogAJAXId');
	}
	elseif (empty($_GET['api_pid'])) {
		$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_257'], 'dialogAJAXId');
	}
	elseif ($_GET['action'] == 'createToken' && $db->getAPIToken($_GET['api_username'], $_GET['api_pid'])) {
		$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_258'], 'dialogAJAXId');
	}
	elseif ($_GET['action'] == 'viewToken') {
		$projInfo = $db->getProject($_GET['api_pid']);
		$userInfo = $db->getUserInfoByUsername($_GET['api_username']);
		$projectLink = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version" . '/index.php?pid=' . $projInfo->project_id;
		$apiProjectLink = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version" . '/API/project_api.php?pid=' . $projInfo->project_id;
		$ajaxData = RCView::div(array('id'=>'api_token_dialog','style'=>'padding:5px;'),
						"{$lang['control_center_325']} <b>{$_GET['api_username']}</b> {$lang['control_center_326']} ".
						RCView::a(array('target'=>'_blank','href'=>$projectLink,'style'=>'text-decoration:underline;'), $projInfo->app_title)."{$lang['period']}".
						// Don't show text that user will be emailed if the user is the current user
						($userid == $userInfo->username ? "" : " {$lang['control_center_327']} <b>{$_GET['api_username']}</b> {$lang['control_center_328']}")."
						<br><br>{$lang['control_center_333']}{$lang['colon']}" .
						RCView::div(array('style'=>'font-size: 18px; font-weight: bold; color: #347235;margin:5px 0;'),
							$db->getAPIToken($_GET['api_username'], $_GET['api_pid'])
						)
					);
		// If the user is the current user, then don't email self AND don't log this event
		if ($userid != $userInfo->username)
		{
			// Log the action
			defined("PROJECT_ID") or define("PROJECT_ID", $_GET['api_pid']);
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $_GET['api_username'], "user = '" . $_GET['api_username'] . "'", "View API token of another user");
			// If don't have an email, then can't send it
			if ($userInfo->user_email != '') {
				// Now email the user to let them know someone just viewed their token
				$email = new Message();
				$email->setFrom($projInfo->project_contact_email);
				$email->setFromName($projInfo->project_contact_name);
				$email->setTo($userInfo->user_email);
				$email->setSubject('[REDCap] '.$lang['control_center_329']);
				$msg =  $lang['control_center_330'] . " $user_firstname $user_lastname (<b>$userid</b>, $user_email) {$lang['control_center_331']}
						<b>{$_GET['api_username']}</b> {$lang['control_center_326']} " .
						RCView::a(array('href'=>$projectLink), RCView::escape($projInfo->app_title))."{$lang['period']}
						{$lang['control_center_336']}<br \><br \>{$lang['control_center_334']} ".
						RCView::a(array('href'=>$apiProjectLink), $lang['control_center_335']).$lang['period'];
				$email->setBody($msg, true);
				$email->send();
			}
		}
	}
	else {
		if ($_GET['mobile_app'] == '') $_GET['mobile_app'] = 0;
		$sql = $db->saveAPIRights($_GET['api_username'], $_GET['api_pid'], $_GET['api_export'], $_GET['api_import'], $_GET['mobile_app']);
		if (count($sql) > 0) {
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $_GET['api_username'], "user = '" . $_GET['api_username'] . "'", "Set API rights for user");
		}

		$rights_proj_user = UserRights::getPrivileges($_GET['api_pid'], $_GET['api_username']);
		$rights = $rights_proj_user[$_GET['api_pid']][strtolower($_GET['api_username'])];
		unset($user_rights_proj_user);
		if (!$rights['api_export'] && !$rights['api_import'] && !$rights['mobile_app']) {
			$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_277'], 'dialogAJAXId');
		}
		else {
			$sql = $db->setAPIToken($_GET['api_username'], $_GET['api_pid']);
			if (count($sql) === 0) {
				$ajaxData = RCView::errorBox($lang['control_center_255'] . ' - ' . $lang['control_center_259'], 'dialogAJAXId');
			}
			else {
				// Logging
				Logging::logEvent("", "redcap_user_rights", "MANAGE", $_GET['api_username'], "user = '" . $_GET['api_username'] . "'", "Create API token for user");
				// Set completed in To-Do List
				$userInfoRequestor = User::getUserInfo($_GET['api_username']);
				ToDoList::updateTodoStatus($_GET['api_pid'], 'token access','completed', $userInfoRequestor['ui_id']);
				// Get project and user info
				$userInfo = $db->getUserInfoByUsername($_GET['api_username']);
				$projInfo = $db->getProject($_GET['api_pid']);
				// Send email (if specified)
				if (isset($_GET['api_send_email']) && $_GET['api_send_email']) {
					$email = new Message();
					$email->setFrom($projInfo->project_contact_email);
					$email->setFromName($projInfo->project_contact_name);
					$email->setTo($userInfo->user_email);
					$email->setSubject('[REDCap] '.$lang['control_center_260']);
					$msg = $lang['control_center_263'] . ' "' . RCView::b(RCView::escape($projInfo->app_title)).'"'.$lang['period'];
					$msg .= "<br><br>\n";
					$retrieveLink = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version" . '/API/project_api.php?pid=' . $projInfo->project_id;
					$msg .= RCView::a(array('href' => $retrieveLink), $lang['control_center_265']);
					$email->setBody($msg, true);
					if (!$email->send()) {
						$ajaxData = RCView::errorBox($lang['control_center_260'] . ' (' . $lang['global_69'] . ')', 'dialogAJAXId');
					}
					else {
						$ajaxData = RCView::confBox($lang['control_center_260'] . ' ' . $lang['data_entry_67'] .
							' ' . RCView::b(RCView::escape($userInfo->username)) . ' ' . $lang['global_51'] . ' ' .
										RCView::b(RCView::i(RCView::escape($projInfo->app_title))) . ' (' . $lang['global_68'] . ')', 'dialogAJAXId');
					}
				} else {
					$ajaxData = RCView::confBox($lang['control_center_260'] . ' ' . $lang['data_entry_67'] .
							' ' . RCView::b(RCView::escape($userInfo->username)) . ' ' . $lang['global_51'] . ' ' .
										RCView::b(RCView::i(RCView::escape($projInfo->app_title))), 'dialogAJAXId');
				}
			}
		}
	}
}
elseif ($_GET['action'] == 'reassignToken') {
	if (empty($_GET['api_username'])) {
		$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_256'], 'dialogAJAXId');
	}
	elseif (empty($_GET['api_pid']) || !is_numeric($_GET['api_pid'])) {
		$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_257'], 'dialogAJAXId');
	}
	else {
		// If we're only returning the drop-down list of users to choose from for reassignment
		if (isset($_GET['showDropDownOnly'])) {
			// Create option list of all users
			$ajaxData = '<option value="">-- '.$lang['control_center_22'].' --</option>';
			foreach (User::getProjectUsernames(array($_GET['api_username']), false, $_GET['api_pid']) as $this_user) {
				$ajaxData .= '<option value="'.$this_user.'">'.$this_user.'</option>';
			}
		} elseif (isset($_GET['new_user'])) {
			## Reassign the token
			// Get the token
			$token = $db->getAPIToken($_GET['api_username'], $_GET['api_pid']);
			// Remove token from old user
			$sql = "update redcap_user_rights set api_token = null where username = '".db_escape($_GET['api_username'])."'
					and project_id = {$_GET['api_pid']}";
			db_query($sql);
			// Reassign token to new user
			$sql = "update redcap_user_rights set api_token = '".db_escape($token)."' where username = '".db_escape($_GET['new_user'])."'
					and project_id = {$_GET['api_pid']}";
			if (!db_query($sql)) {
				$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_259'], 'dialogAJAXId');
			}
			else {
				Logging::logEvent("", "redcap_user_rights", "MANAGE", $_GET['api_username'], "user = '" . $_GET['api_username'] . "'", "Reassign API token to other user");
				$userInfo = $db->getUserInfoByUsername($_GET['api_username']);
				$projInfo = $db->getProject($_GET['api_pid']);
				// Send email to both users
				$projApiPageLink = 	RCView::a(array('href' => APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version/API/project_api.php?pid={$projInfo->project_id}"),
										RCView::escape($projInfo->app_title)
									);
				$email = new Message();
				$email->setFrom($projInfo->project_contact_email);
				$email->setFromName($projInfo->project_contact_name);
				$email->setSubject('[REDCap] '.$lang['control_center_4450']);
				$email->setTo($userInfo->user_email);
				$msg = "{$lang['control_center_4451']} \"<b>{$_GET['api_username']}</b>\" {$lang['control_center_4452']}
					   \"<b>{$_GET['new_user']}</b>\" {$lang['control_center_4455']} \"$projApiPageLink\"{$lang['period']}";
				$email->setBody($msg, true);
				$email->send();
				// Second email
				$userInfo = $db->getUserInfoByUsername($_GET['new_user']);
				$email->setTo($userInfo->user_email);
				$msg = "{$lang['control_center_4453']} \"<b>{$_GET['new_user']}</b>\" {$lang['control_center_4454']}
					   \"<b>{$_GET['api_username']}</b>\" {$lang['control_center_4455']} \"$projApiPageLink\"{$lang['period']}";
				$email->setBody($msg, true);
				$email->send();
				// Return response content
				$ajaxData = RCView::confBox($lang['control_center_4447'] .
							' "' . RCView::b(RCView::escape($_GET['new_user'])) . '" ' . $lang['control_center_4449'] .
							' "' . RCView::b(RCView::escape($_GET['api_username'])) . '" ' . $lang['global_51'] . ' ' .
							RCView::b(RCView::i(RCView::escape($projInfo->app_title))) .
							$lang['period'] . RCView::br() . RCView::br() . $lang['control_center_4456'], 'dialogAJAXId');
			}
		}
	}
}
elseif ($_GET['action'] == 'deleteToken') {
	if (empty($_GET['api_username'])) {
		$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_256'], 'dialogAJAXId');
	}
	elseif (empty($_GET['api_pid'])) {
		$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_257'], 'dialogAJAXId');
	}
	else {
		$sql = $db->deleteAPIToken($_GET['api_username'], $_GET['api_pid']);
		if (count($sql) === 0) {
			$ajaxData = RCView::errorBox($lang['control_center_261'] . ' - ' . $lang['control_center_259'], 'dialogAJAXId');
		}
		else {
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $_GET['api_username'], "user = '" . $_GET['api_username'] . "'", "Delete API token for user");
			// notify the user about the deletion
			$userInfo = $db->getUserInfoByUsername($_GET['api_username']);
			$projInfo = $db->getProject($_GET['api_pid']);
			$email = new Message();
			$email->setFrom($projInfo->project_contact_email);
			$email->setFromName($projInfo->project_contact_name);
			$email->setTo($userInfo->user_email);
			$email->setSubject('[REDCap] '.$lang['control_center_262']);
			$msg = $lang['control_center_282'] . ' "' . RCView::b(RCView::escape($projInfo->app_title)) . '"' . $lang['period'];
			$email->setBody($msg, true);
			$email->send();
			$ajaxData = RCView::confBox($lang['control_center_262'] . ' ' . $lang['data_entry_67'] .
						' ' . RCView::b(RCView::escape($userInfo->username)) . ' ' . $lang['global_51'] . ' ' .
									RCView::b(RCView::i(RCView::escape($projInfo->app_title))), 'dialogAJAXId');
		}
	}
}
elseif ($_GET['action'] == 'countProjectTokens') {
	if (empty($_GET['api_pid'])) {
		$ajaxData = $lang['global_01'] . ' - ' . $lang['control_center_257'];
	}
	else {
		$ajaxData = $db->countAPITokensByProject($_GET['api_pid']);
	}
}
elseif ($_GET['action'] == 'deleteProjectTokens') {
	if (empty($_GET['api_pid'])) {
		$ajaxData = $lang['global_01'] . ' - ' . $lang['control_center_257'];
	}
	else {
		$rights = $db->getProjectRights($_GET['api_pid']);
		$usernames = array();
		foreach ($rights as $r) if (!empty($r->api_token)) $usernames[] = $r->username;
		$users = $db->getUserInfoByUsernames($usernames);
		$projInfo = $db->getProject($_GET['api_pid']);
		$sql = $db->deleteAPIProjectTokens($_GET['api_pid']);
		if (count($sql) == 0) $ajaxData = $lang['control_center_272'];
		else {
			$ajaxData = $lang['control_center_271'];
			// notify users about the deletion
			foreach ($users as $userInfo) {
				$email = new Message();
				$email->setFrom($projInfo->project_contact_email);
				$email->setFromName($projInfo->project_contact_name);
				$email->setTo($userInfo->user_email);
				$email->setSubject('[REDCap] '.$lang['control_center_262']);
				$msg = $lang['control_center_282'] . ' "' . RCView::b(RCView::escape($projInfo->app_title)) . '"' . $lang['period'];
				$email->setBody($msg, true);
				$email->send();
			}
		}
	}
}

exit($ajaxData);
