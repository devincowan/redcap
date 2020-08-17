<?php
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
global $lang;

# change revoke state in database via post call
if (isset($_POST['revoke']))
{
    $sql = "UPDATE redcap_mobile_app_devices SET revoked='".db_escape($_POST['revoke'])."' 
            WHERE project_id = ".PROJECT_ID." AND device_id = '".db_escape($_POST['device_id'])."'";
    db_query($sql);
    print $sql;
    print db_error();
}