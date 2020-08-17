<?php

include 'config.php';

$data = array(
	'project_title' => 'New Project Title via API',
	'is_longitudinal' => 0,
	'surveys_enabled' => 1
);

$params = array(
    'token' => $GLOBALS['api_token'],
    'content' => 'project_settings',
    'format' => 'json',
    'data' => json_encode($data)
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $GLOBALS['api_url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
$output = curl_exec($ch);
print $output;
curl_close($ch);
