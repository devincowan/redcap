<?php

require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStats;

if (!SUPER_USER) redirect(APP_PATH_WEBROOT);
if($_POST['export']) $fhirStats->exportData();
if($_GET) {
    $params = array(
        'date_start' => $date_start = $_GET['date_start'],
        'date_end' => $date_end = $_GET['date_end'],
    );
    $fhirStats = new FhirStats($params);
    $results = $fhirStats->getCounts();
}
include 'header.php';

$periods = array(
    'Past Day',
    'Past Week',
    'Past Month ',
    'Past 3 Months ',
    'Past 6 Months ',
    'Past Year',
    'All',
);

$date_start = $date_start ? date("m-d-Y", strtotime($date_start)) : ''; // convert dates to jquery datepicker format
$date_end = $date_end ? date("m-d-Y", strtotime($date_end)) : ''; // convert dates to jquery datepicker format
$data = compact('periods','results');

$blade = Renderer::getBlade();
$blade->share('date_start',$date_start);
$blade->share('date_end', $date_end);
print $blade->run('control-center.fhir-stats.index', $data);

?>


<?php include 'footer.php'; ?>