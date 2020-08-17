<?php


// Require the System class
require_once dirname(dirname(__FILE__)) . '/Classes/System.php';
// Initialize REDCap
System::init();

//Connect to the MySQL project where the REDCap tables are kept
function db_connect($reportOnlySuperUserErrors=false, $useUpgradesUser=false)
{
	global $lang, $rc_connection, $conn, $redcap_updates_user, $redcap_updates_password;
	$rc_connection = null;
	$db_error_msg = "";
	// For install page, do not report errors here (because messes up installation workflow)
	if (!isset($reportErrors) || $reportErrors == null) {
		$reportErrors = (basename($_SERVER['PHP_SELF']) != 'install.php');
	}
	// Include db file
	$db_conn_file = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'database.php';
	include $db_conn_file;
	// CI Alternative: Use specific environment variables (if available) instead of those in database.php
    if (isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME'])) {
        $hostname = $_SERVER['MYSQL_REDCAP_CI_HOSTNAME'];
        $username = $_SERVER['MYSQL_REDCAP_CI_USERNAME'];
        $password = $_SERVER['MYSQL_REDCAP_CI_PASSWORD'];
        $db = $_SERVER['MYSQL_REDCAP_CI_DB'];
        $salt = sha1($password);
    }
	// If we already have $conn from redcap_connect.php, then don't create a new connection
	if (!$useUpgradesUser && isset($conn) && is_object($conn)) {
		$rc_connection = $conn;
	} else {
		if ($useUpgradesUser) {
			// User the upgrades user/password for the connection
			$username = $redcap_updates_user;
			$password = decrypt($redcap_updates_password);
		}
		// Check the connection values
		if (!isset($db_socket)) $db_socket = null;
		if (!isset($username) || !isset($password) || !isset($db) || (!isset($hostname) && !isset($db_socket))) {
			$db_error_msg = "One or more of your database connection values (\$hostname, \$db, \$username, \$password)
							 could not be found in your database connection file [$db_conn_file]. Please make sure all four variables are
							 defined with a correct value in that file.";
		}
		// First, check that MySQLi extension is installed
		if (!function_exists('mysqli_connect')) {
			exit("<p style='margin:30px;width:700px;'><b>ERROR: MySQLi extension in PHP is not installed!</b><br>
				  REDCap 5.1.0 and later versions require the MySQLi extension in PHP. You will need to first install PHP's MySQLi
				  extension on your webserver before you can continue further.
				  <a target='_blank' href='http://php.net/manual/en/mysqli.setup.php'>Download and install the MySQLi extension</a><br><br>
				  <b>Why has this changed from previous REDCap versions?</b><br>
				  PHP 5.5 and later versions no longer support the MySQL extension, which was used in prior versions of REDCap, thus
				  REDCap now utilizes the MySQLi extension instead.
				  </p>");
		}
		if ($db_socket !== null) {
			if ($password == '') $password = null;
		}
		if (isset($db_ssl_ca) && $db_ssl_ca != '') {
			// Connect to MySQL via SSL
			defined("MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT") or define("MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT", 64);
			$this_connection = mysqli_init();
			mysqli_options($this_connection, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
			mysqli_ssl_set($this_connection, $db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
			$conn_ssl = mysqli_real_connect($this_connection, remove_db_port_from_hostname($hostname), $username, $password, $db, get_db_port_by_hostname($hostname, $db_socket), $db_socket, ((isset($db_ssl_verify_server_cert) && $db_ssl_verify_server_cert) ? MYSQLI_CLIENT_SSL : MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT));
		} else {
			// Connect to MySQL normally
			$this_connection = mysqli_connect(remove_db_port_from_hostname($hostname), $username, $password, $db, get_db_port_by_hostname($hostname, $db_socket), $db_socket);
		}
		if (!$this_connection || (isset($conn_ssl) && !$conn_ssl)) {
			if ($useUpgradesUser) {
				return false;
			}
			$db_error_msg = "Your REDCap database connection file [$db_conn_file] could not connect to the database server.
							 Please check the connection values in that file (\$hostname, \$db, \$username, \$password)
							 because they may be incorrect.";
		}
		// Set connection as $rc_connection
		$rc_connection = $this_connection;
		// Set secondary db connection variable that can be used in hooks and plugins
		if (!$useUpgradesUser) {
			$conn = $rc_connection;
		}
	}
	// Set sql_mode
	System::setDbSqlMode();
	// Ensure that the db character set encoding is set correctly
	System::checkDbEncoding();
	
	// If there was a db connection error, then display it
	if (($reportErrors || $reportOnlySuperUserErrors) && $db_error_msg != "")
	{
		// Add 500 error when db connection fails
		header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		// Display message to user
		if ($reportOnlySuperUserErrors) {
			print RCView::div(array('class'=>'red', 'style'=>'margin-bottom:20px;'), "<b>ERROR:</b> $db_error_msg");
		} else {
			?>
			<div style="font: normal 12px Verdana, Arial;padding:20px;border: 1px solid red;color: #800000;max-width: 600px;background: #FFE1E1;">
				<div style="font-weight:bold;font-size:15px;padding-bottom:5px;">
					CRITICAL ERROR: REDCap server is offline!
				</div>
				<div>
					For unknown reasons, REDCap cannot communicate with its database server, which may be offline. Please contact your
					local REDCap administrator to inform them of this issue immediately. If you are a REDCap administrator, then please see this
					<a href="javascript:;" style="color:#000066;" onclick="document.getElementById('db_error_msg').style.display='block';">additional information</a>.
					We are sorry for any inconvenience.
				</div>
				<div id="db_error_msg" style="display:none;color:#333;background:#fff;padding:5px 10px 10px;margin:20px 0;border:1px solid #bbb;">
					<b>Message for REDCap administrators:</b><br/><?php echo $db_error_msg ?>
				</div>
			</div>
			<?php
		}
		exit;
	}
	// Get the SALT, which is institutional-unique alphanumeric value, and is found in the Control Center db connection file.
	// It is the first part of the total salt used for Date Shifting and (eventually) Encryption at Rest
	if ($reportErrors && (!isset($salt) || (isset($salt) && empty($salt))))
	{
		// Warn user that the SALT was not defined in the connection file and give them new salt
		exit(  "<div style='font-family:Verdana;font-size:12px;line-height:1.5em;padding:25px;'>
				<b>ERROR:</b><br>
				REDCap could not find the variable <b>\$salt</b> defined in [<font color='#800000'>$db_conn_file</font>].<br><br>
				Please open the file for editing and add the following code after your database connection variables:
				<b>\$salt = \"".substr(sha1(rand()), 0, 10)."\";</b>
				</div>");
	}
	// Set global variables
	$GLOBALS['hostname'] = $hostname;
	$GLOBALS['username'] = $username;
	$GLOBALS['password'] = $password;
	$GLOBALS['db'] 		 = $db;
	$GLOBALS['salt'] 	 = $salt;
	if (isset($db_ssl_ca) && $db_ssl_ca != '') {
		$GLOBALS['db_ssl_key'] = $db_ssl_key;
		$GLOBALS['db_ssl_cert'] = $db_ssl_cert;
		$GLOBALS['db_ssl_ca'] = $db_ssl_ca;
		$GLOBALS['db_ssl_capath'] = $db_ssl_capath;
		$GLOBALS['db_ssl_cipher'] = $db_ssl_cipher;
	}
	// DTS connection variables
	if (isset($dtsHostname)) {
		$GLOBALS['dtsHostname'] = $dtsHostname;
		$GLOBALS['dtsUsername'] = $dtsUsername;
		$GLOBALS['dtsPassword'] = $dtsPassword;
		$GLOBALS['dtsDb']		= $dtsDb;
	}
	return true;
}

## ABSTRACTED DATABASE FUNCTIONS
## Replaced mysql_* functions with db_* functions, which are merely abstracted MySQLi functions
// DB: Query the database
function db_query($sql, $conn = null, $resultmode = MYSQLI_STORE_RESULT) {
	global $rc_connection, $lang;
	// If link identifier is explicitly specified, then use it rather than the default $rc_connection.
	if ($conn == null) $conn = $rc_connection;
	// Check existing queries for this user in other mysql processes
	if (isset($GLOBALS['REDCapCurrentUserHasQueries'])) 
	{
		// Loop through all queries that user has running
		foreach ($GLOBALS['CurrentUserQueries'] as $thisMysqlProcessId=>$thisSql) {
			// The SQL returned from MySQL does not include trailing semicolons.
			// If a trailing semicolons exists, we must trim it for it to match below.
			$sql = rtrim($sql, ';');

			// Is same query already running for this user on SAME page?
			if ($thisSql == $sql) {
				// Kill the query in the other process
				mysqli_query($conn, "KILL $thisMysqlProcessId", $resultmode);
				// Remove query from array
				unset($GLOBALS['CurrentUserQueries'][$thisMysqlProcessId]);
			}
		}
		// If no more queries are in the array, then remove var check
		if (empty($GLOBALS['CurrentUserQueries'])) {
			unset($GLOBALS['REDCapCurrentUserHasQueries']);
		}
	}
	// Execute the query
	$success = mysqli_query($conn, $sql, $resultmode);	
	// If got the error "MySQL server has gone away", then the query was either killed by another mysql process
	// using the section of code above, or the server is down, so display an error message.
	if (!$success && mysqli_errno($rc_connection) == 2006 && !defined("ShutdownStarted")) 
	{
		// Custom message for Data Quality rules (ajax calls)
		if (defined('PAGE') && PAGE == "DataQuality/execute_ajax.php")
		{
			// Get current rule_id and the ones following
			list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
			// Set main error msg seen in table
			$dqErrMsg = $lang['dataqueries_314'];
			// Script timeout error
			$msg = "<div id='results_table_{$rule_id}'>
                        <p class='red' style='max-width:500px;'>
                            <b>{$lang['global_01']}{$lang['colon']}</b> {$lang['dataqueries_315']} 5 {$lang['dataqueries_316']}
                        </p>
                    </div>";
			// Send back JSON
			print '{"rule_id":"' . $rule_id . '",'
				. '"next_rule_ids":"' . $rule_ids . '",'
				. '"discrepancies":"1",'
				. '"discrepancies_formatted":"<span style=\"font-size:12px;\">'.$dqErrMsg.'</span>",'
				. '"dag_discrepancies":[],'
				. '"title":"' . RCView::escape($_GET['error_rule_name']) . '",'
				. '"payload":"' . cleanJson($msg)  .'"}';
			exit;
		}
		// General error
	    print RCView::p(array('class'=>'red', 'style'=>'max-width:700px;'),
				"<b>REDCap ERROR:</b> An unknown error has caused the REDCap page to halt. This may have occurred if you have opened
				multiple browser tabs of the same REDCap page. If that is not the case, then please try to reload this page."
			  );
		exit;
	}	
	// Return false if failed. Return object if successful.
	return $success;
}
// DB: Get next result
function db_next_result() {
	global $rc_connection;
	return mysqli_next_result($rc_connection);
}
// DB: Execute multiple SQL queries
function db_multi_query($sql) {
	$sql_array = SQLTableCheck::parseMultipleSqlQueries($sql);
	foreach ($sql_array as $thisSql) db_query($thisSql);
	return true;
}
// DB: Get the current Mysql process/connection/thread ID
function db_thread_id() {
	global $rc_connection;
	return mysqli_thread_id($rc_connection);
}
// DB: Get the MySQL version number to the first decimal point
function db_get_version($returnThirdPlace=false) {
	$q = db_query("select version()");
	$version = db_result($q, 0);
	$version_pieces = explode(".", $version, 3);
	$version_pieces = array_map('intval', $version_pieces);
	if (!isset($version_pieces[1])) $version_pieces[1] = '0';
	if (!isset($version_pieces[2])) $version_pieces[2] = '0';
	if (!$returnThirdPlace) {
	    unset($version_pieces[2]);
	}
	return implode(".", $version_pieces);
}
// DB: Get db server type (MySQL vs MariaDB)
function db_get_server_type() {
    $q = db_query("select version()");
    $version = db_result($q, 0);
    return strpos($version, "MariaDB") ? "MariaDB" : "MySQL";
}
// DB: Mysql status
function db_stat() {
	global $rc_connection;
	return mysqli_stat($rc_connection);
}
// DB: fetch_row
function db_fetch_row($q) {
	if(System::isStatementResult($q)){
		return $q->fetch_row();
	}

	return mysqli_fetch_row($q);
}
// DB: fetch_assoc
function db_fetch_assoc($q) {
	if(System::isStatementResult($q)){
		return $q->fetch_assoc();
	}

	return mysqli_fetch_assoc($q);
}
// DB: fetch_array
function db_fetch_array($q, $resulttype = MYSQLI_BOTH) {

	if(PHP_VERSION_ID < 70000)
	{
		switch ($resulttype) {
		case MYSQL_ASSOC:
			$resulttype=MYSQLI_ASSOC;
			break;
		case MYSQL_NUM:
			$resulttype=MYSQLI_NUM;
			break;
		case MYSQL_BOTH:
			$resulttype=MYSQLI_BOTH;
		}
	}

	if(System::isStatementResult($q)){
		return $q->fetch_array($resulttype);
	}

	return mysqli_fetch_array($q, $resulttype);
}
// DB: num_rows
function db_num_rows($q) {
	// debugQ($q);

	if(System::isStatementResult($q)){
		return $q->num_rows;
	}

	return mysqli_num_rows($q);
}
// DB: affected_rows
function db_affected_rows() {
	global $rc_connection;
	return mysqli_affected_rows($rc_connection);
}
// insert_id
function db_insert_id() {
	global $rc_connection;
	return mysqli_insert_id($rc_connection);
}
// DB: free_result
function db_free_result($q) {
	if(System::isStatementResult($q)){
		return @$q->free_result();
	}

	return @mysqli_free_result($q);
}
// DB: real_escape_string
function db_real_escape_string($str) {
	global $rc_connection;
	return mysqli_real_escape_string($rc_connection, $str);
}
// DB: error
function db_error() {
	global $rc_connection;
	return mysqli_error($rc_connection);
}
// DB: errno
function db_errno() {
	global $rc_connection;
	return mysqli_errno($rc_connection);
}
// DB: field_name
function db_field_name($q, $field_number) {
	// debugQ($q);

	if(System::isStatementResult($q)){
		$ob = $q->fetch_field_direct($field_number);
	}
	else{
		$ob = mysqli_fetch_field_direct($q, $field_number);
	}

	return $ob->name;
}
// DB: fetch_fields
function db_fetch_fields($q) {
	if(System::isStatementResult($q)){
		return $q->fetch_fields();
	}

	return mysqli_fetch_fields($q);
}
// DB: num_fields
function db_num_fields($q) {
	if(System::isStatementResult($q)){
		return $q->field_count;
	}

	return mysqli_num_fields($q);
}
// DB: fetch_object
function db_fetch_object($q) {
	if(System::isStatementResult($q)){
		return $q->fetch_object();
	}

	return mysqli_fetch_object($q);
}
// DB: result
function db_result($q, $pos, $field='') {
	if(!method_exists($q, 'data_seek')){
		return false;
	}

	// debugQ($q);
	$i = 0;
	// If didn't specify field, assume the field in first position
	if ($field == '') $field = db_field_name($q, 0);
	// Set pointer to beginning (0)
    $q->data_seek(0);
	// Loop through fields till we get to the correct field
    while ($row = $q->fetch_array(MYSQLI_BOTH)) {
        if ($i == $pos) {
			// Set pointer to next field before exiting
			$q->data_seek($pos+1);
			// Return the value for our field
			return $row[$field];
		}
        $i++;
    }
    return false;
}

// Determine the MySQL port number from the hostname in database.php
function get_db_port_by_hostname($hostname, $db_socket=null)
{
	if ($hostname === null && $db_socket === null) return null;
	$port = '';
	if (strpos($hostname, ':') !== false) {
		list ($hostname_wo_port, $port) = explode(':', $hostname, 2);
	}
	if (!is_numeric($port)) $port = '3306'; // Default MySQL port
	return $port;
}

// Remove the MySQL port number from the hostname in database.php
function remove_db_port_from_hostname($hostname)
{
	return ($hostname === null) ? null : preg_replace("/\:.*/", '', $hostname);
}

/**
 * PROMPT USER TO LOG IN
 */
function loginFunction()
{
	global $authFail, $project_contact_email, $project_contact_name, $auth_meth, $login_autocomplete_disable,
		   $homepage_contact_email, $homepage_contact, $autologout_timer, $lang, $isMobileDevice, $institution,
		   $login_logo, $login_custom_text, $homepage_announcement, $homepage_announcement_login, $homepage_contact_url, $aafAccessUrl;

	if ($authFail && isset($_POST['submitted'])) {
		// If the authentication has failed after submission
		// return to try and authenticate off the next server
		return 0;
	}

	// Set defaults
	$username_placeholder = $password_placeholder = $custom_login_js = $custom_login_css = $custom_login_html = "";

	// PASSWORD RESET KEY VIA EMAIL: If temporary password flag is set and URL contains reset key and encoded username, then start their session
	if (isset($_GET['action']) && $_GET['action'] == 'passwordreset' && isset($_GET['u']) && trim($_GET['u']) != '' && isset($_GET['k']) && trim($_GET['k']) != '')
	{
		$username_decoded = base64_decode(urldecode($_GET['u']));
		$password_reset_key = rawurldecode(urldecode($_GET['k']));
		// Verify the username and password reset key
		$verifiedPasswordResetKey = Authentication::verifyPasswordResetKey($username_decoded, $password_reset_key);
		// If verified, then manually set username and password to be prefilled on login form
		if ($verifiedPasswordResetKey) {
			// Set username
			$username_placeholder = $username_decoded;
			// Generate new password
			$password_placeholder = Authentication::resetPassword($username_decoded);
			// Set JavaScript to auto submit the login form on pageload
			$custom_login_js = "<script type='text/javascript'>document.form.submit();</script>";
			// Set CSS to make page invisible so that nothing is seen
			$custom_login_css = "<style type='text/css'>body { display:none; }</style>";
			// Set temporary password in a separate password input to prevent browsers from pre-filling password
			$custom_login_html = "<input type='password' name='redcap_login_password_temp' value=\"$password_placeholder\">";
		}
	}

	// If using RSA SecurID two-factor authencation, use passcode instead of password in text
	$passwordLabel = $lang['global_32'].$lang['colon'];
	$passwordTextRight = "";
	$rsaLogo = "";
	if ($auth_meth == 'rsa') {
		$rsaLogo =  RCView::div(array('style'=>'text-align:center;padding-bottom:15px;'),
						RCView::img(array('src'=>'securid2.gif'))
					);
		$passwordLabel = $lang['global_82'].$lang['colon'];
		$passwordTextRight = RCView::div(array('style'=>'color:#800000;font-size:13px;margin:4px 0;text-align:right;'),
								$lang['config_functions_92']
							 );
	}

	// Set "forgot password?" link
	$forgotPassword = "";
	if ($auth_meth == "table" || $auth_meth == "ldap_table" || strpos($auth_meth,'aaf')>-1 || $auth_meth == "shibboleth_table" ) { //***<AAF Modification>***
		$forgotPassword = RCView::div(array("style"=>"float:right;margin-top:10px;margin-right:10px;"),
							RCView::a(array("style"=>"font-size:11px;text-decoration:underline;","href"=>APP_PATH_WEBROOT."Authentication/password_recovery.php"), $lang['pwd_reset_41'])
						  );
	}

	// REDCap Hook injection point: Pass PROJECT_ID constant (if defined).
	Hooks::call('redcap_every_page_before_render', (defined("PROJECT_ID") ? array(PROJECT_ID) : array()));

	// Display the Login Form
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("home.css", 'screen,print');
	$objHtmlPage->PrintHeader();
	// Custom CSS
	print $custom_login_css;

	print '<style type="text/css">#container{ background: url("'.APP_PATH_IMAGES.'redcap-logo-large.png") no-repeat; }</style>';

	print '<div id="left_col">';

	print '<h4 style="margin-top:60px;padding:3px;border-bottom:1px solid #AAAAAA;color:#000000;font-weight:bold;">'.$lang['config_functions_45'].'</h4>';

	// Institutional logo (optional)
	if (trim($login_logo) != "")
	{
		print  "<div style='margin-bottom:20px;text-align:center;'>
					<img src='$login_logo' title=\"".js_escape2(strip_tags(label_decode($institution)))."\" alt=\"".js_escape2(strip_tags(label_decode($institution)))."\" style='max-width:850px;'>
				</div>";
	}

	// Show custom login text (optional)
	if (trim($login_custom_text) != "")
	{
		print "<div style='border:1px solid #ccc;background-color:#f5f5f5;margin:15px 10px 15px 0;padding:10px;'>".nl2br(decode_filter_tags($login_custom_text))."</div>";
	}

	// Show custom homepage announcement text (optional)
	if (trim($homepage_announcement) != "" && $homepage_announcement_login == '1') {
		print RCView::div(array('style'=>'margin-bottom:10px;'), nl2br(decode_filter_tags($homepage_announcement)));
		$hide_homepage_announcement = true; // Set this so that it's not displayed elsewhere on the page
	}

	if ($auth_meth == 'aaf' || $auth_meth == 'aaf_table')
	{
		?>
		<div>
			<!-- nav tabs -->
			<ul class="nav nav-tabs" role="tablist">
				<li role="presentation" class="nav-item"><a href="#inst-login" class="nav-link active" data-toggle="tab" aria-controls="inst-login" role="tab" data-toggle="tab">Institutional login</a>
				</li>
				<li role="presentation" class="nav-item"><a href="#non-inst-login" class="nav-link" data-toggle="tab" aria-controls="non-inst-login" role="tab" data-toggle="tab">Non institutional login</a>
				</li>
			</ul>

			<!-- tab panes -->
			<div class="tab-content">
				<div role="tabpanel" class="tab-pane active" id="inst-login">
					<p>If your institution (for example an Australian University, Health or research institution) is a member of the Australian Access Federation click on the AAF graphic below.</p>
					<p><a href='<?php echo $GLOBALS['aafAccessUrl']; ?>'><img src='https://rapid.aaf.edu.au/aaf_service_223x54.png'/></a></p>
				</div>
				<div role="tabpanel" class="tab-pane" id="non-inst-login">
					<p>If you aren&#39;t a member of the Australian Access Federation, fill in your login details below.</p>
		<?php
	}
	if ($auth_meth == 'shibboleth_table')
	{
        $shibboleth_table_config = json_decode($GLOBALS['shibboleth_table_config'], TRUE);
        $first_shib_data = $shibboleth_table_config['institutions'][0];

        ?>
        <div>
            <!-- nav tabs -->
            <style>
                .nav-tabs {
                    background-color: #f8f8f8;
                    padding: none;
                }
                .nav-tabs a {
                    padding: 10px;
                    text-decoration: none !important;
                    display: block;
                }
                .nav-tabs li a:hover {
                    text-decoration: none !important;
                }
                .nav-tabs li:hover a:hover {
                background-color: #e7e7e7;
                }

                /* The following modifiers would be redundant with the JS below
                but the li element for the default login must be selected
                li:active, a:active {
                background-color: #e7e7e7;
                }
                li:focus, a:focus {
                background-color: #e7e7e7;
                }
                */
                /* The CSS relational pseudo-class "has" is not enabled yet
                once usable, it can replace the CSS altering js functions below
                .active li:has(> a[aria-selected="true"]) {
                background-color: #e7e7e7;
                }
                */
            </style>

            <script>
            const shibTableConfig = <?php echo $GLOBALS["shibboleth_table_config"]; ?>;
            const defaultShibUrl = '<?php echo $_SERVER['Shib-Handler'] . "/Login?target=" . $_SERVER['REQUEST_URI']; ?>';
            const repeatShibParams = shibTableConfig.institutions;
            const shibIdPs = repeatShibParams.length;

            function appendShibOptions() {
                // Fill the login pane for each Shibboleth login option
                for (let i = 1; i < shibIdPs; i++) {
                    const thisShib = repeatShibParams[i];

                    // Fill the tab selectors for each Shibboleth login option
                    const text = thisShib['login_option'];
                    const tabOption = $('#shib_login_tab' + (i - 1)).last();
                    var nextTabOption = tabOption.clone();
                    nextTabOption.insertAfter(tabOption);
                    nextTabOption.attr('id', 'shib_login_tab' + i);
                    nextTabOption.find('a').text(text)
                                 .attr('href', '#inst-login' + i)
                                 .attr('aria-controls', 'inst-login' + i);

                    // Remove the tab title data to not clash with appendShibPaneOptions
                    delete thisShib['login_option'];

                    // Handle panes
                    const paneOption = $('#inst-login0.tab-pane');
                    var nextPaneOption = paneOption.clone();
                    nextPaneOption.insertAfter(paneOption);
                    nextPaneOption.attr('id', 'inst-login' + i);

                    for (const param of Object.keys(thisShib)) {
                        const paramValue = thisShib[param];
                        let paramTargetElement = nextPaneOption.find('#' + param);
                        switch(param) {
                            case 'login_text':
                                paramTargetElement.text(paramValue);
                                break;
                            case 'login_url':
                                paramTargetElement.attr('href', (paramValue !== '') ? paramValue : defaultShibUrl);
                                break;
                            case 'login_image':
                                paramTargetElement.attr('src', paramValue);
                                break;
                            default:
                                paramTargetElement.text(paramValue);
                        }
                    }
                }
            }

            $(document).ready(function() {
                appendShibOptions();

                // Activate login pane according to default
                const defaultSelection = '<?php echo $shibboleth_table_config['splash_default']; ?>';
                document.getElementById(defaultSelection)
                        .setAttribute('class', 'tab-pane active');
                // Add selected styling to li element for default
                document.querySelector('[aria-controls="' + defaultSelection + '"]')
                        // .setAttribute('aria-selected', 'true'); // this is set on click but appears to do nothing; prevents function chaining
                        .parentElement.setAttribute('style', 'background-color: #e7e7e7; text-decoration: none;');
                //  TODO: change the above to pure css now that the element properties are being updated in js

                // Change tab appearance after click
                $('.nav li').on('click', function(event) {
                    $('.nav li').css('background-color','#f8f8f8');
                    $(this).css('background-color','#e7e7e7');
                });
            });
            </script>

			<ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#non-inst-login" aria-controls="non-inst-login" role="tab" data-toggle="tab">
                        <?php echo $shibboleth_table_config['table_login_option']; ?>
                    </a>
                </li>
                <li role="presentation" class="active" id="shib_login_tab0">
                    <a href="#inst-login0" aria-controls="inst-login0" role="tab" data-toggle="tab">
                        <?php echo $first_shib_data['login_option']; ?>
                    </a>
                </li>
			</ul>

			<!-- tab panes -->
			<div class="tab-content">
				<div role="tabpanel" class="tab-pane" id="inst-login0">
                    <p id='login_text'>
                        <?php echo $first_shib_data['login_text']; ?>
                    </p>
                    <p>
                        <a id='login_url' href='<?php echo ( $first_shib_data['login_url'] ) ?: $_SERVER['Shib-Handler'] . '/Login?target=' . $_SERVER['REQUEST_URI']; ?>'>
                            <img id='login_image' src='<?php echo $first_shib_data['login_image']; ?>'/>
                        </a>
                    </p>
                </div>
				<div role="tabpanel" class="tab-pane" id="non-inst-login">
		<?php
	}

	// Login instructions (default)
	print  "<p style='font-size:13px;'>
				{$lang['config_functions_67']}
				<a style='font-size:13px;text-decoration:underline;' href=\"".
				(trim($homepage_contact_url) == '' ? "mailto:$homepage_contact_email" : trim($homepage_contact_url)) .
				"\">$homepage_contact</a>{$lang['period']}
			</p>
			<br>";

	// Sanitize action URL for login form
	$loginFormActionUrl = js_escape(str_replace('`', '', $_SERVER['REQUEST_URI']));
	// Give extra room for non-English languages
    $loginLabelLeft = ($GLOBALS['language_global'] == 'English') ? 'margin-left:30px;' : '';
	$loginLabelWidth = ($GLOBALS['language_global'] == 'English') ? 'width:120px;' : 'width:150px;';
    $loginInputWidth = $isMobileDevice ? 'width:150px;' : 'width:180px;';

	print  "<center>";
	print  "<form name='form' style='max-width:350px;' method='post' action='$loginFormActionUrl'>";
	print  $rsaLogo;
	print  "<div class='input-group'>
				<div class='input-group-prepend' style='$loginLabelLeft'>
					<span class='input-group-text fs14 wrap' id='basic-addon1' style='{$loginLabelWidth}color:#333;'>{$lang['global_11']}{$lang['colon']}</span>
					<input type='text' class='form-control fs14' style='border-top-left-radius:0;border-bottom-left-radius:0;{$loginInputWidth}' aria-labelledby='basic-addon1' name='username' id='username' value='".js_escape($username_placeholder)."' tabindex='1' " . ($login_autocomplete_disable ? "autocomplete='new-password'" : "") . ">
				</div>
			</div>";
	print  "<div class='input-group' style='margin-top:10px;'>
				<div class='input-group-prepend' style='$loginLabelLeft'>
					<span class='input-group-text fs14 wrap' id='basic-addon1' style='{$loginLabelWidth}color:#333;'>$passwordLabel</span>
					<input type='password' class='form-control fs14' style='border-top-left-radius:0;border-bottom-left-radius:0;{$loginInputWidth}' aria-labelledby='basic-addon1' name='password' id='password' value='".js_escape($password_placeholder)."' tabindex='2' " . ($login_autocomplete_disable ? "autocomplete='new-password'" : "") . ">
				</div>
			</div>
			$passwordTextRight";
	print  "<div style='text-align:left;margin:20px 0 0 120px;'>
				<button class='btn btn-md btn-defaultrc fs14' id='login_btn' tabindex='3' onclick=\"setTimeout(function(){ $('#login_btn').prop('disabled',true); },10);\">{$lang['config_functions_45']}</button>
				$forgotPassword
			</div>
			<input type='hidden' name='submitted' value='1'>
			<input type='hidden' id='redcap_login_a38us_09i85' name='redcap_login_a38us_09i85' value=''>";

	// FAILSAFE: If user was submitting data on form and somehow the auth session ends before it's supposed to, take posted data, encrypt it, and carry it over after new login
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && PAGE == 'DataEntry/index.php' && isset($_GET['page'])
		&& isset($_GET['event_id']) && (isset($_POST['submit-action']) || isset($_POST['redcap_login_post_encrypt_e3ai09t0y2'])))
	{
		// Encrypt the submitted values, and if login failed, preserve encrypted value
		$enc_val = isset($_POST['redcap_login_post_encrypt_e3ai09t0y2']) ? $_POST['redcap_login_post_encrypt_e3ai09t0y2'] : encrypt(serialize($_POST));
		print  "<input type='hidden' value='$enc_val' name='redcap_login_post_encrypt_e3ai09t0y2'>
				<p class='green' style='text-align:center;'>
					<img src='" . APP_PATH_IMAGES . "add.png'>
					<b>{$lang['global_02']}{$lang['colon']}</b> {$lang['config_functions_68']}
				</p>";
	}

	// Output any custom form HTML/elements
	print $custom_login_html;

	print "</form>";
	print "</center>";	

	// change this so that the end of the stand form is handled correctly - Deakin 20180802
	if ($auth_meth == 'aaf' || $auth_meth == 'aaf_table')
	{
		?>			<p>*Note – New Non-Institutional Logins must have access requested through a <?php echo js_escape2(strip_tags(label_decode($institution))); ?> Staff, Student or Affiliate before they will be able to access REDCap.</p>
				</div>
			</div>
		</div>
		<?php
	} else {
		?><br></div><?php
	}
?>
	<hr style="margin-bottom: 10px; border-top: 1px solid #AAA;">
<?php

	// Display home page or Traing Resources page below (but without allowing access into projects yet)
	if (isset($_GET['action']) && $_GET['action'] == 'training') {
		include APP_PATH_DOCROOT . "Home/training_resources.php";
	} else {
		include APP_PATH_DOCROOT . "Home/info.php";
	}

	// Put focus on username login field
	print "<script type='text/javascript'>document.getElementById('username').focus();</script>";

	// Output any custom JavaScript
	print $custom_login_js;

	// Since we're showing the login page, destroy all sessions/cookies, just in case they are left over from previous session.
	Session::init();
	$_SESSION = array();
	session_unset();
	session_destroy();
	deletecookie('PHPSESSID');

	$objHtmlPage->PrintFooter();
	exit;
}

// Returns hidden div with X number of random characters. This helps mitigate hackers attempting a BREACH attack.
// ONLY perform this if GZIP is enabled (because BREACH is only effective when HTTP compression is enabled).
function getRandomHiddenText()
{
	// If Gzip enabled, then output it
	if (defined("GZIP_ENABLED") && GZIP_ENABLED) {
		// Set max number of characters
		$maxChars = 128;
		// Get random number between 1 and $maxChars
		$numChars = mt_rand(1, $maxChars);
		// Build random text to place inside hidden div
		$html = generateRandomHash($numChars);
		// Return hidden div
		print RCView::div(array('id'=>'random_text_hidden_div', 'style'=>'display:none;'), $html);
	}
}

// Get currentl full URL
function curPageURL()
{
	$pageURL = (SSL ? 'https' : 'http') . '://';
	if (PORT == "") {
		$pageURL .= SERVER_NAME.$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= SERVER_NAME.":".PORT.$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

// Obtain and return server name (i.e. domain), server port, and if using SSL (boolean)
function getServerNamePortSSL()
{
	global $proxy_hostname, $redcap_base_url, $redcap_version;
	// Trim vars
	$redcap_base_url = trim($redcap_base_url);
	$proxy_hostname  = trim($proxy_hostname);
	if ($redcap_base_url != '')
	{
		## Parse $redcap_base_url to get hostname, ssl, and port
		// Make sure $redcap_base_url ends with a /
		$redcap_base_url .= ((substr($redcap_base_url, -1) != "/") ? "/" : "");
		// Determine if uses SSL
		$ssl = (strtolower(substr($redcap_base_url, 0, 5)) == 'https');
		// Remove http[s]:// from the front and also remove subdirectories on the end to get server_name and port
		$hostStartPos = strpos($redcap_base_url, '://') + 3;
		$hostFirstSlash = strpos($redcap_base_url, '/', $hostStartPos);
		$server_name = substr($redcap_base_url, $hostStartPos, $hostFirstSlash - $hostStartPos);

		$port = '';
		if(strstr($server_name, ':'))
		{
			list ($server_name, $port) = explode(":", $server_name, 2);
		}
		if ($port != '') $port = ":$port";
		// Set relative web path of this webpage
		$page_full = defined("CRON") ? substr($redcap_base_url, $hostFirstSlash) . "redcap_v{$redcap_version}/cron.php" : $_SERVER['PHP_SELF'];
	}
	else
	{
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			$port = ($_SERVER['SERVER_PORT'] != 443) ? ":".$_SERVER['SERVER_PORT'] : "";
			$ssl = true;
		} else {
			$port = ($_SERVER['SERVER_PORT'] != 80)  ? ":".$_SERVER['SERVER_PORT'] : "";
			$ssl = false;
		}
		// Determine web server domain name (and remove any illegal characters)
		$server_name = RCView::escape(str_replace(array("\"", "'", "+"), array("", "", ""), label_decode(getServerName())));
		// Set relative web path of this webpage
		$page_full = $_SERVER['PHP_SELF'];
	}
	// Return values
	return array($server_name, $port, $ssl, $page_full);
}

//Function for rounding up numbers (used for showing file sizes for File fields and prevents file sizes being "0 MB")
function round_up($value, $precision=2)
{
	if ( $value < 0.01 )
	{
		return '0.01';
	}
	else
	{
		return round($value, $precision, PHP_ROUND_HALF_UP);
	}
}


// Function to obtain current event_id from query string, or if does not exist, get first event_id
function getEventId()
{
    $Proj = new Project(PROJECT_ID);
	// If we have event_id in URL
	if (isset($_GET['event_id']) && isset($Proj->eventInfo[$_GET['event_id']])) {
		return $_GET['event_id'];
	// If arm_id is in URL
	} elseif (isset($_GET['arm_id']) && is_numeric($_GET['arm_id'])) {
		return $Proj->getFirstEventIdArmId($_GET['arm_id']);
	// If arm is in URL
	} elseif (isset($_GET['arm']) && is_numeric($_GET['arm'])) {
		return $Proj->getFirstEventIdArm($_GET['arm']);
	// We have nothing so use first event_id in project
	} else {
		return $Proj->firstEventId;
	}
}

// Function to obtain current or lowest Arm number
function getArm()
{
    $arm = "";
	// If we have event_id in URL
	if (isset($_GET['event_id']) && !isset($_GET['arm']) && is_numeric($_GET['event_id'])) {
		$arm = db_result(db_query("select arm_num from redcap_events_arms a, redcap_events_metadata e where a.arm_id = e.arm_id and e.event_id = " .$_GET['event_id']), 0);
	}
	// If we don't have arm in URL
	elseif (!isset($_GET['arm']) || $_GET['arm'] == "" || !is_numeric($_GET['arm'])) {
		$arm = db_result(db_query("select min(arm_num) from redcap_events_arms where project_id = " . PROJECT_ID), 0);
	}
	// If arm is in URL
	elseif (isset($_GET['arm']) && is_numeric($_GET['arm'])) {
		$arm = (int)$_GET['arm'];
	}
	// Just in case arm is blank somehow
	if ($arm == "" || !is_numeric($arm)) {
		$arm = 1;
	}
	return $arm;
}

// Function to obtain current arm_id, or if not current, the arm_id of lowest arm number
function getArmId($arm_id = null)
{
	global $Proj;
	// Set default value
	$armIdValidated = false;
	// Determine arm_id if not provided
	if ($arm_id == null)
	{
		// If we have event_id in URL
		if (isset($_GET['event_id']) && !isset($_GET['arm_id']) && is_numeric($_GET['event_id'])) {
			$sql = "select a.arm_id from redcap_events_arms a, redcap_events_metadata e where a.project_id = " . PROJECT_ID . "
					and a.arm_id = e.arm_id and e.event_id = " . db_escape($_GET['event_id']) . " limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				$arm_id = db_result($q, 0);
				$armIdValidated = true;
			}
		}
		// If arm is in URL
		elseif (isset($_GET['arm_id']) && is_numeric($_GET['arm_id'])) {
			$arm_id = $_GET['arm_id'];
		}
	}
	// Now validate the arm_id we have. If not valid, get the arm_id of lowest arm number
	if (!$armIdValidated) {
		// If arm_id/event_id is not in URL or arm_id is not numeric, then just return the arm_id of lowest arm number
		if (empty($arm_id) || !is_numeric($arm_id)) {
			$arm_id = $Proj->firstArmId;
		}
		// Since we have an arm_id now, validate that it belongs to this project
		else {
			$sql = "select arm_id from redcap_events_arms where project_id = " . PROJECT_ID . " and arm_id = $arm_id";
			if (db_num_rows(db_query($sql)) < 1) {
				$arm_id = $Proj->firstArmId;
			}
		}
	}
	return $arm_id;
}

//Remove certain characters from html strings to use in javascript (assumes will be put inside single quotes)
function js_escape($val, $remove_line_breaks=true)
{
	// Replace MS characters
	replaceMSchars($val);
	// Remove line breaks?
	if ($remove_line_breaks) {
		$repl = array("\r\n", "\r", "\n");
		$orig = array(" ", "", " ");
		$val = str_replace($repl, $orig, $val);
	}
	// Replace
	$repl = array("\t", "'", "  ", "  ");
	$orig = array(" ", "\'", " ", " ");
	$val = str_replace($repl, $orig, $val);
	// If ends with a backslash, then escape it
	if (substr($val, -1) == '\\') $val .= '\\';
	// Return
	return $val;
}

//Remove certain characters from html strings to use in javascript (assumes will be put inside double quotes)
function js_escape2($val, $remove_line_breaks=true)
{
	// Replace MS characters
	replaceMSchars($val);
	// Remove line breaks?
	if ($remove_line_breaks) {
		$repl = array("\r\n", "\r", "\n");
		$orig = array(" ", "", " ");
		$val = str_replace($repl, $orig, $val);
	}
	// Replace
	$repl = array("\t", '"', "  ", "  ");
	$orig = array(" ", '\"', " ", " ");
	$val = str_replace($repl, $orig, $val);
	// If ends with a backslash, then escape it
	if (substr($val, -1) == '\\') $val .= '\\';
	// Return
	return $val;
}

//Function to render the page title/header for individual pages
function renderPageTitle($val = "") {
	if (isset($val) && $val != "") {
		print  "<div class=\"projhdr\">$val</div>";
	}
}

// Function to parse string with fields inside [] brackets and return as array with fields.
// To return ONLY field names and remove all event names and checkbox parentheses, set to ($val, true, true, true).
function getBracketedFields($val, $removeCheckboxBranchingLogicParentheses=true, $returnFieldDotEvent=false, $removeEvent=false)
{
	$these_fields = array();
	$smartVariablesInstance = array('previous-instance', 'current-instance', 'next-instance', 'first-instance', 'last-instance');
	// Collect all fields in brackets
	foreach (explode("|RCSTART|", preg_replace("/(\[[^\[]*\]\[[^\[]*\]|\[[^\[]*\])/", "|RCSTART|$1|RCEND|", $val)) as $this_section)
	{
		$endpos = strpos($this_section, "|RCEND|");
		if ($endpos === false) continue;
		$this_field = substr($this_section, 1, $endpos-2);
		$this_field = str_replace("][", ".", trim($this_field));
		// Remove anything after a ] if we have a lone ]
		if (strpos($this_field, "]")) {
            list ($this_field, $nothing) = explode("]", $this_field, 2);
		}
		// Do not include this field if is blank
		if ($this_field == "" || is_numeric($this_field)) continue;
		// Do not include this field if has unique event name in it and should not be returning unique event name
		if (strpos($this_field, ".") !== false) {
			// Remove instance number and set placeholder for event (if not prepended)
			$parts = explode(".", $this_field);
			$partsLastKey = max(array_keys($parts));
			if (is_numeric($parts[$partsLastKey]) || in_array($parts[$partsLastKey], $smartVariablesInstance)) {
				unset($parts[$partsLastKey]);
			}
			$this_field = implode(".", $parts);
		}
		$dotPos = strpos($this_field, ".");
		$leftParenPos = strpos($this_field, "(");
		if ($dotPos !== false && ($leftParenPos === false || $dotPos < $leftParenPos)) {
			if (!$returnFieldDotEvent) {
				continue;
			} elseif ($removeEvent) {
				list ($this_event, $this_field) = explode(".", $this_field, 2);
			}
		}
		//Insert field into array as key to store as unique
        if (!in_array($this_field, $smartVariablesInstance)) {
			$these_fields[$this_field] = "";
		}
	}
	// Compensate for parentheses in checkbox logic
	$regexFieldCheck = "/^[a-z0-9._\(\):]+$/";
	if ($removeCheckboxBranchingLogicParentheses)
	{
		$regexFieldCheck = "/^[a-z0-9._]+$/";
		foreach ($these_fields as $this_field=>$nothing)
		{
			if (strpos($this_field, "(") !== false)
			{
				// Replace original with one that lacks parentheses
				list ($this_field2, $nothing2) = explode("(", $this_field, 2);
				unset($these_fields[$this_field]);
				$these_fields[$this_field2] = $nothing;
			}
		}
	}
	// Get array of special piping tags that can be used in place of events/fields
	$specialPipingTags = Piping::getSpecialTagsFormatted();
	// Now make sure that each field (or event.field) is the correct formatting (i.e. probably a real field)
	foreach (array_keys($these_fields) as $this_field) {
		$this_event = "";
		if (strpos($this_field, ".") !== false) {
			list ($this_event, $this_field) = explode(".", $this_field);
		}
		if (strpos($this_field, ":") !== false) {
			unset($these_fields[$this_field]);
			list ($this_field, $nothing) = explode(":", $this_field, 2);
			$these_fields[$this_field] = "";
		}
		if (!preg_match($regexFieldCheck, $this_field) && !in_array("[".$this_field."]", $specialPipingTags)) {
			unset($these_fields[$this_field]);
		}
	}

	// Return array of fields
	return $these_fields;
}


/*
 ** Give null value if equals "" (used inside queries)
 */
function checkNull($value, $replaceMSchars=true) {
	if ($value === "" || $value === null || $value === false) {
		return "NULL";
	} else {
		return "'" . db_escape($value, $replaceMSchars) . "'";
	}
}


// DETERMINE IF SERVER IS A VANDERBILT SERVER
function isVanderbilt()
{
	return (strpos($_SERVER['SERVER_NAME'], "vanderbilt.edu") !== false);
}


/**
 * LINK TO RETURN TO PREVIOUS PAGE
 * $val corresponds to PAGE constant (i.e. relative URL from REDCap's webroot)
 */
function renderPrevPageLink($val) {
	global $lang;
	if (isset($_GET['ref']) || $val != null) {
		$val = ($val == null) ? $_GET['ref'] : $val;
		if ($val == "") return;
		print  "<p style='margin:0;padding:10px;'>
					<img src='" . APP_PATH_IMAGES . "arrow_skip_180.png'>
					<a href='" . APP_PATH_WEBROOT . $val . (defined("PROJECT_ID") ? ((strpos($val, "?") === false ? "?" : "&") . "pid=" . PROJECT_ID) : "") . "'
						style='color:#2E87D2;font-weight:bold;'>{$lang['config_functions_40']}</a>
				</p>";
	}
}

/**
 * BUTTON TO RETURN TO PREVIOUS PAGE
 * $val corresponds to PAGE constant (i.e. relative URL from REDCap's webroot)
 * If $val is not supplied, will use "ref" in query string.
 */
function renderPrevPageBtn($val=null,$label=null,$outputToPage=true,$btnClass='jqbutton') {
	global $lang;
	$button = "";
	if (isset($_GET['ref']) || $val != null)
	{
		$val = ($val == null) ? htmlspecialchars(strip_tags(label_decode(urldecode($_GET['ref']))), ENT_QUOTES) : $val;
		if ($val == "") return;
		// Set label
		$label = ($label == null) ? $lang['config_functions_40'] : $label;
		$button =  "<button class='$btnClass' style='' onclick=\"window.location.href='" .
						APP_PATH_WEBROOT . $val . (defined("PROJECT_ID") ? ((strpos($val, "?") === false ? "?" : "&") . "pid=" . PROJECT_ID) : "") . "';\">
						<i class=\"fas fa-chevron-circle-left\"></i> $label
					</button>";
	}
	// Render or return
	if ($outputToPage) {
		print $button;
	} else {
		return $button;
	}
}



/**
 * Run single-field query and return comma delimited set of values (to be used inside other query for better performance than using subqueries)
 */
function pre_query($sql, $conn=null)
{
	if (trim($sql) == "" || $sql == null) return "''";
	$sql = html_entity_decode($sql, ENT_QUOTES);
	if ($conn == null) {
		$q = db_query($sql);
	} else {
		$q = db_query($sql, $conn);
	}
	$val = "";
	if ($q) {
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_array($q)) {
				$val .= "'" . db_escape($row[0]) . "', ";
			}
			$val = substr($val, 0, -2);
		}
	}
	return ($val == "") ? "''" : $val;
}

/**
 * Display query if query fails
 */
function queryFail($sql) {
	global $lang;
	exit("<p><b>{$lang['config_functions_41']}</b><br>$sql</p>");
}

/**
 * Returns first event_id of a project (if specify arm number, returns first event for that arm)
 */
function getSingleEvent($this_project_id, $arm_num = NULL) {
	if (!is_numeric($this_project_id)) return false;
	$sql = "select m.event_id from redcap_events_metadata m, redcap_events_arms a where a.arm_id = m.arm_id
			and a.project_id = $this_project_id";
	if (is_numeric($arm_num)) $sql .= " and a.arm_num = $arm_num";
	$sql .= " order by a.arm_num, m.day_offset, m.descrip limit 1";
	return db_result(db_query($sql), 0);
}

// Essentially just runs filter_tags(label_decode()), which is best if we know we're going to do filter_tags().
function decode_filter_tags($val)
{
	return filter_tags(label_decode($val, false));
}

// Replace &nbsp; with real space
function replaceNBSP($val)
{
	return str_replace(array("&amp;nbsp;", "&nbsp;"), array(" ", " "), $val);
}

// Decode limited set of html special chars rather than using html_entity_decode
function label_decode($val, $insertSpaceLessThanNumber=true)
{
	// Static arrays used for character replacing in labels/notes
	// (user str_replace instead of html_entity_decode because users may use HTML char codes in text for foreign characters)
	// $orig_chars = array("&amp;","&#38;","&#34;","&quot;","&#39;","&#039;","&#60;","&lt;","&#62;","&gt;");
	// $repl_chars = array("&"    ,"&"    ,"\""   ,"\""    ,"'"    ,"'"     ,"<"    ,"<"   ,">"    ,">"   );
	// $val = str_replace($orig_chars, $repl_chars, $val);

	// Set temporary replacement for &nbsp; HTML character code so that html_entity_decode() doesn't mangle it
	$nbsp_replacement = '|*|RC_NBSP|*|';

	// Replace &nbsp; characters
	$val = str_replace(array("&amp;nbsp;", "&nbsp;"), array($nbsp_replacement, $nbsp_replacement), $val);

	// Unescape any HTML
	$val = html_entity_decode($val, ENT_QUOTES, 'UTF-8');

	// Re-replace &nbsp; characters
	$val = str_replace($nbsp_replacement, "&nbsp;", $val);

	// If < character is followed by a number, dollar sign, or equals sign, which PHP will strip out using striptags, add space after < to prevent string truncation.
	if ($insertSpaceLessThanNumber && strpos($val, "<") !== false) {
		if (strpos($val, "<=") !== false) {
			$val = str_replace("<=", "< =", $val);
		}
        if (strpos($val, "<$") !== false) {
            $val = str_replace("<$", "< $", $val);
        }
		$val = preg_replace("/(<)(\d)/", "< $2", $val);
	}

	// Return decoded value
	return $val;
}

// Improved replacement for strip_tags, which can remove <= or <2 from strings.
function strip_tags2($val)
{
	return filter_tags($val, false, false);
}

/**
 * FOR A STRING, CONVERT ALL LINE BREAKS TO SPACES, THEN REPLACE MULTIPLE SPACES WITH SINGLE SPACES, THEN TRIM
 */
function remBr($val) {
	// Replace line breaks with spaces
	$br_orig = array("\r\n", "\r", "\n");
	$br_repl = array(" ", " ", " ");
	$val = str_replace($br_orig, $br_repl, $val);
	// Replace multiple spaces with single spaces
	$val = preg_replace('/\s+/', ' ', $val);
	// Trim and return
	return trim($val);
}


/**
 * Print an array (for debugging purposes)
 */
function print_array($array) {
	print "<br><pre>\n";print_r($array);print "\n</pre>\n";
}


/**
 * Print an array via var_dump (for debugging purposes)
 */
function print_dump($array) {
	print "<br><pre>\n";var_dump($array);print "\n</pre>\n";
}


/**
 * DISPLAY ERROR MESSAGE IF CURL MODULE NOT LOADED IN PHP
 */
function curlNotLoadedMsg() {
	global $lang;
	print  "<div class='red'>
				<img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b><br>
				{$lang['config_functions_42']}
				<a href='http://us.php.net/manual/en/book.curl.php' target='_blank' style='text-decoration:underline;'>{$lang['config_functions_43']}</a>{$lang['period']}
			</div>";
}

/**
 * CALCULATES SIZE OF WEB SERVER DIRECTORY (since disk_total_space() function is not always reliable)
 */
function dir_size($dir) {
	$retval = 0;
	$dirhandle = opendir($dir);
	while ($file = readdir($dirhandle)) {
		if ($file != "." && $file != "..") {
			if (is_dir($dir."/".$file)) {
				$retval = $retval + dir_size($dir."/".$file);
			} else {
				$retval = $retval + filesize($dir."/".$file);
			}
		}
	}
	closedir($dirhandle);
	return $retval;
}


/**
 * CLEAN BRANCHING LOGIC OR CALC FIELD EQUATION OF ANY ERRORS IN FIELD NAME SYNTAX AND RETURN CLEANED STRING
 */
function cleanBranchingOrCalc($val) {
	return preg_replace_callback("/(\[)([^\[]*)(\])/", "branchingCleanerCallback", $val);
}
// Callback function used when cleaning branching logic
function branchingCleanerCallback($matches) {
	return "[" . preg_replace("/[^a-z0-9:A-Z\(\)_-]/", "", str_replace(" ", "", $matches[0])) . "]";
}


/**
 * PARSE THE ELEMENT_ENUM COLUMN FROM METADATA TABLE AND RETURN AS ARRAY
 * (WITH CODED VALUES AS KEY AND LABELS AS ELEMENTS)
 */
function parseEnum($select_choices = "")
{
	if (trim($select_choices) == "") return array();
	$array_to_fill = array();
	// Catch any line breaks (mistakenly saved instead of \n literal string)
	$select_choices = str_replace("\n", "\\n", trim($select_choices));
	$select_array = explode("\\n", $select_choices);
	// Loop through each choice
	foreach ($select_array as $key=>$value) {
		if (strpos($value,",") !== false) {
			$pos = strpos($value, ",");
			$this_value = trim(substr($value,0,$pos));
			$this_text = trim(substr($value,$pos+1));
			// If a comma was previously replaced with its corresponding HTML character code, re-add it back (especially for SQL field types)
			$this_value = str_replace("&#44;", ",", $this_value);
			$this_text = str_replace("&#44;", ",", $this_text);
		} else {
			// If a comma was previously replaced with its corresponding HTML character code, re-add it back (especially for SQL field types)
			$value = str_replace("&#44;", ",", $value);
			$this_value = $this_text = trim($value);
		}
		// If a choice is duplicated, then merge all labels together for that coded choice
		$this_value = $this_value."";
		if (isset($array_to_fill[$this_value])) {
			$array_to_fill[$this_value] .= ", $this_text";
		} else {
			$array_to_fill[$this_value] = $this_text;
		}
	}
	return $array_to_fill;
}

/**
 * PRINT VALUES OF AN EMAIL (OFTEN DISPLAYED WHEN THERE IS ERROR SENDING EMAIL)
 */
function printEmail($to, $from, $subject, $body) {
	?>
	<p>
		<b>To:</b> <?php echo $to ?><br>
		<b>From:</b> <?php echo $from ?><br>
		<b>Subject:</b> <?php echo $subject ?><br>
		<b>Message:</b><br><?php echo $body ?>
	</p>
	<?php
}

/**
 * DETERMINE MAXIMUM SIZE OF FILES THAT CAN BE UPLOADED TO WEB SERVER (IN MB)
 */
function maxUploadSize() {
	// Get server max (i.e. the lowest of two different server values)
	$max_filesize = (ini_get('upload_max_filesize') != "") ? ini_get('upload_max_filesize') : '1M';
	$max_postsize = (ini_get('post_max_size') 		!= "") ? ini_get('post_max_size') 	    : '1M';
	// If ends with G instead of M, then convert to M format
	if (stripos($max_postsize, 'g')) $max_postsize = preg_replace("/[^0-9]/", "", $max_filesize)*1024 . "M";
	if (stripos($max_filesize, 'g')) $max_filesize = preg_replace("/[^0-9]/", "", $max_filesize)*1024 . "M";
	$max_filesize = preg_replace("/[^0-9]/", "", $max_filesize);
	$max_postsize = preg_replace("/[^0-9]/", "", $max_postsize);
	// Return the smallest of the two
	return (($max_filesize > $max_postsize) ? $max_postsize : $max_filesize);
}
function maxUploadSizeFileRespository() {
	global $file_repository_upload_max;
	$file_repository_upload_max = trim($file_repository_upload_max);
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($file_repository_upload_max != "" && is_numeric($file_repository_upload_max) && $file_repository_upload_max < $server_max) {
		return $file_repository_upload_max;
	} else {
		return $server_max;
	}
}
function maxUploadSizeEdoc() {
	global $edoc_upload_max;
	$edoc_upload_max = trim($edoc_upload_max);
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($edoc_upload_max != "" && is_numeric($edoc_upload_max) && $edoc_upload_max < $server_max) {
		return $edoc_upload_max;
	} else {
		return $server_max;
	}
}
function maxUploadSizeAttachment() {
	global $file_attachment_upload_max;
	$file_attachment_upload_max = trim($file_attachment_upload_max);
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($file_attachment_upload_max != "" && is_numeric($file_attachment_upload_max) && $file_attachment_upload_max < $server_max) {
		return $file_attachment_upload_max;
	} else {
		return $server_max;
	}
}
function maxUploadSizeSendit() {
	global $sendit_upload_max;
	$sendit_upload_max = trim($sendit_upload_max);
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($sendit_upload_max != "" && is_numeric($sendit_upload_max) && $sendit_upload_max < $server_max) {
		return $sendit_upload_max;
	} else {
		return $server_max;
	}
}

// Retrieve list of all files and folders within a server directory, sorted alphabetically (output as array)
function getDirFiles($dir) {
	if (is_dir($dir)) {
		$dh = opendir($dir);
		$files = array();
		$i = 0;
		while (false != ($filename = readdir($dh))) {
			if ($filename != "." && $filename != "..") {
				// Make sure we do not exceed 80% memory usage. If so, return false to prevent hitting memory limit.
				if (($i % 1000) == 0) {
					if (memory_get_usage()/1048576 > System::getMemoryLimit()*0.8) return false;
				}
				$files[] = $filename;
				$i++;
			}
		}
		sort($files);
		return $files;
	} else {
		return false;
	}
}

// Output the values from a SQL field type query as an enum string
function getSqlFieldEnum($element_enum, $project_id=null, $record=null, $event_id=null, $instance=null, 
						 $user=null, $participant_id=null, $form=null)
{
	//If one field in query, then show field as both coded value and displayed text.
	//If two fields in query, then show first as coded value and second as displayed text.
	if (strtolower(substr(trim($element_enum), 0, 7)) == "select ")
	{
		$element_enum = html_entity_decode($element_enum, ENT_QUOTES);
		// Replace Smart Variables
        if ($project_id === null && defined("PROJECT_ID")) $project_id = PROJECT_ID;
		$element_enum = Piping::pipeSpecialTags($element_enum, $project_id, $record, $event_id, $instance, $user, true, $participant_id, $form);
		// Execute query
		$rs_temp1_sql = db_query($element_enum);
		if (!$rs_temp1_sql) return "";
		$string_record_select1 = "";
		while ($row = db_fetch_array($rs_temp1_sql, MYSQLI_NUM))
		{
			$string_record_select1 .= str_replace(",", "&#44;", $row[0]);
			if (!isset($row[1])) {
				$string_record_select1 .= " \\n ";
			} else {
				$string_record_select1 .= ", " . str_replace(",", "&#44;", $row[1]) . " \\n ";
			}
		}
		return substr($string_record_select1, 0, -4);
	}
	return "";
}

// Simple encryption
function encrypt($data, $custom_encryption_key=null, $use_mcrypt=false)
{
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$encryption_key = $custom_encryption_key === null ? $GLOBALS['salt'] : $custom_encryption_key;
	try {
		// Return the data
		if ($use_mcrypt) {
			return encrypt_mcrypt($data, $encryption_key);
		} elseif (openssl_loaded()) {
			return Cryptor::Encrypt($data, $encryption_key);
		} else {
			return false;
		}
	} catch (Exception $e) {
		return false;
	}
}

// Simple decryption
function decrypt($data, $custom_encryption_key=null, $use_mcrypt=false)
{
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$encryption_key = $custom_encryption_key === null ? $GLOBALS['salt'] : $custom_encryption_key;
	try {
		// Return the data
		if ($use_mcrypt) {
			return decrypt_mcrypt($data, $encryption_key);
		} elseif (openssl_loaded()) {
			return Cryptor::Decrypt($data, $encryption_key);
		} else {
			return false;
		}
	} catch (Exception $e) {
		return false;
	}
}

// Simple encryption using Mcrypt extension (no longer supported in PHP 7.1 and higher)
function encrypt_mcrypt($data, $custom_encryption_key=null)
{
	// $salt from db connection file
	global $salt;
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$this_encryption_key = $this_encryption_key_orig = ($custom_encryption_key === null) ? $salt : $custom_encryption_key;
	// Key size needed
	$ideal_key_size = mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	// If salt is too short, keep appending it to itself till it is long enough.
	while (strlen($this_encryption_key) < $ideal_key_size) {
		$this_encryption_key .= $this_encryption_key_orig;
	}
	// If salt is longer than 32 characters, then truncate it to prevent issues
	if (strlen($this_encryption_key) > $ideal_key_size) $this_encryption_key = substr($this_encryption_key, 0, $ideal_key_size);
	// Convert the key to binary
	$this_encryption_key = @pack('H*', $this_encryption_key); // non-hex data will be null
	// Define an encryption/decryption variable beforehand
	defined("MCRYPT_IV") or define("MCRYPT_IV", mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
	// Encrypt and return
	return rtrim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this_encryption_key, $data, MCRYPT_MODE_ECB, MCRYPT_IV)),"\0");
}

// Simple decryption using Mcrypt extension (no longer supported in PHP 7.1 and higher)
function decrypt_mcrypt($encrypted_data, $custom_encryption_key=null)
{
	// $salt from db connection file
	global $salt;
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$this_encryption_key = $this_encryption_key_orig = ($custom_encryption_key === null) ? $salt : $custom_encryption_key;
	// Key size needed
	$ideal_key_size = mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	// If salt is too short, keep appending it to itself till it is long enough.
	while (strlen($this_encryption_key) < $ideal_key_size) {
		$this_encryption_key .= $this_encryption_key_orig;
	}
	// If salt is longer than 32 characters, then truncate it to prevent issues
	if (strlen($this_encryption_key) > $ideal_key_size) $this_encryption_key = substr($this_encryption_key, 0, $ideal_key_size);
	// Convert the key to binary
	$this_encryption_key = @pack('H*', $this_encryption_key);  // non-hex data will be null
	// Define an encryption/decryption variable beforehand
	defined("MCRYPT_IV") or define("MCRYPT_IV", mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
	// Decrypt and return
	return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this_encryption_key, base64_decode($encrypted_data), MCRYPT_MODE_ECB, MCRYPT_IV),"\0");
}

// Function for checking if mcrypt PHP extension is loaded
function openssl_loaded($show_error=false) {
	global $lang;
    if (!function_exists('openssl_encrypt')) {
		if ($show_error) {
			if (empty($lang)) $lang = Language::callLanguageFile('English');
			print 	RCView::div(array('class'=>'red'),
						RCView::b($lang['global_01'] . $lang['colon']) . " " . $lang['global_140']
					);
			exit;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

// Checks if username and password are valid without disrupting existing REDCap authentication session
function fakeUserLoginForm() { return; }
function checkUserPassword($username, $password, $authSessionName = "login_test")
{
	// Start the session
	Session::init();

	// Set user/pass as Post values so they get processed correctly
	$_POST['password'] = $password;
	$_POST['username'] = $username;

	// Get current session_id, which will get inevitably changed if auth is successful
	$old_session_id = substr(session_id(), 0, 32);

	// Defaults
	$authenticated = false;	

	// Build and return the DSN array used for Table-based, LDAP-Table, and LDAP authentication
	$dsn = Authentication::buildDsnArray();

	//if ldap and table authentication Loop through the available servers & authentication methods
	foreach ($dsn as $key=>$dsnvalue)
	{
		if (isset($a)) unset($a);

        // shibboleth_table does not load this library when attempting to esign a form
        // System::init() does load this, but for reasons unknown, it is forgotten
        if (!class_exists('Auth')) require_once dirname(dirname(__FILE__)) . '/Libraries/PEAR/Auth.php';

		$a = new Auth($dsnvalue['type'], $dsnvalue['dsnstuff'], "fakeUserLoginForm");
		$a->setSessionName($authSessionName);
		// Table-based authentication hack during login to bypass Pear DB because of SSL compatibility reasons with "MySQL over SSL"
		if ($dsnvalue['type'] == 'DB' && Authentication::verifyTableUsernamePassword($_POST['username'], $_POST['password']))
		{
			$a->setAuth($_POST['username']);
		}
		// Start auth
		$a->start();
		if ($a->getAuth()) {
			$authenticated = true;
		}
	}

	// Now that we're done, remove this part of the session to prevent conflict with REDCap user sessioning
	unset($_SESSION['_auth_'.$authSessionName]);

	// Because the session_id inevitably changes with this new auth session, change the session_id in log_view table
	// for all past page views during this session in order to maintain consistency of having one session_id per session.
	$new_session_id = substr(session_id(), 0, 32);
	if ($old_session_id != $new_session_id && !defined("NOAUTH"))
	{
		// Only check within past 24 hours (to reduce query time)
		$oneDayAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
		$sql = "update redcap_log_view set session_id = '$new_session_id' where user = '".USERID."'
				and session_id = '$old_session_id' and ts > '$oneDayAgo'";
		db_query($sql);
	}

	// Return value as true/false
	return $authenticated;
}


// Obtain web path to REDCap version folder
function getVersionFolderWebPath()
{
	global $redcap_version;

	// Parse through URL to find version folder path
	$found_version_folder = false;
	$url_array = array();
	foreach (array_reverse(explode("/", PAGE_FULL)) as $this_part)
	{
		if ($this_part == "redcap_v" . $redcap_version)
		{
			$found_version_folder = true;
		}
		if ($found_version_folder)
		{
			$url_array[] = $this_part;
		}
	}
	// If ABOVE the version folder
	if (empty($url_array))
	{
		// First, make special exception if this is the survey page (i.e. .../[redcap]/surveys/index.php)
		$surveyPage  = "/surveys/index.php";
		$apiHelpPage = "/api/help/index.php";
		if (substr(PAGE_FULL, -1*strlen($surveyPage)) == $surveyPage)
		{
			return ((strlen(dirname(dirname(PAGE_FULL))) <= 1) ? "" : dirname(dirname(PAGE_FULL))) . "/redcap_v" . $redcap_version . "/";
		}
		// Check if this is the API Help file
		elseif (substr(PAGE_FULL, -1*strlen($apiHelpPage)) == $apiHelpPage)
		{
			return ((strlen(dirname(dirname(dirname(PAGE_FULL)))) <= 1) ? "" : dirname(dirname(dirname(PAGE_FULL)))) . "/redcap_v" . $redcap_version . "/";
		}
		// If user is above the version folder (i.e. /redcap/index.php, /redcap/plugins/example.php)
		else
		{
			// If 'redcap' folder is not seen in URL, then the version folder is in the server web root
			if (strlen(dirname(PAGE_FULL)) <= 1) {
				return "/redcap_v" . $redcap_version . "/";
			// This is the index.php page above the version folder
			} elseif (defined('PAGE')) {
				return dirname(PAGE_FULL) . "/redcap_v" . $redcap_version . "/";
			// Since the version folder is not one or two directories above, find it manually using other methods
			} else {
				// Make sure allow_url_fopen is enabled, else we can't properly find the version folder
				if (ini_get('allow_url_fopen') != '1')
				{
					exit('<p style="max-width:800px;"><b>Your web server does NOT have the PHP setting "allow_url_fopen" enabled.</b><br>
						REDCap cannot properly process this page because "allow_url_fopen" is not enabled.
						To enable "allow_url_fopen", simply open your web server\'s PHP.INI file for editing and change the value of "allow_url_fopen" to
						<b>On</b>. Then reboot your web server and reload this page.</p>');
				}
				// Try to find the file database.php in every directory above the current directory until it's found
				$revUrlArray = array_reverse(explode("/", PAGE_FULL));
				// Remove unneeded array elements
				array_pop($revUrlArray);
				array_shift($revUrlArray);
				// Loop through the array till we find the location of the version folder to return
				foreach ($revUrlArray as $key=>$urlPiece)
				{
					// Set subfolder path
					$subfolderPath = implode("/", array_reverse($revUrlArray));
					// Set the possible path of where to search for database.php
					$dbWebPath = (SSL ? "https" : "http") . "://" . SERVER_NAME . "$port/$subfolderPath/database.php";
					// Try to call database.php to see if it exists
					$dbWebPathContents = file_get_contents($dbWebPath);
					// If we found database.php, then return the proper path of the version folder
					if ($dbWebPathContents !== false) {
						return "/$subfolderPath/redcap_v" . $redcap_version . "/";
					}
					// Unset this array element so it does not get reused in the next loop
					unset($revUrlArray[$key]);
				}
				// Version folder was NOT found
				return "/redcap_v" . $redcap_version . "/";
			}
		}
	}
	// If BELOW the version folder
	else
	{
		return implode("/", array_reverse($url_array)) . "/";
	}
}

// Render ExtJS-like panel
function renderPanel($title, $html, $id="", $collapsed=false)
{
	$id = ($id == "") ? "" : " id=\"$id\"";
	$collapsed_style = $collapsed ? ' style="display:none;"' : '';
	return '<div class="x-panel"'.$id.'>'
		 . ((trim($title) == '') ? '' : '<div class="x-panel-header x-panel-header-leftmenu">' . $title .'</div>')
		 . '<div class="x-panel-bwrap"'.$collapsed_style.'><div class="x-panel-body"><div class="menubox">' . $html . '</div></div></div></div>';
}

// Render ExtJS-like grid/table
function renderGrid($id, $title, $width_px='auto', $height_px='auto', $col_widths_headers=array(), &$row_data=array(),
					$show_headers=true, $enable_header_sort=true, $outputToPage=true, $initiallyHide=false)
{
	## SETTINGS
	// $col_widths_headers = array(  array($width_px, $header_text, $alignment, $data_type), ... );
	// $data_type = 'string','int','date'
	// $row_data = array(  array($col1, $col2, ...), ... );

	// Set dimensions and settings
	$width = is_numeric($width_px) ? "width: " . $width_px . "px;" : "width: 100%;";
	$height = ($height_px == 'auto') ? "" : "height: " . $height_px . "px; overflow-y: auto;";
	if (trim($id) == "") {
		$id = substr(sha1(rand()), 0, 8);
	}
	$table_id_js = "table-$id";
	$table_id = "id=\"$table_id_js\"";
	$id = "id=\"$id\"";

	// Check column values
	$row_settings = array();
	foreach ($col_widths_headers as $this_key=>$this_col)
	{
		$this_width  = is_numeric($this_col[0]) ? ($this_col[0]+10) . "px" : "100%";
		$this_header = $this_col[1];
		$this_align  = isset($this_col[2]) ? $this_col[2] : "left";
		$this_type   = isset($this_col[3]) ? $this_col[3] : "string";
		// Re-assign checked values
		$col_widths_headers[$this_key] = array($this_width, $this_header, $this_align, $this_type);
		// Add width and alignment to other array (used when looping through each row)
		$row_settings[] = array('width'=>$this_width, 'align'=>$this_align);
	}

	// Render grid
	$id2 = preg_replace("/^id\s*=\s*[\"\']?/", "", $id);
	$id2 = preg_replace("/[\"\']$/", "", $id2);
	$id2 = "#table-".$id2;
	$grid = '
	<div class="flexigrid" ' . $id . ' style="' . $width . $height .'">
		<div class="mDiv">
			<div class="ftitle" ' . ((trim($title) != "") ? "" : 'style="display:none;"') . '>'.$title.'</div>
		</div>
		<div class="hDiv" ' . ($show_headers ? "" : 'style="display:none;"') . '>
			<div class="hDivBox">
				<table ' . ($initiallyHide ? 'style="display:none;"' : '') . '>
					<tr>';
	foreach ($col_widths_headers as $col_key=>$this_col)
	{
		$grid .= 	   '<th' . ($enable_header_sort ? " onclick=\"SortTable('$table_id_js',$col_key,'{$this_col[3]}');\"" : " style=\"cursor:initial;\"") . ($this_col[2] == 'left' ? '' : ' align="'.$this_col[2].'"') . '>
							<div style="' . ($this_col[2] == 'left' ? '' : 'text-align:'.$this_col[2].';') . 'width:' . $this_col[0] . ';">
								' . $this_col[1] . '
							</div>
						</th>';
	}
	$grid .= 	   '</tr>
				</table>
			</div>
		</div>
		<div class="bDiv">
			<table ' . $table_id . ' ' . ($initiallyHide ? 'style="display:none;"' : '') . '>';
	foreach ($row_data as $row_key=>$this_row)
	{
		$grid .= '<tr' . ($row_key%2==0 ? '' : ' class="erow"') . '>';
		foreach ($this_row as $col_key=>$this_col)
		{
			$grid .= '<td' . ((isset($row_settings[$col_key]) && $row_settings[$col_key]['align'] == 'left') ? '' : ' align="' . (isset($row_settings[$col_key]) ? $row_settings[$col_key]['align'] : '') . '"') . '>
						<div ';
			if (isset($row_settings[$col_key]) && $row_settings[$col_key]['align'] == 'center') {
				$grid .= 'class="fc" ';
			} elseif (isset($row_settings[$col_key]) && $row_settings[$col_key]['align'] == 'right') {
				$grid .= 'class="fr" ';
			}
			$grid .= 'style="width:' . (isset($row_settings[$col_key]) ? $row_settings[$col_key]['width'] : '') . ';">' . $this_col . '</div>
					  </td>';
		}
		$grid .= '</tr>';
		// Delete last row to clear up memory as we go
		unset($row_data[$row_key]);
	}
	$grid .= '</table>
		</div>
	</div>
	';

	// Render grid (or return as html string)
	if ($outputToPage) {
		print $grid;
		unset($grid);
	} else {
		return $grid;
	}
}

// Returns HTML table from an SQL query (can include title to display)
function queryToTable($sql,$title="",$outputToPage=false,$tableWidth=null)
{
	global $lang;
	$QQuery = db_query($sql);
	$num_rows = db_num_rows($QQuery);
	$num_cols = db_num_fields($QQuery);
	$failedText = ($QQuery ? "" : "<span style='color:red;'>ERROR - Query failed!</span>");
	$tableWidth = (is_numeric($tableWidth) && $tableWidth > 0) ? "width:{$tableWidth}px;" : "";

	$html_string = "<table class='dt2' style='font-family:Verdana;font-size:11px;$tableWidth'>
						<tr class='grp2'><td colspan='$num_cols'>
							<div style='color:#800000;font-size:14px;max-width:700px;'>$title</div>
							<div style='font-size:11px;padding:12px 0 3px;'>
								<b>{$lang['custom_reports_02']}&nbsp; <span style='font-size:13px;color:#800000'>$num_rows</span></b>
								$failedText
							</div>
						</td></tr>
						<tr class='hdr2' style='white-space:normal;'>";

	if ($num_rows > 0) {

		// Display column names as table headers
		for ($i = 0; $i < $num_cols; $i++) {

			$this_fieldname = db_field_name($QQuery,$i);
			//Display the "fieldname"
			$html_string .= "<td style='padding:5px;'>$this_fieldname</td>";
		}
		$html_string .= "</tr>";

		// Display each table row
		$j = 1;
		while ($row = db_fetch_array($QQuery)) {
			$class = ($j%2==1) ? "odd" : "even";
			$html_string .= "<tr class='$class notranslate'>";
			for ($i = 0; $i < $num_cols; $i++)
			{
				// Escape the value in case of harmful tags
				$this_value = htmlspecialchars(html_entity_decode($row[$i], ENT_QUOTES), ENT_QUOTES);
				$html_string .= "<td style='padding:3px;border-top:1px solid #CCCCCC;font-size:11px;'>$this_value</td>";
			}
			$html_string .= "</tr>";
			$j++;
		}

		$html_string .= "</table>";

	} else {

		for ($i = 0; $i < $num_cols; $i++) {

			$this_fieldname = db_field_name($QQuery,$i);

			//Display the Label and Field name
			$html_string .= "<td style='padding:5px;'>$this_fieldname</td>";
		}

		$html_string .= "</tr><tr><td colspan='$num_cols' style='font-weight:bold;padding:10px;color:#800000;'>{$lang['custom_reports_06']}</td></tr></table>";

	}

	if ($outputToPage) {
		// Output table to page
		print $html_string;
	} else {
		// Return table as HTML
		return $html_string;
	}
}

// Return the SQL query results in CSV format
function queryToCsv($query)
{
	// Execute query
	$result = db_query($query);
	if (!$result) return false;
	$num_fields = db_num_fields($result);
	// Set headers
	$headers = array();
	for ($i = 0; $i < $num_fields; $i++) {
		$headers[] = db_field_name($result, $i);
	}
	// Begin writing file from query result
	$fp = fopen('php://memory', "x+");
	if ($fp && $result) {
		fputcsv($fp, $headers);
		while ($row = db_fetch_array($result, MYSQLI_NUM)) {
			fputcsv($fp, $row);
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		return stream_get_contents($fp);
	}
	return false;
}

// Converts html line breaks to php line breaks (opposite of PHP's nl2br() function
function br2nl($string){
	return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
}

// Converts NL \n into CRNL \r\n (and if already has CRNL, then doesn't change it)
function nl2crnl($string){
	return str_replace(array("\r\n", "\n"), array("\n", "\r\n"), $string);
}

// Transform a string to camel case formatting (i.e. remove all non-alpha-numerics and spaces) and truncate it
function camelCase($string, $leave_spaces=false, $char_limit=30)
{
	$string = ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", $string));
	if (!$leave_spaces) {
		$string = str_replace(" ", "", $string);
	}
	return substr($string, 0, $char_limit);
}

// Transform a string to snake case from camel case formatting
function fromCamelCase($input) 
{
	preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
	$ret = $matches[0];
	foreach ($ret as &$match) {
		$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
	}
	return implode('_', $ret);
}

// Initialize auto-logout popup timer and logout reset timer listener
function initAutoLogout()
{
	global $auth_meth, $autologout_timer;
	// Only set auto-logout if not using "none" authentication and if timer value is set
	if ($auth_meth != "none" && $autologout_timer != "0" && is_numeric($autologout_timer) && !defined("NOAUTH"))
	{
		print "
		<script type='text/javascript'>
		$(function(){
			initAutoLogout(".Authentication::AUTO_LOGOUT_RESET_TIME.",$autologout_timer);
		});
		</script>";
	}
}

// Filter potentially harmful html tags
function filter_tags($val, $preserve_allowed_tags=true, $filter_javascript=true)
{
    $val = html_entity_decode($val, ENT_QUOTES);
	// Prevent strip_tags() from removing non-tags that look like tags (e.g., "<4" and "<>")
	$hasLessThan = (strpos($val, '<') !== false);
	if ($hasLessThan) {
		// Remove any HTML comments
		if (strpos($val, '<!--') !== false) {
			$val = preg_replace("/<!--.*?-->/ms", "", $val);
		}

		// Do quick replace and re-replace of <> because strip_tags will remove it
		$not_equal_replacemenet = "||--RC_NOT_EQUAL_TO--||";
		$val = str_replace("<>", $not_equal_replacemenet, $val);

		// Do quick replace and re-replace of <> because strip_tags will remove it
		$ls_equal_replacement = "||--RC_LS_EQUAL_TO--||";
		$val = str_replace("<=", $ls_equal_replacement, $val);

		// Do quick replace and re-replace of <! because strip_tags will remove it
		$ls_exclaim_replacement = "||--RC_LS_EXCLAIM--||";
		$val = str_replace("<!", $ls_exclaim_replacement, $val);

		## Do replace of legitimate tags so we can weed out the ones that *look* legitimate to browsers (e.g., "<this is not real>")
		// Set replacement strings for < and >
		$lt_realtag_replacement = "||--RC_REALTAG_LT--||";
		$gt_realtag_replacement = "||--RC_REALTAG_GT--||";
		$lt_notrealtag_replacement = "||--RC_NOTREALTAG_LT--||";
		// Build regex to replace the "<" part of all allowable HTML tags
		// $regex_realtag = "\<".implode("\>|\<", explode("><", substr(ALLOWED_TAGS, 1, -1)))."\>";
		$regex_realtag = implode("|", explode("><", substr(ALLOWED_TAGS, 1, -1)));
		$val = preg_replace("/(\<)(\/?)($regex_realtag)(\s?)([^\>]*)(\/?)(\>)/i", $lt_realtag_replacement."$2$3$4$5$6".$gt_realtag_replacement, $val);
		// Any remaining "<" must not be valid tags, so put spaces directly after them
		$val = preg_replace("/(<)([^0-9])(\s?)/", $lt_notrealtag_replacement."$2$3", $val);

		// Do quick replace and re-replace of <# because strip_tags will remove it
		$ls_num_replacement = " ||--RC_LS_NUM--||";
		$val = preg_replace("/(<)(\d)/", "$1".$ls_num_replacement."$2", $val);

		// Due to confliction of <i> in regex mistakenly replacing for <iframe>, do this manually here to remove it
		$val = str_ireplace($lt_realtag_replacement."iframe", "<iframe", $val);

		// Due to confliction of ?? in regex mistakenly replacing for <embed>, do this manually here to remove it
		$val = str_ireplace($lt_realtag_replacement."embed", "<embed", $val);
	}
	// Remove all but the allowed tags
	if ($preserve_allowed_tags) {
		$val = strip_tags($val, ALLOWED_TAGS);
	} else {
		$val = strip_tags($val);
	}
	// Re-replace <>, <# and any injected javascript
	if ($hasLessThan) {
		// Re-add "<" for legitimate and legitimate-looking tags
		$val = str_replace($lt_realtag_replacement, "<", $val);
		$val = str_replace($gt_realtag_replacement, ">", $val);
		$val = str_replace(array($lt_notrealtag_replacement, "<  "), array("< ", "< "), $val); // Add space after this one because browsers *might* interpret it as a tag and make it invisible
		// Re-replace <>
		$val = str_replace($not_equal_replacemenet, "<>", $val);
		// Re-replace <=
		$val = str_replace($ls_equal_replacement, "<=", $val);
		// Re-replace <!
		$val = str_replace($ls_exclaim_replacement, "<!", $val);
		// Re-replace <#
		$val = str_replace($ls_num_replacement, "", $val);
		// If any allowed tags contain javascript inside them, then remove javascript due to security issue.
		if ($filter_javascript && strpos($val, '>') !== false)
		{
		    // Make sure nothing malicious is hidden as malformed HTML character codes (missing a semi-colon), so add semi-colon and then decode to clean it
            $val = html_entity_decode(preg_replace('/(&#\d{2,3})/i', "$1;", $val), ENT_QUOTES);
			// Replace any uses of "javascript:" inside any HTML tag attributes
			$regex = "/(<)([^<]*)(javascript\s*:)([^<]*>)/i";
			do {
				$val = preg_replace($regex, "$1$2removed;$4", $val);
			} while (preg_match($regex, $val));
			// Replace any JavaScript events that are used as HTML tag attributes
			$regex = "/(<)([^<]*)(oncontextmenu\s*=|onkeyup\s*=|onkeydown\s*=|onkeypress\s*=|onhashchange\s*=|onscroll\s*=|onpageshow\s*=|onloadstart\s*=|allowscriptaccess\s*=|onload\s*=|onerror\s*=|onabort\s*=|onclick\s*=|ondblclick\s*=|onblur\s*=|onfocus\s*=|onreset\s*=|onselect\s*=|onsubmit\s*=|onmouseup\s*=|onmouseover\s*=|onmouseout\s*=|onmousemove\s*=|onmousedown\s*=|onmouseenter\s*=)(.*>)/i";
			do {
				$val = preg_replace($regex, "$1$2removed=$4", $val);
			} while (preg_match($regex, $val));
		}
	}
	// Return string value
	return $val;
}

// Render divs holding javascript form-validation text (when error occurs), so they get translated on the page
function renderValidationTextDivs()
{
	global $lang;
	?>

	<!-- Add hidden text for slider accessiblity on surveys -->
	<div id="slider-0means" class="hidden"><?php echo $lang['survey_1142'] ?></div>
	<div id="slider-50means" class="hidden"><?php echo $lang['survey_1143'] ?></div>
	<div id="slider-100means" class="hidden"><?php echo $lang['survey_1144'] ?></div>

	<!-- Text used for field validation errors -->
	<div id="valtext_divs">
		<div id="valtext_number"><?php echo $lang['config_functions_52'] ?></div>
		<div id="valtext_integer"><?php echo $lang['config_functions_53'] ?></div>
		<div id="valtext_vmrn"><?php echo $lang['config_functions_54'] ?></div>
		<div id="valtext_rangehard"><?php echo $lang['config_functions_56'] ?></div>
		<div id="valtext_rangesoft1"><?php echo $lang['config_functions_57'] ?></div>
		<div id="valtext_rangesoft2"><?php echo $lang['config_functions_58'] ?></div>
		<div id="valtext_time"><?php echo $lang['config_functions_59'] ?></div>
		<div id="valtext_zipcode"><?php echo $lang['config_functions_60'] ?></div>
		<div id="valtext_phone"><?php echo $lang['config_functions_61'] ?></div>
		<div id="valtext_email"><?php echo $lang['config_functions_62'] ?></div>
		<div id="valtext_regex"><?php echo $lang['config_functions_77'] ?></div>
		<div id="valtext_requiredformat"><?php echo $lang['config_functions_94'] ?></div>
	</div>
	<!-- Regex used for field validation -->
	<div id="valregex_divs">
	<?php foreach (getValTypes() as $valType=>$attr) { ?>
		<div id="valregex-<?php echo $valType ?>" datatype="<?php echo $attr['data_type'] ?>" label="<?php echo js_escape2($attr['validation_label']) ?>"><?php echo $attr['regex_js'] ?></div>
	<?php } ?>
	</div>
	<?php
}

// Will convert a legacy field validation type (e.g., int, float, date) into a real value (e.g., integer, number, date_ymd).
// If not a legacy validation type, then will just return as-is.
function convertLegacyValidationType($legacyType)
{
	if ($legacyType == "int") {
		$realType = "integer";
	} elseif ($legacyType == "float") {
		$realType = "number";
	} elseif ($legacyType == "datetime_seconds") {
		$realType = "datetime_seconds_ymd";
	} elseif ($legacyType == "datetime") {
		$realType = "datetime_ymd";
	} elseif ($legacyType == "date") {
		$realType = "date_ymd";
	} else {
		$realType = $legacyType;
	}
	return $realType;
}

// Will convert an _mdy or _dmy date[time] validation into _ymd (often for PHP/back-end data validation purposes)
function convertDateValidtionToYMD($valType)
{
	if (substr($valType, 0, 16) == "datetime_seconds") {
		$realType = "datetime_seconds_ymd";
	} elseif (substr($valType, 0, 8) == "datetime") {
		$realType = "datetime_ymd";
	} elseif (substr($valType, 0, 4) == "date") {
		$realType = "date_ymd";
	} else {
		$realType = $valType;
	}
	return $realType;
}

// Render hidden divs used by showProgress() javascript function
function renderShowProgressDivs()
{
	global $lang;
	print	RCView::div(array('id'=>'working'),
				RCView::img(array('alt'=>$lang['design_08'], 'src'=>'progress_circle.gif')) . RCView::SP .
				$lang['design_08']
			) .
			RCView::div(array('id'=>'fade'), '');
}

// Convert an array to a REDCap enum format with keys as coded value and value as lables
function arrayToEnum($array, $delimiter="\\n")
{
	$enum = array();
	foreach ($array as $key=>$val)
	{
		$enum[] = trim($key) . ", " . trim($val);
	}
	return implode(" $delimiter ", $enum);
}

// Determine web server domain name (take into account if a proxy exists)
function getServerName()
{
	if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) return $_SERVER['HTTP_HOST'];
	if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) return $_SERVER['SERVER_NAME'];
	if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) return $_SERVER['HTTP_X_FORWARDED_HOST'];
	if (isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && !empty($_SERVER['HTTP_X_FORWARDED_SERVER'])) return $_SERVER['HTTP_X_FORWARDED_SERVER'];
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
	return false;
}

// Determing IP address of server (account for proxies also)
function getServerIP()
{
	if (isset($_SERVER['SERVER_ADDR']) && !empty($_SERVER['SERVER_ADDR'])) return $_SERVER['SERVER_ADDR'];
	if (isset($_SERVER['LOCAL_ADDR']) && !empty($_SERVER['LOCAL_ADDR'])) return $_SERVER['LOCAL_ADDR'];
	if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
}

//Returns file extension of an inputted file name
function getFileExt($doc_name,$outputDotIfExists=false)
{
	$dotpos = strrpos($doc_name, ".");
	if ($dotpos === false) return "";
	return substr($doc_name, $dotpos + ($outputDotIfExists ? 0 : 1), strlen($doc_name));
}

// Replace any MS Word Style characters with regular characters
// NOTE: String parameter is passed by reference
function replaceMSchars(&$str)
{
	// First, we replace any UTF-8 characters that exist.
	$search = array(            // www.fileformat.info/info/unicode/<NUM>/ <NUM> = 2018
                "\xC2\xAB",     // ? (U+00AB) in UTF-8
                "\xC2\xBB",     // ? (U+00BB) in UTF-8
                "\xE2\x80\x98", // ? (U+2018) in UTF-8
                "\xE2\x80\x99", // ? (U+2019) in UTF-8
                "\xE2\x80\x9A", // ? (U+201A) in UTF-8
                "\xE2\x80\x9B", // ? (U+201B) in UTF-8
                "\xE2\x80\x9C", // ? (U+201C) in UTF-8
                "\xE2\x80\x9D", // ? (U+201D) in UTF-8
                "\xE2\x80\x9E", // ? (U+201E) in UTF-8
                "\xE2\x80\x9F", // ? (U+201F) in UTF-8
                "\xE2\x80\xB9", // ? (U+2039) in UTF-8
                "\xE2\x80\xBA", // ? (U+203A) in UTF-8
                "\xE2\x80\x93", // ? (U+2013) in UTF-8
                "\xE2\x80\x94", // ? (U+2014) in UTF-8
                "\xE2\x80\xA6"  // ? (U+2026) in UTF-8
    );
    $replacements = array(
                "<<",
                ">>",
                "'",
                "'",
                "'",
                "'",
                '"',
                '"',
                '"',
                '"',
                "<",
                ">",
                "-",
                "-",
                "..."
    );
	$str = str_replace($search, $replacements, $str);
}

// Sanitize query parameters of an ARRAY and return as a comma-delimited string surrounded by single quotes
function prep_implode($array=array(), $replaceMSchars=true, $useCheckNull=false)
{
	// Loop through array
	foreach ($array as &$str) {
		// Replace any MS Word Style characters with regular characters
		if ($replaceMSchars) replaceMSchars($str);
		// Perform escaping and return
		if ($useCheckNull) {
			$str = checkNull($str, $replaceMSchars);
		} else {
			$str = "'" . db_escape($str, $replaceMSchars) . "'";
		}
	}
	// Return as a comma-delimited string surrounded by single quotes
	return implode(", ", $array);
}

// Sanitize query parameters of a STRING
function db_escape($str, $replaceMSchars=true)
{
	// Replace any MS Word Style characters with regular characters
	if ($replaceMSchars) replaceMSchars($str);
	// Perform escaping and return
	return db_real_escape_string($str);
}

// Determine if a transaction is still active (with autocommit=0)
function db_transaction_active()
{
	$autocommmit = db_result(db_query("select @@AUTOCOMMIT"), 0);
	// If autocommit=0, then we're inside a transaction
	return ($autocommmit == '0');
}


// Render Javascript variables needed on all pages for various JS functions
function renderJsVars()
{
	global $redcap_version, $status, $isMobileDevice, $user_rights, $institution, $sendit_enabled, $super_user, $surveys_enabled,
		   $table_pk, $table_pk_label, $longitudinal, $email_domain_allowlist, $auto_inc_set, $data_resolution_enabled, $lang, $missingDataCodes,
           $file_upload_vault_enabled, $file_upload_versioning_enabled, $secondary_pk;
	// Set JS value for disabling DataTables
	$datatables_disable = json_encode((defined("PROJECT_ID") && isset($GLOBALS['ui_state'][PROJECT_ID]['datatables_disable'])) ? $GLOBALS['ui_state'][PROJECT_ID]['datatables_disable'] : array());
    // Remove the query string if somehow in page_full
    $page = PAGE;
    if (strpos(PAGE, '?')) {
        list ($page, $nothing) = explode("?", PAGE, 2);
    }
	// Output JavaScript
	?>
	<script type="text/javascript">
	<?php if (defined('APP_NAME')) { ?>
	var missing_data_codes = <?php echo "[\"" . implode("\",\"", array_keys($missingDataCodes)) . "\"]" ?>;
	var missing_data_codes_check = <?php echo (empty($missingDataCodes) ? 'false' : 'true') ?>;
	var app_name = '<?php echo APP_NAME ?>';
	var pid = <?php echo PROJECT_ID ?>;
	var status = <?php echo $status ?>;
	var table_pk  = '<?php echo $table_pk ?>'; var table_pk_label  = '<?php echo js_escape($table_pk_label) ?>';
	var longitudinal = <?php echo $longitudinal ? 1 : 0 ?>;
	var auto_inc_set = <?php echo $auto_inc_set ? 1 : 0 ?>;
	var file_upload_vault_enabled = <?php echo $file_upload_vault_enabled ? 1 : 0 ?>;
	var file_upload_versioning_enabled = <?php echo $file_upload_versioning_enabled ? 1 : 0 ?>;
	var data_resolution_enabled = <?php echo is_numeric($data_resolution_enabled) ? $data_resolution_enabled : 0 ?>;
	var lock_record = <?php echo (isset($user_rights) && is_numeric($user_rights['lock_record']) ? $user_rights['lock_record'] : '0') ?>;
	var shared_lib_browse_url = '<?php echo SHARED_LIB_BROWSE_URL . "?callback=" . urlencode(SHARED_LIB_CALLBACK_URL . "?pid=" . PROJECT_ID) . "&institution=" . urlencode($institution) . "&user=" . md5($institution . USERID) ?>';
	<?php } ?>
	var redcap_version = '<?php echo $redcap_version ?>';
	var server_name = '<?php echo SERVER_NAME ?>';
	var app_path_webroot = '<?php echo APP_PATH_WEBROOT ?>';
	var app_path_webroot_full = '<?php echo APP_PATH_WEBROOT_FULL ?>';
	var app_path_images = '<?php echo APP_PATH_IMAGES ?>';
	var page = '<?php echo $page ?>';
    var secondary_pk = '<?php echo $secondary_pk ?>';
	var sendit_enabled = <?php echo (isset($sendit_enabled) && is_numeric($sendit_enabled) ? $sendit_enabled : '0') ?>;
	var super_user = <?php echo (isset($super_user) && is_numeric($super_user) ? $super_user : '0') ?>;
	var surveys_enabled = <?php echo (isset($surveys_enabled) && is_numeric($surveys_enabled) ? $surveys_enabled : '0') ?>;
	var now = '<?php echo NOW ?>'; var now_mdy = '<?php echo date("m-d-Y H:i:s") ?>'; var now_dmy = '<?php echo date("d-m-Y H:i:s") ?>';
	var today = '<?php echo date("Y-m-d") ?>'; var today_mdy = '<?php echo date("m-d-Y") ?>'; var today_dmy = '<?php echo date("d-m-Y") ?>';
	var email_domain_allowlist = new Array(<?php echo ($email_domain_allowlist == '' ? '' : prep_implode(explode("\n", strtolower(str_replace("\r", "", $email_domain_allowlist))))) ?>);
	var user_date_format_jquery = '<?php echo DateTimeRC::get_user_format_jquery() ?>';
	var user_date_format_validation = '<?php echo strtolower(DateTimeRC::get_user_format_base()) ?>';
	var user_date_format_delimiter = '<?php echo DateTimeRC::get_user_format_delimiter() ?>';
	var ALLOWED_TAGS = '<?php echo ALLOWED_TAGS ?>';
	var AUTOMATE_ALL = '<?php echo (defined("AUTOMATE_ALL") ? '1' : '0') ?>';
	<?php if ($_SERVER['SERVER_NAME'] == 'redcap.mc.vanderbilt.edu') { ?>document.domain = 'mc.vanderbilt.edu'; <?php } ?>
	var datatables_disable = <?php print $datatables_disable ?>;	
	var langMsg137 = '<?php print js_escape($lang['messaging_177']) ?>';
	var langMsg68 = '<?php print js_escape($lang['messaging_118']) ?>';
	var langMsg69 = '<?php print js_escape($lang['messaging_119']) ?>';
	var langTimepicker01 = '<?php print js_escape($lang['form_renderer_29']) ?>';
	var langTimepicker02 = '<?php print js_escape($lang['global_13']) ?>';
	var langTimepicker03 = '<?php print js_escape($lang['calendar_widget_hour']) ?>';
	var langTimepicker04 = '<?php print js_escape($lang['calendar_widget_min']) ?>';
	var langTimepicker05 = '<?php print js_escape($lang['calendar_widget_choosetime']) ?>';
	var langAutoLogout01 = '<?php print js_escape($lang['global_147']) ?>';
	var langAutoLogout02 = '<?php print js_escape($lang['global_148']) ?>';
	var langAutoLogout03 = '<?php print js_escape($lang['global_149']) ?>';
	var warn_timeout1 = '<?php print js_escape($lang['global_150']) ?>';
	var warn_timeout2 = '<?php print js_escape($lang['global_151']) ?>';
	var warn_timeout3 = '<?php print js_escape($lang['global_152']) ?>';
    var langOkay = '<?php echo js_escape($lang['design_401']) ?>';
    var langCancel = '<?php echo js_escape($lang['global_53']) ?>';
    var langClose = '<?php echo js_escape($lang['calendar_popup_01']) ?>';
	var langAlert = '<?php print js_escape($lang['alerts_24']) ?>';
	$(function(){
		$.datepicker.setDefaults({
			closeText: '<?php print js_escape($lang['calendar_widget_done']) ?>',
			prevText: '<?php print js_escape($lang['calendar_widget_prev']) ?>',
			nextText: '<?php print js_escape($lang['calendar_widget_next']) ?>',
			currentText: '<?php print js_escape($lang['dashboard_32']) ?>',
			monthNamesShort:['<?php print js_escape($lang['calendar_widget_month_jan']) ?>','<?php print js_escape($lang['calendar_widget_month_feb']) ?>','<?php print js_escape($lang['calendar_widget_month_mar']) ?>','<?php print js_escape($lang['calendar_widget_month_apr']) ?>','<?php print js_escape($lang['calendar_widget_month_may']) ?>','<?php print js_escape($lang['calendar_widget_month_jun']) ?>','<?php print js_escape($lang['calendar_widget_month_jul']) ?>','<?php print js_escape($lang['calendar_widget_month_aug']) ?>','<?php print js_escape($lang['calendar_widget_month_sep']) ?>','<?php print js_escape($lang['calendar_widget_month_oct']) ?>','<?php print js_escape($lang['calendar_widget_month_nov']) ?>','<?php print js_escape($lang['calendar_widget_month_dec']) ?>'],
			dayNames:['<?php print js_escape($lang['calendar_widget_month_day_long_sun']) ?>','<?php print js_escape($lang['calendar_widget_month_day_long_mon']) ?>','<?php print js_escape($lang['calendar_widget_month_day_long_tues']) ?>','<?php print js_escape($lang['calendar_widget_month_day_long_wed']) ?>','<?php print js_escape($lang['calendar_widget_month_day_long_thurs']) ?>','<?php print js_escape($lang['calendar_widget_month_day_long_fri']) ?>','<?php print js_escape($lang['calendar_widget_month_day_long_sat']) ?>'],
			dayNamesMin:['<?php print js_escape($lang['calendar_widget_month_day_short_sun']) ?>','<?php print js_escape($lang['calendar_widget_month_day_short_mon']) ?>','<?php print js_escape($lang['calendar_widget_month_day_short_tues']) ?>','<?php print js_escape($lang['calendar_widget_month_day_short_wed']) ?>','<?php print js_escape($lang['calendar_widget_month_day_short_thurs']) ?>','<?php print js_escape($lang['calendar_widget_month_day_short_fri']) ?>','<?php print js_escape($lang['calendar_widget_month_day_short_sat']) ?>']
		});
	});
	</script>
	<?php
	// Output all the JavaScript variables needed for Messenger
	Messenger::outputJSvars();
}

// Redirects to URL provided using PHP, and if
function redirect($url)
{
	// If contents already output, use javascript to redirect instead
	if (headers_sent())
	{
		exit("<script type=\"text/javascript\">window.location.href=\"$url\";</script>");
	}
	// Redirect using PHP
	else
	{
		header("Location: $url");
		exit;
	}
}

// Pre-fill metadata by getting template fields from prefill_metadata.php
function createMetadata($new_project_id)
{
	$metadata = array();
	$form_names = array();
	$metadata['My First Instrument'] = array(
			array("record_id", "text", "Record ID", "", "", "")
	);
	//print_array($metadata);
	$i = 1;
	// Loop through all metadata fields from prefill_metadata.php and add as new project
	foreach ($metadata as $this_form=>$v2)
	{
		$this_form_menu1 = camelCase($this_form, true);
		$this_form = $form_names[] = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower($this_form)));
		foreach ($v2 as $j=>$v)
		{
			$this_form_menu = ($j == 0) ? $this_form_menu1 : "";
			$check_type = ($v[1] == "text") ? "soft_typed" : "";
			// Insert fields into metadata table
			$sql = "insert into redcap_metadata
					(project_id, field_name, form_name, form_menu_description, field_order, element_type, element_label,
					 element_enum, element_validation_type, element_validation_checktype, element_preceding_header) values
					($new_project_id, ".checkNull($v[0]).", ".checkNull($this_form).", ".checkNull($this_form_menu).", ".$i++.", ".checkNull($v[1]).",
					".checkNull($v[2]).", ".checkNull(str_replace("|","\\n",$v[3])).", ".checkNull($v[4]).", ".checkNull($check_type).", ".checkNull($v[5]).")";
			db_query($sql);
		}
		// Form Status field
		$sql = "insert into redcap_metadata (project_id, field_name, form_name, field_order, element_type,
				element_label, element_enum, element_preceding_header) values ($new_project_id, '{$this_form}_complete', ".checkNull($this_form).",
				".$i++.", 'select', 'Complete?', '0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
		db_query($sql);
	}
	// Return array of form_names to use for user_rights
	return $form_names;
}

// Find difference between two times
function timeDiff($firstTime,$lastTime,$decimalRound=null,$returnFormat='s')
{
	// convert to unix timestamps
	$firstTime = strtotime($firstTime);
	$lastTime = strtotime($lastTime);
	// perform subtraction to get the difference (in seconds) between times
	$timeDiff = $lastTime - $firstTime;
	// return the difference
	switch ($returnFormat)
	{
		case 'm':
			$timeDiff = $timeDiff/60;
			break;
		case 'h':
			$timeDiff = $timeDiff/3600;
			break;
		case 'd':
			$timeDiff = $timeDiff/3600/24;
			break;
		case 'w':
			$timeDiff = $timeDiff/3600/24/7;
			break;
		case 'y':
			$timeDiff = $timeDiff/3600/24/365;
			break;
	}
	if (is_numeric($decimalRound))
	{
		$timeDiff = round($timeDiff, $decimalRound);
	}
	return $timeDiff;
}

// Creates random alphanumeric string
function generateRandomHash($length=6, $addNonAlphaChars=false, $onlyHandEnterableChars=false, $alphaCharsOnly=false) {
	// Use character list that is human enterable by hand or for regular hashes (i.e. for URLs)
	if ($onlyHandEnterableChars) {
		$characters = '34789ACDEFHJKLMNPRTWXY'; // Potential characters to use (omitting 150QOIS2Z6GVU)
	} else {
		$characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ23456789'; // Potential characters to use
		if ($addNonAlphaChars) $characters .= '~.$#@!%^&*-';
	}
	// If returning only letter, then remove all non-alphas from $characters
	if ($alphaCharsOnly) {
		$characters = preg_replace("/[^a-zA-Z]/", "", $characters);
	}
	// Build string
	$strlen_characters = strlen($characters);
    $string = '';
    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[mt_rand(0, $strlen_characters-1)];
    }
	// If hash matches a number in Scientific Notation, then fetch another one
	// (because this could cause issues if opened in certain software - e.g. Excel)
	if (preg_match('/^\d+E\d/', $string)) {
		return generateRandomHash($length, $addNonAlphaChars, $onlyHandEnterableChars);
	} else {
		return $string;
	}
}

// Copy an edoc file on the web server. If fails, fall back to stream_copy().
function file_copy($src, $dest)
{
	if (!copy($src, $dest))
	{
		return stream_copy($src, $dest);
	}
	return true;
}

// Alternative to using copy() function, which can be disabled on some servers.
function stream_copy($src, $dest)
{
	 // Allocate more memory since stream_copy_to_stream() is a memory hog.
	$fsrc  = fopen($src,'rb');
	$fdest = fopen($dest,'w+');
	$len = stream_copy_to_stream($fsrc, $fdest);
	fclose($fsrc);
	fclose($fdest);
	// If entire file was copied (bytes are the same), return as true.
	return ($len == filesize($src));
}

// Copy an edoc_file by providing edoc_id. Returns edoc_id of new file, else False if failed. If desired, set new destination project_id.
function copyFile($edoc_id, $dest_project_id=PROJECT_ID)
{
	global $edoc_storage_option, $rc_connection;
	// Must be numeric
	if (!is_numeric($edoc_id)) return false;
	// Query the file in the edocs table
	$sql = "select * from redcap_edocs_metadata where doc_id = $edoc_id";
	$q = db_query($sql, $rc_connection);
	if (db_num_rows($q) < 1) return false;
	// Get file info
	$edoc_info = db_fetch_assoc($q);
	// Set src and dest filenames
	$src_filename  = $edoc_info['stored_name'];
	$dest_filename = date('YmdHis') . "_pid" . $dest_project_id . "_" . generateRandomHash(6) . getFileExt($edoc_info['doc_name'], true);
	// Default value
	$copy_successful = false;
	// Copy file within defined Edocs folder
	if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
	{
		$copy_successful = file_copy(EDOC_PATH . $src_filename, EDOC_PATH . $dest_filename);
	}
	// S3
	elseif ($edoc_storage_option == '2')
	{
		try {
			$s3 = Files::s3client();
			$s3->copyObject(array(
				'Bucket'     => $GLOBALS['amazon_s3_bucket'],
				'Key'        => $dest_filename,
				'CopySource' => "{$GLOBALS['amazon_s3_bucket']}/{$src_filename}",
			));
			$copy_successful = true;
		} catch (Aws\S3\Exception\S3Exception $e) {
		}
	}
	// Azure
	elseif ($edoc_storage_option == '4')
	{
		$blobClient = Files::azureBlobClient();
		try {
			$blobClient->copyBlob($GLOBALS['azure_container'], $dest_filename, $GLOBALS['azure_container'], $src_filename);
			$copy_successful = true;
		} catch (Exception $e) {			
		}
	}
	// Use WebDAV copy methods
	else
	{
		if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
		$wdc = new WebdavClient();
		$wdc->set_server($webdav_hostname);
		$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
		$wdc->set_user($webdav_username);
		$wdc->set_pass($webdav_password);
		$wdc->set_protocol(1); // use HTTP/1.1
		$wdc->set_debug(false); // enable debugging?
		if (!$wdc->open()) {
			sleep(1);
			return false;
		}
		if (substr($webdav_path,-1) != '/') {
			$webdav_path .= '/';
		}
		// Download source file
		if ($wdc->get($webdav_path . $src_filename, $contents) == '200')
		{
			// Copy to destination file
			$copy_successful = ($wdc->put($webdav_path . $dest_filename, $contents) == '201');
		}
		$wdc->close();
	}
	// If copied successfully, then add new row in edocs_metadata table
	if ($copy_successful)
	{
		//Copy this row in the rs_edocs table and get new doc_id number
		$sql = "insert into redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id, stored_date)
				select '$dest_filename', mime_type, doc_name, doc_size, file_extension, '$dest_project_id', '".NOW."' from redcap_edocs_metadata
				where doc_id = $edoc_id";
		if (db_query($sql, $rc_connection))
		{
			return db_insert_id($rc_connection);
		}
	}
	return false;
}

// Make an HTTP GET request
function http_get($url="", $timeout=null, $basic_auth_user_pass="", $headers=array(), $user_agent=null)
{
	// Try using cURL first, if installed
	if (function_exists('curl_init'))
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPGET, true);
		if (!sameHostUrl($url)) {
			curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
			curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		}
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
		if (is_numeric($timeout)) {
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
		}
		// If using basic authentication = base64_encode(username:password)
		if ($basic_auth_user_pass != "") {
			curl_setopt($curl, CURLOPT_USERPWD, $basic_auth_user_pass);
			// curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
		}
		// If passing headers manually, then add then
		if (!empty($headers) && is_array($headers)) {
			//curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$authorizationBearerToken));
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}
		// If passing user agent
		if ($user_agent != null) {
			curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
		}
		// Execute it
		$response = curl_exec($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);
		// If returns an HTTP 404 error, return false
		if (isset($info['http_code']) && $info['http_code'] == 404) return false;
		if ($info['http_code'] != '0') return $response;
	}
	// Try using file_get_contents if allow_url_open is enabled .
	// If curl somehow returned http status=0, then try this method.
	if (ini_get('allow_url_fopen'))
	{
		// Set http array for file_get_contents
		$http_array = array('method'=>'GET');
		if (is_numeric($timeout)) {
			$http_array['timeout'] = $timeout; // Set timeout time in seconds
		}
		// If using basic authentication (username:password)
		if ($basic_auth_user_pass != "") {
			$http_array['header'] = "Authorization: Basic " . base64_encode($basic_auth_user_pass);
		}
		// If using a proxy
		if (!sameHostUrl($url) && PROXY_HOSTNAME != '') {
			$http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
			$http_array['request_fulluri'] = true;
			if (PROXY_USERNAME_PASSWORD != '') {
				$proxy_auth = "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD);
				if (isset($http_array['header'])) {
					$http_array['header'] .= PHP_EOL . $proxy_auth;
				} else {
					$http_array['header'] = $proxy_auth;
				}
			}
		}
		// Use file_get_contents
		$content = @file_get_contents($url, false, stream_context_create(array('http'=>$http_array)));
	}
	else
	{
		$content = false;
	}
	// Return the response
	return $content;
}

// Send HTTP Post request and receive/return content
function http_post($url="", $params=array(), $timeout=null, $content_type='application/x-www-form-urlencoded', $basic_auth_user_pass="", $headers=array())
{
	// If params are given as an array, then convert to query string format, else leave as is
	if ($content_type == 'application/json') {
		// Send as JSON data
		$param_string = (is_array($params)) ? json_encode($params) : $params;
	} elseif ($content_type == 'application/x-www-form-urlencoded') {
		// Send as Form encoded data
		$param_string = (is_array($params)) ? http_build_query($params, '', '&') : $params;
	} else {
		// Send params as is (e.g., Soap XML string)
		$param_string = $params;
	}

	// Check if cURL is installed first. If so, then use cURL instead of file_get_contents.
	if (function_exists('curl_init'))
	{
		// Use cURL
		$curlpost = curl_init();
		curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
		curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
		curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
		curl_setopt($curlpost, CURLOPT_URL, $url);
		curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
		if (!sameHostUrl($url)) {
			curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
			curl_setopt($curlpost, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		}
		curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
		if (is_numeric($timeout)) {
			curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
		}
		// If using basic authentication = base64_encode(username:password)
		if ($basic_auth_user_pass != "") {
			curl_setopt($curlpost, CURLOPT_USERPWD, $basic_auth_user_pass);
			// curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
		}
		// If not sending as x-www-form-urlencoded, then set special header
		if ($content_type != 'application/x-www-form-urlencoded') {
			curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: " . strlen($param_string)));
		}
		// If passing headers manually, then add then
		if (!empty($headers) && is_array($headers)) {
			curl_setopt($curlpost, CURLOPT_HTTPHEADER, $headers);
		}
		// Make the call
		$response = curl_exec($curlpost);
		$info = curl_getinfo($curlpost);
		curl_close($curlpost);
		// If returns an HTTP 404 error, return false
		if (isset($info['http_code']) && $info['http_code'] == 404) return false;
		if ($info['http_code'] != '0') return $response;
	}
	// Try using file_get_contents if allow_url_open is enabled .
	// If curl somehow returned http status=0, then try this method.
	if (ini_get('allow_url_fopen'))
	{
		// Set http array for file_get_contents
		$http_array = array('method'=>'POST',
							'header'=>"Content-type: $content_type",
							'content'=>$param_string
					  );
		if (is_numeric($timeout)) {
			$http_array['timeout'] = $timeout; // Set timeout time in seconds
		}
		// If using basic authentication (username:password)
		if ($basic_auth_user_pass != "") {
			$http_array['header'] .= PHP_EOL . "Authorization: Basic " . base64_encode($basic_auth_user_pass);
		}
		// If using a proxy
		if (!sameHostUrl($url) && PROXY_HOSTNAME != '') {
			$http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
			$http_array['request_fulluri'] = true;
			if (PROXY_USERNAME_PASSWORD != '') {
				$http_array['header'] .= PHP_EOL . "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD);
			}
		}

		// Use file_get_contents
		$content = @file_get_contents($url, false, stream_context_create(array('http'=>$http_array)));

		// Return the content
		if ($content !== false) {
			return $content;
		}
		// If no content, check the headers to see if it's hiding there (why? not sure, but it happens)
		else {
			$content = implode("", $http_response_header);
			// If header is a true header, then return false, else return the content found in the header
			return (substr($content, 0, 5) == 'HTTP/') ? false : $content;
		}
	}
	// Return false
	return false;
}

// Send HTTP PUT request and receive/return content
function http_put($url="", $params=array(), $timeout=null, $content_type='application/x-www-form-urlencoded', $basic_auth_user_pass="")
{
	// If params are given as an array, then convert to query string format, else leave as is
	if ($content_type == 'application/json') {
		// Send as JSON data
		$param_string = (is_array($params)) ? json_encode($params) : $params;
	} else {
		// Send as Form encoded data
		$param_string = (is_array($params)) ? http_build_query($params, '', '&') : $params;
	}
	// Use cURL
	$curlpost = curl_init();
	curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
	curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
	curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
	curl_setopt($curlpost, CURLOPT_URL, $url);
	curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
	if (!sameHostUrl($url)) {
		curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
		curl_setopt($curlpost, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
	}
	curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
	if (is_numeric($timeout)) {
		curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
	}
	// If using basic authentication = base64_encode(username:password)
	if ($basic_auth_user_pass != "") {
		curl_setopt($curlpost, CURLOPT_USERPWD, $basic_auth_user_pass);
		// curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
	}
	// If not sending as x-www-form-urlencoded, then set special header
	if ($content_type != 'application/x-www-form-urlencoded') {
		curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: " . strlen($param_string)));
	}
	$response = curl_exec($curlpost);
	$info = curl_getinfo($curlpost);
	curl_close($curlpost);
	// If returns an HTTP 404 error, return false
	if (isset($info['http_code']) && $info['http_code'] == 404) return false;
	if ($info['http_code'] != '0') return $response;
}

// Validate if string is a proper URL. Return boolean.
function isURL($url)
{
	$pattern = "/^(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/i";
	return preg_match($pattern, $url);
}

// Retrieve all field validation types from table. Return as array.
function getValTypes($valtype=null)
{
	// Add validation type array as a global variable. If is array (already populated), then return it, otherwise generate from table.
	global $redcap_valtypes;
	// Is it populated already?
	if (!isset($redcap_valtypes) || !is_array($redcap_valtypes)) {
		// Get from table
		$sql = "select * from redcap_validation_types where validation_name is not null
				and validation_name != '' order by validation_label";
		$q = db_query($sql);
		$redcap_valtypes = array();
		while ($row = db_fetch_assoc($q))
		{
			$redcap_valtypes[$row['validation_name']] = array(
				'validation_label'=>$row['validation_label'],
				'regex_js'=>$row['regex_js'],
				'regex_php'=>$row['regex_php'],
				'data_type'=>$row['data_type'],
				'visible'=>$row['visible']
			);
		}
	}
	if ($valtype !== null) {
		return isset($redcap_valtypes[$valtype]) ? $redcap_valtypes[$valtype] : null;
	} else {
		return $redcap_valtypes;
	}
}

// Makes sure that a date in format Y-M-D format has 2-digit month and day and has a 4-digit year
function clean_date_ymd($date)
{
	global $missingDataCodes;
	
	$date = trim($date);
	// Ensure has 2 dashes, and if not 10-digits long, then break apart and reassemble
	if (substr_count($date, "-") == 2 && strlen($date) < 10)
	{
		// Break into components
		list ($year, $month, $day) = explode('-', $date);
		// Make sure year is 4 digits
		if (strlen($year) == 2) {
			$year = ($year < (date('y')+10)) ? "20".$year : "19".$year;
		}
		// Reassemble
		$date = sprintf("%04d-%02d-%02d", $year, $month, $day);
	}
	return $date;
}

// Detect IE version
function vIE()
{
	$browser = new Browser();
	$browser_name = strtolower($browser->getBrowser());
	$browser_version = isIE11compat() ? '11.0' : $browser->getVersion();
	return ($browser_name == 'internet explorer' && is_numeric($browser_version)) ? floatval($browser_version) : -1;
}

// Detect IE11 Compatibility Mode. Return boolean.
function isIE11compat()
{
	global $isIE;
	return ($isIE && stripos($_SERVER['HTTP_USER_AGENT'], 'Trident/7.0') !== false
		&& stripos($_SERVER['HTTP_USER_AGENT'], 'Mozilla/4.0') !== false);
}

// Display a message to the user as a colored div with option to animate and set aesthetics
function displayMsg($msgText=null, $msgId="actionMsg", $msgAlign="center", $msgClass="green", $msgIcon="tick.png", $timeVisible, $msgAnimate=true)
{
	global $lang;
	// Set message text
	if ($msgText == null) {
		$msgText = "<b>{$lang['setup_08']}</b> {$lang['setup_09']}";
	}
	// Check that timeVisible is a positive number (in seconds)
	if (!is_numeric($timeVisible) || (is_numeric($timeVisible) && $timeVisible < 0)) {
		$timeVisible = 7;
	}
	// Display the message
	?>
	<div id="<?php echo $msgId ?>" class="<?php echo $msgClass ?>" style="<?php if ($msgAnimate) echo 'display:none;'; ?>max-width:800px;padding:15px 25px;margin:20px 0;text-align:<?php echo $msgAlign ?>;">
		<img src="<?php echo APP_PATH_IMAGES . $msgIcon ?>"> <?php echo $msgText ?>
	</div>
	<?php
	// Animate the message to display and hide (if set to do so)
	if ($msgAnimate)
	{
		?>
		<!-- Animate action message -->
		<script type="text/javascript">
		$(function(){
			setTimeout(function(){
				$("#<?php echo $msgId ?>").slideToggle('normal');
			},200);
			setTimeout(function(){
				$("#<?php echo $msgId ?>").slideToggle(1200);
			},<?php echo $timeVisible*1000 ?>);
		});
		</script>
		<?php
	}
}

// Add a special header to enforce BOM (byte order mark) if the string is UTF-8 encoded file
function addBOMtoUTF8($string)
{
	if (function_exists('mb_detect_encoding') && mb_detect_encoding($string) == "UTF-8")
	{
		$string = "\xEF\xBB\xBF" . $string;
	}
	return $string;
}

// Remove BOM (byte order mark) if the string is UTF-8 encoded file
function removeBOMfromUTF8($string)
{
	$bom = pack("CCC", 0xef,0xbb,0xbf);
	if (function_exists('mb_detect_encoding') && mb_detect_encoding($string) == "UTF-8" && substr($string, 0, 3) == $bom)
	{
		$string = substr($string, 3);
	}
	return $string;
}

// Check if a directory is writable (tries to write a file to directory as a definite confirmation)
function isDirWritable($dir)
{
	global $edoc_storage_option;
	$is_writable = false; //default
	if ($edoc_storage_option == '3' || (is_dir($dir) && is_writeable($dir))) // Make exception if Google Cloud Storage (3)
	{
		// Try to write a file to that directory and then delete
		$test_file_path = $dir . DS . date('YmdHis') . '_test.txt';
		$fp = fopen($test_file_path, 'w');
		if ($fp !== false && fwrite($fp, 'test') !== false)
		{
			// Set as writable
			$is_writable = true;
			// Close connection and delete file
			fclose($fp);
			unlink($test_file_path);
		}
	}
	return $is_writable;
}

// REDCAP INFO: Display table of REDCap variables, constants, and settings (similar to php_info()).
// This function is just a wrapper for PluginDocs::redcap_info().
function redcap_info($displayInsideOtherPage=false, $displayHeaderLogo=true)
{
	PluginDocs::redcapInfo($displayInsideOtherPage, $displayHeaderLogo);
}

## REDCAP PLUGIN FUNCTION: Limit the plugin to specific projects
function allowProjects()
{
	$args = func_get_args();
	return call_user_func_array('REDCap::allowProjects', $args);
}

## REDCAP PLUGIN FUNCTION: Limit the plugin to specific users
function allowUsers()
{
	$args = func_get_args();
	return call_user_func_array('REDCap::allowUsers', $args);
}

// Clean and escape text to be sent as JSON
function cleanJson($val)
{
	return js_escape2(str_replace('\\', '\\\\', $val));
}

// Take a CSV formatted $_FILE that was uploaded and convert to array
function csv_file_to_array($file) // e.g. $file = $_FILES['allocFile']
{
	global $lang;

	// If filename is blank, reload the page
	if ($file['name'] == "") exit($lang['random_13']);

	// Get field extension
	$filetype = strtolower(substr($file['name'],strrpos($file['name'],".")+1,strlen($file['name'])));

	// If not CSV, print message, exit
	if ($filetype != "csv") exit($lang['global_01'] . $lang['colon'] . " " . $lang['design_136']);

	// If CSV file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
	$file['name'] = APP_PATH_TEMP . date('YmdHis') . (defined('PROJECT_ID') ? "_pid" . PROJECT_ID : '') . "_fileupload." . $filetype;
	$file['name'] = str_replace("\\", "\\\\", $file['name']);

	// If moving or copying the uploaded file fails, print error message and exit
	if (!move_uploaded_file($file['tmp_name'], $file['name'])) {
		if (!copy($file['tmp_name'], $file['name'])) exit($lang['random_13']);
	}

	// Make sure we remove the UTF8 BOM, if exists
	file_put_contents($file['name'], removeBOMfromUTF8(file_get_contents($file['name'])));

	// Now read the stored CSV file into an array
	$csv_array = array();
	if (($handle = fopen($file['name'], "rb")) !== false) {
		// Loop through each row
		while (($row = fgetcsv($handle, 0, ",")) !== false) {
			$csv_array[] = $row;
		}
		fclose($handle);
	}

	// Remove the saved file, since it's no longer needed
	unlink($file['name']);

	// Return the array
	return $csv_array;
}

// Determine if being accessed by REDCap developer
function isDev($includeVanderbiltSuperUsers=false)
{
	return ((isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == '10.151.18.250')
			|| ($_SERVER['SERVER_NAME'] == 'localhost' && defined('DEV'))
			|| ($includeVanderbiltSuperUsers && defined('USERID') && defined('SUPER_USER') && SUPER_USER && isVanderbilt()) || $_SERVER['SERVER_NAME'] == '10.151.18.79');
}

// Renders the home page header and footer with the specified content provided ehre
function renderPage($content)
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("home.css", 'screen,print');
	$objHtmlPage->PrintHeader();
	print RCView::div(array('class'=>'space','style'=>'margin:10px 0;'), '&nbsp;')
		. $content
		. RCView::div(array('class'=>'space','style'=>'margin:5px 0;'), '&nbsp;');
	$objHtmlPage->PrintFooter();
	exit;
}

// Validate if an email address
function isEmail($email)
{
	return (preg_match('/^(?!\\.)((?!.*\\.{2})[a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\.!#$%&\'*+\\-\\/=?^_`{|}~\\-\\d]+)(\\.[a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\.!#$%&\'*+\\-\\/=?^_`{|}~\\-\\d]+)*@(?!\\.)([a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\-\\.\\d]+)((\\.([a-zA-Z\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}]){2,63})+)$/u', $email));
}

// Validate if a U.S. phone number
function isPhoneUS($phone)
{
	// Remove non-numerals
	$phone = preg_replace("/[^0-9]/", "", $phone);
	// Validate format and length
	if (preg_match("/^(?:\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9])\)?)\s*(?:[.-]\s*)?([2-9]1[02-9]|[2-9][02-9]1|[2-9][02-9]{2})\s*(?:[.-]\s*)?([0-9]{4})(?:\s*(?:#|x\.?|ext\.?|extension)\s*(\d+))?$/", $phone)) {
		// Array of ALL non-valid U.S. area codes
		$nonvalid_us_area_codes = array(220,221,222,223,230,232,233,235,237,238,241,243,244,245,247,249,255,257,258,259,261,263,265,266,271,273,275,277,279,280,282,285,286,287,288,290,291,292,293,294,295,296,297,298,299,300,322,324,328,329,332,333,335,338,342,344,346,348,349,350,353,354,355,356,357,358,359,362,363,364,366,367,368,370,371,372,373,374,375,376,377,378,379,381,382,383,384,387,388,389,390,391,392,393,394,395,396,397,398,399,400,420,421,422,426,427,429,433,436,439,444,445,446,448,449,451,452,453,454,455,457,458,459,460,461,462,465,466,467,468,471,472,476,477,482,483,485,486,487,488,489,490,491,492,493,494,495,496,497,498,499,521,522,523,524,525,526,527,528,529,531,532,533,534,535,536,537,538,542,543,544,545,546,547,549,550,552,553,554,556,558,560,565,566,568,569,572,576,577,578,581,582,583,584,588,589,590,591,592,593,594,595,596,597,598,599,621,622,624,625,632,633,634,635,637,638,640,642,643,644,645,648,652,653,654,655,656,663,665,666,667,668,673,674,675,676,677,680,683,685,686,687,688,690,691,692,693,694,695,696,697,698,699,722,723,726,728,729,733,735,736,738,739,741,742,743,744,745,746,748,749,750,751,752,753,755,756,759,761,766,768,771,776,777,783,788,789,790,791,792,793,794,795,796,797,798,799,820,821,823,824,826,827,834,836,837,838,840,841,842,846,851,852,853,854,861,871,874,875,879,883,884,885,886,887,889,890,891,892,893,894,895,896,897,899,921,922,923,924,926,930,932,933,934,938,942,943,944,945,946,948,950,953,955,958,960,961,962,963,964,965,966,967,968,969,974,977,981,982,983,986,987,988,990,991,992,993,994,995,996,997,998,999);
		// Make sure area code (first 3 numbers) is valid by referencing a list since some non-U.S. numbers can look just like a U.S. number
		return !in_array(substr($phone, 0, 3), $nonvalid_us_area_codes);
	}
	return false;
	## NOTE: Generated $nonvalid_us_area_codes above by obtaining list of all valid U.S. area codes at
	## http://www.bennetyee.org/ucsd-pages/area.html (see also https://en.wikipedia.org/wiki/List_of_North_American_Numbering_Plan_area_codes)
	// $all_area_codes = array(201,...); // Copy and paste valid area codes into this array, then loop to find the missing ones
	// for ($i = min($all_area_codes); $i <= max($all_area_codes); $i++) {
		// if (!in_array($i, $all_area_codes)) print "$i, ";
	// }
}

// Get current timezone name (e.g., America/Chicago). If cannot determine, return text "[could not be determined]".
function getTimeZone()
{
	global $lang;
	$timezone = (function_exists("date_default_timezone_get")) ? date_default_timezone_get() : ini_get('date.timezone');
	if (empty($timezone)) $timezone = $lang['survey_298'];
	return $timezone;
}

// Output the script tag for a given JavaScript file
function loadJS($js_file, $outputToPage=true)
{
	// Cache-busting
	$objHtmlPage = new HtmlPage();
	$js_file = $objHtmlPage->CacheBuster(APP_PATH_JS.$js_file);
	// Create script tag
	$output = "<script type=\"text/javascript\" src=\"" . $js_file . "\"></script>\n";
	if ($outputToPage) {
		print $output;
	} else {
		return $output;
	}
}

// GZIP encode a string
function gzip_encode_file($file_content, $file_name, $compression_level=9) {
	$gzipped = 0;
	if (function_exists('gzcompress')) {
		$file_content = gzcompress($file_content, $compression_level);
		$file_name .= ".gz";
		$gzipped = 1;
	}
	return array($file_content, $file_name, $gzipped);
}

// GZIP decode a string
function gzip_decode_file($file_content, $file_name=null) {
	if (function_exists('gzuncompress')) {
		$file_content = gzuncompress($file_content);
		if ($file_name != null && substr($file_name, -3) == ".gz") {
			$file_name = substr($file_name, 0, -3);
		}
	}
	return array($file_content, $file_name);
}

// Permanently delete the project from all db tables right now (as opposed to flagging it for deletion later)
function deleteProjectNow($project_id, $doLogging=true)
{
	// Get project title (app_title)
	$q = db_query("select app_title from redcap_projects where project_id = $project_id");
	$app_title = strip_tags(label_decode(db_result($q, 0)));
	// Get list of users with access to project
	$userList = str_replace("'", "", pre_query("select username from redcap_user_rights where project_id = $project_id and username != ''"));

	// For uploaded edoc files, set delete_date so they'll later be auto-deleted from the server
	db_query("update redcap_edocs_metadata set delete_date = '".date('Y-m-d H:i:s')."' where project_id = $project_id and delete_date is null");
	// Delete all project data and related info from ALL tables (most will be done by foreign keys automatically)
	$deletedFromRedcapProjects = db_query("delete from redcap_projects where project_id = $project_id");
	// Do other deletions manually because some tables don't have foreign key cascade deletion set
	db_query("delete from redcap_data where project_id = $project_id");
	// Don't actually delete these because they are logs, but simply remove any data-related info
	db_query("update redcap_log_view set event_id = null, record = null, form_name = null, miscellaneous = null where project_id = $project_id");
	db_query("update ".Logging::getLogEventTable($project_id)." set event_id = null, sql_log = null, data_values = null, pk = null where project_id = $project_id
				 and description != 'Delete project'");
	// If due to strange cascading foreign key issue that only affects certain MySQL configs (not sure why),
	// Delete from other tables manually
	if (!$deletedFromRedcapProjects)
	{
		// Disable foreign key checks (just in case)
		db_query("set foreign_key_checks = 0");
		db_query("delete from redcap_metadata where project_id = $project_id");
		db_query("delete from redcap_metadata_temp where project_id = $project_id");
		db_query("delete from redcap_metadata_archive where project_id = $project_id");
		db_query("delete from redcap_reports where project_id = $project_id");
		// Delete docs
		db_query("delete from redcap_docs where project_id = $project_id");
		// Delete calendar events
		db_query("delete from redcap_events_calendar where project_id = $project_id");
		// Delete locking data
		db_query("delete from redcap_locking_data where project_id = $project_id");
		// Delete esignatures
		db_query("delete from redcap_esignatures where project_id = $project_id");
		// Delete survey-related info (response tracking, emails, participants) but not actual survey structure
		$event_ids = pre_query("select e.event_id from redcap_events_metadata e, redcap_events_arms a
								where e.arm_id = a.arm_id and a.project_id = $project_id");
		$survey_ids = pre_query("select survey_id from redcap_surveys where project_id = $project_id");
		if ($survey_ids != "''") {
			// Delete emails to those in Participant List
			db_query("delete from redcap_surveys_emails where survey_id in ($survey_ids)");
			// Delete survey responses
			$response_ids = pre_query("select r.response_id from redcap_surveys_response r, redcap_surveys_participants p
									   where p.participant_id = r.participant_id and p.survey_id in ($survey_ids)");
			if ($response_ids != "''") {
				db_query("delete from redcap_surveys_response where response_id in ($response_ids)");
			}
			// Delete "participants" for follow-up surveys only (do NOT delete public survey "participants" or initial survey participants)
			db_query("delete from redcap_surveys_participants where survey_id in ($survey_ids)");
			// Remove all survey invitations that were queued for records in this project
			$ss_ids = pre_query("select ss_id from redcap_surveys_scheduler where survey_id in ($survey_ids)");
			if ($ss_ids != "''") {
				db_query("delete from redcap_surveys_scheduler_queue where ss_id in ($ss_ids)");
			}
			db_query("delete from redcap_surveys_scheduler where survey_id in ($survey_ids)");
		}
		// Delete rows in redcap_surveys
		db_query("delete from redcap_surveys where project_id = $project_id");
		// Remove any randomization assignments
		db_query("delete from redcap_randomization where project_id = $project_id");
		// Delete all records in redcap_data_quality_status
		db_query("delete from redcap_data_quality_status where project_id = $project_id");
		// Delete all records in redcap_ddp_records
		db_query("delete from redcap_ddp_records where project_id = $project_id");
		// Delete all records in redcap_surveys_queue_hashes
		db_query("delete from redcap_surveys_queue_hashes where project_id = $project_id");
		// Delete records in redcap_new_record_cache
		db_query("delete from redcap_new_record_cache where project_id = $project_id");
		// Delete rows in redcap_surveys_phone_codes
		db_query("delete from redcap_surveys_phone_codes where project_id = $project_id");
		// Delete rows in redcap_events_arms
		db_query("delete from redcap_events_arms where project_id = $project_id");
		db_query("delete from redcap_events_metadata where event_id in ($event_ids)");
		// Delete row in redcap_projects
		db_query("delete from redcap_projects where project_id = $project_id");
		// re-enable foreign key checks (just in case)
		db_query("set foreign_key_checks = 1");
	}

	// Log the permanent deletion of the project
	if ($doLogging)
	{
		$loggingDescription = "Permanently delete project";
		$loggingDataValues  = "project_id = $project_id,\napp_title = ".db_escape($app_title).",\nusernames: $userList";
		$loggingTable		= "redcap_projects";
		$loggingEventType	= "MANAGE";
		$loggingPage 		= (defined("CRON")) ? "cron.php" : PAGE;
		$loggingUser 		= (defined("CRON")) ? "SYSTEM"   : USERID;
		db_query("insert into ".Logging::getLogEventTable($project_id)." (project_id, ts, user, page, event, object_type, pk, data_values, description) values
					($project_id, '".date("YmdHis")."', '$loggingUser', '$loggingPage', '$loggingEventType', '$loggingTable',
					'$project_id', '$loggingDataValues', '$loggingDescription')");
	}
}

// [Retrieval of ALL records] If Custom Record Label is specified (such as "[last_name], [first_name]"), then parse and display.
function getCustomRecordLabels($custom_record_label, $event_id=null, $record=null, $removeIdentifiers=false)
{
	global $project_id_parent, $user_rights, $Proj, $double_data_entry, $table_pk;
	// Store all replaced labels in an array with record as key
	$label_array = array();
	if (!empty($custom_record_label))
	{
		// Get the variables in $custom_record_label
		$custom_record_label_fields = array_unique(array_keys(getBracketedFields($custom_record_label, true, true, true)));
		
		// If no fields exist in the custom record label, then return empty array
		if (empty($custom_record_label_fields)) return ($record != null ? '' : array());
		
		// If user has de-id rights, then get list of fields
		$deidFieldsToRemove = (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] > 1)
							? DataExport::deidFieldsToRemove($custom_record_label_fields, ($user_rights['data_export_tool'] == '3'))
							: array();

		// If using DDE, then set filter logic
		$ddeFilter = ($double_data_entry && $user_rights['double_data'] != 0) ? "ends_with([$table_pk], '--{$user_rights['double_data']}')" : false;
		// Get data for all events in the current arm
		$event_ids = array_keys($Proj->events[$Proj->eventInfo[$event_id]['arm_num']]['events']);
		// Get the data
		$custom_record_label_data = Records::getData('array', $record, $custom_record_label_fields, $event_ids, $user_rights['group_id'],
										false, false, false, $ddeFilter);
		// Apply de-id rights if this is a data export (i.e., a PDF)
		$applyDeIdExportRights = (PAGE == 'PdfController:index');
		// Loop through all collected data and add to $dropdownid_disptext array
		foreach (array_keys($custom_record_label_data) as $this_record) 
		{
			$label_array[$this_record] = Piping::replaceVariablesInLabel($custom_record_label, $this_record, $event_id, 1, $custom_record_label_data,
											false, $Proj->project_id, false, "", 1, false, $applyDeIdExportRights);
		}
	}

	// Return array if multiple records, but return string if for only one record
	if ($record != null && !is_array($record)) {
		foreach ($label_array as $this_field_data) {
			return $this_field_data;
		}
	} else {
		return $label_array;
	}
}

// Obtain array of HTTP headers of current web request
function get_request_headers() {
    $headers = array();
    foreach($_SERVER as $key => $value) {
        if(strpos($key, 'HTTP_') === 0) {
            $headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
        }
    }
    return $headers;
}

// Provide array of fields and return array of all fields upon which those fields are dependent
// with regard to calc fields and branching logic, and then obtain all fields dependent
// upon THOSE until all are found many levels down.
function getDependentFields($fields, $includeFieldsInCalc=true, $includeFieldsInBranching=true, $Proj2=null)
{
	// Get Proj object
	if ($Proj2 == null && defined("PROJECT_ID")) {
		global $Proj;
	} else {
		$Proj = $Proj2;
	}
	// Loop through all fields on this survey page and obtain all fields used in branching/calcs on this survey page
	$usedInBranchingCalc = $fieldsAlreadyChecked = array();
	$usedInBranchingCalcCount = 0;
	do {
		$usedInBranchingCalcCountLast = count($usedInBranchingCalc);
		foreach ($fields as $this_field) {
			// If we've already checked this field for its dependent fields, then skip it
			if (isset($fieldsAlreadyChecked[$this_field])) continue;
			if ($includeFieldsInCalc && $Proj->metadata[$this_field]['element_type'] == 'calc') {
				$usedInBranchingCalc = array_merge($usedInBranchingCalc, array_keys(getBracketedFields($Proj->metadata[$this_field]['element_enum'], true, true, true)));
				$fieldsAlreadyChecked[$this_field] = true;
			}
			if ($includeFieldsInBranching && $Proj->metadata[$this_field]['branching_logic'] != '') {
				$usedInBranchingCalc = array_merge($usedInBranchingCalc, array_keys(getBracketedFields($Proj->metadata[$this_field]['branching_logic'], true, true, true)));
				$fieldsAlreadyChecked[$this_field] = true;
			}
		}
		$fields = $usedInBranchingCalc = array_unique($usedInBranchingCalc);
		$usedInBranchingCalcCount = count($usedInBranchingCalc);
	} while ($usedInBranchingCalcCount != $usedInBranchingCalcCountLast);
	// Return array of all dependent fields
	return array_values($usedInBranchingCalc);
}

// Compares two urls to see if they have the same HOST
function sameHostUrl($url1, $url2='') {
	global $redcap_base_url;
	if ($url2 == '') $url2 = $redcap_base_url;
	$parts1 = parse_url($url1);
	$parts2 = parse_url($url2);
	$host1 = $parts1['host'];
	$host2 = $parts2['host'];
	return ($host1 && $host2 && gethostbyname($host1) == gethostbyname($host2));
}


// Better version of readfile that handles memory better when downloading large files
function readfile_chunked($filename, $retbytes=true)
{
   $chunksize = 10 * (1024*1024); // how many bytes per chunk
   $buffer = '';
   $cnt =0;
   // $handle = fopen($filename, 'rb');
   $handle = fopen($filename, 'rb');
   if ($handle === false) {
       return false;
   }
   while (!feof($handle)) {
       $buffer = fread($handle, $chunksize);
       echo $buffer;
       ob_flush();
       flush();
       if ($retbytes) {
           $cnt += strlen($buffer);
       }
   }
   $status = fclose($handle);
   if ($retbytes && $status) {
       return $cnt; // return num. bytes delivered like readfile() does.
   }
   return $status;
}

// Get hashed password of user using login Post parameter 'username'.
// For the sole purpose of Pear Auth, we need a single function to supply as the cryptType parameter
// to be used during the login process for table-based users.
function hashPasswordPearAuthLogin($password)
{
	return Authentication::hashPassword($password, '', $_POST['username']);
}

// Replacement function for PHP's array_fill_keys() if on a PHP version less than 5.2.0
if (!function_exists('array_fill_keys')) {
	function array_fill_keys($target, $value = '') {
		$filledArray = array();
		if (is_array($target)) {
			foreach ($target as $key => $val) {
				$filledArray[$val] = $value;
			}
		}
		return $filledArray;
	}
}

// Similar to PHP's natcasesort() but sorts by keys
function natcaseksort(&$array, $reverseSort=false)
{
	$original_keys_arr = array();
    $original_values_arr = array();
	$i = 0;
    foreach ($array as $key=>$value) {
        $original_keys_arr[$i] = $key;
        $original_values_arr[$i] = $value;
		unset($array[$key]); // Conserve memory
        $i++;
    }
    natcasesort($original_keys_arr);
	if ($reverseSort) {
		$original_keys_arr = array_reverse($original_keys_arr);
	}
    $result_arr = array();
    foreach ($original_keys_arr as $key=>$value) {
        $result_arr[$original_keys_arr[$key]] = $original_values_arr[$key];
    }
    $array = $result_arr;
}

// Obtain array of column names and their default value from specified database table.
// Column name will be array key and default value will be corresponding array value.
function getTableColumns($table)
{
	$sql = "describe `$table`";
	$q = db_query($sql);
	if (!$q) return false;
	$cols = array();
	while ($row = db_fetch_assoc($q)) {
		$cols[$row['Field']] = $row['Default'];
	}
	return $cols;
}


// Performs a case insensitive match of a substring in a string (used in logic)
function contains($haystack='', $needle='')
{
	return (stripos($haystack, $needle) !== false);
}


// Performs a case insensitive match of a substring in a string if NOT MATCHED (used in logic)
function not_contain($haystack='', $needle='')
{
	return (stripos($haystack, $needle) === false);
}


// Checks if string begins with a substring - case insensitive match (used in logic)
function starts_with($haystack='', $needle='')
{
    return ($needle === "" || stripos($haystack, $needle) === 0);
}


// Checks if string ends with a substring - case insensitive match (used in logic)
function ends_with($haystack='', $needle='')
{
    return starts_with(strrev($haystack), strrev($needle));
}

// Remove the ending --# at the end of a record name for DDE
function removeDDEending($record) {
	global $double_data_entry, $user_rights;
	return ($record != '' && isset($double_data_entry) && $double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0 && substr($record, -3) == '--'.$user_rights['double_data']) ? substr($record, 0, -3) : $record;
}

// Append the ending --# to the end of a record name for DDE
function addDDEending($record) {
	global $double_data_entry, $user_rights;
	return $record . (($record != '' && isset($double_data_entry) && $double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) ? '--'.$user_rights['double_data'] : '');
}

// Return boolean if GD2 library is installed
function gd2_enabled()
{
	if (extension_loaded('gd') && function_exists('gd_info')) {
		$ver_info = gd_info();
		preg_match('/\d/', $ver_info['GD Version'], $match);
        return ($match[0] >= 2);
	} else {
		return false;
	}
}

// Store a cookie by name, value, and expiration (0=will expire when session ends)
function savecookie()
{
	$args = func_get_args();
	call_user_func_array('Session::savecookie', $args);
}

// Delete a cookie by name
function deletecookie($name)
{
	$args = func_get_args();
	call_user_func_array('Session::deletecookie', $args);
}

// Conceal all digits of phone number except last 4 (leave non-digits as they were, but replace #'s with X's)
function concealPhone($phoneNumber)
{
	return preg_replace("/[\d]/", "X", substr($phoneNumber, 0, -4)) . substr($phoneNumber, -4);
}

// Format the display of a phone number (US and international)
function formatPhone($phoneNumber)
{
	// If number contains an extension (denoted by a comma between the number and extension), then separate here and add later
	$phoneExtension = "";
	if (strpos($phoneNumber, ",") !== false) {
		list ($phoneNumber, $phoneExtension) = explode(",", $phoneNumber, 2);
	}
	// Format the number
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    if (strlen($phoneNumber) > 10) {
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);
        $phoneNumber = '+'.$countryCode.' '.$areaCode.'-'.$nextThree.'-'.$lastFour;
    }
    elseif (strlen($phoneNumber) == 10) {
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);
        $phoneNumber = $areaCode.'-'.$nextThree.'-'.$lastFour;
    }
    elseif (strlen($phoneNumber) == 7) {
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);
        $phoneNumber = $nextThree.'-'.$lastFour;
    }
	// If has an extension, re-add it
	if ($phoneExtension != "") $phoneNumber .= ",$phoneExtension";
	// Return formatted number
    return $phoneNumber;
}

// function similar to is_numeric but works with commas as decimals
function is_numeric_comma($val)
{
	$regex_pattern = "/^[-+]?[0-9]*,?[0-9]+([eE][-+]?[0-9]+)?$/";
	// Run the value through the regex pattern
	preg_match($regex_pattern, $val, $regex_matches);
	// Was it validated? (If so, will have a value in 0 key in array returned.)
	return isset($regex_matches[0]);	
}

// chkNull function similar to JS used in calculations
function chkNull($val) 
{
	if (is_numeric_comma($val)) $val = str_replace(",", ".", $val);
	return (($val !== '0' && $val !== (int)0 && $val !== (float)0
			&& ($val == '' || $val == null || $val === 'NaN' || is_nan($val) || !is_numeric($val)))
		? NAN : $val*1);
}

// REDCap version of PHP's is_nan that returns TRUE for "" and NULL
function is_nan_null($val)
{
	return (is_nan($val) || $val === '' || $val === null || $val === 'NaN' || $val === 'NAN');
}

// Used to replace chkNull($arg0)==chkNull($arg1) and similar operational strings in calc fields to 
// avoid NaN==NaN and similar constructs that act counter-intuitively due to PHP internal logic.
// (NOTE: This is extremely complex, so please don't modify this unless you really know what you're doing.)
function chkNullCompare($arg0=null, $arg1=null, $operator=null) 
{
	$operatorBase = substr($operator, 0, 1);
	$arg0ChkNull = chkNull($arg0);
	$arg1ChkNull = chkNull($arg1);
	$arg0IsNan = is_nan($arg0ChkNull);
	$arg1IsNan = is_nan($arg1ChkNull);
	$arg0Func = $arg0IsNan ? "''" : $arg0ChkNull;
	$arg1Func = $arg1IsNan ? "''" : $arg1ChkNull;
	
	if (( $arg0IsNan && !$arg1IsNan && $operatorBase == '<') ||
		(!$arg0IsNan &&  $arg1IsNan && $operatorBase == '>')) 
	{
		// If num>NAN or NAN<num, then ALWAYS return False. This is simply to emulate how the JS version behaves in this situation.
		$code = "false";
	} else {
		// Replace any NANs with '' to allow proper/expected evaluation of the two entities.
		$code = "($arg0Func $operator $arg1Func)";
	}
	eval("\$value = $code;");
	return $value;
}

/**
 * CALCULATE MYSQL SPACE USAGE
 * Returns usage in bytes
 */
function getDbSpaceUsage()
{
	global $db;
	// Get table row counts and also total MySQL space used by REDCap (only consider 'redcap_*' tables)
	$total_mysql_space = 0;
	$q = db_query("SHOW TABLE STATUS from `$db` like 'redcap_%'");
	while ($row = db_fetch_assoc($q)) {
		if (strpos($row['Name'], "_20") === false) { // Ignore timestamped archive tables
			$total_mysql_space += $row['Data_length'] + $row['Index_length'];
		}
	}
	// Return total
	return $total_mysql_space;
}

// Convert an array to CSV string
function arrayToCsv($dataset, $returnKeysAsHeaders=true, $delimiter=",")
{
	// Open connection to create file in memory and write to it
	$fp = fopen('php://memory', "x+");
	// Add header row to CSV
	if ($returnKeysAsHeaders) {
		$dataset = array_values($dataset); // Reset keys since we'll use index 0
		fputcsv($fp, array_keys($dataset[0]), $delimiter);
	}
	// Loop through array and output line as CSV
	foreach ($dataset as $line) {
		fputcsv($fp, $line, $delimiter);
	}
	// Open file for reading and output to user
	fseek($fp, 0);
	$output = trim(stream_get_contents($fp));
	fclose($fp);
	return $output;
}

// Correct any mangled UTF-8 strings (often caused by incorrectly encoded Excel files)
function fixUTF8($string, $forceFix=false)
{
	if (!MBSTRING_ENABLED) return $string;
	if ($forceFix || (mb_detect_encoding($string) == 'UTF-8' && $string."" !== mb_convert_encoding($string, 'UTF-8', 'UTF-8')."")) {
		// Convert to true UTF-8 to remove black diamond characters
		$string = utf8_encode($string);
	}
	return $string;
}

// Encode the JSON in $item and correct any special characters that might be dropped
// returns false if bad
function json_encode_rc($item)
{
	$item_json = json_encode($item);
	$json_encode_failed = ($item_json === false || $item_json === null);
	if ($json_encode_failed && MBSTRING_ENABLED) {
		// Fix if any illegal characters are preventing json encoding
		if (is_array($item) || is_object($item)) {
			foreach ($item as &$val) {
				if (is_array($val) || is_object($val)) {
					// Recursive
					$val = json_encode_rc($val);
				} else {
					$val = fixUTF8($val, true);
				}
			}
		} else {
			$item = fixUTF8($item, true);
		}
		$item_json = json_encode($item, JSON_PARTIAL_OUTPUT_ON_ERROR);
	} elseif ($json_encode_failed && !MBSTRING_ENABLED) {
		$item_json = json_encode($item, JSON_PARTIAL_OUTPUT_ON_ERROR);
	}
	return $item_json;
}

// Function json_last_error_msg() is only available in PHP 5.5+, so add a surrogate here.
if (!function_exists('json_last_error_msg')) {
	function json_last_error_msg() {
		static $ERRORS = array(
			JSON_ERROR_NONE => 'No error',
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
			JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
			JSON_ERROR_SYNTAX => 'Syntax error',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
		);

		$error = json_last_error();
		return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
	}
}

// USAGE: For use in textarea/input for filter logic. textarea/input = "item"
//        1. Add this inside item: 'onkeydown' => 'logicSuggestSearchTip(this, event);'
//        2. Add this to the front of the item's onblur: 'logicHideSearchTip(this);'
//        3. Add this directly after item: logicAdd(<ITEM_ID>)
// location_id is id of the textarea or inpupt being focused upon
function logicAdd($location_id)
{
	return "<div id='LSC_id_$location_id' class='fs-item-parent fs-item'></div>";
}
function logicSearchResults($location_id, $searchTerm='', $draftModeSearch=false)
{
	global $lang, $Proj, $status, $draft_mode;
	$label_len = 15;

	// Clean $location_id
	$location_id = preg_replace("/[^a-zA-Z0-9-_]/", "", $location_id);

	// Clean search term
	$word = strip_tags(label_decode($searchTerm));
	// Remove any prepended [ bracket
	$precedingWord = "";
	if (strpos($word, "[") !== false && strpos($word, "][") === false) {
		list ($precedingWord, $word) = explode("[", $word, 2);
	}

	// Should we show draft mode fields instead of live fields
	$doDraftModeSearch = ($status > 0 && $draft_mode > 0 && $draftModeSearch);

	// If we're querying for Draft Mode fields, then show those. Otherwise, search live fields.
	$fields = $doDraftModeSearch ? array_keys($Proj->metadata_temp) : array_keys($Proj->metadata);

	// If prepended event name was provided, then use it to filter to fields in that event
	$showEvents = $Proj->longitudinal;
	$uniqueEventNames = $Proj->getUniqueEventNames();
	if ($Proj->longitudinal && strpos($word, "][") !== false) {
		// Prevent matching with event names since event name is prepended
		$showEvents = false;
		// Get the passed event name and true field name
		list ($searchTermEventName, $word) = explode("][", $word, 2);
		$searchTermEventId = array_search($searchTermEventName, $uniqueEventNames);
		// Limit fields to only this event's designated forms
		if (is_numeric($searchTermEventId)
			// Don't perform limiting if in draft mode (because we don't have a temp version of $Proj->eventForms for draft mode)
			&& !$doDraftModeSearch)
		{
			// Rebuild fields array from designated forms for this event
			$fields = array();
			foreach ($Proj->eventsForms[$searchTermEventId] as $this_form) {
				$fields = array_merge($fields, array_keys($Proj->forms[$this_form]['fields']));
			}
		}
	}

	// Create regex to apply for searching
	$regex = "/^".preg_quote($word).".*$/";

	// EVENTS
	$eventTip = "";
	if ($showEvents) {
		$eventInfo = $Proj->eventInfo;
		$matches = preg_grep($regex, $uniqueEventNames);
		foreach ($matches as $key => $value) {
			if ($eventTip == "") {
				$eventTip .= RCView::div(array("class" => "fs-item-ev-hdr fs-item", "id" => "LSC_ev_".$location_id), $lang['global_130']);
			}
			$event_name = substr($eventInfo[$key]['name_ext'], 0, $label_len);
			if (strlen($eventInfo[$key]['name_ext']) > $label_len) $event_name .= "...";
			$eventTip .= RCView::div(array("class" => "fs-item-ev fs-item", "id" => "LSC_ev_" . $location_id . "_" . $value, "onmousedown" => "logicSuggestClick('[$value]','$location_id');"), "[$value]&nbsp;&nbsp;<i>$event_name</i>");
		}
	}

	// FIELDS
	$num_fields = 0;
	$fieldTip = "";
	$matches = preg_grep($regex, $fields);
	foreach ($matches as $field_name) {
		// Get field attributes
		$row = $doDraftModeSearch ? $Proj->metadata_temp[$field_name] : $Proj->metadata[$field_name];
		// Is a checkbox? If so, add parentheses inside square brackets.
		$isCheckbox = $doDraftModeSearch ? ($Proj->metadata_temp[$field_name]['element_type'] == 'checkbox') : ($Proj->metadata[$field_name]['element_type'] == 'checkbox');
		$field_name_append = $isCheckbox ? "(?)" : "";
		// Skip descriptive fields
		if ($row['element_type'] == 'descriptive') continue;
		// Field header
		if ($num_fields == 0) {
			$fieldTipBg = ($Proj->longitudinal) ? "background-color:#e0e0e0;" : "";
			$fieldTip = RCView::div(array("class" => "fs-item-fn-hdr fs-item", "id" => "LSC_fn_".$location_id, "style" => $fieldTipBg), $lang['global_131']);
		}
		$row['element_label'] = strip_tags(label_decode($row['element_label']));
		$field_label = substr($row['element_label'], 0, $label_len);
		if (strlen($row['element_label']) > $label_len) $field_label .= "...";
		$fieldTip .= RCView::div(array("class" => "fs-item-fn fs-item", "id" => "LSC_fn_" . $location_id . "_" . $row['field_name'], "onmousedown" => "logicSuggestClick('[{$field_name}{$field_name_append}]','$location_id');"), "[$field_name]&nbsp;&nbsp;<i>$field_label</i>");
		$num_fields++;
	}
	
	// Matches for Smart Variables
	$smartVarTip = "";
	$smartVarsMatch = Piping::getSpecialTags($searchTerm);
	if (!empty($smartVarsMatch)) {
		$smartVarTip = RCView::div(array("class" => "fs-item-fn-hdr fs-item", "id" => "LSC_fn_".$location_id, "style" => "background-color:#e0e0e0;"), $lang['global_146']);
	}
	foreach ($smartVarsMatch as $field_name) {
		$smartVarTip .= RCView::div(array("class" => "fs-item-fn fs-item", "id" => "LSC_fn_" . $location_id . "_" . $field_name, "onmousedown" => "logicSuggestClick('[{$field_name}{$field_name_append}]','$location_id');"), "[$field_name]");
	}

	// Output the content
	if (trim($eventTip.$fieldTip.$smartVarTip) == "") {
		return "";
	} else {
		return $eventTip . $fieldTip . $smartVarTip;
	}
}

// Exactly like PHP explode() but done from the right instead of left
function explode_right($delimiter, $string, $limit=2)
{
	$array = array_map('strrev', explode(strrev($delimiter), strrev($string), $limit));
	krsort($array);
	return array_values($array);
}

// Add mime_content_type() function in not already in PHP
if(!function_exists('mime_content_type')) {

    function mime_content_type($filename) {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $ext = strtolower(array_pop(explode('.',$filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }
}

// Replacement for PHP's deprecated create_function function
function create_function_rc()
{
	$func_name = generate_function_name();
	$args = func_get_args();
	$eval_string = "function $func_name({$args[0]}){{$args[1]}}";
	if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
		try {
			eval($eval_string);
		} catch (ParseError $e) { }
	} else {
		$res = @eval($eval_string);
	}
	return $func_name;
}

// Generate a new function name
function generate_function_name()
{
	$found = false;
	$func_prefix = "redcap_func_";
	do {
		$this_func = $func_prefix . substr(sha1(rand()), 0, 16);
		if (!function_exists($this_func)) {
			$found = $this_func;
		}
	} while ($found === false);
	return $found;
}

// Convert CSV string to associative array with first row of CSV as array keys
function csvToArray($data)
{
	// Trim the data, just in case
	$data = trim($data);
	// Add CSV string to memory file so we can parse it into an array
	$h = fopen('php://memory', "x+");
	fwrite($h, $data);
	fseek($h, 0);
	// Now read the CSV file into an array
	$data = array();
	$csv_headers = null;
	while (($row = fgetcsv($h, 0, ",")) !== false) {
		if (!$csv_headers) {
			$csv_headers = $row;
		} else {
			// If row is completely blank, then skip it
			if (strlen(trim(implode("", $row))) > 0) {
				$data[] = array_combine($csv_headers, $row);
			}
		}
	}
	fclose($h);
	unset($csv_headers, $row);
	return $data;
}

// Same as PHP's shuffle() except it additionally maintains the array keys
function shuffle_assoc(&$array) 
{
	$keys = array_keys($array);
	shuffle($keys);
	foreach($keys as $key) {
		$new[$key] = $array[$key];
	}
	$array = $new;
	return true;
}

// A replacement function for using both date($format, mktime()) in PHP, which cannot go beyond year 2038.
// This function replaces date($format, mktime($hour, $minute, $second, $month, $day, $year))
function date_mktime($format='Y-m-d H:i:s', $hour=null, $minute=null, $second=null, $month=null, $day=null, $year=null)
{
	// Set any components that aren't set
	if ($hour === null) $hour = date("H");
	if ($minute === null) $minute = date("i");
	if ($second === null) $second = date("s");
	if ($month === null) $month = date("m");
	if ($day === null) $day = date("d");
	if ($year === null) $year = date("Y");
	// Days in current month
    $cal_days_in_month = false;
    if (function_exists('cal_days_in_month')) {
        $cal_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }
    if (!$cal_days_in_month) $cal_days_in_month = 28;
	// Deal with any values passed that are too large
    $sec_extra = 0;
    if ($day < 0) {
        $sec_extra += ($day*86400);
        $day = 0;
    }
    if ($day > $cal_days_in_month) {
        $days_over = $day - $cal_days_in_month;
        $sec_extra += ($days_over * 86400);
        $day = $cal_days_in_month;
    }
    if ($hour > 24 || $hour < 0) {
        $sec_extra += ($hour*3600);
        $hour = 0;
    }
    if ($minute > 60 || $minute < 0) {
        $second += ($minute*60);
        $minute = 0;
    }
	if ($second > 60 || $second < 0) {
		$sec_extra += $second;
		$second = 0;
	}
	// Format components
	$month = sprintf('%02d', $month);
	$day = sprintf('%02d', $day);
	$hour = sprintf('%02d', $hour);
	$minute = sprintf('%02d', $minute);
	$second = sprintf('%02d', $second);
    // Convert to preferred format
    // $strtotime = strtotime("$year-$month-$day $hour:$minute:$second")+floor($sec_extra);
    // return date($format, $strtotime);
	$date = DateTime::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $hour:$minute:$second");
	if ($sec_extra != 0) {
		$interval = new DateInterval('PT' . abs(floor($sec_extra)) . 'S');
		if ($sec_extra > 0) {
			$date->add($interval);
		} else {
			$date->sub($interval);
		}
	}
    return $date->format($format);
}

// Determine if value is an integer
function isinteger($val)
{
	$val = trim($val."");
	$regex = "/^[-+]?\b\d+\b$/";
	return ($val == (int)$val && preg_match($regex, $val));
}

// Update a row in the config table
function updateConfig($field, $val)
{
	$sql = "update redcap_config set value = '".db_escape($val)."' 
			where field_name = '".db_escape($field)."'";
	return db_query($sql);
}

// Create a short URL
function getREDCapShortUrl($urlToShorten, $customEnding="")
{
    $url_shortener_domain = "redcap.link";
    $params = array(
        "cdn_prefix"=>$url_shortener_domain,
        "url_long"=>$urlToShorten,
        "custom_ending"=>$customEnding,
        'hostkey_hash'=>Stats::getServerKeyHash()
    );
    $json = http_post("https://$url_shortener_domain/admin_shrink_url", $params, 30, 'application/json');
    $response = json_decode($json, true);
    if ($json == "" || !is_array($response)) {
        $response = array('error'=>"Unknown error. Please try again later.");
    }
    return $response;
}

// Provide a bunch of language variables from $lang and add them as JavaScript variables as part of the lang object (e.g., lang.global_01), all wrapped in <script> tags
function addLangToJS($langVars)
{
    global $lang;
    if (!is_array($langVars)) $langVars = array($langVars);
    $jsArray = array();
    foreach ($langVars as $thisVar) {
        if (!isset($lang[$thisVar])) continue;
		$jsArray[] = "lang.$thisVar='".js_escape($lang[$thisVar])."';";
    }
	if (!empty($jsArray)) {
		print "\n<script type=\"text/javascript\">\nif(typeof lang=='undefined'){var lang={}};\n".implode("\n", $jsArray)."\n</script>\n";
	}
}

// Replacement for PHP's log() function (returns NAN if $number is not a number)
function logRC($number=null, $base=M_E)
{
	if ($number == null) return NAN;
	// If missing numeric base, then do natural log
	if (!is_numeric($base)) $base = M_E;
	// Return log
	return log($number, $base);
}

// Determine if value is a number. If user uses is_number instead of is_numeric.
function isnumber($val)
{
	return is_numeric(trim($val));
}

// Round numbers to a given decimal point (returns FALSE if $number is not a number)
function roundRC($number=null,$precision=0)
{
	if ($number === null || $number === '') return NAN;
	return round($number, $precision);
}

// Round numbers up to a given decimal point
function roundup($number=null,$precision=0)
{
	if ($number === null || $number === '') return NAN;
	$factor = pow(10, -1 * $precision);
	return ceil($number / $factor) * $factor;
}

// Round numbers down to a given decimal point
function rounddown($number=null,$precision=0)
{
	if ($number === null || $number === '') return NAN;
	$factor = pow(10, -1 * $precision);
	return floor($number / $factor) * $factor;
}

// Find sum of numbers (each used as parameter)
function sum()
{
	$arg_list = func_get_args();
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : array_sum($arg_list));
}

// Find mean/average of numbers (each used as parameter)
function mean()
{
	$arg_list = func_get_args();
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : array_sum($arg_list) / count($arg_list));
}

/**
 * Median
 * number median ( number arg1, number arg2 [, number ...] )
 * number median ( array numbers )
 */
function median()
{
	$args = func_get_args();
	switch (func_num_args())
	{
		case 0:
			//trigger_error('median() requires at least one parameter',E_USER_WARNING);
			return NAN;
		case 1:
			// Fall through
			if (is_array($args)) {
				$args = array_pop($args);
			}
			// Median of a single number is the number itself
			if (!is_array($args)) {
				return (is_numeric($args) ? $args : NAN);
			}
		default:
			if (!is_array($args)) {
				//trigger_error('median() requires a list of numbers to operate on or an array of numbers',E_USER_NOTICE);
				return NAN;
			}
			// Make sure all are numbers
			foreach ($args as $argnum=>$arg)
			{
				// Trim it first
				$args[$argnum] = $arg = trim($arg);
				// Make sure it's a number, else remove it
				if (!is_numeric($arg)) unset($args[$argnum]);
			}
			if (empty($args)) return NAN;
			// Sort the args
			sort($args);
			$n = count($args);
			$h = intval($n / 2);
			// Determine the median
			if($n % 2 == 0) {
				$median = ($args[$h] + $args[$h-1]) / 2;
			} else {
				$median = $args[$h];
			}
			break;
	}
	return $median;
}

// Calculate standard deviation from an array of numerical values
function stdev()
{
	$std = func_get_args();
	switch (func_num_args())
	{
		case 0:
			//trigger_error('median() requires at least one parameter',E_USER_WARNING);
			return NAN;
		case 1:
			// Fall through
			if (is_array($std)) {
				$std = array_pop($std);
			}
		default:
			if (!is_array($std)) {
				return NAN;
			}
			// Make sure all are numbers
			foreach ($std as $argnum=>$arg)
			{
				// Trim it first
				$std[$argnum] = $arg = trim($arg);
				// Make sure it's a number, else remove it
				if (!is_numeric($arg)) unset($std[$argnum]);
			}
			// Stdev of one number or no numbers is undefined
			if (count($std) <= 1) return NAN;
			sort($std);
			$total = 0;
			// Count array elements
			$count_std = count($std);
			while(list($key,$val) = each($std))
			{
				$total += $val;
			}
			reset($std);
			$mean = $total/$count_std;
			$sum = 0;
			while(list($key,$val) = each($std))
			{
				$sum += pow(($val-$mean),2);
			}
			$var = sqrt($sum/($count_std-1));
			return $var;
			break;
	}
}

// Calculate the percentile of numerical array (array must already be numerically sorted).
// Uses "continuous sample quantile - type 7", which is the default for R and Microsoft Excel.
function percentile($data=array(), $percentile)
{
	if (0 < $percentile && $percentile < 1) {
		$p = $percentile;
	} else if (1 < $percentile && $percentile <= 100) {
		$p = $percentile * .01;
	} else {
		return "";
	}

	// Make sure all are numbers
	foreach ($data as $key=>$val)
	{
		// Trim it first
		$data[$key] = $val = trim($val);
		// Make sure it's a number, else remove it
		if (!is_numeric($val)) unset($data[$key]);
	}

	$count = count($data);
	$allindex = ($count - 1) * $p;
	$intvalindex = intval($allindex);
	$floatval = $allindex - $intvalindex;
	sort($data);
	if (!is_float($floatval)) {
		$result = $data[$intvalindex];
	} else {
		if ($count > $intvalindex+1) {
			$result = $floatval*($data[$intvalindex+1] - $data[$intvalindex]) + $data[$intvalindex];
		} else {
			$result = $data[$intvalindex];
		}
	}
	return $result;
}

// Return true if value is blank/null or is a Missing Data Code, else false
function isblankormissingcode($val)
{
	global $missingDataCodes;
	// If null, return true
	if ($val === null) return true;
	// Make sure it's a string
	$val .= "";
	// If blank, then return true
	if ($val == "") return true;
	// If a missing code, then return true
	return isset($missingDataCodes[$val]);
}


// Date Differencing Functions
function datediff($d1, $d2, $unit=null, $returnSigned=false, $returnSigned2=false)
{
	global $missingDataCodes;
	// Make sure Units are provided and that dates are trimmed
	if ($unit == null) return NAN;
	$d1 = trim($d1);
	$d2 = trim($d2);
	// Missing data codes
	if (isset($missingDataCodes) && !empty($missingDataCodes)) {
		if ($d1 != '' && isset($missingDataCodes[$d1])) $d1 = '';
		if ($d2 != '' && isset($missingDataCodes[$d2])) $d2 = '';
	}
	// If ymd, mdy, or dmy is used as the 4th parameter, then assume user is using Calculated field syntax
	// and assume that returnSignedValue is the 5th parameter.
	if (in_array(strtolower(trim($returnSigned)), array('ymd', 'dmy', 'mdy'))) {
		$returnSigned = $returnSigned2;
	}
	// Initialize parameters first
	if (strtolower($d1) === "today") $d1 = TODAY; elseif (strtolower($d1) === "now") $d1 = NOW;
	if (strtolower($d2) === "today") $d2 = TODAY; elseif (strtolower($d2) === "now") $d2 = NOW;
	$d1isToday = ($d1 == TODAY);
	$d2isToday = ($d2 == TODAY);
	$d1isNow = ($d1 == NOW);
	$d2isNow = ($d2 == NOW);
	$returnSigned = ($returnSigned === true || $returnSigned === 'true');
	// Determine data type of field ("date", "time", "datetime", or "datetime_seconds")
	$format_checkfield = ($d1isToday ? $d2 : $d1);
	$numcolons = substr_count($format_checkfield, ":");
	if ($numcolons == 1) {
		if (strpos($format_checkfield, "-") !== false) {
			$datatype = "datetime";
		} else {
			$datatype = "time";
		}
	} else if ($numcolons > 1) {
		$datatype = "datetime_seconds";
	} else {
		$datatype = "date";
	}
	// TIME only
	if ($datatype == "time" && !$d1isToday && !$d2isToday) {
		if ($d1isNow) {
			$d2 = "$d2:00";
			$d1 = substr($d1, -8);
		} elseif ($d2isNow) {
			$d1 = "$d1:00";
			$d2 = substr($d2, -8);
		}
		// Return in specified units
		return secondDiff(timeToSeconds($d1),timeToSeconds($d2),$unit,$returnSigned);
	}
	// DATE, DATETIME, or DATETIME_SECONDS
	// If using 'today' for either date, then set format accordingly
	if ($d1isToday) {
		if ($datatype == "time") {
			return NAN;
		} else {
			$d2 = substr($d2, 0, 10);
		}
	} elseif ($d2isToday) {
		if ($datatype == "time") {
			return NAN;
		} else {
			$d1 = substr($d1, 0, 10);
		}
	}
	// If a date[time][_seconds] field, then ensure it has dashes
	if (substr($datatype, 0, 4) == "date" && (strpos($d1, "-") === false || strpos($d2, "-") === false)) {
		return NAN;
	}
	// Make sure the date/time values aren't empty
	if ($d1 == "" || $d2 == "" || $d1 == null || $d2 == null) {
		return NAN;
	}
	// Make sure both values are same length/datatype
	if (strlen($d1) != strlen($d2)) {
		if (strlen($d1) > strlen($d2) && $d2 != '') {
			if (strlen($d1) == 16) {
				if (strlen($d2) == 10) $d2 .= " 00:00";
				$datatype = "datetime";
			} else if (strlen($d1) == 19) {
				if (strlen($d2) == 10) $d2 .= " 00:00";
				else if (strlen($d2) == 16) $d2 .= ":00";
				$datatype = "datetime_seconds";
			}
		} else if (strlen($d2) > strlen($d1) && $d1 != '') {
			if (strlen($d2) == 16) {
				if (strlen($d1) == 10) $d1 .= " 00:00";
				$datatype = "datetime";
			} else if (strlen($d2) == 19) {
				if (strlen($d1) == 10) $d1 .= " 00:00";
				else if (strlen($d1) == 16) $d1 .= ":00";
				$datatype = "datetime_seconds";
			}
		}
	}
	// Separate time if datetime or datetime_seconds
	$d1b = explode(" ", $d1);
	$d2b = explode(" ", $d2);
	// Split into date and time (in units of seconds)
	$d1 = $d1b[0];
	$d2 = $d2b[0];
	$d1sec = (!empty($d1b[1])) ? timeToSeconds($d1b[1]) : 0;
	$d2sec = (!empty($d2b[1])) ? timeToSeconds($d2b[1]) : 0;
	// Separate pieces of date component
	$dt1 = explode("-", $d1);
	$dt2 = explode("-", $d2);
	// Convert the dates to seconds (conversion varies due to dateformat)
	$dat1 = mktime(0,0,0,$dt1[1],$dt1[2],$dt1[0]) + $d1sec;
	$dat2 = mktime(0,0,0,$dt2[1],$dt2[2],$dt2[0]) + $d2sec;
	// Get the difference in seconds
	$sec = $dat2 - $dat1;
	if (!$returnSigned) $sec = abs($sec);
	// Return in specified units
	if ($unit == "s") {
		return $sec;
	} else if ($unit == "m") {
		return $sec/60;
	} else if ($unit == "h") {
		return $sec/3600;
	} else if ($unit == "d") {
		return ($datatype == "date" ? round($sec/86400) : $sec/86400);
	} else if ($unit == "M") {
		return $sec/2630016; // Use 1 month = 30.44 days
	} else if ($unit == "y") {
		return $sec/31556952; // Use 1 year = 365.2425 days
	}
	return NAN;
}
// Convert military time to seconds (i.e. number of seconds since midnight)
function timeToSeconds($time) {
	if (strpos($time, ":") === false) {
		return NAN;
	}
	$timearray = explode(":", $time);
	return ($timearray[0]*3600) + ($timearray[1]*60) + (!isset($timearray[2]) ? 0 : $timearray[2]*1);
}
// Return the difference of two number values in desired units converted from seconds
function secondDiff($time1,$time2,$unit,$returnSigned) {
	$sec = $time2-$time1;
	if (!$returnSigned) $sec = abs($sec);
	// Return in specified units
	if ($unit == "s") {
		return $sec;
	} else if ($unit == "m") {
		return $sec/60;
	} else if ($unit == "h") {
		return $sec/3600;
	} else if ($unit == "d") {
		return $sec/86400;
	} else if ($unit == "M") {
		return $sec/2630016; // Use 1 month = 30.44 days
	} else if ($unit == "y") {
		return $sec/31556952; // Use 1 year = 365.2425 days
	}
	return NAN;
}

// Find min of numbers (each used as parameter)
function minRC()
{
	$arg_list = func_get_args();
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : min($arg_list));
}

// Find max of numbers (each used as parameter)
function maxRC()
{
	$arg_list = func_get_args();
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : max($arg_list));
}

// Truncate text with ellipsis in middle
function truncateTextMiddle($this_label, $max_length=50, $chars_at_end=15) {
    if (strlen($this_label) > $max_length) {
        $this_label = rtrim(substr($this_label, 0, $max_length-$chars_at_end-2)) . "..." . ltrim(substr($this_label, -1*$chars_at_end));
    }
    return $this_label;
}

// Replace an HTML link with a plain text URL (using the "src" attribute of the HTML tag)
function replaceHtmlLinkWithUrl($html, $excludeLinkLabelForSurveyLinks=false)
{
    $foundMatch = preg_match_all('/<a[^>]+href\s*=\s*([\'"])(?<href>.+?)\1[^>]*>(.*)<\/a>/i', $html, $matches, PREG_PATTERN_ORDER);
    if ($foundMatch === false) return $html;
    // Loop through results to reformat the link to be: Label (URL)
    foreach ($matches[0] as $key=>$this_match) {
        $this_link = trim($matches['href'][$key]);
        // If this is a survey link or survey queue link (probably via [survey-link] or [survey-queue-link]), include link label or not? Do not include it for SMS.
        if ($excludeLinkLabelForSurveyLinks && Survey::isSurveyLink($this_link)) {
            $matches['href'][$key] = $this_link . " ";
        } else {
            $matches['href'][$key] = $matches[3][$key] . " ($this_link)";
        }
    }
    $html = str_replace($matches[0], $matches['href'], $html);
    return $html;
}

## LEGACY FUNCTIONS TO MAINTAIN IN CASE PLUGIN DEVELOPERS HAVE USED THEM
function getIpAddress() { return System::clientIpAddress(); }
function prep() { $args = func_get_args(); return call_user_func_array('db_escape', $args); }
function cleanHtml() { $args = func_get_args(); return call_user_func_array('js_escape', $args); }
function cleanHtml2() { $args = func_get_args();  return call_user_func_array('js_escape2', $args); }
function callJSfile() { $args = func_get_args();  return call_user_func_array('loadJS', $args); }
function form_renderer() { $args = func_get_args();  return call_user_func_array('DataEntry::renderForm', $args); }
function getAutoId() { $args = func_get_args();  return call_user_func_array('DataEntry::getAutoId', $args); }