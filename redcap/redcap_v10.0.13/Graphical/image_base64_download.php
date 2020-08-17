<?php


// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
}

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (isset($_POST['chart_download']) && !empty($_POST['chart_download'])) {
	$str = $_POST['chart_download'];
	// Get mime type and filename
	$mime_type = substr($str, 5, strpos($str, ';')-5);
	if (isset($_POST['image_name'])) {
		$filename = $_POST['image_name'];
	} else {
		$filename = "image." . str_replace("image/", "", $mime_type);
	}
	$commaPos = strpos($str, ',');
	if ($commaPos !== false) {
		$str = substr($str, $commaPos+1);
	}
	// Santize and validate the base64 image data
	$str = RCView::escape($str);
	if (!Files::check_base64_image($str)) exit("ERROR!");
	// Headers
	header('Content-Type: '.$mime_type.'; name="'.$filename.'"');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	// Output content
	print base64_decode($str);
	// Logging
	Logging::logEvent("","redcap_data","manage",$_GET['field'],"field_name = '".db_escape($_GET['field'])."'","Download graphical chart for field");
} else {
	print "ERROR!";
}