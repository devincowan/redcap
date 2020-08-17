<?php


include 'header.php';
if (!SUPER_USER && !ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);

// setup database access
$db = new RedCapDB();

// are we looking up an existing user?
$ui_id = empty($_POST['ui_id']) ? null : $_POST['ui_id'];
$user_obj = new StdClass();
if (!empty($ui_id)) $user_obj = $db->getUserInfo($ui_id);
$orig_email = empty($user_obj->user_email) ? '' : $user_obj->user_email;

// save user data to the DB
if (isset($_POST['username']) && isset($_POST['user_firstname']) && isset($_POST['user_lastname']))
{
	// Render back button to go back to the User Iinfo page
	if ($ui_id) {
		print 	RCView::div(array('style'=>'padding:0 0 20px;'),
					renderPrevPageBtn("ControlCenter/view_users.php?username=".$_POST['username'],$lang['global_77'],false)
				);
	}
	// Ensure user doesn't already exist in user_information or auth table for inserts
	$userExists = $db->usernameExists($_POST['username']);
	if ($userExists && !$ui_id)
	{
		print  "<div class='red' style='margin-bottom: 20px;'>
					<img src='" . APP_PATH_IMAGES . "exclamation.png'>
					{$lang['global_01']}: {$lang['control_center_29']} {$lang['control_center_30']}
					\"<b>" . $_POST['username'] . "</b>\".
				</div>";
	}
	else
	{
		// Unescape posted values
		$_POST['username'] = trim(strip_tags(label_decode($_POST['username'])));
		// Get user info
		$user_info = User::getUserInfo($_POST['username']);
		// Unescape posted values
		$_POST['user_firstname'] = trim(strip_tags(label_decode($_POST['user_firstname'])));
		$_POST['user_lastname'] = trim(strip_tags(label_decode($_POST['user_lastname'])));
		$_POST['user_email'] = trim(strip_tags(label_decode($_POST['user_email'])));
		$_POST['user_email2'] = trim(strip_tags(label_decode($_POST['user_email2'])));
		$_POST['user_email3'] = trim(strip_tags(label_decode($_POST['user_email3'])));
		$_POST['user_inst_id'] = trim(strip_tags(label_decode($_POST['user_inst_id'])));
		$_POST['user_comments'] = trim(strip_tags(label_decode($_POST['user_comments'])));
		if ($_POST['user_comments'] == '') $_POST['user_comments'] = null;
		$_POST['user_expiration'] = trim(strip_tags(label_decode($_POST['user_expiration'])));
		$_POST['user_expiration'] = ($_POST['user_expiration'] == '') ? NULL : DateTimeRC::format_ts_to_ymd($_POST['user_expiration']).':00';
		$_POST['user_sponsor'] = ($_POST['user_sponsor'] == '') ? NULL : trim(strip_tags(label_decode($_POST['user_sponsor'])));
		$_POST['user_phone'] = ($_POST['user_phone'] == '') ? NULL : preg_replace("/[^0-9,]/", '', $_POST['user_phone']);
		$_POST['user_phone_sms'] = ($_POST['user_phone_sms'] == '') ? NULL : preg_replace("/[^0-9,]/", '', $_POST['user_phone_sms']);
		if (!isset($_POST['messaging_email_preference'])) $_POST['messaging_email_preference'] = '4_HOURS';
		if (!isset($_POST['messaging_email_urgent_all']) || $_POST['messaging_email_urgent_all'] != '1') $_POST['messaging_email_urgent_all'] = '0';
		$_POST['api_token_auto_request'] = (isset($_POST['api_token_auto_request']) && $_POST['api_token_auto_request'] == "on") ? 1 : 0;
		// If "domain allowlist for user emails" is enabled and email fails test, then revert it to old value
		if (User::emailInDomainAllowlist($_POST['user_email']) === false)  $_POST['user_email']  = $user_info['user_email'];
		if (User::emailInDomainAllowlist($_POST['user_email2']) === false) $_POST['user_email2'] = $user_info['user_email2'];
		if (User::emailInDomainAllowlist($_POST['user_email3']) === false) $_POST['user_email3'] = $user_info['user_email3'];
		$isAaf = 0;
		

		// Set value if can create/copy new projects
		$allow_create_db = (isset($_POST['allow_create_db']) && $_POST['allow_create_db'] == "on") ? 1 : 0;
		$fhir_data_mart_create_project = (isset($_POST['fhir_data_mart_create_project']) && $_POST['fhir_data_mart_create_project'] == "on") ? 1 : 0;		
		$display_on_email_users = (isset($_POST['display_on_email_users']) && $_POST['display_on_email_users'] == "on") ? 1 : 0;
		$pass = generateRandomHash(8);
		$sql = $db->saveUser($ui_id, $_POST['username'], $_POST['user_firstname'],
						$_POST['user_lastname'], $_POST['user_email'], $_POST['user_email2'], $_POST['user_email3'], $_POST['user_inst_id'],
						$_POST['user_expiration'], $_POST['user_sponsor'], $_POST['user_comments'], $allow_create_db, $pass,
						$default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep, $display_on_email_users,
						$_POST['user_phone'], $_POST['user_phone_sms'], $_POST['messaging_email_preference'], $_POST['messaging_email_urgent_all'],
						$_POST['api_token_auto_request'], $isAaf, $fhir_data_mart_create_project);
		// repopulate with newly saved data
		if (!empty($ui_id)) $user_obj = $db->getUserInfo($ui_id);
		if (count($sql) === 0) {
			// Failure to add user
			print  "<div class='red' style='margin-bottom: 20px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'>
						{$lang['global_01']}{$lang['colon']} {$lang['control_center_240']}
					</div>";
		}
		else {
			// Display confirmation message that user was saved successfully
			print  "<div class='darkgreen' style='margin-bottom: 20px;'>
						<img src='" . APP_PATH_IMAGES . "tick.png'> " .
						$lang['control_center_241'] . ($ui_id ? '' : ' ' . $lang['control_center_242'] . ' ' . $_POST['user_email']) .
					"</div>";
			// Email the user (new users get their login info, existing users get notified if their email changes)
			$email = new Message();
			$email->setTo($_POST['user_email']);
			$email->setFrom($user_email);
			$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
			if (empty($ui_id)) {
				// Log the new user
				Logging::logEvent(implode(";\n", $sql),"redcap_auth","MANAGE",$_POST['username'],"user = '{$_POST['username']}'","Create username");
				// Get reset password link
				$resetpasslink = Authentication::getPasswordResetLink($_POST['username']);
				// Set up the email to send to the user
				$email->setSubject('REDCap '.$lang['control_center_101']);
				$emailContents = $lang['control_center_4488'].' "<b>'.$_POST['username'].'</b>"'.$lang['period'].' '.
								 $lang['control_center_4486'].'<br /><br /> '.
								 $lang['control_center_4655'].'<br /><br />
								 <a href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a>';
				// If the user had an expiration time set, then let them know when their account will expire.
				if ($_POST['user_expiration'] != '') {
					$daysFromNow = floor((strtotime($_POST['user_expiration']) - strtotime(NOW)) / (60*60*24));
					$emailContents .= " ".$lang['control_center_4402']."<b>".DateTimeRC::format_ts_from_ymd($_POST['user_expiration'])
									. " -- $daysFromNow " . $lang['control_center_4438']
									. "</b>".$lang['control_center_4403'];
				}
				// If "auto-suspend due to inactivity" feature is enabled, the notify user generally that users may
				// get suspended if don't log in for a long time.
				if ($suspend_users_inactive_type != '') {
					$emailContents .= " ".$lang['control_center_4424'];
				}
				// The Display Name was removed here because it was causing these particular emails to be flagged as spam on many email servers and thus being blocked.
				$removeDisplayName = true;
				// Send the email
				$email->setBody($emailContents, true);
				if (!$email->send($removeDisplayName)) print $email->getSendError ();
			}
			else {
				// existing user
				Logging::logEvent(implode(";\n", $sql),"redcap_user_information","MANAGE",$_POST['username'],"username = '{$_POST['username']}'","Edit user");
				// If the user's email address was changed, then send an email to both accounts to notify them of the change.
				if ($_POST['user_email'] != $orig_email)
				{
					$email->setSubject('REDCap '.$lang['control_center_100'].' '.$_POST['user_email']);
					$emailContents = $lang['control_center_92'].' REDCap ('.$orig_email.') '.$lang['control_center_93'].' '
						.$_POST['user_email'].$lang['period'].' '.$lang['control_center_94'].'<br><br>
						<b>REDCap</b> - '.APP_PATH_WEBROOT_FULL;
					$email->setBody($emailContents, true);
					// first send to the new email address
					if (!$email->send()) {
						print $email->getSendError ();
					} elseif ($user_obj->email_verify_code != '') {
						// If primary email was changed BUT original email had not yet been verified, then remove verification
						$sql = "update redcap_user_information set email_verify_code = null where ui_id = " . $user_obj->ui_id;
						db_query($sql);
					}
					// now send to the old email address
					$email->setTo($orig_email);
					if (!$email->send()) print $email->getSendError ();
					// Display message that email was changed and that user was emailed about the change
					print 	RCView::div(array('class'=>'yellow','style'=>'margin-bottom:15px;'),
								RCView::img(array('src'=>'exclamation_orange.png')) .
								RCView::b($lang['global_02'].$lang['colon']) . ' ' .$lang['control_center_373']
							);
				}
			}
		}
	}
}




// Page header, instructions, and tabs
if ($ui_id) {
	// Edit user info
	print RCView::h4(array('style'=>'margin-top:0;'), $lang['control_center_239']) .
		  RCView::p(array(), $lang['control_center_244']);
} else {
	// Add new user
	print 	RCView::h4(array('style' => 'margin-top: 0;'), $lang['control_center_4427']) .
			RCView::p(array('style'=>'margin-bottom:20px;'), $lang['control_center_411']);
	// If not using auth_meth of none, table, or ldap_table, the don't display page
	if (!in_array($auth_meth_global, array('none', 'table', 'ldap_table','aaf_table', 'shibboleth_table'))) { //***<AAF Modification>***
		print 	RCView::p(array('class'=>'yellow', 'style'=>'margin-bottom:20px;'),
					RCView::img(array('src'=>'exclamation_orange.png')) .
					RCView::b($lang['global_03'].$lang['colon'])." " .$lang['control_center_4401']
				);
		include 'footer.php';
		exit;
	}
	// Display dashboard of table-based users that are on the old MD5 password hashing.
	print User::renderDashboardPasswordHashProgress();
	// Tabs
	$tabs = array('ControlCenter/create_user.php'=>'<i class="fas fa-user-plus" style="padding-bottom: 3px;"></i> ' . $lang['control_center_409'],
				  'ControlCenter/create_user_bulk.php'=>RCView::img(array('src'=>'xls2.png')) . $lang['control_center_410']);
	RCView::renderTabs($tabs);
	print 	RCView::p(array(), $lang['control_center_43']);
}
?>


<style type="text/css">
#edit-user-table td { padding:5px; }
</style>

<form method='post' action='<?php echo $_SERVER['PHP_SELF'] ?>'>
	<input type="hidden" name="ui_id" value="<?php echo (empty($user_obj->ui_id) ? '' : htmlspecialchars($user_obj->ui_id, ENT_QUOTES)); ?>">
	<table id='edit-user-table' border='0'>


	<tr>
		<td colspan="2" style="color:#888;border-top:1px solid #ddd;padding:5px 0;"><?php echo $lang['user_71'] ?> </td>
	</tr>
	<tr>
		<td><?php echo $lang['global_11'].$lang['colon'] ?> </td>
		<td>
			<input type='text' class='x-form-text x-form-field' id='username' name='username' maxlength='255'
				onblur="if (this.value.length > 0) {if(!chk_username(this)) return alertbad(this,'<?php echo $lang['rights_354'] ?>'); }"
				value="<?php echo (empty($user_obj->username) ? '' : htmlspecialchars($user_obj->username, ENT_QUOTES)); ?>"
				<?php echo (empty($user_obj->ui_id) ? '' : 'readonly="readonly"') ?>>
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['pub_023'].$lang['colon'] ?> </td>
		<td>
			<input type='text' class='x-form-text x-form-field' id='user_firstname' name='user_firstname' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				value="<?php echo (empty($user_obj->user_firstname) ? '' : htmlspecialchars($user_obj->user_firstname, ENT_QUOTES)); ?>">
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['pub_024'].$lang['colon'] ?> </td>
		<td>
			<input type='text' class='x-form-text x-form-field' id='user_lastname' name='user_lastname' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				value="<?php echo (empty($user_obj->user_lastname) ? '' : htmlspecialchars($user_obj->user_lastname, ENT_QUOTES)); ?>">
		</td>
	</tr>
	<tr>
		<td style="padding-bottom:10px;"><?php echo $lang['user_45'].$lang['colon'] ?> </td>
		<td style="padding-bottom:10px;">
			<input type='text' class='x-form-text x-form-field' id='user_email' name='user_email' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				onBlur="if (redcap_validate(this,'','','hard','email')) emailInDomainAllowlist(this);"
				value="<?php echo (empty($user_obj->user_email) ? '' : htmlspecialchars($user_obj->user_email, ENT_QUOTES)); ?>">
		</td>
	</tr>

	<!-- REDCap Messenger options -->
	<?php if ($user_messaging_enabled) { ?>
	<tr>
		<td colspan="2" style="color:#888;border-top:1px solid #ddd;padding:5px 0;"><?php echo $lang['messaging_10'].$lang['colon'] ?> </td>
	</tr>
	<tr>
		<td valign="top" style="padding-top:15px;">
			<?php echo $lang['messaging_12'] ?>
		</td>
		<td style="padding-top:15px;">
			<?php
			print RCView::select(array('name'=>'messaging_email_preference', 'class'=>'x-form-text x-form-field', 'style'=>'font-family:tahoma;'),
    					User::getMessagingEmailPreferencesOptions(), isset($user_obj->messaging_email_preference) ? $user_obj->messaging_email_preference : '4_HOURS');
			?>
		</td>
	</tr>
	<tr>
		<td style="padding-bottom:15px;">
			<?php echo $lang['messaging_13'] ?>
		</td>
		<td style="padding-bottom:15px;">
			<?php
			$messaging_email_urgent_all_checked = (!isset($user_obj->messaging_email_urgent_all) || $user_obj->messaging_email_urgent_all == '1') ? 'checked' : '';
			print RCView::checkbox(array('name'=>'messaging_email_urgent_all', 'value'=>'1', $messaging_email_urgent_all_checked=>$messaging_email_urgent_all_checked));
    		?>
		</td>
	</tr>
	<?php } ?>

	<tr>
		<td colspan="2" style="color:#888;border-top:1px solid #ddd;padding:5px 0;"><?php echo $lang['user_70'] ?> </td>
	</tr>

	<tr>
		<td><?php echo $lang['user_46'].$lang['colon'] ?> </td>
		<td>
			<input type='text' class='x-form-text x-form-field' id='user_email2' name='user_email2' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				onBlur="if (redcap_validate(this,'','','hard','email')) emailInDomainAllowlist(this);"
				value="<?php echo (empty($user_obj->user_email2) ? '' : htmlspecialchars($user_obj->user_email2, ENT_QUOTES)); ?>">
		</td>
	</tr>
	<tr>
		<td><?php echo $lang['user_55'].$lang['colon'] ?> </td>
		<td>
			<input type='text' class='x-form-text x-form-field' id='user_email3' name='user_email3' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				onBlur="if (redcap_validate(this,'','','hard','email')) emailInDomainAllowlist(this);"
				value="<?php echo (empty($user_obj->user_email3) ? '' : htmlspecialchars($user_obj->user_email3, ENT_QUOTES)); ?>">
		</td>
	</tr>

	<!-- Phone numbers -->
	<?php if ($two_factor_auth_enabled && $two_factor_auth_twilio_enabled) { ?>
	<tr>
		<td valign="top" style="padding-top:15px;">
			<?php echo $lang['system_config_478'].$lang['colon'] ?>
			<div style="font-size:11px;color:#777;"><?php echo $lang['system_config_479'] ?> </div>
		</td>
		<td style="padding-top:15px;">
			<input type='text' class='x-form-text x-form-field' id='user_phone' name='user_phone' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				onBlur="this.value = this.value.replace(/[^0-9,]/g,'');"
				value="<?php echo (empty($user_obj->user_phone) ? '' : htmlspecialchars($user_obj->user_phone, ENT_QUOTES)); ?>">
			<div style="max-width:250px;font-size:11px;line-height:11px;color:#000066;margin:3px 0 0;">
				<?php echo $lang['system_config_486'] ?>
			</div>
		</td>
	</tr>
	<tr>
		<td style="padding-bottom:15px;">
			<?php echo $lang['system_config_452'].$lang['colon'] ?>
			<div style="font-size:11px;color:#777;"><?php echo $lang['system_config_480'] ?> </div>
		</td>
		<td style="padding-bottom:15px;">
			<input type='text' class='x-form-text x-form-field' id='user_phone_sms' name='user_phone_sms' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				onBlur="this.value = this.value.replace(/[^0-9,]/g,'');"
				value="<?php echo (empty($user_obj->user_phone_sms) ? '' : htmlspecialchars($user_obj->user_phone_sms, ENT_QUOTES)); ?>">
		</td>
	</tr>
	<?php } ?>

	<tr>
		<td><?php echo $lang['control_center_236'].$lang['colon'] ?> </td>
		<td>
			<input type='text' class='x-form-text x-form-field' id='user_inst_id' name='user_inst_id' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				value="<?php echo (empty($user_obj->user_inst_id) ? '' : htmlspecialchars($user_obj->user_inst_id, ENT_QUOTES)); ?>">
			<div class="cc_info">(<?php echo $lang['control_center_237'] ?>)</div>
		</td>
	</tr>

	<!-- sponsor -->
	<tr>
		<td valign="top" style="padding-top:10px;">
			<?php echo $lang['user_72'].RCView::br().$lang['user_75'].$lang['colon'] ?> 
			<div class="cc_info" style="max-width:380px;"><?php echo $lang['user_74'] ?></div>
		</td>
		<td valign="top" style="padding-top:8px;">
			<input type='text' class='x-form-text x-form-field' id='user_sponsor' name='user_sponsor' maxlength='255'
				onkeydown='if(event.keyCode == 13) return false;'
				value="<?php echo (empty($user_obj->user_sponsor) ? '' : htmlspecialchars($user_obj->user_sponsor, ENT_QUOTES)); ?>">
			<div class="cc_info"><?php echo $lang['user_73'] ?></div>
		</td>
	</tr>

	<!-- expiration -->
	<tr>
		<td valign="top" style="padding-top:10px;"><?php echo $lang['rights_54'].$lang['colon'] ?> </td>
		<td style="padding-top:5px;">
			<input class="x-form-text x-form-field" type="text" id="user_expiration" name="user_expiration" onfocus="if (!$('.ui-datepicker:visible').length) $(this).next('img').click();"
				value="<?php echo (empty($user_obj->user_expiration) ? '' : DateTimeRC::format_user_datetime(substr($user_obj->user_expiration, 0, 16), 'Y-M-D_24', null, true)); ?>"
				style="width: 103px;" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);"
					onkeydown="if(event.keyCode == 13) return false;"/>
				<span class="df"><?php echo DateTimeRC::get_user_format_label() ?> H:M</span>
			<div class="cc_info" style="max-width:450px;"><?php echo $lang['control_center_4381'] . " " . User::USER_EXPIRE_FIRST_WARNING_DAYS .
				" " . $lang['scheduling_25'] . " " . $lang['control_center_4400'] ?></div>
		</td>
	</tr>

	<!-- miscellaneous comments about user -->
	<tr>
		<td valign="top" style="padding-top:10px;"><?php echo $lang['user_76'] ?> </td>
		<td style="padding-top:5px;">
			<textarea style="height:60px;" class="x-form-field notesbox" id="user_comments" name="user_comments"><?php echo (empty($user_obj->user_comments) ? '' : htmlspecialchars($user_obj->user_comments, ENT_QUOTES)); ?></textarea>
			<div id='user_comments-expand' class='expandLinkParent'>
				<a href='javascript:;' class='expandLink' style='margin-right:5px;' onclick="growTextarea('user_comments');return false;"><?php print $lang['form_renderer_19'] ?></a>
			</div>
		</td>
	</tr>

	<?php if ($api_token_request_type == 'auto_approve_selected') { ?>
	<tr>
		<td colspan='2' style="padding-top:5px;">
			<?php
				$api_token_auto_request_checked = '';
				if (isset($user_obj->api_token_auto_request) && $user_obj->api_token_auto_request == '1') {
					$api_token_auto_request_checked = "checked";
				}
			?>
			<input type='checkbox' name='api_token_auto_request' <?php echo $api_token_auto_request_checked ?>>
			<?php echo $lang['api_140'] ?>
		</td>
	</tr>
	<?php } ?>

	<tr>
		<td colspan='2' style="padding-top:5px;">
			<?php
				$display_on_email_users_checked = '';
				if (!isset($user_obj->display_on_email_users) || (isset($user_obj->display_on_email_users) && $user_obj->display_on_email_users)) {
					$display_on_email_users_checked = "checked";
				}
			?>
			<input type='checkbox' name='display_on_email_users' <?php echo $display_on_email_users_checked ?>>
			<?php echo $lang['control_center_4492'] ?>
		</td>
	</tr>
	
	<?php
	// DDP Data Mart
	$DDP = new DynamicDataPull(0, 'FHIR');
	if ($DDP->isEnabledInSystemFhir() && $fhir_data_mart_create_project) { 
	?>
	<tr>
		<td colspan='2' style="padding-top:5px;">
			<?php
			$fhir_data_mart_create_project_checked = ($user_obj->fhir_data_mart_create_project=='1') ? 'checked' : '';
			?>
			<input type='checkbox' name='fhir_data_mart_create_project' <?php echo $fhir_data_mart_create_project_checked ?>>
			<?php echo RCView::b($lang['control_center_4705']) ?>
		</td>
	</tr>
	<?php } ?>

	<tr>
		<td colspan='2' style="padding-top:5px;">
			<?php
				$allow_checked = '';
				if (isset($user_obj->allow_create_db) && $user_obj->allow_create_db ||
					!isset($user_obj->allow_create_db) && $allow_create_db_default) {
					$allow_checked = "checked";
				}
			?>
			<input type='checkbox' name='allow_create_db' <?php echo $allow_checked ?>>
			<?php
			echo ($superusers_only_create_project
				? RCView::b($lang['control_center_320']). RCView::div(array('style'=>'margin-left:22px;'), $lang['control_center_321'])
				: RCView::b($lang['control_center_46']) )
			?>
		</td>
	</tr>

	<tr>
		<td>&nbsp;</td>
		<td style="padding-top:10px;">
			<input name='submit' type='submit' value='<?php echo js_escape($lang['designate_forms_13']) ?>' onclick="
				if ($('#user_email').val().length < 1 || $('#user_firstname').val().length < 1 || $('#user_lastname').val().length < 1) {
					simpleDialog('<?php echo js_escape($lang['control_center_428']) ?>');
					return false;
				}
				var expireval = trim($('input[name=user_expiration]').val());
				if (expireval != '') {
					var today_numerical = today.replace(/-/g, '')*1;
					if (user_date_format_validation == 'dmy') {
						var thisdate_numerical = (expireval.substring(6, 10)+expireval.substring(3, 5)+expireval.substring(0, 2))*1;
					} else if (user_date_format_validation == 'mdy') {
						var thisdate_numerical = (expireval.substring(6, 10)+expireval.substring(0, 2)+expireval.substring(3, 5))*1;
					} else {
						var thisdate_numerical = (expireval.substring(0, 4)+expireval.substring(5, 7)+expireval.substring(8, 10))*1;
					}
					if (thisdate_numerical <= today_numerical) {
						simpleDialog('<?php echo js_escape($lang['control_center_4430']) ?>',null,'expire_date_check_dialog');
						return false;
					}
				}
			">
			<a style="text-decoration:underline;margin-left:10px;" href="<?php print APP_PATH_WEBROOT . "ControlCenter/view_users.php" . (!isset($user_obj->username) || $user_obj->username == '' ? "" : "?username=" . htmlspecialchars($user_obj->username, ENT_QUOTES)) ?>"><?php print $lang['global_53'] ?></a>
		</td>
	</tr>
	</table>
</form>

<script type='text/javascript'>
var mess1 = '<?php echo js_escape(isset($lang['control_center_47']) ? $lang['control_center_47'] : ''); ?>';
// Auto-suggest for adding new users
function enableUserSearch() {
	$('#user_sponsor').autocomplete({
		source: app_path_webroot+"UserRights/search_user.php?searchEmail=1&searchSuspended=1",
		minLength: 2,
		delay: 150,
		select: function( event, ui ) {
			$(this).val(ui.item.value);
			//$('#user_search_btn').click();
			return false;
		}
	})
	.data('ui-autocomplete')._renderItem = function( ul, item ) {
		return $("<li></li>")
			.data("item", item)
			.append("<a>"+item.label+"</a>")
			.appendTo(ul);
	};
}
$(function(){
	// Enable username search for sponsor
	enableUserSearch();
	// Datepicker widget for user expiration time
	$('#user_expiration').datetimepicker({
		buttonText: 'Click to select a date', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
		showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
	});
});
</script>

<?php
include 'footer.php';
