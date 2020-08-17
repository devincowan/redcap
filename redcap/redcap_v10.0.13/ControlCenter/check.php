<?php


// If downloading zip of non-versioned files, then zip them up for download
if (isset($_GET['download']) && $_GET['download'] == 'nonversioned_files')
{
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	ControlCenter::exportNonVersionedFiles();
	exit;
}

// Begin displaying page
if (isset($_GET['upgradeinstall'])) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Check for any extra whitespace from config files that would mess up lots of things
	$prehtml = ob_get_contents();
	// Header
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("home.css", 'screen,print');
	$objHtmlPage->PrintHeaderExt();
} else {
	// Header
	include 'header.php';
}
if (!SUPER_USER && !System::isCI()) redirect(APP_PATH_WEBROOT);

?>
<script type="text/javascript">
var allow_outbound_http = '<?=$allow_outbound_http?>';
$(function(){
	// Consortium server check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the REDCap Consortium server. "
		+ "You WILL be able to use the \"automatic reporting\" method to report your site stats, as well as use the REDCap Shared Library.";
	var msgError = "<b>FAILED!</b> - Could NOT communicate with the REDCap Consortium server. "
		+ "You will NOT be able to report your institutional REDCap statistics using the \"automatic reporting\" method, but you may try "
		+ "the \"manual reporting\" method instead (see the General Configuration page in the Control Center for this setting).";
	checkExternalService('', 'get', 'server_ping_response_div', msgSuccess, msgError);
	// Twilio service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the Twilio API server (https://api.twilio.com).";
	var msgError = "<b>FAILED!</b> - Could NOT communicate with the Twilio API server (https://api.twilio.com).";
	checkExternalService('https://api.twilio.com', 'post', 'twilio_service_check', msgSuccess, msgError);
	// PROMIS service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the PROMIS API server (<?php print $promis_api_base_url ?>).";
	var msgError = "<b>FAILED!</b> - Could NOT communicate with the PROMIS API server (<?php print $promis_api_base_url ?>).";
	checkExternalService('<?php print $promis_api_base_url ?>', 'get', 'promis_service_check', msgSuccess, msgError);
	// BioPortal service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the BioPortal API server (<?php print $bioportal_api_url ?>).";
	var msgError = "<b>FAILED!</b> - Could NOT communicate with the BioPortal API server (<?php print $bioportal_api_url ?>).";
	checkExternalService('<?php print $bioportal_api_url ?>', 'get', 'bioportal_service_check', msgSuccess, msgError);
	// Bit.ly service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the Bit.ly API server (http://api.bit.ly).";
	var msgError = "<b>FAILED!</b> - Could NOT communicate with the Bit.ly API server (http://api.bit.ly).";
	checkExternalService('http://api.bit.ly', 'get', 'bitly_service_check', msgSuccess, msgError);
	// IS.GD service check
	var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the IS.GD API server (https://is.gd).";
	var msgError = "<b>FAILED!</b> - Could NOT communicate with the IS.GD API server (https://is.gd).";
	checkExternalService('https://is.gd', 'get', 'isgd_service_check', msgSuccess, msgError);
    // REDCAP.LINK service check
    var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the REDCAP.LINK API server (https://redcap.link).";
    var msgError = "<b>FAILED!</b> - Could NOT communicate with the REDCAP.LINK API server (https://redcap.link).";
    checkExternalService('https://redcap.link', 'get', 'redcaplink_service_check', msgSuccess, msgError);
    // Google Captcha service check
    var msgSuccess = "<img src='"+app_path_images+"tick.png'> <b>SUCCESSFUL!</b> - Communicated successfully with the Google reCAPTCHA server (https://www.google.com/recaptcha/api/siteverify).";
    var msgError = "<b>FAILED!</b> - Could NOT communicate with the Google reCAPTCHA server (https://www.google.com/recaptcha/api/siteverify).";
    checkExternalService('https://www.google.com/recaptcha/api/siteverify', 'get', 'googlerecaptcha_service_check', msgSuccess, msgError);
});

function checkExternalService(serviceUrl, method, divId, msgSuccess, msgError) {
	// Ajax request
	var resp = msgError;
	var pass = false;
	if (method != 'post') method = 'get';
	if (allow_outbound_http != '1') {
        $('#'+divId).html(resp);
        configCheckSetBoxColor($('#'+divId).parent(), pass);
	    return;
    }
	var thisAjax = $.post(app_path_webroot+'ControlCenter/check_server_ping.php', { url: serviceUrl, type: method }, function(data) {
		if (data.length > 0) {
			pass = true;
			resp = msgSuccess;
		}
		$('#'+divId).html(resp);
		configCheckSetBoxColor($('#'+divId).parent(), pass);
	});
	// Check after 10s to see if communicated with server, in case it loads slowly. If not after 10s, then assume cannot be done.
	var resptimer = resp;
	var maxAjaxTime = 10; // seconds
	setTimeout(function(){
		if (thisAjax.readyState == 1) {
			thisAjax.abort();
			$('#'+divId).html(resptimer);
			configCheckSetBoxColor($('#'+divId).parent(), false);
		}
	},maxAjaxTime*1000);
}

function configCheckSetBoxColor(ob, pass) {
	ob.removeClass('gray');
	if (pass) {
		ob.addClass('darkgreen').css('color','green');
	} else {
		ob.addClass('red');
	}
}

function whyComponentMissing() {
	var msg = "Because some components are added to REDCap at a specific version and are never modified thereafter, they are not included in every "
			+ "version of REDCap in the upgrade zip file. Since such components are only added once, it does not make sense to include them in every "
			+ "upgrade file, so instead they are thus only included in the version that first utilizes them. This triggers the "
			+ "error you see here, which prompts you to go download them now. This is not ideal, but it is the best approach for now. "
			+ "Sorry for any inconvenience.";
	alert(msg);
}
</script>
<?php

############################################################################################

//PAGE HEADER
print RCView::h4(array('style'=>'margin-top:0;'), '<i class="fas fa-clipboard-check"></i> ' . $lang['control_center_443']);
print  "<p>
			This page will test your current REDCap configuration to determine if any errors exist
			that might prevent it from functioning properly.
		</p>";


## Basic tests
print "<p style='padding-top:10px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>Basic tests</p>";


if (!System::isCI()) {
    $testInitMsg = "<b>TEST 1: Establish basic REDCap file structure</b>
				<br>Search for necessary files and folders that should be located in the main REDCap folder
				(i.e. \"" . dirname(APP_PATH_DOCROOT) . "\").";
    $missing_files = 0;
    if (substr(basename(APP_PATH_DOCROOT), 0, 8) != "redcap_v" && basename(APP_PATH_DOCROOT) != "codebase") {
        exit (RCView::div(array('class' => 'red'), "$testInitMsg<br> &bull; redcap_v?.?.? - <b>MISSING!<p>ERROR! - This file (ControlCenter/check.php) should be located in a folder named with the following format:
	/redcap/redcap_v?.?.?/. Find this folder and place the ControlCenter/check.php file in it, and run this test again.</b>"));
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/temp")) {
        $testInitMsg .= "<br> &bull; temp - <b>MISSING!</b>";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/edocs")) {
        $testInitMsg .= "<br> &bull; edocs - <b>MISSING!</b>";
        $missing_files = 1;
    }
    if (!is_file(dirname(APP_PATH_DOCROOT) . "/database.php")) {
        $testInitMsg .= "<br> &bull; database.php - <b>MISSING!</b>";
        $missing_files = 1;
    }
    if (is_dir(dirname(APP_PATH_DOCROOT) . "/webtools2")) {
        // See if the webdav folder is in correct location
        if (!is_dir(dirname(APP_PATH_DOCROOT) . "/webtools2/webdav")) {
            $testInitMsg .= "<p><b>ERROR! - The sub-folder named \"webdav\" is missing from the \"webtools2\" folder.</b>
		<br>Find this folder and place it in the \"webtools2\" folder. Then run this test again.";
            $missing_files = 1;
        }
        // LDAP folder
        if (!is_file(dirname(APP_PATH_DOCROOT) . "/webtools2/ldap/ldap_config.php")) {
            $testInitMsg .= "<br> &bull; webtools2/ldap/ldap_config.php - <b>MISSING!</b>";
            $missing_files = 1;
        }
        // TinyMCE folder
        if (!is_dir(dirname(APP_PATH_DOCROOT) . "/webtools2/tinymce_" . TINYMCE_VERSION)) {
            $testInitMsg .= "<br> &bull; webtools2/tinymce_" . TINYMCE_VERSION . " - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 4.9.7.
				See the <a href='https://community.projectredcap.org/page/download.html' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
				(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
            $missing_files = 1;
        }

    } else {
        $testInitMsg .= "<br> &bull; webtools2 - <b>MISSING! &nbsp; <font color=#800000>This folder needs to be in the folder named \"" . dirname(APP_PATH_DOCROOT) . "\".</font></b>";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/languages")) {
        $testInitMsg .= "<br> &bull; languages - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 3.2.0.
			See the <a href='https://community.projectredcap.org/page/download.html' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/api")) {
        $testInitMsg .= "<br> &bull; api - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 3.3.0.
			See the <a href='https://community.projectredcap.org/page/download.html' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/api/help")) {
        $testInitMsg .= "<br> &bull; api/help - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 3.3.0.
			See the <a href='https://community.projectredcap.org/page/download.html' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
        $missing_files = 1;
    }
    if (!is_dir(dirname(APP_PATH_DOCROOT) . "/surveys")) {
        $testInitMsg .= "<br> &bull; surveys - <b>MISSING!</b> - Must be obtained from install/upgrade zip file from version 4.0.0.
			See the <a href='https://community.projectredcap.org/page/download.html' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
			(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)";
        $missing_files = 1;
    }

    if ($missing_files == 1) {
        exit(RCView::div(array('class' => 'red'), "$testInitMsg<br><br><b><font color=red>ERROR!</font> - One or more of the files/folders listed above could not be found
			in the folder named \"" . dirname(APP_PATH_DOCROOT) . "\". Please locate those files/folders in the Install/Upgrade zip file
			that you downloaded from the REDCap Community website, then add them to the correct location on your server and run this test again."));
    } else {
        $testInitMsg .= "<br><br><img src='" . APP_PATH_IMAGES . "tick.png'> <b>SUCCESSFUL!</b> - All necessary files and folders were found.";
        print RCView::div(array('class' => 'darkgreen', 'style' => 'color:green;'), $testInitMsg);
    }
}




$testMsg3 = "<b>TEST 2: Connect to the table named \"redcap_config\"</b><br><br>";
$QQuery = db_query("SHOW TABLES FROM `$db` LIKE 'redcap_config'");
if (db_num_rows($QQuery) == 1) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), "$testMsg3 <img src='".APP_PATH_IMAGES."tick.png'> <b>SUCCESSFUL!</b> - The table \"redcap_config\" in the MySQL database named <b>".$db."</b>
			was accessed successfully.");
} else {
	exit (RCView::div(array('class'=>'red'), "$testMsg3<b>ERROR! - The database table named \"redcap_config\" could NOT be accessed.</b>
	<br>This error may have resulted if there was an error during the install/upgrade process. Please make sure that the
	\"redcap_config\" table is located in the MySQL project <b>".$db."</b>.
	If it is not, you will need to re-install/re-upgrade REDCap, and then run this test again."));
}



## Check if REDCap database structure is correct
$testMsg = "<b>TEST 3: Check REDCap database table structure</b><br><br>";
$tableCheck = new SQLTableCheck();
// Use the SQL from install.sql compared with current table structure to create SQL to fix the tables
$sql_fixes = $tableCheck->build_table_fixes();
if ($sql_fixes != '') {
	// NORMAL INSTALL: TABLES ARE MISSING OR PIECES OF TABLES ARE MISSING
	// If we are able to auto-fix this, then provide button to do so
	$autoFixDbTablesBtn = "";
	if (Upgrade::hasDbStructurePrivileges()) {
		$autoFixDbTablesBtn = RCView::div(array('style'=>'margin:15px 0 3px;'),
								RCView::button(array('class'=>'btn btn-danger btn-sm', 'style'=>'margin-right:5px;', 'onclick'=>'autoFixTables();'),
									$lang['control_center_4680']
								) .
								$lang['control_center_4681']
							) .
							RCView::div(array('style'=>'margin:10px 0 6px;font-weight:bold;'),
								"&ndash; " . $lang['global_46'] . " &ndash;"
							);
	}
	// If there are fixes to be made, then display text box with SQL fixes
	print 	RCView::div(array('class'=>'red', 'style'=>'margin-bottom:15px;'),
				RCView::img(array('src'=>'exclamation.png')) .
				"$testMsg<b>{$lang['control_center_4431']}</b><br>
				{$lang['control_center_4682']} $autoFixDbTablesBtn {$lang['control_center_4683']}" .
				RCView::div(array('id'=>'sql_fix_div', 'style'=>'margin:10px 0 3px;'),
					RCView::textarea(array('class'=>'x-form-field notesbox', 'style'=>'height:60px;font-size:11px;width:97%;height:100px;', 'readonly'=>'readonly', 'onclick'=>'this.select();'),
						"-- SQL TO REPAIR REDCAP TABLES\nUSE `$db`;\nSET SESSION SQL_SAFE_UPDATES = 0;\nSET FOREIGN_KEY_CHECKS = 0;\n$sql_fixes\nSET FOREIGN_KEY_CHECKS = 1;"
					)
				)
			);
} else {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), "$testMsg <img src='".APP_PATH_IMAGES."tick.png'>
			<b>SUCCESSFUL!</b> - Your REDCap database structure is correct!");
}



## Check if cURL is installed
$testMsg = "<b>TEST 4: Check if PHP cURL extension is installed</b><br><br>";
// cURL is installed
if (function_exists('curl_init'))
{
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testMsg." <img src='".APP_PATH_IMAGES."tick.png'> <b>SUCCESSFUL!</b> - The cURL extension is installed.<br>");
}
// cURL not installed
else
{
	?>
    <div class="red">
		<?php echo $testMsg ?>
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b>Your web server does NOT have the PHP library cURL installed.</b> cURL is required to utilize many major features in REDCap.
		To add cURL to REDCap, you will need to download cURL/libcurl, and then install and configure it with PHP on your web server. You will find
		<a href='http://us.php.net/manual/en/book.curl.php' target='_blank' style='text-decoration:underline;'>instructions for cURL/libcurl installation here</a>.
	</div>
	<?php
}



## Check if can communicate with REDCap Consortium server (for reporting stats)
$testMsg = "<b>TEST 5: Checking communication with REDCap Consortium server</b> (".CONSORTIUM_WEBSITE.")<br>
			(used to report weekly site stats and connect to Shared Library)<br><br>";
// Send request to consortium server using cURL via an ajax request (in case it loads slowly)
?>
<div class="gray">
	<?php echo $testMsg ?>
	<div id="server_ping_response_div">
		<img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif">
		<b>Communicating with server... please wait</b>
	</div>
</div>
<?php




## Check if REDCap Cron Job is running
$testMsg = "<b>TEST 6: Check if REDCap Cron Job is running</b><br><br>";
if (Cron::checkIfCronsActive()) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'), $testMsg." <img src='".APP_PATH_IMAGES."tick.png'> <b>SUCCESSFUL!</b> - REDCap Cron Job is running properly.<br>");
} else {
	print RCView::div(array('class'=>'red'),
		$testMsg .
		RCView::img(array('src'=>'exclamation.png')) .
		RCView::b($lang['control_center_288']) . RCView::br() . $lang['control_center_289'] . RCView::br() . RCView::br() .
		RCView::a(array('href'=>'javascript:;','style'=>'','onclick'=>"window.location.href=app_path_webroot+'ControlCenter/cron_jobs.php';"), $lang['control_center_290'])
	);
}




/**
 * SECONDARY TESTS
 */
print "<p style='padding-top:15px;color:#800000;font-weight:bold;font-family:verdana;font-size:13px;'>Secondary tests</p>";


// Check for SSL
if (SSL || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>Using SSL</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>NOT using SSL
			- CRITICAL:</b> It is HIGHLY recommended that you use SSL (i.e. https) on your web server when hosting REDCap. Otherwise,
			data security could be compromised. If your server does not already have an SSL certificate, you will need to obtain one.</div>";
}

// Get the minimum required PHP version that is supported by REDCap
if (version_compare(PHP_VERSION, System::getMinPhpVersion(), '<')) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>NOT using PHP ".System::getMinPhpVersion()." or higher
			- CRITICAL:</b> It is required that you upgrade your web server to a more recent supported version of PHP 
			(you are currently running PHP ".PHP_VERSION.").</div>";
} else {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>Using PHP ".System::getMinPhpVersion()." or higher</b></div>";
}

// Check for MySQL 5 or higher (or MariaDB 10 or higher)
$q = db_query("select version()");
$mysql_version = db_result($q, 0);
list ($mysql_version_main, $nothing) = explode(".", $mysql_version, 2);
if ($mysql_version_main >= 10) {
	// MariaDB 10+
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>Using MariaDB 10 or higher</b></div>";
} elseif ($mysql_version_main >= 5) {
	// MySQL 5+
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>Using MySQL 5 or higher</b></div>";
} else {
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> <b>NOT using MySQL 5
			- RECOMMENDED:</b> It is recommended that you upgrade your database server to MySQL 5 (you are currently running MySQL $mysql_version).
			Some functionality within REDCap may not be functional on MySQL versions prior to MySQL 5.</div>";
}


## Check for DOM Document class
if (!class_exists('DOMDocument')) {
	print RCView::div(array('class'=>'red'),
			RCView::img(array('src'=>'exclamation.png')) .
			"<b>DOM extension in PHP is not installed
			- RECOMMENDED:</b> It is recommended that you <a target='_blank' href='http://php.net/manual/en/book.dom.php'>install the DOM extension</a> in PHP
			on your web server.	Some important features in REDCap will not be available without it installed."
		);
}


## Check for XMLReader class
if (!class_exists('XMLReader')) {
	print RCView::div(array('class'=>'red'),
			RCView::img(array('src'=>'exclamation.png')) .
			"<b>XMLReader extension in PHP is not installed
			- RECOMMENDED:</b> It is recommended that you <a target='_blank' href='http://php.net/manual/en/book.xmlreader.php'>install the XMLReader extension</a> in PHP
			on your web server.	Some important features in REDCap will not be available without it installed."
		);
}


## Check for GD library (version 2 and up)
if (gd2_enabled()) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'),
			"<img src='".APP_PATH_IMAGES."tick.png'> <b>GD library (version 2 or higher) is installed</b>"
		);
} else {
	print RCView::div(array('class'=>'yellow'),
			RCView::img(array('src'=>'exclamation_orange.png')) .
			"<b>GD Library (version 2 or higher) is not installed
			- RECOMMENDED:</b> It is recommended that you <a target='_blank' href='http://php.net/manual/en/image.installation.php'>install the GD2 Library</a> in PHP
			on your web server.	Some features in REDCap will not be available without GD2 installed, such as the ability for users to generate QR codes
			for survey links."
		);
}

## Check if Fileinfo extension is installed
// finfo is installed
if (function_exists('finfo_open')) {
	print RCView::div(array('class'=>'darkgreen','style'=>'color:green;'),
			"<img src='".APP_PATH_IMAGES."tick.png'> <b>PHP Fileinfo extension is installed</b>"
		);
}
// cURL not installed
else
{
	?>
	<div class="yellow">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation_orange.png">
		<b>Your web server does NOT have the PHP Fileinfo extension installed.</b>
		Fileinfo extension for PHP is NOT necessary to run REDCap normally, but it is highly
		recommended. Fileinfo is used for some optional functionality in REDCap, such as for file import operations when
		importing whole REDCap projects as XML files (i.e., CDISC ODM file imports).
		It is recommended that you download the Fileinfo extension, and then install and configure it with PHP on your web server. You will find
		<a href='http://php.net/manual/en/book.fileinfo.php' target='_blank' style='text-decoration:underline;'>instructions for Fileinfo installation here</a>.
	</div>
	<?php
}

/**
 * CHECK IF USING SSL WHEN THE REDCAP BASE URL DOES NOT BEGIN WITH "HTTPS"
 */
if (substr($redcap_base_url, 0, 5) == "http:") {
	?>
	<div id="ssl_base_url_check" class="red" style="display:none;padding-bottom:15px;">
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<b><?php echo $lang['control_center_4436'] ?></b><br>
		<?php echo $lang['control_center_4437'] ?>
	</div>
	<script type="text/javascript">
	if (window.location.protocol == "https:") {
		document.getElementById('ssl_base_url_check').style.display = 'block';
	}
	</script>
	<?php
}


// Check if mcrypt PHP extension is loaded
if (!function_exists('openssl_encrypt')) {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>OpenSSL extension not installed
			- RECOMMENDED:</b> It is recommended that you install the 
			<a target='_blank' href='http://php.net/manual/en/book.openssl.php'>PHP OpenSSL extension</a>
			on your web server since certain application functions depend upon it. 
			After installing the extension, reboot your web server, and then reload this page.</div>";
}

// ZIP export support for downloading uploaded files in ZIP (Check for PHP 5.2.0+ and ZipArchive)
if (!Files::hasZipArchive()) {
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> <b>ZipArchive is not installed
			- RECOMMENDED:</b> It is recommended that you install the
			<a target='_blank' href='http://php.net/manual/en/book.zip.php'>PHP Zip extension</a>.
			Some features in REDCap will not be available without this extension installed, such as the feature in the
			Data Export Tool that allows users to download a ZIP file of all their uploaded files for records in a project.</div>";
}

// Must have PHP extension "mbstring" installed in order to render UTF-8 characters properly
if (!function_exists('mb_convert_encoding'))
{
	print  "<div class='red'>
				<img src='".APP_PATH_IMAGES."exclamation.png'>
				<b>PHP extension \"mbstring\" not installed - CRITICAL:</b>
				This extension is required because it is used by many different processes and features in REDCap.
				To enable it, you must install the
				<a href='http://php.net/manual/en/mbstring.setup.php' target='_blank'>PHP extension \"mbstring\"</a>
				on your web server. Once installed, reboot your web server.
			</div>";
} else {
	// Check if emails can be sent via SMTP (this check requires MBSTRING be enabled)
	$test_email_address = 'redcapemailtest@gmail.com';
	$emailContents = "This email was sent when the user <b>".USERID."</b> opened the Configuration Check page for <b>".APP_PATH_WEBROOT_FULL."</b>
					(REDCap version $redcap_version).";
	$email = new Message();
	$email->setTo($test_email_address);
	$email->setFrom($test_email_address);
	$email->setFromName("REDCap Email Test");
	$email->setSubject('REDCap Configuration Check: '.APP_PATH_WEBROOT_FULL);
	$email->setBody($emailContents,true);
	if ($email->send()) {
		print  "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'>
				<b style='color:green;'>REDCap is able to send emails</b></div>";
	} else {
		print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>REDCap is not able to send emails
				- CRITICAL:</b>
				It appears that your SMTP configuration (email-sending functionality) is either not set up or not configured correctly on the web server.
				It is HIGHLY recommended that you configure your email/SMTP server correctly in your web server's PHP.INI configuration file
				or else emails will not be able to be sent out from REDCap. REDCap requires email-sending capabilities for many
				vital application functions. For more details on configuring email-sending capabilities on your web server, visit
				<a href='http://php.net/manual/en/mail.configuration.php' target='_blank'>PHP's mail configuration page.</a></div>";
	}
}

// Check if any whitespace has been output to the buffer unne
if ($prehtml !== false && strlen($prehtml) > 0) {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>Error inside a REDCap configuration file:</b>
			It appears that in one or more of REDCap's configuration files (e.g., database.php, webtools2/ldap/ldap_config.php,
			webtools2/webdav/webdav_connection.php) there is some extra \"whitespace\", such as trailing spaces or empty lines that
			occur either before the opening PHP \"&lt;?php\" tag or after the closing PHP \"?&gt;\" tag.
			Please make sure all preceding spaces, trailing spaces, and empty lines are removed from before or after the PHP tags in those files.
			Certain things in REDCap will not work correctly until this is fixed.</div>";
}


// Check if InnoDB engine is enabled in MySQL
if (!$tableCheck->innodb_enabled()) {
	print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>InnoDB engine is NOT enabled in MySQL
			- CRITICAL:</b>
			It appears that your MySQL database server does not have the InnoDB table engine enabled, which is required for REDCap
			to run properly. To enable it, open your my.cnf (or my.ini) configuration file for MySQL
			and remove all instances of \"--innodb=OFF\" and \"--skip-innodb\". Then restart MySQL, and then reload this page.
			If that does not work, then see the official MySQL documentation for how to enable InnoDB for your specific version of MySQL.</div>";
}


// Check max_input_vars
$max_input_vars = ini_get('max_input_vars');
$max_input_vars_min = 10000;
if (is_numeric($max_input_vars) && $max_input_vars < $max_input_vars_min)
{
	// Give recommendation to increase max_input_vars
	print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
				<b>'max_input_vars' could be larger - RECOMMENDED:</b>
				It is highly recommended that you change your value for 'max_input_vars' in your PHP.INI configuration file to
				a value of $max_input_vars_min or higher. If not increased, then REDCap might not be able to successfully save data when entered on a very long survey
				or data entry form.	You can modify this setting in your server's PHP.INI configuration file.
				If 'max_input_vars' is not found in your PHP.INI file, you should add it as <i style='color:#800000;'>max_input_vars = $max_input_vars_min</i>.
				Once done, restart your web server for the changes to take effect.
			</div>";
}

// Make sure 'upload_max_filesize' and 'post_max_size' are large enough in PHP so files upload properly
$maxUploadSize = maxUploadSize();
if ($maxUploadSize <= 2) { // <=2MB
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'>
			<b>'upload_max_filesize' and 'post_max_size' are too small:</b>
			It is HIGHLY recommended that you change your value for both 'upload_max_filesize' and 'post_max_size' in PHP to a higher value, preferably
			greater than 10MB (e.g., 32M). You can modify this in your server's PHP.INI configuration file, then restart your web server.
			At such small values, your users will likely have issues uploading files if you do not increase these.</div>";
} elseif ($maxUploadSize <= 10) { // <=10MB
	print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'>
			<b>'upload_max_filesize' and 'post_max_size' could be larger
			- RECOMMENDED:</b> It is recommended that you change your value for both 'upload_max_filesize' and 'post_max_size' in PHP to a higher value, preferably
			greater than 10MB (e.g., 32M). You can modify this in your server's PHP.INI configuration file, then restart your web server.
			At such small values, your users could potentially have issues uploading files if you do not increase these.</div>";
}

// Check if the PDF UTF-8 fonts are installed (otherwise cannot render special characters in PDFs)
$pathToPdfUtf8Fonts = APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS . "unifont" . DS;
if (!is_dir($pathToPdfUtf8Fonts))
{
	print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
				<b>Missing UTF-8 fonts for PDF export - RECOMMENDED:</b>
				In REDCap version 4.5.0, the capability was added for rendering special UTF-8 characters in PDF files
				exported from REDCap. This feature is not necessary but is good to have, especially for any international projects
				that might want to enter data or create field labels using special non-English characters.
				Without this feature installed, some special characters might appear jumbled and unreadable in a PDF export.
				In order to utilize this capability, the UTF-8 fonts must be installed in REDCap. To do this, simply
				download the install zip file of the latest REDCap version, 
				and then extract the contents from the /webtools2/pdf/ folder in the zip file into the /webtools2/pdf subfolder 
				in the main REDCap folder on your web server.
				The file structure should then be /webtools2/pdf/fonts/unifont. Overwrite any existing files or folders there.
				In addition, to utilize this feature, you must also have the
				<a href='http://php.net/manual/en/mbstring.setup.php' target='_blank'>PHP extension \"mbstring\"</a>
				installed on your web server. If not installed, install it, then reboot your web server.
			</div>";
}

// Check if we're missing any hook functions
$hook_functions_file = trim($hook_functions_file);
if (isset($hook_functions_file) && $hook_functions_file != '')
{
    require_once APP_PATH_CLASSES."Hooks.php";
	// Get list of all methods available in Hooks class
	$hookMethods = get_class_methods("Hooks");
	$hookMethodsMissing = array();
	foreach ($hookMethods as $thisMethod) {
	    if ($thisMethod == 'call') continue; // Ignore this one
        if (!function_exists($thisMethod)) {
			$hookMethodsMissing[] = $thisMethod;
        }
    }
	if (!empty($hookMethodsMissing))
	{
	    // Hook functions are missing
		print  "<div class='yellow'>
				<img src='".APP_PATH_IMAGES."exclamation_orange.png'>
				<b>SUGGESTION: Missing some hook functions in your Hook Functions file</b><br>
				It appears that your hook functions file \"<b>$hook_functions_file</b>\" does not contain one or more
				REDCap hook functions that can be utilized in your current version of REDCap. 
				<b>NOTE:</b> These hook functions are only necessary if you are using custom REDCap Hooks on this REDCap installation (this does NOT 
				include hooks used in External Modules, which are not affected by this). It is recommended that these hook functions be added to that file on your web server.
				The following hooks are missing from the hook functions file:
				<b><ul class='my-2'><li>".implode("</li><li>", $hookMethodsMissing)."</li></ul></b>
				To obtain the default code for these hook functions, it is recommended that you download the latest Install Zip file of REDCap (Standard Release) from
				the REDCap Community website and open the file in the zip file named \"redcap/hook_functions.php\" in a text editor. Find the functions in that PHP file,
                and copy and paste the entire function(s) into your Hook Functions file (located at $hook_functions_file on your web server). Then save the file.
                Once they have been added, this warning message should no longer display.
			</div>";
    }
}

// Check if all non-versioned files are up-to-date
ControlCenter::checkNonVersionedFiles();

// Make sure 'innodb_buffer_pool_size' is large enough in MySQL
$q = db_query("SHOW VARIABLES like 'innodb_buffer_pool_size'");
if ($q && db_num_rows($q) > 0)
{
	while ($row = db_fetch_assoc($q)) {
		$innodb_buffer_pool_size = $row['Value'];
	}
	$total_mysql_space = 0;
	$q = db_query("SHOW TABLE STATUS from `$db` like 'redcap_%'");
	while ($row = db_fetch_assoc($q)) {
		if (strpos($row['Name'], "_20") === false) { // Ignore timestamped archive tables
			$total_mysql_space += $row['Data_length'] + $row['Index_length'];
		}
	}
	// Set max buffer pool size that anyone would probably need
	$innodb_buffer_pool_size_max_neccessary = 1*1024*1024*1024; // 1 GB
	// Compare
	if ($innodb_buffer_pool_size <= ($innodb_buffer_pool_size_max_neccessary*0.95) && $innodb_buffer_pool_size < ($total_mysql_space*1.1))
	{
		// Determine severity (red/severe is < 20% of total MySQL space)
		$class = ($innodb_buffer_pool_size < ($total_mysql_space*.2)) ? "red" : "yellow";
		$img   = ($class == "red") ? "exclamation.png" : "exclamation_orange.png";
		// Set recommend pool size
		$recommended_pool_size = ($total_mysql_space*1.1 < $innodb_buffer_pool_size_max_neccessary) ? $total_mysql_space*1.1 : $innodb_buffer_pool_size_max_neccessary;
		// Give recommendation
		print "<div class='$class'><img src='".APP_PATH_IMAGES."$img'> <b>'innodb_buffer_pool_size' could be larger
				- RECOMMENDED:</b> It is recommended that you change your value for 'innodb_buffer_pool_size' in MySQL to a higher value.
				It is generally recommended that it be set to 10% larger than the size of your database, which is currently
				".round($total_mysql_space/1024/1024)."MB in size. So ideally <b>'innodb_buffer_pool_size'
				should be set to at least ".round($recommended_pool_size/1024/1024)."MB</b> if possible
				(it is currently ".round($innodb_buffer_pool_size/1024/1024)."MB).
				Also, it is recommended that the size of 'innodb_buffer_pool_size' <b>not exceed 80% of your total RAM (memory)
				that is allocated to MySQL</b> on your database server.
				You can modify this in your MY.CNF configuration file (or MY.INI for Windows), then restart MySQL.
				If you do not increase this value, you may begin to see performance issues in MySQL.</div>";
	}
}


// Check if web server's tmp directory is writable
$temp_dir = sys_get_temp_dir();
if (isDirWritable($temp_dir)) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>The REDCap web server's temp directory is writable<br>Location: $temp_dir</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>The REDCap web server's temp directory is NOT writable
			- CRITICAL:</b> It is HIGHLY recommended that you modify your web server's temp directory (located at $temp_dir) so that it is
			writable for all server users. Some functionality within REDCap will not be functional until this directory is writable.</div>";
}

// Check if /redcap/temp is writable
$temp_dir = APP_PATH_TEMP;
if (isDirWritable($temp_dir)) {
	print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>\"temp\" directory is writable<br>Location: $temp_dir</b></div>";
} else {
	print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>\"temp\" directory is NOT writable
			- CRITICAL:</b> It is HIGHLY recommended that you modify the REDCap \"temp\" folder (located at $temp_dir) so that it is
			writable for all server users. Some functionality within REDCap will not be functional until this folder is writable.</div>";
}

// Check if /edocs is writable
if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
	// LOCAL STORAGE
	$edocs_dir = EDOC_PATH;
	if (isDirWritable($edocs_dir)) {
		print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>File upload directory is writable<br>Location: ".EDOC_PATH."</b></div>";
	} else {
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>File upload directory is NOT writable
				- CRITICAL:</b> It is HIGHLY recommended that you modify the REDCap \"edocs\" folder (located at $edocs_dir) so that it is
				writable for all server users. Some functionality within REDCap will not be functional until this folder is writable.</div>";
	}
	// Check if using default .../redcap/edocs/ folder for file uploads (not recommended)
	if ($edoc_storage_option == '0' && trim($edoc_path) == "")
	{
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'>
				<b>Directory that stores user-uploaded documents is exposed to the web:</b><br>
				It is HIGHLY recommended that you change your location where user-uploaded files are stored.
				Currently, they are being stored in REDCap's \"edocs\" directory, which is the default location and is completely accessible to the web.
				Although it is extremely unlikely that anyone could successfully retrieve a file from that location on the server via the web,
				it is still a potential security risk, especially if the documents contain sensitive information.
				<br><br>
				It is recommend that you go to the File Upload Settings page in the Control Center and set a new path for your user-uploaded documents
				(i.e. \"Enable alternate internal storage of uploaded files rather than default 'edocs' folder\"), and set it to
				a path on your web server that is NOT accessible from the web. Once you have
				changed that value, go to the 'edocs' directory and copy all existing files in that folder to the new location you just set.
				</div>";
	}
} elseif ($edoc_storage_option == '2') {
	// AMAZON S3 STORAGE
	if (version_compare(PHP_VERSION, '5.5.0', '<')) {
		// AWS S3 SDK requires PHP 5.5
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>NOT using PHP 5.5.0 or higher (required for AWS S3 storage)
				- CRITICAL:</b> You are using AWS S3 for file storage. However, the AWS S3 library used in REDCap requires
				PHP 5.5.0 or higher (you are currently running PHP ".PHP_VERSION."). So in order for file uploads and file storage to
				keep working in REDCap, you must upgrade PHP to version 5.5.0 or higher.</div>";
	} else {
		// Try to write a file to that directory and then delete
		$test_file_name = date('YmdHis') . '_test.txt';
		$test_file_content = "test";
		try {
			$s3 = Files::s3client();
			$result = $s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$test_file_name, 'Body'=>$test_file_content, 'ACL'=>'private'));
			// Success
			print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'>
					<b style='color:green;'>File upload directory is writable on Amazon S3 server in bucket \"$amazon_s3_bucket\"</b></div>";
			// Now delete the file we just created
			$s3->deleteObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$test_file_name));
		} catch (Aws\S3\Exception\S3Exception $e) {
			// Failed
			print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>File upload directory is NOT writable on Amazon S3 server
					- CRITICAL:</b> It is HIGHLY recommended that you investigate your Amazon S3 connection info on the File Upload Settings page
					in the Control Center. REDCap is not able to successfully store files in the Amazon S3 bucket named \"$amazon_s3_bucket\".
					Please make sure all the connection values are correct and also that the directory is writable.
					Some functionality within REDCap will not be functional until files can be written to that bucket.</div>";
		}
	}
} elseif ($edoc_storage_option == '4') {
	// AZURE STORAGE
	if (version_compare(PHP_VERSION, '5.6.0', '<')) {
		// AZURE SDK requires PHP 5.6
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>NOT using PHP 5.6.0 or higher (required for Microsoft Azure Blob Storage)
				- CRITICAL:</b> You are using Microsoft Azure Blob Storage for file storage. However, the Azure Blob library used in REDCap requires
				PHP 5.6.0 or higher (you are currently running PHP ".PHP_VERSION."). So in order for file uploads and file storage to
				keep working in REDCap, you must upgrade PHP to version 5.6.0 or higher.</div>";
	} else {
		// Try to write a file to that directory and then delete
		$test_file_name = date('YmdHis') . '_test.txt';
		$test_file_content = "test";
		try {
			$blobClient = Files::azureBlobClient();
			$blobClient->createBlockBlob($GLOBALS['azure_container'], $test_file_name, $test_file_content);
			$blobClient->deleteBlob($GLOBALS['azure_container'], $test_file_name);
		} catch (Exception $e) {
			// Failed
			print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>File upload directory is NOT writable on Microsoft Azure Blob Storage server
					- CRITICAL:</b> It is HIGHLY recommended that you investigate your Microsoft Azure Blob Storage connection info on the File Upload Settings page
					in the Control Center. REDCap is not able to successfully store files in the Blob Container named \"{$GLOBALS['azure_container']}\".
					Please make sure all the connection values are correct and also that the directory is writable.
					Some functionality within REDCap will not be functional until files can be written to that bucket.</div>";
		}
	}
} else {
	// WEBDAV STORAGE
	// Try to write a file to that directory and then delete
	$test_file_name = date('YmdHis') . '_test.txt';
	$test_file_content = "test";
	// Store using WebDAV
	if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); // use HTTP/1.1
	$wdc->set_debug(false); // enable debugging?
	if (!$wdc->open()) {
		// Send error response
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>Cannot connect to the WebDAV file server
				- CRITICAL:</b> It is HIGHLY recommended that you modify your WebDAV server configuration or your WebDAV connection info in
				<b>".dirname(APP_PATH_DOCROOT).DS."webtools2".DS."webdav".DS."webdav_connection.php</b>. The current connection info is attempting to communicate with
				WebDAV server \"$webdav_hostname\" at path \"$webdav_path\", and it is failing. Please make sure all the connection
				values are correct and also that the directory is writable. Some functionality within REDCap will not be functional until this is fixed.</div>";
	}
	if (substr($webdav_path,-1) != '/') {
		$webdav_path .= '/';
	}
	$http_status = $wdc->put($webdav_path . $test_file_name, $test_file_content);
	if ($http_status == '201') {
		// Success
		print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'>
				<b style='color:green;'>File upload directory is writable on WebDAV server \"$webdav_hostname\" at path \"$webdav_path\"</b></div>";
		// Now delete the file we just created
		$http_status = $wdc->delete($webdav_path . $test_file_name);
	} else {
		// Failed
		print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>File upload directory is NOT writable on WebDAV server
				- CRITICAL:</b> It is HIGHLY recommended that you modify your WebDAV server configuration or your WebDAV connection info in
				<b>".dirname(APP_PATH_DOCROOT).DS."webtools2".DS."webdav".DS."webdav_connection.php</b>. The current connection info is attempting to communicate with
				WebDAV server \"$webdav_hostname\" at path \"$webdav_path\", and it is failing. Please make sure all the connection
				values are correct and also that the directory is writable. Some functionality within REDCap will not be functional until that WebDAV directory is writable.</div>";
	}
	$wdc->close();
}

// Check if /redcap/modules exists and is writable for External Modules
if (defined("APP_PATH_EXTMOD")) {
	$dir = dirname(APP_PATH_DOCROOT) . DS . "modules" . DS;
	if (!is_dir($dir)) {
		print "<div class='red'><b>\"modules\" directory - MISSING!</b> - The REDCap \"modules\" folder (located at $dir) is missing, and must be obtained from install zip file from the latest version
				(or someone can just create an empty directory named \"modules\" at the location $dir).
				See the <a href='https://community.projectredcap.org/page/download.html' style='text-decoration:underline;' target='_blank'>REDCap download page</a>.
				(<a href='javascript:;' onclick='whyComponentMissing()' style='color:#800000'>Why is this missing?</a>)</div>";
	} else {
		if (isDirWritable($dir)) {
			print "<div class='darkgreen'><img src='".APP_PATH_IMAGES."tick.png'> <b style='color:green;'>\"modules\" directory is writable<br>Location: $dir</b></div>";
		} else {
			print "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>\"modules\" directory is NOT writable
					- CRITICAL:</b> It is HIGHLY recommended that you modify the REDCap \"modules\" folder (located at $dir) so that it is
					writable for all server users. The External Modules functionality in REDCap will not be functional until this folder is writable.</div>";
		}
	}
}

// Make sure we have correct db privileges for Easy Upgrade (if applicable)
$dbPrivs = Upgrade::hasDbStructurePrivileges(true);
if (is_array($dbPrivs)) {
	if ($dbPrivs['create'] === true && ($dbPrivs['alter'] !== true || $dbPrivs['drop'] !== true)) {
		if ($dbPrivs['alter'] !== true && $dbPrivs['drop'] !== true) {
			$dbPrivsText = "This user has CREATE table privileges, but does NOT have ALTER or DROP table privileges.";
		} elseif ($dbPrivs['alter'] === true) {
			$dbPrivsText = "This user has CREATE and ALTER table privileges, but does NOT have DROP table privileges.";
		} else {
			$dbPrivsText = "This user has CREATE and DROP table privileges, but does NOT have ALTER table privileges.";
		}
		print "<div class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> <b>MySQL database user privileges should be modified
				- RECOMMENDED:</b> It is recommended that you modify the database privileges of your MySQL user \"<b>{$dbPrivs['username']}</b>\".
				<u>$dbPrivsText</u> This user must have all three privileges (CREATE, ALTER, DROP) or else none of those three.
				If this is not fixed, REDCap may mistakenly create many database tables with names beginning with \"redcap_ztemp_\", which are created
				as temporary tables when testing your MySQL user's privileges. Also, if you wish to use the Easy Upgrade module, the MySQL user must have
				all three privileges (CREATE, ALTER, DROP).
				</div>";
	}
}

// Check external services
if ($twilio_enabled_global) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b("External Service Check: Checking communication with Twilio telephony API services") . RCView::br() .
				RCView::div(array('id'=>'twilio_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
				)
			);
}
if ($promis_enabled) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b("External Service Check: Checking communication with PROMIS assessment API services") . RCView::br() .
				RCView::div(array('id'=>'promis_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
				)
			);
}
if ($enable_ontology_auto_suggest && $bioportal_api_token != '') {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b("External Service Check: Checking communication with BioPortal biomedical ontology API services") . RCView::br() .
				RCView::div(array('id'=>'bioportal_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
				)
			);
}
if ($enable_url_shortener_redcap) {
	print 	RCView::div(array('class'=>'gray'),
                RCView::b("External Service Check: Checking communication with REDCAP.LINK URL shortening API services") . RCView::br() .
                RCView::div(array('id'=>'redcaplink_service_check'),
                    RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
                )
            );
} elseif ($enable_url_shortener) {
	print 	RCView::div(array('class'=>'gray'),
				RCView::b("External Service Check: Checking communication with Bit.ly URL shortening API services") . RCView::br() .
				RCView::div(array('id'=>'bitly_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
				)
			);
	print 	RCView::div(array('class'=>'gray'),
				RCView::b("External Service Check: Checking communication with IS.GD URL shortening API services") . RCView::br() .
				RCView::div(array('id'=>'isgd_service_check'),
					RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
				)
			);
}
if ($google_recaptcha_site_key != '' && $google_recaptcha_secret_key != '') {
    print 	RCView::div(array('class'=>'gray'),
        RCView::b("External Service Check: Checking communication with Google reCAPTCHA API services") . RCView::br() .
        RCView::div(array('id'=>'googlerecaptcha_service_check'),
            RCView::img(array('src'=>'progress_circle.gif')) . "Communicating with server... please wait"
        )
    );
}

// If using SSL, then suggest that the cookie "secure" attribute be enabled in PHP.INI
if (SSL) {
	$cookie_params = session_get_cookie_params();
	if ($cookie_params['secure'] !== true) {
		print "<div class='gray'><span style='color:#A00000;'><i class=\"fas fa-lightbulb\"></i> <b>Security improvement - SUGGESTION:</b></span>
                It appears that you are running REDCap over SSL/HTTPS (which you *should* if this is a production server). 
                For better security, it is recommended that you enable the <i>session.cookie_secure</i> option in your web server's PHP.INI file.                
                To enable \"session.cookie_secure option\", simply open your web server's PHP.INI file for editing and change the value of \"session.cookie_secure\" option to \"On\",
                or if it does not exist yet, add the following line in the <code>[Session]</code> section of PHP.INI:<br>
                <code>session.cookie_secure = On</code><br>Then reboot your web server.
                Doing this is not required, but it is recommended since it improves the overall security of the REDCap system.</div>";
	}
}

/**
 * CONGRATULATIONS!
 */
if (isset($_GET['upgradeinstall']))
{
	loadJS('ControlCenter.js');
	print  "<p><br><hr><p><h4 style='font-size:20px;color:#800000;'>
			<img src='".APP_PATH_IMAGES."star.png'> CONGRATULATIONS! <img src='".APP_PATH_IMAGES."star.png'></h4>
			<p><b>It appears that the REDCap software has been correctly installed/upgraded and configured on your system. ";

	print  "It is ready for use.</b>
			You may begin using REDCap by first visiting the REDCap home page at the link below.
			(It may be helpful to bookmark this link.)";

	print  "<div class='blue' style='padding:10px;'>
			<b>REDCap home page:</b>&nbsp;
			<a style='text-decoration:underline;'  href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>
			</div>";

	// Check global auth_meth value
	if ($auth_meth_global == "none")
	{
		print "<p><b>Currently, REDCap is using the authentication method \"None (Public)\"</b>,
		which is utilized solely by a generic user named \"<b>site_admin</b>\". This authentication method is best to use if you are using a
		development server or if you have not yet worked out all issues with user authentication on your system. Once you have your site's authentication working
		properly, you may go into the Control Center to the Security & Authentication page to change the
		authentication method to the one you will be implementing on
		your system (i.e. LDAP, Table-based, RSA SecurID, LDAP/Table combination, Shibboleth).
		<b>If you decide to switch from \"None\" authentication to \"Table-based\"</b>,
		please be sure to add yourself as a new Table-based user (on the User Control tab in the Control Center)
		before you switch over the authentication method, otherwise you won't be able to log in.";
	}
}

print "<div style='margin-bottom:100px;'> </div>";

// Create a DKIM private key and store in redcap_config if we don't already have one
try {
	$dkim = new DKIM();
	$dkim->createPrivateKey();
	// For DEV/Vanderbilt testing only
	if (isDev(true) &&
		$dkim->hasPrivateKey() && !$dkim->hasDkimDnsTxtRecord())
	{
	    print $dkim->getDnsTxtRecordSuggestion();
	}
}  catch (Exception $e) { }


if (isset($_GET['upgradeinstall'])) {
	$objHtmlPage->PrintFooterExt();
} else {
	include 'footer.php';
}