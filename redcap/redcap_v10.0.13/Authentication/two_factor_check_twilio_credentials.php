<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
// Test the credentials passed via Post
$success = Authentication::testTwilioCrendentialsTwoFactor($_POST['sid'], $_POST['token'], $_POST['phone_number'], true);
// Return 1 on success, else return error message (string)
print ($success === true) ? '1' : $success;