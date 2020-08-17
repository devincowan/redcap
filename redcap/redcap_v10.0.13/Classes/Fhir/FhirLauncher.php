<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

use Vanderbilt\REDCap\Classes\Fhir\FhirServices;

class FhirLauncher
{

	/**
	 * launcher modes
	 */
	const MODE_NONE = 'none';
	const MODE_SHOW_PORTAL = 'show_portal';
	const MODE_AUTHORIZE = 'authorize';
	const MODE_TOKEN = 'token';
	const MODE_STANDALONE_LAUNCH = 'standalone_launch';
	const MODE_CLIENT_CREDENTIALS = 'client_credentials';
	const MODE_ERROR = 'error';

	/**
	 * modes that perform a launch
	 *
	 * @var array
	 */
	public static $launch_modes = array(
		self::MODE_CLIENT_CREDENTIALS,
		self::MODE_STANDALONE_LAUNCH,
		self::MODE_AUTHORIZE
	);

	/**
	 * modes that need the user to be authenticated
	 *
	 * @var array
	 */
	public static $protected_modes = array(
		FhirLauncher::MODE_TOKEN,
		FhirLauncher::MODE_SHOW_PORTAL
	);

	/**
	 * name of the session variable where the FHIR related data is saved
	 *
	 * @var string
	 */
	const SESSION_NAME = 'ehr-fhir';
	
	/**
	 * name of the cookie where the session is stored
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'ehr-fhir';


	/**
	 * object for FHIR interaction
	 *
	 * @var FhirServices
	 */
	private $fhirServices;

	public function __construct($fhirServices, $redirect_uri)
	{
		$this->startSession();
		$this->restoreSession();
		$this->fhirServices = $fhirServices;
		$this->redirect_uri = $redirect_uri;
	}

	/**
	 * persist the session in the cookie
	 */
	public function __destruct()
	{
		$this->startSession();
		if($session_data = $_SESSION[self::SESSION_NAME])
		{
			self::saveCookie($session_data);
		}
	}

	/**
	 * start a session if not already started
	 * init the fhir container in the session
	 *
	 * @return void
	 */
	private function startSession()
	{
		\Session::init();
	}


	/**
	 * restore session from the cookie if available
	 *
	 * @return void
	 */
	private function restoreSession()
	{
		if($cookie_data = self::getCookieData())
		{	
			$_SESSION[self::SESSION_NAME] = $cookie_data;
		}
	}

	/**
	 * cleanup the session and the cookie
	 *
	 * @return void
	 */
	public static function cleanup()
	{
		self::destroyCookie();
		self::destroySessionData();
	}

	/**
	 * reset the session data
	 *
	 * @return void
	 */
	private static function destroyCookie()
	{
		deletecookie(self::COOKIE_NAME);
	}

	public static function getCookieData()
	{
		$cookie = $_COOKIE[self::COOKIE_NAME];
		return empty($cookie) ? false : unserialize(decrypt($cookie));
	}

	/* private function saveFhirCookie1($key, $value)
	{
		$data = unserialize(decrypt($_COOKIE[self::COOKIE_NAME]));
		// make sure that $data is an array
		if(empty($data) || is_array($data)) $data = array();
		// set the data
		$data[$key] = $value;
		// persist data in cookie
		savecookie(self::COOKIE_NAME, encrypt(serialize($data)), 1800);
	} */

	private function saveCookie($data)
	{
		// persist data in cookie
		savecookie(self::COOKIE_NAME, encrypt(serialize($data)), 1800);
	}

	/**
	 * reset the session data
	 *
	 * @return void
	 */
	private static function destroySessionData()
	{
		unset($_SESSION[self::SESSION_NAME]);		
	}

	/**
	 * set a value in the FHIR session data container
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return array the FHIR session data
	 */
	public function setSessionData($key, $value)
	{
		// make sure that the session is an array
		if(!is_array($_SESSION[self::SESSION_NAME])) $_SESSION[self::SESSION_NAME] = array();
		$_SESSION[self::SESSION_NAME][$key] = $value;
		return $_SESSION[self::SESSION_NAME];
	}

	/**
	 * get a value stored in the session
	 *
	 * @param string $key
	 * @return void
	 */
	public static function getSessionData($key)
	{
		$value = @$_SESSION[self::SESSION_NAME][$key];
		return $value;
	}

	/**
	 * Store the URL that started a launch flow.
	 * This is usually set in the standalone launch
	 * or the client cedentials flow
	 *
	 * @return void
	 */
	private function setLaunchPage()
	{
		$this->setSessionData('launch_page', urlencode($_SERVER['HTTP_REFERER']));
	}

	/**
	 * get the URL of the page that started a launch
	 *
	 * @return string|null
	 */
	public function getLaunchPage()
	{
		$page = self::getSessionData('launch_page');
		return urldecode($page);
	}

	/**
	 * Parse the URL for a 'user' variable.
	 * The EHR user is typically available in epic
	 * when the FHIR integration is setup to use
	 * the %EPICUSERID% token
	 * 
	 * this method returns a fake user for some
	 * EHR simulators
	 * 
	 * @see https://apporchard.epic.com/Article?docId=Launching
	 *
	 * @return string|null
	 */
	private function getEhrUserFromUrl()
	{
		global $fhir_endpoint_base_url;
		// change all key to lowercase to get both user or USER
		$_GET_lower = array_change_key_case($_GET, CASE_LOWER);
		if($user = trim(rawurldecode(urldecode($_GET_lower['user']))))
		{
			return $user;
		}
		// list of EHR simulators and relative fake user
		$testWebsitesUsers = array(
			'smarthealthit.org' => 'SMART_FAKE_USER',
			'open-ic.epic.com' => 'OPEN_EPIC_FAKE_USER',
		);
		foreach ($testWebsitesUsers as $url => $user) {
			$regExp = sprintf('/%s/i', preg_quote($url, '/'));
			if(preg_match($regExp, $fhir_endpoint_base_url)) return $user;
		}
	}

	/**
	 * detect the current mode 
	 *
	 * @return void
	 */
	public function getMode()
	{
		if(!empty($_GET['standalone_launch'])) return self::MODE_STANDALONE_LAUNCH;
		if(!empty($_GET['client_credentials'])) return self::MODE_CLIENT_CREDENTIALS;
		if(!empty($_GET['fhirPatient'])) return self::MODE_SHOW_PORTAL;
		if(!empty($_GET['launch'])) return self::MODE_AUTHORIZE;
		else if(!empty($_GET['code'])) return self::MODE_TOKEN;
		else if(!empty($_GET['error'])) return self::MODE_ERROR;
		// none of the above
		return self::MODE_NONE;
	}

	/**
	 * get the current session ID
	 * the session ID is used as state parameter
	 *
	 * @return string
	 */
	public function getSessionID()
	{
		$id = session_id();
		return $id;
	}

	/**
	 * authorize
	 *
	 * @return void
	 */
	public function authorize()
	{
		if($ehr_user = $this->getEhrUserFromUrl())
		{
			// an ehr_user has been found in the URL; add it to the session
			// the mapping will be set in the token step, when the user is authenticated
			$this->setSessionData('ehr_user', $ehr_user);
		}
		$launch_code = $_GET['launch'];
		// get the identity provider; will be used as aud parameter in the authorize URL
		$identity_provider = isset($_GET['iss']) ? $_GET['iss'] : null;
		$scopes = FhirServices::$scopes;
		$state = $this->getSessionID();
		$this->setSessionData('state', $state);
		$redirect_uri = $this->redirect_uri;
		$this->fhirServices->getAuthorizationToken($scopes, $state, $redirect_uri, $identity_provider, $launch_code);
	}


	/**
	 * check if a URL is reachable
	 *
	 * @param string $url
	 * @throws Requests_Exception
	 * @return Requests_Response
	 */
	private static function checkOnline($url)
	{
		$http_options = array(
			'data' => array(),
			'headers' => array(),
			'options' => array(
				'blocking'=>true,
				'timeout'=>30,
			),
		);
		$result = \HttpClient::request('GET', $url, $http_options);
		return $result;
	}



	/**
	 * start a standalone launch
	 * @throws Exception if the authorization endpoint is not reachable
	 *
	 * @return void
	 */
	public function standaloneLaunchFlow()
	{
		$this->setLaunchPage(); // remember the page that started the standalone launch
		// remove the 'launch' scope
		$scopes = array_filter(FhirServices::$scopes, function($scope) {
			return !preg_match("/^launch$/i", $scope);
		});

		if($isCerner = $this->fhirServices->checkPublisher('cerner'))
		{
			// 11/22/2019 cerner only supports stand alone launch for patient facing apps
			// 12/13/2019 standalone works with wildcard or user level for scopes
			// 12/13/2019 get a refresh token only using user scopes
			// IMPORTANT: the 'launch' scope must not be specified
			// change the level to the wildcard one
			foreach ($scopes as &$scope) {
				// 01/21/2020 standalone launch should specify "User". Source: https://groups.google.com/d/msg/cerner-fhir-developers/-DFma4Ibrbo/pNottf4TCgAJ
				$scope = FhirServices::changeScopeLevel($scope, $level='user');
			}
		}
		$state = $this->getSessionID();
		$this->setSessionData('state', $state);
		$identity_provider = isset($_GET['iss']) ? $_GET['iss'] : null;
		$redirect_uri = $this->redirect_uri;
		$this->fhirServices->getAuthorizationToken($scopes, $state, $redirect_uri, $identity_provider);
	}

	/**
	 * get the token
	 * - connect to the token endpoint and exchange an authorization token for an access token
	 * - use a cookie if a token has been previously stored there (if user had to login)
	 *
	 * @return object
	 */
	public function getToken()
	{
		$state = $this->getSessionID();
		$previous_state = $_GET['state']; // we sent this in the authorize step
		$this->fhirServices->checkState($state, $previous_state); // check if the current state and the previous one match
		$redirect_uri = $this->redirect_uri;
		if($auth_code = $_GET['code']) return $this->fhirServices->getAccessToken($auth_code, $redirect_uri);
	}

	/**
	 * perform actions after the token has been acquired from the FHIR provider
	 * - map the EHR user to allow autologin in later requests
	 * - perform the autologin procedure
	 *
	 * @param object $token
	 * @return void
	 */
	public function processToken($token)
	{
		$ehr_user = $this->getSessionData('ehr_user'); // see if the EHR is already in the SESSION
		$token_ehr_user = trim(rawurldecode(urldecode($token->username))); // try to get the EHR user from the token
		if(!empty($token_ehr_user)) {
			// a EHR user is provided from the token; override the one found in the URL (if any)
			$ehr_user = $token_ehr_user;
			$this->setSessionData('ehr_user', $ehr_user); // save EHR user in the session
		}
		if(!empty($ehr_user)) {
			$this->addEhrUserMap($ehr_user, $userName = $_SESSION['username']); // map the EHR user
		}
	}

	/**
	 * Map REDCap username to EHR username in db table
	 *
	 * @param string $ehr_user
	 * @param string $redcap_user
	 * @throws Exception if no valid user is provided
	 * @return void
	 */
	private function addEhrUserMap($ehr_user, $redcap_user)
	{
		// Get user ui_id
		$user_id = \User::getUIIDByUsername($redcap_user);
		if(empty($ehr_user)) throw new \Exception("Error mapping the EHR user: no EHR user provided.", 1);
		if(empty($user_id)) throw new \Exception("Error mapping the EHR user: no valid REDCap user provided.", 1);
		
		$sql = "REPLACE INTO redcap_ehr_user_map (ehr_username, redcap_userid) 
				VALUES ('".db_escape($ehr_user)."', '".db_escape($user_id)."')";
		return db_query($sql);
	}

	/**
	 * return the URL to trigger a standalone launch
	 * return empty string if standalone launch is not enabled in the system
	 *
	 * @return string
	 */
	public static function getStandaloneLaunchUrl()
	{
		global $fhir_standalone_authentication_flow;
		if($fhir_standalone_authentication_flow!==self::MODE_STANDALONE_LAUNCH) return '';
		return APP_PATH_WEBROOT.'ehr.php?standalone_launch=1';
	}

	/**
	 * Check if the session has a EHR user
	 * and perform the DDP on FHIR auto-login.
	 * The autologin can only be performed in the 
	 * mode MODE_TOKEN, ie after an authorization
	 * code has been obtained from the 'authorize' endpoint
	 *
	 * @return void
	 */
	public function checkAutoLogin()
	{
		if($this->getMode()!==self::MODE_TOKEN) return; // only proceed if in MODE_TOKEN
		$ehr_user = self::getSessionData('ehr_user'); // see if the EHR is already in the SESSION
		if(empty($ehr_user)) return; // no EHR user in session; exit
		// See if this user is mapped in the db table
		if($redcapUsername = $this->getMappedUsernameFromEhrUser($ehr_user))
		{
			// Perform auto-login
			require_once APP_PATH_DOCROOT . 'Libraries/PEAR/Auth.php';
			\Authentication::autoLogin($redcapUsername);
		}
	}

	/**
	 * Query table to get REDCap username from passed EHR username
	 *
	 * @param string $ehr_user
	 * @return string
	 */
	private function getMappedUsernameFromEhrUser($ehr_user)
	{		
		$sql = sprintf("SELECT i.username FROM redcap_ehr_user_map m, redcap_user_information i
				WHERE i.ui_id = m.redcap_userid AND m.ehr_username = '%s' LIMIT 1", db_escape($ehr_user)
		);
		$result = db_query($sql);
		if(!$result) return false;
		if($result && $row = db_fetch_assoc($result)) return $row['username'];
	}

	/**
	 * return an error object
	 *
	 * @return object containing error and url
	 */
	public function getError()
	{
		$data = array();
		if($error = $_GET['error'])
		{
			$data['message'] = $error;
			if($URL = $_GET['error_uri']) $data['url'] = $URL;
		}
		return (object)$data;
	}

	public function clientCredentialFlow()
	{
		// remember the page that started the clienrt credentials launch
		$this->setLaunchPage();
		$scopes = FhirServices::$client_credentials_scopes;
		return $this->fhirServices->getTokenWithClientCredentials($scopes);
	}
}