<?php
/**
 * PLUGIN NAME: Name Of The Plugin
 * DESCRIPTION: A brief description of the Plugin.
 * VERSION: The Plugin's Version Number, e.g.: 1.0
 * AUTHOR: Name Of The Plugin Author
 */

// Disable REDCap's authentication
define("NOAUTH", true);

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// OPTIONAL: Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().


// OPTIONAL: Display the header
$HtmlPage = new HtmlPage();
$HtmlPage->PrintHeaderExt();

// Your HTML page content goes here
?>
<h3 style="color:#800000;">
	Plugin Example Page with Authentication Disabled
</h3>
<p>
	This is an example plugin page that has REDCap's <b>authentication disabled</b>. 
	So no one will be forced to login to this page because it is fully public and available to the web (supposing this
	web server isn't locked down behind a firewall).
</p>
<?php

// OPTIONAL: Display the footer
$HtmlPage->PrintFooterExt();