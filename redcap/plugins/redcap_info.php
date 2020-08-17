<?php

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// Only allow super users to view redcap_info()
if (!SUPER_USER) exit("Access denied! Only super users can access this page.");

// Display table of REDCap variables, constants, and settings - similar to phpinfo()
redcap_info();