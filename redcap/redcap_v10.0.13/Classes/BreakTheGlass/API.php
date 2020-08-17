<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

class API
{

    // endpoints URL templates
    const ENDPOINT_ACCEPT = 'api/epic/2013/Security/BreakTheGlass/AcceptBreakTheGlass/Security/BreakTheGlass/Accept';
    const ENDPOINT_CANCEL = 'api/epic/2013/Security/BreakTheGlass/CancelBreakTheGlass/Security/BreakTheGlass/Cancel';
    const ENDPOINT_CHECK = 'api/epic/2013/Security/BreakTheGlass/CheckBreakTheGlass/Security/BreakTheGlass/AccessCheck';
    const ENDPOINT_INITIALIZE = 'api/epic/2013/Security/BreakTheGlass/InitializeBreakTheGlass/Security/BreakTheGlass/Initialize';

    private static $timeout = 30.0;
    private static $connect_timeout = 30.0;

    /**
     * Break the glass settings
     *
     * @var base URL for endpoints
     */
    private $base_url = '';

    /**
     * user provided to the endpoint (corresponds to the epic user)
     *
     * @var string
     */
    private $user = '';

    /**
     * type of user provided to the endpoint
     *
     * @var string
     */
    private $ehr_usertype = '';

    /**
     * authorization used in post calls (bearer or basic)
     *
     * @var string
     */
    private $authorization = '';
    /**
     * cleint ID of the APP on the epic app orchard
     *
     * @var string
     */
    private $epic_client_ID = '';

    public function __construct($params)
    {
        if(!empty($params['user'])) $this->user = $params['user'];
        if(!empty($params['base_url'])) $this->base_url = $params['base_url'];
        if(!empty($params['authorization'])) $this->authorization = $params['authorization'];
        if(!empty($params['epic_client_ID'])) $this->epic_client_ID = $params['epic_client_ID'];
        if(!empty($params['ehr_usertype'])) $this->ehr_usertype = $params['ehr_usertype'];
        // the 2 below are not necessary as of 2020-05-27
        // $this->epic_user_type = isset($params['epic_user_type']) ? $params['epic_user_type'] : '';
        // $this->epic_user_ID = isset($params['epic_user_ID']) ? $params['epic_user_ID'] : '';
    }


    /**
     * get the full URL of the break the glass endpoint
     *
     * @param string $endpoint
     * @return string
     */
    private function getEndpointUrl($endpoint)
    {
        $break_the_glass_endpoints = [
            self::ENDPOINT_ACCEPT,
            self::ENDPOINT_CANCEL,
            self::ENDPOINT_CHECK,
            self::ENDPOINT_INITIALIZE,
        ];
        if(!in_array($endpoint,$break_the_glass_endpoints)) throw new \Exception("The requested endpoint is not available", 1);
        
        // web service at Vanderbilt used fot testing purposes
        // $base_url = 'https://ic1-dev.service.vumc.org/Interconnect-DEV-WEBSVC/';
        return $this->base_url.$endpoint;
    }
    /**
     * This service runs the released checks in Break-the-Glass to determine the type of access the user can have:
     *  The user is denied access to the information if an inappropriate check is passed.
     *  The user is granted access to the information if an appropriate check is passed.
     *  The user needs to break the glass if neither an appropriate nor an inappropriate check is passed.
     *
     * @param array $params
     *      "PatientID" => $this->patient[$patientType]['PatientID'],
     *      "PatientIDType" => MRN, EPI, ...
     *      "ContactID"
     *      "ContactIDType"
     *      "UserID" => intrc
     *      "UserIDType" => Systemlogin, EXTERNAL, ...
     *      "CheckView" => "100",
     *      "CheckType" => array('Record'),
     * 
     * @return CheckBreakTheGlassResponse
     *      AccessType ( int ) The type of access the user has been granted.
     *          0 means inappropriate or blocked.
     *          1 means appropriate or granted.
     *          2 means the user needs to break the glass in order to get access.
     *      Message ( string ) 	When the user needs to break the glass,
     *                          this list of messages should appear on
     *                          the Break-the-Glass prompt form.
     */
    function check($params=array())
    {
        $mandatory_fields = array(
            'PatientID',
            'UserID',
            'CheckType',
            'CheckView',
        );
        $url = $this->getEndpointUrl(self::ENDPOINT_CHECK);

        $defaultParams = array(
            // PatientID can be the MRN or the patient (as stored in the redcap_ehr_access_tokens table)
            // if we use the patient, then a PatientIDType of FHIR must be specified
            "PatientID" => '',
            "PatientIDType" => GlassBreaker::PATIENT_TYPE_MRN, // hardcoded to MRN because it is what REDCap uses
            "ContactID" => "",
            "ContactIDType" => "",
            "UserID" => $this->user, // get from the mapping
            "UserIDType" => $this->ehr_usertype, // vanderbilt=SystemLogin, epic app orchard=external
            "CheckView" => "1000", // or 100?
            "CheckType" => array('Record'),
        );

        $params = array_replace_recursive($defaultParams, $params);

        return $this->postData($url, $params);
    }

    /**
     * This service logs an accepted Break-the-Glass form
     * to run through the action lists set up in Epic
     * 
     * to use the MRN as PatientID a PatientIDType of MRN must be specified
     * to use a login enabled user as UserID a UserIDType of SystemLogin must be specified
     *
     * @param array $params
     * @return void
     */
    function accept($params=array())
    {
        $mandatory_fields = array(
            'PatientID',
            'UserID',
            'DepartmentID',
            'CheckView',
        );
        $url = $this->getEndpointUrl(self::ENDPOINT_ACCEPT);

        $defaultParams = array(
            "PatientID" => '',
            "PatientIDType" => GlassBreaker::PATIENT_TYPE_MRN,
            "ContactID" => '',
            "ContactIDType" => '',
            "UserID" => $this->user,
            "UserIDType" => $this->ehr_usertype,
            "DepartmentID" => '', //"101000206",
            "DepartmentIDType" => GlassBreaker::DEPARTMENT_TYPE_INTERNAL, //"INTERNAL",
            "CheckView" => "100",
            "Reason" => "other", // 3
            "Explanation" => "REDCap Glass Breaker",
        );

        $params = array_replace_recursive($defaultParams, $params);

        // log to database
        \Logging::logEvent(
            $sql="",
            $object_type="redcap_glass_breaker",
            $event="MANAGE",
            $record="",
            $data_values=json_encode(array(
                'action' => 'accept',
                'params' => $params,
            ), JSON_PRETTY_PRINT),
            $change_reason= "Accepted break the glass"
        );
        /* $description = json_encode($params, JSON_PRETTY_PRINT);
        REDCap::logEvent("break the glass", $description); */
        return $this->postData($url, $params);
    }

    /**
     * This service handles logging a cancelled Break-the-Glass prompt
     * 
     * to use the MRN as PatientID a PatientIDType of MRN must be specified
     * to use a login enabled user as UserID a UserIDType of SystemLogin must be specified
     *
     * @param array $params
     * @return void
     */
    function cancel($params=array())
    {
        $mandatory_fields = array(
            'PatientID',
            'UserID',
            'UserIDType',
            'DepartmentID',
            'FailedReason',
        );
        $url = $this->getEndpointUrl(self::ENDPOINT_CANCEL);

        $defaultParams = array(
            "PatientID" => '',
            "PatientIDType" => GlassBreaker::PATIENT_TYPE_MRN,
            "UserID" => $this->user,
            "UserIDType" => $this->ehr_usertype,
            'ContactID' => '',
            'ContactIDType' => '',
            "DepartmentID" => '', // (101000206)
            "DepartmentIDType" => GlassBreaker::DEPARTMENT_TYPE_INTERNAL, //"INTERNAL",
            "FailedReason" => GlassBreaker::FAILED_REASON_CANCELLED_BTG_FORM, //"REDCap Glass Breaker canceled",
        );

        $params = array_replace_recursive($defaultParams, $params);

        // log to database
        \Logging::logEvent($sql="",
            $object_type="redcap_glass_breaker",
            $event="MANAGE",
            $record="",
            $data_values=json_encode(array(
                'action' => 'cancel',
                'params' => $params,
            ), JSON_PRETTY_PRINT),
            $change_reason= "Cancel break the glass"
        );
        /* $description = json_encode($params, JSON_PRETTY_PRINT);
        REDCap::logEvent("break the glass", $description); */
        return $this->postData($url, $params);
    }

    /**
     * This service returns Break-the-Glass initialization information required for client
     * development to implement Break-the-Glass outside of Epic. The information includes
     * the data requirements for the reason and explanation fields, the legal message,
     * a list of possible reasons, the message to display for inappropriate access,
     * the default Hyperspace timeout in minutes, and any reason-specific overrides
     * for the explanation field's data requirement
     *
     * @return void
     */
    function initialize()
    {
        $url = $this->getEndpointUrl(self::ENDPOINT_INITIALIZE);
        // log to database
        \Logging::logEvent($sql="",
            $object_type="redcap_glass_breaker",
            $event="MANAGE",
            $record="",
            $data_values="",
            $change_reason= "Initialize break the glass"
        );
        return $this->postData($url);
    }

    /**
     * post data to remote endpoint
     * all Break the glass endpoints use the POST method
     *
     * @param string $url
     * @param array $data
     * @param array $settings HTTP request settings (headers, options)
     * 
     * @throws Exception if the HttpClient request fails
     * 
     * @return mixed json decoded data
     */
    private function postData($url, $data=array(), $settings=array())
    {
        if (empty($this->authorization)) throw new \Exception("No authorization available.", 400);

        $default_settings = array(
            'options' => array(
                'timeout' => self::$timeout,
                'connect_timeout' => self::$connect_timeout,
            ),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => $this->authorization,
                'Epic-Client-ID' => $this->epic_client_ID,
                // 'Epic-User-IDType' => $this->epic_user_type
            ),
            'form_params' => $data,
        );
        $request_settings = array_replace_recursive($default_settings, $settings);

        $response = \HttpClient::request('POST', $url, $request_settings);
        return json_decode($response->getBody());
    }

}
