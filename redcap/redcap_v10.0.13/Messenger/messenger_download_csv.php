<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if (isset($_GET['title']) && isset($_GET['thread_id'])){

  $title = $_GET['title'];
  $thread_id = (int)$_GET['thread_id'];

    // Validate that the user has access to this thread
    $sql = "select 1 from redcap_messages_recipients where thread_id = $thread_id and recipient_user_id = ".UI_ID;
    $q = db_query($sql);
    if (db_num_rows($q) < 1) exit("ERROR");

  $result = Messenger::csvDownload($thread_id);
  $filename = "Conversation ".'"'.$title.'"'." ".date("Y-m-d_Hi").".csv";
  header('Pragma: anytextexeptno-cache', true);
  header("Content-type: application/csv");
  header('Content-Disposition: attachment; filename=' . $filename);

  echo $result;

}