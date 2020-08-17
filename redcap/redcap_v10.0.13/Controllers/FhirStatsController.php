<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStats;

class FhirStatsController extends BaseController
{


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * export data to CSV
     *
     * @return void
     */
    public function export()
    {
        $params = array();
        if($_GET) {
            $params['date_start'] = $date_start = $_GET['date_start'];
            $params['date_end'] = $date_end = $_GET['date_end'];
        }
        $fhir_stats = new FhirStats($params);
        $fhir_stats->exportData();
    }

    /**
     * get a link to export data
     *
     * @param array $params search parameters
     * @return string
     */
    private function getExportLink($params)
    {
        $query_params = array(
            'route' => __CLASS__.":export"
        );
        $query_params = array_merge($query_params, $params);
        $export_link = APP_PATH_WEBROOT."index.php?".http_build_query($query_params);
        return $export_link;
    }
    
    public function index()
    {
        global $lang;
        if (!SUPER_USER) redirect(APP_PATH_WEBROOT);
        $results = array();

        $show = isset($_GET['show']); // show results if show is in get parameters
        // check if show has been set
        if($show) {
            $params = array();
            if(isset($_GET['date_start'])) $params['date_start'] = $date_start = $_GET['date_start'];
            if(isset($_GET['date_end'])) $params['date_end'] = $date_end = $_GET['date_end'];
            $fhir_stats = new FhirStats($params);
            $results = $fhir_stats->getCounts();
        }
        include APP_PATH_DOCROOT . 'ControlCenter/header.php';

        $date_start = empty($date_start) ? '' : date("m-d-Y", strtotime($date_start)); // convert dates to jquery datepicker format
        $date_end = empty($date_end) ? '' : date("m-d-Y", strtotime($date_end)); // convert dates to jquery datepicker format
        $export_link = $this->getExportLink($params);
        
        $browser_supported = !$isIE || vIE() > 10;
        $data = compact('date_start','date_end','results','show','export_link','browser_supported');

        $blade = Renderer::getBlade(); // get an instance of the templating engine
        // share variables to make them available in sub-views
        $blade->share('lang', $lang);
        $blade->share('app_path_js', APP_PATH_JS);
        print $blade->run('control-center.fhir-stats.index', $data);
        include APP_PATH_DOCROOT . 'ControlCenter/footer.php';
    }

}