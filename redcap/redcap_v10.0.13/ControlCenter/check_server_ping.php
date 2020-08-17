<?php

// Disable authentication IF "noauthkey" is passed in the query string with the correct value (MD5 hash of $salt and today's date+hour)
if (isset($_POST['noauthkey'])) {
	// Get $salt from database.php
	require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'database.php';
	// Validate $salt
	if (!isset($salt)) exit;
	// Validate "noauthkey"
	if ($_POST['noauthkey'] == md5($salt . date('YmdH'))) {
		// Disable authentication
		define("NOAUTH", true);
	} else {
		// Failed, so stop here
		exit;
	}
}

// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Manually set an allowlist of allowed URLs
$restricted_ping_urls = "
https://www.google.com/recaptcha/api/siteverify
https://redcap.link
https://is.gd
http://api.bit.ly
https://api.twilio.com
https://redcap.vanderbilt.edu/consortium/ping.php
$promis_api_base_url
$bioportal_api_url
";

// Set page to call on consortium server or specific URL
$page_to_ping = (isset($_POST['url']) && $_POST['url'] != '') ? $_POST['url'] : CONSORTIUM_WEBSITE . 'ping.php';
if (!isURL($page_to_ping)) exit;

// Validate the URL against the allowlist
$restricted_ping_urls = explode("\n", trim($restricted_ping_urls));
foreach ($restricted_ping_urls as &$thisUrl) $thisUrl = trim($thisUrl);
if (!in_array($page_to_ping, $restricted_ping_urls)) {
	exit("This URL is not on the restricted list");
}

// Use HTTP Post method
if (isset($_POST['type']) && $_POST['type'] == 'post')
{
	print http_post($page_to_ping);
}

// Use HTTP GET method
else
{
	print http_get($page_to_ping);
}