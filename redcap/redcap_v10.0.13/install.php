<?php


/********************************************************************************************
This file is used for doing a fresh installation of REDCap.
The page will guide you through the install process for getting everything setup.
********************************************************************************************/

error_reporting(0);

header("Expires: 0");
header("cache-control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
// Declare current page with full path
$_SERVER['PHP_SELF'] = str_replace("&amp;", "&", htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES));
define("PAGE_FULL", $_SERVER['PHP_SELF']);
// Declare current page
define("PAGE", basename(PAGE_FULL));
// Set constant to not display any blank options for drop-downs on this page
define('DROPDOWN_DISABLE_BLANK', true);
// Define DIRECTORY_SEPARATOR as DS for less typing
define("DS", DIRECTORY_SEPARATOR);
define('APP_PATH_DOCROOT', dirname(__FILE__) . DS);

// If this is an auto-install GET request, set post params
if ($_SERVER['REQUEST_METHOD'] != 'POST' && (isset($_GET['auto']) || isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME']))) {
    $_GET['sql'] = $_GET['auto'] = 1;
}

//Get install version number and set the file paths correctly
$maindir  = "";
if (isset($install_version)) {
	$maindir  = "redcap_v" . $install_version . "/";
} else {
	if (basename(dirname(__FILE__)) == "codebase") {
		// If this is a developer with 'codebase' folder instead of version folder, then use JavaScript to get version from query string instead
		if (isset($_GET['version'])) {
			$install_version = $_GET['version'];
		} else {
			// Redirect via JavaScript
			?>
			<script type="text/javascript">
			var urlChunks = window.location.href.split('/').reverse();
			window.location.href = window.location.href+'?version='+urlChunks[1].substring(8);
			</script>
			<?php
			exit;
		}
	} else {
		//Get the current version number to upgrade to from the folder name of "redcap_vX.X.X".
		$temp = explode("redcap_v", basename(dirname(__FILE__)));
		$install_version = $temp[1];
	}
}
// Set constants for paths
$redcap_version = $install_version;
define('REDCAP_VERSION', 	$redcap_version);
define('APP_PATH_WEBROOT', 	$maindir);
define('APP_PATH_IMAGES', 	$maindir . "Resources/images/");
define('APP_PATH_CSS', 		$maindir . "Resources/css/");
define('APP_PATH_JS', 		$maindir . "Resources/js/");
define('APP_PATH_WEBPACK', 	$maindir . "Resources/webpack/");

// Files with necessary functions
require_once dirname(__FILE__) . '/Config/init_functions.php';

// Pre-fill form with default values
$form_data = array( "system_offline" => "0",
					"auth_meth_global" => "none",
					"auth_meth" => "none",
					"login_autocomplete_disable" => "0",
					"superusers_only_create_project" => "0",
					"bioportal_api_token" => (isDev() ? "065188de-67e2-42a9-aa7b-e2340d314cb0" : ""),
					"superusers_only_move_to_prod" => (isDev() ? "0" : "1"),
					"autologout_timer" => "30",
					"enable_plotting" => "0",
					"auto_report_stats" => (isDev() ? "0" : "1"),
					"shibboleth_username_field" => "none",
					"display_nonauth_projects" => "1",
					"homepage_contact" => (isDev() ? "Rob Taylor (343-9024)" : "REDCap Administrator (123-456-7890)"),
					"homepage_contact_email" => (isDev() ? "robtaylor1978@gmail.com" : "email@yoursite.edu"),
					"edoc_field_option_enabled" => "1",
					"edoc_storage_option" => "0",
					"footer_links" => "http://www.mc.vanderbilt.edu/, VUMC\nhttp://www.mc.vanderbilt.edu/diglib/, DigLib\nhttps://phonedirectory.vanderbilt.edu/cdb/, People Finder\nhttps://www.mc.vanderbilt.edu/gcrc/, GCRC",
					"footer_text" => "Vanderbilt University | 1211 22nd Ave S, Nashville, TN 37232 (615) 322-5000",
					"project_language" => "English",
					"project_contact_name" => (isDev() ? "Rob Taylor (343-9024)" : "REDCap Administrator (123-456-7890)"),
					"project_contact_email" => (isDev() ? "robtaylor1978@gmail.com" : "email@yoursite.edu"),
					"institution" => "SoAndSo University",
					"site_org_type" => "SoAndSo Institute for Clinical and Translational Research",
					"login_logo" => "https://www.mc.vanderbilt.edu/victr/secure/redcap/vumc.png",
					"shared_library_enabled" => "1",
					"sendit_enabled" => "1",
					"google_translate_enabled" => "1",
					"language_global" => "English",
					"api_enabled" => "1",
					"identifier_keywords" => System::identifier_keywords_default,
					"logout_fail_limit" => '5',
					'logout_fail_window' => '15',
					'display_project_logo_institution' => '0',
					'enable_url_shortener' => '1',
					'default_datetime_format' => $install_datetime_default,
					'default_number_format_decimal' => $install_decimal_default,
					'default_number_format_thousands_sep' => $install_thousands_sep_default
				  );


// Language: Call the correct language file for this project (default to English)
$lang = Language::getLanguage('English');

if (!isset($_GET['sql']))
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeader();
	?>
	<script type="text/javascript">
	$(function(){

		// Test HTTP request to REDCap consortium server (to see if can report stats automatically)
		// First try direct ajax method
		var thisAjax1 = $.ajax({ type: 'GET', crossDomain: true, url: 'https://redcap.vanderbilt.edu/consortium/ping.php',
			error: function(e) {
				// Now try server-side method
				var thisAjax2 = $.post(app_path_webroot+'ControlCenter/check_server_ping.php', { noauthkey: '<?php echo  md5($salt . date('YmdH')) ?>' }, function(data) {
					if (data.length == 0 || data != "1") {
						$('form#form :input[name="auto_report_stats"]').val('0');
					}
				});
				// If does not finish after X seconds, then set stats reporting to "manual"
				setTimeout(function(){
					if (thisAjax2.readyState == 1) {
						thisAjax2.abort();
						$('form#form :input[name="auto_report_stats"]').val('0');
					}
				},8000);
			}});
		// If does not finish after X seconds, then set stats reporting to "manual"
		setTimeout(function(){
			if (thisAjax1.readyState == 1) {
				thisAjax1.abort();
			}
		},5000);

		// Pre-fill the redcap_base_url using javascript
		var redcap_base_url = dirname(document.URL);
		var redcap_version_dir = 'redcap_v'+redcap_version;
		if (redcap_base_url.substr(redcap_base_url.length-redcap_version_dir.length-1) == '/'+redcap_version_dir) {
			redcap_base_url = redcap_base_url.substr(0, redcap_base_url.length-redcap_version_dir.length);
		} else {
			redcap_base_url += '/';
		}
		$('form#form :input[name="redcap_base_url"]').val(redcap_base_url);
	});
	</script>

	<style type='text/css'>
	.labelrc, .data {
		background:#F0F0F0 url('<?php echo $maindir ?>Resources/images/label-bg.gif') repeat-x scroll 0 0;
		border:1px solid #CCCCCC;
		font-size:12px;
		font-weight:bold;
		
		padding:5px 10px;
	}
	.labelrc a:link, .labelrc a:visited, .labelrc a:active, .labelrc a:hover { font-size:12px; font-family: "Open Sans",Helvetica,Arial,sans-serif; }
	.form_border { width: 100%;	}
	#sub-nav { font-size:60%; }
	/* Weird issue with Firefox/Chrome with textboxes on this page */
	.x-form-text { height:22px; }
	</style>

	<?php
	//PAGE HEADER
	print "<table align=center><tr><td style='border:2px solid #D0D0D0;width:700px;padding: 10px 15px 5px 15px;background:#FFFFFF;'>";
	print "<table width=100% cellpadding=0 cellspacing=0><tr><td valign=top align=left>
				<h4 style='font-size:20px;color:#800000;'>REDCap " . "$install_version Installation Module</h4>
		   </td><td valign=top style='text-align:right;'>
				<img src='" . $maindir . "Resources/images/redcap-logo-small.png'>
		   </td></tr></table>";
}

// If this is a semi-automated install GET request, set post params
if ($_SERVER['REQUEST_METHOD'] != 'POST' && isset($_GET['sql'])) {
	$_POST = $form_data;
}

/**
 * GENERATE INSTALLATION SQL
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' || ($_SERVER['REQUEST_METHOD'] != 'POST' && isset($_GET['sql']))) {

	// print "<pre>";print_r($_POST);print "</pre>";

	// Render textbox with SQL
	if (!isset($_GET['sql'])) {
		print  "<hr size=1>
				<p>
					<b>STEP 4: Create the REDCap database tables</b><br><br>
					Copy the SQL in the box below and execute it in a MySQL client.
				</p>";
		// Render textarea box for putting SQL
		print  "<p><textarea style='font-size:11px;width:90%;height:150px;' readonly='readonly' onclick='this.select();'>\n"
			.  "-- ----------------------------------------- --\n-- REDCap Installation SQL --\n-- ----------------------------------------- --\n"
			.  "USE `$db`;\n-- ----------------------------------------- --\n";
	}
	// Include SQL from install.sql and install_data.sql files as the base table structure and initial values needed
	$installSQL = file_get_contents(dirname(__FILE__) . "/Resources/sql/install.sql");
	// utf8mb4 encoding is only supported in MySQL 5.5+
	$canUseUtf8mb4 = version_compare(db_get_version(), '5.5', '>=');
	if (!$canUseUtf8mb4) {
		// Reset db charset and collation to utf8 rather than utf8mb4
		$installSQL = str_replace("utf8mb4", "utf8", $installSQL);
		$installSQL = str_replace("`(191)", "`", $installSQL);
	}
	print $installSQL;
	include dirname(__FILE__) . "/Resources/sql/install_data.sql";
	// Now add the custom site values
	print "\n\n-- Add custom site configuration values --\n";
	// Set password algorithm
	print  "UPDATE redcap_config SET value = '".db_escape(Authentication::getBestHashAlgo())."' WHERE field_name = 'password_algo';\n";
	foreach ($_POST as $this_field=>$this_value) {
		print "UPDATE redcap_config SET value = '".db_escape(decode_filter_tags($this_value))."' "
			. "WHERE field_name = '".db_escape(decode_filter_tags($this_field))."';\n";
	}
	// Add new version number
	print  "UPDATE redcap_config SET value = '$install_version' WHERE field_name = 'redcap_version';\n";
	// Include SQL to auto-create demo project(s)
	include dirname(__FILE__) . "/Resources/sql/create_demo_db1.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db4.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db2.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db3.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db5.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db6.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db7.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db8.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db9.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db10.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db11.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db12.sql";
	include dirname(__FILE__) . "/Resources/sql/create_demo_db13.sql";

	if (isset($_GET['sql'])) {
        if (isset($_GET['auto'])) {
            // If config table is missing, then do auto-install
            db_connect();
            $q = db_query("select * from redcap_config");
            $installSQL = ob_get_clean();
			if (db_error() != '') {
                db_multi_query($installSQL);
                print "Install completed!";
            } else {
                print "No need to install";
            }
        }
    } else {
		print  "</textarea></p>";
		// Configuration Check
		print  "<br><hr size=1>
				<p>
					<b>STEP 5: Configuration Check</b><br><br>
					After you have successfully executed the SQL in the box above, the installation process is almost complete.
					The only thing left to do is to navigate to the
					<a href='{$maindir}ControlCenter/check.php?upgradeinstall=1' style='text-decoration:underline;font-weight:bold;'>REDCap Configuration Check</a>
					page to ensure that all of REDCap's essential components are in place. If all the test's are successful,
					it will give you the link for accessing REDCap.
				</p>";
		print  "<br><br><br>
			</td></tr></table>
			</BODY>
			</HTML>";
	}
	exit;

}

//Introduction
print  "<hr size=1>
		<p style='margin-top:25px;'>
			This page will guide you through the process of installing <font color=#800000>REDCap version ".$install_version."</font> on your system.
			At this point, you must have a MySQL or MariaDB database server running in order to continue with the installation process. 
			To complete the installation, you will need to use a MySQL client (e.g., phpMyAdmin, MySQL Command-Line Tool, MySQL Workbench) 
			to interface with the MySQL server. Note: REDCap is compatible with MariaDB as an alternative to MySQL.
		</p>
		<hr size=1>";

print  "<div class='p'>
			<b>STEP 1: Create a MySQL database/schema and user (using a MySQL client)</b><br><br>

			Using a MySQL client of your choice, you will first need to create a MySQL database (i.e., schema) in which to place the REDCap tables.
			You will also need to create a corresponding MySQL user for REDCap to use to access the MySQL database. Below are examples
			of the queries you might run to create the database and user (if you wish, you may choose your own name for the database or user).<br><br>
			
			<pre style='font-size:12px;padding:3px 6px;'>
-- Example for creating the database (in either MariaDB or MySQL)
CREATE DATABASE IF NOT EXISTS `redcap`;

-- (MARIADB ONLY) Example for creating the MariaDB user (replace the user and password with your own values)
CREATE USER 'redcap_user'@'%' IDENTIFIED BY 'password_for_redcap_user';
GRANT SELECT, INSERT, UPDATE, DELETE ON `redcap`.* TO 'redcap_user'@'%';

-- (MYSQL ONLY - DO NOT USE WITH MARIADB) 
-- Example for creating the MySQL user (replace the user and password with your own values)
CREATE USER 'redcap_user'@'%' IDENTIFIED WITH mysql_native_password BY 'password_for_redcap_user';
GRANT SELECT, INSERT, UPDATE, DELETE ON `redcap`.* TO 'redcap_user'@'%';
</pre>
			
			If using a MySQL client with a GUI, you may alternatively use its built-in methods for creating a database
			and user rather than executing the queries above. Note: For security reasons, it is recommended that REDCap's MySQL user
			only be given SELECT, INSERT, UPDATE, and DELETE privileges for the database.
		</div>
		<hr size=1>";

print  "<p>
			<b>STEP 2: Add MySQL connection values to 'database.php'</b><br><br>
			You now need to set up the database connection file that will allow REDCap to connect to the MySQL database you just created.
			This database connection file will store the hostname, username, password, and database/schema name for that MySQL database.
			Find the file 'database.php' (which sits under your main REDCap directory of your web server) and open it for editing in a text editor
			of your choice. Add your MySQL database connection values (hostname, database name, username, password) to that file by replacing the
			placeholder values in single quotes. Also, while still in the 'database.php' file, add a random value of your choosing
			for the \$salt variable at the bottom of the page, preferably an alpha-numeric string with 8 characters or more.
			(This value wll be used for de-identification hashing in the Data Export module. Do NOT change the \$salt value once it has been
			set initially.) If you have not yet performed this step, you will likely see an error below. Once you have added the values to
			'database.php', reload this page to test it.<br><br>";

// Path to database.php
$cxn_file_path = dirname(dirname(__FILE__)) . DS . "database.php";

// Could not find database.php
if (!include $cxn_file_path) {
	exit("<b style='color:red;'>ERROR:</b> REDCap could not find 'database.php'. Please find it and place it at the following
		  location on your web server, and then reload this page: <b>$cxn_file_path</b>");
// Found database.php
} else {
	//Check to see if all variables are accounted for inside the cxn file. If not, ask to fix file and try again.
	if (!isset($username) || !isset($password) || !isset($db) || (!isset($hostname) && !isset($db_socket))) {
		print  "<b style='color:red;'>ERROR:</b> REDCap could not find all the following variables in 'database.php' that are necessary
				for a database connection: <b>\$hostname</b>, <b>\$username</b>, <b>\$password</b>, <b>\$db</b>. All four (4) variables are needed. Please add
				any that are missing and reload this page.";
		exit;
	// Found connection values
	} else {
		print "<b>Now attempting connection to database server...</b><br>";
		db_connect(true);
		print  "<b style='color:green;'>Connection to the MySQL database '$db' was successful!</b><br><br>";
		// Check if InnoDB engine is enabled in MySQL
		$tableCheck = new SQLTableCheck();
		if (!$tableCheck->innodb_enabled()) {
			print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>InnoDB engine is NOT enabled in MySQL
					- CRITICAL:</b>
					It appears that your MySQL database server does not have the InnoDB table engine enabled, which is required for REDCap
					to run properly. To enable it, open your my.cnf (or my.ini) configuration file for MySQL
					and remove all instances of \"--innodb=OFF\" and \"--skip-innodb\". Then restart MySQL, and then reload this page.
					If that does not work, then see the official MySQL documentation for how to enable InnoDB for your specific version of MySQL.</div>";
			exit;
		}
		// Does db server have utf8mb4 charset?
		if (!System::dbHasUtf8mb4Encoding()) {
			print  "<div class='red'><img src='".APP_PATH_IMAGES."exclamation.png'> <b>\"utf8mb4\" character set is NOT enabled in MySQL
					- CRITICAL:</b>
					It appears that your MySQL database server does not have the \"utf8mb4\" character set installed or enabled, which is required for REDCap
					to run properly. To enable it, you should talk to your local database person to learn how to install it or enable it on your MySQL database server. 
					Once done, restart MySQL, and then reload this page.
					If that does not work, then see the official MySQL documentation for how to install/enable it for your specific version of MySQL.</div>";
			exit;
		}
		// Get the SALT variable, which is institutional-unique alphanumeric value.
		if (!isset($salt) || $salt == "") {
			// Warn user that the SALT was not defined in the connection file and give them new salt
			exit(  "<b style='color:red;'>ERROR:</b> REDCap could not find the variable <b>\$salt</b> defined in [$cxn_file_path].<br><br>
					Please open the file for editing and add code below after your database connection variables and then
					reload this page. (The value was auto-generated, but you may use any random value of your choosing,
					preferably an alpha-numeric string with 8 characters or more.<br><br>
					<b>\$salt = '".substr(sha1(rand()), 0, 10)."';</b>");
		}
	}
}

print  "</p>
		<hr size=1>";


print  "<p>
			<b>STEP 3: Customize values for your server and institution</b><br><br>
			Set the values below for your site's initial configuration. You will be able to change these after this installation process
			in REDCap's Control Center. REDCap's user authentication will be initially set as \"None (Public)\", but proper authentication
			can later be enabled on the Control Center's Security & Authentication page once you have gotten
			REDCap fully up and running. When you have set all the values, click the SUBMIT button at the bottom of the page.
		</p>
		<p style='color:#800000;'>
			<i>NOTE: All the settings below can be easily modified, if needed, after you have completed the installation process.</i>
		</p>";
// Get files for rendering form
include dirname(__FILE__) . "/ControlCenter/install_config_metadata.php";
// Set array of U.S. timezones for setting defaults for these
$timeZonesUS = array('America/New_York', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles',
					 'America/Anchorage', 'America/Adak', 'Pacific/Honolulu');
$isUS = in_array(getTimeZone(), $timeZonesUS);
if ($isUS) {
	$install_datetime_default = 'M/D/Y_12';
	$install_decimal_default = '.';
	$install_thousands_sep_default = '&#44;';
} else {
	$install_datetime_default = 'D/M/Y_12';
	$install_decimal_default = '&#44;';
	$install_thousands_sep_default = '.';
}
// Render form
DataEntry::renderForm($elements, $form_data);


print  "
<br><br><br><br></td></tr></table>
</BODY>
</HTML>";
