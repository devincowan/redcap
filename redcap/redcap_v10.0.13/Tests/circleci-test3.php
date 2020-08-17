<?php
/**
 * Created by PhpStorm.
 * User: taylorr4
 * Date: 4/30/2019
 * Time: 8:12 PM
 */
// Presets
$_GET['pid'] = $project_id = 1;
$user = 'site_admin';
define("REDCAP_API_NO_EXIT", true);
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'init_project.php';
// Make sure project has a user with a token
$tok = strtoupper(md5("$user&$project_id&" . microtime()));
$sql = "replace into redcap_user_rights (project_id, username, api_token) values ($project_id, '$user', '$tok')";
db_query($sql);
// Since we're having trouble calling localhost via curl in this context, we'll simulate an API call via PHP "require"
$dataExpected = '[{"study_id":"1","first_name":"Test","last_name":"Record"}]'; // This data was saved in the previous test, so it is already there.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['content'] = 'record';
$_POST['token'] = $tok;
$_POST['format'] = 'json';
$_POST['fields'] = array('study_id', 'first_name', 'last_name');
require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'API' . DIRECTORY_SEPARATOR . 'index.php';
$dataGet = ob_get_clean();
// Output
print "API check:\n";
if ($dataExpected == $dataGet) {
    print "SUCCESS";
} else {
    throw new Exception("ERROR", 1);
}