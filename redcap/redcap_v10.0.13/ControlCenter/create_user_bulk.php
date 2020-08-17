<?php

/**
 * ADD USERS VIA BULK UPLOAD
 */
// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Set header string for bulk upload file
$bulk_import_header = "Username, First name, Last name, Email address, Institution ID, Sponsor username, Expiration, Comments";
// User's landline phone and SMS phone (for Twilio two-step login)
if ($two_factor_auth_enabled && $two_factor_auth_twilio_enabled) {
	$bulk_import_header .= ", Phone, Mobile phone, 2-Step Login Expiration (minutes)";
}

// Download CSV import template file
if (isset($_GET['download_template']))
{
	// Begin output to file
	$file_name = "UserImportTemplate.csv";
	header('Pragma: anytextexeptno-cache', true);
	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=$file_name");
	// Output the data
	exit($bulk_import_header);
}




// Page header, instructions, and tabs
include 'header.php';
if (!SUPER_USER && !ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);
print 	RCView::h4(array('style' => 'margin-top: 0;'), $lang['control_center_4427']) .
		RCView::p(array('style'=>'margin-bottom:20px;'), $lang['control_center_411']);
// Display dashboard of table-based users that are on the old MD5 password hashing.
print User::renderDashboardPasswordHashProgress();
// Tabs
$tabs = array('ControlCenter/create_user.php'=>'<i class="fas fa-user-plus" style="padding-bottom: 3px;"></i> ' . $lang['control_center_409'],
    'ControlCenter/create_user_bulk.php'=>RCView::img(array('src'=>'xls2.png')) . $lang['control_center_410']);
RCView::renderTabs($tabs);

// Check if using email domain allowlist
$email_domain_allowlist_array = ($email_domain_allowlist == '') ? array() : explode("\n", strtolower(str_replace("\r", "", $email_domain_allowlist)));

// If adding new Table-based user, add new user to redcap_user_information and redcap_auth tables
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// Set value if can create/copy new projects
	$allow_create_db = (isset($_POST['allow_create_db']) && $_POST['allow_create_db'] == "on") ? 1 : 0;

	#Process uploaded CSV file
	$uploadedfile_name = $_FILES['fname']['tmp_name'];
	$updateitems = csv_to_bulk($uploadedfile_name);

	foreach ($updateitems as $key => $item)
	{
		if (!empty($item[0]))
		{
			$item[0] = trim($item[0]);
			$item[1] = trim($item[1]);
			$item[2] = trim($item[2]);
			$item[3] = trim($item[3]);
			$item[4] = trim($item[4]);
			$item[5] = trim($item[5]);
			$item[6] = trim($item[6]);
			$item[7] = trim($item[7]);
			if ($two_factor_auth_enabled && $two_factor_auth_twilio_enabled) {
				$item[8] = preg_replace("/[^0-9,]/", '', $item[8]);
				$item[9] = preg_replace("/[^0-9,]/", '', $item[9]);
				$item[10] = preg_replace("/[^0-9]/", '', $item[10]);
			} else {
				unset($item[8], $item[9]);
			}
			if (!isset($item[10]) || !is_numeric($item[10])) $item[10] = 2; // default

			// Validate the username
			if (!preg_match('/^([a-zA-Z0-9_\.\-\@])+$/', $item[0])) {
				print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_412']} {$lang['rights_354']}
							{$lang['control_center_427']} \"<b>" . RCView::escape($item[0]) . "</b>\"
						</div>";
				continue;
			}

			// Check if user in user_information table
            $thisUserInfo = User::getUserInfo($item[0]);
			if (is_array($thisUserInfo)) {
                print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_412']} {$lang['control_center_4765']}
							{$lang['control_center_427']} \"<b>" . RCView::escape($item[0]) . "</b>\"
						</div>";
                continue;
            }

			// Validate the email
			if ($item[3] == '') {
				print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_413']} \"<b>" . RCView::escape($item[0]) . "</b>\"
						</div>";
				continue;
			} elseif (!isEmail($item[3])) {
				print  "<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_414']} \"<b>" . RCView::escape($item[0]) . "</b>\"
							{$lang['control_center_415']} <b>".RCView::escape($item[3])."</b>
						</div>";
				continue;
			} elseif (trim($email_domain_allowlist) != '') {
				// If using the email domain allowlist, make sure the email's domain is in the allowlist
				list ($nothing, $email_domain) = explode('@', $item[3]);
				if (!in_array(strtolower($email_domain), $email_domain_allowlist_array)) {
					print  "<div class='red' style='margin:5px 0;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png'>
								{$lang['global_01']}{$lang['colon']} {$lang['control_center_4494']} \"<b>".RCView::escape($item[0])."</b>\" (<b>".RCView::escape($item[3])."</b>)
								{$lang['control_center_4493']} \"<b>".implode("</b>\", \"<b>", $email_domain_allowlist_array)."</b>\"{$lang['period']}
							</div>";
					continue;
				}
			}


			// Validate sponsor username
			if ($item[5] != '') {
				$sponsorUserInfo = User::getUserInfo($item[5]);
				if ($sponsorUserInfo === false) {
					print  "<div class='red' style='margin:5px 0;'>
								<img src='" . APP_PATH_IMAGES . "exclamation.png'>
								{$lang['global_01']}{$lang['colon']} {$lang['control_center_4404']} \"<b>" . RCView::escape($item[0]) . "</b>\"{$lang['period']}
								{$lang['control_center_4405']} \"<b>".RCView::escape($item[5])."</b>\"{$lang['period']}
							</div>";
					continue;
				}
			}

			// Validate user expiration
			if ($item[6] != '') {
				// Break up into date and time
				list ($thisdate, $thistime) = explode(' ', $item[6], 2);
				// Assume American format (mm/dd/yyyy) if contains forward slash, else assume yyyy-mm-dd format
				if (strpos($item[6], "/") !== false) {
					list ($month, $day, $year) = explode("/", $thisdate);
				} else {
					list ($year, $month, $day) = explode("-", $thisdate);
				}
				// Pad time, if needed
				if (strlen($thistime) == 0) $thistime = "00:00:00";
				if (strlen($thistime) == 4 || strlen($thistime) == 7) $thistime = "0".$thistime;
				if (strlen($thistime) == 5) $thistime .= ":00";
				// Make sure year is 4 digits
				if (strlen($year) == 2) {
					$year = ($year < (date('y')+10)) ? "20".$year : "19".$year;
				}
				$item[6] = sprintf("%04d-%02d-%02d", $year, $month, $day) . ' ' . $thistime;
			}

			// Get random value for temporary password
			$pass = generateRandomHash(8);
			$password_salt = Authentication::generatePasswordSalt();
			$hashed_password = Authentication::hashPassword($pass, $password_salt);
			// Add to table
			$sql = "INSERT INTO redcap_auth (username, password, password_salt, temp_pwd)
					VALUES ('" . db_escape($item[0]) . "', '" . db_escape($hashed_password) . "', '" . db_escape($password_salt) . "', 1)";
			$q = db_query($sql);
			// Send email to new user with username/password
			if ($q) {
				// Logging
				Logging::logEvent($sql, "redcap_auth", "MANAGE", $item[0], "user = '" . db_escape($item[0]) . "'", "Create username");
				// Get reset password link
				$resetpasslink = Authentication::getPasswordResetLink($item[0]);
				// Set up email
				$email = new Message ();
				$email->setTo($item[3]);
				$email->setFrom($user_email);
				$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
				$email->setSubject('REDCap '.$lang['control_center_101']);
				$emailContents = $lang['control_center_4488'].' "<b>'.$item[0].'</b>"'.$lang['period'].' '.
								 $lang['control_center_4486'].'<br /><br /> '.
								 $lang['control_center_4655'].'<br /><br />
								 <a href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a>';
				// If the user had an expiration time set, then let them know when their account will expire.
				if ($item[6] != '') {
					$daysFromNow = floor((strtotime($item[6]) - strtotime(NOW)) / (60*60*24));
					$emailContents .= " ".$lang['control_center_4402']."<b>".DateTimeRC::format_ts_from_ymd($item[6])
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

			## Add user's info to redcap_user_information table
			$sql = "insert into redcap_user_information (username, user_email, user_firstname, user_lastname, user_inst_id,
					allow_create_db, user_creation, user_sponsor, user_expiration, user_comments,
					datetime_format, number_format_decimal, number_format_thousands_sep, user_phone, user_phone_sms,
					two_factor_auth_code_expiration) values
					(" . checkNull($item[0]) . ", " . checkNull($item[3]) . ", " . checkNull(fixUTF8($item[1])) . ", " .
					checkNull(fixUTF8($item[2])) . ", " . checkNull(fixUTF8($item[4])) . ", $allow_create_db, '".NOW."',
					" . checkNull($item[5]) . ", " . checkNull($item[6]) . ", " . checkNull(fixUTF8($item[7])) . ",
					'".db_escape($default_datetime_format)."', '".db_escape($default_number_format_decimal)."',
					'".db_escape($default_number_format_thousands_sep)."', " . checkNull($item[8]) . ",
					" . checkNull($item[9]) . ", " . checkNull($item[10]) . ")";
			if (!db_query($sql)) {
				// Failure to add user
				print 	"<div class='red' style='margin:5px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['global_01']}{$lang['colon']} {$lang['control_center_424']}
							" . (db_errno() == 1062 ? "{$lang['control_center_426']} \"<b>" . RCView::escape($item[0]) . "</b>\"" : "") . "
						</div>";
			} else {
				// Display confirmation message that user was added successfully
				print 	"<div class='darkgreen'>
							<img src='" . APP_PATH_IMAGES . "tick.png'>
							{$lang['control_center_425']} \"<b>" . RCView::escape($item[0]) . "</b>\"
							(<a href='mailto:{$item[3]}'>".RCView::escape($item[3])."</a>){$lang['period']}
						</div>";
			}
		}
	}
	// Give error message if uploaded file was empty
	if (empty($updateitems))
	{
		print 	RCView::div(array('class'=>'red'),
					RCView::img(array('src'=>'exclamation.png')) .
					"{$lang['global_01']}{$lang['colon']} {$lang['control_center_423']}"
				);
	}
	// Display "start over" button
	print 	RCView::div(array('style'=>'margin:30px 0 10px;'),
				RCView::button(array('onclick'=>"window.location.href=app_path_webroot+page;"), "&lt;- {$lang['control_center_422']}")
			);
}
else
{
	/**
	 * ADD TABLE-BASED USERS
	 */
		print 	"<p>{$lang['control_center_4406']}</p>
				<p style='font-weight:bold;margin-bottom:0;'>{$lang['control_center_419']}</p>
				<p class='pre' style='".($two_factor_auth_enabled && $two_factor_auth_twilio_enabled ? "max-width:1050px;width:1050px;" : "width:700px;")."margin-top:2px;margin-bottom:20px;border:1px solid #ddd;padding:3px 3px 3px 10px;background-color:#f5f5f5;'>$bulk_import_header</p>
				<p>
					<a href='".PAGE_FULL."?download_template=1' style='text-decoration:underline;color:green;'><img src='".APP_PATH_IMAGES."xls.gif'> {$lang['control_center_421']}</a>
				</p>
				<form method='post' name='bulk_upload' action='{$_SERVER['PHP_SELF']}?view=user_controls' style='border:1px solid #ddd;padding:10px;margin:30px 0 10px;' enctype='multipart/form-data'>
					<b>{$lang['control_center_418']}</b><br><br>
					<input type='file' name='fname' size='50'><br><br>
					<input type='checkbox' name='allow_create_db' ".($allow_create_db_default ? "checked" : "").">
					".($superusers_only_create_project
						? RCView::b($lang['control_center_416']). RCView::div(array('style'=>'margin-left:22px;'), $lang['control_center_321'])
						: RCView::b($lang['control_center_417']) )."
					<div style='text-align:center;padding:10px;'>
						<input name='submit' type='submit' value='".js_escape($lang['design_127'])."' onclick=\"
							if (document.forms['bulk_upload'].elements['fname'].value.length < 1) {
								simpleDialog('".js_escape($lang['data_import_tool_114'])."');
								return false;
							}
						\"> &nbsp;
					</div>
				</form>";
}


include 'footer.php';


// Convert CSV file of users to array (ignore first row)
function csv_to_bulk($csvfilepath)
{
    $new_users = array();
    $file = fopen($csvfilepath, "r");
    $row = 0;
    while (($data = fgetcsv($file)) !== FALSE) {
        $item_count = count($data);
        if ($row > 0) {/* skip the first row, it contains only a header */
            for ($i = 0; $i < $item_count; $i++) {
                $new_users[$row - 1][$i] = $data[$i];
            }
        }
        $row++;
    }
    fclose($file);
	unlink($csvfilepath);
    return $new_users;
}
