<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Get user info
$user_info = User::getUserInfo($userid);

// Display header
$objHtmlPage = new HtmlPage();
$objHtmlPage->PrintHeaderExt();
// Display link on far right to log out
print 	RCView::div(array('style'=>'text-align:right;'),
			RCView::a(array('href'=>'javascript:;','onclick'=>"window.location.href = app_path_webroot_full+'?logout=1';",'style'=>'text-decoration:underline;'), $lang['bottom_02'])
		);


## IF USER HAS AN EMAIL, BUT IT HASN'T BEEN VERIFIED YET.
if ($user_info['user_email'] != "" && $user_info['email_verify_code'] != "")
{
	// If user clicked verification code link in their email, validation the code and complete the setup process
	if (isset($_GET['user_verify']))
	{
		// Verify the code provided
		$emailAccount = User::verifyUserVerificationCode($userid, $_GET['user_verify']);
		if ($emailAccount !== false) {
			// Activate the account by removing the verification code
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
			## QR code dialog for enabling REDCap in Google Authenticator
			$two_factor_auth_authenticator_enabled = db_result(db_query("select value from redcap_config where field_name = 'two_factor_auth_authenticator_enabled'"), 0);
			if ($two_factor_auth_enabled && $two_factor_auth_authenticator_enabled) {
				print   "<style type='text/css'>#two_factor_totp_setup { display: block; }</style>";
				print 	RCView::h4(array('style'=>'margin:50px 0 0;font-weight:bold;border-bottom: 1px solid #ccc;padding-bottom: 8px;'), '<i class="fas fa-bell"></i> '.$lang['user_105']);
				print 	User::renderTwoFactorInstructionsAuthenticator($userid);
			}
		} else {
			// Error: code could not be verified
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
	// If verification email was just sent, then display confirmation to user
	elseif (isset($_GET['verify_email_sent']))
	{
		// Account exists but user changed their email address on User Profile page, then give confirmation of verification email sent
		if (PAGE == 'Profile/user_profile.php') {
			print 	RCView::h4(array('style'=>'margin:10px 0 25px;color:green;'),
						RCView::img(array('src'=>'tick.png')) . $lang['user_38']
					) .
					RCView::div(array('class'=>'darkgreen','style'=>'padding:10px;margin-bottom:30px;'),
						$lang['user_39'] . RCView::SP .
						RCView::a(array('href'=>"mailto:".$user_info['user_email'],'style'=>'color:#800000;text-decoration:underline;'), $user_info['user_email']) .
						RCView::SP . $lang['user_40'] . RCView::br() . RCView::br() .
						RCView::b(
							RCView::img(array('src'=>'email_go.png')) .
							$lang['user_28'] . RCView::SP .
							RCView::a(array('href'=>"mailto:".$user_info['user_email'],'style'=>'color:#800000;text-decoration:underline;font-weight:bold;'), $user_info['user_email'])
						)
					);
		}
		// Account was just created
		else {
			print 	RCView::h4(array('style'=>'margin:10px 0 25px;color:green;'),
						RCView::img(array('src'=>'tick.png')) . $lang['user_25']
					) .
					RCView::div(array('class'=>'darkgreen','style'=>'padding:10px;margin-bottom:30px;'),
						$lang['user_26'] . RCView::SP .
						RCView::a(array('href'=>"mailto:".$user_info['user_email'],'style'=>'color:#800000;text-decoration:underline;'), $user_info['user_email']) .
						RCView::SP . $lang['user_27'] . RCView::br() . RCView::br() .
						RCView::b(
							RCView::img(array('src'=>'email_go.png')) .
							$lang['user_28'] . RCView::SP .
							RCView::a(array('href'=>"mailto:".$user_info['user_email'],'style'=>'color:#800000;text-decoration:underline;font-weight:bold;'), $user_info['user_email'])
						)
					);
		}
	}
	// Display notice to user that their verification is pending
	else {
		print 	RCView::h4(array('style'=>'margin:10px 0 25px;'),
					RCView::img(array('src'=>'clock_frame.png')) .
					$lang['user_22']
				) .
				RCView::div(array('class'=>'yellow','style'=>'padding:10px;margin-bottom:30px;'),
					$lang['user_23'] . RCView::SP .
					RCView::a(array('href'=>"mailto:".$user_info['user_email'],'style'=>'color:#800000;text-decoration:underline;'), $user_info['user_email']) .
					$lang['period'] . RCView::SP . $lang['user_24'] . RCView::br() . RCView::br() .
					RCView::b(
						RCView::img(array('src'=>'email_go.png')) .
						$lang['user_28'] . RCView::SP .
						RCView::a(array('href'=>"mailto:".$user_info['user_email'],'style'=>'color:#800000;text-decoration:underline;font-weight:bold;'), $user_info['user_email'])
					)
				);
	}
}




## IF USER DOES NOT HAVE AN EMAIL ASSOCIATED WITH THEIR ACCOUNT, THEM PROMPT THEM TO ENTER IT
elseif ($user_info['user_email'] == "")
{
	?>
	<h4 style="color:#800000;"><img src="<?php echo APP_PATH_IMAGES ?>user_edit.png"> <?php echo $lang['user_01'] ?></h4>
	<p>
		<?php echo $lang['user_02'] ?>
	</p>
	<br>
	<div style='max-width:700px;text-align:center;'>
	<form method="post" action="<?php echo APP_PATH_WEBROOT ?>Profile/user_info_action.php">
		<table id="user_info" align='center'>
			<tr>
				<td align='left' style='padding-bottom:15px;'><?php echo $lang['global_11'].$lang['colon'] ?> </td>
				<td style='padding-bottom:15px;font-weight:bold;'>
					<?php echo $userid ?>
					<input type="hidden" name="userid" value="<?php echo $userid ?>">
				</td>
			</tr>
			<tr>
				<td align='left'><?php echo $lang['pub_023'].$lang['colon'] ?> </td>
				<td><input type="text" id="firstname" name="firstname" class='x-form-text x-form-field' size=20 onkeydown='if(event.keyCode == 13) return false;'> </td>
			</tr>
			<tr>
				<td align='left'><?php echo $lang['pub_024'].$lang['colon'] ?> </td>
				<td><input type="text" id="lastname" name="lastname" class='x-form-text x-form-field' size=20 onkeydown='if(event.keyCode == 13) return false;'> </td>
			</tr>
			<tr>
				<td align='left'><?php echo $lang['global_33'].$lang['colon'] ?> </td>
				<td>
					<input type="text" id="email" name="email" class='x-form-text x-form-field' size=35 onkeydown='if(event.keyCode == 13) return false;'
						onBlur="if (redcap_validate(this,'','','hard','email')) emailInDomainAllowlist(this);">
				</td>
			</tr>
			<tr>
				<td align='left'><?php echo $lang['user_15'].$lang['colon'] ?> </td>
				<td>
					<input type="text" id="email_dup" name="email_dup" class='x-form-text x-form-field' size=35 onkeydown='if(event.keyCode == 13) return false;'
						onBlur="if (!(redcap_validate(this,'','','hard','email') && emailInDomainAllowlist(this) !== false)) {
									return false;
								} else {
									validateEmailMatch2();
								}">
				</td>
			</tr>
			<tr>
				<td align='left'></td>
				<td style="color:#555;font-size:11px;">
					<div style="width:400px;line-height:12px;"><?php echo $lang['user_16'] ?></div>
				</td>
			</tr>
		</table>
		<p style='text-align:center;'>
			<input type='submit' value='<?php echo js_escape($lang['survey_200']) ?>' onclick="return validateUserInfoForm();">
		</p>
	</form>
	</div>
	<br>
	<script type='text/javascript'>
	function validateUserInfoForm() {
		if ($('#email').val().length < 1 || $('#email_dup').val().length < 1 || $('#firstname').val().length < 1 || $('#lastname').val().length < 1) {
			simpleDialog('<?php echo js_escape($lang['user_17']) ?>');
			return false;
		}
		if (!validateEmailMatch2()) {
			return false;
		}
		return true;
	}
	function validateEmailMatch2() {
		$('#email').val( trim($('#email').val()) );
		$('#email_dup').val( trim($('#email_dup').val()) );
		if ($('#email').val().length > 0 && $('#email_dup').val().length > 0 && $('#email').val() != $('#email_dup').val()) {
			simpleDialog('<?php echo js_escape($lang['user_18']) ?>',null,null,null,"$('#email_dup').focus();");
			return false;
		}
		return true;
	}
	</script>
	<style type="text/css">
	table#user_info { font-size:13px; }
	table#user_info td { text-align:left;padding: 3px 6px; }
	</style>
	<?php
}
// If user does have an email AND it has been verified, then redirect back to home page
else {
	System::redirectHome();
}

$objHtmlPage->PrintFooterExt();