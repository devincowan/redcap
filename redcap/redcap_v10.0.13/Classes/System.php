<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

/**
 * System Class
 * Contains methods used for general operations in REDCap
 */
class System
{
	// Set the lowest version of PHP with which REDCap is compatible
	const minimum_php_version_required = '5.5.0';
	// Set a list of the recommended versions of PHP
	public static $recommendedPhpVersions = array("7.2", "7.3", "7.4");
	// Hard-coded "Confidential" text
	const confidential = 'Confidential';
	const powered_by_redcap = 'Powered by REDCap';
	
	// Default keywords used by the Check For Identifiers module
	const identifier_keywords_default = "name, street, address, city, county, precinct, zip, postal, date, phone, fax, mail, ssn, social security, mrn, dob, dod, medical, record, id, age";
	
	// Disable GZIP: List of specific pages where a file is downloaded (rather than a webpage displayed) where GZIP should be disabled
	public static $fileDownloadPages = array("DataExport/data_export_csv.php", "DataExport/sas_pathway_mapper.php", "DataExport/spss_pathway_mapper.php",
											"DataImportController:downloadTemplate", "Design/data_dictionary_download.php", "Design/data_dictionary_demo_download.php",
											"FileRepository/file_download.php", "Locking/esign_locking_management.php", "Logging/csv_export.php",
											"Randomization/download_allocation_file.php", "Randomization/download_allocation_file_template.php",
											"Reports/report_export.php", "SendIt/download.php", "Surveys/participant_export.php", "DataEntry/file_download.php",
											"PdfController:index", "ControlCenter/pub_matching_ajax.php", "ControlCenter/create_user_bulk.php",
											"DataQuality/data_resolution_file_download.php", "DataQuality/field_comment_log_export.php",
											"DataExport/file_export_zip.php", "Design/zip_instrument_download.php", "ControlCenter/check.php",
                                            "AlertsController:downloadAttachment",
											// The pages below aren't used for file downloads, but we need to disable GZIP on them anyway
											// (often because their output is so large that it uses too much memory to keep in buffer).
											"DataExport/index.php"
										  );
	
	// Disable error reporting
	public static function setErrorReporting()
	{
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('log_errors', 1);
		error_reporting(0); 
		// Enable error reporting always for REDCap developers
		if (isDev() || isset($_GET['__display_errors']) || isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME'])) {
		    error_reporting(E_ALL); // For __display_errors=1, it will be reverted if current user is not an authenticated REDCap admin
		}
		// To enable all error reporting, uncomment the next line.
		// error_reporting(E_ALL);
	}

	// Disable error reporting for all EXCEPT for authenticated REDCap admins (specifically when using __display_errors=1 in the query string)
	public static function disableErrorReportingForNonAdmins()
	{
		if ((!defined("SUPER_USER") || (defined("SUPER_USER") && !SUPER_USER)) && isset($_GET['__display_errors'])) {
			error_reporting(0);
		}
	}
	
	// Initialize any general request
	public static function init()
	{
		require_once(dirname(__DIR__)."/vendor/autoload.php");
		// Set flag to know that we've already run this method so that it doesn't get run again
		if (defined("REDCAP_INIT")) return;
		define("REDCAP_INIT", true);
		// Disable error reporting
		self::setErrorReporting();
		// Prevent caching
		self::setCacheControl();
		// Set value used when reading uploaded CSV files
		ini_set('auto_detect_line_endings', true);
		// Set mbstring substitute_character to none
		ini_set('mbstring.substitute_character', 'none');
		// Make sure the character set is UTF-8
		ini_set('default_charset', 'UTF-8');
		// Set API key for Google Maps API v3
		defined("GOOGLE_MAP_KEY") or define("GOOGLE_MAP_KEY", "AIzaSyCN9Ih8gzAxfPmvijTP8HsE0PAKU8X1Nt0");
		// Set whether or not Multibyte String extension is installed in PHP
		define("MBSTRING_ENABLED", function_exists('mb_detect_encoding'));
		// Define DIRECTORY_SEPARATOR as DS for less typing
		defined("DS") or define("DS", DIRECTORY_SEPARATOR);
		// Add constant if doesn't exists (it only exists in PHP 5.3+)
		if (!defined('ENT_IGNORE')) define('ENT_IGNORE', 0);
		// Add constant if doesn't exists (it only exists in PHP 5.4+)
		if (!defined('ENT_SUBSTITUTE')) define('ENT_SUBSTITUTE', ENT_IGNORE);
		// Get current date/time to use for all database queries (rather than using MySQL's clock with now())
		define("SCRIPT_START_TIME", microtime(true));
		defined("NOW") 	 or define("NOW", date('Y-m-d H:i:s'));
		defined("TODAY") or define("TODAY", date('Y-m-d'));
		defined("today") or define("today", TODAY); // The lower-case version of the TODAY constant allows for use in Data Quality rules (e.g., datediff)
		defined("now")   or define("now", NOW); 	// The lower-case version of the NOW constant allows for use in Data Quality rules (e.g., datediff)
		// Set class autoload function
		$GLOBALS['rc_autoload_function'] = 'System::classAutoloader';
		spl_autoload_register($GLOBALS['rc_autoload_function']);
		// Call Composer autoload file
		if (version_compare(PHP_VERSION, self::getMinPhpVersion(), '>=')) {
			require dirname(dirname(__FILE__)) . DS . "Libraries" . DS . "vendor" . DS . "autoload.php";
		}
		// Make sure dot is added to include_path in case it is missing. Also add path to Classes/PEAR inside REDCap.
		set_include_path('.' . PATH_SEPARATOR .
						dirname(dirname(__FILE__)) . DS . 'Libraries' . DS . 'PEAR' . DS . PATH_SEPARATOR .
						get_include_path());
		// Increase memory limit in case needed for intensive processing
		self::increaseMemory(1024);
		// Increase initial server value to account for a lot of processing
		self::increaseMaxExecTime(1200);
		// Set the HTML tags that are allowed for use in user-defined labels/text (e.g., field labels, survey instructions)
		define('ALLOWED_TAGS', '<ol><ul><li><label><pre><p><a><br><center><font><b><i><u><h6><h5><h4><h3><h2><h1><hr><table><tbody><tr><th><td><thead><tfoot><img><span><div><em><strong><acronym><sub><sup><map><area>');
		// Set error handler
		set_error_handler('System::REDCapErrorHandler');
		// Register all functions to be run at shutdown of script
		register_shutdown_function('System::beginShutdown');
		register_shutdown_function('Logging::updateLogViewRequestTime');
		register_shutdown_function('Session::writeClose');
		register_shutdown_function('System::fatalErrorShutdownHandler');
		// Set session handlers and session cookie params
        Session::preInit();
		// Enable output to buffer
		ob_start();
		// Determine and set the client's OS, browser, and if a mobile device
		self::detectClientSpecs();
		// Make initial database connection
		db_connect();
		// Clean $_GET and $_POST to prevent XSS and SQL injection
		self::cleanGetPost();
		// Pull values from redcap_config table and set as global variables
		self::setConfigVals();
		// If the server has been flagged as a dev/test/staging server, then enable full 
		// error reporting to help with developer troubleshooting
		if (isset($GLOBALS['is_development_server']) && $GLOBALS['is_development_server'] == '1') {
			error_reporting(E_ALL);
		}
		// Set Access-Control-Allow-Origin header
		self::setCrossDomainHttpAccessControl();
		// Set X-Frame-Options header
		self::setClickJackingControl();
		// Remove X-Powered-By header
		self::removeXPoweredByHeader();
		// Check content length max size for POST requests (e.g., if uploading massive files)
		self::checkUploadFileContentLength();
		// Prevent users from accessing Views directly in their web browser (for security reasons)
		self::preventDirectViewAccess();
	}

	// Remove the X-Powered-By header so that it doesn't reveal the PHP version, which is the default behavior
	private static function removeXPoweredByHeader()
	{
		header_remove("X-Powered-By");
	}
	
	// Ensure we don't overload max_input_vars
	private static function checkPostParamCount()
	{
		global $lang;
		if (isset($_POST) && count($_POST, COUNT_RECURSIVE) > ini_get('max_input_vars')) 
		{
			$max_input_vars_msg = $lang['system_config_605'] . " " . ini_get('max_input_vars') . $lang['period'];
			if (API) {
				API::outputApiErrorMsg($max_input_vars_msg);
			} else {
				exit($lang['global_01'].$lang['colon']." ".$max_input_vars_msg);
			}
		}
	}
	
	// Creates response header with X number of random characters. This helps mitigate hackers attempting a BREACH attack.
	// ONLY perform this if GZIP is enabled (because BREACH is only effective when HTTP compression is enabled).
	private static function addHeaderRandomText()
	{
		// If Gzip enabled, then output it
		if (defined("GZIP_ENABLED") && GZIP_ENABLED) {
			// Set max number of characters
			$maxChars = 64;
			// Get random number between 1 and $maxChars
			$numChars = mt_rand(1, $maxChars);
			// Build random text to place inside header
			$randomText = generateRandomHash($numChars);
			// Set header
			header("REDCap-Random-Text: $randomText");
		}
	}
	
	// Prevent users from accessing Views directly in their web browser (for security reasons)
	private static function preventDirectViewAccess()
	{
		// Are we access a view directly? If not, then return.
		if (strpos($_SERVER['PHP_SELF'], "/redcap_v" . REDCAP_VERSION . "/Views/") === false) return;
		// We are, so redirect to Views/index.html to display error message.
		include dirname(dirname(__FILE__)) . "/Views/index.html";		
		exit;
	}
	
	// Set cache control to prevent caching
	public static function setCacheControl()
	{
		header("Expires: 0");
		header("Cache-control: no-store, no-cache, must-revalidate");
		header("Pragma: no-cache");
		// Also set some security-related headers
        header("X-XSS-Protection: 1; mode=block");
        header('X-Content-Type-Options: nosniff');
	}
	
	// Determine if the web server running PHP is any type of Windows OS (boolean)
	public static function isWindowsServer()
	{
		return ((defined('PHP_OS') && strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') || (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN'));
	}
	
	// Find real IP address of user
	public static function clientIpAddress() 
	{
		$ip = (empty($_SERVER['HTTP_CLIENT_IP']) ? (empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_X_FORWARDED_FOR']) : $_SERVER['HTTP_CLIENT_IP']);
		$ip = filter_var($ip, FILTER_VALIDATE_IP);
		return ($ip === false ? '' : $ip);
	}

	// Increase PHP web server max_execution_time value in seconds, if lower (if higher, then leave as is)
	public static function increaseMaxExecTime($seconds)
	{	
		if (is_numeric($seconds) && ini_get('max_execution_time') < $seconds) {
			ini_set('max_execution_time', $seconds);
			@set_time_limit($seconds);
		}
	}

	// Increase PHP web server memory to a given value in MB, if lower (if higher, then leave as is)
	public static function increaseMemory($mb)
	{
		if (is_numeric($mb) && self::getMemoryLimit() < $mb) {
			ini_set('memory_limit', $mb . 'M');
		}
	}
	

	// Return the PHP web server memory limit in MB
	public static function getMemoryLimit()
	{
		$unitMultiplier = stripos(ini_get('memory_limit'), 'g') ? 1024 : 1;
		return preg_replace("/[^0-9]/", "", ini_get('memory_limit')) * $unitMultiplier;
	}

	// Set Access-Control-Allow-Origin header
	public static function setCrossDomainHttpAccessControl()
	{
		global $cross_domain_access_control;
		if (!isset($cross_domain_access_control) || trim($cross_domain_access_control) == '') {
			// Allow all origins
			header("Access-Control-Allow-Origin: *");
		} else {
			// Parse the domains and set each as allowed
			$cross_domain_access_control = str_replace(array("\r\n","\r"), array("\n","\n"), trim($cross_domain_access_control));
			$allowed_domains = explode("\n", $cross_domain_access_control);
			// Add self
			list ($server_name, $port, $ssl, $page_full) = getServerNamePortSSL();
			$allowed_domains[] = "http://" . $server_name;
			$allowed_domains[] = "https://" . $server_name;
			// Is HTTP_ORIGIN in our allowed list, including REDCap itself?
			if ($_SERVER['HTTP_ORIGIN'] != null && in_array($_SERVER['HTTP_ORIGIN'], $allowed_domains)) {
				header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
		}
	}
	
	// Set X-Frame-Options header
	public static function setClickJackingControl()
	{
		global $clickjacking_prevention;
		
		$fhir_cookie = FhirLauncher::getCookieData();
		// If we are performing an EHR Launch for CDP, then ignore
        if ($fhir_cookie!=false || basename($_SERVER['PHP_SELF']) == 'ehr.php') {
            return;
		}
        // If enabled, then set header
		if ($clickjacking_prevention == '1') {
			header('X-Frame-Options: SAMEORIGIN');
		}
	}

	/**
	 * AUTOLOAD CLASSES
	 * Function will autoload the proper class file when the class is called
	 */
	public static function classAutoloader($className)
	{		
		// Remove namespace if prepended to class name
		$classNameArray = explode("\\", $className);
		$className = array_pop($classNameArray);
		// Main REDCap version directory
		$main_dir = dirname(dirname(__FILE__));
		// First, try Classes directory
		$classPath = $main_dir . DS . "Classes" . DS . $className . ".php";
		if (file_exists($classPath) && include_once $classPath) return;
		// Now try Controllers directory
		$classPath = $main_dir . DS . "Controllers" . DS . $className . ".php";
		if (file_exists($classPath) && include_once $classPath) return;
		// Now try Libraries directory
		$classPath = $main_dir . DS . "Libraries" . DS . $className . ".php";
		if (file_exists($classPath) && include_once $classPath) return;
	}
	
	// Obtain values from redcap_config table
	public static function getConfigVals()
	{
		global $db;
		$vars = array();
		$q = db_query("select * from redcap_config");
		if (!$q && basename($_SERVER['PHP_SELF']) != 'install.php')
		{
			$installPage = (substr(basename(dirname($_SERVER['PHP_SELF'])), 0, 8) == 'redcap_v'
							|| substr(basename(dirname(dirname($_SERVER['PHP_SELF']))), 0, 8) == 'redcap_v')
							? '../install.php' : 'install.php';
			// If table doesn't exist or something is wrong with it, tell to re-install REDCap.
			print  "<div style='max-width:700px;'>ERROR: Could not find the \"redcap_config\" database table in the MySQL database named \"$db\"!<br><br>
					It looks like the REDCap database tables were not created during installation, which means that you may still
					need to complete the <a href='$installPage'>installation</a>. If you did complete the installation, then
					you may have accidentally created the REDCap database tables in the wrong MySQL database (if so, please check if
					they exist in the \"$db\" database).</div>";
			exit;
		}
		while ($row = db_fetch_assoc($q))
		{
			$vars[$row['field_name']] = $row['value'];
		}
		// If auto logout time is set to "0" (which means 'disabled'), then set to 1 day ("1440") as the upper limit.
		if ($vars['autologout_timer'] == '0')
		{
			$vars['autologout_timer'] = 1440;
		}
		// Return variables
		return $vars;
	}
	
	// Obtain values from redcap_config table and set as global variables
	public static function setConfigVals()
	{
		foreach (self::getConfigVals() as $field_name=>$value)
		{
			// Set field as global variable
			$GLOBALS[$field_name] = $value;
			// If using a proxy server, set variable as a constant
			if ($field_name == 'proxy_hostname') {
				define("PROXY_HOSTNAME", ($value == "" ? "" : trim($value)));
			} elseif ($field_name == 'proxy_username_password') {
				define("PROXY_USERNAME_PASSWORD", ($value == "" ? "" : trim($value)));
			}
		}
		// this *EXPERIMENTAL* code can cause *SYSTEM INSTABILITY* if set to true
		if (!array_key_exists('pub_matching_experimental', $GLOBALS)) {
			$GLOBALS['pub_matching_experimental'] = false;
		}
		// Force rApache to be disabled despite back-end value (service was retired in 5.12.0)
		if ($GLOBALS['enable_plotting'] == '1') $GLOBALS['enable_plotting'] = '2';
		// If we are automating everything (for demo purposes, etc.), then make sure certain
		// config settings are set to allow this (just in case not set manually)
		if (defined("AUTOMATE_ALL")) {
			$GLOBALS['superusers_only_create_project'] = '0';
			$GLOBALS['superusers_only_move_to_prod'] = '0';
		}
		// If using secondary MySQL user, and its password is stored in plain text (temporary), then encrypt it and replace it
		if ($GLOBALS['redcap_updates_password_encrypted'] == '0' && $GLOBALS['redcap_updates_password'] != '' && strpos($_SERVER['PHP_SELF'], "/ControlCenter/") !== false) 
		{
			$GLOBALS['redcap_updates_password'] = encrypt($GLOBALS['redcap_updates_password']);
			$GLOBALS['redcap_updates_password_encrypted'] = '1';
			updateConfig('redcap_updates_password_encrypted', $GLOBALS['redcap_updates_password_encrypted']);
			updateConfig('redcap_updates_password', $GLOBALS['redcap_updates_password']);
			// Reload the page so that this change is reflected
			redirect($_SERVER['REQUEST_URI']);
		}
		// Set the default redcap_log_event* table to be used (will be overwritten by project-specific value in a project context)
		$GLOBALS['log_event_table'] = 'redcap_log_event';
		// Set REDCap version as a constant
		define("REDCAP_VERSION", $GLOBALS['redcap_version']);		
	}

	// Make sure the PHP version is compatible (only run on Upgrade and Install pages)
	public static function checkMinPhpVersion()
	{
		global $redcap_version;
		// Skip this check when on the install page
		if (basename($_SERVER['PHP_SELF']) == "install.php") return;
		// Make sure the version folder for the current version exists (in case someone accidentally removed it after upgrading)
		$redcapSubDirs = getDirFiles(dirname(dirname(dirname(__FILE__))));
		if (!in_array("redcap_v$redcap_version", $redcapSubDirs)) {
			exit("<p style='margin:30px;width:700px;'>
				<b>ERROR: REDCAP DIRECTORY IS MISSING!</b><br>
				The directory for your current REDCap version (".dirname(dirname(dirname(__FILE__))).DS."redcap_v$redcap_version".DS.")
				cannot be found. It may have been mistakenly removed.
				REDCap version $redcap_version cannot operate without its corresponding version directory.
				Please restore the redcap_v$redcap_version directory on your web server. This may require
				re-downloading the REDCap upgrade zip package and obtaining the directory from the zip file.
				</p>");
		}
		// Get version number from directory and compare to db's REDCap version.
		// If different and we are NOT on the upgrade page, then return.
		if (basename(dirname(dirname(__FILE__))) != $redcap_version && basename($_SERVER['PHP_SELF']) != "upgrade.php"
			 && basename($_SERVER['PHP_SELF']) != "install.php") return;
		// Check PHP version based on REDCap version. If outdated, display error message and stop.
		if (version_compare(PHP_VERSION, self::getMinPhpVersion(), '<')) 
		{
			exit("<p style='margin:30px;width:750px;'>
				<b>ERROR: Current PHP version is not compatible with REDCap. Please upgrade to PHP ".System::getMinPhpVersion()." or higher.</b><br>
				You are currently running PHP ".PHP_VERSION." on your web server.
				REDCap ".REDCAP_VERSION." requires PHP ".System::getMinPhpVersion()." or higher. You cannot upgrade REDCap until PHP has first been upgraded.
				<a target='_blank' href='http://php.net/downloads.php'>Upgrade to PHP ".System::getMinPhpVersion()." or higher</a>
				</p>");
		}
	}

	// Get the minimum required PHP version that is supported by REDCap
	public static function getMinPhpVersion()
	{
		return self::minimum_php_version_required;
	}

	private static function sanitizeParameters(&$array)
	{
		foreach ($array as $key=>&$value)
		{
			if (is_array($value)) {
				self::sanitizeParameters($value);
			} else {
				if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
					$array[$key] = $value = stripslashes($value);
				}

				// Remove IE's CSS "style=x:expression(" (used for XSS attacks)
				$array[$key] = preg_replace("/(\s+)(style)(\s*)(=)(\s*)(x)(\s*)(:)(\s*)(e)([\/\*\*\/]*)(x)([\/\*\*\/]*)(p)([\/\*\*\/]*)(r)([\/\*\*\/]*)(e)([\/\*\*\/]*)(s)([\/\*\*\/]*)(s)([\/\*\*\/]*)(i)([\/\*\*\/]*)(o)([\/\*\*\/]*)(n)(\s*)(\()/i", ' (', $value);
			}
		}
	}

	// Clean $_GET and $_POST to prevent XSS and SQL injection
	public static function cleanGetPost()
	{
		// Fix vulnerabilities for $_SERVER values that could be spoofed
		if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']) 
			// Do not apply this for Google App Engine because it will not work with GAE's dev environment
			&& !isset($_SERVER['APPLICATION_ID']))
		{
			// Make sure we chop off end of URL if using something like .../index.php/database.php
			$_SERVER['PHP_SELF'] = substr($_SERVER['PHP_SELF'], 0, -1 * strlen($_SERVER['PATH_INFO']));
		}
		$_SERVER['PHP_SELF']     = str_replace("&amp;", "&", htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES));
		$_SERVER['QUERY_STRING']  = preg_replace("/=\s*javascript\s*:/i", "=", $_SERVER['QUERY_STRING']);
		$_SERVER['QUERY_STRING'] = str_replace("&amp;", "&", htmlspecialchars(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '', ENT_QUOTES));
		$_SERVER['REQUEST_URI']  = preg_replace("/=\s*javascript\s*:/i", "=", $_SERVER['REQUEST_URI']);
		$_SERVER['REQUEST_URI']  = str_replace("&amp;", "&", htmlspecialchars(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', ENT_QUOTES));
		// Santize $_GET array
		System::sanitizeParameters($_GET);

		// Santize $_POST array
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
		{
			foreach ($_POST as $key=>$value) {
				if (is_array($value)) {
					foreach ($value as $innerKey=>$innerValue) {
						$_POST[$key][$innerKey] = stripslashes($innerValue);
					}
				} else {
					$_POST[$key] = stripslashes($value);
				}
			}
		}
	}

	// Check content length max size for POST requests (e.g., if uploading massive files)
	public static function checkUploadFileContentLength()
	{
        global $lang;
		if (!($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['CONTENT_LENGTH']))) return;
		// Get server post_max_size. If ends with G instead of M, then convert to M format.
		$max_postsize = (ini_get('post_max_size') != "") ? ini_get('post_max_size') : '1';
		$max_postsize = preg_replace("/[^0-9]/", "", $max_postsize) * (stripos($max_postsize, 'g') ? 1024 : 1) * 1048576;
		if ($_SERVER['CONTENT_LENGTH'] > $max_postsize) 
		{
            $max_postsize_mb = round($max_postsize/1024/1024);
			print  "<br><br>ERROR: The page you just submitted has exceeded the REDCap server's maximum submission size ($max_postsize_mb MB). 
					The request cannnot be processed. If you just uploaded a file, this error may have resulted from the file 
					being too large in its file size. A file that large simply cannnot be processed by the server, unfortunately.";
			if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
				print "<br><br><a href='".htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES)."'>Return to previous page</a>";
			}
			exit;
		}
	}

	// Determine and set the client's OS, browser, and if a mobile device
	public static function detectClientSpecs()
	{
		// Detect if a mobile device (don't consider tablets mobile devices)
		$mobile_detect = new Mobile_Detect();
		$GLOBALS['isTablet'] = $mobile_detect->isTablet();
		$GLOBALS['isMobileDevice'] = (isset($_GET['isMobileDevice']) || (!isset($_GET['isMobileDevice']) && $mobile_detect->isMobile() && !$GLOBALS['isTablet']));
		// Detect if using iOS (or an iPad specifically)
		$GLOBALS['isIOS'] = ($mobile_detect->is('iOS'));
		$GLOBALS['isIpad'] = ($mobile_detect->is('iPad'));
		// Check if using Internet Explorer
		$GLOBALS['isIE'] = (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false));
		// Detect if the current request is an AJAX call (via $_SERVER['HTTP_X_REQUESTED_WITH'])
		$GLOBALS['isAjax'] = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
	}

	// Redirect user to home page from a project-level page
	public static function redirectHome()
	{
		redirect(((strlen(dirname(dirname($_SERVER['PHP_SELF']))) <= 1) ? "/" : dirname(dirname($_SERVER['PHP_SELF']))));
	}
	
	// Run all methods for non-project-level pages
	public static function initGlobalPage()
	{
		// Initialize REDCap
		self::init();
		// Define all PHP constants used throughout the application
		self::defineAppConstants();
		// Make sure the PHP version is compatible
		self::checkMinPhpVersion();
		// Enable GZIP compression for webpages (if Zlib extention is enabled).
		self::enableGzipCompression();
		// Create response header with X number of random characters
		self::addHeaderRandomText();
		// Check if the URL is pointing to the correct version of REDCap. If not, redirect to correct version.
		self::checkREDCapVersionRedirect();
		// Initialize External Modules library
		self::initExternalModules();
		// Language: Call the correct language file for global pages
		$GLOBALS['lang'] = Language::getLanguage($GLOBALS['language_global']);
		// Set pre-defined multiple choice options for Yes-No and True-False fields
		define("YN_ENUM", "1, {$GLOBALS['lang']['design_100']} \\n 0, {$GLOBALS['lang']['design_99']}");
		define("TF_ENUM", "1, {$GLOBALS['lang']['design_186']} \\n 0, {$GLOBALS['lang']['design_187']}");
		// Authenticate the user (use global auth value to authenticate global pages witih $auth_meth variable)
		$GLOBALS['auth_meth'] = $GLOBALS['auth_meth_global'];
		Authentication::authenticate();
		// Disable error reporting for all EXCEPT for authenticated REDCap admins
		self::disableErrorReportingForNonAdmins();
		// Prevent CRSF attacks by checking a custom token
		self::checkCsrfToken();
		// Check if system has been set to Offline
		self::checkSystemStatus();
		// Count this page hit
		Logging::logPageHit();
		// Add this page viewing to log_view table
		Logging::logPageView('PAGE_VIEW', defined('USERID') ? USERID : '');
		// If the current user has any currently running queries in another mysql process, than gather than in array
		Logging::getUserCurrentQueries();
		// Ensure we don't overload max_input_vars
		self::checkPostParamCount();
		// REDCap Hook injection point: Pass PROJECT_ID constant (if defined).
		Hooks::call('redcap_every_page_before_render');
	}
	
	// Run all methods for project-level pages
	public static function initProjectPage()
	{
		// Initialize REDCap
		self::init();
		// Define all PHP constants used throughout the application.
		self::defineAppConstants();
		// Make sure the PHP version is compatible
		self::checkMinPhpVersion();
		// Enable GZIP compression for webpages (if Zlib extention is enabled).
		self::enableGzipCompression();
		// Create response header with X number of random characters
		self::addHeaderRandomText();
		// Check if the URL is pointing to the correct version of REDCap. If not, redirect to correct version.
		self::checkREDCapVersionRedirect();
		// Make sure we have either pnid or pid in query string. If not, then redirect to Home page.
		if (!Project::isProjectPage()) self::redirectHome();
		// Set Shared Library control
		$shared_library_enabled_global = $GLOBALS['shared_library_enabled'];
		// Query redcap_projects table for project-level values and set as global variables.
		$projectVals = Project::setProjectVals();
		// Bring project values into this scope
		extract($projectVals);
		//set array of missing data codes as global variable
		$GLOBALS['missingDataCodes'] = parseEnum($missing_data_codes);
		// Define constants and variables for project
		$GLOBALS['app_name'] = $_GET['pnid'] = $project_name;
		$_GET['pid'] = $project_id;
		defined("APP_NAME")   or define("APP_NAME",   $GLOBALS['app_name']);
		defined("PROJECT_ID") or define("PROJECT_ID", $project_id);
		$GLOBALS['hidden_edit'] = 0;
		// Disable Shared Library control for project if disabled globally
		if ($shared_library_enabled_global == '0') $GLOBALS['shared_library_enabled'] = '0';
		// Disable Twilio option for project if disabled on the Edit Project Settings page
		if ($GLOBALS['twilio_enabled_global'] == '1' && $twilio_hide_in_project == '1') $GLOBALS['twilio_enabled_global'] = '0';
		// Check DTS global value. If disabled, then disable project-level value also.
		if (!$GLOBALS['dts_enabled_global']) $GLOBALS['dts_enabled'] = false;
		// Check randomization module's global value. If disabled, then disable project-level value also.
		if (!$GLOBALS['randomization_global']) $GLOBALS['randomization'] = 0;
		// Language: Call the correct language file for this project (default to English)
		$GLOBALS['lang'] = Language::getLanguage($project_language);
		// Set pre-defined multiple choice options for Yes-No and True-False fields
		define("YN_ENUM", "1, {$GLOBALS['lang']['design_100']} \\n 0, {$GLOBALS['lang']['design_99']}");
		define("TF_ENUM", "1, {$GLOBALS['lang']['design_186']} \\n 0, {$GLOBALS['lang']['design_187']}");
		// Object containing all project information
		$GLOBALS['Proj'] = $Proj = new Project();
		// Ensure that the field being used as the secondary id still exists as a field. If not, set $secondary_pk to blank.
		if ($secondary_pk != '' && !isset($Proj->metadata[$secondary_pk])) {
			$GLOBALS['secondary_pk'] = '';
		}
		// Determine if longitudinal (has multiple events) and multiple arms
		$GLOBALS['longitudinal'] = $longitudinal = $Proj->longitudinal;
		$GLOBALS['multiple_arms'] = $multiple_arms = $Proj->multiple_arms;
		// Establish the record id Field Name and its Field Label
		$GLOBALS['table_pk'] = $table_pk = $Proj->table_pk;
		$GLOBALS['table_pk_phi'] = $table_pk_phi = $Proj->table_pk_phi;
		$GLOBALS['table_pk_label'] = $table_pk_label = $Proj->table_pk_label;
		// Instantiate DynamicDataPull object
		$GLOBALS['DDP'] = new DynamicDataPull(PROJECT_ID, $Proj->project['realtime_webservice_type']);
		// If surveys are not enabled global, then make sure they are also disabled for the project
		if (!$GLOBALS['enable_projecttype_singlesurveyforms']) $GLOBALS['surveys_enabled'] = 0;
		// If survey_email_participant_field has a value but is no longer a real field (or is no longer email-valiated), then reset it to blank.
		// Also reset to blank if surveys are not enabled for this project.
		if (!$surveys_enabled || ($survey_email_participant_field != '' && (!isset($Proj->metadata[$survey_email_participant_field])
			|| (isset($Proj->metadata[$survey_email_participant_field])
			&& $Proj->metadata[$survey_email_participant_field]['element_validation_type'] != 'email'))))
		{
			$GLOBALS['survey_email_participant_field'] = '';
		}
		// Initialize External Modules library
		self::initExternalModules();
		// Disable authentication if we're on the buildRecordListCache route since it is merely a passthru with no user input and might be utilized on a NOAUTH plugin
        if (isset($_GET['route']) && isset($_GET['NOAUTH_BUILDRECORDLIST']) && $_GET['route'] == 'DataEntryController:buildRecordListCache') {
			define("NOAUTH", true);
        }
		// Authenticate the user
		Authentication::authenticate();
		// Disable error reporting for all EXCEPT for authenticated REDCap admins
		self::disableErrorReportingForNonAdmins();
		// Project-level user privileges
		if(!defined("ANYAUTH")){
		    $UserRights = new UserRights(true);
		}
		// SURVEY: If on survey page, start the session and manually set username to [survey respondent]
		$isSurveyPage = (PAGE == "surveys/index.php" || (defined("NOAUTH") && isset($_GET['s'])));
		if ($isSurveyPage)
		{
			// Initialize the PHP session for survey pages (they are different from typical REDCap sessions)
			Session::init("survey");
			// Set "username" for logging purposes (static for all survey respondents) - BUT it can be overridden if $_SESSION['username'] exists		
			defined("USERID") or define("USERID", strtolower(isset($_SESSION['username']) ? $_SESSION['username'] : "[survey respondent]"));
		}
		// NON-SURVEY: Normal project page
		else
		{
			// Prevent CRSF attacks by checking a custom token
			self::checkCsrfToken();
			// Instantiate ExternalLinks object
			$GLOBALS['ExtRes'] = new ExternalLinks();
			// If project has been scheduled for deletion, then don't display items on left-hand menu (i.e. remove user rights to everything)
			if ($GLOBALS['date_deleted'] != "" || $GLOBALS['completed_time'] != "") $GLOBALS['user_rights'] = array();
			// If using Double Data Entry, make sure users cannot use record auto numbering (since it wouldn't make sense)
			if ($GLOBALS['double_data_entry'] && $GLOBALS['auto_inc_set']) $GLOBALS['auto_inc_set'] = 0;
		}
		// Check if system has been set to Offline
		self::checkSystemStatus();
		// Check Online/Offline status of project
		self::checkOnlineStatus();
		// Count this page hit
		Logging::logPageHit();
		// Add this page viewing to log_view table
		Logging::logPageView('PAGE_VIEW', USERID);
		// Clean up any temporary files sitting on the web server (for various reasons)
		Files::manage_temp_files();
		// If the current user has any currently running queries in another mysql process, than gather than in array
		Logging::getUserCurrentQueries();
		// Ensure repeating instance number is valid (always set default instance as 1)
		if (!isset($_GET['instance']) || !is_numeric($_GET['instance']) || $_GET['instance'] < 1) {
			$_GET['instance'] = 1;
		}
		$_GET['instance'] = (int)$_GET['instance'];
		// Ensure we don't overload max_input_vars
		self::checkPostParamCount();
		// REDCap Hook injection point: Pass PROJECT_ID constant (if defined)
		Hooks::call('redcap_every_page_before_render', array(PROJECT_ID));
		// Record List Cache: If we're on a specific page and the cache has not been built, then redirect to a specific route in order to build it
        Records::determineBuildRecordListCache();
	}
	
	// Initialize External Modules library
	// Search first in /redcap/external_modules, and then in /redcap/redcap_vX.X.X/ExternalModules/
	private static function initExternalModules()
	{
		$ExtModClassPath = 'classes/ExternalModules.php';
		// Make sure that we are on PHP 5.4+ or else Ext Mods should not be included
		if (version_compare(PHP_VERSION, '5.4.0', '<')) return;
		// First check in /redcap/external_modules (so it can serve as an override)
		if (file_exists(dirname(APP_PATH_DOCROOT) . DS . 'external_modules' . DS . $ExtModClassPath)) 
		{
			define("APP_PATH_EXTMOD", dirname(APP_PATH_DOCROOT) . DS . 'external_modules' . DS);
			define("APP_URL_EXTMOD", APP_PATH_WEBROOT_FULL . "external_modules/");
			// Note that the Ext Mods installation is external/outside the REDCap version directory
			define("EXTMOD_EXTERNAL_INSTALL", true);
		} 
		// Next check in /redcap/redcap_vX.X.X/ExternalModules/
		elseif (file_exists(APP_PATH_DOCROOT . 'ExternalModules' . DS . $ExtModClassPath)) 
		{
			define("APP_PATH_EXTMOD", APP_PATH_DOCROOT . 'ExternalModules' . DS);
			define("APP_URL_EXTMOD", APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/ExternalModules/");
			// Note that the Ext Mods installation is interal/inside the REDCap version directory
			define("EXTMOD_EXTERNAL_INSTALL", false);
		}
		if (defined("APP_URL_EXTMOD")) 
		{
			// if (isDev()) define("APP_URL_EXTMOD_LIB", "http://10.151.18.250/redcap_standard/consortium/modules/"); else 
			define("APP_URL_EXTMOD_LIB", "https://redcap.vanderbilt.edu/consortium/modules/");
			define("APP_PATH_MODULES", dirname(APP_PATH_DOCROOT).DS.'modules'.DS);
			include_once APP_PATH_EXTMOD . $ExtModClassPath;
			// Disable authentication if accessing a module plugin page via API endpoint using NOAUTH query string param
			if (defined("API_EXTMOD") && !defined("NOAUTH") && isset($_GET['NOAUTH'])) {
				define("NOAUTH", true);
			}
		}

		self::checkForOldExternalModuleFrameworkVersionRequests();
	}

	private static function checkForOldExternalModuleFrameworkVersionRequests(){
		$parts = explode('/', APP_PATH_WEBROOT);
		$redcapVersionDirIndex = count($parts) - 2;
		$externalModulesDirIndex = $redcapVersionDirIndex + 1;
		$currentRedcapVersionDir = @$parts[$redcapVersionDirIndex];
		$requestUri = $_SERVER['REQUEST_URI'];
		$parts = explode('/', $requestUri);

		if(@$parts[$externalModulesDirIndex] !== 'ExternalModules'){
			// This is not a request to a bundled version of the External Module framework.
			// No further checking is required.
			return;
		}

		$redcapVersionDirFromRequest = @$parts[$redcapVersionDirIndex];
		if(strpos($redcapVersionDirFromRequest, 'redcap_v') !== 0){
			// This is not a request to a REDCap version directory.
			// No further checking is required.
			return;
		}

		if(EXTMOD_EXTERNAL_INSTALL){
			echo "The External Module framework directory is overridden on this REDCap instance.  Requests to the bundled copy of the framework are disallowed.";
			exit;
		}

		if($redcapVersionDirFromRequest !== $currentRedcapVersionDir){
			// A request is being made to an old version of the External Module framework.
			// This should not happen under normal circumstances.
			// This may be a malicious attempt to exploit bugs in older versions.
			// Redirect in case there are any legitimate cases where this might happen.
			$pos = strpos($requestUri, $redcapVersionDirFromRequest);
			$newRequestUri = substr_replace($requestUri, $currentRedcapVersionDir, $pos, strlen($redcapVersionDirFromRequest));
			header("Location: $newRequestUri");
			exit;
		}
	}

	/**
	 * ERROR HANDLING
	 */
	public static function REDCapErrorHandler($code, $message, $file, $line)
	{
		global $lang, $log_all_errors, $redcapCronJobCurrent, $project_contact_email;
		$errorRendered = false;
		
		if (isset($lang) && !empty($lang)) {
			$err1 = $lang['config_functions_01'];
			$err2 = $lang['config_functions_02'];
			$err3 = $lang['config_functions_03'];
			$err4 = $lang['config_functions_04'];
		} else {
			$err1 = "REDCap crashed due to an unexpected PHP fatal error!";
			$err2 = "Error message:";
			$err3 = "File:";
			$err4 = "Line:";
		}
		
		// If this variable is set to TRUE in database.php, then log all errors in PHP log file
		if ($log_all_errors) 
		{
			$errortype = array(
				E_ERROR=>"Error",
				E_WARNING=>"Warning",
				E_PARSE=>"Parsing Error",
				E_NOTICE=>"Notice",
				E_CORE_ERROR=>"Core Error",
				E_CORE_WARNING=>"Core Warning",
				E_COMPILE_ERROR=>"Compile Error",
				E_COMPILE_WARNING=>"Compile Warning",
				E_USER_ERROR=>"Error",
				E_USER_WARNING=>"Warning",
				E_USER_NOTICE=>"Notice",
				E_STRICT=>"Runtime Notice",
				E_DEPRECATED=>"Deprecated"
			);
			if (isset($errortype[$code])) {
				$code = $errortype[$code];
			}
			// Log the error
			error_log("$err2 $code - $message, $err3 $file, $err4 $line");
		}

		// Fatal error is code=1
		if ($code == 1)
		{
			// Kill the MySQL process so that it doesn't continue after PHP script stops
			db_query("KILL CONNECTION_ID()");

			// If a PLUGIN calls an undefined method/function, give custom message so plugin developer may be notified
			if (defined("PLUGIN") && (strpos($message, "Call to undefined function") !== false
				|| strpos($message, "Call to undefined method") !== false))
			{
				print  "<div class='red' style='max-width:700px;'>
							<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['config_functions_87']}<br><br>
							<b>{$lang['config_functions_02']}</b> $message <br>
							<b>{$lang['config_functions_03']}</b> $file <br>
							<b>{$lang['config_functions_04']}</b> $line
						</div>";
				return;
			}
			
			// If a cron job fails, then email the REDCap admin
			if (defined("CRON") && $redcapCronJobCurrent != null)
			{
				$emailContents =   "<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
									REDCap Administrator,<br><br>
									The REDCap cron job named \"<b>$redcapCronJobCurrent</b>\" crashed unexpectedly on the server <b>".SERVER_NAME."</b> at <b>".NOW."</b>!<br><br>
									If you are not on the latest version of REDCap, you might consider upgrading REDCap to the latest version to see if 
									that fixes this issue. If not, you might want to post this as a Bug Report on the REDCap Community website. See details below.<br><br>
									<b>{$lang['config_functions_02']}</b> $message <br>
									<b>{$lang['config_functions_03']}</b> $file <br>
									<b>{$lang['config_functions_04']}</b> $line <br>
									</body></html>";
				$email = new Message();
				$email->setTo($project_contact_email);
				$email->setFrom($project_contact_email);
				$email->setFromName($GLOBALS['project_contact_name']);
				$email->setSubject('[REDCap] Cron job crashed!');
				$email->setBody($emailContents);
				$email->send();
			}

			// If a Vanderbilt user, send email to admin to troubleshoot (exclude Plugins and specific pages)
			if (REDCAP_VERSION == '9.8.0' && SERVER_NAME == 'redcap.vanderbilt.edu'
                && !defined("PLUGIN") && PAGE != 'PdfController:index' && PAGE != 'DataQuality/execute_ajax.php' && PAGE != 'api/index.php' && PAGE != 'DataImportController:index'
				&& !((PAGE == 'DataExport/data_export_ajax.php' || PAGE == 'DataExport/report_ajax.php') && strpos($message, "Maximum execution time of") !== false))
			{
				$errorEmail = (SERVER_NAME == 'redcap.vanderbilt.edu') ? 'redcap@vumc.org' : 'rob.taylor@vumc.org';
				$emailContents =   "<html><body style=\"font-family:arial,helvetica;font-size:10pt;\">
									PHP Crashed on <b>".SERVER_NAME."</b> at <b>".NOW."</b>!<br><br>
									<b>Page:</b> https://".SERVER_NAME.$_SERVER['REQUEST_URI']."<br>".
									($_SERVER['REQUEST_METHOD'] == 'POST' ? "<b>Post params:</b> ".print_r($_POST, true)."<br>" : '') . "
									<b>User:</b> ".USERID."<br><br>
									<b>{$lang['config_functions_02']}</b> $message <br>
									<b>{$lang['config_functions_03']}</b> $file <br>
									<b>{$lang['config_functions_04']}</b> $line <br>
									</body></html>";
				$email = new Message ();
				$email->setTo($errorEmail);
				$email->setFrom($errorEmail);
				$email->setSubject('[REDCap] PHP Crashed!');
				$email->setBody($emailContents);
				$email->send();
			}

			// Google OAuth2 failure
			global $auth_meth_global;
			if ($auth_meth_global == 'openid_google' && strpos($message, "Error fetching OAuth2 access token") !== false) 
			{
				print  "<html>
						<head><meta http-equiv='refresh' content='5' /></head>
						<body>
							<div class='red' style='max-width:700px;'>
								<b>{$lang['global_01']}{$lang['colon']} Google login failure!</b><br><br>
								We're sorry, but for unknown reasons this application is not able to connect with Google's OAuth2 authentication provider.
								Please try again in a moment, and if the issue is not resolved at that time, 
								please inform an administrator about this issue. Our apologies for any inconvenience.
							</div>
						</body>
						</html>";
				return;
			}

			// Custom message for memory overload or script timeout (all pages)
			if (defined('PAGE') && PAGE == "DataQuality/execute_ajax.php")
			{
				// Get current rule_id and the ones following
				list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
				// Set error message
				if (strpos($message, "Maximum execution time of") !== false) {
					// Script timeout error
					$msg = "<div id='results_table_{$rule_id}'>
								<p class='red' style='max-width:500px;'>
									<b>{$lang['dataqueries_105']}</b> {$lang['dataqueries_106']}
									".ini_get('max_execution_time')." {$lang['dataqueries_107']}
								</p>";
					// Set main error msg seen in table
					$dqErrMsg = $lang['dataqueries_108'];
				} else {
					// Memory overload error
					$msg = "<div id='results_table_{$rule_id}'>
								<p class='red' style='max-width:500px;'>
									<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['dataqueries_32']} <b>{$_GET['error_rule_name']}</b> {$lang['dataqueries_33']}
									" . (is_numeric($rule_id) ? $lang['dataqueries_34'] : $lang['dataqueries_96']) . "
								</p>";
					// Set main error msg seen in table
					$dqErrMsg = $lang['global_01'];
				}
				// Provide super users with further context about error
				if (defined('SUPER_USER') && SUPER_USER) {
					$msg .=	"<p class='red' style='max-width:600px;'>
								<b>{$lang['config_functions_01']}</b><br><br>
								<b>{$lang['config_functions_02']}</b> $message<br>
								<b>{$lang['config_functions_03']}</b> $file<br>
								<b>{$lang['config_functions_04']}</b> $line<br>
							 </p>";
				}
				$msg .=	"</div>";
				// Send back JSON
				print '{"rule_id":"' . $rule_id . '",'
					. '"next_rule_ids":"' . $rule_ids . '",'
					. '"discrepancies":"1",'
					. '"discrepancies_formatted":"<span style=\"font-size:12px;\">'.$dqErrMsg.'</span>",'
					. '"dag_discrepancies":[],'
					. '"title":"' . RCView::escape($_GET['error_rule_name']) . '",'
					. '"payload":"' . cleanJson($msg)  .'"}';
				return;
			}

			// Return output of "0" for report and data export ajax request
			if (defined('PAGE') && (PAGE == "DataExport/data_export_ajax.php" || PAGE == "DataExport/report_ajax.php"))
			{
				exit("0");
			}

			// Render error message to super users only OR user is on Install page and can't get it to load
			if ((defined('SUPER_USER') && SUPER_USER) || (defined('PAGE') && PAGE == "install.php"))
			{
				?>
				<div class="red" style="margin:20px 0px;max-width:700px;">
					<b><?php echo $err1 ?></b><br><br>
					<b><?php echo $err2 ?></b> <?php echo htmlspecialchars($message) ?><br>
					<b><?php echo $err3 ?></b> <?php echo $file ?><br>
					<b><?php echo $err4 ?></b> <?php echo $line ?><br>
				</div>
				<?php
				$errorRendered = true;
			}

			// Catch any pages that timeout
			if (strpos($message, "Maximum execution time of") !== false)
			{
				// Set error message text
				$max_execution_error_msg = 	RCView::div(array('class'=>'red', 'style'=>'max-width:700px;'),
												RCView::b($lang['dataqueries_105']) . " " .
												$lang['dataqueries_106'] . " " . ini_get('max_execution_time') . " " .
												$lang['dataqueries_107']
											);
				// API error only
				if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php"))
				{
					API::outputApiErrorMsg($max_execution_error_msg);
				}
				// Non-API page
				else
				{
					exit($max_execution_error_msg);
				}
			}

			// API error only for data imports where data is not properly formatted (especially for XML)
			if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php")
				&& strpos($message, "Cannot create references to/from string offsets nor overloaded objects") !== false)
			{
				API::outputApiErrorMsg('The data being imported is not formatted correctly');
			}

			// Custom message for memory overload (all pages)
			if (defined('PAGE') && strpos($message, "Allowed memory size of") !== false)
			{
				// Specific message for Data Import Tool
				if (PAGE == "DataImportController:index")
				{
					?>
					<div class="red" style="max-width:700px;">
						<b><?php echo $lang['global_01'] . $lang['colon'] . " " . $lang['config_functions_05'] ?></b><br>
						<?php echo $lang['config_functions_06'] ?>
					</div>
					<?php
				}
				// Specific message for PDF export
				elseif (PAGE == "PdfController:index")
				{
					?>
					<div class="red" style="max-width:700px;">
						<b><?php echo $lang['global_01'] . $lang['colon'] . " " . $lang['config_functions_80'] ?></b><br>
						<?php echo $lang['config_functions_81'] ?>
					</div>
					<?php
				}
				 // Specific message for API requests (typically import or export)
				elseif (PAGE == "api/index.php" || PAGE == "API/index.php")
				{
					exit(RestUtility::sendResponse(500, 'REDCap ran out of server memory. The request cannot be processed. Please try importing/exporting a smaller amount of data.'));
				}
				// Generic message for "out of memory" error
				else
				{
					?>
					<div class="red" style="max-width:700px;">
						<b>ERROR: REDCap ran out of memory!</b><br>
						The current web page has hit the maximum allowed memory limit (<?php echo ini_get('memory_limit') ?>B).
						<?php if (defined('SUPER_USER') && SUPER_USER) { ?>
							Super user message: You might think about increasing your web server memory used by PHP by
							changing the value of "memory_limit" in your server's PHP.INI file.
							(Don't forget to reboot the web server after making this change.)
						<?php } else { ?>
							Please contact a REDCap administrator to inform them of this issue.
						<?php } ?>
					</div>
					<?php
				}
				$errorRendered = true;
			}

			// API error only
			if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php") && (!isset($_GET['type']) || $_GET['type'] != 'module'))
			{
				API::outputApiErrorMsg('An unknown error occurred. Please check your API parameters.');
			}

			// Give general error message to normal user
			if (!$errorRendered)
			{
				?>
				<div class="red" style="margin:20px 0px;max-width:700px;">
					<b><?php echo $lang['config_functions_07'] ?></b><br><br>
					<?php echo $lang['config_functions_08'] ?>
				</div>
				<?php

			}
		}
	}
		
	// Method that is first called when beginning shutdown of every PHP request
	public static function beginShutdown()
	{
		define("ShutdownStarted", true);
	}
	
	// Method for handling fatal PHP errors
	public static function fatalErrorShutdownHandler()
	{
		// Get last error
		$last_error = @error_get_last();
		if (isset($last_error['type']) && $last_error['type'] === E_ERROR) {
			// fatal error
			self::REDCapErrorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
		}
	}

	// Enable GZIP compression for webpages (if Zlib extention is enabled).
	// Return boolean if gzip is enabled for this "page" (i.e. request).
	public static function enableGzipCompression()
	{
		global $enable_http_compression;
		// Make sure we only enable compression on visible webpages (as opposed to file downloads).
		if (!$enable_http_compression
			// Do not compress if PAGE constant is not set
			|| (!defined('PAGE'))
			// Ignore certain allowlisted pages where we don't want to use compression
			|| (defined('PAGE') && ((isset($_GET['__passthru']) && PAGE == 'surveys/index.php') || in_array(PAGE, System::$fileDownloadPages)))
			// Do not compress file
			|| (defined('API') && isset($_POST['content']) && $_POST['content'] == 'file')
		) {
			define("GZIP_ENABLED", false);
		}
		else
		{
			// Compress the PHP output (uses up to 80% less bandwidth)
			ini_set('zlib.output_compression', 4096);
			ini_set('zlib.output_compression_level', -1);
			// Set flag if gzip is enabled on the web server
			define("GZIP_ENABLED", (function_exists('ob_gzhandler') && ini_get('zlib.output_compression')));
		}
		// Return value if gzip is now enabled
		return GZIP_ENABLED;
	}

	// Version Redirect: Make sure user is on the correct REDCap version for this project.
	// Note that $redcap_version is pulled from config table and $redcapdir_version is the version from the folder name
	// If they are not equal, then a redirect should occur so that user is accessing correct page in correct version (according to the redcap_projects table)
	public static function checkREDCapVersionRedirect()
	{
		global $redcap_version, $isAjax;
		// If we're on the LanguageCenter page, don't redirect because we may be trying to get translation file
		// to next version BEFORE we upgrade.
		if (basename(dirname($_SERVER['PHP_SELF'])) . "/" . basename($_SERVER['PHP_SELF']) == 'LanguageUpdater/index.php') {
			return;
		}
		// Set informal docroot
		$app_path_docroot = dirname(dirname(__FILE__)).DS;
		// Bypass version check for developers who are using the "codebase" directory (instead of redcap_vX.X.X) for development purposes
		if (basename($app_path_docroot) == 'codebase') return;
		// Determine if this is the API
		$isAPI = (basename(dirname($_SERVER['PHP_SELF'])) . "/" . basename($_SERVER['PHP_SELF']) == 'api/index.php');
		// Get version we're currently in from the URL
		$redcapdir_version = substr(basename($app_path_docroot), 8);
		// If URL version does not match version number in redcap_config table, redirect to correct directory.
		// Do NOT redirect if the version number is not in the URL.
		if ($redcap_version != $redcapdir_version && ($isAPI || strpos($_SERVER['REQUEST_URI'], "/redcap_v{$redcapdir_version}/") !== false))
		{
			// Only redirect if version number in redcap_config table is an actual directory
			if (in_array("redcap_v" . $redcap_version, getDirFiles(dirname($app_path_docroot))))
			{
				if ($isAPI && !defined("API_EXTMOD")) {
					// API: Make Post request to the version-specific API path for the correct version
					// (This should only be used temporarily when someone has added a new version directory to their web server
					// but has not yet fully upgraded the database to the new REDCap version.)
					exit(http_post(APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/API/index.php", $_POST));
				} else {
					// Replace version number in URL, then redirect
					$redirectto = str_replace("/redcap_v" . $redcapdir_version . "/", "/redcap_v" . $redcap_version . "/", $_SERVER['REQUEST_URI']);
					// Make sure that the page we're redirecting to actually exists in the newer version (if not, redirect to home)
					$subDir = basename(dirname($_SERVER['PHP_SELF']));
					$subDir = ($subDir == "redcap_v" . $redcapdir_version) ? "" : $subDir.DS;
					$redirecttoFullPath = dirname(APP_PATH_DOCROOT).DS."redcap_v".$redcap_version.DS.$subDir.basename($_SERVER['PHP_SELF']);
					if (!file_exists($redirecttoFullPath)) {
						if (isset($_GET['pid'])) {	
							// Redirect to the project's Home Page					
							redirect(APP_PATH_WEBROOT."index.php?pid=".$_GET['pid']);
						} else {
							// Redirect to the REDCap Home page
							System::redirectHome();
						}
					}
					// Check if post or get request
					if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isAjax) {
						// If this was a non-ajax post request, then preserve the submitted values by building
						// an invisible form that posts itself to same page in the new version.
						$postElements = "";
						foreach ($_POST as $key=>$val) {
							$postElements .= "<input type='hidden' name=\"".htmlspecialchars($key, ENT_QUOTES)."\" value=\"".htmlspecialchars($val, ENT_QUOTES)."\">";
						}
						?>
						<html><body>
						<form action="<?php echo $redirectto ?>" method="post" name="form" enctype="multipart/form-data">
							<?php echo $postElements ?>
						</form>
						<script type='text/javascript'>
						document.form.submit();
						</script>
						</body>
						</html>
						<?php
						exit;
					} else {						
						// If this is a call to an External Module's API endpoint, then redirect directly inside the version
						if (defined("API_EXTMOD")) {
							$redirectto = APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/API/index.php?".$_SERVER['QUERY_STRING'];
						}
						// Redirect to the same page in the new version
						redirect($redirectto);
					}
				}
			}
		}
	}

	// Set main directories for REDCap
	public static function defineAppConstants()
	{
		global $redcap_version, $edoc_path, $redcap_base_url, $redcap_survey_base_url, $google_cloud_storage_temp_bucket, $google_cloud_storage_edocs_bucket, $edoc_storage_option;
		// Get server name (i.e. domain), server port, and if using SSL (boolean)
		list ($server_name, $port, $ssl, $page_full) = getServerNamePortSSL();
		define("SERVER_NAME", $server_name);
		define("SSL", $ssl);
		define("PORT", str_replace(":", "", $port)); // Set PORT as numeric w/o colon
		// Declare current page with full path
		define("PAGE_FULL", $page_full);
		// Check for route. If exists, set as PAGE.
		$Route = new Route(false);
		if ($Route->get()) define("PAGE", $Route->get());
		// Declare current page path from the version folder. If in subfolder, include subfolder name.
		if (basename(dirname(PAGE_FULL)) == "redcap_v" . $redcap_version) {
			// Page in version folder
			defined("PAGE") or define("PAGE", basename(PAGE_FULL));
		} elseif (basename(dirname(dirname(PAGE_FULL))) == "redcap_v" . $redcap_version) {
			// Page in subfolder under version folder
			defined("PAGE") or define("PAGE", basename(dirname(PAGE_FULL)) . "/" . basename(PAGE_FULL));
		} else {
			$subfolderPage = basename(dirname(PAGE_FULL)) . "/" . basename(PAGE_FULL);
			if ( 	// If main index.php above version folder
					basename(dirname(dirname(dirname(__FILE__)))) . "/index.php" == $subfolderPage
                   || "/index.php" ==  $subfolderPage
					// Or if survey page
					|| "surveys/index.php" ==  $subfolderPage
					// Or if API page
					|| "api/index.php" ==  $subfolderPage
				) {
			    // Remove any slashes from beginning
                $subfolderPage = ltrim($subfolderPage, '/');
				// Only for the index.php page above the version folder OR for survey page
				defined("PAGE") or define("PAGE", $subfolderPage);
			} else {
				// If using a file above the version folder (other than index.php, survey page, or API page), then PAGE will not be defined
			}
		}
		// Define web path to REDCap version folder (if redcap_base_url is defined, then use it to determine APP_PATH_WEBROOT)
		if (isset($redcap_base_url) && !empty($redcap_base_url)) {
			$redcap_base_url .= ((substr($redcap_base_url, -1) != "/") ? "/" : "");
			$redcap_base_url_parsed = parse_url($redcap_base_url);
			define("APP_PATH_WEBROOT", $redcap_base_url_parsed['path'] . "redcap_v{$redcap_version}/");
			// Define full web address
			define("APP_PATH_WEBROOT_FULL",		$redcap_base_url);
		} else {
			define("APP_PATH_WEBROOT", getVersionFolderWebPath());
			// Define full web address
			define("APP_PATH_WEBROOT_FULL",		(SSL ? "https" : "http") . "://" . SERVER_NAME . $port . ((strlen(dirname(APP_PATH_WEBROOT)) <= 1) ? "" : dirname(APP_PATH_WEBROOT)) . "/");
		}
		// Path to server folder above REDCap webroot
		define("APP_PATH_WEBROOT_PARENT", 		((strlen(dirname(APP_PATH_WEBROOT)) <= 1) ? "" : dirname(APP_PATH_WEBROOT)) . "/");
		// Docroot will be used by php includes
		$redcap_version_dir = dirname(dirname(__FILE__));
		if (self::isCI()) {
            define("APP_PATH_DOCROOT", $redcap_version_dir . DS);
        } elseif (basename($redcap_version_dir) == "redcap_v" . $redcap_version || basename($redcap_version_dir) == "codebase"
			|| defined("CRON") || defined("API") || defined("API_EXTMOD") || isset($_GET['pid']) || isset($_GET['route']) 
			|| strpos(PAGE_FULL, "/ControlCenter/") !== false
			|| strpos(PAGE_FULL, "/LanguageUpdater/") !== false 
			|| strpos(PAGE_FULL, "/SendIt/") !== false
			|| strpos(PAGE_FULL, "/PubMatch/") !== false
			|| strpos(PAGE_FULL, "/Plugins/") !== false
            || strpos(PAGE_FULL, "/Messenger/info.php") !== false
			// Not yet sure how to deal with specific global pages outside Control Center and Send-It
			|| strpos(PAGE_FULL, "/Design/action_tag_explain.php") !== false
			|| strpos(PAGE_FULL, "/Design/smart_variable_explain.php") !== false
			|| strpos(PAGE_FULL, "/DataEntry/piping_explanation.php") !== false
			|| strpos(PAGE_FULL, "/DataEntry/field_embedding_explanation.php") !== false
		) {
			define("APP_PATH_DOCROOT", $redcap_version_dir . DS);
		} else {
			// If we're about to upgrade (new directory is on the server), then use redirection so that APP_PATH_DOCROOT doesn't point to new version.
			redirect(APP_PATH_WEBROOT . "Home/index.php" . ($_SERVER['QUERY_STRING'] == '' ? '' : "?" . $_SERVER['QUERY_STRING']));
		}
		// Path to REDCap temp directory
		if ($edoc_storage_option == '3') {
			// Google Cloud Storage
			define("APP_PATH_TEMP",				"gs://$google_cloud_storage_temp_bucket/");
		} else {
			// Normal local temp directory
			define("APP_PATH_TEMP",				dirname(APP_PATH_DOCROOT) . DS . "temp" . DS);
		}
		// Webtools folder path
		define("APP_PATH_WEBTOOLS",				dirname(APP_PATH_DOCROOT) . DS . "webtools2" . DS);
		// Path to folder containing uploaded files (default is "edocs", but can be changed in Control Center system config)
		$edoc_path = trim($edoc_path);
		if ($edoc_storage_option == '3') {
			// Google Cloud Storage
			define("EDOC_PATH",					"gs://$google_cloud_storage_edocs_bucket/");
		} elseif ($edoc_path == "") {
			// Default local edocs directory
			define("EDOC_PATH",					dirname(APP_PATH_DOCROOT) . DS . "edocs" . DS);
		} else {
			// Non-default local edocs directory
			define("EDOC_PATH",					$edoc_path . ((substr($edoc_path, -1) == "/" || substr($edoc_path, -1) == "\\") ? "" : DS));
		}
		// Classes
		define("APP_PATH_CLASSES",  			APP_PATH_DOCROOT . "Classes" . DS);
		// Controllers
		define("APP_PATH_CONTROLLERS", 			APP_PATH_DOCROOT . "Controllers" . DS);
		// Views
		define("APP_PATH_VIEWS", 				APP_PATH_DOCROOT . "Views" . DS);
		// Libraries
		define("APP_PATH_LIBRARIES", 			APP_PATH_DOCROOT . "Libraries" . DS);
		// Image repository
		define("APP_PATH_IMAGES",				APP_PATH_WEBROOT . "Resources/images/");
		// CSS
		define("APP_PATH_CSS",					APP_PATH_WEBROOT . "Resources/css/");
		// External Javascript
		define("APP_PATH_JS",					APP_PATH_WEBROOT . "Resources/js/");
		// Webpack
		define("APP_PATH_WEBPACK",				APP_PATH_WEBROOT . "Resources/webpack/");
		// Tiny MCE (rich text editor) - set current version used and its path
		define("TINYMCE_VERSION",				"3.4.9");
		define("APP_PATH_MCE",      			APP_PATH_WEBROOT_PARENT . "webtools2/tinymce_" . TINYMCE_VERSION . "/jscripts/tiny_mce/");
		// Survey URL
		define("APP_PATH_SURVEY",				APP_PATH_WEBROOT_PARENT . "surveys/");
		// If using alternative survey base URL for Full URL
		$redcap_survey_base_url = trim($redcap_survey_base_url);
		if ($redcap_survey_base_url != '') {
			// Make sure $redcap_survey_base_url ends with a /
			$redcap_survey_base_url .= ((substr($redcap_survey_base_url, -1) != "/") ? "/" : "");
			// Full survey URL
			define("APP_PATH_SURVEY_FULL",		$redcap_survey_base_url . "surveys/");
		} else {
			// Full survey URL
			define("APP_PATH_SURVEY_FULL",		APP_PATH_WEBROOT_FULL . "surveys/");
		}
		// REDCap Consortium website domain name
		define("CONSORTIUM_WEBSITE_DOMAIN",		"https://projectredcap.org");
		// REDCap Consortium website URL
		define("CONSORTIUM_WEBSITE",			"https://redcap.vanderbilt.edu/consortium/");
		// REDCap Shared Library URLs
		define("SHARED_LIB_PATH",				CONSORTIUM_WEBSITE 	  . "library/");
		define("SHARED_LIB_BROWSE_URL",			SHARED_LIB_PATH 	  . "login.php");
		define("SHARED_LIB_UPLOAD_URL",			SHARED_LIB_PATH 	  . "upload.php");
		define("SHARED_LIB_UPLOAD_ATTACH_URL",	SHARED_LIB_PATH 	  . "upload_attachment.php");
		define("SHARED_LIB_DOWNLOAD_URL",		SHARED_LIB_PATH 	  . "get.php");
		define("SHARED_LIB_SCHEMA",				SHARED_LIB_PATH 	  . "files/SharedLibrary.xsd");
		define("SHARED_LIB_CALLBACK_URL",		APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/SharedLibrary/receiver.php");
		// REDCap version
		defined("REDCAP_VERSION") or define("REDCAP_VERSION", $redcap_version);
	}
	
	// Check if system has been set to Offline. If so, prevent normal users from accessing site.
	public static function checkSystemStatus() 
	{
		global $system_offline, $system_offline_message, $homepage_contact_email, $homepage_contact, $lang;

		$GLOBALS['delay_kickout'] = $delay_kickout = false;

		if ($system_offline && PAGE != 'ControlCenter/check.php' && PAGE != 'ControlCenter/check_server_ping.php' && (!defined('SUPER_USER') || (defined('SUPER_USER') && !SUPER_USER)))
		{

			// If custom offline message is set, then display it inside red box
			$system_offline_message_text = '';
			if (isset($system_offline_message) && trim($system_offline_message) != '') {
				// Custom message
				$system_offline_message_text = nl2br(decode_filter_tags($system_offline_message));
			} else {
				// Default message
				$system_offline_message_text = RCView::img(array('src'=>'exclamation.png')) . " " . $lang['config_functions_36'];
			}

			//To prevent loss of data, don't kick the user out until the page has been processed when on data entry page.
			if (PAGE == "DataEntry/index.php") {
				$GLOBALS['delay_kickout'] = true;
				return;
			}
			// If using the API, do not display all the HTML but just the message
			elseif (PAGE == "api/index.php" || PAGE == "API/index.php") {
				API::outputApiErrorMsg($system_offline_message_text);
			}
			// If this is the Cron, do not display all the HTML but just the message
			elseif (defined("CRON")) {
				exit(trim(strip_tags($system_offline_message_text)));
			}

			// Initialize page display object
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->addStylesheet("home.css", 'screen,print');
			$objHtmlPage->PrintHeader();

			print  "<div style='padding:20px 0;'>
						<img src='" . APP_PATH_IMAGES . "redcap-logo-large.png'>
					</div>
					<div class='red' style='margin:20px 0;'>
						$system_offline_message_text
					</div>
					<p style='padding-bottom:30px;'>
						{$lang['config_functions_37']}
						<a style='font-size:13px;text-decoration:underline;' href='mailto:$homepage_contact_email'>$homepage_contact</a>.
					</p>";

			$objHtmlPage->PrintFooter();
			exit;
		}
	}

	// Check Online/Offline Status: If project has been marked as OFFLINE in Control Center, then disallow access and give explanatory message.
	public static function checkOnlineStatus() 
	{
		global $delay_kickout, $online_offline, $lang, $project_contact_name, $project_contact_email, $lang, $homepage_contact_email, $homepage_contact;

		if (!$online_offline && (!defined('SUPER_USER') || (defined('SUPER_USER') && !SUPER_USER))) {
			//To prevent loss of data, don't kick the user out until the page has been processed when on data entry page.
			if (PAGE != "DataEntry/index.php") {
				// Initialize page display object
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();

				print  "<div style='padding:20px 0;'>
							<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects'><img src='" . APP_PATH_IMAGES . "redcap-logo-large.png'></a>
						</div>
						<div class='red' style='margin:20px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['config_functions_121']}
						</div>
						<p style='padding-bottom:30px;'>
							{$lang['config_functions_37']}
							<a style='font-size:13px;text-decoration:underline;' href='mailto:$homepage_contact_email'>$homepage_contact</a>.
						</p>";

				$objHtmlPage->PrintFooter();
				exit;
			} else {
				// Delay kickout until user has submitted their data
				$delay_kickout = true;
			}
		}
		$GLOBALS['delay_kickout'] = $delay_kickout;
	}

	// Prevent CSRF attacks by checking a custom token
	public static function checkCsrfToken()
	{
		global $isAjax, $lang, $salt, $userid, $auth_meth;//***<AAF Modification>****
		// Is this an API request?
		$isApi = (PAGE == "api/index.php" || PAGE == "API/index.php");
		// Is the page a REDCap plugin?
		$isExtModPage = (strpos($_SERVER['REQUEST_URI'], "/redcap_v" . REDCAP_VERSION . "/ExternalModules/") !== false || strpos($_SERVER['REQUEST_URI'], "/external_modules/") !== false);
		$isPlugin = defined("PLUGIN");
		$isAaf = (strpos($auth_meth,'aaf')>-1 && isset($_SESSION['tli']) && $_SESSION['tli']==0);//***<AAF Modification>****
		// List of specific pages exempt from creating/updating CSRF tokens
		$pagesExemptFromTokenCreate = array("Design/edit_field.php", "Reports/report_export.php",
											"DataEntry/file_upload.php", "DataEntry/file_download.php",
											"DataQuality/data_resolution_file_upload.php", "DataQuality/data_resolution_file_download.php",
											"Graphical/pdf.php/download.pdf", "PdfController:index", "DataExport/data_export_csv.php",
											"Design/file_attachment_upload.php", "DataEntry/image_view.php", "SharedLibrary/image_loader.php",
											"DataImportController:downloadTemplate", "Design/data_dictionary_download.php"
										   );
		// List of specific pages exempt from checking CSRF tokens
		$pagesExemptFromTokenCheck = array(	"Profile/user_info_action.php", "SharedLibrary/image_loader.php",
											"PubMatch/index_ajax.php", "Authentication/two_factor_check_login_status.php",
											"Authentication/two_factor_verify_code.php", "Authentication/two_factor_send_code.php",
                                            "AlertsController:saveAttachment");
		// Do not perform token check for non-Post methods, API requests, when logging in, for pages without authentication enabled,
		// or (for LDAP only) when providing user info immediately after logging in the first time.
		$exemptFromTokenCheck  = ($isExtModPage || $isPlugin || $isApi || in_array(PAGE, System::$fileDownloadPages) || in_array(PAGE, $pagesExemptFromTokenCheck)
									|| (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST') || isset($_POST['redcap_login_a38us_09i85']) || defined("NOAUTH")
								   // Two factor auth: code verification
								   || ((PAGE == "Authentication/two_factor_verify_code.php" || PAGE == "Authentication/two_factor_send_code.php") && !isset($_SESSION['two_factor_auth']))
								   // In case uploading a file and exceeds PHP limits and normal error catching does not catch the error
								   || ((PAGE == "SendItController:upload" || PAGE == "FileRepository/index.php") && empty($_FILES))
								 );
		// Do not create/update token for Head/API/AJAX requests, when logging in, or for pages that produce downloadable files,
		// non-displayable pages, receive Post data via iframe, or have authentication disabled.
		$exemptFromTokenCreate = ( $isAjax || $isApi || in_array(PAGE, $pagesExemptFromTokenCreate) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'HEAD')
								   || isset($_POST['redcap_login_a38us_09i85']) || isset($_POST['redcap_login_openid_Re8D2_8uiMn']) 
								   || (defined("NOAUTH") && !defined("EHR")) );
		// Check for CSRF token
		if (!$exemptFromTokenCheck)
		{
			// Set token value (can come from Post or Get ajax)
			$redcap_csrf_token = null;
			if (isset($_POST['redcap_csrf_token'])) {
				$redcap_csrf_token = $_POST['redcap_csrf_token'];
			} elseif (isset($_GET['redcap_csrf_token'])) {
				$redcap_csrf_token = $_GET['redcap_csrf_token'];
			}

			self::forceCsrfTokenCheck($redcap_csrf_token);
		}
		// GENERATE A NEW CRSF TOKEN, which jquery will add to all forms on the rendered page
		if (!$exemptFromTokenCreate)
		{
			// Initialize array if does not exist
			if (!isset($_SESSION['redcap_csrf_token']) || !is_array($_SESSION['redcap_csrf_token'])) {
				$_SESSION['redcap_csrf_token'] = array();
			}
			// If more than X number of elements exist in array, then remove the oldest
			$maxTokens = 50;
			if (count($_SESSION['redcap_csrf_token']) > $maxTokens) {
				array_shift($_SESSION['redcap_csrf_token']);
			}
			// Generate token and put in array
			$_SESSION['redcap_csrf_token'][NOW] = md5($salt . NOW . $userid);
		}
		// Lastly, remove token from Post or Get to prevent any conflict in processing
		unset($_POST['redcap_csrf_token'], $_GET['redcap_csrf_token']);
	}

	public static function forceCsrfTokenCheck($redcap_csrf_token)
	{
		global $salt, $userid, $lang;

		// Compare Post/Get token with Session token (should be the same)
		if (!isset($_SESSION['redcap_csrf_token']) || $redcap_csrf_token == null || !in_array($redcap_csrf_token, $_SESSION['redcap_csrf_token']))
		{
			// Default
			$displayError = true;
			// FAIL SAFE: Because of strange issues with the last token not getting saved to the session table,
			// do a check of all possible tokens that could have been created between now
			// and the time of the last token generated. If a match is found, then don't give user the error.
			if ($redcap_csrf_token != null && $redcap_csrf_token != "")
			{
				// Determine number of seconds passed since last token was generated
				$csrf_keys = array_keys(isset($_SESSION) && isset($_SESSION['redcap_csrf_token']) ? $_SESSION['redcap_csrf_token'] : array());
				$lastTokenTime = end($csrf_keys);
				if (empty($lastTokenTime) || $lastTokenTime == "") {
					$sec_ago = 21600; // 6 hours
				} else {
					$sec_ago = strtotime(NOW) - strtotime($lastTokenTime);
				}
				// Find time when the posted token was generated, if can be found
				for ($this_sec_ago = -10; $this_sec_ago <= $sec_ago; $this_sec_ago++)
				{
					$this_ts = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s")-$this_sec_ago,date("m"),date("d"),date("Y")));
					if ($redcap_csrf_token == md5($salt . $this_ts . $userid))
					{
						// Found the token's timestamp, so note it and set flag to not display the error message
						$displayError = false;
						break;
					}
				}
			}
			// Display the error to the user
			if ($displayError)
			{
				// Give error message and stop (fatal error)					
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->PrintHeaderExt();
				$msg = "<p style='font-family:arial,helvetica;margin:20px;background-color:#FAFAFA;border:1px solid #ddd;padding:15px;font-size:13px;max-width:700px;'>
							<img src='".APP_PATH_IMAGES."exclamation.png' style='position:relative;top:3px;'>
							<b style='color:#800000;font-size:14px;'>{$lang['config_functions_64']}</b>
							<br><br>{$lang['config_functions_65']}
							<br><br>{$lang['config_functions_93']}";
				if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
					// Button to go back one page
					$msg .= " <br><br><button onclick=\"history.go(-1);return false;\">&#60;- {$lang['form_renderer_15']}</button>";
				}
				$msg .= "</p>";
				print $msg;
				// If we're inside an iframe, then do a popup alert of this message so that it's visible
				print  "<script type='text/javascript'>
						if (inIframe()) {
							alert('".js_escape(trim(strip_tags($msg)))."');
							window.parent.window.location.reload();
						}
						</script>";
				$objHtmlPage->PrintFooterExt();
				exit;
			}
		}
	}

	// Add CSRF token to all forms on the webpage using jQuery
	public static function createCsrfToken()
	{
		if (isset($_SESSION['redcap_csrf_token']))
		{
			?>
			<script type="text/javascript">
			// Add CSRF token as javascript variable and add to every form on page
			var redcap_csrf_token = '<?php echo self::getCsrfToken() ?>';
			$(function(){ appendCsrfTokenToForm(); });
			</script>
			<?php
		}
	}

	// Retrieve CSRF token from session
	public static function getCsrfToken()
	{
		// Make sure the session variable exists first and is an array
		if (!isset($_SESSION['redcap_csrf_token']) || (isset($_SESSION['redcap_csrf_token']) && !is_array($_SESSION['redcap_csrf_token'])))
		{
			return false;
		}
		// Get last token in csrf token array and return it
		return end($_SESSION['redcap_csrf_token']);
	}

	// Set db sql mode setting and disable "safe updates"
	public static function setDbSqlMode()
	{
		global $rc_connection;
		mysqli_query($rc_connection, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION', SESSION sql_safe_updates = 0");	
	}

	// Checking db character encoding
	public static function checkDbEncoding()
	{
		global $db_character_set, $db_collation, $rc_connection;
		// If db char encoding is not stored, then store it
		if (!isset($db_character_set) || $db_character_set == '') {
			// First, see if we have the encoding/collation in the config table
			list ($db_character_set, $db_collation) = self::getDbEncodingFromTable();
			if ($db_character_set == '' || $db_collation == '') {
				// Encoding not set, so set it in config table
				self::storeDbEncoding();
				return true;
			}
		}
		// Get current connection's char set
		$current_character_set = mysqli_character_set_name($rc_connection);
		if ($db_character_set != $current_character_set) {
			// If char set has changed at some point on the server, then set manually to maintain data consistency
			self::setDbEncoding();
		}
		// Set collation (based on value in redcap_config) - Reordered such that collation is set after encoding.
		if (isset($db_collation) && $db_collation != '') {
			// mysqli_query($rc_connection, "SET collation_connection = '$db_collation'");
			mysqli_query($rc_connection, "SET SESSION collation_connection = '$db_collation'");
		}
		// If we got here, then all is fine
		return true;
	}

	// Set db character encoding to the value stored in the config table to maintain data consistency
	public static function setDbEncoding()
	{
		global $db_character_set, $rc_connection;
		if (!isset($db_character_set) || $db_character_set == '') return false;
		// Set charset
		return mysqli_set_charset($rc_connection, $db_character_set);
	}

	// Get db character encoding and collation stored in config table
	public static function getDbEncodingFromTable()
	{
		// Get charset
		$sql = "select * from redcap_config 
				where field_name in ('db_character_set', 'db_collation')";
		$q = db_query($sql);
		if (!db_num_rows($q)) return array('', '');
		while ($row = db_fetch_assoc($q)) {
			if ($row['field_name'] == 'db_character_set') {
				$db_character_set = $row['value'];
			} else {
				$db_collation = $row['value'];
			}
		}
		return array($db_character_set, $db_collation);
	}

	// Store db character encoding in config table
	public static function storeDbEncoding()
	{
		// Do not do this if config table is empty (which means we are in the middle of installing REDCap)		
		$sql = "select count(1) from redcap_config";
		$q = db_query($sql);
		if (!$q || db_result($q, 0) == 0) return false;
		// Get current char set
		global $rc_connection, $db_character_set, $db_collation;
		$db_character_set = mysqli_character_set_name($rc_connection);
		// Save current char set in table
		$sql = "replace into redcap_config (field_name, value) 
				values ('db_character_set', '".db_escape($db_character_set)."')";
		$a = db_query($sql);
		// Get current collation_connection
		$sql = "show variables where variable_name = 'collation_connection'";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		$db_collation = $row['Value'];
		// Save current char set in table
		$sql = "replace into redcap_config (field_name, value) 
				values ('db_collation', '".db_escape($db_collation)."')";
		$b = db_query($sql);
		// Return on success		
		return ($a && $b);
	}

	// Does db server have utf8mb4 charset?
	public static function dbHasUtf8mb4Encoding()
	{
		$sql = "SHOW CHARACTER SET WHERE Charset = 'utf8mb4'";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

	// Return boolean if REDCap is running on AWS Elastic Beanstalk
	public static function usingAwsElasticBeanstalk()
	{
		// If the following files exist OR if the quickstart config setting is set to "1", this means we're using AWS Elastic Beanstalk
		return ($GLOBALS['aws_quickstart'] == '1' || file_exists('/var/log/eb-activity.log') || file_exists('/var/log/eb-hooks.log'));
	}

    // Return boolean if a Continuous Integration (CI) environment, such as CircleCI
    public static function isCI()
    {
        return isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME']);
    }

	// Kill any currently-running MySQL processes by the current user/session on THIS page.
    // If $countKeepNewest > 0, do not kill the newest X number of processes.
	public static function killConcurrentRequests($windowTimeMinutes=1, $countKeepNewest=0)
	{
		$countKeepNewest = (int)$countKeepNewest;
		$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-$windowTimeMinutes,date("s"),date("m"),date("d"),date("Y")));
		$sql = "select v.log_view_id, r.mysql_process_id from redcap_log_view_requests r, redcap_log_view v 
                where v.log_view_id = r.log_view_id and r.script_execution_time is null 
                and r.ui_id = ".UI_ID." and v.ts > '$xMinAgo' and v.session_id = '".session_id()."'
                and v.page = '".db_escape(PAGE)."' and r.mysql_process_id != '".db_thread_id()."'
                order by v.log_view_id desc";
		$q = db_query($sql);
		$loops = 0;
		while ($row = db_fetch_assoc($q)) {
			$loops++;
			// If we're keeping the newest ones (the first ones here since ordering desc), then keep skipping the kill process until we've hit that count
			if ($countKeepNewest > 0 && $loops < $countKeepNewest) {
				continue;
			}
		    // Kill the MySQL process and also remove from redcap_log_view_requests to help prevent re-running these after they've already been killed
			db_query("KILL ".$row['mysql_process_id']);
			db_query("delete from redcap_log_view_requests where log_view_id = ".$row['log_view_id']);
		}
	}

	// Return the Universal FROM Email address
    public function getUniversalFromAddess()
    {
		global $from_email;
		if (!isEmail($from_email)) $from_email = '';
	    return trim($from_email);
    }

	// Check if an email address matches one listed in the Suppress Universal FROM Email address list of addresses (if enabled)
    // Returning TRUE implies to use the sender's email address and NOT the Universal FROM address
	public static function suppressUniversalFromAddress($email='')
    {
		global $from_email_domain_exclude;

		// Format all to lowercase
		$email = strtolower(trim($email));
		$from_email_domain_exclude = strtolower(trim($from_email_domain_exclude));
		if ($email == '') return false;

		// If no universal from address is set, return true to use sender's address
		$universal_from_email = self::getUniversalFromAddess();
		if ($universal_from_email == '') return true;

		// If the "suppress" setting is not defined, then return false to use the universal from address
		if ($universal_from_email != '' && $from_email_domain_exclude == '') return false;

		// Return true if we have a match
		$from_email_domain_exclude_array = explode("\n", str_replace("\r", "", $from_email_domain_exclude));
		list ($emailFirstPart, $emailDomain) = explode('@', $email, 2);
		return in_array($emailDomain, $from_email_domain_exclude_array);
	}

	// Is the current web server on a recommended PHP version? Return boolean.
	public function isOnRecommendedPhpVersion()
	{
        // Get current PHP version to one decimal place
		$version_pieces = explode(".", PHP_VERSION);
		$version = round(intval($version_pieces[0]).".".intval($version_pieces[1]), 1)."";
		if (strpos($version, ".") === false) $version .= ".0";
		// Is in array?
        return in_array($version, self::$recommendedPhpVersions);
	}

	// This method is tested hourly when the External Modules framework tests run.
	// This should immediately detect breakage (ex: due to the class being renamed).
	public static function isStatementResult($result){
		return get_class($result) === 'ExternalModules\StatementResult';
	}
}
