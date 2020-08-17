<?php


use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

// Set flag to know we are in the EHR portal
define("EHR", true);
// Disable authentication unless receiving REDCap login form submission
if (!isset($_POST['redcap_login_a38us_09i85'])) define("NOAUTH", true);
// Config for project-level or non-project pages
if (isset($_GET['pid'])) {
	require_once dirname(__FILE__) . "/Config/init_project.php";
} else {
	require_once dirname(__FILE__) . "/Config/init_global.php";
}
// Instantiate FHIR EHR class
try {
	$FhirEhr = new FhirEhr();
	
	$FhirEhr->listenForUserActions();

	$FhirEhr->launchFromEhr();
} catch (Exception $e) {
	// Display any errors
	$HtmlPage = new HtmlPage();
	$HtmlPage->PrintHeaderExt();
	FhirLauncher::cleanup();
	print RCView::b($lang['global_01'] . $lang['colon']) . " " . $e->getMessage();
	$HtmlPage->PrintFooterExt();
}