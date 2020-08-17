<?php



/**
 * USER Class
 * Contains methods used with regard to users
 */
class User
{
	// All number formats and their defaults for decimal and thousands separator
	public static $default_number_format_decimal_system = '.';
	public static $number_format_decimal_formats = array('.', ',');
	public static $default_number_format_thousands_sep_system = ',';
	public static $number_format_thousands_sep_formats = array(',', '.', "'", 'SPACE', '');
	// Array of all messaging email user preference options
	public static $messaging_email_preference_options = array('2_HOURS','4_HOURS','6_HOURS','8_HOURS','12_HOURS','DAILY','NONE');
	// Time when 1st warning email is sent prior to user expiration (in days)
	const USER_EXPIRE_FIRST_WARNING_DAYS = 14;
	// Time when 2nd warning email is sent prior to user expiration (in days)
	const USER_EXPIRE_SECOND_WARNING_DAYS = 2;

	// Return array of ALL project usernames with key as username and value also as username (unless $appendFirstLastName=true)
	public static function getUsernames($excludeUsernames=array(), $appendFirstLastName=false, $orderByFirstName=true)
	{
		global $lang;
		// Place all usernames in array to return
		$users = array();
		$where = $excludeUsernames ? ' AND i.username NOT IN (' . prep_implode($excludeUsernames) . ')' : '';
		$orderby = $orderByFirstName ? 'trim(i.user_firstname)' : 'trim(i.username)';

		// Get email addresses and names from table
		$sql = "
			SELECT
				lower(trim(i.username)) as username,
				trim(concat(i.user_firstname, ' ', i.user_lastname)) AS full_name
			FROM redcap_user_information i
			WHERE i.username != '' $where
			ORDER BY $orderby
		";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add user to array
			$users[$row['username']] = $row['username'] . (($appendFirstLastName && $row['full_name'] != '') ? " ({$row['full_name']})" : '');
		}
		// Return the array
		return $users;
	}

	// Return array of ALL projects that the user has access to.
	// If $user is a string, then search one user. If an array of users, search for all those users.
	public static function getProjectsByUser($user=array(), $excludeArchived=true)
	{
		if (!is_array($user)) $user = array($user);
		$sub = $excludeArchived ? "and p.status != '3'" : "";
		$sql = "select distinct p.project_id, trim(p.app_title) as app_title from redcap_projects p, redcap_user_rights u
				where p.project_id = u.project_id and u.username in (" . prep_implode($user) . ") $sub
				order by trim(p.app_title), p.project_id";
		$q = db_query($sql);
		$projectList = array();
		while ($row = db_fetch_assoc($q))
		{
			$row['app_title'] = strip_tags(label_decode($row['app_title']));
			if (strlen($row['app_title']) > 80) {
				$row['app_title'] = trim(substr($row['app_title'], 0, 70)) . " ... " . trim(substr($row['app_title'], -15));
			}
			if ($row['app_title'] == "") {
				$row['app_title'] = $lang['create_project_82'];
			}
			$projectList[$row['project_id']] = $row['app_title'];
		}
		return $projectList;
	}

	// Return array of ALL projects that $users have access to, where the project_id is the key
	// and the sub-array contains the UI_ID of each user that has access to it.
	// If $user is a string, then search one user. If an array of users, search for all those users.
	public static function getProjectsUiIDsByUsers($users=array(), $excludeArchived=true)
	{
		if (!is_array($users)) $users = array($users);
		$sub = $excludeArchived ? "and p.status != '3'" : "";
		$sql = "select p.project_id, i.ui_id from redcap_projects p, redcap_user_rights u, redcap_user_information i
				where p.project_id = u.project_id and u.username in (" . prep_implode($users) . ") $sub
				and i.username = u.username
				order by p.project_id";
		$q = db_query($sql);
		$projectList = array();
		while ($row = db_fetch_assoc($q))
		{
			$projectList[$row['project_id']][] = $row['ui_id'];
		}
		return $projectList;
	}

	// Return array of ALL project usernames with key as username and value also as username (unless $appendFirstLastName=true)
	public static function getProjectUsernames($excludeUsernames=array(), $appendFirstLastName=false, $project_id=null)
	{
		global $lang;
		// Place all usernames in array to return
		$users = array();
		// Get project_id
		$project_id = (defined("PROJECT_ID")) ? PROJECT_ID : $project_id;
		// Get email addresses and names from table
		$sql = "select u.username, trim(concat(i.user_firstname, ' ', i.user_lastname)) as full_name
				from redcap_user_rights u left join redcap_user_information i
				on i.username = u.username where u.project_id = $project_id";
		if (!empty($excludeUsernames)) {
			$sql .= " and u.username not in (".prep_implode($excludeUsernames).")";
		}
		$sql .= " order by u.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$row['username'] = trim(strtolower($row['username']));
			// Add user to array
			$users[$row['username']] = $row['username'] . (($appendFirstLastName && $row['full_name'] != '') ? " ({$row['full_name']})" : '');
		}
		// Return the array
		return $users;
	}

	// Return HTML for a select drop-down of ALL project users with ui_id as key
	public static function dropDownListAllUsernames($dropdownId, $selectedValue='', $excludeUsernames=array(), $onChangeJS='', $appendFirstLastName=true, $disabled=false)
	{
		global $lang;
		// Set disabled attribute
		$disabled = ($disabled) ? "disabled" : "";
		// Create select list of usernames
		$userOptions = array(''=>$lang['rights_133']);
		// Get email addresses and names from table
		$sql = "select i.ui_id, i.username, trim(concat(i.user_firstname, ' ', i.user_lastname)) as full_name
				from redcap_user_rights u, redcap_user_information i
				where u.project_id = ".PROJECT_ID." and i.username = u.username order by i.username";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Exclude users
			if (in_array($row['username'], $excludeUsernames)) continue;
			// Add user to array
			$userOptions[$row['ui_id']] = $row['username'];
			// Add first/last name to array (if flag is set)
			if ($appendFirstLastName) {
				$userOptions[$row['ui_id']] .= " ({$row['full_name']})";
			}
		}
		// Set select box html
		$userSelect = RCView::select(array('id'=>$dropdownId,'class'=>'x-form-text x-form-field', $disabled=>$disabled,
						'style'=>'', 'onchange'=>$onChangeJS), $userOptions, $selectedValue, 100);
		// Return the HTML
		return $userSelect;
	}

	// Return HTML for a select drop-down of the current user's email addresses associated with
	// their REDCap account. If don't have a secondary/tertiary email listed, then let last option (if desired)
	// be a clickable trigger to open dialog for setting up a secondary/tertiary email.
	public static function emailDropDownList($appendAddEmailOption=true,$dropdownId='emailFrom',$dropdownName='emailFrom')
	{
		global $lang, $user_email, $user_email2, $user_email3;
		// Create select list for From email address (do not display any that are still pending approval)
		$fromEmailOptions = array('1'=>$user_email);
		if ($user_email2 != '') {
			$fromEmailOptions['2'] = $user_email2;
		}
		if ($user_email3 != '') {
			$fromEmailOptions['3'] = $user_email3;
		}
		// Add option to add more emails (if designated)
		if ($appendAddEmailOption && ($user_email2 == '' || $user_email3 == '')) {
			$fromEmailOptions['999'] = $lang['survey_763'];
		}
		// Set select box html
		$fromEmailSelect = RCView::select(array('id'=>$dropdownId,'name'=>$dropdownName,'class'=>'x-form-text x-form-field',
			'style'=>'',
			'onchange'=>"if(this.value=='999') { setUpAdditionalEmails(); this.value='1'; }"), $fromEmailOptions, '1', 100);
		// Return the HTML
		return $fromEmailSelect;
	}

	// Return array of ALL project users' email addresses associated with their REDCap account
	public static function getEmailAllProjectUsers($project_id)
	{
		$emails = array();
		// Get email addresses and names from table
		$sql = "select distinct x.email from (
				(select i.user_email as email from redcap_user_rights u, redcap_user_information i
					where u.project_id = ".PROJECT_ID." and i.username = u.username and i.email_verify_code is null and i.user_email is not null)
				union
				(select i.user_email2 as email from redcap_user_rights u, redcap_user_information i
					where u.project_id = ".PROJECT_ID." and i.username = u.username and i.email2_verify_code is null and i.user_email2 is not null)
				union
				(select i.user_email3 as email from redcap_user_rights u, redcap_user_information i
					where u.project_id = ".PROJECT_ID." and i.username = u.username and i.email3_verify_code is null and i.user_email3 is not null)
				) x order by x.email";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			if ($row['email'] == '') continue;
			// Clean, just in case
			$row['email'] = strtolower(label_decode($row['email']));
			// Add to array
			$emails[] = $row['email'];
		}
		$emails = array_values(array_unique($emails));
		return $emails;
	}

	// Return array of ALL project users' phone numbers associated with their REDCap account
	public static function getPhoneAllProjectUsers($project_id, $formatNumbers=false, $returnUserFirstLastNameAsValue=false)
	{
		$phones = array();
		// Get email addresses and names from table
		$sql = "select distinct x.phone, x.name from (
				(select i.user_phone as phone, trim(concat(i.user_firstname, ' ', i.user_lastname)) as name
				    from redcap_user_rights u, redcap_user_information i
					where u.project_id = ".$project_id." and i.username = u.username and i.user_phone is not null)
				union
				(select i.user_phone_sms as phone, trim(concat(i.user_firstname, ' ', i.user_lastname)) as name
				    from redcap_user_rights u, redcap_user_information i
					where u.project_id = ".$project_id." and i.username = u.username and i.user_phone_sms is not null)
				) x order by x.phone";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			if ($row['phone'] == '') continue;
			// Clean, just in case
			$row['phone'] = preg_replace("/[^0-9]/", "", $row['phone']);
			// Format the number?
            $key = $row['phone'];
            $value = $formatNumbers ? formatPhone($key) : $key;
            // Use the user's first/last name as the array value?
            if ($returnUserFirstLastNameAsValue) {
                $value = $row['name'];
            }
			// Add to array
			$phones[$key] = $value;
		}
		return $phones;
	}

	// Return HTML for a select drop-down of ALL project users' email addresses associated with
	// their REDCap account. If don't have a secondary/tertiary email listed, then let last option (if desired)
	// be a clickable trigger to open dialog for setting up a secondary/tertiary email.
	public static function emailDropDownListAllUsers($selectedValue=null,$appendAddEmailOption=true,$dropdownId='emailFrom',$dropdownName='emailFrom')
	{
		global $lang, $user_email, $user_email2, $user_email3;
		// Create select list for From email address of ALL project users (do not display any that are still pending approval)
		$fromEmailOptions = array();
		$selectedValue = strtolower($selectedValue);
		// Get email addresses and names from table
		foreach (self::getEmailAllProjectUsers(PROJECT_ID) as $thisEmail) {
			// Add to array
			$fromEmailOptions[$thisEmail] = $thisEmail;
		}
		// If selected email doesn't belong to anyone on the project anymore, then keep it as an extra option
		if ($selectedValue != '' && !in_array($selectedValue, $fromEmailOptions)) {
			$fromEmailOptions[$selectedValue] = $selectedValue;
			$fromEmailOptions[$selectedValue] .= " {$lang['survey_1237']}";
		}
		// Add option to add more emails (if designated)
		if ($appendAddEmailOption && ($user_email2 == '' || $user_email3 == '')) {
			$fromEmailOptions['999'] = $lang['survey_763'];
		}
		// Set the default selected value (if none, then use current user's primary email)
		$selectedValue = ($selectedValue == '') ? $user_email : $selectedValue;
		// Set select box html
		$fromEmailSelect = RCView::select(array('id'=>$dropdownId,'name'=>$dropdownName,'class'=>'x-form-text x-form-field',
			'style'=>'',
			'onchange'=>"if(this.value=='999') { setUpAdditionalEmails(); this.value='".js_escape($selectedValue)."'; }"), $fromEmailOptions, $selectedValue, 100);
		// Return the HTML
		return $fromEmailSelect;
	}

	// Generate unique user verification code for their email account
	private static function generateUserVerificationCode()
	{
		do {
			// Generate a new random hash
			$code = generateRandomHash(20);
			// Ensure that the hash doesn't already exist in table
			$sql = "select 1 from redcap_user_information where (email_verify_code = '$code'
					or email2_verify_code = '$code' or email3_verify_code = '$code') limit 1";
			$codeExists = (db_num_rows(db_query($sql)) > 0);
		} while ($codeExists);
		// Code is unique, so return it
		return $code;
	}


	// Set the user's user_access_dashboard_view timestamp to NOW when they view the project user access dashboard/summary page
	public static function setUserAccessDashboardViewTimestamp($user)
	{
		// Set timestamp in table for user
		$sql = "update redcap_user_information set user_access_dashboard_view = '" . NOW . "'
				where username = '".db_escape($user)."'";
		return (db_query($sql));
	}
	

	// Get list of all suspended users in the current project
	public static function getSuspendedUsers()
	{
		$suspendedUsers = array();
		// Query to get suspended users in project
		$sql = "select i.username from redcap_user_information i, redcap_user_rights u
				where u.username = i.username and u.project_id = ".PROJECT_ID." and i.user_suspended_time is not null
				and i.user_suspended_time <= '".NOW."'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$suspendedUsers[] = strtolower($row['username']);
		}
		return $suspendedUsers;
	}


	// Obtain a count of how many users of which the specified user is a sponsor
	public static function getSponsorUserCount($user)
	{
		// Set timestamp in table for user
		$sql = "select count(*) from redcap_user_information where user_sponsor = '".db_escape($user)."'";
		$q = db_query($sql);
		return db_result($q, 0);
	}


	// Obtain array of attributes of the users of which the specified user is a sponsor OR return list of specific UI_IDs
	public static function getSponsorUserAttributes($sponsor='', $displayOnlyTheseUiIds=array())
	{
		$users = array();
		$sql2 = ($sponsor == '') ? '' : "and i.user_sponsor = '".db_escape($sponsor)."'";
		$sql3 = empty($displayOnlyTheseUiIds) ? '' : "and i.ui_id in (".prep_implode($displayOnlyTheseUiIds).")";
		$sql = "select i.ui_id, i.username, i.user_sponsor, i.user_firstactivity, i.user_lastactivity, i.user_lastlogin, 
				i.user_suspended_time, i.user_expiration, i.user_firstname, i.user_lastname, i.user_email, 
				if(a.username is null, 0, 1) as table_user, i.super_user, i.user_inst_id, i.user_comments, i.allow_create_db as create_project
				from redcap_user_information i
				left join redcap_auth a on a.username = i.username
				where i.username != '' $sql2 $sql3 and i.username is not null 
				order by trim(i.username)";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$row['username'] = trim(strtolower($row['username']));
			$ui_id = $row['ui_id'];
			unset($row['ui_id']);
			$users[$ui_id] = $row;
		}
		return $users;
	}


	// Set user's email address (primary=1, secondary=2, or tertiary=3)
	// Provide user's ui_id and which email account this is for.
	public static function setUserEmail($ui_id, $email="", $email_account=1)
	{
		// Validate email
		if (!isEmail($email)) return false;
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "user_email" . ($email_account > 1 ? $email_account : "");
		// Add code to table (if code already exists for this primary/secondary/tertiary email, then update the code with new value)
		$sql = "update redcap_user_information set $user_email_field = '" . db_escape($email) . "'
				where ui_id = '".db_escape($ui_id)."'";
		return (db_query($sql));
	}

	// Remove a user's secondary=2 or tertiary=3 email address from their account
	public static function removeUserEmail($ui_id, $email_account=null)
	{
		if (!is_numeric($email_account)) return false;
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "user_email{$email_account}";
		$user_verify_code_field = "email{$email_account}_verify_code";
		// Remove email from table
		$sql = "update redcap_user_information set $user_email_field = null, $user_verify_code_field = null
				where ui_id = '".db_escape($ui_id)."'";
		$q = db_query($sql);
		if (!$q) return false;
		// If secondary email was removed, then if tertiary email exist, make it the secondary email (move value in table)
		if ($email_account == '2')
		{
			// Get user info
			$user_info = User::getUserInfo(USERID);
			// If it has a tertiary email, move to secondary position
			if ($user_info['user_email3'] != '') {
				$sql = "update redcap_user_information set user_email2 = user_email3, email2_verify_code = email3_verify_code,
						user_email3 = null, email3_verify_code = null where ui_id = '".db_escape($ui_id)."'";
				$q = db_query($sql);
			}
		}
		return true;
	}

	// Get unique user verification code for their email account
	// Provide user's ui_id and which email account this is for.
	public static function setUserVerificationCode($ui_id, $email_account=1)
	{
		// Generate a new random code
		$code = self::generateUserVerificationCode();
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "email" . ($email_account > 1 ? $email_account : "") . "_verify_code";
		// Add code to table (if code already exists for this primary/secondary/tertiary email, then update the code with new value)
		$sql = "update redcap_user_information set $user_email_field = '$code'
				where ui_id = '".db_escape($ui_id)."'";
		return (db_query($sql) ? $code : false);
	}

	// Email the user email verification code to the user
	public static function sendUserVerificationCode($new_email, $code, $email_account=1, $this_userid=null)
	{
		global $project_contact_email, $lang, $redcap_version, $user_email;
		// If $this_userid not provided, use USERID
		if ($this_userid == null) $this_userid = (defined('USERID') ? USERID : '');
		// The Display Name was removed here because it was causing these particular emails to be flagged as spam on many email servers and thus being blocked.
		$removeDisplayName = true;
		// Email the user (new users get their login info, existing users get notified if their email changes)
		$email = new Message();
		// Send the email From the user's primary address
		$email->setTo($new_email);
		$email->setFrom($project_contact_email);
		$email->setFromName($GLOBALS['project_contact_name']);
		$email->setSubject('[REDCap] '.$lang['user_19']);
		if ($email_account == 1) {
			// Primary email account
			$emailContents = "{$lang['user_66']} \"<b>$this_userid</b>\"{$lang['user_67']}";
			// Set verification url
			$url = APP_PATH_WEBROOT_FULL . "index.php?user_verify=$code";
		} else {
			// Secondary or tertiary email account
			$emailContents = "{$lang['user_68']} \"<b>$this_userid</b>\"{$lang['user_69']}";
			// Set verification url
			$url = APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/Profile/additional_email_verify.php?user_verify=$code";
		}
		$emailContents .= '<br /><br /><a href="'.$url.'">'.$lang['user_21'].'</a><br /><br />'
						. $lang['survey_135'].'<br />'.$url.'<br /><br />'.$lang['survey_137'];
		$email->setBody($emailContents, true);
		if ($email->send($removeDisplayName)) {
			// Logging
			Logging::logEvent("","redcap_user_information","MANAGE",$this_userid,"username = '$this_userid'","Send email address verification to user");
			return true;
		}
		exit($email->getSendError());
	}

	// Remove a user's email verification code from the user_info table after their account has been verified
	public static function removeUserVerificationCode($userid, $email_account=1)
	{
		// Determine which user_email field we're updating based upon $email_account
		$user_email_field = "email" . ($email_account > 1 ? $email_account : "") . "_verify_code";
		// Query the table
		$sql = "update redcap_user_information set $user_email_field = null
				where username = '".db_escape($userid)."' limit 1";
		$q = db_query($sql);
		return ($q && db_affected_rows() > 0);
	}

	// Verify a user's email verification code that they received in an email.
	// Return the email account it corresponds to (1=primary,2=secondary,3=tertiary) or false if failed.
	public static function verifyUserVerificationCode($userid, $code)
	{
		// Query the table
		$sql = "select email_verify_code, email2_verify_code, email3_verify_code
				from redcap_user_information where username = '".db_escape($userid)."'
				and (email_verify_code = '".db_escape($code)."' or email2_verify_code = '".db_escape($code)."'
				or email3_verify_code = '".db_escape($code)."') limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			$row = db_fetch_assoc($q);
			// Determine which email account it corresponds to
			if ($row['email_verify_code'] == $code) {
				return '1';
			} elseif ($row['email2_verify_code'] == $code) {
				return '2';
			} elseif ($row['email3_verify_code'] == $code) {
				return '3';
			}
			return false;
		} else {
			return false;
		}
	}

	// Verify a that an email verification code received in an email belongs to *SOME* user (we may not know who).
	// Return array of username/email_verfiy field name if a match, else return false. True if code exists for some user, false if not for any user
	public static function verifyUserVerificationCodeAnyUser($code)
	{
		// Query the table
		$sql = "select username, if (email_verify_code = '".db_escape($code)."', 'user_email', if (email2_verify_code = '".db_escape($code)."', 'user_email2', 'user_email3')) as email_account 
				from redcap_user_information
				where (email_verify_code = '".db_escape($code)."' or email2_verify_code = '".db_escape($code)."'
				or email3_verify_code = '".db_escape($code)."') limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0) ? array(db_result($q, 0, 'username'), db_result($q, 0, 'email_account')) : false;
	}

	// Get all info for specified user from user_information table and return as array
	public static function getUserInfo($userid)
	{
		$sql = "select * from redcap_user_information where username = '".db_escape($userid)."' limit 1";
		$q = db_query($sql);
		return (($q && db_num_rows($q) > 0) ? db_fetch_assoc($q) : false);
	}

	//***<AAF Modification>>***
        public static function getAafUser($userid,$email){
                $q=db_query("select * from redcap_user_information where ui_id=0");
                $sqlStrArr=array("select * from redcap_user_information where username = '".db_escape($userid)."'","select * from redcap_user_information where username='".db_escape($email)."' or user_email='".db_escape($email)."' or user_email2='".db_escape($email)."' or user_email3='".db_escape($email)."'");
                foreach($sqlStrArr as $value){
                        $q=db_query($value);
                        if(db_num_rows($q)==1){
                                break;
                        }
                }
                return $q;
        }

        public static function updateUsernameForAaf($newUsername,$oldUsername,$edupersontargetid){
                $usernameArr=array("redcap_user_information","redcap_user_rights","redcap_user_allowlist","redcap_esignatures","redcap_external_links_users","redcap_locking_data","redcap_reports_access_users","redcap_sendit_docs");
                $userArr=Logging::getLogEventTables();
                $userArr[]="redcap_log_view";
                $userDel=array("redcap_auth","redcap_auth_history");
                foreach($usernameArr as $value){
                        $sql="update ".$value." set username='".$newUsername."' where username='".db_escape($oldUsername)."'";
                        if(strcasecmp($value,"redcap_user_information")==0){
                                $sql="update ".$value." set username='".$newUsername."',user_inst_id='".$edupersontargetid."' where username='".db_escape($oldUsername)."'";
                        }
                        db_query($sql);
                }
                foreach($userArr as $value){
                        $sql="update ".$value." set user='".$newUsername."' where user='".db_escape($oldUsername)."'";
                        db_query($sql);
                }
                foreach($userDel as $value){
                        $sql="delete from ".$value." where username='".db_escape($oldUsername)."'";
                        db_query($sql);
                }
        }
	//***</AAF Modification>>***

	public static function getUIIDByUsername($username)
	{
		if (defined("USERID") && $username == USERID && defined("UI_ID")) {
			// Return the current user's UI_ID constant, if available
			return UI_ID;
		} else {
			$info = self::getUserInfo($username);
			return $info ? $info['ui_id'] : null;
		}
	}
	
	// Get integer value for $user_sponsor_set_expiration_days
	public static function getUserSponsorSetExpireDays()
	{
		global $user_sponsor_set_expiration_days;
		$user_sponsor_set_expiration_days = (int)$user_sponsor_set_expiration_days;
		if (!is_numeric($user_sponsor_set_expiration_days) || $user_sponsor_set_expiration_days < 1) {
			$this_user_sponsor_set_expiration_days = 365;
		} else {
			$this_user_sponsor_set_expiration_days = $user_sponsor_set_expiration_days;
		}
		return $this_user_sponsor_set_expiration_days;
	}

	// Get all info for specified user from user_information table by using user's UI_ID and return as array
	public static function getUserInfoByUiid($ui_id)
	{
		if (!is_numeric($ui_id)) return false;
		$sql = "select * from redcap_user_information where ui_id = $ui_id limit 1";
		$q = db_query($sql);
		return (($q && db_num_rows($q) > 0) ? db_fetch_assoc($q) : false);
	}

	// Update user_firstvisit value for specified user in user_information table
	public static function updateUserFirstVisit($userid)
	{
		$sql = "update redcap_user_information set user_firstvisit = '".NOW."'
				where username = '".db_escape($userid)."' limit 1";
		return db_query($sql);
	}

	// Determine if specified username is a Table-based user (i.e. in redcap_auth)
	public static function isTableUser($userid)
	{
		// Query the table
		$sql = "select 1 from redcap_auth where username = '".db_escape($userid)."' limit 1";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

	// Check if an email address is acceptable regarding the "domain allowlist for user emails" (if enabled)
	public static function emailInDomainAllowlist($email='') {
		global $email_domain_allowlist;
		$email = strtolower(trim($email));
		if ($email_domain_allowlist == '' || $email == '') return null;
		$email_domain_allowlist_array = explode("\n", str_replace("\r", "", strtolower($email_domain_allowlist)));
		list ($emailFirstPart, $emailDomain) = explode('@', $email, 2);
		return (in_array($emailDomain, $email_domain_allowlist_array));
	}

	// Return array of users with Data Resolution "respond" privileges. Has UI_ID as key and username as value.
	public static function getUsersDataResRespond($appendFirstLastName=true)
	{
		global $lang, $user_rights;
		// Create select list of usernames
		$userOptions = array(''=>$lang['rights_133']);
		// Get array of all users and their rights
		$projectUsers = UserRights::getPrivileges(PROJECT_ID);
		// Loop through all users to filter out those who cannot do DRW respond
		$projectUsernamesDRWrespond = array();
		foreach ($projectUsers[PROJECT_ID] as $this_user=>$row)
		{
			// Only add to array if data_resolution rights > 1 AND only if user is in user_information table
			if ($row['data_quality_resolution'] > 1) {
				// Add user to array
				$projectUsernamesDRWrespond[] = $this_user;
			}
		}
		// Get email addresses and names from table (if user is in a DAG, return only users in their DAG and non-DAG users)
		$sql = "select u.username, i.ui_id, trim(concat(i.user_firstname, ' ', i.user_lastname)) as full_name
				from redcap_user_rights u, redcap_user_information i
				where u.project_id = ".PROJECT_ID." and i.username = u.username
				and i.username in (".prep_implode($projectUsernamesDRWrespond).")";
		if (is_numeric($user_rights['group_id'])) {
			$sql .= " and (u.group_id is null or u.group_id = '{$user_rights['group_id']}')";
		}
		$sql .= " order by u.username";
		//print $sql;
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Add user to array
			$userOptions[$row['ui_id']] = $row['username'];
			// Add first/last name to array (if flag is set)
			if ($appendFirstLastName) {
				$userOptions[$row['ui_id']] .= " ({$row['full_name']})";
			}
		}
		// Return the array of users
		return $userOptions;
	}


	// Display dashboard of table-based users that are on the old MD5 password hashing.
	// Give options to email the users to encourage them to log in OR to suspend them.
	public static function renderDashboardPasswordHashProgress()
	{
		global $lang, $homepage_contact_email;
		// Html
		$h = '';
		// Get count of un-suspended table-based users that have legacy hash
		$sql = "select count(1) from redcap_auth a, redcap_user_information i
				where a.username = i.username and a.legacy_hash = 1 and i.user_suspended_time is null";
		$q = db_query($sql);
		$num_users_legacy_hash = db_result($q, 0);

		// Get count of un-suspended table-based users that have new hash
		$sql = "select count(1) from redcap_auth a, redcap_user_information i
				where a.username = i.username and a.legacy_hash = 0 and i.user_suspended_time is null";
		$q = db_query($sql);
		$num_users_new_hash = db_result($q, 0);

		// Create yellow/green table displaying user counts
		$table_user_count = RCView::table(array('cellspacing'=>0, 'style'=>'border:1px solid #aaa;'),
			RCView::tr('',
				RCView::td(array('style'=>'background-color:#FFFF80;padding:5px;'),
					RCView::img(array('src'=>'exclamation_orange.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'),
						$lang['rights_287']
					)
				) .
				RCView::td(array('style'=>'text-align:center;background-color:#FFFF80;padding:5px;font-weight:bold;font-size:14px;'),
					$num_users_legacy_hash
				)
			) .
			// Do NOT show the action buttons if 0 users using weaker hash
			($num_users_legacy_hash == 0 ? '' :
				RCView::tr('',
					RCView::td(array('colspan'=>'2', 'style'=>'text-align:center;background-color:#FFFF80;padding:0 7px 5px;'),
						// Remind users via email to log in soon
						RCView::button(array('id'=>'table_user_security_btn1', 'class'=>'jqbuttonsm', 'style'=>'vertical-align:middle;', 'onclick'=>"
							simpleDialog(null,null,'table_user_security_remind_login_action',600,null,'".js_escape($lang['global_53'])."',
							function(){
								$.post(app_path_webroot+'ControlCenter/password_hash_actions.php',{action:'reminder'},function(data){
									if (data != '1') {
										alert(woops);
										return;
									}
									$('#table_user_security_btn1').button('disable');
									simpleDialog('".js_escape($lang['rights_295'])."','".js_escape($lang['setup_08'])."');
								});
							},
							'".js_escape($lang['rights_293'])."');
						"),
							$lang['rights_288']
						) .
						// Suspend users with weaker hash
						RCView::button(array('id'=>'table_user_security_btn1', 'class'=>'jqbuttonsm', 'style'=>'vertical-align:middle;', 'onclick'=>"
							simpleDialog(null,null,'table_user_security_suspend_action',500,null,'".js_escape($lang['global_53'])."',
							function(){
								$.post(app_path_webroot+'ControlCenter/password_hash_actions.php',{action:'suspend'},function(data){
									if (data != '1') {
										alert(woops);
										return;
									}
									simpleDialog('".js_escape($lang['rights_296'])."','".js_escape($lang['setup_08'])."',null,null,
										function(){ window.location.href=app_path_webroot+'ControlCenter/create_user.php'; } );
								});
							},
							'".js_escape($lang['rights_298'])."');
						"),
							$lang['rights_289']
						)
					)
				)
			) .
			RCView::tr('',
				RCView::td(array('style'=>'background-color:#8BEA84;padding:5px;'),
					RCView::img(array('src'=>'tick_circle.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'),
						$lang['rights_290']
					)
				) .
				RCView::td(array('style'=>'text-align:center;background-color:#8BEA84;padding:5px;font-weight:bold;font-size:14px;'),
					$num_users_new_hash
				)
			)
		);

		// Render hidden dialog divs
		$h .= RCView::div(array('id'=>'table_user_security_remind_login_action', 'class'=>'simpleDialog', 'title'=>$lang['rights_288']),
				$lang['rights_294'] .
				RCView::div(array('style'=>'margin-top:15px;padding:5px;border:1px solid #ddd;'),
					RCView::b("Subject:") . " " .$lang['rights_282'] . RCView::br() . RCView::br() .
					"{$lang['cron_02']}<br /><br />{$lang['rights_283']} \"<b>USERNAME</b>\"{$lang['period']}
					{$lang['rights_284']}<br /><br />{$lang['rights_285']}
					<a style=\"text-decoration:underline;\" href=\"mailto:$homepage_contact_email\">$homepage_contact_email</a>{$lang['period']}<br /><br />
					<b>REDCap</b> - <a style=\"text-decoration:underline;\" href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>"
				)
			  );
		$h .= RCView::div(array('id'=>'table_user_security_suspend_action', 'class'=>'simpleDialog', 'title'=>$lang['rights_289']),
				$lang['rights_297']
			  );
		// Display the box
		// $h .= RCView::fieldset(array('style'=>'margin-bottom:20px;padding:5px;border:1px solid #ccc;'),
				// RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
					// RCView::img(array('src'=>'tick_shield_lock.png')) .
					// $lang['rights_286']
				// ) .
				// RCView::div(array('style'=>'float:left;color:#333;font-size:11px;line-height:11px;width:340px;'),
					// $lang['rights_291'] . " " .
					// ($num_users_legacy_hash == 0 ? '' :
						// RCView::a(array('href'=>'javascript:;', 'id'=>'table_user_security_info_link', 'style'=>'text-decoration:underline;font-size:11px;', 'onclick'=>"$(this).hide();$('#table_user_security_info_div').show();"), $lang['scheduling_78']) .
						// RCView::div(array('id'=>'table_user_security_info_div', 'style'=>'margin-top:8px;display:none;'),
							// $lang['rights_292']
						// )
					// )
				// ) .
				// RCView::div(array('style'=>'float:right;'),
					// $table_user_count
				// ) .
				// RCView::div(array('class'=>'clear'), '')
			 // );
		// Return the HTML
		return $h;
	}


	// Return array of all number format decimal options (to be used as options in drop-down)
	public static function getNumberDecimalFormatOptions()
	{
		global $lang;
		$options = array();
		// Loop through all options
		foreach (self::$number_format_decimal_formats as $option)
		{
			$val = $option;
			if ($option == '.') {
				$option .= " " . $lang['user_85'];
			} elseif ($option == ',') {
				$option .= " " . $lang['user_86'];
			}
			$options[$val] = $option;
		}
		// Return options
		return $options;
	}


	// Return array of all number format thousands separator options (to be used as options in drop-down)
	public static function getNumberThousandsSeparatorOptions()
	{
		global $lang;
		$options = array();
		// Loop through all options
		foreach (self::$number_format_thousands_sep_formats as $option)
		{
			$val = $option;
			if ($option == '.') {
				$option .= " " . $lang['user_85'];
			} elseif ($option == ',') {
				$option .= " " . $lang['user_86'];
			} elseif ($option == 'SPACE') {
				$option = " " . $lang['user_87'];
			} elseif ($option == "'") {
				$option .= " " . $lang['user_88'];
			} elseif ($option == "") {
				$option .= " " . $lang['user_92'];
			}
			$options[$val] = $option;
		}
		// Return options
		return $options;
	}


	// Get user's number format for decimals
	public static function get_user_number_format_decimal()
	{
		global $number_format_decimal;
		// Set destination format
		return ($number_format_decimal == null ? self::$default_number_format_decimal_system : $number_format_decimal);
	}


	// Get user's number format for thousands separator
	public static function get_user_number_format_thousands_separator()
	{
		global $number_format_thousands_sep;
		// Set destination format
		return ($number_format_thousands_sep === null)
				? self::$default_number_format_thousands_sep_system
				: ($number_format_thousands_sep == 'SPACE' ? ' ' : $number_format_thousands_sep);
	}


	// Format a number to a user's preferred display format for decimal and thousands separator
	public static function number_format_user($val, $decimals=0)
	{
		return number_format($val, $decimals, self::get_user_number_format_decimal(), self::get_user_number_format_thousands_separator());
	}

	public static function getMessagingEmailPreferencesOptions(){
		global $lang;
		$options = array();
		// Loop through all options
		foreach (self::$messaging_email_preference_options as $option)
		{
			$val = $option;
			if ($option == 'NONE') {
				$option  = $lang['user_94'];
			} elseif ($option == 'DAILY') {
				$option = $lang['user_96'];
			} elseif ($option == 'ALL') {
				$option = $lang['user_97'];
			} else {
				list ($num_hours, $word) = explode("_", $option, 2);
				$option = $num_hours.$lang['user_99'];
			}
			$options[$val] = $option;
		}
		// Return options
		return $options;
	}
	
	// Render the Sponsor Dashboard page
	public static function renderSponsorDashboard($controlCenterView=false, $displayOnlyTheseUiIds=array())
	{
		extract($GLOBALS);
		if (!$controlCenterView) $displayOnlyTheseUiIds = array();
		if (!$controlCenterView) $displayOnlyTheseUiIds = array();
		// Does this installation use Table-based users?
		$hasTableBasedUsers = in_array($auth_meth_global, array('none', 'table', 'ldap_table'));
		// Allowable actions
		$actions = array('suspend'=>$lang['control_center_4610'], 'unsuspend'=>$lang['control_center_4609'], 
			'set expiration'=>$lang['control_center_4608'], 'extend expiration'=>$lang['control_center_4612']);
		if ($hasTableBasedUsers) $actions['reset password'] = $lang['control_center_140'];
		if ($hasTableBasedUsers) $actions['resend account creation email'] = $lang['control_center_4699'];
		// Determine the requester (current user or requester in query string?)
		if (!$controlCenterView && isset($_GET['requester']) && is_numeric($_GET['requester'])) {
			$userInfo = User::getUserInfoByUiid($_GET['requester']);
			$requester = is_array($userInfo) ? $userInfo['username'] : "";
			$requester_email = is_array($userInfo) ? $userInfo['user_email'] : "";
			$requester_email_display_name = is_array($userInfo) ? $userInfo['user_firstname']." ".$userInfo['user_lastname'] : "";
		} else {
			$requester = USERID;
			$requester_email = $user_email;
			$userInfo = User::getUserInfoByUiid(UI_ID);
			$requester_email_display_name = $GLOBALS['user_firstname']." ".$GLOBALS['user_lastname'];
		}
		// Get the sponsor's users
		$sponsorUsers = array();
		if (!$controlCenterView || !($controlCenterView && empty($displayOnlyTheseUiIds)) 
			|| ($controlCenterView && $_SERVER['REQUEST_METHOD'] == 'POST')) 
		{
			$sponsorUsers = User::getSponsorUserAttributes(($controlCenterView ? '' : $requester), $displayOnlyTheseUiIds);
		}
		// Decode param
		if (isset($_GET['type']))  $_GET['type']  = rawurldecode(urldecode($_GET['type']));
		if (isset($_POST['type'])) $_POST['type'] = rawurldecode(urldecode($_POST['type']));
		// Set confirmation message to display
		$confirmMsg = "";
		if (isset($_GET['msg'])) {
			if ($_GET['msg'] == 'requested') {
				$confirmMsg = $lang['control_center_4617'] . " " . $lang['control_center_4618'];
			} elseif ($_GET['msg'] == 'admin_save') {
				$confirmMsg = $lang['rights_349'];	
			}
		}


		// IF USER POSTED CHANGES, PROCESS THEM
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['type']) && isset($actions[$_POST['type']]) 
			&& ($controlCenterView || $requester != ""))
		{
			// Add selected users to array
			$selectedUsers = array();
			// Loop through Post values
			foreach ($_POST as $key=>$val) {
				// Get the ui_id and validate it
				$ui_id = str_replace("uiid_", "", $key);
				if (!is_numeric($ui_id) || !isset($sponsorUsers[$ui_id])) continue;
				$selectedUsers[] = $ui_id;
			}
			if (!empty($selectedUsers)) {
				// Set type of request\
				$requestTypeText = "Sponsor request - ".$_POST['type'];	
				$action_url = APP_PATH_WEBROOT_FULL . "index.php?action=user_sponsor_dashboard";	
				## APPROVE BY ADMIN
				if (SUPER_USER && ($controlCenterView || (isset($_GET['request_id']) && is_numeric($_GET['request_id']))))
				{
					// Perform the requested action
					$sql = "";
					switch ($_POST['type']) {
						case 'suspend':
							$sql = "update redcap_user_information set user_suspended_time = '".NOW."' 
									where ui_id in (".prep_implode($selectedUsers).")";
							break;
						case 'unsuspend':
							// Note: If user is being unsuspended and also has an acct expiration date that has passed, 
							// then go ahead and remove the expiration (otherwise they won't be able to access REDCap)
							$sql = "update redcap_user_information set user_suspended_time = null,
									user_expiration = if (user_expiration > '".NOW."', user_expiration, null) 
									where ui_id in (".prep_implode($selectedUsers).")";
							break;
						case 'set expiration':
							// If set/extend timestamp 
							if (isset($_POST['expire-time'])) {
								$acctExpireTime = ($_POST['expire-time'] == '') ? '' : DateTimeRC::format_ts_to_ymd(trim($_POST['expire-time'])).":00";
							} else {
								$acctExpireTime = DateTimeRC::format_user_datetime(date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")+self::getUserSponsorSetExpireDays(),date("Y"))), 'Y-M-D_24', null, true);
							}
							$sql = "update redcap_user_information 
									set user_expiration = ".checkNull($acctExpireTime)."
									where ui_id in (".prep_implode($selectedUsers).")";
							break;
						case 'extend expiration':
							$this_user_sponsor_set_expiration_days = User::getUserSponsorSetExpireDays();
							$sql = "update redcap_user_information 
									set user_expiration = if(user_expiration is null, TIMESTAMPADD(DAY,$this_user_sponsor_set_expiration_days,'".NOW."'), 
										TIMESTAMPADD(DAY,$this_user_sponsor_set_expiration_days,user_expiration)) 
									where ui_id in (".prep_implode($selectedUsers).")";
							break;
						case 'reset password':
							foreach ($selectedUsers as $thisUiId) {
								$thisUserInfo = User::getUserInfoByUiid($thisUiId);
								$resetSuccess = Authentication::resetPasswordSendEmail($thisUserInfo['username'], false);
							}
							break;
						case 'resend account creation email':
							foreach ($selectedUsers as $thisUiId) {
								$thisUserInfo = User::getUserInfoByUiid($thisUiId);								
								// Email the user (new users get their login info, existing users get notified if their email changes)
								$email = new Message();
								$email->setTo($thisUserInfo['user_email']);
								$email->setFrom($GLOBALS['user_email']);
								$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
								// Get reset password link
								$resetpasslink = Authentication::getPasswordResetLink($thisUserInfo['username'], false);
								// Set up the email to send to the user
								$email->setSubject('REDCap '.$lang['control_center_101']);
								$emailContents = $lang['control_center_4488'].' "<b>'.$thisUserInfo['username'].'</b>"'.$lang['period'].' '.
												 $lang['control_center_4486'].'<br /><br /> '.
												 $lang['control_center_4655'].'<br /><br />
												 <a href="'.$resetpasslink.'">'.$lang['control_center_4487'].'</a>';
								// If the user had an expiration time set, then let them know when their account will expire.
								if ($thisUserInfo['user_expiration'] != '') {
									$daysFromNow = floor((strtotime($thisUserInfo['user_expiration']) - strtotime(NOW)) / (60*60*24));
									$emailContents .= " ".$lang['control_center_4402']."<b>".DateTimeRC::format_ts_from_ymd($thisUserInfo['user_expiration'])
													. " -- $daysFromNow " . $lang['control_center_4438']
													. "</b>".$lang['control_center_4403'];
								}
								// If "auto-suspend due to inactivity" feature is enabled, the notify user generally that users may
								// get suspended if don't log in for a long time.
								if ($GLOBALS['suspend_users_inactive_type'] != '') {
									$emailContents .= " ".$lang['control_center_4424'];
								}
								// The Display Name was removed here because it was causing these particular emails to be flagged as spam on many email servers and thus being blocked.
								$removeDisplayName = true;
								// Send the email
								$email->setBody($emailContents, true);
								$email->send($removeDisplayName);
							}
							break;
					}
					if ($sql != "") db_query($sql);
					if ($controlCenterView) {
						// Logging
						Logging::logEvent($sql, "redcap_user_information", "MANAGE", "", "ui_id in (".implode(",", $selectedUsers).")", 
										  "Administrator multiple user action: ".$_POST['type']);
					} else {
						// Remove from To-Do List
						ToDoList::updateTodoStatus("", $requestTypeText, 'completed', $_GET['requester']);
						// Send email to requester
						$msg = RCView::escape("{$userInfo['user_firstname']} {$userInfo['user_lastname']} {$lang['leftparen']}{$requester}{$lang['rightparen']}").$lang['comma'];
						$msg .= "<br><br>\n";
						$msg .= $lang['control_center_4627'] . ' ' . $lang['leftparen'] . RCView::b($actions[$_POST['type']]) . $lang['rightparen'] . ' ';
						$msg .= $lang['control_center_4628'] . ' ' . count($selectedUsers) . ' ' . $lang['control_center_4631'];
						$msg .= "<br><br>\n" . RCView::a(array('href' => $action_url), $lang['control_center_4630']);
						$msg .= "<br><br>\n" . $lang['control_center_4650'];
						foreach ($selectedUsers as $thisUiId) {							
							$thisUserInfo = User::getUserInfoByUiid($thisUiId);
							$msg .= "<br>\n- " . $thisUserInfo['username'];
						}
						$email = new Message();
						$email->setTo($requester_email);
						$email->setFrom($user_email);
						$email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
						$email->setSubject('[REDCap] ' . $lang['control_center_4629'] . ' ' . $actions[$_POST['type']]);
						$email->setBody($msg, true);
						if (!$email->send()) print $email->getSendError();
						// Logging
						Logging::logEvent($sql, "redcap_user_information", "MANAGE", $_GET['request_id'], "user_sponsor = '$requester',\nrequest_id = ".$_GET['request_id'], "Administrator approve: $requestTypeText");
						// Display success message to admin
						print RCView::div(array('class'=>'darkgreen', 'style'=>'margin-bottom:50px;'),
								RCView::img(array('src'=>'tick.png')) . $lang['control_center_4613']
							);
						?>
						<script type="text/javascript">
						// If we're in the To-Do List, then close the iframe dialog
						$(function(){
							if (inIframe()) {
								window.top.$('.iframe-container').fadeOut(200, function(){
									window.top.location.reload();
								});
							}
						});
						</script>
						<?php
						$objHtmlPage = new HtmlPage();
						$objHtmlPage->PrintFooter();
						exit;
					}
				}
				## SUBMIT REQUEST BY SPONSOR
				else 
				{
					// Set comment text that lists the users in the request
					$request_comments = "";
					$request_comments_users = array();
					foreach ($selectedUsers as $thisUiId) {							
						$thisUserInfo = User::getUserInfoByUiid($thisUiId);
						$request_comments_users[] = $thisUserInfo['username'];
					}
					$request_comments .= implode(", ", $request_comments_users);
					// Add to todolist
					$action_url .= "&requester=".UI_ID."&type=".urlencode($_POST['type'])."&userids=".implode(",", $selectedUsers);
					$request_id = ToDoList::insertAction(UI_ID, $project_contact_email, $requestTypeText, $action_url, null, $lang['control_center_4654']." ".$request_comments);
					$action_url .= "&request_id=$request_id";
					// Send email to admin
					$doRedirect = true;
					if ($send_emails_admin_tasks) {
						$msg = RCView::escape("{$userInfo['user_firstname']} {$userInfo['user_lastname']} ($userid) ");
						$msg .= $lang['control_center_4615'] . "<br><br>\n";
						$msg .= RCView::a(array('href' => $action_url), $lang['control_center_4616']." ".$_POST['type']);
						$msg .= "<br><br>\n" . $lang['control_center_4653']." ".nl2br($request_comments);
						$email = new Message();
						$email->setFrom($requester_email);
						$email->setFromName($requester_email_display_name);
						$email->setTo($project_contact_email);
						$email->setSubject("[REDCap] ".$lang['control_center_4614']." ".$_POST['type']);
						$email->setBody($msg, true);
						$doRedirect = $email->send();
						if (!$doRedirect) print $email->getSendError();
					}
					// Logging
					Logging::logEvent("", "redcap_user_information", "MANAGE", $request_id, "user_sponsor = '$userid',\nrequest_id = $request_id", $requestTypeText);
					// Redirect to prevent double post
					if ($doRedirect) redirect($_SERVER['REQUEST_URI']."&msg=requested");
				}
				return;
			}
		}

		// If userids in query string, validate them and put in array
		$userids = array();
		foreach (explode(",", $_GET['userids']) as $ui_id) {
			if (!is_numeric($ui_id) || !isset($sponsorUsers[$ui_id])) continue;
			$userids[] = $ui_id;
		}
		
		// Build the table of uses
		$table = '<table id="sponsorUsers-table" style="width:100%;">
			<thead>
			<tr>
				<th class="text-center"><input type="checkbox" onclick="selectOrDeselectAll()"></th>
				<th class="wrap" style="font-size:12px;">'.$lang['global_11'].'</th>
				<th class="wrap">'.$lang['global_41'].'</th>
				<th class="wrap">'.$lang['global_42'].'</th>
				<th class="wrap">'.$lang['global_33'].'</th>
				<th class="wrap">'.$lang['control_center_4635'].'</th>
				<th class="wrap">'.$lang['rights_334'].'</th>
				'.($controlCenterView ? '<th class="wrap" style="word-wrap: break-word;">'.$lang['control_center_57'].'</th>' : '').'
				'.($controlCenterView ? '<th class="wrap">'.$lang['user_72'].'</th>' : '').'
				'.($controlCenterView ? '<th class="wrap">'.$lang['control_center_236'].'</th>' : '').'
				'.($controlCenterView ? '<th class="wrap">'.$lang['dataqueries_146'].'</th>' : '').
				(!$superusers_only_create_project && $controlCenterView ? '<th class="wrap">'.$lang['control_center_4701'].'</th>' : '').
				'<th class="wrap">'.$lang['rights_335'].'</th>
				<th class="wrap">'.$lang['control_center_148'].'</th>
				<th class="wrap">'.$lang['control_center_429'].'</th>
				'.($hasTableBasedUsers ? '<th class="wrap">'.$lang['control_center_4632'].(empty($sponsorUsers) ? '' : '<br><a id="fetchAllLink" class="nowrap" href="javascript:;" style="font-weight:normal;font-size:11px;text-decoration:underline;">'.$lang['control_center_4634'].'</a>').'</th>' : '').'
			</tr>
			</thead>
			<tbody>';
		$allUsernames = array();
		foreach ($sponsorUsers as $thisUiId=>$thisUser) 
		{
			// Add username to array
			$allUsernames[] = $thisUser['username'];
			// Is user listed in query string?
			$userSelected = in_array($thisUiId, $userids) ? "checked" : "";
			// Do not show checkbox for suspended users
			$suspendIcon = ($thisUser['user_suspended_time'] == '') ? '' : '<img src="'.APP_PATH_IMAGES.'exclamation.png" class="opacity75" title="'.js_escape2(DateTimeRC::format_ts_from_ymd($thisUser['user_suspended_time'])).'">';	
			$passLastReset = '<span style="color:#ccc;">'.$lang['control_center_149'].'</span>';
			$superUserIcon = $thisUser['super_user'] ? '<img src="'.APP_PATH_IMAGES.'tick.png" class="opacity75">' : '';	
			// Set classes for checkbox
			$classes = "";
			if ($thisUser['user_suspended_time'] == '') $classes .= " set-expiration";
			if ($thisUser['user_expiration'] != '' && $thisUser['user_suspended_time'] == '') $classes .= " extend-expiration";
			if ($thisUser['table_user']) {
                $classes .= " reset-password-fetch";
				if ($thisUser['user_suspended_time'] == '') $classes .= " reset-password";
				if ($thisUser['user_suspended_time'] == '' && $thisUser['user_lastlogin'] == '') $classes .= " resend-account-creation-email";
				$passLastReset = '<span style="color:#777;">'.$lang['control_center_4633'].'</span>';
			}
			$classes .= ($thisUser['user_suspended_time'] == '') ? " suspend" : " unsuspend";
			// Checkbox
			$checkbox = '<input type="checkbox" name="uiid_'.$thisUiId.'" '.$userSelected.' class="'.$classes.'">';
			// If users can create projects, display column showing who has project creation rights
			$create_project = '';
			if (!$superusers_only_create_project && $controlCenterView) {
				$create_project = '<td class="text-center">'.($thisUser['create_project'] ? '<img src="'.APP_PATH_IMAGES.'tick.png" class="opacity75">' : "").'</td>';
			}
			// Add row
			$table .= 	'<tr>
							<td class="text-center wrap" style="">'.$checkbox.'</td>
							<td>'.($controlCenterView ? '<a href="view_users.php?username='.$thisUser['username'].'" style="font-size:11px;text-decoration:underline;color:#A00000;">'.$thisUser['username'].'</a>' : '<span style="color:#A00000;">'.$thisUser['username'].'</span>').'</td>
							<td>'.RCView::escape($thisUser['user_firstname']).'</td>
							<td>'.RCView::escape($thisUser['user_lastname']).'</td>
							<td><a style="font-size:11px;" href="mailto:'.$thisUser['user_email'].'">'.RCView::escape($thisUser['user_email']).'</a></td>
							<td class="text-center"><button class="btn btn-xs btn-defaultrc" style="font-size:11px;" onclick="getProj('.$thisUiId.',this);return false;">'.$lang['global_84'].'</a></td>
							<td class="text-center">'.$suspendIcon.'</td>
							'.($controlCenterView ? '<td class="text-center">'.$superUserIcon.'</td>' : '').'
							'.($controlCenterView ? '<td>'.($thisUser['user_sponsor'] != '' ? '<a href="view_users.php?username='.RCView::escape($thisUser['user_sponsor']).'" style="font-size:11px;text-decoration:underline;">'.RCView::escape($thisUser['user_sponsor']).'</a>' : '').'</td>' : '').'
							'.($controlCenterView ? '<td>'.RCView::escape($thisUser['user_inst_id']).'</td>' : '').'
							'.($controlCenterView ? '<td><div style="max-height:70px;overflow-y:scroll;">'.RCView::escape($thisUser['user_comments']).'</div></td>' : '').
							$create_project . 
							'<td class="nowrap"><span class="hidden">'.$thisUser['user_expiration'].'</span>'.DateTimeRC::format_ts_from_ymd($thisUser['user_expiration']).'</td>
							<td class="nowrap"><span class="hidden">'.$thisUser['user_lastactivity'].'</span>'.DateTimeRC::format_ts_from_ymd($thisUser['user_lastactivity']).'</td>
							<td class="nowrap"><span class="hidden">'.$thisUser['user_lastlogin'].'</span>'.DateTimeRC::format_ts_from_ymd($thisUser['user_lastlogin']).'</td>
							'.($hasTableBasedUsers ? '<td uiid="'.$thisUiId.'" class="plr text-center nowrap">'.$passLastReset.'</td>' : '').'
						</tr>';
			
		}
		$table .= '</tbody></table>';

		// Build project drop-down list
		$projectsAllUsers = User::getProjectsByUser($allUsernames);
		$projectsAllUsers = array(''=>$lang['control_center_4639']) + $projectsAllUsers;
		$projectsDropDown = RCView::select(array('id'=>'projectDropDown', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:300px;font-size:12px;', 'onchange'=>"selectUsersViaProjectDD(this.value);return false;"), 
								$projectsAllUsers, '', 500);
		$usersInProjects = User::getProjectsUiIDsByUsers($allUsernames);
		
		$h = '';
		if (!$controlCenterView) {
			// Set page title
			$h .= RCView::div(array('style'=>''),
					RCView::div(array('style'=>'float:left;font-size:18px;font-weight:bold;'),
						RCView::img(array('src'=>'user_sponsor.png', 'style'=>'width:32px;margin-right:3px;')) .
						$lang['rights_330']
					) .
					RCView::div(array('class'=>'d-print-none', 'style'=>'float:right;margin-right:50px;'),
						RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.print();", 'style'=>'font-size:12px;'),
							RCView::img(array('src'=>'printer.png', 'style'=>'vertical-align:middle;')) .
							RCView::span(array('style'=>'vertical-align:middle;'),
								$lang['custom_reports_13']
							)
						)
					) .
					RCView::div(array('class'=>'clear'), '')
				 );
			// Instructions
			$h .= 	RCView::p(array('class'=>'d-none d-sm-block', 'style'=>'margin:20px 0;max-width:920px;'),
						$lang['rights_336']
					);
		}
		// Save buttons
		$buttonDisable = array();
		$buttonAppendText = array();
		foreach (array_keys($actions) as $thisAction) {
			$buttonDisable[$thisAction] = $buttonAppendText[$thisAction] = "";
		}
		if (!isset($_GET['requester']) && !$controlCenterView) {
			foreach (array_keys($actions) as $thisAction) {
				if (ToDoList::checkIfRequestExist("", UI_ID, "Sponsor request - ".$thisAction) > 0) {
					$buttonDisable[$thisAction] = "disabled";
					$buttonAppendText[$thisAction] = RCView::br() . RCView::span(array('class'=>'nowrap', 'style'=>'color:#000;'), $lang['leftparen'].$lang['edit_project_179'].$lang['rightparen']);
				}
			}
		}
		$b = "";
		if (!empty($sponsorUsers)) {
			$b = 	RCView::div(array('class'=>'d-print-none', 'style'=>'text-align:center;margin:30px 0;'),
						RCView::div(array('style'=>'margin-bottom:20px;color:#333;font-weight:bold;'), 
							($controlCenterView ? $lang['rights_347'] : $lang['rights_340'])
						) .  
						// Re-send the account creation email (table-based only)
						(!$hasTableBasedUsers ? '' :
							RCView::button(array('id'=>'btn-resend-account-creation-email', 'class'=>'btn btn-primaryrc btn-sm wrap', 'style'=>'max-width:130px;', $buttonDisable['resend account creation email']=>$buttonDisable['resend account creation email'],
								'onclick'=>"submitSponsorForm('resend account creation email');return false;"), $actions['resend account creation email'] . $buttonAppendText['resend account creation email']) .
							RCView::span(array('style'=>'color:#777;margin:0 6px 0 2px;'), $lang['global_47'])
						) . 
						// Reset password (table-based only)
						(!$hasTableBasedUsers ? '' :
							RCView::button(array('id'=>'btn-reset-password', 'class'=>'btn btn-primaryrc btn-sm wrap', 'style'=>'max-width:120px;', $buttonDisable['reset password']=>$buttonDisable['reset password'],
								'onclick'=>"submitSponsorForm('reset password');return false;"), $actions['reset password'] . $buttonAppendText['reset password']) .
							RCView::span(array('style'=>'color:#777;margin:0 6px 0 2px;'), $lang['global_47'])
						) . 
						// Set expiration
						RCView::button(array('id'=>'btn-set-expiration', 'class'=>'btn btn-info btn-sm wrap', 'style'=>'max-width:120px;', $buttonDisable['set expiration']=>$buttonDisable['set expiration'],
							'onclick'=>"submitSponsorForm('set expiration');return false;"), $actions['set expiration'] . $buttonAppendText['set expiration']) .
						// Extend expiration
						RCView::span(array('style'=>'color:#777;margin:0 6px 0 2px;'), $lang['global_47']) . 
						RCView::button(array('id'=>'btn-extend-expiration', 'class'=>'btn btn-info btn-sm wrap', 'style'=>'max-width:120px;', $buttonDisable['extend expiration']=>$buttonDisable['extend expiration'],
							'onclick'=>"submitSponsorForm('extend expiration');return false;"), $actions['extend expiration'] . $buttonAppendText['extend expiration']) .
						// Unuspend
						RCView::span(array('style'=>'color:#777;margin:0 6px 0 2px;'), $lang['global_47']) . 
						RCView::button(array('id'=>'btn-unsuspend', 'class'=>'btn btn-success btn-sm wrap', 'style'=>'max-width:120px;', $buttonDisable['unsuspend']=>$buttonDisable['unsuspend'],
							'onclick'=>"submitSponsorForm('unsuspend');return false;"), $actions['unsuspend'] . $buttonAppendText['unsuspend']) .
						// Suspend
						RCView::span(array('style'=>'color:#777;margin:0 6px 0 2px;'), $lang['global_47']) . 
						RCView::button(array('id'=>'btn-suspend', 'class'=>'btn btn-danger btn-sm wrap', 'style'=>'max-width:120px;', $buttonDisable['suspend']=>$buttonDisable['suspend'],
							'onclick'=>"submitSponsorForm('suspend');return false;"), $actions['suspend'] . $buttonAppendText['suspend']) .				
						// Clear button
						RCView::br() . RCView::br() . 
						RCView::a(array('href'=>'javascript:;', 'onclick'=>"selectAll(false);return false;", 'style'=>'font-size:12px;text-decoration:underline;margin-left:10px;'), $lang['control_center_4611'])
					);
		}
		// Display table
		$h .= RCView::form(array('method'=>'post', 'id'=>'sponsor_form', 
				'action'=>($controlCenterView ? APP_PATH_WEBROOT."ControlCenter/view_users.php?criteria_search=1&msg=admin_save&".$_SERVER['QUERY_STRING'] : 
				$_SERVER['REQUEST_URI']), 'enctype'=>'multipart/form-data'),
					($table != ''
						? 	$table . $b :
						// No table to display
						RCView::div(array('class'=>'yellow', 'style'=>'margin:20px 0;'),
							RCView::b($lang['global_03'].$lang['colon'])." ".$lang['rights_342']
						)
					) .
					RCView::hidden(array('name'=>'redcap_csrf_token', 'value'=>System::getCsrfToken()))
				);
		// Put all html in padded div
		$h = RCView::div(array(), $h);
		// Javascript
		?>
		<script type="text/javascript">
		var sponsorDataTable;
		var projectIdsUiIds = <?php print json_encode_rc($usersInProjects) ?>;
		$(function(){
			// DataTable
			doSponsorDataTable(true);
			// If super user performing request on behalf of user
			if (super_user && getParameterByName('userids') != '' && isNumeric(getParameterByName('request_id'))) {
				var getType = decodeURIComponent(getParameterByName('type'));
				getType = getType.replace(/\+/g,' ');
				submitSponsorForm(getType);
			}
			// Fetch all password reset times
			$('#fetchAllLink').click(function(e){
				e.stopPropagation();
				// Loop through each user
				var passIds = new Array(), i=0, thisUiId;
				$("td.plr").each(function(){
					var thisUiId = $(this).attr('uiid');
					if ($('#sponsorUsers-table input[name="uiid_'+thisUiId+'"].reset-password-fetch:checked').length) {
						passIds[i++] = thisUiId;
					}
				});
				var selectedChkbox = $('#sponsorUsers-table input[type="checkbox"].reset-password-fetch:checked');
				if (selectedChkbox.length == 0) {
					simpleDialog('<?php print js_escape($lang['rights_337']) ?>','<?php print js_escape($lang['global_01']) ?>');
					return;
				}
				selectedChkbox.parentsUntil('tr').parent().find("td.plr").html('<img src="'+app_path_images+'progress_circle.gif">');
				fetchLastResetTime(passIds);
			});
			// If any checkboxes are clicked, reset the project drop-down list
			$('#sponsorUsers-table input[type="checkbox"]').click(function(e){
				$('#projectDropDown').val('');
			});
			// Display confirmation message
			<?php if ($confirmMsg != '') { ?>
			simpleDialog('<?php print js_escape(RCView::div(array('style'=>'color:green;'), RCView::img(array('src'=>'tick.png')).$confirmMsg)) ?>','<?php print js_escape($lang['global_79']) ?>');
			<?php } ?>
		});
		// Select users from project drop-down selection
		function selectUsersViaProjectDD(pid) {
			// First, uncheck all
			$('#sponsorUsers-table input[type="checkbox"]:checked').prop('checked',false);
			for (var key in projectIdsUiIds[pid]) {
				var thisUserChkbox = $('#sponsorUsers-table input[name="uiid_'+projectIdsUiIds[pid][key]+'"]');
				thisUserChkbox.prop('checked',true);
				highlightTableRowOb(thisUserChkbox.parentsUntil('tr').parent(),3000);
			}
		}
		// DataTable
		function doSponsorDataTable(sortOnLoad) {
			try { 
				sponsorDataTable.destroy();
			} catch(e) { }
			var dataTableParams = { 
				"autoWidth": false,
				"processing": true,
				"paging": false,
				"info": false,
				"fixedHeader": { header: true, footer: false, headerOffset: 50 },
				"searching": <?php print ($controlCenterView ? "false" : "true") ?>,
				"ordering": true,
				"oLanguage": { "sSearch": "" },
				"columnDefs": [{
					"targets": 0,
					"orderable": false
				}]
			}
			if (sortOnLoad) {
				$.extend(dataTableParams, {
					"aaSorting": []
				});
			}
			sponsorDataTable = $('#sponsorUsers-table').DataTable(dataTableParams);
			// Set search input
			$('#sponsorUsers-table_filter input[type="search"]').attr('type','text').addClass('d-print-none').prop('placeholder','<?php print js_escape($lang['control_center_439']) ?>');	
			// Set title above table
			<?php if (!$controlCenterView) { ?>
			$('#sponsorUsers-table_wrapper div.row div.col-md-6:first')
				.html('<div class="clearfix"><div class="float-left" style="font-weight:bold;color:#C00000;font-size:15px;margin-right:15px;">'
					+ '<?php print js_escape($lang['rights_341']) ?> (<?php print count($sponsorUsers) ?>)</div>'
					+ '<div class="float-right" style="text-align:right;"><?php print js_escape($projectsDropDown) ?><br><input type="checkbox" onclick="hideSuspended(this.checked);" style="position:relative;top:2px;"> <span style="margin-right:15px;color:#A00000;font-size:11px;"><?php print js_escape($lang['rights_351']) ?></span></div></div>');
			// Change size via class of the search and title
			$('#sponsorUsers-table_wrapper div.row div.col-md-6:first').removeClass('col-md-6').addClass('col-md-9');
			$('#sponsorUsers-table_wrapper div.row div.col-md-6:first').removeClass('col-md-6').addClass('col-md-3');
			<?php } ?>
		}
		// Hide or show suspended users in table
		function hideSuspended(hide) {
			var ob = $('#sponsorUsers-table input.unsuspend').parentsUntil('tr').parent();
			if (hide) ob.hide(); else ob.show();
		}
		// Single AJAX call to fetch password last reset time
		function fetchLastResetTime(passIds) {
			if (passIds.length == 0) {
				doSponsorDataTable(false);
				return;
			}
			var userid = passIds.shift();
			$.post(app_path_webroot+'Home/user_sponsor_dashboard_ajax.php?action=last_password_reset&user='+userid,{ },function(data){
				var thisTd = $('td.plr[uiid='+userid+']');
				thisTd.removeClass('plr').removeAttr('uiid').html(data);
				fetchLastResetTime(passIds);
			});
		}
		// Fetch project list for this user and display in a dialog
		function getProj(userid,ob) {
			var thisUsername = $(ob).parentsUntil('tr').parent().find('td:eq(1)').text().trim();
			$.post(app_path_webroot+'Home/user_sponsor_dashboard_ajax.php?action=project_list&user='+userid,{ },function(data){
				simpleDialog(data,'<?php print js_escape($lang['control_center_4638']) ?> "'+thisUsername+'"');
			});
		}
		function selectAll(checked) {
			$('#sponsorUsers-table input[type="checkbox"]').prop('checked',checked);
			$('#projectDropDown').val('');
		}
		function selectOrDeselectAll() {
			var allAreChecked = ($('#sponsorUsers-table tbody input[type="checkbox"]:checked').length == $('#sponsorUsers-table tbody input[type="checkbox"]').length);
			selectAll(!allAreChecked);
		}
		function submitSponsorForm(type) {
			var selected = $('#sponsorUsers-table tbody input[type="checkbox"]:checked');
			var numSelected = selected.length;
			if (numSelected < 1) {
				if (super_user && getParameterByName('userids') != '' && isNumeric(getParameterByName('request_id'))) {
					// If super user is processing this, and there's nothing to process, let them know there's nothing to do
					simpleDialog('<?php print js_escape($lang['rights_350']) ?>','<?php print js_escape($lang['global_01']) ?>');
				} else {
					// Let user know about this issue
					simpleDialog('<?php print js_escape($lang['rights_337']) ?>','<?php print js_escape($lang['global_01']) ?>');
				}
				return;
			}
			var typeClass = type.replace(/ /g, '-');
			var typeLabel = $('button#btn-'+typeClass).text().trim();
			var notApplicable = $('#sponsorUsers-table tbody input[type="checkbox"]:checked').not('.'+typeClass);
			if (notApplicable.length > 0) {
				// Some users selected are not applicable for the selection. Allow undo.
				var notApplUsers = new Array();
				var i=0, thisUser;
				notApplicable.each(function(){
					thisUser = $(this).parentsUntil('tr').parent().find('td:eq(1)').text().trim()
							 + " (" + $(this).parentsUntil('tr').parent().find('td:eq(2)').text().trim()
							 + " " + $(this).parentsUntil('tr').parent().find('td:eq(3)').text().trim() + ")";
					if (thisUser != '') notApplUsers[i++] = thisUser;
				});
				var othermsg = '';
				if (typeClass == 'resend-account-creation-email') {
					othermsg = '<b style="color:#A00000;"><?php print js_escape($lang['control_center_4700']) ?></b><br><br>';
				}
				var msg = '<?php print js_escape($lang['control_center_4620']) ?> <b>'+typeLabel+'</b><?php print js_escape($lang['period']." ".$lang['control_center_4623']) ?><br><br>'
						+ othermsg
						+ '<b><?php print js_escape($lang['control_center_4624']) ?> ('+notApplUsers.length+')<?php print js_escape($lang['colon']) ?></b><div style="font-size:11px;color:#C00000;"> &bull; '+notApplUsers.join('<br> &bull; ')+'</div>';
				simpleDialog(msg,'<?php print js_escape($lang['control_center_4622']) ?>',null,450,null,
					'<?php print js_escape($lang['global_53']) ?>',"$('#sponsorUsers-table tbody input[type=\"checkbox\"]:checked').not('."+typeClass+"').prop('checked',false);setTimeout(\"submitSponsorForm('"+type+"')\",200);",'<?php print js_escape($lang['control_center_4621']) ?>');
			} else {
				// Confirmation
				var applUsers = new Array();
				var i=0, thisUser;
				selected.each(function(){
					thisUser = $(this).parentsUntil('tr').parent().find('td:eq(1)').text().trim()
							 + " (" + $(this).parentsUntil('tr').parent().find('td:eq(2)').text().trim()
							 + " " + $(this).parentsUntil('tr').parent().find('td:eq(3)').text().trim() + ")";
					if (thisUser != '') applUsers[i++] = thisUser;
				});
				if (super_user && (getParameterByName('criteria_search') != '' || (getParameterByName('userids') != '' && isNumeric(getParameterByName('request_id'))))) {
					// Admin processing the request
					var dlgTitle = '<?php print js_escape($lang['rights_339']) ?> '+typeLabel;
					var text2 = '<?php print js_escape($lang['rights_338']) ?>';
					var submitBtn = typeLabel;
					if (type == 'extend expiration') {
						text2 += '<div style="margin-top:10px;color:#A00000;"><?php print js_escape($lang['rights_345']." ".RCView::b(self::getUserSponsorSetExpireDays()." ".$lang['scheduling_25'])) ?></div>';
					} else if (type == 'set expiration') {
						text2 += '<div style="padding:20px 0 5px;font-weight:bold;">'+typeLabel+' <?php print js_escape($lang['control_center_4643']) ?> <span style="font-weight:normal;"><?php print js_escape($lang['control_center_4644']) ?></span></div>'
								+ '<?php print js_escape(
										RCView::text(array('id'=>'expire-time','value'=>DateTimeRC::format_user_datetime(date("Y-m-d H:i", mktime(date("H"),date("i"),date("s"),date("m"),date("d")+self::getUserSponsorSetExpireDays(),date("Y"))), 'Y-M-D_24', null, true),
											'onblur'=>"if(redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)) window.newInviteTime=this.value;",
											'class'=>'x-form-text x-form-field','style'=>'width:120px;')) .
										"<span class='df'>".DateTimeRC::get_user_format_label()." H:M</span>"
									) ?>';
					}
					<?php if (isset($_GET['request_id']) && !ToDoList::checkIfRequestPendingById($_GET['request_id'])) { ?>
						simpleDialog('<?php print js_escape($lang['edit_project_190']) ?>','<?php print js_escape($lang['edit_project_189']) ?>');
						return;
					<?php } ?>
				} else {
					// Sponsor making the request
					var dlgTitle = '<?php print js_escape($lang['control_center_4619']) ?>';
					var text2 = '<?php print js_escape($lang['control_center_4626']) ?>';
					var submitBtn = '<?php print js_escape($lang['survey_200']) ?>';
					if (type == 'extend expiration' || type == 'set expiration') {
						text2 += '<div style="margin-top:10px;color:#A00000;"><?php print js_escape($lang['rights_345']." ".RCView::b(self::getUserSponsorSetExpireDays()." ".$lang['scheduling_25'])) ?></div>';
					}
				}
				simpleDialog('<b>'+numSelected+' <?php print js_escape($lang['control_center_4625']) ?> <u style="color:#008000;">'+typeLabel+'</u><?php print js_escape($lang['period']) ?></b> '
					+ text2
					+'<br><br><b><?php print js_escape($lang['extres_28']) ?> ('+applUsers.length+')<?php print js_escape($lang['colon']) ?></b><div style="font-size:11px;color:#008000;"> &bull; '+applUsers.join('<br> &bull; ')+'</div>',
					dlgTitle,null,480,null,
					'<?php print js_escape($lang['global_53']) ?>',"submitSponsorFormDo('"+type+"')",submitBtn);
			}
			if ($('#expire-time').length) {
				$('#expire-time').datetimepicker({
					buttonText: 'Click to select a date', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
					hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
					showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
				});
			}
		}
		function submitSponsorFormDo(type) {
			if ($('#expire-time').length) {
				$('#sponsor_form').append('<input type="hidden" name="expire-time" value="'+$('#expire-time').val()+'">');
			}
			$('#sponsor_form').append('<input type="hidden" name="type" value="'+type+'">').submit();
		}
		</script>
		<style type="text/css">
		table.dataTable tbody th, table.dataTable tbody td { padding:3px 5px;font-size:11px; }
		table.dataTable thead th, table.dataTable thead td { padding:5px 7px;font-weight:bold;font-size:11px; }
		</style>
		<?php 
		if ($controlCenterView) { 
			?>
			<div class="clearfix" style="margin-bottom:10px;max-width:800px;">
				<div class="float-left" style="width:650px;">
					<?php echo RCView::span(array('style'=>'font-size:14px;font-weight:bold;'), 
								$lang['email_users_15'] . " " . $lang['leftparen'] . count($usersInfo) . $lang['rightparen'] . $lang['colon']) 
							 . RCView::br() . $lang['rights_348'] ?>
				</div>
				<div class="float-right" style="margin-top:25px;">
					<button class="btn btn-defaultrc btn-xs" style="font-size:13px;" onclick="downloadUserHistoryList();"><img src="<?php print APP_PATH_IMAGES ?>xls.gif"> <span style="vertical-align:middle;color:green;"><?php echo $lang['random_19'] ?></span></button>
				</div>
			</div>
			<style type="text/css">
			#pagecontainer { max-width: 1500px; }
			</style>
			<?php
		}
		// Output all HTML
		print $h;
	}
	
	// Return array of user info based on criteria passed in query string on Browse Users page
	public static function getUserInfoByCriteria()
	{
		global $autologout_timer;
		$usersInfo = array();
		// determine if we are narrowing our search by latest activity within a given time frame.
		// this is delineated by the d $_REQUEST variable
		$queryAddendum = '';
		if (isset($_GET['d']))
		{
			// do a sanity check on the variables to make sure everything is kosher and no URL hacking is going on
			if (is_numeric($_GET['d'])) {
				// Active in...
			   $queryAddendum = " and user_lastactivity is not null and user_lastactivity >= '".date('Y-m-d H:i:s',time()-(86400*$_GET['d']))."'";
			} elseif (strpos($_GET['d'], "NA-") !== false) {
				// Not active in...
				list ($nothing, $notactive_days) = explode("-", $_GET['d'], 2);
				if (!is_numeric($notactive_days)) {
					$queryAddendum = '';
				} else {
					$queryAddendum = " and (user_lastactivity < '".date('Y-m-d H:i:s',time()-(86400*$notactive_days))."' or user_lastactivity is null)";
				}
			} elseif ($_GET['d'] == 'T' || $_GET['d'] == 'NT') {
				// Table-based users only OR LDAP users only
				$subQueryAddendum = "select username from redcap_auth";
				if ($_GET['d'] == 'T') {
					$queryAddendum = " and username in (" . pre_query($subQueryAddendum) . ")";
				} else {
					$queryAddendum = " and username not in (" . pre_query($subQueryAddendum) . ")";
				}
			} elseif ($_GET['d'] == 'I') {
				// Suspended
				$queryAddendum = " and user_suspended_time IS NOT NULL";
			} elseif ($_GET['d'] == 'NI') {
				// Non-suspended
				$queryAddendum = " and user_suspended_time IS NULL";
			} elseif ($_GET['d'] == 'E') {
				// Has expiration set
				$queryAddendum = " and user_expiration IS NOT NULL";
			} elseif ($_GET['d'] == 'NE') {
				// Does not have expiration set
				$queryAddendum = " and user_expiration IS NULL";
			} elseif ($_GET['d'] == 'CL' || $_GET['d'] == 'NCL') {
				// Currently logged in or not
				$logoutWindow = date("Y-m-d H:i:s", mktime(date("H"),date("i")-$autologout_timer,date("s"),date("m"),date("d"),date("Y")));
				$subQueryAddendum = "select distinct v.user from redcap_sessions s, redcap_log_view v
						where v.user != '[survey respondent]' and v.session_id = s.session_id and v.ts >= '$logoutWindow'";
				if ($_GET['d'] == 'CL') {
					$queryAddendum = " and username in (" . pre_query($subQueryAddendum) . ")";
				} else {
					$queryAddendum = " and username not in (" . pre_query($subQueryAddendum) . ")";
				}
			} elseif (strpos($_GET['d'], "NL-") !== false) {
				// Not logged in within...
				list ($nothing, $notloggedin_days) = explode("-", $_GET['d'], 2);
				if (!is_numeric($notloggedin_days)) {
					$queryAddendum = '';
				} else {
					$queryAddendum = " and (user_lastlogin is null or user_lastlogin < '".date('Y-m-d H:i:s',time()-(86400*$notloggedin_days))."')";
				}
			} elseif (strpos($_GET['d'], "L-") !== false) {
				// Logged in within...
				list ($nothing, $loggedin_days) = explode("-", $_GET['d'], 2);
				if ($loggedin_days == '0') {
					$queryAddendum = ' and user_lastlogin is null';
				} elseif (!is_numeric($loggedin_days)) {
					$queryAddendum = '';
				} else {
					$queryAddendum = " and user_lastlogin is not null and user_lastlogin > '".date('Y-m-d H:i:s',time()-(86400*$loggedin_days))."'";
				}
			} else {
				$queryAddendum = '';
			}
		}

		// Set SQL for search term
		if (!isset($_GET['search_term'])) $_GET['search_term'] = '';
		if (!isset($_GET['search_attr'])) $_GET['search_attr'] = '';
		$querySearch = '';
		$allowableSearchAttr = array('', 'username', 'user_firstname', 'user_lastname', 'user_email', 'user_inst_id', 'user_sponsor', 'user_comments');
		if ($_GET['search_term'] != '')
		{
			$_GET['search_term'] = rawurldecode(urldecode($_GET['search_term']));
			if (in_array($_GET['search_attr'], $allowableSearchAttr)) {
				// Search ALL valid attributes
				if ($_GET['search_attr'] == '') {
					$querySearch = " and (username like '%" . db_escape($_GET['search_term']) . "%'
									  or  user_firstname like '%" . db_escape($_GET['search_term']) . "%'
									  or  user_lastname like '%" . db_escape($_GET['search_term']) . "%'
									  or  user_email like '%" . db_escape($_GET['search_term']) . "%'
									  or  user_inst_id like '%" . db_escape($_GET['search_term']) . "%'
									  or  user_sponsor like '%" . db_escape($_GET['search_term']) . "%'
									  or  user_comments like '%" . db_escape($_GET['search_term']) . "%'
									)";
				}
				// Search single attribute
				else {
					$querySearch = " and " . db_escape($_GET['search_attr']) . " like '%" . db_escape($_GET['search_term']) . "%'";
				}
			} else {
				$_GET['search_attr'] = '';
			}
		}

		// Retrieve list of users
		$dbQuery = "select * from redcap_user_information 
					where username != '' $queryAddendum $querySearch order by trim(username)";
		$q = db_query($dbQuery);
		while ($row = db_fetch_assoc($q))
		{
			$ui_id = $row['ui_id'];
			unset($row['ui_id']);
			$row['username'] = strtolower(trim($row['username']));
			$usersInfo[$ui_id] = $row;
		}
		// Return user info array
		return $usersInfo;
	}
	
	// Render the setup instructions for Google Authenticator 2-factor QR code setup
	public static function renderTwoFactorInstructionsAuthenticator($username, $emailEmbed=false, $displayOpenInNewWindow=true, $displayQRcode=true)
	{
		global $lang;
		// Get user info
		$user_info = User::getUserInfo($username);
		// Get REDCap server's domain name
		$parse = parse_url(APP_PATH_WEBROOT_FULL);
		$redcap_server_hostname = $parse['host'];
		// If missing user's two factor auth secret hash, then generate it
		if ($user_info['two_factor_auth_secret'] == "") {
			$user_info['two_factor_auth_secret'] = Authentication::createTwoFactorSecret($username);
		}
		// Generate string to be converted into QR code to enable 2FA in an app
		$otpauth = 'otpauth://totp/' . urlencode($username . "@" . $redcap_server_hostname)
				 . '?secret=' .  urlencode($user_info['two_factor_auth_secret']) . '&issuer=REDCap';
		// Dialog content
		return	RCView::div(array('id'=>'two_factor_totp_setup', 'class'=>'simpleDialog', 'title'=>$lang['system_config_445'], 'style'=>'font-size:13px;'),
					// Instructions
					RCView::div(array('style'=>'font-size:14px;'),
						$lang['system_config_446'] . RCView::br() . RCView::br() .
						// Step 1
						RCView::span(array('style'=>'color:#C00000;font-weight:bold;font-size:14px;'), $lang['system_config_448']) .
						// Google Authenticator
						RCView::div(array('style'=>'margin:3px 0 15px 22px;'),
							$lang['system_config_449'] . " " .
							RCView::a(array('href'=>'https://itunes.apple.com/us/app/google-authenticator/id388497605', 'target'=>'_blank', 'style'=>'margin:0 3px;text-decoration:underline;font-size:14px;'),
								"Apple App Store"
							) . " " .
							$lang['global_47'] . " " .
							RCView::a(array('href'=>'https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2', 'target'=>'_blank', 'style'=>'margin:0 3px;text-decoration:underline;font-size:14px;'),
								"Google Play Store"
							) . " " .
							$lang['system_config_447']
						) .
						// Step 2
						RCView::span(array('style'=>'color:#C00000;font-weight:bold;font-size:14px;'), $lang['system_config_373']) . 
						(!$displayOpenInNewWindow ? '' :
							($displayQRcode ? '' : RCView::br() . RCView::br()) .
							RCView::a(array('href'=>APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/Authentication/generate_qrcode.php?value=".urlencode($otpauth), 
								'target'=>'_blank', 'style'=>'text-decoration:underline;margin-left:15px;'), $lang['system_config_588'])
						) . 
						RCView::br() .
						// Display QR code
						(!$displayQRcode ? '' :
							RCView::div(array('style'=>'margin-left:10px;'),
								"<img src='".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/Authentication/generate_qrcode.php?value=".urlencode($otpauth)."'>"
							)
						) .
						// Manual method
						($emailEmbed ? '' :
							RCView::div(array('style'=>'line-height: 14px;font-size:12px;margin:2px 0 12px 25px;color:#800000;border:1px solid #ddd;padding:3px;'),
								$lang['system_config_514'] . RCView::br() . RCView::br() .
								$lang['system_config_515'] . " " . RCView::b($username . "@" . $redcap_server_hostname) . RCView::br() .
								$lang['system_config_516'] . " " . RCView::b($user_info['two_factor_auth_secret'])
							)
						) .
						// Step 3
						RCView::span(array('style'=>'color:#C00000;font-weight:bold;font-size:14px;'), $lang['system_config_451']) .
						// Final words
						RCView::div(array('style'=>'margin:3px 0 0 22px;'),
							$lang['system_config_450']
						)
					)
				);
	}
	
	// Determine if a user is a super user by username
	public static function isSuperUser($username)
	{
		$user_info = self::getUserInfo($username);
		return ($user_info['super_user'] == '1');
	}
}
