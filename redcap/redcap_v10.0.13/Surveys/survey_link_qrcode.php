<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
// Include the QR Code class
require_once APP_PATH_LIBRARIES . "phpqrcode/qrlib.php";

// Check hash
if (!isset($_GET['hash'])) exit('0');
$hash = $_GET['hash'];

// Output QR code image
QRcode::png(APP_PATH_SURVEY_FULL . "?s=$hash", false, 'H', 3);
