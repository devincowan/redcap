<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Set query string "action" values for NON-super users
$nonSuperUserActions = array("reset_password_as_temp", "reset_security_question", "email_authenticator_setup");
$superUserOnlyActions = array("remove_super_user", "add_super_user", "remove_account_manager", "add_account_manager");
//If user is not a super user, go back to Home page
if (!SUPER_USER && !ACCOUNT_MANAGER && !in_array($_GET['action'], $nonSuperUserActions))
{
	if ($isAjax) exit('0');
	redirect(APP_PATH_WEBROOT);
}
if (!SUPER_USER && in_array($_GET['action'], $superUserOnlyActions))
{
	if ($isAjax) exit('0');
	redirect(APP_PATH_WEBROOT);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Do any processing
switch ($action)
{
	// Email the Google Authenticator setup instructions to a user
	case 'email_authenticator_setup':
		if ($_SERVER['REQUEST_METHOD'] != 'POST' || (!SUPER_USER && !ACCOUNT_MANAGER)) exit('0');
		// Get user's verification code first
		$userInfo = User::getUserInfo($_POST['username']);
		if ($userInfo === false) exit('0');
		// Set up the email
		$email = new Message();
		$email->setTo($userInfo['user_email']);
		$email->setFrom($project_contact_email);
		$email->setFromName($GLOBALS['project_contact_name']);
		$email->setSubject('[REDCap] ' . $lang['system_config_445']);
		$msg = "<b>".$lang['system_config_586'] . " \"{$_POST['username']}\"".$lang['period']." ".$lang['system_config_587']."</b><br><br>".
			   User::renderTwoFactorInstructionsAuthenticator($_POST['username'], true, true, false);
		$email->setBody($msg, true);
		if ($email->send()) {
			// Logging
			Logging::logEvent("","redcap_user_information","MANAGE",$_POST['username'],"username = '" . db_escape($_POST['username']) . "'","Email the Google Authenticator setup instructions for 2-step login");
			exit('1');
		}
		exit('0');
		break;
	
	// Set user's two-factor auth code expiration time (default 2-min)
	case "set_two_factor_code_expiration":
		if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('0');
		// Get user's verification code first
		$thisUserInfo = User::getUserInfo($_POST['username']);
		if ($thisUserInfo === false) exit('0');
		// Is the value a valid option?
		if (in_array($_POST['two_factor_code_expiration'], Authentication::getTwoFactorCodeExpirationTimes())) {
			// Update table
			$sql = "update redcap_user_information
					set two_factor_auth_code_expiration = '" . db_escape($_POST['two_factor_code_expiration']) . "'
					where username = '" . db_escape($_POST['username']) . "'";
			$q = db_query($sql);
			// Logging
			if ($q) {
				Logging::logEvent($sql,"redcap_user_information","MANAGE",$_POST['username'],"username = '" . db_escape($_POST['username']) . "'","Modify expiration time for 2-step login code");
				exit('1');
			}
		}
		exit('0');
		break;

	// Resend a user's verification email
	case "resend_verification_email":
		if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('0');
		// Get user's verification code first
		$thisUserInfo = User::getUserInfo($_GET['username']);
		if ($thisUserInfo === false) exit('0');
		// Validate email account number (1, 2, or 3)
		if (!in_array($_GET['email_account'], array(1,2,3))) exit('0');
		// Determine which user_email field we're updating based upon $email_account
		$user_email_verify_field = "email" . ($_GET['email_account'] > 1 ? $_GET['email_account'] : "") . "_verify_code";
		// Verify that we have an email address to send to
		$user_email_field = "user_email" . ($_GET['email_account'] > 1 ? $_GET['email_account'] : "");
		// Make sure we have a verification code and email to send
		if ($thisUserInfo[$user_email_field] == '' || $thisUserInfo[$user_email_verify_field] == '') exit('0');
		// Send verification email to user
		$emailSent = User::sendUserVerificationCode($thisUserInfo[$user_email_field], $thisUserInfo[$user_email_verify_field], $_GET['email_account'], $_GET['username']);
		// If failed, give error
		if (!$emailSent) exit('0');
		// Return html for dialog
		$popupContent = RCView::div(array('class'=>'darkgreen','style'=>'padding:10px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['control_center_4417'] . RCView::SP .
							RCView::a(array('href'=>"mailto:".$thisUserInfo[$user_email_field]), $thisUserInfo[$user_email_field]) .
							$lang['period']
						);
		exit($popupContent);
		break;

	// Remove a user's email verification code
	case "remove_verification_code":
		if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('0');
		// Get user's verification code first
		$thisUserInfo = User::getUserInfo($_GET['username']);
		if ($thisUserInfo === false) exit('0');
		// Validate email account number (1, 2, or 3)
		if (!in_array($_GET['email_account'], array(1,2,3))) exit('0');
		// Verify that we have an email address to send to
		$user_email_field = "user_email" . ($_GET['email_account'] > 1 ? $_GET['email_account'] : "");
		// Make sure we have an email to send
		if ($thisUserInfo[$user_email_field] == '') exit('0');
		// Send verification email to user
		$codeRemoved = User::removeUserVerificationCode($_GET['username'], $_GET['email_account']);
		// If failed, give error
		if (!$codeRemoved) exit('0');
		// Logging
		Logging::logEvent("","redcap_user_information","MANAGE",$this_userid,"username = '$this_userid'","Auto-verify user email address");
		// Return html for dialog
		$popupContent = RCView::div(array('class'=>'darkgreen','style'=>'padding:10px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['control_center_4420']
						);
		exit($popupContent);
		break;

	// Designate selected user as a new Super User
	case "add_super_user":
		if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('0');
		$sql = "update redcap_user_information set super_user = 1, account_manager = 0 where username = '" . db_escape($_GET['username']) . "'";
		$q = db_query($sql);
		// Logging
		if ($q) {
			Logging::logEvent($sql,"redcap_user_information","MANAGE",$_GET['username'],"username = '" . db_escape($_GET['username']) . "'","Designate admininstator");
			exit('1');
		}
		break;

	// Designate selected user as a new Account Manager
	case "add_account_manager":
		if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('0');
		$sql = "update redcap_user_information set super_user = 0, account_manager = 1 where username = '" . db_escape($_GET['username']) . "'";
		$q = db_query($sql);
		// Logging
		if ($q) {
			Logging::logEvent($sql,"redcap_user_information","MANAGE",$_GET['username'],"username = '" . db_escape($_GET['username']) . "'","Designate account manager");
			exit('1');
		}
		break;

	// Remove Super User status
	case "remove_super_user":
	case "remove_account_manager":
		if ($_SERVER['REQUEST_METHOD'] != 'POST') exit('0');
		$sql = "update redcap_user_information set super_user = 0, account_manager = 0 where username = '" . db_escape($_GET['username']) . "'";
		$q = db_query($sql);
		// Logging
		if ($q) {
			$loggingDescr = ($action == 'remove_super_user') ? "Remove admininstator" : "Remove account manager";
			Logging::logEvent($sql,"redcap_user_information","MANAGE",$_GET['username'],"username = '" . db_escape($_GET['username']) . "'",$loggingDescr);
			exit('1');
		}
		break;

	// Allow/disallow user to create or copy projects
	case "allow_create_db":
		$sql = "update redcap_user_information set allow_create_db = {$_GET['allow_create_db']} where username = '" . db_escape($_GET['username']) . "'";
		$q = db_query($sql);
		// Logging
		$allowText = $_GET['allow_create_db'] ? "Grant user rights to create or copy projects" : "Remove user rights to create or copy projects";
		if ($q) Logging::logEvent($sql,"redcap_user_information","MANAGE",$_GET['username'],"username = '" . db_escape($_GET['username']) . "'",$allowText);
		print $q ? "1" : "0";
		exit;
		break;

	// A table-based user resets their own password and sets as temporary password
	case "reset_password_as_temp":
		$sql = "update redcap_auth set temp_pwd = 1 where username = '" . db_escape(USERID) . "'";
		$q = db_query($sql);
		// Logging
		if ($q) Logging::logEvent($sql,"redcap_auth","MANAGE",USERID,"username = '" . db_escape(USERID) . "'","Reset own password");
		print $q ? "1" : "0";
		exit;
		break;

	// A table-based user resets their security question
	case "reset_security_question":
		$sql = "update redcap_auth set password_question = null, password_answer = null,
				password_question_reminder = null where username = '" . db_escape(USERID) . "'";
		$q = db_query($sql);
		// Logging
		if ($q) Logging::logEvent($sql,"redcap_auth","MANAGE",USERID,"username = '" . db_escape(USERID) . "'","Reset user security question");
		print $q ? "1" : "0";
		exit;
		break;

	// Reset a table-based user's password and set as temporary password, then send user an email with new password
	case "reset_password":
		$resetSuccess = Authentication::resetPasswordSendEmail($_GET['username'], true);
		if ($resetSuccess) {
			$this_user_email = db_result(db_query("select user_email from redcap_user_information where username = '" . db_escape($_GET['username']) . "'"), 0);
			exit("{$lang['control_center_64']} $this_user_email {$lang['control_center_4490']}");
		}
		break;
		
	case '':
		break;
		
	default:
		exit('0');
		break;
}


/**
 * VIEW USER
 */
if ($_GET['user_view'] == "view_user")
{
	// Create "add new user" text box
	$usernameTextboxValue  = (isset($_GET['username']) ? $_GET['username'] : "");
	$usernameTextbox = RCView::text(array('id'=>'user_search', 'class'=>'x-form-text x-form-field', 'maxlength'=>'255',
						'style'=>'width:100%;max-width:450px;', 'value'=>$usernameTextboxValue,
						'onkeydown'=>"if(event.keyCode==13) { $('#user_search_btn').click(); return false; }"));
	print 	RCView::div(array(),
				RCView::div(array('style'=>'margin-bottom:10px;'),
					RCView::b($lang['control_center_4640'].$lang['colon']) . " " . $lang['control_center_4429']
				) .
				$usernameTextbox .
				RCView::button(array('id'=>'user_search_btn', 'class'=>'btn btn-primaryrc btn-xs', 'style'=>'margin-left:4px;font-size:13px;', 'onclick'=>"
						var us_ob = $('#user_search');
						us_ob.trigger('focus');
						us_ob.val( trim(us_ob.val()) );
						var userParts = us_ob.val().split(' ');
						us_ob.val( trim(userParts[0]) );
						if (us_ob.val().length > 0 && !chk_username(us_ob)) {
							return alertbad(us_ob,'".js_escape($lang['rights_354'])."');
						}
						view_user( us_ob.val() );
						modifyURL(app_path_webroot+'ControlCenter/view_users.php?username='+us_ob.val());"), $lang['control_center_439']) .
				RCView::span(array('id'=>'view_user_progress', 'style'=>'margin-left:8px;visibility:hidden;'),
					RCView::img(array('src'=>'progress_circle.gif'))
				)
			);

	## Display user information table if username has been selected
	if (isset($_GET['username']))
	{
		// Get user info
		$thisUserInfo = User::getUserInfo($_GET['username']);
		// If user doesn't exist...
		if ($thisUserInfo === false) {
			print 	RCView::div(array('class'=>'yellow'),
						RCView::img(array('src'=>'exclamation_orange.png')) .
						$lang['control_center_441']
					);
			exit;
		}
		// Set user info vars
		$user_creation = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_creation']);
		$first_login = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_firstvisit']);
		$first_activity = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_firstactivity']);
		$last_activity  = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_lastactivity']);
		$last_login  = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_lastlogin']);
		$user_suspended_time = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_suspended_time']);
		$user_expiration = DateTimeRC::format_ts_from_ymd($thisUserInfo['user_expiration']);
		$display_on_email_users = $thisUserInfo['display_on_email_users'];
		$this_user_lastname  = $thisUserInfo['user_lastname'];
		$this_user_firstname = $thisUserInfo['user_firstname'];
		$this_user_inst_id   = $thisUserInfo['user_inst_id'];
		$this_user_phone   = $thisUserInfo['user_phone'];
		$this_user_phone_sms   = $thisUserInfo['user_phone_sms'];
		$this_user_sponsor   = $thisUserInfo['user_sponsor'];
		$this_user_comments  = $thisUserInfo['user_comments'];
		if (strlen($this_user_comments) > 70) {
			$this_user_comments_full = $this_user_comments;
			$this_user_comments = substr($this_user_comments, 0, 67) . "...";
		}
		$this_ui_id = $thisUserInfo['ui_id'];
		$this_user_email  = $thisUserInfo['user_email'];
		$this_user_email2 = $thisUserInfo['user_email2'];
		$this_user_email3 = $thisUserInfo['user_email3'];
		$this_user_email_verified = ($this_user_email != '' && $thisUserInfo['email_verify_code'] == '');
		$this_user_email2_verified = ($this_user_email2 != '' && $thisUserInfo['email2_verify_code'] == '');
		$this_user_email3_verified = ($this_user_email3 != '' && $thisUserInfo['email3_verify_code'] == '');
		// Check if user is currently logged in (ignore if suspended)
		$logoutWindow = date("Y-m-d H:i:s", mktime(date("H"),date("i")-$autologout_timer,date("s"),date("m"),date("d"),date("Y")));
		$sql = "select 1 from redcap_sessions s, redcap_log_view v, redcap_user_information i
				where v.user = '".db_escape($_GET['username'])."' and v.user = i.username and v.session_id = s.session_id
				and v.ts >= '$logoutWindow' and i.user_suspended_time is null limit 1";
		$isLoggedIn = (db_num_rows(db_query($sql)) > 0);
		if ($isLoggedIn) {
			$isLoggedInIcon = RCView::img(array('src'=>'circle_green_tick.png')) .
							  RCView::span(array('style'=>'color:green;'), $lang['design_100']);
		} else {
			$isLoggedInIcon = RCView::img(array('src'=>'stop_gray.png')) .
							  RCView::span(array('style'=>'color:#800000;'), $lang['design_99']);
		}
		// Set suspended user html (button or link)
		if ($user_suspended_time == "")
		{
			$unsuspend_link = "";
			$user_suspended_time = "<input type='button' value=\"" . js_escape2($lang['control_center_142']) . "\" style='font-size:11px;' onclick=\"
										if (confirm('" . js_escape($lang['control_center_143']) . "')) {
											$.post(app_path_webroot+'ControlCenter/suspend_user.php', { suspend: 1, username: '{$_GET['username']}' },function(data) {
												if (data != '0') {
													$.get('user_controls_ajax.php', { user_view: 'view_user', username: '{$_GET['username']}' },function(data) {
														$('#view_user_div').html(data);
														highlightTable('indv_user_info',2500);
												        enableUserSearch();
													});
													simpleDialog('" . js_escape($lang['control_center_144']) . "');
												} else {
													alert(woops);
												}
											});
										}
									\">";
		} else {
			$unsuspend_link = "&nbsp;(<a href='javascript:;' style='text-decoration: underline; font-size: 10px; font-family: tahoma;' onclick=\"
										if (confirm('" . js_escape($lang['control_center_147']) . "')) {
											$.post(app_path_webroot+'ControlCenter/suspend_user.php', { suspend: 0, username: '{$_GET['username']}' },function(data) {
												if (data != '0') {
													$.get('user_controls_ajax.php', { user_view: 'view_user', username: '{$_GET['username']}' },function(data) {
														$('#view_user_div').html(data);
														highlightTable('indv_user_info',2500);
												        enableUserSearch();
													});
													simpleDialog('" . js_escape($lang['control_center_146']) . "');
												} else {
													alert(woops);
												}
											});
										}
									\">" . $lang['control_center_145'] . "</a>)";
		}
		// Is a table-based user?
		$isTableUser = User::isTableUser($_GET['username']);
		// Retrieve project access count
		$proj_access_count = db_result(db_query("select count(1) from redcap_user_rights u, redcap_projects p where u.project_id = p.project_id
												 and u.username = '" . db_escape($_GET['username']) . "'"), 0);
		// Retrieve if user can create/copy new projects
		$allow_create_db = $thisUserInfo['allow_create_db'];
		// Render table
		print  "<table id='indv_user_info' cellpadding=0 cellspacing=0 style='margin-top:20px;width:100%;border:0;border-collapse:collapse;'>
					<tr>
						<td class='blue' colspan='2'>
							<div style='float:left;padding:5px 0 0 5px;font-size:13px;'>
								{$lang['control_center_4408']}
								\"<b>{$_GET['username']}</b>\" (<b>$this_user_firstname $this_user_lastname</b>)
							</div>";

		?>
		<form method="post" id="edit_user_form" action="<?php echo APP_PATH_WEBROOT ?>ControlCenter/create_user.php" style="float:right;display: inline;">
			<input type="hidden" name="redcap_csrf_token" value="<?php echo System::getCsrfToken() ?>">
			<input type="hidden" name="ui_id" value="<?php echo $this_ui_id; ?>">
			<button style="padding:1px 5px 2px 5px;margin-right:50px;" onclick="$('#edit_user_form').submit();">
				<img src="<?php echo APP_PATH_IMAGES ?>user_edit.png" style="vertical-align:middle;">
				<span style="vertical-align:middle;"><?php echo $lang['control_center_239'] ?></span>
			</button>
		</form>
		<div class="clear"></div>
		<?php

		print  "		</td>
					</tr>
					<tr>
						<td class='header' colspan='2' style='padding:5px 10px 3px;'>
							{$lang['control_center_4409']}
						</td>
					</tr>
					<!-- Username -->
					<tr>
						<td class='data2'>
							{$lang['global_11']}
						</td>
						<td class='data2' style='font-weight:bold;width:300px;'>
							{$_GET['username']}
						</td>
					</tr>
					<!-- Name -->
					<tr>
						<td class='data2'>
							{$lang['email_users_12']}
						</td>
						<td class='data2' style='font-weight:bold;width:300px;'>
							$this_user_firstname $this_user_lastname
						</td>
					</tr>
					<!-- email -->
					<tr>
						<td class='data2'>
							{$lang['user_45']}
						</td>
						<td class='data2' style='width:300px;'>
							" .
							($this_user_email == ""
								? "<i>{$lang['database_mods_81']}</i>"
								: RCView::a(array('href'=>"mailto:$this_user_email", 'style'=>'margin-right:10px;font-size:11px;font-family:verdana;text-decoration:underline;'), $this_user_email) .
								  ($isTableUser ? "" :
									  ($this_user_email_verified
										 ? 	RCView::img(array('src'=>'security-high.png', 'style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;font-size:11px;color:green;'), $lang['control_center_4413'])
										 : 	RCView::img(array('src'=>'security-low.png', 'style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;font-size:11px;color:#C00000;'), $lang['control_center_4414']) .
											RCView::div(array('style'=>'text-align:right;padding-top:5px;'),
												RCView::button(array('style'=>'font-size:11px;margin:0 5px;', 'onclick'=>"resendVerificationEmail('".js_escape($_GET['username'])."',1);"), $lang['control_center_4415']) .
												RCView::button(array('style'=>'font-size:11px;', 'onclick'=>"autoVerifyEmail('".js_escape($_GET['username'])."',1);"), $lang['control_center_4416'])
											)
									  )
									)
							) . "
						</td>
					</tr>
					<!-- email2 -->
					<tr>
						<td class='data2'>
							{$lang['user_46']}
						</td>
						<td class='data2' style='width:300px;'>
							" .
							($this_user_email2 == ""
								? "<i>{$lang['database_mods_81']}</i>"
								: RCView::a(array('href'=>"mailto:$this_user_email2", 'style'=>'margin-right:10px;font-size:11px;font-family:verdana;text-decoration:underline;'), $this_user_email2) .
								  ($this_user_email2_verified
									 ? 	RCView::img(array('src'=>'security-high.png', 'style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;font-size:11px;color:green;'), $lang['control_center_4413'])
									 : 	RCView::img(array('src'=>'security-low.png', 'style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;font-size:11px;color:#C00000;'), $lang['control_center_4414']) .
										RCView::div(array('style'=>'text-align:right;padding-top:5px;'),
											RCView::button(array('style'=>'font-size:11px;margin:0 5px;', 'onclick'=>"resendVerificationEmail('".js_escape($_GET['username'])."',2);"), $lang['control_center_4415']) .
											RCView::button(array('style'=>'font-size:11px;', 'onclick'=>"autoVerifyEmail('".js_escape($_GET['username'])."',2);"), $lang['control_center_4416'])
										)
								  )
							) . "
						</td>
					</tr>
					<!-- email3 -->
					<tr>
						<td class='data2'>
							{$lang['user_55']}
						</td>
						<td class='data2' style='width:300px;'>
							" .
							($this_user_email3 == ""
								? "<i>{$lang['database_mods_81']}</i>"
								: RCView::a(array('href'=>"mailto:$this_user_email3", 'style'=>'margin-right:10px;font-size:11px;font-family:verdana;text-decoration:underline;'), $this_user_email3) .
								    ($this_user_email3_verified
										 ? 	RCView::img(array('src'=>'security-high.png', 'style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;font-size:11px;color:green;'), $lang['control_center_4413'])
										 : 	RCView::img(array('src'=>'security-low.png', 'style'=>'vertical-align:middle;')) . RCView::span(array('style'=>'vertical-align:middle;font-size:11px;color:#C00000;'), $lang['control_center_4414']) .
											RCView::div(array('style'=>'text-align:right;padding-top:5px;'),
												RCView::button(array('style'=>'font-size:11px;margin:0 5px;', 'onclick'=>"resendVerificationEmail('".js_escape($_GET['username'])."',3);"), $lang['control_center_4415']) .
												RCView::button(array('style'=>'font-size:11px;', 'onclick'=>"autoVerifyEmail('".js_escape($_GET['username'])."',3);"), $lang['control_center_4416'])
											)
									)
							) . "
						</td>
					</tr>";
		// User's landline phone and SMS phone (for Twilio two-step login)
		if ($two_factor_auth_enabled && $two_factor_auth_twilio_enabled) {
			print "	<tr>
						<td class='data2'>
							{$lang['system_config_478']}
						</td>
						<td class='data2' style='width:300px;'>".($this_user_phone == '' ? "<i>{$lang['database_mods_81']}</i>" : formatPhone($this_user_phone))."</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['system_config_452']}
						</td>
						<td class='data2' style='width:300px;'>".($this_user_phone_sms == '' ? "<i>{$lang['database_mods_81']}</i>" : formatPhone($this_user_phone_sms))."</td>
					</tr>";
		}
		print "		<!-- Institution ID -->
					<tr>
						<td class='data2'>
							{$lang['control_center_236']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($this_user_inst_id == "" ? "<i>{$lang['database_mods_81']}</i>" : $this_user_inst_id) . "
						</td>
					</tr>
					<!-- Sponsor -->
					<tr>
						<td class='data2'>
							{$lang['user_72']} {$lang['user_75']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($this_user_sponsor == "" ? "<i>{$lang['database_mods_81']}</i>" :
								RCView::a(array('href'=>"javascript:;", 'style'=>'font-size:11px;font-family:verdana;text-decoration:underline;', 'onclick'=>"view_user('".js_escape($this_user_sponsor)."');modifyURL(app_path_webroot+'ControlCenter/view_users.php?username=".js_escape($this_user_sponsor)."');"), $this_user_sponsor)
							) . "
						</td>
					</tr>
					<!-- Allow user to create/copy projects? -->
					<tr>
						<td class='data2'>
							{$lang['control_center_79']}";
		// If only super users can create new projects, then add note that the create/copy feature is overridden.
		if ($superusers_only_create_project) {
			print  "		<div style='margin-top:3px;color:#800000;line-height:11px;'>
								({$lang['global_02']}{$lang['colon']} {$lang['control_center_80']})
							</div>";
		}
		print  "		</td>
						<td class='data2' style='width:300px;'>
							".($allow_create_db ? $lang['design_100'] : $lang['design_99'])."
						</td>
					</tr>
					<!-- Comments -->
					<tr>
						<td class='data2'>
							{$lang['dataqueries_146']}
						</td>
						<td class='data2' style='width:300px;'>
							" .
							(!isset($this_user_comments_full)
								?  ($this_user_comments == "" ? "<i>{$lang['database_mods_81']}</i>" :
										RCView::div(array('style'=>'line-height:11px;'), $this_user_comments)
									)
								:	RCView::span(array('style'=>'line-height:11px;', 'id'=>'user_comments_trunc'), $this_user_comments) .
									RCView::span(array('style'=>'display:none;line-height:11px;', 'id'=>'user_comments_full'), $this_user_comments_full) .
									RCView::a(array('href'=>"javascript:;", 'style'=>'font-size:10px;font-family:verdana;text-decoration:underline;',
										'onclick'=>"$(this).hide();$('#user_comments_trunc').hide();$('#user_comments_full').show();"), $lang['create_project_94'])
							) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_4382']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($user_expiration == "" ? "<i>{$lang['database_mods_81']}</i>" : $user_expiration) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_4492']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($display_on_email_users == "1" ? $lang['design_100'] : $lang['design_99']) . "
						</td>
					</tr>";
		// If user can create projects via DDP Data Mart
		$DDP = new DynamicDataPull(0, 'FHIR');
		if ($fhir_data_mart_create_project && $DDP->isEnabledInSystemFhir()) {
			print "	<tr>
						<td class='data2'>
							{$lang['control_center_4705']}
						</td>
						<td class='data2' style='width:300px;'>
							".($thisUserInfo['fhir_data_mart_create_project'] == '1' ? $lang['design_100'] : $lang['design_99'])."
						</td>
					</tr>";
		}
		// If user can auto-approve their own API tokens
		if ($api_token_request_type == 'auto_approve_selected') {
			print "	<tr>
						<td class='data2'>
							{$lang['api_140']}
						</td>
						<td class='data2' style='width:300px;'>
							".($thisUserInfo['api_token_auto_request'] == '1' ? $lang['design_100'] : $lang['design_99'])."
						</td>
					</tr>";
		}
		// Set expiration time for this user's 2FA verification code
		if ($two_factor_auth_enabled) {
			print "	<tr>
						<td class='data2'>
							{$lang['control_center_4495']}
							<div style='margin-top:1px;color:#777;font-size:10px;font-family:tahoma;'>
								{$lang['system_config_526']}
							</div>
						</td>
						<td class='data2' style='width:300px;'>
							<div id='2fa_code_display'>
								<span id='2fa_code_expire_time'>{$thisUserInfo['two_factor_auth_code_expiration']}</span> {$lang['survey_428']}
								<img src='".APP_PATH_IMAGES."pencil_small.png' style='margin:0 2px 0 12px;'><a href='javascript:;'
									onclick=\"$('#2fa_code_display').hide();$('#2fa_code_edit').show();\" style='text-decoration:underline;font-size:11px;'>{$lang['design_169']}</a>
							</div>
							<div id='2fa_code_edit' style='display:none;'>
								".RCView::select(array('style'=>'font-size:11px;font-family:Verdana;', 'onchange'=>"
									var ddval = $(this).val();
									$.post(app_path_webroot+'ControlCenter/user_controls_ajax.php?action=set_two_factor_code_expiration',{ username: '{$_GET['username']}', two_factor_code_expiration: ddval },function(data){
										if (data != '1') {
											alert(woops);
											window.location.reload();
										} else {
											$('#2fa_code_display').show();
											$('#2fa_code_edit').hide();
											$('#2fa_code_expire_time').html(ddval);
										}
									});
									"),
									Authentication::getTwoFactorCodeExpirationTimesDropdown(), $thisUserInfo['two_factor_auth_code_expiration']
								)."
							</div>
						</td>
					</tr>";
			if ($two_factor_auth_authenticator_enabled) {
				print "	<tr>
							<td class='data2'>
								{$lang['system_config_584']}
							</td>
							<td class='data2' style='width:300px;'>
								<button style='font-size:11px;' onclick=\"
									$.post('user_controls_ajax.php?action=email_authenticator_setup', { username: '{$_GET['username']}' }, function(data) {
										if (data != '1') {
											alert(woops);
											return;
										}
										simpleDialog('" . js_escape("<img src='".APP_PATH_IMAGES."tick.png'> ".$lang['survey_225']." <b>{$thisUserInfo['user_email']}</b>") . "','" . js_escape($lang['system_config_368']) . "');
									});
								\"><img src='".APP_PATH_IMAGES."google_authenticator_sm.png'> {$lang['system_config_585']}</button>
							</td>
						</tr>";
			}
		}
		print "		<tr>
						<td class='header' colspan='2' style='padding:5px 10px 3px;'>
							{$lang['control_center_4410']}
						</td>
					</tr>
					<!-- Logged in? -->
					<tr>
						<td class='data2'>
							{$lang['control_center_431']}
						</td>
						<td class='data2' style='width:300px;'>
							$isLoggedInIcon
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_76']}".
							(ACCOUNT_MANAGER ? "" : "
								<span style='padding-left:3px;'>
									(<a style='text-decoration:underline;font-size:10px;font-family:tahoma;'
										href='" . APP_PATH_WEBROOT . "ControlCenter/view_projects.php?userid={$_GET['username']}'>{$lang['control_center_402']}</a>)
								</span>"
							)."
						</td>
						<td class='data2' style='width:300px;'>
							$proj_access_count
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_4383']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($user_creation == "" ? "<i>{$lang['database_mods_81']}</i>" : $user_creation) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_72']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($first_login == "" ? "<i>{$lang['database_mods_81']}</i>" : $first_login) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_430']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($last_login == "" ? "<i>{$lang['database_mods_81']}</i>" : $last_login) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_73']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($first_activity == "" ? "<i>{$lang['database_mods_81']}</i>" : $first_activity) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_74']}
						</td>
						<td class='data2' style='width:300px;'>
							" . ($last_activity == "" ? "<i>{$lang['database_mods_81']}</i>" : $last_activity) . "
						</td>
					</tr>
					<tr>
						<td class='data2'>
							{$lang['control_center_138']} $unsuspend_link
						</td>
						<td class='data2' style='width:300px;'>
							$user_suspended_time
						</td>
					</tr>";
		// If user is a table-based user (i.e. in redcap_auth table), then give option to reset password
		$isTableUser = (strpos($auth_meth, "table") !== false && User::isTableUser($_GET['username']));
		if ($isTableUser) {
			// Determine last time that their password was reset (if any)
			$lastPasswordReset = Authentication::getUserLastPasswordResetTime($_GET['username']);
			// Button to reset password
			print  "<tr>
						<td class='data2'>
							{$lang['control_center_4411']}
						</td>
						<td class='data2' style='width:300px;'>
							<input type='button' value='" . js_escape($lang['control_center_140']) . "' style='font-size:11px;' onclick=\"
								if (confirm('" . js_escape($lang['control_center_81']) . " \'{$_GET['username']}\'?\\n\\n" . js_escape($lang['control_center_4489']) . "')) {
									$.post('user_controls_ajax.php?username={$_GET['username']}&action=reset_password', { }, function(data) {
										simpleDialog(data);
									});
								}
							\">
							<div style='color:#666;line-height:11px;'>
								".($lastPasswordReset == null ? $lang['control_center_383'] : $lang['control_center_384'] . ' ' . DateTimeRC::format_ts_from_ymd($lastPasswordReset))."
							</div>
						</td>
					</tr>";
		}

		// Button to delete user
		$deleteUserBtn = "<button style='font-size:11px;color:#800000;' onclick=\"
							if (confirm('" . js_escape($lang['control_center_83']) . " \'{$_GET['username']}\'?\\n\\n" . js_escape($lang['control_center_84']) . " " . ($proj_access_count > 0 ? js_escape($lang['control_center_85']) . " $proj_access_count " . js_escape($lang['control_center_86']) : "") . "')) {
								$.post('delete_user.php', { username: '{$_GET['username']}' },
									function(data) {
										if (data != '0') {
											$.get('user_controls_ajax.php', { user_view: 'view_user' },
												function(data) {
													$('#view_user_div').html(data);
												}
											);
											simpleDialog('" . js_escape($lang['control_center_87']) . " \'{$_GET['username']}\' " . js_escape($lang['control_center_88']) . "');
										} else {
											simpleDialog('{$lang['global_01']}{$lang['colon']} " . js_escape($lang['control_center_89']) . "');
										}
									}
								);
							}\">
							<img src='".APP_PATH_IMAGES."cross_small2.png' style='vertical-align:middle;'>
							<span style='vertical-align:middle;'>".$lang['control_center_139']."</span>
						</button>";
		if (ACCOUNT_MANAGER && $thisUserInfo['super_user']) {
			$deleteUserBtn = "You are not allowed to delete this user account because the user is a REDCap Administrator.";
		}
		print  "<tr>
					<td class='data2'>{$lang['control_center_4412']}</td>
					<td class='data2' style='width:300px;'>$deleteUserBtn</td>
				</tr>";
		
		print  "</table>";
	}

}