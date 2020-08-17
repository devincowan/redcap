<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// If user is not a super user then do nothing
if (!$super_user) exit();

// do I need to create a log for this event?

$result = ToDoList::csvDownload();
$filename = "Todo-List_".date("Y-m-d_Hi").".csv";
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/csv");
header('Content-Disposition: attachment; filename=' . $filename);

echo $result;