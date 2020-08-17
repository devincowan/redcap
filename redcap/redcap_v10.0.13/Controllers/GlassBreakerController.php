<?php

use Vanderbilt\REDCap\Classes\BreakTheGlass\Basket;
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;

class GlassBreakerController extends BaseController
{

    /**
     * GlassBreaker instance
     *
     * @var GlassBreaker
     */
    private $glass_breaker;

    public function __construct()
    {
        global $userid, $fhir_client_id, $fhir_endpoint_base_url,
            // generic break the glass settings
            $fhir_break_the_glass_enabled,
            $fhir_break_the_glass_ehr_usertype,
            // username_token specific:
            $fhir_break_the_glass_token_usertype,
            $fhir_break_the_glass_token_username,
            $fhir_break_the_glass_token_password,
            $fhir_break_the_glass_username_token_base_url;
        try {
            $settings = array(
                'authorization_mode' => $fhir_break_the_glass_enabled,
                'redcap_userid' => $userid,
                'fhir_client_id' => $fhir_client_id,
                'ehr_usertype' => $fhir_break_the_glass_ehr_usertype,
                'username_token_usertype' => $fhir_break_the_glass_token_usertype,
                'fhir_endpoint_base_url' => $fhir_endpoint_base_url,
                'username_token_username' => $fhir_break_the_glass_token_username,
                'username_token_password' => $fhir_break_the_glass_token_password,
                'username_token_base_url' => $fhir_break_the_glass_username_token_base_url,
            );
            
            $this->glass_breaker = new GlassBreaker($settings);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * route, get a list of reasons and legal messages from the epic endpoint
     *
     * @return string json response
     */
    public function initialize()
    {
        try {
            $response = $this->glass_breaker->api->initialize();
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * handles logging a cancelled Break-the-Glass prompt
     *
     * @return string json response
     */
    public function cancel()
    {
        try {
            $mrn = $_POST['mrn'];
            $department = $_POST['department'];
            $department_type = $_POST['department_type'];
            $reason = $_POST['reason'];
            $params = array(
                'PatientID' => $mrn,
                'DepartmentID' => $department,
                'DepartmentIDType' => $department_type, //'INTERNAL',
                'FailedReason' => $reason,
            );
            $response = $this->glass_breaker->api->cancel($params);
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * check if a patient is protected by the "break the glass" feature
     *
     * @return string json response
     */
    public function check()
    {
        try {
            $mrn = $_POST['mrn'];
            if(empty($mrn)) throw new Exception("A medical record number must be provided", 1);
            $params = array(
                'PatientID'=> $mrn,
                "PatientIDType" => 'MRN',
            );
            $response = $this->glass_breaker->api->check($params);
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * route, authorize a break the glass request
     * can only be access via the authentication
     * proxy 'protectedAccept'
     *
     * @return string json response
     */
    public function accept()
    {
        global $project_id;
        try {
            $this->checkCredentials(); // a REDCap password must be provided
            $mrn = $_POST['mrn'];
            $params = array(
                'reason' => $_POST['reason'],
                'explanation' => $_POST['explanation'],
                'department' => $_POST['department'],
                'department_type' => $_POST['department_type'],
            );
            $response = $this->glass_breaker->breakTheGlass($project_id, $mrn, $params);
            $this->printJSON($response, $code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * authentication proxy
     *
     * allow to perform an accept only if the user provides his REDCap password
     * 
     * @return void
     */
    private function checkCredentials()
    {
        global $userid;
        $password = $_POST['password'];
        $authenticated = checkUserPassword($userid, $password, $authSessionName = "break_the_glass_user_check");
        if(!$authenticated) throw new Exception("wrong REDCap password", 403);
        return true;
    }

    private function checkREDCapCredentials()
    {
        global $userid;
        $password = $_POST['password'];
        $authenticated = checkUserPassword($userid, $password, $authSessionName = "break_the_glass_user_check");
        if($authenticated) {
            $this->printJSON(array('success'=>true));
        }else {
            $this->printJSON(array('message'=>'wrong password'), 401);
        }
    }

    /**
     * Get a list of MRNs that are marked as 'protected'.
     * Such MRNs are protected by the Epic "break the glass"
     * feature and require an "accept" action from the user to
     * be performed.
     *
     * @return void
     */
    public function getProtectedMrnList()
    {
        global $project_id, $userid;
        $ui_id = \User::getUIIDByUsername($userid);
        try {
            $list = $this->glass_breaker->getProtectedMrnList($project_id, $ui_id);
            /*  
                //remove comment for DEBUG!
                $list = [
                "adasdas",
                "201688",
                "201833",
                "206925",
            ]; */
            $this->printJSON($list);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    public function clearProtectedMrnList()
    {
        global $project_id;
        $request_method = $_SERVER['REQUEST_METHOD'];
        if($request_method!=='DELETE') $this->printJSON('method not allowed', 405);
        $basket = new Basket($project_id);
        return $basket->empty();
    }

    public function index()
    {
        extract($GLOBALS);
        $mrns = $_POST['mrns'];
        $app_path_js = APP_PATH_JS;
        $browser_supported = !$isIE || vIE() > 10;
        $dist_url = APP_PATH_JS."glass-breaker/dist"; // path to the JS code
        $blade = Renderer::getBlade();
	    $blade->share('app_path_js', $app_path_js);
	    $blade->share('browser_supported', $browser_supported);
	    $blade->share('lang', $lang);
	    $blade->share('dist_url', $dist_url);
	    $browser_supported = !$isIE || vIE() > 10; 
        print $blade->run('glass-breaker.index', array());
    }

}