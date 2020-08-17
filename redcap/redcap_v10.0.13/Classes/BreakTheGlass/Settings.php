<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
/**
 * Settings manager for a GlassBreaker object
 */
class Settings
{
    /**
     * break the glass authorization mode
     *
     * @var string
     */
    private $authorization_mode = GlassBreaker::AUTHORIZATION_MODE_ACCESS_TOKEN;
    /**
     * REDCap user that is using the break the glass features
     *
     * @var string
     */
    private $redcap_userid;

    /**
     * client ID of the FHIR app
     *
     * @var string
     */
    private $fhir_client_id;


    /**
     * type of EHR user
     * REDCap uses the mapping in redcap_ehr_user_map
     *
     * @var string
     */
    private $ehr_usertype = GlassBreaker::USER_SYSTEMLOGIN;

    /**
     * type of username used in username_token mode
     *
     * @var string
     */
    private $username_token_usertype;
    /**
     * username to use in username token authentication mode
     *
     * @var string
     */
    private $username_token_username;
    /**
     * password to use in username token authentication mode
     *
     * @var string
     */
    private $username_token_password;

    /**
     * base URL for username token authorization mode
     *
     * @var string
     */
    private $username_token_base_url;

    /**
     * access token needed to post data to the endpoint (OAuth2)
     *
     * @var string
     */
    private $access_token;

    /**
     * username token needed to post data to the endpoint (non-OAuth2)
     *
     * @var string
     */
    private $username_token;

    /**
     * base URL for access token authorization mode (standard FHIR endpoints)
     *
     * @var string
     */
    private $fhir_endpoint_base_url;

    public function __construct($settings=array())
    {
        foreach ($settings as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * getter for the authorization mode
     *
     * @return string
     */
    public function getAuthorizationMode()
    {
        return $this->authorization_mode;
    }


    /**
     * Get the type of EHR user.
     * REDCap uses the mapping in redcap_ehr_user_map.
     *
     * @return string
     */
    public function getEhrUsertype()
    {
        return $this->ehr_usertype;
    }

    /**
     * getter for the FHIR client id
     *
     * @return string
     */
    public function getFhirClientID()
    {
        if(empty($this->fhir_client_id))
            throw new \Exception("A FHIR client ID must be provided on class creation", 400);
        return $this->fhir_client_id;
    }

    /**
     * getter for the REDCap user
     * if the string username is provided then het the ui_id
     *
     * @return string
     */
    public function getRedcapUser()
    {
        if(empty($this->redcap_userid))
            throw new \Exception("A REDCap user must be provided on class creation", 400);
        if(!is_numeric($this->redcap_userid)) {
            $this->redcap_userid = \User::getUIIDByUsername($this->redcap_userid);
        }
        return $this->redcap_userid;
    }

    /**
     * getter for the authorization mode
     *
     * @return string
     */
    private function getAccessToken()
    {
        $userid = $this->getRedcapUser();
        $token_manager = new FhirTokenManager($userid);
        $access_token = $token_manager->getAccessToken();
        return $access_token;
    }

    /**
     * retrieve the authorization.
     * could be Bearer (FHIR) or Basic (non-OAuth2)
     * @throws Exception if no authorization method is available
     * @return string
     */
    public function getAuthorization()
    {
        $authorization_mode = $this->getAuthorizationMode();
        if($authorization_mode==GlassBreaker::AUTHORIZATION_MODE_ACCESS_TOKEN) return "Bearer ".$this->getAccessToken();
        if($authorization_mode==GlassBreaker::AUTHORIZATION_MODE_USERNAME_TOKEN) return "Basic ".$this->getUsernameToken();
        throw new \Exception(
            "No authorization method available.
            Please provide an access token for OAuth2 endpoints
            or a username token for non-OAuth2 endpoints.", 1);
    }

    /**
     * getter for the username_token_usertype
     *
     * @return string
     */
    public function getUsernameTokenUsertype()
    {
        return $this->username_token_usertype;
    }

    /**
     * getter for the username_token_username
     *
     * @return string
     */
    public function getUsernameTokenUsername()
    {
        return $this->username_token_username;
    }



    /**
     * Set the username token that will be used in non-OAuth2 requests
     *
     * @param string $username
     * @param string $password
     * @param string $user_type
     * @param string $format
     * @return void
     */
    public function getUsernameToken()
    {
        // return the access token provided on class creation if available
        if($this->username_token) return $this->username_token;
        $user_type = $this->username_token_usertype;
        $username = $this->username_token_username;
        $password = $this->username_token_password;
        $format = 'REST'; // only the REST format is supported
        return self::makeUsernameToken($username, $password, $user_type, $format);
    }

    /**
     * create a token that can be used in HTTP Basic Authentication
     * REDCap only supports REST
     * @see https://apporchard.epic.com/Article?docId=NonOauth2
     *
     * @param string $username
     * @param string $password
     * @param string $user_type emp, local or windows. emp, the default, is the one created in epic database
     * @param string $format can be REST or SOAP. determines which separator to use between user_type and username
     * @return void
     */
    public static function makeUsernameToken($username, $password, $user_type='EMP', $format='REST')
    {
        $separator = ($format==='REST') ? '$' : ':';
        $token_string = sprintf("%s%s%s:%s", strtolower($user_type), $separator, $username, $password);
        return base64_encode($token_string);
    }

    /**
     * access_token mode: extract the epic base URL for Epic REST endpoints
     * username_token mode: return the username token base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        switch ($this->authorization_mode) {
            case GlassBreaker::AUTHORIZATION_MODE_ACCESS_TOKEN:
                $reg_exp = '/(?<base>.+?)api\/FHIR\/(?:DSTU2|STU3|R4)\/?$/i';
                $base_url = preg_replace($reg_exp, '\1', $this->fhir_endpoint_base_url);
                return $base_url;
                break;
            case GlassBreaker::AUTHORIZATION_MODE_USERNAME_TOKEN:
                return $this->username_token_base_url;
                break;
            default:
                throw new \Exception("A valid authorization mode must be set to get a base URL", 400);
                break;
        }
    }

    /**
     * Query table to get REDCap username from passed EHR username
     *
     * @param string $userid
     * @return string
     */
    private function getEhrMappedUsername($userid)
    {
        $sql = sprintf(
            "SELECT ehr_username
            FROM redcap_ehr_user_map
            WHERE redcap_userid = '%s'
            LIMIT 1",
            db_escape($userid)
        );
        $result = db_query($sql);
        if(!$result) return false;
        if($result && $row = db_fetch_assoc($result)) return $row['ehr_username'];
    }

    /**
     * return EHR user mapped to the current REDCap user
     * @throws Exception if no mapping is found in the redcap_ehr_user_map table
     * @return string
     */
    public function getUser()
    {
        $userid = $this->getRedcapUser();

        $ehr_user = $this->getEhrMappedUsername($userid);
        if(empty($ehr_user)) throw new \Exception(sprintf("No mapped EHR user has been found for the user ID %u", $userid), 1);
        return $ehr_user;
        /* $user = $ehr_user ?: $userid;
        return $user; */
    }

    /**
     * try and get the mapped EHR user for the current REDCap user
     * fallback using the REDCap user and the 'system login' authentication type
     *
     * @return void
     */
    public function getUserNameAndType()
    {
        try {
            // try and get the mapped EHR user
            $user = $this->getUser();
            $user_type = $this->getEhrUsertype();
            return array($user, $user_type);
        } catch (\Exception $e) {
            // no EHR user; try the REDCap user with 'system login' auth type
            $redcap_user = $this->getRedcapUser();
            $user_type = GlassBreaker::USER_SYSTEMLOGIN;
            return array($redcap_user, $user_type);
        }
    }

    /**
     * magic getter for private properties
     *
     * @param string $name
     * @return void
     */
    /* public function __get($name)
    {
        if (property_exists($this, $name))
        {
            return $this->{$name};
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    } */
}