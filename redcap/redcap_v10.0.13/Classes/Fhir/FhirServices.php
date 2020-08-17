<?php


namespace Vanderbilt\REDCap\Classes\Fhir;

class FhirServices
{
	public static $scopes = array(
		'launch',
		'openid',
		'fhirUser',
		'online_access', // for refresh token
		'patient/Patient.read',
		'patient/Observation.read',
		'patient/Condition.read',
		'patient/MedicationOrder.read',
		'patient/AllergyIntolerance.read',
		'patient/FamilyMemberHistory.read',
		'patient/DiagnosticReport.read',
		'patient/Immunization.read',
		'patient/Procedure.read',
		'patient/Device.read',
		'patient/DocumentReference.read',
	);

	public static $client_credentials_scopes = array(
		'*/AllergyIntolerance.read',
		'*/Binary.read',
		'*/Condition.read',
		'*/DocumentReference.read',
		'*/MedicationOrder.read',
		'*/Observation.read',
		'*/Patient.read',
	);

	/**
	 * type of the authorize URL in the conformance statement
	 */
	const CONFORMANCE_URL_AUTHORIZE = 'authorize';

	/**
	 * type of the token URL in the conformance statement
	 */
	const CONFORMANCE_URL_TOKEN = 'token';

	/**
	 * base URL of the FHIR endpoint
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * the client ID for the OAUTH2 app
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * the client secret for the OAUTH2 app
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * conformance statement
	 *
	 * @var object
	 */
	private $conformance_statement;

	
	public function __construct($base_url, $client_id, $client_secret)
	{
		$this->base_url = preg_replace('/(.+[^\/])$/','\1/', $base_url); // make sure it ends with a '/'
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
	}

	/**
	 * get an instance of FhirServices using the global REDCap configuration 
	 *
	 * @return FhirServices
	 */
	public static function getInstance()
	{
		global $fhir_endpoint_base_url, $fhir_client_id, $fhir_client_secret;
		return new self($fhir_endpoint_base_url, $fhir_client_id, $fhir_client_secret);
	}
	
	/**
	 * check if the current state (session ID)
	 * is the same state used in the OAUTH2 flow
	 *
	 * @param string $state
	 * @return bool
	 */
	public function checkState($state, $previous_state)
	{
		if(!$state===$previous_state)
		{
			$data = array(
				'state' => $state,
				'previous_state' => $previous_state,
			);
			throw new \DataException("Error: the current state and the one supplied in the OAUTH2 flow do not match", $data, 1);
		}
	}

	/**
	 * get the cached conformance statement
	 * or retrieve it from remote endpoint
	 *
	 * @return object
	 */
	public function getConformanceStatement()
	{
		if(empty($this->conformance_statement))
		{
			$this->conformance_statement = $this->getFhirConformanceStatement();
		}
		return $this->conformance_statement;
	}
	
	/**
	 * get the conformance statement from the FHIR endpoint
	 *
	 * @param string $fhir_base_url
	 * @throws Exception
	 * @return object
	 */
	private function getFhirConformanceStatement() {
		// Set metadata URL of conformance statement
		$metadataEndPoint = $this->base_url . "metadata";
		// Conformance statement - obtain authorize endpoint URL and token endpoint URL
		try {
			$http_options = array(
				'headers' => array(
					'Accept' => 'application/json'
				),
			);
			$response = \HttpClient::request($method='GET', $metadataEndPoint, $http_options);
			if (!$response) throw new \Exception("Could not make a successful call to the Conformance Statement at " . $this->endpoint . "metadata");
			$metadata = json_decode($response->getBody(), false);
			return $metadata;
		} catch (\Exception $e) {
			$message = $e->getMessage();
			throw new \Exception("Could not make a successful call to the Conformance Statement at {$metadataEndPoint}.\n\r{$message}");
		}
	}
	
	
	/**
	 * extract the url value for token and authorize URLs
	 * from the conformance statement
	 *
	 * @param string $fhir_endpoint_base_url
	 * @param string $type can be 'authorize' or 'token'
	 * @return string the URL
	 */
	private function getConformanceUrlByType($type)
	{
		$metadata = $this->getConformanceStatement();
		try {
			$extensions = $metadata->rest[0]->security->extension[0]->extension;
			$url = array_filter($extensions, function($extension) use($type) {
				return strtolower($extension->url) === strtolower($type);
			});
			if($urlObject = reset($url)) return $urlObject->valueUri; // return the first value found
		} catch (\Exception $e) {
			throw new \Exception("Could not parse the Conformance Statement from {$this->base_url} metadata");
		}
	}

	/**
	 * get the publisher from the conformance statement
	 *
	 * @return string
	 */
	public function getPublisher()
	{
		$metadata = $this->getConformanceStatement();
		$publisher = $metadata->publisher;
		$name = $metadata->name;
		return $publisher;
	}

	/**
	 * check if the publisher in the conformance statement
	 * is the one provided as parameter
	 *
	 * @param string $publisher_name
	 * @return boolean
	 */
	public function checkPublisher($publisher_name)
	{
		$publisher = $this->getPublisher();
		$regExp = sprintf('/%s/i', preg_quote($publisher_name, '/'));
		return preg_match($regExp, $publisher);
	}
	
	/**
	 * get the authorize URL from REDCap settings
	 * if setting is empty use the conformance statement
	 *
	 * @return string
	 */
	public function getAutorizationUrl()
	{
		global $fhir_endpoint_authorize_url;
		if(!empty($fhir_endpoint_authorize_url)) return $fhir_endpoint_authorize_url;
		return $this->getConformanceUrlByType($type=self::CONFORMANCE_URL_AUTHORIZE);
	}
	
	/**
	 * get the token URL from REDCap settings
	 * if setting is empty use the conformance statement
	 *
	 * @return string
	 */
	public function getTokenUrl()
	{
		global $fhir_endpoint_token_url;
		if(!empty($fhir_endpoint_token_url)) return $fhir_endpoint_token_url;
		return $this->getConformanceUrlByType($type=self::CONFORMANCE_URL_TOKEN);
	}

	/**
	 * create an authorization header.
	 * can be used when asking for access token or refresh token
	 *
	 * @return void
	 */
	private function getAuthorizationHeader()
	{
		return base64_encode("{$this->client_id}:{$this->client_secret}");
	}

	/**
	 * use the client credentials OAuth2 flow to get an access token
	 * (11/29/2019) it is only supported by cerner
	 *
	 * @param array $scopes
	 * @return object an token
	 */
	public function getTokenWithClientCredentials($scopes=array())
	{
		$scope_string = implode(' ', $scopes);
		$data = array(
			'grant_type' => 'client_credentials',
			'client_id' => $this->client_id,
			'scope' => $scope_string,
		);
		$authorizationHeader = $this->getAuthorizationHeader();
		$headers = array(
			'Accept' => 'application/json',
			'Content-type' => 'application/x-www-form-urlencoded',
			'Authorization' => "Basic {$authorizationHeader}",
		);
		$http_options = array(
			'form_params' => $data,
			'headers' => $headers,
		);
		try {
			$tokenUrl = $this->getTokenUrl();
			$response = \HttpClient::request('POST', $tokenUrl, $http_options);
			return json_decode($response->getBody());
		} catch (\Exception $e) {
			$message = $e->getMessage();
			$data = array(
				'url' => $tokenUrl,
				'http_options' => $http_options,
			);
			throw new \DataException("Error trying to get the access token. {$message}", $data, $e->getCode() );
		}
	}
	
	/**
	 * connect to the authorize endpoint
	 * to exchange the launch code for an access token.
	 * a standalone launch is made if no launch code is provided
	 *
	 * @param array $scope the scopes to use. provided as array so I can make changes easily
	 * @param string $state provide the current state so we can check it later
	 * @param string $identity_provider the identifier for the identity provider who issued the token
	 * @param string $launch_code if $launch_code is null then a standalone launch is initiated
	 * @return void
	 */
	public function getAuthorizationToken($scopes, $state, $redirect_uri, $identity_provider, $launch_code=null)
	{
		// remove the launch scope if no launch code is provided
		if(!isset($launch_code))
		{
			$launch_scope_index = array_search('launch', $scopes);
			// unset($scopes[$launch_scope_index]);
		}
		$scope_string = implode(' ', $scopes);
		
		$audience =  $identity_provider ?: $this->base_url;

		$params = array(
			'response_type' => 'code',
			'client_id' => $this->client_id,
			'scope' => $scope_string,
			'launch' => $launch_code, // if this is null a standalone launch is started
			'redirect_uri' => $redirect_uri,
			'aud' => $audience,
			'state' => $state
		);

		$authorizationUrl = $this->getAutorizationUrl();
		if(empty($authorizationUrl)) throw new \Exception("No authorization URL available", 1);
		$query_params = http_build_query($params);
		$requestUrl = "{$authorizationUrl}?{$query_params}";
		\Logging::logEvent( "", "FHIR", "MANAGE", "", \Logging::printArray(compact('authorizationUrl', 'params')), "Redirecting to the authorize URL" );
		\HttpClient::redirect($requestUrl, true);
	}
	
	/**
	 * get an access token from the token endpoint
	 * 
	 * @param string $auth_code the authorization code
	 * @return object the access token info object
	 */
	public function getAccessToken($auth_code, $redirect_uri)
	{
		$data = array(
			'grant_type' => 'authorization_code',
			'code' => $auth_code,
			'redirect_uri' => $redirect_uri,
			'client_id' => $this->client_id,
		);
		$headers = array(
			'Accept' => 'application/json',
			'Content-type' => 'application/x-www-form-urlencoded',
		);
		if(!empty($this->client_secret))
		{
			// do not pass the client ID if the client secret is available
			unset($data['client_id']);
			// get the token bearer and add it to the headers
			$tokenBearer = $this->getAuthorizationHeader();
			$headers['Authorization'] = "Basic {$tokenBearer}";
		}
		$http_options = array(
			'form_params' => $data,
			'headers' => $headers,
		);
		try {
			$tokenUrl = $this->getTokenUrl();
			// Log event $sql, $object_type, $event, $record, $data_values, $description
			$filtered_http_options = array_diff_key($http_options, array('headers'=>1)); // get all HTTP options but exclude headers; contains sensitive data
			$filtered_http_options['tokenUrl'] = $tokenUrl; // add the FHIR token URL

			$response = \HttpClient::request('POST', $tokenUrl, $http_options);
			return json_decode($response->getBody());
		} catch (\Exception $e) {
			$error_code = $e->getCode();
			$error_message = $e->getMessage();
			$data = array(
				'url' => $tokenUrl,
				'http_options' => $http_options,
			);
			$message =  sprintf("Error getting authorization token. Please review your settings (client ID, client secret and redirect URL) - %s", $error_message);
			throw new \DataException($message, $data, $error_code);
		}
	}

	/**
	 * refresh a token
	 *
	 * @param [type] $refresh_token
	 * @return void
	 */
	public function refreshToken($refresh_token)
	{
		$data = array(
			'grant_type' => 'refresh_token',
			'refresh_token' => $refresh_token,
		);
		$tokenBearer = $this->getAuthorizationHeader();
		$headers = array(
			'Accept' => 'application/json',
			'Content-type' => 'application/x-www-form-urlencoded',
			'Authorization' => "Basic {$tokenBearer}",
		);
		$http_options = array(
			'form_params' => $data,
			'headers' => $headers,
		);
		try {
			$tokenUrl = $this->getTokenUrl();
			$result = \HttpClient::request('POST', $tokenUrl, $http_options);
			$token_info = json_decode($result->getBody());
			if(!isset($token_info->refresh_token)) $token_info->refresh_token = $refresh_token;
			return $token_info;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * get data from the patient endpoint
	 *
	 * @param string $access_token a valid access token to use as bearer
	 * @param string $patient_id FHIR ID for the patient
	 * @return void
	 */
	public function getPatientDemographics($access_token=null, $patient_id=null)
	{
		if (empty($access_token) || empty($patient_id)) {
			return false;
		}
		$url = "{$this->base_url}Patient/{$patient_id}";
		$fhirData = $this->getFhirData($url, $access_token);
		return $fhirData;
	}

	/**
	 * change the level of the scope
	 * levels: user | system | patient | *
	 * resource: {resource_name} | *
	 * permissions: read | write | *
	 * scope = {level=user|system|patient|*}/{resource=*}.{permissions=read|write|*}
	 *
	 * @param string $scope
	 * @param string $level
	 * @return string
	 */
	public static function changeScopeLevel($scope, $level="*")
	{
		// regular expression with 3 tage: level, resource, permissions
		$regexp = "/^(?:(?<level>(?!launch)[^\/]*)\/)?(?<resource>[^\.]+)(?:\.(?<permissions>.*))?/";
		preg_match($regexp, $scope, $matches);
		if(!$matches['level']) return $scope; // return the scope unmodified if has no level
		$resource = $matches["resource"];
		// compose level and resource
		$modified_scope = "{$level}/{$resource}";
		// add the permission if available
		if($permissions=$matches["permissions"]) $modified_scope .= ".{$permissions}";
		return $modified_scope;
	}

	/**
	 * get data from a fhir endpoint
	 *
	 * @param string $url
	 * @param string $access_token
	 * @throws Exception 
	 * @return object
	 */
	public function getFhirData($url=null, $access_token=null)
	{
		if (empty($access_token)) throw new \Exception("No access token available.");

		$http_options = array(
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Authorization' => "Bearer {$access_token}",
			),
		);

		$response = \HttpClient::request($method='GET', $url, $http_options);
		$fhirData = json_decode($response->getBody());
		if (empty($fhirData)) {
			throw new \Exception("<b>ERROR:</b> Response from the FHIR endpoint is not in expected format.
														See details below.<br><br>
														<b>URL:</b><br><i>$url</i><br><br>
														<b>RESPONSE:</b><br>".$response->body);
		}
		return $fhirData;
	}
	
}