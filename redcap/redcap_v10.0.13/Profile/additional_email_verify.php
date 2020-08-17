<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';


// If user clicked verification code link in their email, validation the code and complete the setup process
if (isset($_GET['user_verify']))
{
	// Display header
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	// Display link on far right to log out
	print 	RCView::div(array('style'=>'text-align:right;'),
				RCView::a(array('href'=>'javascript:;','onclick'=>"window.location.href = app_path_webroot_full+'?logout=1';",'style'=>'text-decoration:underline;'), $lang['bottom_02'])
			);

	// Get user info
	$user_info = User::getUserInfo($userid);

	// Verify the code provided
	$emailAccount = User::verifyUserVerificationCode($userid, $_GET['user_verify']);
	if ($emailAccount !== false) {
		## Verified
		// Activate the new email by removing the verification code
		User::removeUserVerificationCode($userid, $emailAccount);
		// Log the event
		defined("USERID") or define("USERID", $userid);
		Logging::logEvent("", "redcap_user_information", "MANAGE", $userid, "username = '$userid'", "Verify user email address");
		// Confirmation that account has been activated
		print 	RCView::h4(array('style'=>'margin:10px 0 25px;color:green;'),
					RCView::img(array('src'=>'tick.png')) . $lang['user_29']
				) .
				RCView::div(array('class'=>'darkgreen','style'=>'padding:10px;margin-bottom:30px;'),
					$lang['user_30'] .
					RCView::div(array('style'=>'padding:10px;text-align:center;'),
						RCView::button(array('class'=>'jqbutton','onclick'=>"window.location.href=app_path_webroot;"), $lang['global_88'])
					)
				);
	} else {
		## Error: code could not be verified
		// Check to see if the verification code actually belongs to another user
		$intended_userid_email_num = User::verifyUserVerificationCodeAnyUser($_GET['user_verify']);
		if ($intended_userid !== false) {
			// Code belongs to ANOTHER user. Output error message.
			list ($intended_userid, $email_num_field) = $intended_userid_email_num;
			$intended_user_info = User::getUserInfo($intended_userid);
			// Code doesn't belong to ANY user. Output error message.
			print 	RCView::h4(array('style'=>'margin:10px 0 25px;color:#800000;'),
						RCView::img(array('src'=>'delete.png')) . $lang['user_31'] . RCView::SP .
						$lang['user_65'] . RCView::SP . "\"" . USERID . "\"" . $lang['exclamationpoint']
					) .
					RCView::div(array('class'=>'red','style'=>'padding:10px;margin-bottom:30px;'),
						$lang['user_104'] . " \"" . RCView::b("\"$userid\" ($user_firstname $user_lastname)") .$lang['period']." ".
						$lang['user_100'] . " (" . RCView::b("\"$intended_userid\" - {$intended_user_info['user_firstname']} {$intended_user_info['user_lastname']}") . ") " . 
						$lang['user_101'] . " " . RCView::b($intended_user_info[$email_num_field]) . $lang['period']." " .
						$lang['user_102'] . " " . RCView::b("\"$intended_userid\" ({$intended_user_info['user_firstname']} {$intended_user_info['user_lastname']})") . " " .
						$lang['user_103'] . " " . RCView::b($user_email) . $lang['period']
					);
		} else {
			// Code doesn't belong to ANY user. Output error message.
			print 	RCView::h4(array('style'=>'margin:10px 0 25px;color:#800000;'),
						RCView::img(array('src'=>'delete.png')) . $lang['user_31']
					) .
					RCView::div(array('class'=>'red','style'=>'padding:10px;margin-bottom:30px;'),
						$lang['user_32'] . RCView::SP .
						RCView::a(array('href'=>"mailto:".$project_contact_email,'style'=>'text-decoration:underline;'), $project_contact_name) .
						$lang['period']
					);
		}
	}

	// Footer
	$objHtmlPage->PrintFooterExt();
	exit;
}

System::redirectHome();