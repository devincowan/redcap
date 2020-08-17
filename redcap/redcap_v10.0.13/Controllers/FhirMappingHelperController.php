<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirException;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\MappingHelper\FhirMappingHelper;
use Vanderbilt\REDCap\Classes\Fhir\Resources\FhirResource;

/**
 * @method void index()
 * @method string fhirTest()
 * @method string fetchFhirResource()
 * @method string getTokens()
 */
class FhirMappingHelperController extends BaseController
{

    /**
     * instance of the model
     *
     * @var FhirMappingHelper
     */
    private $model;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * route, get a list of revisions
     *
     * @return string json response
     */
    public function fhirTest()
    {
        $response = array('test' => 123);
        $this->printJSON($response, $status_code=200);
    }

    /**
     * get info about a project
     *
     * @return void
     */
    public function getProjectInfo()
    {
        try {
            $model = new FhirMappingHelper();
            $project_id = $_GET['pid'];
            $project_info = $model->getProjectInfo($project_id);
            $datamart_active_revision = $model->getDatamartRevision($project_id);
            $cdp_mapping = $model->getClinicalDataPullMapping($project_id);
            $response = array(
                'info' => $project_info,
                'datamart_revision' => $datamart_active_revision,
                'cdp_mapping' => $cdp_mapping,
            );
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $response = compact('message');
            $this->printJSON($response, $status_code=400);
        }
    }

    public function exportCodes()
    {
        $codings = $_POST['codings'];
        if(!is_array($codings)) return;
        $lines = array_map(function($coding) {
            return sprintf("%s %s", $coding['code'], $coding['display']);
        }, $codings);
        FileManager::exportText($lines);
    }

    /**
     * get data from a FHIR endpoint
     *
     * @return string json response
     */
    public function fetchFhirResourceByMrn()
    {
        $model = new FhirMappingHelper();
        $mrn = $_GET['mrn'];
        $interaction = $_GET['interaction'];
        $resource_type = $_GET['resource_type'];
        $params = json_decode($_GET['params']);

        try {
            $resource = $model->getResourceByMrn($mrn, $resource_type, $interaction, $params);
            $response = array();
            if(is_a($resource, FhirResource::class))
            {
                $response['data'] = $resource;
                $response['metadata'] = $resource->getMetaData();
            }
            $this->printJSON($response, $status_code=200);
        } catch (FhirException $e) {
            $this->printJSON($e, $status_code=$e->getCode());
        }
    }

    /**
     * get data from a FHIR endpoint
     *
     * @return string json response
     */
    public function fetchFhirResource()
    {
        $model = new FhirMappingHelper();
        $endpoint = $_GET['endpoint'];
        $interaction = $_GET['interaction'];
        $resource_type = $_GET['resource_type'];
        $id = $_GET['id'];
        $params = $_GET['params'];
        try {
            $access_token = FhirMappingHelper::getAccessToken();
            if(in_array($interaction, array(\FhirEndpoint::INTERACTION_READ, \FhirEndpoint::INTERACTION_UPDATE, \FhirEndpoint::INTERACTION_DELETE)))
            {

            }else
            {

            }
            $resource = $model->getResource($endpoint, $access_token, $params);
            $response = array();
            if(is_a($resource, FhirResource::class))
            {
                $response['data'] = $resource;
                $response['metadata'] = $resource->getMetaData();
            }
            $this->printJSON($response, $status_code=200);
        } catch (FhirException $e) {
            $this->printJSON($e, $status_code=$e->getCode());
        }
    }

    /**
     * get a list of token for a user_id
     *
     * @return void
     */
    public function getTokens()
    {
        global $userid;
        $model = new FhirMappingHelper();
        $user = \User::getUserInfo($userid);
        if(!$user)
        {
            $e = new FhirException('No user has been specified', $code=400);
            $this->printJSON($e, $status_code=$e->getCode());
        }
        $tokens = $model->getTokens($user);
        $response = $tokens;
        $this->printJSON($response, $status_code=200);
    }
    /**
     * get a list of token for a user_id
     *
     * @return void
     */
    public function getUserInfo()
    {
        global $userid;
        $model = new FhirMappingHelper();
        $user = new FhirUser($userid);
        if(!$user)
        {
            $e = new FhirException('No user has been specified', $code=400);
            $this->printJSON($e, $status_code=$e->getCode());
        }
        $tokens = $model->getTokens($userid);
        $response = array(
            'info' => $user,
            'tokens' => $tokens,
        );
        $this->printJSON($response, $status_code=200);
    }

    /**
     * get settings and parameters for the app
     *
     * @return void
     */
    public function getSettings()
    {
        global $lang, $project_id, $fhir_source_system_custom_name, $fhir_standalone_authentication_flow;
        $model = new FhirMappingHelper();
        $base_url = preg_replace('/(.+?)\/?$/', '$1', APP_PATH_WEBROOT_FULL); // remove trailing slash
        $settings = array(
            'project_id' => $project_id,
            'lang' => $lang,
            'standalone_authentication_flow' => $fhir_standalone_authentication_flow,
            'standalone_launch_enabled' => $fhir_standalone_authentication_flow==FhirLauncher::MODE_STANDALONE_LAUNCH,
            'standalone_launch_url' => $base_url.FhirLauncher::getStandaloneLaunchUrl(),
            'ehr_system_name' => strip_tags($fhir_source_system_custom_name),
            'blocklisted_codes' => $model->getBlocklistedCodes(),
        );
        $this->printJSON($settings, $status_code=200);
    }

    /**
     * get fields and codes
     *
     * @return void
     */
    public function getFhirMetadata()
    {
        $model = new FhirMappingHelper();
        $ddp = new \DynamicDataPull(0, 'FHIR');
        $source_fields = $ddp->getExternalSourceFields();
        $fields = $model->getGroupedSourceFields($source_fields);
        $data = array(
            'fields' => $fields,
            'codes' => array_keys($source_fields),
        );
        $this->printJSON($data, $status_code=200);
    }

    /**
     * send a notification to an admin
     * when a user wants to add a code
     * to those available in REDCap
     * admin will add the codes in Resources/misc/loinc_labs_additions.csv
     * @return void
     */
    public function notifyAdmin()
    {
        global $lang, $userid, $project_id, $project_contact_email;
        $ui_id = \User::getUIIDByUsername($userid);
        $user = \User::getUserInfo($userid);
        $user_email = $user['user_email'];
        $user_fullname = sprintf("%s %s", $user->user_firstname, $user->user_lastname);
        $project = new \Project($project_id);
        $project_admin_email = $project->project['project_contact_email'];

        $code = $_POST['code'];
        $resource_type = $_POST['resource_type'];
        $interaction = $_POST['interaction'];
        $mrn = $_POST['mrn'];

        /**
         * send an email
         */
        $email = new \Message();
        $emailSubject = "[REDCap] Request to insert a new FHIR code";
        $email->setFrom($user_email);
        $email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
        $to = array($project_contact_email, $project_admin_email);
        $email->setTo(implode(';', $to));
        $body = \Renderer::run('mapping-helper.emails.request-code', compact('lang', 'emailSubject','user_email','user_fullname','project_id','code','resource_type','interaction','mrn'));
        // Finalize email
        $email->setBody($body);
        $email->setSubject($emailSubject);
        
        if($email_sent = $email->send())
        {
            $response = array(
                'message'=>'email sent.',
                'code' => $code,
                'userid' => $userid,
                'project_id' => $project_id,
                'project' => $project,
            );
            $this->printJSON($response, $status_code=200);
        }else {
            $response = array('message'=>'error sending the email.');
            $this->printJSON($response, $status_code=400);
        }
        
    }

    public function index()
    {
        extract($GLOBALS);
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        $browser_supported = !$isIE || vIE() > 10;
        // $dist_path = APP_PATH_DOCROOT.'Resources/js/mapping-helper/dist';
        $app_path_js = APP_PATH_JS; // path to the JS folder
        print \Renderer::run('mapping-helper.index', compact('browser_supported', 'lang', 'app_path_js'));
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

}