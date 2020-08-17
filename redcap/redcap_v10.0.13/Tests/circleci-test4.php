<?php
/**
 * Created by PhpStorm.
 * User: taylorr4
 * Date: 5/1/2019
 * Time: 4:40 AM
 */
// Presets
$_GET['pid'] = $project_id = 4;
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'init_project.php';
// Get public survey hash
$_GET['s'] = $hash = Survey::getSurveyHash($Proj->firstFormSurveyId, $Proj->firstEventId);
// Since we're having trouble calling localhost via curl in this context, we'll simulate an HTTP survey page call via PHP "require"
require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Surveys' . DIRECTORY_SEPARATOR . 'index.php';
$page = ob_get_clean();
// Output
print "Survey check:\n";
if (strpos($page, 'Example Survey') !== false && strpos($page, 'Powered by REDCap') !== false) {
    print "SUCCESS";
} else {
    throw new Exception("ERROR", 1);
}