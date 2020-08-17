<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

use Vanderbilt\REDCap\Classes\Fhir\FhirServices;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FHIRToken;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceBundle;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceCoding;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceOperationOutcome;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceDocumentReference;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceAllergyIntolerance;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceObservation;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceMedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourceCondition;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResourcePatient;
use Vanderbilt\REDCap\Classes\Fhir\Resources\MedicationOrder\Dosage;

class FhirEhr
{	
	// Params for Open Epic (for demo purposes)
	private $openEpicLaunchUrl = 'https://open-ic.epic.com/Argonaut/api/Argonaut/2015/Argonaut/FHIR/LAUNCH/LaunchToken';
	private $openEpicPatient = 'ToHDIzZiIn5MNomO309q0f7TCmnOq6fbqOAWQHA1FRjkB'; // Open Epic test patient "James Kirk"
	// Other FHIR-related settings
	private static $fhirRedirectPage = 'ehr.php';
	private $ehrUser = null; // Capture (if possible) the EHR username during EHR launch
	public $ehrUIID = null;
	public  $fhirPatient = null; // Current patient
	public  $fhirAccessToken = null; // Current FHIR access token
	private $fhirServices = null;
	private $fhirPatientRecord = null; // Current patient's record name when in project context
	public $fhirResourceErrors = array();
	private $fhirDataConditions = array();
	private $fhirDataMeds = array();
	private $fhirDataAllergies = array();
	private $fhirDataDiagnoses = "";

	/**
	 * list of keys that must not be shown on the EHR error page
	 * and in the logs table
	 */
	private static $sensitive_data_keys = array('Authorization');

	/**
	 * Standard Standalone Launch authentication flow
	 */
	const AUTHENTICATION_FLOW_STANDARD = 'standalone_launch';
	/**
	 * OAuth2 client credentials authentication flow (cerner only)
	 */
	const AUTHENTICATION_FLOW_CLIENT_CREDENTIALS = 'client_credentials';

	// Map the FHIR endpoint category to the name of the query string "date" parameter to limit the request's time period.
	// Note: The 'Patient', 'Device', and 'FamilyMemberHistory' categories do not have this structure.
	public static $fhirEndpointQueryStringDateParameter = array(
		// FHIR endpoint => date parameter name using in query string of FHIR request
		'Observation' => 'date', // or 'issued'
		'Condition' => 'onset',
		'MedicationOrder' => 'dateWritten',
		'AllergyIntolerance' => 'onset',
		'DiagnosticReport' => 'date',
		'Immunization' => 'date',
		'Procedure' => 'date',
		'CarePlan' => 'date',
		'DocumentReference' => 'created'
	);
	// Map the FHIR endpoint category to the name of the query string "code" parameter.
	// Note: Most categories do not have this structure.
	public static $fhirEndpointQueryStringCodeParameter = array(
		// FHIR endpoint => date parameter name using in query string of FHIR request
		'Observation' => 'code', // or 'category', Vitals as 'category=vital-signs'
		'Condition' => 'category' // or 'clinicalStatus', Problem List as 'category=problem,complaint,symptom,finding,diagnosis,health-concern' http://argonautwiki.hl7.org/index.php?title=Problems_and_Health_Concerns
	);
	
	// Construct
	public function __construct()
	{
		// Start the session if not started
		\Session::init();
		// Initialization check to ensure we have all we need
		$this->initCheck();
	}

	/**
	 * listen for user actions
	 * TODO: move to it's own controller
	 * - force destroy session data
	 * - create a patient record
	 * - add a project 
	 */
	public function listenForUserActions()
	{		
		// listen for Add/remove project from Registered Project list
		$this->checkAddRemoveProject();

		// detect if user wants to clear session data
		if (isset($_SESSION['username']) && isset($_POST['action']) && $_POST['action'] == 'destroy_fhir_session')
		{
			FhirLauncher::cleanup();
			$response = array('success' => true);
			print json_encode($response);
			exit;
		}

		// detect if we are creating a record for a patient
		if (isset($_SESSION['username']) && defined("PROJECT_ID") && isset($_POST['action']) && $_POST['action'] == 'create_record')
		{
			$patientMrn = $_POST['mrn'];
			$this->createPatientRecord($patientMrn);
			exit;
		}
	}
	
	// Set UI_ID contant (if not set)
	public function setUiId()
	{
	    global $lang, $homepage_contact_email;
		if (isset($this->ehrUIID) && is_numeric($this->ehrUIID)) return true;
		// Determine UI_ID from the username
		if (defined("USERID")) {
			$userInfo = \User::getUserInfo(USERID);
			$this->ehrUIID = $userInfo['ui_id'];
			// If user is suspended, then stop here with an error
			if ($userInfo['user_suspended_time'] != '') {
			    $msg = "{$lang['global_01']}{$lang['colon']} {$lang['config_functions_75']} <b>".USERID."</b>{$lang['period']}
					    {$lang['config_functions_76']} $homepage_contact_email</a>{$lang['period']}";
				exit($msg);
			}
		}
		/**
		 * The cron will not have a user session or
		 * have USERID set; the FhirTokenManager will select the
		 * best token available
		 */
		if (defined("CRON")) {
			$this->ehrUIID = null;
		}
		// Set UI_ID (but not for cron jobs)
		if (!defined("UI_ID") && !defined("CRON") && is_numeric($this->ehrUIID)) {
			define("UI_ID", $this->ehrUIID);
		}
		// Return boolean on if UI_ID is valid
		return is_numeric($this->ehrUIID);
	}
	
	// Set username constant
	public function setUserId()
	{
		if (!isset($_SESSION['username'])) return;
		defined("USERID") or define("USERID", strtolower($_SESSION['username']));
	}
	
	// Return variable name of field having MRN data type
	private function getFieldWithMrnValidationType()
	{
		global $Proj;
		$mrnValTypes = $this->getMrnValidationTypes();
		foreach ($Proj->metadata as $field=>$attr) {
			if ($attr['element_validation_type'] == '') continue;
			if (!isset($mrnValTypes[$attr['element_validation_type']])) continue;
			return $field;
		}
		return false;
	}
	
	// Return array of field validation types with MRN data type
	private function getMrnValidationTypes()
	{
		$mrnValTypes = array();
		$valTypes = getValTypes();
		foreach ($valTypes as $valType=>$attr) {
			if ($attr['data_type'] != 'mrn') continue;
			$mrnValTypes[$valType] = $attr['validation_label'];
		}
		return $mrnValTypes;
	}
	
	// Return the form_name and event_id of the DDP MRN field or the MRN data type field in the current project (if multiple, then return first)
	private function getFormEventOfMrnField()
	{
		global $Proj;
		// If DDP is enabled, then get DDP mapped MRN field
		$DDP = new \DynamicDataPull($Proj->project_id, $Proj->project['realtime_webservice_type']);
		if ($DDP->isEnabledInSystemFhir() && $DDP->isEnabledInProjectFhir()) {
			list ($field, $event_id) = $DDP->getMappedIdRedcapFieldEvent();
		} else {
			$field = $this->getFieldWithMrnValidationType();
			$event_id = $this->getFirstEventForField($field);
		}
		return array($field, $event_id);
	}
	
	// Return the event_id where a field's form_name is first used in a project
	private function getFirstEventForField($field)
	{
		global $Proj;
		// Get field's form
		$form = $Proj->metadata[$field]['form_name'];
		// Loop through events to find first event to which this form is designated
		foreach ($Proj->eventsForms as $event_id=>$forms) {
			if (!in_array($form, $forms)) continue;
			return $event_id;
		}
		return $Proj->firstEventId;
	}
	
	// Create new record for patient in project
	private function createNewPatientRecord($newRecord, $mrn)
	{
		global $Proj;
		// Find the form and event where the MRN field is located
		list ($mrnField, $mrnEventId) = $this->getFormEventOfMrnField();
		// Make sure record doesn't already exist
		if (\Records::recordExists(PROJECT_ID, $newRecord)) exit("ERROR: Record \"$newRecord\" already exists in the project. Please try another record name.");
		// Add record as 2 data points: 1) record ID field value, and 2) MRN field value
		$sql = "insert into redcap_data (project_id, event_id, record, field_name, value) values
				(".PROJECT_ID.", $mrnEventId, '".db_escape($newRecord)."', '".db_escape($Proj->table_pk)."', '".db_escape($newRecord)."'),
				(".PROJECT_ID.", $mrnEventId, '".db_escape($newRecord)."', '".db_escape($mrnField)."', '".db_escape($mrn)."')";
		$q = db_query($sql);
		if ($q) {
			// Logging
			defined("USERID") or define("USERID", strtolower($_SESSION['username']));
			\Logging::logEvent($sql, "redcap_data", "INSERT", $newRecord, "{$Proj->table_pk} = '$newRecord',\n$mrnField = '$mrn'", "Create record");
		}
		// Return boolean for success
		return $q;
	}

	/**
	 * create a record with the new patient
	 *
	 * @param string $patientMrn
	 * @return void
	 */
	private function createPatientRecord($patientMrn)
	{
		global $lang, $auto_inc_set;
		if (empty($patientMrn)) exit("ERROR: Did not receive the MRN!");
		// Get record and MRN values
		$newRecord = $auto_inc_set ? \DataEntry::getAutoId() : $_POST['record'];
		if ($newRecord == '') exit("ERROR: Could not determine the record name for the project!");			
		// Create new record for patient in project
		if ($this->createNewPatientRecord($newRecord, $patientMrn)) {				
			global $Proj;
			$errors = "";
			// If DDP-FHIR is enabled in the project, then go ahead and trigger DDP to start importing data
			if ($Proj->project['realtime_webservice_enabled'] && $Proj->project['realtime_webservice_type'] == 'FHIR') 
			{
				// Fetch DDP data from EHR
				$DDP = new \DynamicDataPull($Proj->project_id, $Proj->project['realtime_webservice_type']);
				list($itemsToAdjudicate, $html) = $DDP->fetchAndOutputData($newRecord, null, array(), $Proj->project['realtime_webservice_offset_days'], 
													$Proj->project['realtime_webservice_offset_plusminus'], false);
				// Any errors?
				if (isset($this->fhirResourceErrors) && !empty($this->fhirResourceErrors)) {
					$errors = "<div class='red' style='margin:10px 0;'><b><i class='fas fa-exclamation-triangle'></i> {$lang['global_03']}{$lang['colon']}</b> {$lang['ws_246']}<ul style='margin:0;'><li>".implode("</li><li>", $this->fhirResourceErrors)."</li></ul></div>";
				}
			}
			// Text to display in the dialog
			print $lang['data_entry_384'] . $errors;
			// Also add hidden field as the new record name value
			print \RCView::hidden(array('id'=>'newRecordCreated', 'value'=>$newRecord));
			// Add note about how many DDP items there are to adjudicate (if any)
			if (isset($itemsToAdjudicate) && $itemsToAdjudicate > 0) {
				print 	\RCView::div(array('style'=>'color:#C00000;margin-top: 10px;'),
							\RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/record_home.php?pid={$Proj->project_id}&id={$newRecord}&openDDP=1", 'style'=>'color:#C00000;'), 
								\RCView::span(array('class'=>'badgerc'), $itemsToAdjudicate) . $lang['data_entry_378']
							)
						);
			}
		} else {
			exit("ERROR: There was an error creating a new record.");
		}
	}

	// Capture EHR user and add it to session if "user" param is in launch query string
	public function getEhrUserFromUrl()
	{
		global $fhir_endpoint_base_url;
		$testWebsitesUsers = array(
			// 'smarthealthit.org' => 'SMART_FAKE_USER',
			'open-ic.epic.com' => 'OPEN_EPIC_FAKE_USER',
		);
		foreach ($testWebsitesUsers as $url => $user) {
			$regExp = sprintf('/%s/i', preg_quote($url, '/'));
			if(preg_match($regExp, $fhir_endpoint_base_url)) return $user;
		}

		// change all key to lowercase to get both user or USER
		$_GET_lower = array_change_key_case($_GET, CASE_LOWER);
		if($user = trim(rawurldecode(urldecode($_GET_lower['user']))))
		{
			return $user;
		}
	}


	/**
	 * store a token in the database
	 *
	 * @param array $token
	 * @param string $user_id
	 * @return boolean
	 */
	private function storeToken($token, $user_id)
	{
		if(!empty($user_id))
		{
			$savedToken = FhirTokenManager::storeToken($token, $user_id);
			return !empty($savedToken);
		}else
		{
			throw new \Exception("Error storing the token: you are not logged in.", 1);	
		}
	}

	/**
	 * get patient data
	 *
	 * @param string $patient_id
	 * @param string $user_id
	 * @param FhirServices $fhir_services
	 * @throws Exception
	 * @return void
	 */
	private function getPatientData($patient_id, $user_id, $fhir_services)
	{
		try {
			$patient_id = urldecode($_GET['fhirPatient']);
			// show the portal if a patient  ID is provided
			$tokenManager = new FhirTokenManager($user_id, $patient_id);
			if($token = $tokenManager->getToken())
			{
				if(!$access_token = $token->access_token) throw new \Exception("No valid access token available", 1);
				
				$patientData = $fhir_services->getPatientDemographics($access_token, $patient_id);
				// save patientData in session so it can be used inside render functions
				// Get institution-specific MRN
				$institutionMrn = $this->getPatientMrnFromPatientData($patientData, $GLOBALS['fhir_ehr_mrn_identifier']);
				
				// If there is an institution-specific MRN, then store in access token table to pair it with the patient id
				if ($institutionMrn) {
					$tokenManager->removeMrnDuplicates($institutionMrn);
					$tokenManager->storePatientMrn($patient_id, $institutionMrn);
				}
				return $patientData;
			}
			throw new \Exception('No tokens available', 0);
		} catch (\Exception $e) {
			$data = array(
				'patient_id' => $patient_id,
			);
			throw new \DataException($e->getMessage(), $data, $e->getCode());
		}
	}

	// Perform FHIR launch via EHR
	public function launchFromEhr()
	{
		global $fhir_endpoint_base_url, $fhir_client_id, $fhir_client_secret, $fhir_standalone_authentication_flow;

		// Instantiate FHIR Services
		$fhir_services = self::getFhirServices();
		$redirect_uri = self::getFhirRedirectUrl();
		// create a launcher
		$launcher = new FhirLauncher($fhir_services, $redirect_uri);
		$launcher->checkAutoLogin(); // check if an autologin can be performed

		$mode = $launcher->getMode();

		// session data must be empty when starting a launch
		if(in_array($mode, FhirLauncher::$launch_modes)) FhirLauncher::cleanup();
		if(in_array($mode, FhirLauncher::$protected_modes))
		{
			// user must be logged in to proceed
			$user_id = self::getUserID();
			if(empty($user_id)) loginFunction(); 
		}

		try {
			switch ($mode) {
				case FhirLauncher::MODE_CLIENT_CREDENTIALS:
					$token = $launcher->clientCredentialFlow($fhir_services);
					$launcher->processToken($token);
					$user_id = self::getUserID();
					if($this->storeToken($token, $user_id))
					{
						\Logging::logEvent( "", "FHIR", "MANAGE", "", "", "FHIR Token info saved to database.");
					}
					$launch_page = $launcher->getLaunchPage();
					$app_path_webroot_full = APP_PATH_WEBROOT_FULL;
					return print \Renderer::run('ehr.token', compact('token', 'launch_page', 'app_path_webroot_full'));
					break;
				case FhirLauncher::MODE_STANDALONE_LAUNCH:
					\Logging::logEvent( "", "FHIR", "MANAGE", "", \Logging::printArray($_GET), "Starting standalone launch" );
					$launcher->standaloneLaunchFlow();
					break;
				case FhirLauncher::MODE_AUTHORIZE:
					// Log event $sql, $object_type, $event, $record, $data_values, $description
					\Logging::logEvent( "", "FHIR", "MANAGE", "", \Logging::printArray($_GET), "Received launch code from EHR" );
					// the launch code is used to get an authorization code
					$launcher->authorize();
					break;
				case FhirLauncher::MODE_TOKEN:
					// the authorization code is used to get an access token 
					\Logging::logEvent( "", "FHIR", "MANAGE", "", \Logging::printArray($_GET), "Exchanging FHIR code for authorization token" );
					$token = $launcher->getToken();
					$launcher->processToken($token);
					$user_id = self::getUserID();
					if($this->storeToken($token, $user_id))
					{
						\Logging::logEvent( "", "FHIR", "MANAGE", "", "", "FHIR Token info saved to database.");
					}
					// launch is over and token is stored
					if($patient_fhir_id = $token->patient)
					{
						// have patient ID; rto the launch page and show the portl
						$redirect_url = $_SERVER['PHP_SELF']."?fhirPatient=".urlencode($patient_fhir_id);
						\HttpClient::redirect($redirect_url);
					}else
					{
						// no patient available in the token;
						// we are probably here after a standalone launch
						$launch_page = $launcher->getLaunchPage();
						$app_path_webroot_full = APP_PATH_WEBROOT_FULL;
						return print \Renderer::run('ehr.token', compact('token', 'launch_page', 'app_path_webroot_full'));
					}
					break;
				case FhirLauncher::MODE_ERROR:
					$error = $launcher->getError();
					if($error->url)
					{
						$error_link = sprintf('<a href="%s" target="_BLANK">%s</a>', $error->url, $error->message);
						$data = array('error_link' => $error_link);
					}else {
						$data = array('error' => $error->message);
					}
					throw new \DataException("Error Processing Request", $data, 1);
					break;
				case FhirLauncher::MODE_SHOW_PORTAL:
					$patient_id = $_GET['fhirPatient'];
					$patientData = $this->getPatientData($patient_id, $user_id, $fhir_services);
					$launcher->setSessionData('patient-data', $patientData); // will be used in portal and navbar
					return $this->renderFhirPortal($patientData);
					break;
				case FhirLauncher::MODE_NONE:
				default:
						//show the default page
						$authentication_flow = $fhir_standalone_authentication_flow;
						$app_path_webroot= APP_PATH_WEBROOT;
						return print \Renderer::run('ehr.index', compact('authentication_flow', 'app_path_webroot'));
					break;
			}
		} catch (\Exception $e) {
			$exception_code = $e->getCode();
			$exception_message = $e->getMessage();
			$exception_data = array();
			if($e instanceof \DataException) $exception_data = $e->getData();

			// SEARCH AND ANONIMIZE SENSITIVE DATA
			$cleaned_exception_data = self::removeSensitiveData($exception_data, self::$sensitive_data_keys);

			\Logging::logEvent( "", "FHIR", "MANAGE", $cleaned_exception_data, $exception_code, $exception_message );
			$launcher->cleanup(); // cleanup

			$variables = array(
				'code' => $exception_code,
				'message' => $exception_message,
				'data' => $cleaned_exception_data,
			);
			print \Renderer::run('ehr.error', $variables);
		}
		return;
	}

	/**
	 * remove sensitive data from an array
	 * looking for specific keys
	 *
	 * @param array $data
	 * @param array $keys
	 * @return array data with no sensitive data
	 */
	private static function removeSensitiveData($data, $keys)
	{
		// helper function:
		// anonimize matched keys
		$anonimize = function(&$item, $key) use($keys)
		{
			foreach($keys as $protected_key)
			{
				$regexp = "/{$protected_key}/i";
				if(preg_match($regexp, $key, $matches))
				{
					$length = strlen($item);
					$item = str_repeat('*', $length);
				}
			}
		};
		array_walk_recursive($data, $anonimize);
		return $data;
	}

    // Display page embedded in the patient portal
    private function renderPatientPortal($patientData)
    {
		global $lang;
		
        $patientFirstName = $patientData->name[0]->given[0];
        $patientLastName = $patientData->name[0]->family[0];
        $patientBirthDate = $patientData->birthDate;
        $patientId = $patientData->id;
        // Get institution-specific MRN
        $patientMrn = $this->getPatientMrnFromPatientData($patientData, $GLOBALS['fhir_ehr_mrn_identifier']);
		if(empty($patientMrn)) $patientMrn = $patientId;
		
        print  "This page is an embedded REDCap page inside the patient portal.<br>
                <br>Patient MRN: {$patientMrn}
                <br>Patient name: {$patientFirstName} {$patientLastName}
                <br>Patient DOB: {$patientBirthDate}";
	}
	
	/**
	 * Return the institution-specific version of the MRN (e.g., $system_string='urn:oid:1.2.5.8.2.7' for Vanderbilt MRN)
	 *
	 * @param string $patient_info
	 * @param string $system_string
	 * @return string|false
	 */
	private function getPatientMrnFromPatientData($patient_info, $system_string='')
	{
		if (!empty($system_string) && is_object($patient_info) && isset($patient_info->identifier)) {
			foreach ($patient_info->identifier as $identifier) {
				if (isset($identifier->system) && $identifier->system == $system_string && isset($identifier->value)) {
					return $identifier->value;
				}
			}
		}
		return false;
	}

	public static function getUserID()
	{
		if ($GLOBALS['auth_meth_global'] == 'none') {
			$_SESSION['username'] = 'site_admin';
		}
		\Session::init();
		if (!isset($_SESSION['username'])) return;
		if(!defined("USERID")) define("USERID", strtolower($_SESSION['username']));
		$user_id = \User::getUIIDByUsername(USERID);
		/* $user_info = (object)\User::getUserInfo($id=USERID);
		$user_id = $user_info->ui_id; */
		return $user_id;
	}
	
	// Display page with EHR user and patient in context
	private function renderFhirPortal($patientData)
	{
		global $lang;
		// retrieve patient data from the session
		if(!$patientData)
			throw new \Exception("Error: no patient data has been found", 1);
		
        $patientID = $patientData->id;
        // Get institution-specific MRN
		$patientMrn = $this->getPatientMrnFromPatientData($patientData, $GLOBALS['fhir_ehr_mrn_identifier']);
		if(empty($patientMrn)) $patientMrn = $patientID; // use patient ID if no MRN is found
		
		// Get array of MRN field validation types
		$mrnValidationTypes = $this->getMrnValidationTypes();
		$user_id = self::getUserID();
		
		// Create arrays of registered and unregistered projects for the current user
		$registeredProjects = $this->setRegisteredProjects($patientMrn, $user_id);
		$unregisteredProjects = $this->setUnegisteredProjects($user_id);
			
		// Render page and navbar
		$HtmlPage = new \HtmlPage();
		$HtmlPage->PrintHeaderExt();
		$this->renderNavBar($patientData);

		$variables = array(
			'patientID' => $patientID,
			'patientMrn' => $patientMrn,
			'lang' => $lang,
			'registeredProjects' => $registeredProjects,
			'unregisteredProjects' => $unregisteredProjects,
			'app_path_webroot' => APP_PATH_WEBROOT,
			'mrnValidationTypes' => $mrnValidationTypes,
		);
		if(empty($_GET['fhirPatient']))
		{
			// update the URL
			$symbol = strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?'; //symbol to connect patient ID with URL
			$modifyScriptURL = "{$_SERVER['REQUEST_URI']}{$symbol}fhirPatient={$patientID}";
			$variables['modifyScriptURL'] = $modifyScriptURL;
		}
		print \Renderer::run('ehr.portal', $variables);
		
		if(SUPER_USER) echo \Renderer::run('ehr.patient-identifier', array('identifiers' => $patientData->identifier)); //  $this->printPatientIdentifier($patientData);
		// Footer
		$HtmlPage->PrintFooterExt();
	}
	
	// Render navbar
	public function renderNavBar($patientData)
	{
		global $lang;
		if(empty($patientData))
			throw new \Exception("Error: no patient data has been found", 1);

        $patientFirstName = $patientData->name[0]->given[0];
        $patientLastName = $patientData->name[0]->family[0];
        $patientBirthDate = $patientData->birthDate;
		$patientID = $patientData->id;
		$ehr_user = FhirLauncher::getSessionData('ehr_user');
        // Get institution-specific MRN
		$patientMrn = $this->getPatientMrnFromPatientData($patientData, $GLOBALS['fhir_ehr_mrn_identifier']);
		// set template variables
		$variables = array(
			'lang' => $lang,
			'patientFirstName' => $patientFirstName,
			'patientLastName' => $patientLastName,
			'patientBirthDate' => $patientBirthDate,
			'patientID' => $patientID,
			'patientMrn' => $patientMrn,
			'app_path_webroot' => APP_PATH_WEBROOT,
			'app_path_images' => APP_PATH_IMAGES,
			'ehr_user' => $ehr_user,
			'user' => $_SESSION['username'],
		);
		if (defined("PROJECT_ID")) $variables['app_title'] = $GLOBALS['app_title'];
		print \Renderer::run('ehr.navbar', $variables);
    }
	
	// Add/remove project from Registered Project list, then redirect back to prev page.
	private function checkAddRemoveProject()
	{
		
		$user_id = self::getUserID();
		$redirect_url = self::getFhirRedirectUrl();
		if($patient_id = urldecode($_GET['fhirPatient'])) $redirect_url .= "?fhirPatient={$patient_id}";
		// Add
		if (isset($_GET['addProject']) && is_numeric($_GET['addProject'])) {
			if(empty($user_id)) loginFunction(); // user must be logged in to proceed
			$sql = "insert into redcap_ehr_user_projects (project_id, redcap_userid) values ('".db_escape($_GET['addProject'])."', ".$user_id.")";
			if (db_query($sql)) \HttpClient::redirect($redirect_url);
		}		
		// Remove
		elseif (isset($_GET['removeProject']) && is_numeric($_GET['removeProject'])) {
			if(empty($user_id)) loginFunction(); // user must be logged in to proceed
			$sql = "delete from redcap_ehr_user_projects where project_id = '".db_escape($_GET['removeProject'])."' and redcap_userid = ".$user_id;
			if (db_query($sql)) \HttpClient::redirect($redirect_url);
		}
	}
	
	// Query table to determine if REDCap username has been allowlisted for DDP on FHIR
	public function isDdpUserAllowlistedForFhir($username)
	{		
		$sql = "select 1 from redcap_ehr_user_map m, redcap_user_information i
				where i.ui_id = m.redcap_userid and i.username = '".db_escape($username)."'";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}
	
	/**
	 * Query table to determine if REDCap username has been allowlisted for Data Mart project creation rights.
	 * Super users are allowed by default.
	 * 
	 */
	public function isDdpUserAllowlistedForDataMart($username)
	{		
		$sql = "SELECT 1 FROM redcap_user_information WHERE username = '".db_escape($username)."'
				AND (super_user = 1 OR fhir_data_mart_create_project = 1)";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}
	
	// Obtain the FHIR redirect URL for this external module (assumes that page=index is the main launching page)
	public static function getFhirRedirectUrl()
	{
		return APP_PATH_WEBROOT_FULL . self::$fhirRedirectPage;
	}
	
	// Obtain the FHIR service endpoint base URL
	public static function getFhirEndpointBaseUrl()
	{
		global $fhir_endpoint_base_url;
		// If we are launching and have launch and iss params, then override with iss param
		if (isset($_GET['launch']) && isset($_GET['iss']))
		{
			$fhirEndpoint = rawurldecode(urldecode($_GET['iss']));
		}
		// Get endpoint from module config. Also, add it to session to keep
		else {
			$fhirEndpoint = $fhir_endpoint_base_url;
		}
		// Ensure the endpoint ends with a slash "/"	
		if (substr($fhirEndpoint, -1) != "/") $fhirEndpoint .= "/";
		// Return the endpoint
		return $fhirEndpoint;
	}
	
	// Initialization check to ensure we have all we need
	private function initCheck()
	{
		$errors = array();
		if (empty($GLOBALS['fhir_client_id'])) {
			$errors[] = "Missing the FHIR client_id! Please add value to module configuration.";
		}
		if (empty($GLOBALS['fhir_endpoint_base_url'])) {
			$errors[] = "Missing the FHIR endpoint base URL! Please add value to module configuration.";
		}
		if (!empty($errors)) {
			throw new \Exception("<br>- ".implode("<br>- ", $errors));
		}	
	}

	/**
	 * get FhirServices
	 *
	 * @return FhirServices
	 */
	public static function getFhirServices($endpoint=null)
	{
			global $fhir_endpoint_base_url, $fhir_client_id, $fhir_client_secret;
			$endpoint = $endpoint ?: $fhir_endpoint_base_url;
			return new FhirServices($endpoint, $fhir_client_id, $fhir_client_secret);
	}

	/**
	 * get the FHIR id of a patient using the MRN number
	 *
	 * @param string $mrn
	 * @throws Exception if the patient ID is not found
	 * @return string
	 */
	public function getPatientIdFromMrn($mrn)
	{
		global $fhir_ehr_mrn_identifier;
		if(empty($fhir_ehr_mrn_identifier)) return $mrn; //the MRN is also the FHIR ID

		$query_string = "SELECT * FROM redcap_ehr_access_tokens WHERE mrn = ".checkNull($mrn)." LIMIT 1";
		$result = db_query($query_string);

		if ($token=db_fetch_object($result)) return $token->patient;

		// patient not in database; retrieve it using remote web service
		$patient = $this->getPatientIdFromMrnWebService($mrn); // retrieve FHIR ID 

		return $patient;
	}

	/**
	 * check if a project has EHR servvices enabled
	 *
	 * @param integer $project_id
	 * @return boolean
	 */
	public static function isFhirEnabledInProject($project_id)
	{
		$project = new \Project($project_id);
		$realtime_webservice_enabled = $project->project['realtime_webservice_enabled'];
		$realtime_webservice_type = $project->project['realtime_webservice_type'];
		$datamart_enabled = $project->project['datamart_enabled'];
		return ( $datamart_enabled==true || ($realtime_webservice_enabled==true && $realtime_webservice_type=='FHIR') );
	}

	/**
	 * render the menu for the FHIR tools
	 *
	 * @param string $menu_id
	 * @param boolean $collapsed 
	 * @return string
	 */
	public static function renderFhirLaunchModal()
	{
		global $lang, $fhir_standalone_authentication_flow, $fhir_source_system_custom_name;
		$autorization_flow = $fhir_standalone_authentication_flow;
		// exit if we are in client credentials authentication mode or if standalone launch is not enabled
		if( $autorization_flow != self::AUTHENTICATION_FLOW_STANDARD) return;
		
		// get token 
		$user_id = FhirEhr::getUserID();
		$tokenManager = new FhirTokenManager($user_id);
		$token = $tokenManager->getToken();
		$token_found = $token instanceof FhirToken;
		$token_valid =  $token_found and $token->isValid();

		// exit if the token is valid
		if($token_valid) return;

		$data = array(
			'lang' => $lang,
			'autorization_flow' => $autorization_flow,
			'ehr_system_name' => strip_tags($fhir_source_system_custom_name),
			'app_path_webroot' => APP_PATH_WEBROOT,
		);
		$modal = \Renderer::run('ehr.launch_modal', $data);
		return $modal;
	}

	/**
	 * Use any access token and an MRN to obtain a patient id value
	 * Cerner requires URN:OID to be included
	 * @see https://fhir.cerner.com/millennium/dstu2/individuals/patient/#parameters
	 * @see https://www.hl7.org/fhir/dstu2/search.html#token
	 * @see https://www.hl7.org/fhir/dstu2/datatypes.html#Identifier
	 *
	 * @param string $mrn
	 * @throws Exception if the patient ID is not found
	 * @return string the patient FHIR ID
	 */
	public function getPatientIdFromMrnWebService($mrn)
	{
		global $userid, $fhir_ehr_mrn_identifier, $fhir_endpoint_base_url;
		// add slash at the end if missing
		$base_url = preg_replace('/(.+[^\/]$)/', '$1/', $fhir_endpoint_base_url);

		if ($fhir_ehr_mrn_identifier == '') {
			$message = sprintf("Unable to find a FHIR ID for the MRN %s. A string patient identifier has not been setup", $mrn);
			throw new \Exception($message, 1);
		};
		// get an access token for the current user (could be null in a CRON job)
		$ui_id = \User::getUIIDByUsername($userid);
        $tokenManager = new FhirTokenManager($ui_id);
		$token = $tokenManager->getToken(); // get any valid token
		if(!$token || !$access_token=$token->getAccessToken()) {
			$message = sprintf("Unable to find a FHIR ID for the MRN %s. No valid access token available.", $mrn);
			throw new \Exception($message, 1);
		}
		
		// Remove "urn:oid:" from beginning of identifier->system	
		// $identifierSystem = str_replace("urn:oid:", "", $fhir_ehr_mrn_identifier);
		$identifierSystem = $fhir_ehr_mrn_identifier; //Cerner requires URN:OID to be included
		$url = $base_url . "Patient?identifier=".urlencode("$identifierSystem|$mrn");
		$fhir_services = self::getFhirServices();
		$data = $fhir_services->getFhirData($url, $access_token);
		if (!isset($data->resourceType) || $data->resourceType != 'Bundle' || $data->total != '1') {
			$message = sprintf("Unable to find a FHIR ID for the MRN %s. Invalid data returned from the endpoint", $mrn);
			throw new \Exception($message, 1);
		};
		// Set patient id
		$patient_id = $data->entry[0]->resource->id;
		if(!$patient_id) {
			$message = sprintf("unable to find a FHIR ID for the MRN %s", $mrn);
			throw new \Exception($message, 1);
		}
		$tokenManager->removeMrnDuplicates($mrn);
		$this->storePatientID($patient_id, $mrn);
        
		// Return the patient id
		return $patient_id;
	}

	/**
	 * store patient FHIR ID and MRN for reference
	 *
	 * @param string $patient_id
	 * @param string $mrn
	 * @return FhirToken
	 */
	private function storePatientID($patient_id, $mrn)
	{
		$tokenData = array(
			'patient' => $patient_id,
			'mrn' => $mrn,
		);
		return FhirTokenManager::storeToken($tokenData, null);
	}
	
	// Obtain an array (pid=>array(attributes)) of the current user's registered projects
	private function setRegisteredProjects($mrn, $user_id)
	{
		// Query projects
		// $sql = "select p.project_id, if (u.role_id is null, u.record_create, (select ur.record_create from redcap_user_roles ur 
					// where ur.role_id = u.role_id and ur.project_id = p.project_id)) as record_create, 
				// p.auto_inc_set as record_auto_numbering, p.app_title, if (x.project_id is null, 0, 1) as has_mrn_field_type,
				// if (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR', 1, 0) as ddp_enabled, 				
				// if (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR', d2.record, d.record) as record,
				// if (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR', r.item_count, null) as ddp_items
				// from redcap_user_rights u, redcap_user_information i, redcap_ehr_user_projects e, redcap_projects p
				// left join (select m.project_id, m.field_name from redcap_metadata m, redcap_validation_types v, redcap_user_rights u2, redcap_user_information i2 
					// where v.data_type = 'mrn' and m.element_validation_type = v.validation_name and u2.project_id = m.project_id 
					// and u2.username = i2.username and i2.ui_id = '".db_escape($this->ehrUIID)."') x on p.project_id = x.project_id
				// left join redcap_data d on d.project_id = p.project_id and d.field_name = x.field_name 
					// and d.value = '".db_escape($_SESSION['ehr-fhir']['patientInfo'][$this->fhirPatient]['fhirPatientMRN'])."'
				// left join redcap_ddp_mapping dm on dm.project_id = p.project_id and dm.is_record_identifier = 1
				// left join redcap_data d2 on d2.project_id = p.project_id and d2.field_name = dm.field_name 
					// and d2.value = '".db_escape($_SESSION['ehr-fhir']['patientInfo'][$this->fhirPatient]['fhirPatientMRN'])."'				
				// left join redcap_ddp_records r on r.project_id = p.project_id and r.record = d2.record
				// where e.redcap_userid = i.ui_id and p.project_id = e.project_id and p.date_deleted is null 
					// and u.project_id = p.project_id and u.username = i.username and i.ui_id = '".db_escape($this->ehrUIID)."'
					// and p.status in (0, 1)
				// order by p.project_id";
		$sql = "select p.project_id, if (u.role_id is null, u.record_create, (select ur.record_create from redcap_user_roles ur 
					where ur.role_id = u.role_id and ur.project_id = p.project_id)) as record_create, 
				p.auto_inc_set as record_auto_numbering, p.app_title,
				if (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR', 1, 0) as ddp_enabled, 				
				if (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR', d2.record, null) as record,
				if (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR', r.item_count, null) as ddp_items
				from redcap_user_rights u, redcap_user_information i, redcap_ehr_user_projects e, redcap_projects p
				left join redcap_ddp_mapping dm on dm.project_id = p.project_id and dm.is_record_identifier = 1
				left join redcap_data d2 on d2.project_id = p.project_id and d2.field_name = dm.field_name 
					and d2.value = '".db_escape($mrn)."'				
				left join redcap_ddp_records r on r.project_id = p.project_id and r.record = d2.record
				where e.redcap_userid = i.ui_id and p.project_id = e.project_id and p.date_deleted is null 
					and u.project_id = p.project_id and u.username = i.username and i.ui_id = '".db_escape($user_id)."'
					and p.status in (0, 1)
				order by p.project_id";
		$q = db_query($sql);
		$projects = array();
		while ($row = db_fetch_assoc($q)) {
			$pid = $row['project_id'];
			unset($row['project_id']);
			$row['app_title'] = strip_tags($row['app_title']);
			// Add to array
			$projects[$pid] = $row;
		}
		return $projects;
	}
	
	// Obtain an array (pid=>title) of the current user's UNregistered projects.
	// Separate viable and non-viable projects in sub-arrays
	private function setUnegisteredProjects($user_id)
	{
		global $lang;
		
		// $sql = "select p.project_id, p.app_title,
				// if ((x.project_id is not null or (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR')), 1, 0) as viable
				// from (redcap_user_rights u, redcap_user_information i, redcap_projects p)
				// left join redcap_ehr_user_projects e on e.project_id = p.project_id and e.redcap_userid = i.ui_id
				// left join (select m.project_id, m.field_name from redcap_metadata m, redcap_validation_types v, redcap_user_rights u2, redcap_user_information i2 
					// where v.data_type = 'mrn' and m.element_validation_type = v.validation_name and u2.project_id = m.project_id 
					// and u2.username = i2.username and i2.ui_id = '".db_escape($this->ehrUIID)."') x on p.project_id = x.project_id
				// where p.date_deleted is null and u.project_id = p.project_id and u.username = i.username 
					// and e.redcap_userid is null and i.ui_id = '".db_escape($this->ehrUIID)."' and p.status in (0, 1) 
				// order by if ((x.project_id is not null or (p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR')), 1, 0) desc, p.project_id";
		$sql = "select p.project_id, p.app_title,
				if ((p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR'), 1, 0) as viable
				from (redcap_user_rights u, redcap_user_information i, redcap_projects p)
				left join redcap_ehr_user_projects e on e.project_id = p.project_id and e.redcap_userid = i.ui_id
				where p.date_deleted is null and u.project_id = p.project_id and u.username = i.username 
					and e.redcap_userid is null and i.ui_id = '".db_escape($user_id)."' and p.status in (0, 1) 
				order by if ((p.realtime_webservice_enabled = '1' and p.realtime_webservice_type = 'FHIR'), 1, 0) desc, p.project_id";
		$q = db_query($sql);
		$projects = array();
		while ($row = db_fetch_assoc($q)) {
			$row['app_title'] = strip_tags($row['app_title']);
			$viableText = $row['viable'] ? $lang['data_entry_395'] : $lang['data_entry_396'];
			$projects[$viableText][$row['project_id']] = strip_tags($row['app_title']);
		}
		return $projects;
	}
	

	// Parse the FHIR data returned from a FHIR service and return it as a flattened array
	public function parseFhirData($fhir_data, $fieldsRequested=array(), $endpoint='', $returnAllFieldsWithLabel=false, $isDataMart=false)
	{
		global $fhir_include_email_address, $fhir_include_email_address_project;
		// define the new line separator for repeated instances (vitals, labs, allergies, medications)
		$new_line_separator = "\r\n-----\r\n";
		// Place all flattened data into array
		$fhirArray = array();
		// If not find resource type, return
		$resourceType = $fhir_data->resourceType;
		if (!isset($resourceType)) return $fhirArray;
		// Parse FHIR bundle
		switch ($resourceType) {
			case 'Bundle':
				// Loop through all resources in the bundle
				foreach (array_keys($fhir_data->entry) as $entry_key) {
					// Skip if we can't find the correct structure
					if (!isset($fhir_data->entry[$entry_key]->resource)) continue;
					// Recursive call to parse individual resources in this bundle
					$fhirArray = array_merge($fhirArray, $this->parseFhirData($fhir_data->entry[$entry_key]->resource, $fieldsRequested, $endpoint, $returnAllFieldsWithLabel, $isDataMart));
				}
				break;
			case 'OperationOutcome':
				$operationOutcome = new FhirResourceOperationOutcome($fhir_data);
				$issues = $operationOutcome->getIssues();
				
				// Parse error message
				foreach ($issues as $issue) {
					// Skip warnings
					if (isset($issue->severity) && strtolower($issue->severity) == 'warning') {
						continue;
					}
					// Collect error message
					if (isset($issue->details->coding[0])) {
						$this->fhirResourceErrors[] = "Error code <b>".$issue->details->coding[0]->code."</b> on FHIR endpoint \"<b>$endpoint</b>\": ".$issue->details->coding[0]->display;
					}
					elseif (isset($issue->details->text)) {
						$this->fhirResourceErrors[] = "Error on FHIR endpoint \"<b>$endpoint</b>\": ".$issue->details->text;
					}
				}
				break;
			case 'Patient':
				// Parse patient demographics
				$patient = new FhirResourcePatient($fhir_data);
				$resource_type = $patient->getResourceType();
				$patient_values = array(
					'fhir_id' => $patient->id,
					'name-given' => $patient->getNameGiven(),
					'name-family' => $patient->getNameFamily(),
					'birthDate' => $patient->getBirthDate(),
					'gender' => $patient->getGender(),
					'race' => $patient->getRace(),
					'ethnicity' => $patient->getEthnicity(),
					'address-line' => $patient->getAddressLine(),
					'address-city' => $patient->getAddressCity(),
					'address-state' => $patient->getAddressState(),
					'address-postalCode' => $patient->getAddressPostalCode(),
					'address-country' => $patient->getAddressCountry(),
					'phone-home' => $patient->getPhoneHome(),
					'phone-mobile' => $patient->getPhoneMobile(),
					'phone-mobile' => $patient->getPhoneMobile(),
					'deceasedBoolean' => intval($patient->isDeceased()), // cast to int
					'preferred-language' => $patient->getPreferredLanguage(),
				);
				// EMAIL
				if ($fhir_include_email_address == '1' || $fhir_include_email_address_project == '1') {
					$patient_values['email'] = $patient->getEmail();
				}

				foreach ($patient_values as $key => $value) {
					if(!isset($value)) continue; // check if a value is found
					$fhirArray[] = array('resourceType'=>$resource_type,'field'=>$key,'value'=>$value, 'timestamp'=>null);
				}

				break;
			case 'MedicationOrder':
				$medicationOrder = new FhirResourceMedicationOrder($fhir_data);
				$resource_type = $medicationOrder->getResourceType();
				$status = $medicationOrder->getStatus();
				
				// Add to field data array
				if ($isDataMart) {
					$doses = $medicationOrder->getDoses(); // get the doses for the current medication
					$firstDose = reset($doses); // get the first dose
					$dosage = is_a($firstDose, Dosage::class) ? $firstDose->getText() : '';
					$display = $medicationOrder->getText();
					$timestamp = $medicationOrder->getFormattedDate();
					$data = array(
						'fhir_id'=>$medicationOrder->id,
						'resourceType'=>$resource_type,
						'display'=>$display,
						'dosage'=>$dosage,
						'timestamp'=>$timestamp,
						'status'=>$status
					);
					// define prefix to append to 'code' and 'display' keys for datamart
					$standards_mapping = array(
						FhirResourceCoding::SYSTEM_RxNorm => 'rxnorm',
					);
					foreach ($medicationOrder->getCodings() as $coding) {
						$standard = $coding->getStandard();
						if($prefix = $standards_mapping[$standard])
						{
							$data[$prefix.'_code'] = $coding->getCode();
							$data[$prefix.'_display'] = $coding->getDisplay();
						}
					}
					$fhirArray[] = $data;
				} else {
					//dynamically set the target field based on the medication status
					$targetField = sprintf('%s-medications-list', $status);
					$codeDisplay = $medicationOrder->getlongText();
					// make sure to create an array for each status
					if(!is_array($this->fhirDataMeds[$targetField])) $this->fhirDataMeds[$targetField] = array();
					// store the current status
					$this->fhirDataMeds[$targetField][] = $codeDisplay;
					// join all status of a type in a string 
					$medications_value = implode($new_line_separator, $this->fhirDataMeds[$targetField]);
					$fhirArray[$targetField] = array('field' => $targetField, 'value' => $medications_value, 'timestamp' => null);
				}
				break;
			case 'Condition':
				$condition = new FhirResourceCondition($fhir_data);
				$resource_type = $condition->getResourceType();

				if ($isDataMart) {
					$status = $condition->getClinicalStatus();
					$timestamp = $condition->getDateRecorded();
					$data = array(
						'fhir_id'=>$condition->id,
						'resourceType'=>$resource_type,
						'timestamp'=>$timestamp,
						'clinical_status'=>$status
					);
					// define prefix to append to 'code' and 'display' keys for datamart
					$standards_mapping = array(
						FhirResourceCoding::SYSTEM_ICD_9_CM => 'icd9',
						FhirResourceCoding::SYSTEM_ICD_10_CM => 'icd10',
						FhirResourceCoding::SYSTEM_SNOMED_CT => 'snomed',
					);
					foreach ($condition->getCodings() as $coding) {
						$standard = $coding->getStandard();
						if($prefix = $standards_mapping[$standard])
						{
							$data[$prefix.'_code'] = $coding->getCode();
							$data[$prefix.'_display'] = $coding->getDisplay();
						}
					}
					$fhirArray[] = $data;
				} else {
					// $targetField = 'problem-list-'.$status; // TODO: implement mapping of multiple status
					$targetField = 'problem-list';
					$codeDisplay = $condition->getLongText();
					$this->fhirDataConditions[] = $codeDisplay;
					$conditions_value = implode($new_line_separator, $this->fhirDataConditions);
					/* // TODO: watch for string too long
					if(strlen($conditions_value)>=65535) $conditions_value = 'WARNING: this string is too long and cannot be saved.';
					// END TODO */
					$fhirArray[$targetField] = array('resourceType'=>$resource_type,'field' => $targetField, 'value' => $conditions_value, 'timestamp' => null);
				}

				break;
			case 'AllergyIntolerance':
				$allergy = new FhirResourceAllergyIntolerance($fhir_data);
				$resource_type = $allergy->getResourceType();

				if ($isDataMart) {
					$timestamp = $allergy->getFormattedDate('Y-m-d');
					$data = array(
						'fhir_id'=>$allergy->id,
						'resourceType'=>$resource_type,
						'timestamp'=>$timestamp,
					);
					// define prefix to append to 'code' and 'display' keys for datamart
					$standards_mapping = array(
						FhirResourceCoding::SYSTEM_FDA_UNII => 'unii',
						FhirResourceCoding::SYSTEM_NDF_RT => 'ndfrt',
						FhirResourceCoding::SYSTEM_SNOMED_CT => 'snomed',
					);
					foreach ($allergy->getCodings() as $coding)
					{
						$standard = $coding->getStandard();
						if($prefix = $standards_mapping[$standard])
						{
							$data[$prefix.'_code'] = $coding->getCode();
							$data[$prefix.'_display'] = $coding->getDisplay();
						}
					}
					$fhirArray[] = $data;
				} else {
					$targetField = 'allergy-list';
					$codeDisplay = $allergy->getLongText();
					$this->fhirDataAllergies[] = $codeDisplay;
					$allergies_value = implode($new_line_separator, $this->fhirDataAllergies);
					$fhirArray[$targetField] = array('field' => $targetField, 'value' => $allergies_value, 'timestamp' => null);
				}
				break;
			case 'Observation':
				$observation = new FhirResourceObservation($fhir_data);
				$resourceType = $observation->getResourceType();
				$timestamp = $observation->getFormattedDate();
				// get all values for the current observation (\FhirResourceObservation\Value[])
				$values = $observation->getValues();
				foreach ($values as $observation_value)
				{
					$field = $observation_value->getCode();
					$display = $observation_value->getDisplay();
					$value_object = $observation_value->getValue(); // contains all data
					$value = $observation_value->getTextValue(); // text version of the value
					// Add data value and timestamp
					if ($returnAllFieldsWithLabel) {
						if ($display == 'Vital signs') continue; // Generic and means nothing, so skip
						$fhirArray[] = array(
							'fhir_id'=>$observation->id,
							'resourceType'=>$resourceType,
							'field'=>$field,
							'display'=>$display,
							'value'=>$value,
							'timestamp'=>$timestamp,
						);
					} else {
						// Check if the code matches a code we're looking for (from $fieldsRequested parameter)				
						if (!in_array($field, $fieldsRequested)) continue;
						$fhirArray[] = array(
							'fhir_id'=>$observation->id,
							'resourceType'=>$resourceType,
							'field'=>$field,
							'value'=>$value,
							'timestamp'=>$timestamp,
						);
					}
				}
				break;
			case 'DocumentReference':
				// TODO
				$documentReference = new FhirResourceDocumentReference($fhir_data);
				break;
			default:
				# code...
				break;
		}

		// Return flattened data in array
		return $fhirArray;
	}

	/**
	 * check if clinical data interoperability services are enabled
	 * for at least a project in REDCap
	 *
	 * @return boolean
	 */
	public static function isCdisEnabledInSystem()
	{
		global $realtime_webservice_global_enabled, $fhir_ddp_enabled;
		
		$cdp_custom_enabled = boolval($realtime_webservice_global_enabled);
		$cdp_enabled = boolval($fhir_ddp_enabled);
		$data_mart_enabled = DataMart::isEnabledInSystem();
		return $cdp_custom_enabled || $cdp_enabled || $data_mart_enabled;
	}
	
	// Clean a timestamp so that it only consists of numerals, spaces, dashes, and colons
	private function cleanTimestamp($timestamp)
	{
		return trim(preg_replace("/[^0-9-_: ]/", " ", $timestamp));
	}
}
