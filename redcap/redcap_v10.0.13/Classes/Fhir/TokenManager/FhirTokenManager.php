<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use Vanderbilt\REDCap\Classes\Fhir\FhirServices;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

class FhirTokenManager
{
    /**
     * user ID to use when storing and retrieving tokens from the database
     *
     * @var integer
     */
    private $user_id; // must be defined on instance creation

    /**
     * maximum number of valid token to retrieve from database
     *
     * @var integer
     */
    private $token_limit; // the maximum number of token to try when fetching data


    /**
     * the index of the active token
     *
     * @var FhirToken
     */
    private $activeToken;

    
    /**
     * list of available tokens
     * if a token is not valid, the next one in this list
     * will be used
     * 
     *
     * @var FhirToken[]
     */
    private $tokens = array();

    function __construct($user_id=null, $patient_id=null, $token_limit=10)
    {
        $this->user_id = $user_id;
        $this->patient_id = $patient_id;
        $this->token_limit = $token_limit;
        // set a list of valid tokens
        $this->tokens = $this->getTokens($this->user_id, $this->patient_id);
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
     * get a token using the client credentials flow
     *
     * @return FHIRToken|false a valid access token or false if not 
     */
    private function getTokenUsingClientCredentials()
    {
        try {
            $fhirServices = self::getFhirServices();
            $scopes = FhirServices::$client_credentials_scopes;
            $new_token = $fhirServices->getTokenWithClientCredentials($scopes);
            $token = self::storeToken($new_token, $this->user_id);
            return $token;
        } catch (\Exception $e) {
            $exception_code = $e->getCode();
            $exception_message = $e->getMessage();
            $exception_data = array();
            if($e instanceof \DataException) $exception_data = $e->getData();
            \Logging::logEvent( "", "FHIR", "MANAGE", $exception_data, $exception_code, $exception_message );
            return false;
        }
    }

    /**
     * get a valid access token for a user
     * refresh the token if expired
     * if the refresh fails, try the next token
     *
     * @return FHIRToken|false a valid access token or false if not 
     */
    public function getToken()
    {
        global $fhir_standalone_authentication_flow;
        $token = $this->getActiveToken();
        if($token===false)
        {
            if($fhir_standalone_authentication_flow==FhirEhr::AUTHENTICATION_FLOW_CLIENT_CREDENTIALS)
            {
                // try to get a token using client credentials flow
                return $this->getTokenUsingClientCredentials();
            }else
            {
                return false; // stop if no tokens available
            }
        }
        

        if($token->isExpired())
        {
            // refresh if expired
            $fhirServices = self::getFhirServices();
            $token->refresh($fhirServices);
        }

        // check if token is valid
        if($token->isValid()) return $token;

        // if the token has not been refreshed try the next token
        $this->getNextActiveToken();
        return $this->getToken();
    }

    /**
     * get an access token
     * 
     * @throws Exception if access token is not available
     * @return string
     */
    public function getAccessToken()
    {
        global $lang;
        $token = $this->getToken();
        if(!is_a($token, FhirToken::class) || !($access_token = $token->getAccessToken()) )
		{
			throw new \Exception($message = $lang['data_entry_398'], 401); // 401 = unauthorized client
        }
        return $access_token;
    }

    /**
     * set and return the active token from the list of available tokens
     *
     * @return FhirToken
     */
    private function getActiveToken()
    {
        // the active token is not set: reset the pointer and get the first element
        if(!isset($this->activeToken)) reset($this->tokens);
        $this->activeToken = current($this->tokens);
        return $this->activeToken;
    }

    /**
     * move the pointer of the active tokens to the next element
     *
     * @return FhirToken
     */
    public function getNextActiveToken()
    {
        $this->activeToken = next($this->tokens);
        return $this->activeToken;
    }

    /**
     * no token available
     *
     * @throws Exception
     */
    private function throwNoTokensAvailableException()
    {
        throw new \Exception("Error: no tokens available.", 1);
    }

    /**
     * get all valid tokens for a specific user
     * an token is valid if
     *  - access_token is not expired
     *      OR
     *  - refresh_token is not older than 30 days
     * 
     * if a user is specified then only his tokens are selected
     * (usually we have no user when a cron job is running)
     * 
     * specific tokens are prioritized.
     * the priority order is:
     *  - patient
     *  - expiration
     *
     * @param integer $user_id
     * @param string $patient
     * @return FHIRToken[]
     */
    public function getTokens($user_id=null, $patient=null)
    {
        $list = array();
        // get 
        $query_string = 'SELECT * FROM redcap_ehr_access_tokens';
        $query_string .= sprintf(' WHERE 
                        (
                            (access_token IS NOT NULL AND expiration > "%1$s")
                            OR
                            (refresh_token IS NOT NULL AND expiration > DATE_SUB("%1$s", INTERVAL 30 DAY))
                        )', NOW);
        // set constraint if user is sepcified
        if(isset($user_id)) $query_string .= sprintf(" AND token_owner = '%u'", $user_id);

        $order_by_clauses = array();               
        // if(isset($user_id)) $order_by_clauses[] = sprintf("FIELD (token_owner, %u) DESC", $user_id);
        if(isset($patient)) $order_by_clauses[] = sprintf("FIELD (patient, '%s') DESC", $patient);
        $order_by_clauses[] = 'expiration DESC';

        $order_by_string = " ORDER BY ".implode(', ', $order_by_clauses);

        $query_string .= $order_by_string;
        $query_string .= sprintf(" LIMIT %u", $this->token_limit);
        // query the DB
        $result = db_query($query_string);
        while($tokenInfo = db_fetch_object($result))
        {
            $list[] = new FHIRToken($tokenInfo);
        }
        // if there are no tokens throw an exception
        // if(empty($list)) $this->throwNoTokensAvailableException();

        return $list;
    }

    /**
     * persist a token to the database
     *
     * @param object|array $token_data
     * @param integer $user_id
     * @return FHIRToken
     */
    public static function storeToken($token_data, $user_id=null)
    {
        $token = new FHIRToken($token_data);
        if($user_id) $token->setOwner($user_id);
        $token->save();
        return $token;
    }

    // If there is an institution-specific MRN, then store in access token table to pair it with the patient id
    /**
     * Undocumented function
     *
     * @param string $patient
     * @param string $mrn
     * @return void
     */
	public function storePatientMrn($patient, $mrn)
	{
	    if (empty($mrn)) return false;
		$query_string = sprintf("UPDATE redcap_ehr_access_tokens SET mrn = %s
				        WHERE patient='%s'", checkNull($mrn), db_escape($patient));
		return db_query($query_string);
    }
    
    /**
     * cleanup MRN entries for a user
     * 
     * the table could contain orphaned MRNs 
     * if the FHIR ID changes for any reason (i.e. EHR updates)
     *
     * @param integer $user_id token owner
     * @param string $mrn
     * @return boolean
     */
    public function removeMrnDuplicates($mrn)
    {
        if(!$user_id = $this->user_id) return;
        $query_string = sprintf(
            "DELETE FROM redcap_ehr_access_tokens 
            WHERE mrn=%s AND token_owner=%u",
            checkNull($mrn),$user_id);
        return db_query($query_string);
    }

    /**
     * remove all entries of a FHIR id (patient)
     * used when we get a 404 error using the FHIR ID in a patient.read call
     *
     * @param string $patient_id
     * @return void
     */
    public function removeCachedPatient($patient_id)
    {
        if(!$user_id = $this->user_id) return;
        $query_string = sprintf(
            "DELETE FROM redcap_ehr_access_tokens 
            WHERE patient=%s",
            checkNull($patient_id),$user_id);
        return db_query($query_string);
    }

    /**
     * Add patient's access token to db table for later usage
     *
     * @param [Object] $tokenInfo
     * EPIC: 
     * access_token:"i8ybbkfXIrgSmqQZURcn_x1_U5ERCvsyR8jAl_TUBXR-jCQxeKu6vAF_3qMeedd6kdKjOIYNI3hOAbL-tvRCsUvi2a9Jc9xOYomc3FN5FRqrr283YQQlFPZ5Hx0gSgN1"
     * token_type:"bearer"
     * expires_in:3600
     * scope:"AllergyIntolerance.read AllergyIntolerance.search Condition.read Condition.search Immunization.read Immunization.search MedicationOrder.read MedicationOrder.search Observation.read Observation.search Patient.read Patient.search "
     * state:"m3ofg20jfjs0ktg9rhpht6qkv8"
     * refresh_token:"Kb8mxi2Lme3NLFxTIY181lUIsUdk_R_uMcxpwGyq0meiLygUC2fYQfB8xBpoyeiZ7JDNSX1SHE_lTYhPo-WUUmUFBF9hWbCd10WEnTA8uf4E3JYKcErOdSL2Rz2EZe0L"
     * need_patient_banner:"false"
     * patient:"Tnf4SJzRHNFDdwDNiqj27h2ilfTIOLSklOIGPR9iBj44B"
     * smart_style_url:"https://ic1-dev.service.vumc.org/Interconnect-DEV-FHIR/api/epic/2016/EDI/HTTP/style/100043/I0VFRjdGRnwjQzEyMTI3fCMxNEE1RkZ8I0Q1RUJGRnwjODZCNTQwfCMwMDAwMDB8MHB4fDEwcHh8fEFyaWFsLCBzYW5zLXNlcmlmfCdTZWdvZSBVSScsIEFyaWFsLCBzYW5zLXNlcmlmfHw%3D.json"
     * 
     * ------
     * 
     * SMART Health IT:
     * need_patient_banner:false
     * smart_style_url:"https://launch.smarthealthit.org/smart-style.json"
     * patient:"22f14621-f275-4ece-baae-096b292f900b"
     * encounter:"4998eaa1-d654-438b-bbe9-81c1db4212bd"
     * token_type:"bearer"
     * scope:"launch patient/Patient.read patient/Observation.read patient/Condition.read patient/MedicationOrder.read patient/AllergyIntolerance.read patient/FamilyMemberHistory.read  patient/DiagnosticReport.read  patient/Immunization.read  patient/Procedure.read  patient/Device.read  patient/DocumentReference.read"
     * expires_in:3600
     * access_token:"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9
     * @param [type] $mrn
     * @return void
     */



}

