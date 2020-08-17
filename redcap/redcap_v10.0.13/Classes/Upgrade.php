<?php

class Upgrade
{
	// Set the URL where REDCap versions can be downloaded
	const UPGRADE_ENDPOINT = "https://redcap.vanderbilt.edu/plugins/redcap_consortium/versions.php";
	
	// Spoof fake versions of upgrade files to download (for testing purposes only)
	const SPOOF_UPGRADE_VERSIONS = false;
	
	// Should we spoof versions in the upgrade list (only for developers)
	public function spoofVersions()
	{
		return (self::SPOOF_UPGRADE_VERSIONS && isDev());
	}
	
	// Execute SQL (if any) to automatically fix any REDCap db tables that aren't correct. 
	// Return TRUE if any tables were fixed, NLL if nothing needs to be done, and FALSE if it tried and failed.
	public function autoFixTables()
	{
		global $redcap_updates_user, $redcap_updates_password;
		$success = $updatesCxnSuccessful = false;
		if (!self::hasDbStructurePrivileges()) return $success;
		// Get fixes to make
		$tableCheck = new SQLTableCheck();
		$sql_fixes = $tableCheck->build_table_fixes();		
		if ($sql_fixes == '') return null;
		// Disable FK checks because they might prevent everything from running
		$sql_fixes_fk = "SET SESSION SQL_SAFE_UPDATES = 0;\nSET FOREIGN_KEY_CHECKS = 0;\n$sql_fixes\nSET FOREIGN_KEY_CHECKS = 1;";
		// If the MySQl upgrades user/password exists, then use it instead of default db connection
		if ($redcap_updates_user != '' && $redcap_updates_password != '') {
			$updatesCxnSuccessful = db_connect(false, true);
		}
		// Execute the queries to fix the tables
		if (db_multi_query($sql_fixes_fk)) {
			// Run the check again to see if we still get the same results
			$tableCheck = new SQLTableCheck();
			$sql_fixes = $tableCheck->build_table_fixes();	
			$success = ($sql_fixes == '');
		}
		// Set the db connection back to the default again
		if ($updatesCxnSuccessful) db_connect();
		// Return success status
		return $success;
	}
	
	// Check if mysql user can perform structural table changes .
	// If $returnArrayOfPrivileges=TRUE, it will not return a boolean but an array denoting create, alter, and drop privilege status.
	public function hasDbStructurePrivileges($returnArrayOfPrivileges=false)
	{
		global $redcap_updates_user, $redcap_updates_password;
		$errors = false;
		$table_created = $table_altered = $table_dropped = null;
		// If the MySQl upgrades user/password exists, then use it instead of default db connection
		$updatesCxnSuccessful = false;
		if ($redcap_updates_user != '' && $redcap_updates_password != '') {
			$updatesCxnSuccessful = db_connect(false, true);
			if (!$updatesCxnSuccessful) {
				db_connect();
				return false;
			}
		}
		// Set random table to try to create
		$random_table = "redcap_ztemp_".date("YmdHis")."_".substr(md5(rand()), 0, 4);
		// Set sql to create, alter, and drop the table		
		$sql = "CREATE TABLE `$random_table` (
				`test_id` int(10) NOT NULL AUTO_INCREMENT,
				`project_id` int(10) DEFAULT NULL,
				PRIMARY KEY (`test_id`),
				KEY `project_id` (`project_id`)
				) ENGINE=InnoDB";
		$table_created = db_query($sql);
		if (!$table_created) $errors = true;
		if (!$errors) {
			$sql = "ALTER TABLE `$random_table` ADD `survey_id` INT(10) NULL DEFAULT NULL";
			$table_altered = db_query($sql);
			if (!$table_altered) $errors = true;
		}
		if (!$errors) {
			$sql = "ALTER TABLE `$random_table` ADD INDEX `survey_id` (`survey_id`)";
			$table_altered = db_query($sql);
			if (!$table_altered) $errors = true;
		}
		if (!$errors) {
			$sql = "ALTER TABLE `$random_table` DROP `survey_id`";
			$table_altered = db_query($sql);
			if (!$table_altered) $errors = true;
		}
		if ($table_created) {
			$sql = "DROP TABLE `$random_table`";
			$table_dropped = db_query($sql);
			if (!$table_dropped) $errors = true;
		}
		$arrayOfPrivileges = array('username'=>$GLOBALS['username'], 'create'=>$table_created, 'alter'=>$table_altered, 'drop'=>$table_dropped);
		// Set the db connection back to the default again
		if ($updatesCxnSuccessful) db_connect();
		// Return array
		if ($returnArrayOfPrivileges) {
			return $arrayOfPrivileges;
		}
		// Return boolean on success status
		else {
			return !$errors;
		}
	}
	
	// Display alert message in Control Center if any new REDCap versions are available
	public static function renderREDCapNewVersionAlert()
	{
		global $lang, $redcap_updates_available, $db, $username, $hostname, $redcap_updates_community_user, $redcap_updates_community_password;
		// First, ensure that one-click upgrade is possible
		if (!self::canPerformOneClickUpgrade()) {
			// Display message about how to enable one-click upgrade
			$html = '';
			if ($redcap_updates_available == '') {
				// Cannot communicate with consortium server
				$html .= "<div style='color:#C00000;text-indent:-0.7em;margin-left:3em;margin-top:6px;'><i class='far fa-times-circle'></i>  ".$lang['control_center_4658']."</div>";				
			}
			if (!self::isREDCapWebrootWritable()) {
				// REDCap webroot is not writable
				$html .= "<div style='color:#C00000;text-indent:-0.7em;margin-left:3em;margin-top:6px;'><i class='far fa-times-circle'></i>  ".$lang['control_center_4659'].dirname(APP_PATH_DOCROOT).DS.$lang['control_center_4660']."</div>";				
			}
			if (!self::hasDbStructurePrivileges()) {
				// MySQL user needs structure/definition privileges
				$new_user = db_escape($username)."2";
				$new_pass = generateRandomHash(15);
				$html .= "<div style='color:#C00000;text-indent:-0.7em;margin-left:3em;margin-top:6px;'><i class='far fa-times-circle'></i>  
						".$lang['control_center_4662'].
						"<div style='margin:5px 0;text-indent: 0;'>".$lang['control_center_4663']."</div>".
						"<code style='display:block;text-indent: 0;'>
							GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER ON `".str_replace("_","\_",db_escape($db))."`.* TO '".db_escape($username)."'@'$hostname';<br>
							FLUSH PRIVILEGES;
						</code>
						<div style='margin:5px 0;text-indent: 0;'>".$lang['control_center_4697']."</div>".
						"<code style='display:block;text-indent: 0;'>
							-- Create new user<br>
							CREATE USER '<b>$new_user</b>'@'$hostname' IDENTIFIED BY '<b>".db_escape($new_pass)."</b>';<br>
							GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER ON `".str_replace("_","\_",db_escape($db))."`.* TO '<b>$new_user</b>'@'$hostname';<br>
							FLUSH PRIVILEGES;<br>
							-- Add new user and password to the redcap_config table.<br>
							-- Note: The password in plain text will automatically get encrypted and re-saved by REDCap.<br>
							USE `$db`;<br>
							REPLACE INTO redcap_config (field_name, value) VALUES<br>
							('redcap_updates_user', '<b>".db_escape($new_user)."</b>'),<br>
							('redcap_updates_password', '<b>".db_escape($new_pass)."</b>'),<br>
							('redcap_updates_password_encrypted', '0');
						</code>".
						"</div>
						<div style='margin:10px 0 5px;text-indent:0;margin-left:3em;'>
							".$lang['control_center_4693']." '<code>$hostname</code>'" .$lang['control_center_4694']." 
							'<code>%.myinstitution.edu</code>'".$lang['period']." ".$lang['control_center_4695']."
						</div>";				
			}
			if ($html != '') {
				$html = "<div style='margin-top:6px;'>".$lang['control_center_4661']."</div>".$html;
			}
			// Blue recommendation div about enabling one-click upgrade
			$hideDiv = (UIState::getUIStateValue('controlcenter', 'index', 'easy_upgrade') == '1');
			$divClass = $hideDiv ? 'gray2' : 'blue';
			$jsHide = $hideDiv ? '0' : '1';
			$jsFunc = $hideDiv ? 'hideEasyUpgrade(0);' : '';
			$textHide = $hideDiv ? '' : $lang['ws_144'];
			print  "<div id='easy_upgrade_alert' class='$divClass' style='margin-bottom:20px;'>
						<div class='clearfix'>
							<div class='float-left'>
								<i class='fas fa-bell'></i> <span style='margin-left:3px;'>{$lang['control_center_4656']}</span>
								<a href='javascript:;' onclick=\"$(this).hide();$('#easy_upgrade_alert').removeClass('gray2').addClass('blue');$('.redcap-updates-rec').show();$jsFunc\" style='margin-left:3px;'>{$lang['control_center_4657']}</a>
							</div>
							<div class='float-right'>
								<a href='javascript:;' style='font-size:12px;' onclick='$(this).remove();hideEasyUpgrade($jsHide);'>$textHide</a>
							</div>
						</div>
						<div class='redcap-updates-rec' style='display:none;'>$html</div>
					</div>";
		} else {
			// Display versions to upgrade to (if any are available)
			$updates = json_decode($redcap_updates_available, true);
			if (!is_array($updates) || empty($updates)) return false;
			// Get list of files/dirs in REDCap webroot to refence below
			$webroot = dirname(APP_PATH_DOCROOT).DS;
			$redcapWebrootDirs = getDirFiles($webroot);
			// Loop through version array
			$links = "";
			$numVersions = 0;
			foreach ($updates as $type=>$updates2) {
				if ($type == 'current_branch' || empty($updates2) || !is_array($updates2)) continue;
				$links .= "<div class='float-left' style='max-width:49%;padding-right:10px;'><div style='font-weight:bold;margin:5px 0;'>";
				$links .= ($type == 'lts' ? $lang['control_center_4665'] : $lang['control_center_4666']);
				$changelogurl = ($type == 'lts' ? 'http://community.projectredcap.org/articles/14/changelog-lts.html' : 'https://community.projectredcap.org/articles/13/changelog-standard-release.html');
				$links .= "<span style='margin-left:20px;font-weight:normal;font-size:11px;'>{$lang['leftparen']}<a href='$changelogurl' target='_blank' style='font-size:11px;margin:0 1px;'>{$lang['control_center_4696']}</a>{$lang['rightparen']}</span>";
				$links .= "</div>";
				foreach ($updates2 as $id=>$attr) {
					// If version is <= current version, then skip
					if (self::getDecVersion($attr['version_number']) <= self::getDecVersion(REDCAP_VERSION)) continue;
					// If version directory already exists on the server, then skip
					$dirExists = (in_array("redcap_v" . $attr['version_number'], $redcapWebrootDirs) && is_dir($webroot . "redcap_v" . $attr['version_number']));
					if ($dirExists) continue;
					// Render this version as a row
					$numVersions++;
					$btn_class = ($id == 0 && $updates['current_branch'] == $type) ? "btn-success" : "btn-defaultrc";
					$links .= "<div style='margin:4px;'><button class='btn $btn_class btn-xs' onclick=\"oneClickUpgrade('{$attr['version_number']}');\">"
						   .  "<span class='fas fa-download'></span> {$attr['version_number']}</button> {$attr['release_notes']} (".DateTimeRC::format_user_datetime($attr['release_date'], 'Y-M-D_24').")</div>";
				}
				$links .= "</div>";
			}
			if ($numVersions > 0) {
				print  "<div class='yellow redcap-updates' style='margin-bottom:20px;'>
							<div style='color:#A00000;'>
								<i class='fas fa-bell'></i> <span style='margin-left:3px;font-weight:bold;'>
								{$lang['control_center_4669']}
								<a href='javascript:;' onclick=\"$(this).hide();$('.redcap-updates-list').show();\" style='margin-left:3px;'>{$lang['global_84']}</a>
							</div>
							<div class='redcap-updates-list' style='display:none;'>
								<div style='margin:8px 0 5px;'>{$lang['control_center_4668']}</div>
								<div class='clearfix'>$links</div>
							</div>
						</div>
						<div id='oneClickUpgradeDialog' class='simpleDialog' title='".js_escape($lang['control_center_4670'])."'>
							{$lang['control_center_4671']} <b>REDCap v<span id='oneClickUpgradeDialogVersion'></span></b> {$lang['control_center_4672']}
							<div style='margin:10px 0;'>
								{$lang['control_center_4675']}
								<a href='https://community.projectredcap.org' target='_blank' style='text-decoration:underline;'>{$lang['control_center_4676']}</a>{$lang['period']}
								{$lang['control_center_4677']}
							</div>
							<div style='margin:5px 0;'>
								<b style='font-weight: 600;'>{$lang['control_center_4673']}</b>&nbsp;
								<input id='redcap_updates_community_user' value=\"".htmlspecialchars($redcap_updates_community_user, ENT_QUOTES)."\" type='text' autocomplete='new-password' class='x-form-text x-form-field' style='max-width:150px;'>
							</div>
							<div style='margin:5px 0;'>
								<b style='font-weight: 600;'>{$lang['control_center_4674']}</b> &nbsp;
								<input id='redcap_updates_community_password' value=\"".htmlspecialchars($redcap_updates_community_password, ENT_QUOTES)."\" type='password' autocomplete='new-password' class='x-form-text x-form-field' style='max-width:150px;'>
							</div>"
							.(!System::usingAwsElasticBeanstalk() ? "<div style='font-size:12px;margin:15px 0 0;padding-top:5px;border-top:1px dashed #ccc;color:#C00000;'>{$lang['control_center_4688']}</div>" : "").
						"</div>
						<script type='text/javascript'>
						var redcap_updates_community_user = '".js_escape($redcap_updates_community_user)."';
						var redcap_updates_community_password = '".js_escape($redcap_updates_community_password)."'; 
						</script>";
			} else {
				// Blue recommendation div about enabling one-click upgrade
				print  "<div class='gray' style='margin-bottom:20px;'>
							{$lang['control_center_4678']}
							<a href='javascript:;' onclick=\"
								showProgress(1,1);
								setTimeout(function(){
									window.location.href=app_path_webroot+page+'?version_check_refresh=1';
								},500);
							\" style='margin-left:5px;font-size:12px;'>{$lang['control_center_4679']}</a>
						</div>";
			}
		}
	}
	
	// Hide the Easy Upgrade box on the main Control Center page
	public function hideEasyUpgrade($hide)
	{
		if ($hide == '1') {
			UIState::saveUIStateValue('controlcenter', 'index', 'easy_upgrade', '1');
		} else {
			UIState::removeUIStateValue('controlcenter', 'index', 'easy_upgrade');
		}
		return true;
	}
	
	// Check the upgrade_fast_versions.txt file to see if all versions that we're upgrading through are considered "fast".
	// If so, then let admin know that the server does not need to be taken offline during the upgrade.
	public static function isFastUpgrade($current_version, $redcap_version) 
	{
		// If using AWS Elastic Beanstalk, then always assume a fast upgrade (because it's really hard to do this otherwise)
		if (System::usingAwsElasticBeanstalk()) return true;
        // If using Google Cloud hosting (assumed from the use of Google Cloud Storage file hosting), then always assume a fast upgrade (because it's really hard to do this otherwise)
        if ($GLOBALS['edoc_storage_option'] == '3') return true;
		// Convert version number to numerical format
		$current_version_dec = self::getDecVersion($current_version);
		$redcap_version_dec  = self::getDecVersion($redcap_version);
		// Get the list of fast versions
		$fast_versions_file = file_get_contents(dirname(APP_PATH_DOCROOT)."/redcap_v{$redcap_version}/Resources/sql/upgrade_fast_versions.txt");
		$fast_versions = array();
		foreach (explode("\n", trim(str_replace("\r", "", $fast_versions_file))) as $key=>$this_version) {
			if (trim($this_version) == '') continue;
			$fast_versions[] = self::getDecVersion($this_version);
		}
		natcasesort($fast_versions);
		// Get listing of all upgrade files in directory
		$dh = opendir(dirname(APP_PATH_DOCROOT)."/redcap_v{$redcap_version}/Resources/sql/");
		$files = array();
		while (false != ($filename = readdir($dh))) { $files[] = $filename; }
		closedir($dh);
		natcasesort($files);
		// Parse through the files and select the ones we need
		$upgrade_sql = array();
		foreach ($files as $this_file) {
			if (substr($this_file, 0, 8) == "upgrade_" && (substr($this_file, -4) == ".sql" || substr($this_file, -4) == ".php")) {
				$this_file_version = self::getDecVersion(substr($this_file, 8, -4));
				if ($this_file_version > $current_version_dec && $this_file_version <= $redcap_version_dec) {
					$upgrade_sql[] = $this_file_version;
				}
			}
		}
		natcasesort($upgrade_sql);
		// Loop through all upgrade files. If ANY version if missing from $fast_versions, then return FALSE.
		foreach ($upgrade_sql as $this_version) {
			if (!in_array($this_version, $fast_versions)) return false;
		}
		// If ALL versions we're upgrading through (which have a corresponding upgrade PHP/SQL file)
		// are in upgrade_fast_versions.txt, then this IS a fast upgrade, so return TRUE.
		return true;
	}
	
	// Add leading zeroes inside version number (keep dots)
	public static function getLeadZeroVersion($dotVersion) {
		list ($one, $two, $three) = explode(".", $dotVersion);
		return $one . "." . sprintf("%02d", $two) . "." . sprintf("%02d", $three);
	}
	
	// Remove leading zeroes inside version number (keep dots)
	public static function removeLeadZeroVersion($leadZeroVersion) {
		list ($one, $two, $three) = explode(".", $leadZeroVersion);
		return $one . "." . ($two + 0) . "." . ($three + 0);
	}
	
	// Add leading zeroes inside version number (remove dots)
	function getDecVersion($dotVersion) {
		list ($one, $two, $three) = explode(".", $dotVersion);
		return $one . sprintf("%02d", $two) . sprintf("%02d", $three);
	}
	
	// For each version, run any PHP scripts in /Resources/files first, then run raw SQL files in that folder
	public static function getUpgradeSql($current_version, $redcap_version) 
	{
		global $db;
		// Check to make sure that the target REDCap directory is already on the server
		$upgradeVersionDirPath = dirname(APP_PATH_DOCROOT).DS."redcap_v".$redcap_version.DS;
		$versionDirectoryExists = (file_exists($upgradeVersionDirPath) && is_dir($upgradeVersionDirPath));
		if (!$versionDirectoryExists) return '';
		// Begin outputting SQL
		ob_start();
		print "-- --- SQL to upgrade REDCap to version $redcap_version from $current_version --- --\n";
		print "USE `$db`;\nSET SESSION SQL_SAFE_UPDATES = 0;\n";
		$current_version_dec = self::getDecVersion($current_version);
		$redcap_version_dec  = self::getDecVersion($redcap_version);
		// Get listing of all files in directory
		$pathToUpgradeFilesNewVersion = dirname(APP_PATH_DOCROOT).DS."redcap_v".$redcap_version.DS."Resources".DS."sql".DS;
		$dh = opendir($pathToUpgradeFilesNewVersion);
		$files = array();
		while (false != ($filename = readdir($dh))) { $files[] = $filename; }
		closedir($dh);
		natcasesort($files);
		// Parse through the files and select the ones we need
		$upgrade_sql = array();
		foreach ($files as $this_file) {
			if (substr($this_file, 0, 8) == "upgrade_" && (substr($this_file, -4) == ".sql" || substr($this_file, -4) == ".php")) {
				$this_file_version = self::getDecVersion(substr($this_file, 8, -4));
				if ($this_file_version > $current_version_dec && $this_file_version <= $redcap_version_dec) {
					$upgrade_sql[] = $this_file;
				}
			}
		}
		natcasesort($upgrade_sql);
		// Include all the SQL and PHP files to do cumulative upgrade
		foreach ($upgrade_sql as $this_file) {
			print "\n-- SQL for Version " . self::removeLeadZeroVersion(substr($this_file, 8, -4)) . " --\n";
			include $pathToUpgradeFilesNewVersion . $this_file;
		}
		print "\n\n-- Set date of upgrade --\n";
		print "UPDATE redcap_config SET value = CURDATE() WHERE field_name = 'redcap_last_install_date' LIMIT 1;\n";
		print "REPLACE INTO redcap_history_version (`date`, redcap_version) values (CURDATE(), '$redcap_version');\n";
		print "-- Set new version number --\n";
		print "UPDATE redcap_config SET value = '$redcap_version' WHERE field_name = 'redcap_version' LIMIT 1;\n";
		// Return the SQL
		return ob_get_clean();
	}
	
	// Return boolean if the "redcap" webroot directory is writable by the application
	public static function isREDCapWebrootWritable() 
	{
		return isDirWritable(dirname(APP_PATH_DOCROOT).DS);
	}
	
	// Return boolean if the "redcap" webroot directory is writable by the application
	public static function canPerformOneClickUpgrade()
	{
		// If we've never fetched the JSON version list, then do so now
		global $redcap_updates_available;
		if ($redcap_updates_available == '') {
			self::fetchREDCapVersionUpdatesList();
		}
		return ($redcap_updates_available != '' && self::isREDCapWebrootWritable() && self::hasDbStructurePrivileges());
	}
	
	/**
	 * Check if there is a newer REDCap version available
	 */
	public function fetchREDCapVersionUpdatesList()
	{
		global $redcap_updates_available, $allow_outbound_http;
		if (!$allow_outbound_http) return false;
		// Set URL endpoint
		$url = self::UPGRADE_ENDPOINT."?current_version=".REDCAP_VERSION;
		if (self::spoofVersions()) {
			$url .= "&dev=1";
		}
		// Make HTTP request
		$versionsJson = http_get($url);
		if ($versionsJson === false) return false;
		$versions = json_decode($versionsJson, true);
		if (!is_array($versions) || empty($versions)) {
			return false;
		} else {
			// Store the JSON string in config to display later in Control Center
			$redcap_updates_available = $versionsJson;
			updateConfig('redcap_updates_available', $versionsJson);
			updateConfig('redcap_updates_available_last_check', NOW);
			return $versions;
		}
	}
	
	// Perform the One-Click Upgrade
	public function performOneClickUpgrade()
	{
		global $redcap_updates_community_user, $redcap_updates_community_password, $allow_outbound_http;
		if (!$allow_outbound_http) {
            exit("ERROR: REDCap could not communicate with the REDCap Consortium server (".self::UPGRADE_ENDPOINT.") because the setting 
                'Can REDCap server access the web (make outbound HTTP calls)?' is set to 'No' on the REDCap General Configuration page. 
                The upgrade cannot be completed until that setting is changed.");
        }
		// If user/pass are blank, that means that were pre-filled and already saved in config
		if ($_POST['redcap_updates_community_password'] != '' && isset($_POST['decrypt_pass']) && $_POST['decrypt_pass'] == '1') {
			$_POST['redcap_updates_community_password'] = decrypt($_POST['redcap_updates_community_password']);
		}
		$comm_user = ($_POST['redcap_updates_community_user'] == '') ? $redcap_updates_community_user : $_POST['redcap_updates_community_user'];
		$comm_pass = ($_POST['redcap_updates_community_password'] == '') ? decrypt($redcap_updates_community_password) : $_POST['redcap_updates_community_password'];
		// Set URL endpoint
		$url = self::UPGRADE_ENDPOINT;
		if (self::spoofVersions()) {
			$url .= "?dev=1";
		}
		// Make HTTP request
		$params = array('username'=>$comm_user, 'password'=>$comm_pass, 'version'=>$_POST['version']);
		if (System::usingAwsElasticBeanstalk()) {
			// For AWS EB specifically, fetch the Install Zip instead
			$params['install'] = '1';
		}
		$response = http_post($url, $params);
		if ($response === false) {
			// FAILED
			exit("ERROR: For unknown reasons, REDCap could not communicate with the REDCap Consortium server (".self::UPGRADE_ENDPOINT."). The upgrade cannot be completed.");
		}
		// If it's in JSON format, then it is an error
		if (substr($response, 0, 1) == '{') {
			$jsonError = json_decode($response, true);		
			if (is_array($jsonError) && isset($jsonError['ERROR'])) {
				exit("ERROR: ".$jsonError['ERROR']);
			}
		}
		// The Community credentials were correct so store them in the config table
		updateConfig('redcap_updates_community_user', $comm_user);
		updateConfig('redcap_updates_community_password', encrypt($comm_pass));		
		// Place the file in the temp directory before extracting it
		$filename = APP_PATH_TEMP . date('YmdHis') . "_redcap_upgrade_" . substr(sha1(rand()), 0, 6) . ".zip";
		if (file_put_contents($filename, $response) === false) {
			// Upgrade zip couldn't be written to temp
			unlink($filename);
			exit("ERROR: For unknown reasons, the upgrade zip file could not be saved in the redcap/temp/ directory. The upgrade cannot be completed.");
		}
		// If running on AWS Elastic Beanstalk, then perform custom actions
		if (self::initUpgradeAwsElasticBeanstalk($filename)) {			
			// Call the shell file
		}
		// Reconfigure the inner structure of the zip before extracting
		else  {
			$zip = new \ZipArchive;
			if ($zip->open($filename) !== TRUE) {
				unlink($filename);
				exit("ERROR: For unknown reasons, the upgrade zip file could not be opened. The upgrade cannot be completed.");
			}
			$i = 0;
			$redcapVersionInZip = '';
			while ($item_name = $zip->getNameIndex($i))
			{
				if ($redcapVersionInZip == '' && strpos($item_name, "redcap/redcap_v") === 0) {
					$item_name_parts = explode("/", $item_name);
					$redcapVersionInZip = str_replace("redcap_v", "", $item_name_parts[1]);
				}
				if ($redcapVersionInZip != '' && strpos($item_name, "redcap/redcap_v{$redcapVersionInZip}/") === 0) {
					$item_name = str_replace("redcap/redcap_v{$redcapVersionInZip}/", "redcap_v{$_POST['version']}/", $item_name);
					$zip->renameIndex($i++, $item_name);
				} else {
					$zip->deleteIndex($i++);
				}
			}
			$zip->close();
			// Now extract the zip to the /redcap/ folder
			$zip = new \ZipArchive;
			if ($zip->open($filename) === TRUE) {
				$zip->extractTo(dirname(APP_PATH_DOCROOT).DS);
				$zip->close();
			}
		}
		// Remove temp file
		unlink($filename);
		exit("1");
	}
	
	// Get the current REDCap version from the config table
	public function currentREDCapVersion()
	{
		$sql = "select value from redcap_config where field_name = 'redcap_version'";
		$q = db_query($sql);
		return db_result($q, 0);
	}
	
	// Execute the upgrade SQL script to complete an upgrade
	public function executeUpgradeSQL($versionUpgradeTo=null)
	{
		global $redcap_updates_user, $redcap_updates_password;
		// Return specific code for AWS to take admin to the upgrade page
		if (System::usingAwsElasticBeanstalk() && PAGE != 'upgrade.php') {
			return "3";
		}
		// If this is not a fast upgrade, then redirect to upgrade module page
		if (!self::isFastUpgrade(REDCAP_VERSION, $versionUpgradeTo)) {
			return "2";
		} 
		// Obtain the upgrade SQL
		$upgradeSql = self::getUpgradeSql(REDCAP_VERSION, $versionUpgradeTo);
		// If the MySQl upgrades user/password exists, then use it instead of default db connection
		$updatesCxnSuccessful = false;
		if ($redcap_updates_user != '' && $redcap_updates_password != '') {
			$updatesCxnSuccessful = db_connect(false, true);
		}
		// Execute the upgrade SQL
		if ($upgradeSql != '' && db_multi_query($upgradeSql)) {
			// Check to make sure upgrade completed. If not, return error
			if (self::currentREDCapVersion() != $versionUpgradeTo) return "0";
			// Set the db connection back to the default again
			if ($updatesCxnSuccessful) db_connect();
			return "1";
		}
		// Error
		return "0";
	}

	// If REDCap is running on AWS Elastic Beanstalk, execute the AWS upgrade file
	public static function initUpgradeAwsElasticBeanstalk($upgradeFilePath)
	{
		if (System::usingAwsElasticBeanstalk()) 
		{
			// Copy our existing database.php into the zip file just downloaded (so that the whole install zip can be deployed)
			$databaseFileContents = file_get_contents(dirname(APP_PATH_DOCROOT).DS."database.php");
			$zip = new ZipArchive;
			$fileToModify = 'redcap/database.php';
			if ($databaseFileContents != '' && $zip->open($upgradeFilePath) === TRUE) {
				// Add the current contents of our server's database.php into the zip file's database.php
				$zip->deleteName($fileToModify);
				$zip->addFromString($fileToModify, $databaseFileContents);
				$zip->close();
			} else {
				return false;
			}
			// Execute the AWS upgrade shell script to finish the upgrade process
			$shellScriptPath = APP_PATH_DOCROOT . 'upgrade-aws-eb.sh';
			chmod($shellScriptPath, 0775);
			shell_exec("dos2unix $shellScriptPath");
			shell_exec("$shellScriptPath --file $upgradeFilePath");
			return true;
		}
		return false;
	}
}