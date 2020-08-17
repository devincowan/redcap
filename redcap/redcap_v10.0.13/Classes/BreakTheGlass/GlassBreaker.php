<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use DateTime;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\BreakTheGlass\Basket;
use Vanderbilt\REDCap\Classes\BreakTheGlass\Settings;
use Vanderbilt\REDCap\Classes\Fhir\FhirLogsMapper;

class GlassBreaker
{
    /**
     * authorization modes (REDCap setting)
     * 
     * disabled: Break the glass is disabled 
     * access_token: Use an OAuth2 access token and the standard FHIR base URL
     * username_token: use the username token and non OAuth2 base URL
     */
    const AUTHORIZATION_MODE_DISABLED = 'disabled';
    const AUTHORIZATION_MODE_ACCESS_TOKEN = 'access_token';
    const AUTHORIZATION_MODE_USERNAME_TOKEN = 'username_token';

    // patient types
    const PATIENT_TYPE_INTERNAL = 'Internal';
    const PATIENT_TYPE_EXTERNAL = 'External';
    const PATIENT_TYPE_CID = 'CID';
    const PATIENT_TYPE_MRN = 'MRN';
    const PATIENT_TYPE_NATIONALID = 'NationalID';
    const PATIENT_TYPE_CSN = 'CSN';
    const PATIENT_TYPE_FHIR = 'FHIR';
    // MyChart login name (WPR 110) ??
    // Identity ID Type Descriptor (I IIT 600) ??

    // department types
    const DEPARTMENT_TYPE_INTERNAL = 'Internal';
    const DEPARTMENT_TYPE_EXTERNAL = 'External';
    const DEPARTMENT_TYPE_EXTERNALKEY = 'ExternalKey';
    const DEPARTMENT_TYPE_CID = 'CID';
    const DEPARTMENT_TYPE_NAME = 'Name';
    const DEPARTMENT_TYPE_IIT = 'IIT';

    // user types
    const USER_INTERNAL = 'Internal';
    const USER_EXTERNAL = 'External';
    const USER_EXTERNALKEY = 'ExternalKey';
    const USER_CID = 'CID';
    const USER_NAME = 'Name';
    const USER_SYSTEMLOGIN = 'SystemLogin';
    const USER_ALIAS = 'Alias';
    const USER_IIT = 'IIT';

    const FAILED_REASON_TIMEOUT = 'Timeout';
    const FAILED_REASON_CANCELLED_BTG_FORM = 'Cancelled BTG Form';
    const FAILED_REASON_FAILED_AUTHENTICATION = 'Failed Authentication';

    /**
     * list of status returned by the check endpoint
     */
    const PATIENT_ACCESS_BLOCKED = 0; // inappropriate or blocked.
    const PATIENT_ACCESS_GRANTED = 1; // appropriate or granted.
    const PATIENT_ACCESS_PROTECTED = 2; // the user needs to break the glass in order to get access.
    const PATIENT_ACCESS_CANCELED = 'canceled'; // NOT SURE IF I'll USE THIS!!! use this when the user canceled the break the glass.

    /**
     * the API class
     *
     * @var API
     */
    public $api;

    /**
     * Break the glass settings
     *
     * @var Settings
     */
    private $settings;

    public function __construct($settings=array())
    {
        if(empty($settings)) throw new \Exception("No settings have been provided", 1);
        $this->settings = new Settings($settings);
        list($user, $user_type) = $this->settings->getUserNameAndType();
        $api_params = array(
            'base_url' => $this->settings->getBaseUrl(),
            'authorization' => $this->settings->getAuthorization(),
            'epic_client_ID' => $this->settings->getFhirClientID(),
            'user' => $user,
            'ehr_usertype' => $user_type,
            // 'epic_user_type' => $this->settings->getUsernameTokenUsertype(),
            // 'epic_user_ID' => $this->settings->getUsernameTokenUsername(),
        );
        $this->api = new API($api_params);
    }



    /**
     * check if settings are stored in the database and install
     *
     * @return void
     */
    public static function checkInstall()
    {
        $installer = new Installer();
        if(!$installed = $installer->isInstalled()) {
            $installer->install();
        }
    }

    /**
     * return if the Glass Breaker is enabled in the system
     *
     * @return boolean
     */
    public static function isSystemEnabled()
    {
        $config = \System::getConfigVals();
        $authorization_mode = $config['fhir_break_the_glass_enabled'] ?: '';
        $enabled_modes = array(self::AUTHORIZATION_MODE_ACCESS_TOKEN, self::AUTHORIZATION_MODE_USERNAME_TOKEN);
        return in_array($authorization_mode, $enabled_modes);
    }

    /**
     * check if break the glass can be enabled
     * in a project
     *
     * @return boolean
     */
    public static function isAvailable($project_id)
    {
        if(!self::isSystemEnabled()) return false;
        // continue only if the project is FHIR enabled
        if(!FhirEhr::isFhirEnabledInProject($project_id)) return false;
        return true;
    }

    public static function isEnabled($project_id)
    {
        if(!self::isSystemEnabled()) return false;
        $project = new \Project($project_id);
        return $project->project['break_the_glass_enabled']==1;
    }


    /**
     * break the glass applying the same
     * settings to a list of MRNs
     *
     * @param integer $project_id
     * @param string $mrn
     * @param array $params [reason,explanation,department,department_type]
     * @return array
     */
    public function breakTheGlass($project_id,$mrn,$params, $action='accept')
    {
        // make sure all required parameters are provided
        $required_params = array('reason','explanation','department','department_type');
        foreach($required_params as $key)
            if(!array_key_exists($key, $params)) throw new \Exception("Missing required parameter: '{$key}'", 400);
        
        $reason = $params['reason'];
        $explanation = $params['explanation'];
        $department = $params['department'];
        $department_type = $params['department_type'];

        $basket = new Basket($project_id);
        $params = array(
            'PatientID' => $mrn,
            // 'PatientID' => 'E2734',
            // 'PatientIDType' => 'EPI',
            'Reason' => $reason,
            'Explanation' => $explanation,
            'DepartmentID' => $department, // 10501101, // hardcoded for testing!!
            'DepartmentIDType' => $department_type, //'INTERNAL', // hardcoded for testing!!
        );
        try {
            // successful results are empty objects
            $result = $this->api->accept($params);
            $response = array('success' => isset($result));
            // update the status for the unlocked patient
            $basket->add($mrn, self::PATIENT_ACCESS_GRANTED);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
        }finally {
            return $response;
        }
    }

    /**
     * get a list of recently blocked mrns
     * from the FHIR logs table.
     * The query returns all the MRNs that have been blocked
     * recently (403 error), and that had no successful access (status 200) AFTER
     * the most recent blocked date.
     *
     * @param integer $project_id
     * @param integer $user_id
     * @return array
     */
    public function getRecentlyBlockedMrns($project_id, $user_id, $start_date=null)
    {
        $start_date = $start_date ?: new \DateTime('-1 days');
        $date_time = $start_date->format(FhirLogsMapper::DATE_FORMAT);
        $logs_table = FhirLogsMapper::TABLE_NAME;

        $query_string = "SELECT
            a.mrn,
            max(a.created_at) AS forbidden_time,
            b.created_at AS successful_time
            FROM $logs_table AS a
            LEFT JOIN
            (
                # sub query with most recent succesful call
                SELECT mrn, max(created_at) AS created_at,status FROM $logs_table
                WHERE created_at>='$date_time'
                AND project_id=$project_id
                AND user_id=$user_id
                AND status=200
                GROUP BY mrn
            ) AS b
            ON a.mrn=b.mrn
            WHERE a.created_at>='$date_time'
            AND a.project_id=$project_id
            AND a.user_id=$user_id
            AND a.status=403
            AND (b.created_at IS NULL OR a.created_at>=b.created_at)
            GROUP BY mrn";

        $result = db_query($query_string);
        $list = array();
        while ($row=db_fetch_assoc($result)) {
            $list[] = $row['mrn'];
        }

        return $list;
    }

    /**
     * Get a list of MRNs from the basket.
     * The list is stored in the session
     * and is seeded with recently blocked MRNs
     * coming from the FHIR logs table.
     * For each MRN from the logs table perform a check
     * to verify that "break the glass" is needed.
     *
     * @param integer $project_id
     * @param integer $user_id
     * @return array
     */
    public function getProtectedMrnList($project_id, $user_id)
    {
        $basket = new Basket($project_id);
        $mrn_list = $basket->getList();
        $project_list = &$mrn_list[$project_id];
        // get a list of recently blocked MRNs from the FHIR logs table
        $recently_blocked_mrns = $this->getRecentlyBlockedMrns($project_id, $user_id, new DateTime('- 10 days'));
        $protected_mrn_list = []; // collect the MRNs that are stored in session as unbroken (break the glass not performed)
        foreach ($recently_blocked_mrns as $mrn) {
            // check if the MRN is stored in session
            if(array_key_exists($mrn, $project_list)) {
                $status = $project_list[$mrn]['status'];
                // add to list if protected
            }else {
                // retrieve the status from the endpoint if the MRN is not stored in session
                $check_params = array('PatientID' => $mrn);
                $response = $this->api->check($check_params);
                // check the access type (defaults to inappropriate/blocked)
                $status = @$response->AccessType ?: self::PATIENT_ACCESS_BLOCKED;
                $basket->add($mrn, $status);
            }
            // add to list if protected by "break the glass"
            if($status==self::PATIENT_ACCESS_PROTECTED) $protected_mrn_list[] = $mrn;
            // $protected_mrn_list[] = $mrn; // WARNING just for testing
        }
        return $protected_mrn_list;
    }

}
