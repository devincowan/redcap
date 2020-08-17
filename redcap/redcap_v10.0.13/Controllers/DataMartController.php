<?php

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher;

/**
 * @method string revisions()
 * @method string getUser()
 * @method string addRevision()
 * @method string deleteRevision()
 * @method string exportRevision()
 * @method string importRevision()
 * @method string sourceFields()
 * @method string runRevision()
 * @method string approveRevision()
 * @method void index()
 */
class DataMartController extends BaseController {

    /**
     * maximum number of simultaneous revisions per hour
     */
    const MAX_REVISIONS_PER_HOUR = 10;

    /**
     * instance of the model
     *
     * @var DataMart
     */
    private $model;

    /**
     * list of allowed routes with request types
     *
     * @var array
     */
    protected $routes = array(
        // 'index' => array('GET'), // main page
        // 'revisions' => array('GET'), // get all revisions
        // 'getUser' => array('GET'), // get DataMart user
        // 'sourceFields' => array('GET'), // get source fields
        // 'addRevision' => array('POST'), // add a revision (and submit a revision request)
        // 'runRevision' => array('POST'), // run a revision
        // 'approveRevision' => array('POST'), // approve a revision
        // 'deleteRevision' => array('DELETE'), // delete a revision
        // 'exportRevision' => array('GET'), // export a revision
        // 'importRevision' => array('POST'), // import a revision
    );

    public function __construct()
    {
        parent::__construct();
        // $this->enableCORS();
        $this->model = new DataMart();
    }

    /**
     * route, get a list of revisions
     *
     * @return string json response
     */
    public function revisions()
    {
        $project_id = $_REQUEST['pid'];
        if($request_id = $_REQUEST['request_id'])
        {
            $revision = $this->model->getRevisionFromRequest($request_id);
            if(!$revision)
            {
                $error = new JsonError(
                    $title = 'revision not found',
                    $detail = sprintf("no revision associated to the request ID %s has been found", $request_id),
                    $status = 400,
                    $source = PAGE // get the current page
                );
                $this->printJSON($error, $status_code=400);
            }
            $response = array($revision);
        }else
        {
            $response = $this->model->getRevisions($project_id);
        }
        $this->printJSON($response, $status_code=200);
    }

    /**
     * route, get the user
     *
     * @return string json response
     */
    public function getUser()
    {
        global $userid;
        /* 
         * static version
        $modelClassName = get_class($this->model);
        $response =   call_user_func_array(array($modelClassName, "getUserInfo"), array($this->username, $this));
        */


        $project_id = $_REQUEST['pid'];
        $response =   $this->model->getUser($userid, $project_id);
        $this->printJSON($response, $status_code=200);
    }

    /**
     * add a revision
     *
     * @return string
     */
    public function addRevision()
    {
        $settings = array(

            'project_id'    => $_REQUEST['pid'],
            'request_id'    => $_REQUEST['request_id'],
            'mrns'          => $_REQUEST['mrns'],
            'fields'        => $_REQUEST['fields'],
            'date_min'      => $_REQUEST['date_min'],
            'date_max'      => $_REQUEST['date_max'],
        );
        $response = $this->model->addRevision($settings);
        if($response==true)
            $this->printJSON($response, $status_code=200);
        else
            $this->printJSON($response, $status_code=400);
    }

    /**
     * delete a revision
     *
     * @return void
     */
    public function deleteRevision()
    {
        // gete the data from the DELETE method
        $data = file_get_contents("php://input");
        $params = json_decode($data);
        $id = $params->revision_id;
        $deleted = $this->model->deleteRevision($id);
        if($deleted==true)
        {
            $response = array('data'=>array('id'=>$id));
            $this->printJSON($response, $status_code=200);
        } else
        {
            // typical structure for a json object
            $error = new JsonError(
                $title = 'Revision not deleted',
                $detail = sprintf("The revision ID %u could not be deleted.", $id ),
                $status = 400,
                $source = PAGE
            );
            $this->printJSON($error, $status_code=400);
        }
    }
    /**
     * export a revision
     *
     * @return void
     */
    public function exportRevision()
    {
        $revision_id = $_REQUEST['revision_id'];
        $format = isset($_REQUEST['format']) ? $_REQUEST['format'] : 'csv';
        $csv_delimiter = isset($_REQUEST['csv_delimiter']) ? $_REQUEST['csv_delimiter'] : ",";
        $fields = isset($_REQUEST['fields']) ? $_REQUEST['fields'] : array();
        $this->model->exportRevision($revision_id, $fields, $format, $csv_delimiter);
    }

    /**
     * parse a file for a revision
     *
     * @return string
     */
    public function importRevision()
    {
        $uploaded_files = FileManager::getUploadedFiles();
        $files = $uploaded_files['files'];
        $file = reset($files); // get the first element in the array of files
        if($file)
        {
            $data = $this->model->importRevision($file);
            $this->printJSON($data, $status_code=200);
        }else
        {
            $error = new JsonError(
                $title = 'no file to process',
                $detail = 'A file must be provided to import a revision',
                $status = 400,
                $source = PAGE // get the current page
            );
            $this->printJSON($error, $status_code=400);
        }
    }

    /**
     * get the sourcefields
     *
     * @return void
     */
    /* private function sourceFieldsOriginal()
    {
        $response = $this->model->getSourceFields();
        $this->printJSON($response, $status_code=200);
    } */
    /**
     * get the sourcefields
     *
     * @return string
     */
    public function sourceFields()
    {
        $fields = $this->model->getExternalFields();
        $response = array('data' => $fields);
        $this->printJSON($response, $status_code=200);
    }

    /**
     * get settings and parameters for the datamart
     *
     * @return void
     */
    public function getSettings()
    {
        global $lang, $project_id, $fhir_source_system_custom_name, $fhir_standalone_authentication_flow;
        

        $base_url = preg_replace('/(.+?)\/?$/', '$1', APP_PATH_WEBROOT_FULL); // remove trailing slash
        $settings = array(
            'project_id' => $project_id,
            'lang' => $lang,
            'standalone_authentication_flow' => $fhir_standalone_authentication_flow,
            'standalone_launch_enabled' => $fhir_standalone_authentication_flow==FhirLauncher::MODE_STANDALONE_LAUNCH,
            'standalone_launch_url' => $base_url.FhirLauncher::getStandaloneLaunchUrl(),
            'ehr_system_name' => strip_tags($fhir_source_system_custom_name),
            'mapping_helper_url' => APP_PATH_WEBROOT."index.php?pid={$project_id}&route=FhirMappingHelperController:index",
            'fhir_fields' => $this->getFhirFields(),
        );
        $this->printJSON($settings, $status_code=200);
    }

    private function getFhirFields() {
        $ddp = new \DynamicDataPull(0, 'FHIR');
        $source_fields = $ddp->getExternalSourceFields();
        $groups = array();
        foreach ($source_fields as $field) {
            $category = $field['category'];
            if(empty($category)) {
                // this is for ID field (no category or subcategory)
                $groups[] = $field;
                continue;
            }
            // make sure category is an array
            if(!is_array($groups[$category])) $groups[$category] = array();
            // priority to sub categories
            if($sub_category = $field['subcategory'])
            {
                // make sure sub_category is an array
                if(!is_array($groups[$category][$sub_category])) $groups[$category][$sub_category] = array();
                $groups[$category][$sub_category][] = $field;
            }else
            {
                $groups[$category][] = $field;
            }
        }
        return $groups;
    }

    /**
     * helper function that sends an error response if the maximum
     * number of requests for a page has been reached
     *
     * @param integer $limit
     * @return string|null
     */
    public function throttle($limit=10)
    {
        $page = PAGE; // get the current page
        $throttler = new Throttler();
        
        if($throttler->throttle($page, $limit))
        {
            // typical structure for a json object
            $error = new JsonError(
                $title = 'Too Many Requests',
                $detail = sprintf('The maximum of %u simultaneus request%s has been reached. Try again later.', $limit, $singular=($limit===1) ? '' : 's' ),
                $status = Throttler::ERROR_CODE,
                $source = PAGE
            );

            $this->printJSON($error , $status_code=$status);
        }
    }

    /**
     * method for testing the throttle
     *
     * @return string
     */
    private function throttleTest()
    {
        $this->throttle(1); //limit to a maximum of 1
        sleep(10);
        $this->printJSON(array('success' => true, 'errors'=>array()), $status_code=200);
    }

    /**
     * run a revision
     *
     * @return string
     */
    public function runRevision()
    {
        $this->throttle(self::MAX_REVISIONS_PER_HOUR);

        $id = $_POST['revision_id'];
        $mrn = $_POST['mrn'];
        try {
            $response = $this->model->runRevision($id, $mrn);
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $error = new JsonError(
                $title = 'Cannot run the revision',
                $detail = $e->getMessage(),
                $status = $e->getCode(),
                $source = PAGE
            );
            $this->printJSON($error, $status_code=$e->getCode());
        }
    }

    /**
     * approve a revision
     *
     * @return string
     */
    public function approveRevision()
    {
        $id = $_REQUEST['revision_id'];
        $revision = $this->model->approveRevision($id);
        if($revision)
        {
            $response = array('data'=>$revision);
            $this->printJSON($response, $status_code=200);
        }else
        {
            $error_code = 401; //unauthorized
            $error = new JsonError(
                $title = 'Revision not approved',
                $detail = sprintf("The revision ID %u could not be approved.", $id),
                $status = $error_code,
                $source = PAGE
            );
            $this->printJSON($error, $status_code=$error_code);
        }
    }

    public function index()
    {
        extract($GLOBALS);
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        $browser_supported = !$isIE || vIE() > 10;
        $datamart_enabled = DataMart::isEnabled($project_id);
        $app_path_js = APP_PATH_JS; // path to the JS directory
		// generate CSS and javascript tags
        $blade = Renderer::getBlade();
		$blade->share('app_path_js', $app_path_js);
        print $blade->run('datamart.index', compact('browser_supported', 'datamart_enabled'));
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

    public function index1()
    {
        extract($GLOBALS);
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        if ($isIE && vIE() <= 10) : ?>
            <h3>
                <i class="fas fa-exclamation-triangle"></i>
                <span>This feature is not available for your browser.</span>
            </h3>
        <?php elseif (DataMart::isEnabled($project_id)) : ?>
            <?php FhirMappingHelper::printLink($project_id) ?>
            <div id="datamart-target"></div>
            <script type="text/javascript">
                (function (window, document) {
                    /* var translations = {
                        'step1': "<?php print js_escape2($lang['data_mart_refresh_001']) ?>",
                        'step2': "<?php print js_escape2($lang['data_mart_refresh_002']) ?>",
                        'step3': "<?php print js_escape2($lang['data_mart_refresh_003']) ?>",
                    } */
                    if (window._DATAMART_) {
                        var target = document.getElementById("datamart-target");
                        var datamart = new _DATAMART_({target: target});
                        window.dataMart = datamart
                    }
                }(window, document));
            </script>
            
        <?php else :?>
            <h3>
                <i class="fas fa-info-circle"></i>
                <span>This is not a Datamart Project!</span>
            </h3>
        <?php endif;
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

}