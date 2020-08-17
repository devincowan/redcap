<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$role_id = $_POST['role_id'];
$user = $_POST['username'];

//find the role user is trying to assign
$sql = "select * from redcap_user_roles where (project_id = " . PROJECT_ID . " AND role_id = '" . db_escape($role_id) ."')";
$q = db_query($sql);

while ($row = db_fetch_assoc($q)) {
  $user_rights = $row['user_rights'];
}

//check if user_rights are not enabled for main user (ignore this for super users)
if(!SUPER_USER && $user == USERID && $user_rights == 0){
  echo 0;
}else{
  echo 1;
}
