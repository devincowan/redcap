<?php

class API
{
	// API: Output an error message for an API request in correct return format
	public static function outputApiErrorMsg($error_msg)
	{
		global $returnFormat; // Need to set this as global for RestUtility
		// Render message in format set in API request
		$format = "xml";
		$returnFormat = "xml";
		// First, get the format specified
		$format = $_POST['format'];
		switch ($format)
		{
			case 'json':
				break;
			case 'csv':
				break;
			default:
				$format = "xml";
		}
		// Second, if returnFormat is specified, it'll override format
		$tempFormat = ($_POST['returnFormat'] != "") ? strtolower($_POST['returnFormat']) : strtolower($format);
		switch ($tempFormat)
		{
			case 'json':
				$returnFormat = "json";
				break;
			case 'csv':
				$returnFormat = "csv";
				break;
			default:
				$returnFormat = "xml";
		}
		// Output offline message in specified format
		exit(RestUtility::sendResponse(400, trim(strip_tags($error_msg))));
	}
}
