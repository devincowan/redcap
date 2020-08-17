<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) System::redirectHome();

if(!isset($_SESSION['api_exp_file_path']))
{
	exit;
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . basename($_SESSION['api_exp_file_path']));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($_SESSION['api_exp_file_path']));
readfile($_SESSION['api_exp_file_path']);
exit;
