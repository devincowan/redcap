<?php



/**
 * A simple controller for AJAX requests related to the project API page.
 */

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) exit("API is disabled!");

$db = new RedCapDB();


$ajaxData = "Invalid AJAX call!"; // holds the data that will be returned by the AJAX call

if (isset($_POST['action']) && $_POST['action'] == 'requestToken' && $api_enabled) {
	$userInfo = $db->getUserInfoByUsername($userid);
	if (empty($project_id)) {
		$ajaxData = RCView::errorBox($lang['edit_project_89'] . ' - ' . $lang['control_center_257'], 'apiDialogId');
	}
	elseif ($db->getAPIToken($userid, $project_id)) {
		$ajaxData = RCView::errorBox($lang['edit_project_89'] . ' - ' . $lang['control_center_258'], 'apiDialogId');
	}
	elseif (SUPER_USER || defined("AUTOMATE_ALL") 
		// Auto-approve tokens for all users
		|| $api_token_request_type == 'auto_approve_all'
		// Auto-approve tokens only for selected users
		|| ($api_token_request_type == 'auto_approve_selected' && $userInfo->api_token_auto_request == '1')
	) {
		// Allow user to generate their own token if a Super User or if AUTOMATE_ALL flag is set for this installation
		$sql = $db->setAPIToken($userid, $project_id);
		Logging::logEvent("", "redcap_user_rights", "MANAGE", $userid, "user = '$userid'", "Create API token for self");
	}
	else {
		$ui_id = $userInfo->ui_id;
		$projInfo = $db->getProject($project_id);
		$request_to = $projInfo->project_contact_email;
		$todo_type = "token access";
		$action_url = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version" . '/ControlCenter/user_api_tokens.php?action=createToken&api_username=' . $userid . '&api_pid=' . $project_id;
		$project_url = APP_PATH_WEBROOT.'index.php?pid='.$project_id;
		ToDoList::insertAction($ui_id, $request_to, $todo_type, $action_url, $project_id);
		$email = new Message();
		$email->setFrom($userInfo->user_email);
		$email->setFromName($userInfo->user_firstname." ".$userInfo->user_lastname);
		$email->setTo($projInfo->project_contact_email);
		$email->setSubject('[REDCap] "'.$userid . '" ' . $lang['edit_project_91']);
		$msg = RCView::escape("$userInfo->user_firstname $userInfo->user_lastname ($userid) ");
		$msg .= $lang['edit_project_91'] . ' ';
		$msg .= $lang['edit_project_92'];
		$msg .= ' "'.RCView::b(RCView::escape($projInfo->app_title)).'"'.$lang['period'];
		$msg .= "<br><br>\n";
		$approveLink = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version" . '/ControlCenter/user_api_tokens.php?action=createToken&api_username=' . $userid . '&api_pid=' . $project_id;
		$msg .= RCView::a(array('href' => $approveLink), $lang['edit_project_93']);
		$email->setBody($msg, true);
		If ($send_emails_admin_tasks) {
		if ($email->send()) {
				$ajaxData = RCView::div(array('style'=>'color:green;font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['edit_project_90']
						);
			// Logging
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $userid, "user = '$userid'", "Request API token");
		}
		else {
			$ajaxData = RCView::errorBox($lang['edit_project_89'] . ' - ' . $lang['global_66'], 'apiDialogId');
		}
		}else{
			$ajaxData = RCView::div(array('style'=>'color:green;font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['edit_project_90']
						);
			// Logging
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $userid, "user = '$userid'", "Request API token");
		}
	}
}
elseif (isset($_POST['action']) && $_POST['action'] == 'deleteToken' && $api_enabled) {
	if (empty($project_id)) {
		$ajaxData = RCView::errorBox($lang['edit_project_98'] . ' - ' . $lang['control_center_257'], 'apiDialogId');
	}
	elseif (!$db->getAPIToken($userid, $project_id)) {
		$ajaxData = RCView::errorBox($lang['edit_project_98'] . ' - ' . $lang['control_center_270'], 'apiDialogId');
	}
	else {
		$sql = $db->deleteAPIToken($userid, $project_id);
		if (count($sql) === 0) {
			$ajaxData = RCView::errorBox($lang['edit_project_98'] . ' - ' . $lang['control_center_259'], 'apiDialogId');
		}
		else {
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $userid, "user = '" . $userid . "'", "User delete own API token");
			$ajaxData = RCView::div(array('style'=>'color:green;font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['edit_project_99']
						);
		}
	}
}
elseif (isset($_POST['action']) && $_POST['action'] == 'regenToken' && $api_enabled) {
	if (empty($project_id)) {
		$ajaxData = RCView::errorBox($lang['edit_project_100'] . ' - ' . $lang['control_center_257'], 'apiDialogId');
	}
	elseif (!$db->getAPIToken($userid, $project_id)) {
		$ajaxData = RCView::errorBox($lang['edit_project_100'] . ' - ' . $lang['control_center_270'], 'apiDialogId');
	}
	else {
		$sql = $db->setAPIToken($userid, $project_id);
		if (count($sql) === 0) {
			$ajaxData = RCView::errorBox($lang['edit_project_100'] . ' - ' . $lang['control_center_259'], 'apiDialogId');
		}
		else {
			Logging::logEvent("", "redcap_user_rights", "MANAGE", $userid, "user = '" . $userid . "'", "User regenerate own API token");
			$ajaxData = RCView::div(array('style'=>'color:green;font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['edit_project_101']
						);
		}
	}
}
elseif ($_GET['action'] == 'getToken' && $api_enabled) {
	$ajaxData = $db->getAPIToken($userid, $project_id);
}
elseif ($_GET['action'] == 'getTokens' && !empty($project_id) && $api_enabled) {
	$usernames = array();
	$toks = $db->getAPITokens(false, $project_id);
	foreach ($toks as $t) $usernames[] = $t->username;
	$ajaxData = RCView::escape(implode(', ', $usernames));
}
elseif ($_GET['action'] == 'getAppCode') {
	if (isset($_GET['qrcode']) && $_GET['qrcode'] == '1') {
		// Include the QR Code class
		require_once APP_PATH_LIBRARIES . "phpqrcode/qrlib.php";
		// Set array of things to encode as JSON and then convert to a QR code
		$params = array('username'=>USERID, 'url'=>APP_PATH_WEBROOT_FULL, 'project_id'=>PROJECT_ID,
						'token'=>$db->getAPIToken($userid, $project_id), 'error'=>'',
						'project_title'=>strip_tags(label_decode($app_title)), 'record_auto_numbering'=>$auto_inc_set,
						'download_data'=>$user_rights['mobile_app_download_data'], 'email'=>$user_email);
		// Output QR code image
		QRcode::png(json_encode($params), false, 0, 3);
	} else {
		// Send username and REDCap base URL to consortium server
		$params = array('user'=>USERID, 'url'=>APP_PATH_WEBROOT_FULL, 'project_id'=>PROJECT_ID, 'hostkey_hash'=>encrypt(Stats::getServerKeyHash()),
						'token'=>$db->getAPIToken($userid, $project_id), 'user_code'=>$_GET['user_code'],
						'project_title'=>strip_tags(label_decode($app_title)), 'record_auto_numbering'=>$auto_inc_set,
						'download_data'=>$user_rights['mobile_app_download_data'], 'email'=>$user_email);
		$appCodeRequestResults = http_post("https://redcap.vanderbilt.edu/consortium/app/get_code.php", $params);
		print $appCodeRequestResults;
	}
	exit;
}

exit($ajaxData);
