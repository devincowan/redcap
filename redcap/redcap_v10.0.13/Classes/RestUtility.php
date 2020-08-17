<?php

class RestUtility
{
	public static function processRequestSuper($data)
	{
		global $lang;
		
		// Check super token length
		if (strlen($data['token']) != 64) {
			// Invalid token is used, so log this failed API request and return 403 error
			Logging::logEvent("","redcap_user_information","ERROR",'',json_encode($_POST),"Failed API request (invalid super token)");
			// Return 403 error
			die(RestUtility::sendResponse(403, $lang['api_120']));	# invalid token
		}
		
		$token = db_escape($data['token']);
		
		# check user rights
		$sql = "
			SELECT username, super_user
			FROM redcap_user_information
			WHERE api_token = '".db_escape($token)."'
			AND user_suspended_time IS NULL
			LIMIT 1
		";
		$q = db_query($sql);

		if(!($q && $q !== false && db_num_rows($q) == 1))
		{
			// Invalid token is used, so log this failed API request and return 403 error
			Logging::logEvent($sql,"redcap_user_information","ERROR",'',json_encode($_POST),"Failed API request (invalid super token)");
			// Return 403 error
			die(RestUtility::sendResponse(403, $lang['api_120']));	# invalid token
		}
		
		$row = db_fetch_assoc($q);
		// Is user a super user?
		defined("SUPER_USER") or define("SUPER_USER", $row['super_user']);			
		// Set username
		defined("USERID") or define("USERID", strtolower($row['username']));

		$returnObject  = new RestRequest();
		$returnObject->setMethod(strtolower($_SERVER['REQUEST_METHOD']));

		// TODO: faked for now
		$data['api_import'] = 1;

		$returnObject->setRequestVars($data);
		
		return $returnObject;
	}

	/**
	 * 	@name		processRequest
	 *  @desc		processes an incoming api request
	 *  @return		an object of type RestRequest
	**/
	public static function processRequest($tokenRequired=true)
	{
		$requestMethod 	= strtolower($_SERVER['REQUEST_METHOD']);
		$returnObject	= new RestRequest();
		$data			= array();
		
		# make sure only valid request methods were used
		switch ($requestMethod)
		{
			case 'post':
				$data = $_POST;
				break;
			default:
				// Invalid request method is used, so log this failed API request and return 501 error
				Logging::logEvent("","","ERROR",'',json_encode(array('request_method'=>$_SERVER['REQUEST_METHOD'])),"Failed API request (invalid request method)");
				die(RestUtility::sendResponse(501));
				break;
		}
		
		## Validate token and check user privileges for user/project
		if ($tokenRequired)
		{
			if( 
				// Fetching vesion using super token
				($data['content'] == 'version' && strlen($data['token']) == 64) || 
				// OR Creating new project via XML file
				($data['content'] == 'project' && isset($data['data']) && $data['data'] != ''))
			{
				return self::processRequestSuper($data);
			}

			# Set token. Trim token, if needed.
			$token = $data['token'];
			
			# Check format of token
			if (!preg_match("/^[A-Za-z0-9]+$/", $token)) {
				// Invalid token is used, so log this failed API request and return 403 error
				Logging::logEvent($sql,"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (invalid token)");
				// Return 403 error
				die(RestUtility::sendResponse(403));	# invalid token
			}

			# check user rights
			$sql = "SELECT u.username, u.project_id, i.super_user 
					FROM redcap_user_information i, redcap_user_rights u
					WHERE u.api_token = '".db_escape($token)."' AND i.username = u.username
					AND i.user_suspended_time is null LIMIT 1";
			$q = db_query($sql);
			if (db_num_rows($q) < 1) {
				// Invalid token is used, so log this failed API request and return 403 error
				Logging::logEvent($sql,"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (invalid token)");
				// Return 403 error
				die(RestUtility::sendResponse(403));	# invalid token
			}
			$row = db_fetch_assoc($q);
			// Set $userRights array, which will carry all rights for current user.
			$userRights = UserRights::getPrivileges($row['project_id'], $row['username']);
			$userRights = $userRights[$row['project_id']][strtolower($row['username'])];
			
			// Is user a super user or account manager?
			$super_user = ($row['super_user'] == '1') ? '1' : '0';			
			defined("SUPER_USER") or define("SUPER_USER", $super_user);
			$account_manager = (!$super_user && $row['account_manager'] == '1') ? '1' : '0';
			defined("ACCOUNT_MANAGER") or define("ACCOUNT_MANAGER", $account_manager);
			
			// Set username
			defined("USERID") or define("USERID", strtolower($userRights['username']));
			
			# determine if user's rights for this project have expired
			if (!SUPER_USER && $userRights['expiration'] != "" && str_replace("-", "", $userRights['expiration']) <= date('Ymd')) {
				// Logging
				Logging::logEvent($sql,"redcap_user_rights","ERROR",'',json_encode($_POST),"Failed API request (user rights expired)");
				die(RestUtility::sendResponse(403, "You do not have API rights because your privileges have expired for this project as of {$userRights['expiration']}."));
			}

			// get the project id
			$data['projectid'] = $userRights['project_id'];
			defined("PROJECT_ID") or define("PROJECT_ID", $data['projectid']);
			
			// get the username, export_rights and DAG id
			$data['username'] = $userRights['username'];
			$data['export_rights'] = (SUPER_USER ? 1 : $userRights['data_export_tool']);
			$data['dataAccessGroupId'] = (SUPER_USER ? null : $userRights['group_id']);

			# get access rights
			$data['api_export'] = (SUPER_USER ? 1 : $userRights['api_export']);
			$data['api_import'] = (SUPER_USER ? 1 : $userRights['api_import']);
			$data['record_delete'] = (SUPER_USER ? 1 : $userRights['record_delete']);
			$data['user_rights'] = (SUPER_USER ? 1 : $userRights['user_rights']);
			$data['design_rights'] = (SUPER_USER ? 1 : $userRights['design']);
			
			# if user has mobile_app rights, then if this is a request from the Mobile App itself, then set full data export rights and full API export/import rights
			if ((SUPER_USER || $userRights['mobile_app']) && defined("API") && ((isset($_POST['mobile_app']) && $_POST['mobile_app'] == '1') 
				// The mobileApp parameter check can be removed in May 2015 since it is only a temp workaround to deal with a Mobile App bug prior to official launch
				|| (isset($_POST['mobileApp']) && $_POST['mobileApp'] == '1'))) {
				$data['api_export'] = $data['api_import'] = $data['export_rights'] = $_POST['mobile_app'] = 1;
			}			
		}
		
		// store the method
		$returnObject->setMethod($requestMethod);
		
		// set the raw data, so we can access it later if needed
		$returnObject->setRequestVars($data);
		
		//if importing, need to save the data being uploaded
		if(isset($data['data']))
		{
			switch ($data['format'])
			{
				case 'json':
					$content = json_decode($data['data'], TRUE);
					if ($content == '') {
						die(RestUtility::sendResponse(400, 'The data being imported is not formatted correctly. PHP error from JSON decoder: '.json_last_error_msg().". The JSON must be in an array, as in [{ ... }]."));
					}
					$returnObject->setData($content, true);
					break;
				case 'xml':
                                        $error_ary = array();
					$content = Records::xmlDecode(html_entity_decode($data['data'], ENT_QUOTES), 0, 'tag', $error_ary);
					if ($content == '') die(RestUtility::sendResponse(400, 'The data being imported is not formatted correctly. Error: ' . implode("\n", $error_ary)));
					$returnObject->setData($content);
					break;
				case 'csv':
					$returnObject->setData($data['data']);
					break;
			}
		}
		
		return $returnObject;
	}
	
	/**
	 * 	@name		processRequest
	 *  @desc		processes an incoming api request
	 *  @param 		status - integer - status code
	 *  @param		body - string - message body
	 *  @param		contentFormat - string - the format of the content being passed in
	 *  @return		string - page output
	**/
	public static function sendResponse($status = 200, $body = '', $contentFormat = '')
	{
		global $returnFormat;
		
		# set return format as same as content format if not provided
		if ($contentFormat == '') $contentFormat = $returnFormat;

		# set the content type
		switch ($contentFormat)
		{
			case 'json':
				$contentType = 'application/json';
				break;
			case 'csv':
				$contentType = 'text/csv';
				break;
			case 'xml':
				$contentType = 'text/xml';
				break;
		}

		// set the status
		$statusHeader = 'HTTP/1.1 ' . $status . ' ' . RestUtility::getStatusCodeMessage($status);
		header($statusHeader);

		// set the content type
		header('Content-type: ' . $contentType . '; charset=utf-8');
		
		if ($status != 200)
		{
			if ($body == '')
			{
				switch($status)
				{
					case 400:
						$body = 'There were errors with your request. If you are sending data, then it might be due to the data being misformatted. '.$body;
						break;
					case 401:
						$body = 'The API token was missing or incorrect';
						break;
					case 403:
						$body = 'You do not have permissions to use the API';
						break;
					case 404:
						$body = 'The requested URI ' . $_SERVER['REQUEST_URI'] . ' was not found.';
						break;
					case 500:
						$body = 'The server encountered an error processing your request.';
						break;
					case 501:
						$body = 'The requested method is not implemented.';
						break;
				}
			}

			if ($returnFormat == 'json') {
				if (substr($body, 0, 8) != '{"error"') {
					$obj = new stdClass();
					$obj->error = $body;
					$body = json_encode($obj);
					//$body = '{"error": "'.$body.'"}';
				}

			}
			elseif ($returnFormat == 'csv') {
				$body = "ERROR: $body";
			}
			else {
				$output = '<?xml version="1.0" encoding="UTF-8" ?>';
				if (substr($body, 0, 7) != "<error>")
					$output .= "<hash><error>$body</error></hash>";
				else
					$output .= "<hash>$body</hash>";
	
				$body = $output;
			}
		}

		echo $body;
        if (!defined("REDCAP_API_NO_EXIT")) exit;
	}
	
	public static function sendFile($status = 200, $filepath, $filename, $contentType)
	{
		// set the status
		$statusHeader = 'HTTP/1.1 ' . $status . ' ' . RestUtility::getStatusCodeMessage($status);
		header($statusHeader);

		// set the content type
		header('Content-type: ' . $contentType . '; name="' . $filename . '"');
		
		ob_end_flush();
		readfile_chunked($filepath);

        if (!defined("REDCAP_API_NO_EXIT")) exit;
	}
	
	public static function sendFileContents($status = 200, $contents, $filename, $contentType)
	{
		// set the status
		$statusHeader = 'HTTP/1.1 ' . $status . ' ' . RestUtility::getStatusCodeMessage($status);
		header($statusHeader);

		// set the content type
		header('Content-type: ' . $contentType . '; name="' . $filename . '"');
		
		$filepath = APP_PATH_TEMP . date('YmdHis') . "_attach_" . $filename;
		file_put_contents($filepath, $contents);
		
		ob_end_flush();
		readfile_chunked($filepath);
		unlink($filepath);

        if (!defined("REDCAP_API_NO_EXIT")) exit;
	}

	/**
	 * 	@name		getStatusCodeMessage
	 *  @desc		processes an incoming api request
	 *  @param		status - integer - the status code
	 *  @return		string - expanded status code 
	**/
	public static function getStatusCodeMessage($status)
	{
		$codes = Array(
		    100 => 'Continue',
		    101 => 'Switching Protocols',
		    200 => 'OK',
		    201 => 'Created',
		    202 => 'Accepted',
		    203 => 'Non-Authoritative Information',
		    204 => 'No Content',
		    205 => 'Reset Content',
		    206 => 'Partial Content',
		    300 => 'Multiple Choices',
		    301 => 'Moved Permanently',
		    302 => 'Found',
		    303 => 'See Other',
		    304 => 'Not Modified',
		    305 => 'Use Proxy',
		    306 => '(Unused)',
		    307 => 'Temporary Redirect',
		    400 => 'Bad Request',
		    401 => 'Unauthorized',
		    402 => 'Payment Required',
		    403 => 'Forbidden',
		    404 => 'Not Found',
		    405 => 'Method Not Allowed',
		    406 => 'Not Acceptable',
		    407 => 'Proxy Authentication Required',
		    408 => 'Request Timeout',
		    409 => 'Conflict',
		    410 => 'Gone',
		    411 => 'Length Required',
		    412 => 'Precondition Failed',
		    413 => 'Request Entity Too Large',
		    414 => 'Request-URI Too Long',
		    415 => 'Unsupported Media Type',
		    416 => 'Requested Range Not Satisfiable',
		    417 => 'Expectation Failed',
		    500 => 'Internal Server Error',
		    501 => 'Not Implemented',
		    502 => 'Bad Gateway',
		    503 => 'Service Unavailable',
		    504 => 'Gateway Timeout',
		    505 => 'HTTP Version Not Supported'
		);

		return (isset($codes[$status])) ? $codes[$status] : '';
	}
}

class RestRequest
{
	private $requestVars;
	private $data;
	private $httpAccept;
	private $method;
	private $queryString;
	
	public function __construct()
	{
		$this->requestVars		= array();
		$this->data				= '';
		$this->httpAccept		= 'text/xml';
		$this->method			= 'get';
		$this->queryString		= array();
	}

	public function getData()
	{
		return $this->data;
	}

	public function setData($value)
	{
		$this->data = $value;
	}
	
	public function getHttpAccept()
	{
		return $this->httpAccept;
	}
	
	public function setHttpAccept($value)
	{
		return $this->httpAccept = $value;
	}

	public function getMethod()
	{
		return $this->method;
	}

	public function setMethod($value)
	{
		$this->method = $value;
	}

	public function getQueryString()
	{
		return $this->queryString;
	}

	public function setQueryString($value)
	{
		$this->queryString = $value;
	}

	public function getRequestVars()
	{
		return $this->requestVars;
	}

	public function setRequestVars($value)
	{
		$this->requestVars = $value;
	}
}
