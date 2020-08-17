<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Set default response
$response = '0';

// Change setting
if ($two_factor_auth_enabled && $two_factor_auth_twilio_enabled && isset($_POST['two_factor_auth_twilio_prompt_phone'])
	&& ($_POST['two_factor_auth_twilio_prompt_phone'] == '1' || $_POST['two_factor_auth_twilio_prompt_phone'] == '0'))
{
	// Set setting in user information table
	$sql = "update redcap_user_information set two_factor_auth_twilio_prompt_phone = '".db_escape($_POST['two_factor_auth_twilio_prompt_phone'])."'
			where username = '".db_escape(USERID)."'";
	$response = (db_query($sql) ? '1' : '0');
}

// Response
print $response;
