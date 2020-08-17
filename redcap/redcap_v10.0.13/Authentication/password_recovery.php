<?php


define('NOAUTH', true);

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();

// Clean post username
if (isset($_POST['username'])) {
	$_POST['username'] = trim(strip_tags(label_decode($_POST['username'])));
}

// Form for entering username
$usernameForm = "<center>
	<form method='post' action='".PAGE_FULL."'>
	<table style='margin-top:15px;padding:15px 20px;width:300px;' class='blue'>
		<tr>
			<td style='padding:5px;'>
				{$lang['global_11']}{$lang['colon']} &nbsp;
			</td>
			<td style='padding:5px;'>
				<input type=\"text\" class='x-form-text x-form-field' name=\"username\" autocomplete='new-password' value=\"".(isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES) : "")."\">
			</td>
		</tr>
		<tr>
			<td style='padding:5px;'></td>
			<td style='padding:5px;'>
				<input type='submit' class='btn' value=\"".js_escape2($lang['pwd_reset_30'])."\" onclick=\"username.value=trim(username.value);if(username.value.length < 1) { alert('".js_escape($lang['pwd_reset_24'])."'); return false; }\">
			</td>
		</tr>
	</table>
	</form>
	</center>";


// Render instructions
print  "<h4 style='margin-top:40px;color:#800000;'>{$lang['pwd_reset_23']}</h4>
		<p>{$lang['pwd_reset_57']}</p>";


/**
 * CHECK IF IP IS BANNED
 * Check logging for past 24 hours to make sure this IP didn't get temporarily banned for this page in that period
 */
// Get timestamp for 1 day ago
$oneDayAgo = date("YmdHis", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
// Get IP
$ip = System::clientIpAddress();
// Banned log description
$bannedLogDescription = "Temporarily ban IP address for Password Recovery page";
// Check logging table for IP
$sql = "select 1 from redcap_log_event where ts > $oneDayAgo and description = '$bannedLogDescription' and ip = '".db_escape($ip)."' limit 1";
$q = db_query($sql);
if (db_num_rows($q) > 0)
{
	// Message that user is locked out temporarily
	print RCView::p(array('class'=>'yellow'),
			RCView::img(array('src'=>'exclamation_orange.png')) .
			RCView::b($lang['pwd_reset_51']) . RCView::br() . $lang['pwd_reset_53'] . RCView::b($ip) . $lang['pwd_reset_54']
		);
	// Footer
	print RCView::div(array('class'=>'space'), "&nbsp;");
	$objHtmlPage->PrintFooter();
	exit;
}
## If IP is not banned, then check if they've accessed this page 20x in the past minute. If they have, ban them (i.e. log it).
// Get timestamp for 1 min ago
$oneMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-1,date("s"),date("m"),date("d"),date("Y")));
// Check log_view table for IP in past minute
$sql = "select 1 from redcap_log_view where ts > '$oneMinAgo' and page = '".PAGE."'	and ip = '".db_escape($ip)."'";
$q = db_query($sql);
if (db_num_rows($q) >= 20)
{
	// Logging: Log that use has been banned
	Logging::logEvent("","redcap_auth","MANAGE",$ip,"ip = '$ip'",$bannedLogDescription);
	// Redirect page to itself so that the banned IP message will be displayed
	redirect(PAGE_FULL);
}


## Display instructions and username text field
if ($_SERVER['REQUEST_METHOD'] != 'POST')
{
	// Enter username
	print $usernameForm;
}


elseif (isset($_POST['username']))
{
	## CHECK USERNAME
	## First, make sure they don't already have 5 failed attempts in past 15 minutes. If so, don't process further.
	// Get timestamp for 30 minutes ago
	$thirtyMinAgo = date("YmdHis", mktime(date("H"),date("i")-30,date("s"),date("m"),date("d"),date("Y")));
	// Check logging table for failed attempts in past 15 minutes
	$sql = "select 1 from redcap_log_event where ts > $thirtyMinAgo and user = '".db_escape($_POST['username'])."'
			and description = 'Failed to reset own password'";
	$q = db_query($sql);
	if (db_num_rows($q) >= 4)
	{
		// Message that user is locked out temporarily
		print RCView::p(array('class'=>'yellow'),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				RCView::b($lang['pwd_reset_51']) . RCView::br() . $lang['pwd_reset_52']
			);
		// Footer
		print RCView::div(array('class'=>'space'), "&nbsp;");
		$objHtmlPage->PrintFooter();
		exit;
	}
	// Query tables to verify as user
	$sql = "select a.username, a.password_question, a.password_answer, i.username as info_username
			from redcap_auth a right outer join redcap_user_information i
			on a.username = i.username where i.username = '".db_escape($_POST['username'])."'";
	$q = db_query($sql);
	// Check if a user
	$isUser = (db_num_rows($q) > 0);
	// Is a REDCap user, so check if they are a table-based user
	$securityQid = ($isUser) ? db_result($q, 0, 'password_question') : null;
	$isTableBasedUser = ($isUser) ? (db_result($q, 0, 'username') != null) : null;
	// Is a real user AND is table-based user?
	if (!$isUser || !$isTableBasedUser || $securityQid == null) {
		// Use custom password reset text, if exists
		$resetPassInvalidText = (trim($password_recovery_custom_text) != '')
			? nl2br(decode_filter_tags($password_recovery_custom_text))
			: "{$lang['pwd_reset_25']} \"<b>".RCView::escape($_POST['username'])."</b>\" {$lang['pwd_reset_26']}";
		// Either not a REDCap user OR not a table-based user OR hasn't set up security question yet. Give error msg.
		$questionForm =	$usernameForm .
						RCView::div(array('class'=>'yellow','style'=>'margin:20px 0;max-width:100%;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							"<b>{$lang['pwd_reset_31']} \"<span style='color:#800000;'>".RCView::escape($_POST['username'])."\"</span></b><br><br>
							$resetPassInvalidText<br><br>
							{$lang['pwd_reset_32']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}"
						);
	} else {
		// Is a table-based user, so let them answer the security question
		$questionForm =	RCView::div(array('style'=>'max-width:700px;'),
							RCView::div(array('style'=>'color:#800000;margin:20px 0 10px;'), $lang['pwd_reset_28']) .
							RCView::form(array('method'=>'post','action'=>PAGE_FULL,'style'=>'margin:20px 0 10px;padding:10px;border:1px solid #ccc;background-color:#f5f5f5;'),
								RCView::div(array('style'=>'font-weight:bold;padding-bottom:10px;'),
									Authentication::getSecurityQuestions($securityQid)
								) .
								"<input type='text' name='answer' class='x-form-text x-form-field' style='width:200px;' autocomplete='new-password'>" . RCView::SP .
								RCView::hidden(array('name'=>'username','value'=>$_POST['username'])) .
								RCView::submit(array('class'=>'btn', 'onclick'=>'answer.value=trim(answer.value);if(answer.value.length<1){alert("'.js_escape2($lang['pwd_reset_29']).'");return false;}'))
							)
						);
	}

	## Submitted username. Verify user.
	if (!isset($_POST['answer']) && isset($questionForm))
	{
		print $questionForm;
	}
	## Submitted security answer. Verify answer.
	else
	{
		// Clean answer and hash it
		$answerHash = Authentication::hashSecurityAnswer($_POST['answer'], $_POST['username']);
		// Add to table
		$sql = "select i.user_email from redcap_auth a, redcap_user_information i where a.username = '".db_escape($_POST['username'])."'
				and a.password_answer = '".db_escape($answerHash)."' and i.username = a.username limit 1";
		$q = db_query($sql);
		if (db_num_rows($q))
		{
			## Success! Reset password and email new temp password
			// Flag of success (false by default)
			$successfullyReset = false;
			// Get email address
			$user_email = db_result($q, 0);
			// Reset the password
			$pass = Authentication::resetPassword($_POST['username'], "Reset own password");
			if ($pass !== false)
			{
				// Get reset password link
				$resetpasslink = Authentication::getPasswordResetLink($_POST['username']);
				// Send email
				$email = new Message();
				$emailSubject = 'REDCap '.$lang['control_center_102'];
				$emailContents = $lang['control_center_4485'].' "<b>'.$_POST['username'].'</b>"'.$lang['period'].' '.
								 $lang['control_center_4486'].'<br /><br /> '.
								 $lang['control_center_4655'].'<br /><br />
								 <a href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a><br /><br />
								'.$lang['control_center_98'].' '.$project_contact_name.' '.$lang['global_15'].' '.$project_contact_email.$lang['period'];
				$email->setTo($user_email);
				$email->setFrom($project_contact_email);
				$email->setFromName($GLOBALS['project_contact_name']);
				$email->setSubject($emailSubject);
				$email->setBody($emailContents,true);
				if ($email->send())
				{
					// Set flag to true
					$successfullyReset = true;
					// Give message of success
					print RCView::div(array('class'=>'darkgreen','style'=>'margin:20px 0 15px;padding:12px 15px 15px;'),
							RCView::img(array('src'=>'tick.png')) .
							"{$lang['pwd_reset_58']} \"<b>".RCView::escape($_POST['username'])."</b>\"{$lang['period']}
							<div style='padding-top:20px;font-size:11px;'>{$lang['pwd_reset_44']}</div>"
						  );
					print RCView::button(array('class'=>'jqbuttonmed','onclick'=>'window.location.href="'.APP_PATH_WEBROOT_FULL.'";'), $lang['pwd_reset_45']);
				}
			}
			// Check flag
			if (!$successfullyReset)
			{
				// Failed for unknown reasons
				print RCView::div(array('class'=>'red'),
					RCView::img(array('src'=>'exclamation.png')) .
					"{$lang['pwd_reset_43']} <a href='mailto:$homepage_contact_email'>{$lang['bottom_39']}</a>{$lang['period']}"
				);
				print $questionForm;
			}
		}
		else
		{
			// Failed!
			print RCView::div(array('class'=>'red'),
				RCView::img(array('src'=>'exclamation.png')) . $lang['pwd_reset_42']
			);
			print $questionForm;
			## Logging
			// For logging purposes, make sure we've got a username to attribute the logging to
			defined("USERID") or define("USERID", $_POST['username']);
			// Log the failed attempt
			Logging::logEvent("","redcap_auth","MANAGE",$_POST['username'],"username = '" . db_escape($_POST['username']) . "'","Failed to reset own password");
		}
	}
}

// Footer
print RCView::div(array('class'=>'space'), "&nbsp;");
$objHtmlPage->PrintFooter();