<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Return 1 if user has logged in via Duo already
print ($two_factor_auth_duo_enabled && isset($_SESSION['two_factor_auth'])) ? '1' : '0';