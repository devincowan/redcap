<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Set default response
$response = '0';

// Check if user responded to SMS code
if ($isAjax && $two_factor_auth_twilio_enabled && isset($_POST['tf_id']))
{
	// Check if verified in the redcap_two_factor_response table
	$isVerified = Authentication::checkVerifiedTwoFactorCodeByTwcId($_POST['tf_id']);
	if ($isVerified) {
		$response = '1';
		// Set session variable to denote that user has performed two factor auth
		Authentication::twoFactorLoginSuccess(strToUpper($_POST['twoFactorMethod']));
		// If user selected that this computer can be trusted, then set cookie
		Authentication::twoFactorSetTrustCookie($_POST['two_factor_auth_trust']);
	}
}

// Response
print $response;