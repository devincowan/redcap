<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
// Include the QR Code class
require_once APP_PATH_LIBRARIES . "phpqrcode/qrlib.php";
// Check value that is expected
if (!isset($_GET['value'])) exit;
// Output QR code image
QRcode::png(urldecode($_GET['value']), false, 'H', 4);
