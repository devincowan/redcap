<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Get user info
$user_info = User::getUserInfo(USERID);

// Generate verification code
$ga = new GoogleAuthenticator();
$code = $ga->getCode($user_info['two_factor_auth_secret']);

// Set default response
$response = '0';

// Send verification code via SMS
if ($two_factor_auth_twilio_enabled && $_POST['type'] == 'sms' && $user_info['user_phone_sms'] != '') {
	// Init Twilio
	TwilioRC::init();
	// Instantiate a new Twilio Rest Client
	$twilioClient = new Services_Twilio($two_factor_auth_twilio_account_sid, $two_factor_auth_twilio_auth_token);
	// Send SMS to their phone number
	$success = TwilioRC::sendSMS($lang['system_config_456'] . " " . $code . $lang['system_config_458'], $user_info['user_phone_sms'], $twilioClient, $two_factor_auth_twilio_from_number, false);
	if ($success === true) {
		// Add to table in case they reply to SMS rather than entering the code
		$tf_id = Authentication::addTwoFactorCodeForPhoneNumber($user_info['user_phone_sms']);
		// Return the tf_id key
		$response = ($tf_id === false) ? '0' : $tf_id;
	}
}


// Make voice call to phone number
elseif ($two_factor_auth_twilio_enabled && $_POST['type'] == 'voice' && $user_info['user_phone'] != '') {
	// Init Twilio
	TwilioRC::init();
	// Instantiate a new Twilio Rest Client
	$twilioClient = new Services_Twilio($two_factor_auth_twilio_account_sid, $two_factor_auth_twilio_auth_token);
	// Set the survey URL that Twilio will make the request to
	$question_url = APP_PATH_SURVEY_FULL . "?" . Authentication::TWILIO_2FA_PHONECALL_FLAG . "=1";
	// Call the phone number
	try {
		// If calling a number with an extension, add to call_params
		$call_params = array();
		$number_to_call = $user_info['user_phone'];
		if (strpos($number_to_call, ",") !== false) {
			list ($number_to_call, $number_extension) = explode(",", $number_to_call, 2);
			// Add extention to call_params
			$call_params['SendDigits'] = $number_extension;
		}
		// Make call
		$call = $twilioClient->account->calls->create(TwilioRC::formatNumber($two_factor_auth_twilio_from_number), TwilioRC::formatNumber($number_to_call), $question_url, $call_params);
		// Add to table in case they reply to SMS rather than entering the code
		$tf_id = Authentication::addTwoFactorCodeForPhoneNumber($number_to_call);
		// Return the tf_id key
		$response = ($tf_id === false) ? '0' : $tf_id;
	} catch (Exception $e) {
		// If return code 21215, then need to set up int'l calling for the Twilio account
		print 	RCView::div(array('class'=>'red'),
					RCView::b($lang['global_124']) . " " . $e->getMessage()
				);
		exit;
	}
}


// Send verification code via email
elseif ($_POST['type'] == 'email') {
	// Set email body
	$email = $lang['system_config_366'] . " $code";
	// Get code expiration time (a user-level feature with 2-min default)
	$code_expiration = (isset($user_info['two_factor_auth_code_expiration']) && is_numeric($user_info['two_factor_auth_code_expiration']))
					   ? $user_info['two_factor_auth_code_expiration'] : 2;
	// Add note that code is only good for 2 minutes (or other time set at user-level)
	$email .= RCView::br().RCView::br().$lang['system_config_524']." $code_expiration ".$lang['system_config_525'];
	// If Twilio SMS/Voice 2FA is enabled and user does NOT have a phone number listed for them, then tell them about that option
	if ($two_factor_auth_twilio_enabled && $user_info['user_phone'] == '' && $user_info['user_phone_sms'] == '') {
		$email .= RCView::br().RCView::br()."------------------------".RCView::br().$lang['system_config_483'];
	}
	// Send email to user's email (the from address will be the project_contact_email
	$success = 	REDCap::email($user_info['user_email'], $project_contact_email, $lang['system_config_457'], $email);
	if ($success) $response = '1';
}

// Response
print $response;
