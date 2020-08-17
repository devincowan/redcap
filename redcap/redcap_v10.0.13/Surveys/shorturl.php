<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

if (Survey::checkSurveyProject($_GET['survey_id']) && isset($_GET['hash']))
{
	// Retrieve shortened URL from URL shortener service
	if ($GLOBALS['enable_url_shortener_redcap']) {
		$shorturl_status = getREDCapShortUrl(APP_PATH_SURVEY_FULL . '?s=' . $_GET['hash']);
		if (isset($shorturl_status['error'])) exit($shorturl_status['error']);
		if (!isset($shorturl_status['url_short'])) exit("0");
		exit($shorturl_status['url_short']);
	} else {
		$shorturl = Survey::getShortUrl(APP_PATH_SURVEY_FULL . '?s=' . $_GET['hash']);
		// Ensure that we received a link in the expected format
		if ($shorturl !== false) exit($shorturl);
	}
}

// If failed
exit("0");
