<?php
/**
 * Created by PhpStorm.
 * User: taylorr4
 * Date: 4/30/2019
 * Time: 7:50 PM
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'init_global.php';
// Test saving data and then verifying it
$project_id = 1;
$dataSave = '[{"study_id":"1","first_name":"Test","last_name":"Record"}]';
$response = REDCap::saveData($project_id, 'json', $dataSave);
$dataGet = REDCap::getData($project_id, 'json', array(), array('study_id', 'first_name', 'last_name'));
// Output
print "Data check:\n";
if ($dataSave == $dataGet) {
    print "SUCCESS";
} else {
    throw new Exception("ERROR", 1);
}