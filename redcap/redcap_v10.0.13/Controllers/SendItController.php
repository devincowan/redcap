<?php

class SendItController extends Controller
{
	// Render the Send-It upload page
	public function upload()
	{
		SendIt::renderUploadPage();
	}
	
	// Render the Send-It download page
	public function download()
	{
		// Redirect to SendIt download route
		if (isset($_GET['route']) && $_GET['route'] == 'SendItController:download') {
			$key = trim(substr(trim(str_replace("route=SendItController:download&", "", $_SERVER['QUERY_STRING'])), 0 , 25));
			redirect(APP_PATH_WEBROOT . "SendIt/download.php?$key");
		} else {
			SendIt::renderDownloadPage();
		}
	}
}