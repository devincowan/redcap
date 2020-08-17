<?php



/**
 * PluginDocs Class used for methods dealing with documentation regarding
 * methods available for REDCap plugin/hook developers.
 */
class PluginDocs
{
	// Set name of class containing methods for plugins/hooks
	CONST REDCAP_CLASS = 'REDCap';

	// Set name of class containing hook functions
	CONST HOOKS_CLASS = 'Hooks';


	// REDCAP INFO: Display table of REDCap variables, constants, and settings (similar to php_info())
	public static function redcapInfo($displayInsideOtherPage=false, $displayHeaderLogo=true)
	{
		global $lang;
		// Obtain all REDCap-defined PHP contants
		$all_constants = get_defined_constants(true);
		$redcap_constants = $all_constants['user'];
		// Manually set a list as an array of contants and variables that would be helpful for REDCap developers
		$redcap_variables = array(
			'constants' => array('USERID', 'SUPER_USER', 'NOW', 'SERVER_NAME', 'PAGE_FULL', 'APP_PATH_WEBROOT', 'APP_PATH_SURVEY',
								 'APP_PATH_WEBROOT_PARENT', 'APP_PATH_WEBROOT_FULL',
								 'APP_PATH_SURVEY_FULL', 'APP_PATH_IMAGES', 'APP_PATH_CSS', 'APP_PATH_JS',
								 'APP_PATH_DOCROOT', 'APP_PATH_CLASSES', 'APP_PATH_TEMP', 'APP_PATH_WEBTOOLS', 'EDOC_PATH',
								 'CONSORTIUM_WEBSITE', 'SHARED_LIB_PATH'
			),
			'variables_system' => array_keys(System::getConfigVals())
		);
		// If authentication is disabled, then remove USERID and SUPER_USER as constants to be displayed
		if (!defined("USERID"))
		{
			$key = array_search('USERID', $redcap_variables['constants']);
			unset($redcap_variables['constants'][$key]);
			$key = array_search('SUPER_USER', $redcap_variables['constants']);
			unset($redcap_variables['constants'][$key]);
		}
		// Remove all system variables that exist as columns in redcap_projects table (the project-level values override them)
		$q = db_query("SHOW COLUMNS FROM redcap_projects");
		while ($row = db_fetch_assoc($q))
		{
			$col = $row['Field'];
			$key = array_search($col, $redcap_variables['variables_system']);
			if ($key !== false)
			{
				unset($redcap_variables['variables_system'][$key]);
			}
		}
		// Remove some system variables (may cause confusion with developer)
		$key = array_search('edoc_path', $redcap_variables['variables_system']);
		unset($redcap_variables['variables_system'][$key]);
		// Get system variables and add to $redcap_variables array
		$projectVals = Project::getProjectVals();
		// Get drop-down list options for all projects the current user has access to
		if (SUPER_USER) {
			$sql = "select project_id, app_title from redcap_projects order by trim(app_title), project_id";
		} else {
			$sql = "select p.project_id, trim(p.app_title) as app_title from redcap_projects p, redcap_user_rights u
					where p.project_id = u.project_id and u.username = '" . USERID . "' order by trim(p.app_title), p.project_id";
		}
		$q = db_query($sql);
		$projectList = "";
		while ($row = db_fetch_assoc($q))
		{
			$row['app_title'] = strip_tags(label_decode($row['app_title']));
			if (strlen($row['app_title']) > 80) {
				$row['app_title'] = trim(substr($row['app_title'], 0, 70)) . " ... " . trim(substr($row['app_title'], -15));
			}
			if ($row['app_title'] == "") {
				$row['app_title'] = $lang['create_project_82'];
			}
			$selected = (isset($_GET['pid']) && $_GET['pid'] == $row['project_id']) ? "selected" : "";
			$projectList .= "<option value='{$row['project_id']}' $selected>{$row['app_title']}</option>";
		}

		// Display the page
		if (!$displayInsideOtherPage) {
			// Page header
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->setPageTitle('redcap_info()');
			$objHtmlPage->PrintHeader(false);
		}
		?>
		<style type="text/css">
		body {background-color: #ffffff; color: #000000; font-size:13px;}
		body, td, th {font-family: 'Open Sans', Helvetica, Arial, sans-serif;}
		.rcicenter table { max-width:800px;table-layout:fixed; }
		.rcicenter table td { padding: 3px 2px; word-wrap:break-word; }
		.rcicenter th { text-align: center !important; }
		td, th { border: 1px solid #000000; font-size:13px;}
		h4 {font-weight:bold;margin-bottom:10px;}
		h5 {font-weight:bold;margin-bottom:10px;}
		.p {text-align: left;}
		.e {background-color: #aaa; font-weight: bold; color: #000000;}
		.h {background-color: #eee; font-weight: bold; color: #000000;}
		.v {background-color: #ddd; color: #000000;}
		.vr {background-color: #cccccc; text-align: right; color: #000000;}
		hr {width:100%;max-width: 600px; background-color: #cccccc; border: 0px; height: 1px; color: #000000;}
		</style>
		<div class="rcicenter">
		<!-- Title -->
		<?php if ($displayHeaderLogo) { ?>
		<style type="text/css">
		.rcicenter { margin: 15px 40px 50px; }
		</style>
		<table style="width:100%;">
			<tr class="h">
				<td>
					<div><img style="border:0;" src="<?php echo APP_PATH_IMAGES ?>redcap-logo.png" alt="REDCap Logo" /></div>
					<h4 class="p">REDCap Version <?php echo $GLOBALS['redcap_version'] ?></h4>
				</td>
			</tr>
		</table>
		<br>
		<?php } ?>
		<!-- Constants -->
		<h5 style="font-weight:bold;margin:20px 0 5px;">REDCap PHP Constants</h5>
		<table style="width:100%;">
		<?php foreach ($redcap_variables['constants'] as $this_constant) { ?>
			<tr>
				<td class="e"><?php echo $this_constant ?> </td>
				<td class="v"><?php echo $redcap_constants[$this_constant] ?> </td>
			</tr>
		<?php }  ?>
		</table>
		<br>
		<!-- System variables -->
		<h5 style="font-weight:bold;margin:20px 0 5px;">REDCap PHP Variables (System-Level)</h5>
		<table style="width:100%;">
			<tr>
				<td class="v" colspan="2">
					<b>The variables below are accessible in the global scope.</b>
				</td>
			</tr>
			<?php foreach ($redcap_variables['variables_system'] as $this_var) { ?>
				<tr>
					<td class="e"><?php echo $this_var ?> </td>
					<td class="v"><?php echo ($GLOBALS[$this_var] === false ? '0' : htmlspecialchars(html_entity_decode($GLOBALS[$this_var], ENT_QUOTES), ENT_QUOTES)) ?> </td>
				</tr>
			<?php }  ?>
		</table>
		<br>
		<!-- Project variables -->
		<h5 style="font-weight:bold;margin:20px 0 5px;" id="proj_vals">REDCap PHP Variables (Project-Level)</h5>
		<table style="width:100%;">
			<tr>
				<td class="v" colspan="2">
					<b>The variables below are accessible in the global scope.</b><br><br>
					Select one of the projects below that you currently
					have access to in order to view its project-level variables/values.<br>
					<select style="max-width:550px;" onchange="var url='<?php echo $_SERVER['REQUEST_URI'] ?>';if(this.value!=''){url += (url.indexOf('?') < 0 ? '?' : '&') + 'pid=' + this.value;}window.location.href=url+'#proj_vals';">
						<option value="">-- select project --</option>
						<?php echo $projectList ?>
					</select>
				</td>
			</tr>
			<?php if ($projectVals !== false) { ?>
				<?php foreach ($projectVals as $this_var=>$this_val) { ?>
					<?php if ($this_var == 'report_builder' || $this_var == 'custom_reports') continue; ?>
					<tr>
						<td class="e"><?php echo $this_var ?> </td>
						<td class="v"><?php echo ($this_val === false ? '0' : htmlspecialchars(html_entity_decode($this_val, ENT_QUOTES), ENT_QUOTES)) ?> </td>
					</tr>
				<?php }  ?>
			<?php }  ?>
		</table>
		</div>
		<?php
		if (!$displayInsideOtherPage) {
			?>
			</body>
			</html>
			<?php
		}
	}


	// Display HTML for all plugin methods and their attributes from REDCap class
	public static function displayPluginMethods()
	{
		global $hook_functions_file;

		// Get array of REDCap methods and their attributes
		$RedcapMethods = self::getPluginMethods(self::REDCAP_CLASS);

		// Get array of Hook methods and their attributes
		$HookMethods = self::getPluginMethods(self::HOOKS_CLASS);
		
		// Set certain hook methods from class to ignore
		$HookMethodsIgnore = array();
		
		/*
		// Loop through hooks to build menu
		$hookmenu = "";
		foreach ($HookMethods as $method=>$attr)
		{
			// Set menu item for this method
			$hookmenu .= RCView::div(array('class'=>'mm'),
							RCView::a(array('href'=>PAGE_FULL . '?HookMethod=' . $method, 'style'=>'font-weight:normal;background:transparent;font-size:13px;'),
								((isset($_GET['HookMethod']) && $_GET['HookMethod'] == $method) ? RCView::b($method) : $method)
							)
						);
			// If REDCapMethod parameter is not in query string, then skip the rest of the loop
			if (!isset($_GET['REDCapMethod']) || (isset($_GET['REDCapMethod']) && $_GET['REDCapMethod'] != $method)) continue;
		}
				 */
		// Loop through each method and display
		$html = "";
		$menu = "";
		$all_methods = array(self::REDCAP_CLASS=>$RedcapMethods, self::HOOKS_CLASS=>$HookMethods);
		foreach ($all_methods as $class_name=>$methods)
		{
			// Set section header for this menu group
			$menu .= RCView::h5(array('style'=>"margin-top:15px;font-weight:bold;", 'id'=>($class_name == self::HOOKS_CLASS ? "hook_functions" : "developer_methods")),
						($class_name == self::HOOKS_CLASS ? "Hook functions" : "Developer methods for<br>Plugins, Hooks, & External Modules")
					 );
			foreach ($methods as $method=>$attr)
			{
				// Ignore this hook method?
				if ($class_name == self::HOOKS_CLASS && in_array($method, $HookMethodsIgnore)) {
					continue;
				}
				// Set menu item for this method
				$menu .= RCView::div(array('class'=>'mm'),
							RCView::a(array('href'=>PAGE_FULL . '?'.$class_name.'Method=' . $method),
								((isset($_GET[$class_name.'Method']) && $_GET[$class_name.'Method'] == $method)
									? RCView::b(($class_name == self::REDCAP_CLASS ? $class_name."::" : '').$method)
									: ($class_name == self::REDCAP_CLASS ? $class_name."::" : '').$method
								)
							)
						 );
				// If [X]Method parameter is not in query string, then skip the rest of the loop
				if (!isset($_GET[$class_name.'Method']) || (isset($_GET[$class_name.'Method']) && $_GET[$class_name.'Method'] != $method)) continue;
				// Initialize this loop's html
				$m = "";
				// Method name
				$m .= RCView::div(array('style'=>'font-weight:bold;font-size:18px;float:left;margin:2px 0;'),
						($class_name == self::REDCAP_CLASS ? $class_name."::" : '').$method
					  );
				// REDCap version
				$m .= RCView::div(array('style'=>'float:right;color:#444;'), '(REDCap >= ' . $attr['VERSION'] . ')');
				$m .= RCView::div(array('style'=>'clear:both;'), '');
				// Summary
				$m .= 	RCView::div(array('style'=>'margin:20px 0;'),
							RCView::span(array('style'=>'color:#666;font-style:italic;'),
								($class_name == self::REDCAP_CLASS ? $class_name."::" : '').$method
							) .
							' &mdash; ' . $attr['SUMMARY']
						);
				// Description
				$m .= 	RCView::div(array('class'=>'h sub'),
							RCView::div(array('style'=>'font-weight:bold;'), "Description") .
							RCView::div(array('class'=>'w', 'style'=>''), $attr['DESCRIPTION']) .
							RCView::div(array('style'=>'margin:8px 5px;'), $attr['DESCRIPTION_TEXT'])
						);
				// Location of execution
				if (!empty($attr['LOCATION_OF_EXECUTION'])) {
					$m .= 	RCView::div(array('class'=>'h sub'),
								RCView::div(array('style'=>'font-weight:bold;'), "Location of Execution") .
								RCView::div(array('style'=>'margin:8px 5px;'), $attr['LOCATION_OF_EXECUTION'])
							);
				}
				// Parameters
				if (!empty($attr['PARAM'])) {
					$params = '';
					foreach ($attr['PARAM'] as $this_param) {
						// Bold the parameter name
						list ($this_param_name, $this_param) = explode(" - ", $this_param, 2);
						$params .= 	RCView::div(array('class'=>'pa'),
										RCView::span(array('style'=>'font-size:15px;font-family:monospace;color:#444;font-weight:bold;'), $this_param_name) .
										"<br>$this_param"
									);
					}
					$m .= 	RCView::div(array('class'=>'h sub'),
								RCView::div(array('style'=>'font-weight:bold;'), "Parameters") .
								$params
							);
				}
				// Return values
				$m .= 	RCView::div(array('class'=>'h sub'),
							RCView::div(array('style'=>'font-weight:bold;'), "Return Values") .
				RCView::div(array('style'=>'margin:8px 5px;'), isset($attr['RETURN']) ? $attr['RETURN'] : '')
						);
				// Restrictions
				if (!empty($attr['RESTRICTIONS'])) {
					$m .= 	RCView::div(array('class'=>'h sub'),
								RCView::div(array('style'=>'font-weight:bold;'), "Restrictions") .
								RCView::div(array('style'=>'margin:8px 5px;'), $attr['RESTRICTIONS'])
							);
				}
				// Examples
				if (!empty($attr['EXAMPLE'])) {
					$examples = '';
					foreach ($attr['EXAMPLE'] as $exampleNo=>$this_example) {
						//$examples .= RCView::div(array('class'=>''), "Example #$exampleNo: <br>$this_example");
						$examples .= RCView::div(array('class'=>'h sub example', 'style'=>'font-size:14px;'),
										RCView::div(array('style'=>'font-weight:bold;margin-bottom:8px;'), "Example #$exampleNo:") .
										$this_example
									 );
					}
					$m .= 	RCView::div(array('class'=>'', 'style'=>'font-weight:bold;'),
								"Examples"
							) .
							$examples;
				}
				// Wrap all in a table row
				$html .= RCView::div(array('class'=>'p'), $m);
			}
		}



		// INTRO TEXT
		if (!isset($_GET['REDCapMethod']) && !isset($_GET['HooksMethod']) && !isset($_GET['page'])) {
			ob_start();
			include APP_PATH_DOCROOT . "Plugins/intro.php";
			$html .= ob_get_clean();
		}
		// FAQ for Plugins
		elseif (isset($_GET['page']) && $_GET['page'] == 'faq_plugins') {
			ob_start();
			include APP_PATH_DOCROOT . "Plugins/faq_plugins.php";
			$html .= RCView::h3(array('style'=>'margin-bottom:35px;'), "Plugin FAQ (Frequently Asked Questions)") . ob_get_clean();
		}
		// FAQ for Hooks
		elseif (isset($_GET['page']) && $_GET['page'] == 'faq_hooks') {
			ob_start();
			include APP_PATH_DOCROOT . "Plugins/faq_hooks.php";
			$html .= RCView::h3(array('style'=>'margin-bottom:35px;'), "Hook FAQ (Frequently Asked Questions)") . ob_get_clean();
		}
		// External Module documentation (readme.md)
		elseif (defined("APP_PATH_EXTMOD") && isset($_GET['page']) && strpos($_GET['page'], 'ext_mods_docs') === 0) {
			$html .= \ExternalModules\ExternalModules::renderDocumentation();
		}
		// redcap_info()
		elseif (isset($_GET['page']) && $_GET['page'] == 'redcap_info') {
			ob_start();
			include APP_PATH_DOCROOT . "Plugins/redcap_info.php";
			$html .= RCView::h3(array('style'=>''), "redcap_info()") .
					 RCView::p(array(),
						"REDCap has a PHP function called redcap_info(), similar to phpinfo(), that can be called from
						anywhere within a REDCap page, plugin, or hook. Calling the redcap_info() function will automatically
						render a web page (when viewing the script in a web browser) that will display a table listing all
						PHP constants and variables that have been pre-defined by REDCap and are thus available for utilization in a hook or plugin.
						This provides plugin/hook developers with a head start by making known what resources are available that
						they may utilize in their code. The tables displayed below are the output of the redcap_info() function.") .
					 ob_get_clean();
		}

		// Check if redcap_connect.php exists on server. If not, give message to download it.
		$redcap_connect_file_dir = dirname(APP_PATH_DOCROOT) . DS;
		$redcap_connect_file = $redcap_connect_file_dir . "redcap_connect.php";
		if (file_exists($redcap_connect_file) && is_file($redcap_connect_file)) {
			$redcap_connect_check = "";
		} else {
			$redcap_connect_check = RCView::div(array('style'=>'margin:10px 0 15px;font-size:16px;padding:10px;border: 1px solid #FAD42A;color: #674100;background-color: #FFF7D2;'),
										RCView::div(array('style'=>'font-weight:bold;margin-bottom:8px;'),
											RCView::img(array('src'=>'exclamation_orange.png')) .
											"WARNING: Plugins will not work without redcap_connect.php!"
										) .
										"The file redcap_connect.php could NOT
										be found inside the following directory	on your web server: ".
										RCView::span(array('style'=>'color:#C00000;'), $redcap_connect_file_dir).".
										Without redcap_connect.php, plugins cannot work.
										You may obtain the file from the install zip of the latest version of REDCap, and then place the redcap_connect.php
										file inside the directory $redcap_connect_file_dir on your web server, after which your REDCap
										plugins should function normally."
									);
		}

		// Check if the Hook Functions file has been defined and exists on server. If not, give message to set it.
		$hook_functions_file = trim($hook_functions_file);
		$hook_functions_file_check = "";
		if (isset($hook_functions_file) && $hook_functions_file != '' && file_exists($hook_functions_file)) {
			$hook_functions_file_check = "";
		} elseif ($hook_functions_file == '') {
			$hook_functions_file_check = RCView::div(array('style'=>'margin:10px 0 15px;font-size:16px;padding:10px;border: 1px solid #FAD42A;color: #674100;background-color: #FFF7D2;'),
											RCView::div(array('style'=>'font-weight:bold;margin-bottom:8px;'),
												RCView::img(array('src'=>'exclamation_orange.png')) .
												"WARNING: Hooks will not work yet without the Hook Functions file!"
											) .
											"In order to utilize REDCap hooks, you must create your Hook Functions PHP file on your
											web server, and then provide the full path to that file on the
											" . RCView::a(array('style'=>'text-decoration: underline;', 'href'=>APP_PATH_WEBROOT."ControlCenter/general_settings.php#hook_functions_file-tr"), "General Configuration page") . "
											of the Control Center."
										);
		} elseif ($hook_functions_file != '' && !file_exists($hook_functions_file)) {
			$hook_functions_file_check = RCView::div(array('style'=>'margin:10px 0 15px;font-size:16px;padding:10px;border: 1px solid #FAD42A;color: #674100;background-color: #FFF7D2;'),
											RCView::div(array('style'=>'font-weight:bold;margin-bottom:8px;'),
												RCView::img(array('src'=>'exclamation_orange.png')) .
												"WARNING: Hook Functions file could not be found!"
											) .
											"In order to utilize REDCap hooks, you must create your Hook Functions PHP file on your
											web server, and then provide the full path to that file on the
											" . RCView::a(array('style'=>'text-decoration: underline;', 'href'=>APP_PATH_WEBROOT."ControlCenter/general_settings.php#hook_functions_file-tr"), "General Configuration page") . "
											of the Control Center. It appears that the full path of the Hook Functions file
											has already been defined; however, it cannot be found at the specified path
											(\"".RCView::span(array('style'=>'font-weight:bold;color:#C00000;'), $hook_functions_file)."\").
											Please check to find the location of that file."
										);
		}

		// Display the page
		$objHtmlPage = new HtmlPage();
		$objHtmlPage->setPageTitle("REDCap Plugin, Hook, & External Module Documentation");
		$objHtmlPage->PrintHeader(false);
		?>
        <style type="text/css">
        body {background-color: #ffffff; color: #000000;}
        body, td, th {font-family: 'Open Sans', Helvetica, Arial, sans-serif; font-size:15px;}
        a:link, a:visited, a:active {color: #000066; text-decoration: none; }
        a:hover {text-decoration: underline;}
        .center th { text-align: center !important; }
        td, th { border: 1px solid #000000; padding:5px; font-size:13px; }
        h4 {font-weight:bold;margin-bottom:10px;}
        h5 {font-weight:bold;margin-bottom:10px;}
        #faq h5 { color: #800000; }
        li { font-size: 13px; }
        .p {text-align: left;}
        .e {background-color: #aaa; color: #000000;}
        .h {background-color: #eee; color: #000000;}
        .v {background-color: #ddd; color: #000000;}
        .w {background-color: #fff; color: #000000; margin:10px 5px; padding:8px 10px; border:1px solid #ccc; font-size: 15px; font-family: monospace;}
        .pa {margin:4px 6px 4px 25px;text-indent:-25px; padding:5px;}
        .vr {background-color: #cccccc; text-align: right; color: #000000;}
        img {border: 0px;}
        hr {width:100%;max-width: 600px; background-color: #cccccc; border: 0px; height: 1px; color: #000000;}
        .mm {
            border-bottom-color:#AAAAAA;
            border-bottom-style:dotted;
            border-width:0 0 1px;
            margin:1px;
            padding: 3px;
        }
        div.sub { margin:5px 0 15px;border:1px solid #bbb;padding:5px; }
        pre {margin: 10px 5px; font-family: monospace; padding:10px; color: #555; background-color: #ffffff; border:1px solid #ccc; font-size:12px;}
        </style>
		<!-- top navbar -->
		<nav class="rcproject-navbar navbar navbar-light navbar-expand-md fixed-top" style="background-color:#f8f8f8;border-bottom:1px solid #e7e7e7;padding:10px;" role="navigation">
			<div class="navbar-header">
				<span class="navbar-brand" style="max-width:78%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-right:0;">Plugin, Hook, & External Module Documentation</span>
				<button type="button" class="navbar-toggler" onclick="$('#left-menu').toggleClass('d-sm-block').toggleClass('d-none');">
					<span class="navbar-toggler-icon"></span>
				</button>
			</div>
		</nav>
		<div class="center row container-fluid" style="margin:0;padding:0;">
			<div id="left-menu" class="h d-none d-md-block col-md-4 col-lg-3"  style="width:260px;padding:10px 10px 100px;border-right:1px solid #aaa;">
				<!-- Logo -->
				<div class="" style="margin:10px 0 15px;">
					<a href="<?php echo APP_PATH_WEBROOT_PARENT ?>index.php?action=myprojects" style="background-color:transparent;"><img
						border="0" src="<?php echo APP_PATH_IMAGES ?>redcap-logo.png" alt="REDCap Logo" /></a>
				</div>
				<div style="margin-top:10px;"></div>
				<!-- Links back to REDCap -->
				<div class="mm"><a href="<?php echo APP_PATH_WEBROOT_PARENT ?>index.php?action=myprojects" style="font-weight:normal;background:transparent;font-size:13px;">My Projects</a></div>
				<?php if (SUPER_USER) { ?>
				<div class="mm"><a href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/" style="font-weight:normal;background:transparent;font-size:13px;">Control Center</a></div>
				<?php } ?>
				<!-- Basics Links -->
				<h5 style="margin-top:15px;font-weight:bold;">Plugins, Hooks, & External Modules</h5>
				<div class="mm"><a href="<?php echo PAGE_FULL ?>" style="font-weight:normal;background:transparent;font-size:13px;">Introduction</a></div>
				<div class="mm"><a href="<?php echo PAGE_FULL ?>?page=faq_plugins" style="font-weight:normal;background:transparent;font-size:13px;">FAQ for Plugins</a></div>
				<div class="mm"><a href="<?php echo PAGE_FULL ?>?page=faq_hooks" style="font-weight:normal;background:transparent;font-size:13px;">FAQ for Hooks</a></div>
				<?php if (defined("APP_PATH_EXTMOD")) { ?>
				<div class="mm"><a href="<?php echo PAGE_FULL ?>?page=ext_mods_docs/" style="font-weight:normal;background:transparent;font-size:13px;">External Modules Documentation</a></div>
				<?php } ?>
				<div class="mm"><a href="<?php echo PAGE_FULL ?>?page=redcap_info" style="font-weight:normal;background:transparent;font-size:13px;">redcap_info()</a></div>
				<?php print $menu ?>
			</div>
			<!-- Main content window -->
			<div class="col-12 col-md-8 col-lg-9" style="padding:10px 10px 10px;border:0;max-width:900px;">
				<div class="h d-none d-sm-block" style="padding:10px 10px;border:1px solid #000;margin:0 0 20px;">
					<h4 style="font-weight:bold;margin:0;line-height: 32px;"><span style="color:#A00000;">REDCap Developer Tools:</span><br>Documentation for Plugins, Hooks, & External Modules</h4>
					<h5 style="margin-bottom:0;font-weight:normal;">REDCap Version <?php echo $GLOBALS['redcap_version'] ?></h5>
				</div>
				<div class="d-block d-sm-none" style="margin-top:60px;"></div>
				<div class="col-12">
					<?php print $redcap_connect_check . $hook_functions_file_check ?>
					<?php print $html ?>
				</div>
			</div>
			<!-- Footer -->
			<div class="col-12" style="padding:20px 0;border:0;color:#aaa;text-align:center;font-size:12px;">
				<a href="https://projectredcap.org" style="color:#aaa;text-decoration:none;font-weight:normal;font-size:12px;" target="_blank">REDCap <?php print REDCAP_VERSION ?></a> - &copy; <?php print date("Y") ?> Vanderbilt University
			</div>
		</div>

		</body>
		</html>
		<?php
	}


	// Return array of all plugin methods and their attributes from REDCap class
	public static function getPluginMethods($class)
	{
		// Set valid attributes to look for (e.g., SUMMMARY, PARAM)
		$validAttributes = array('SUMMARY', 'DESCRIPTION', 'DESCRIPTION_TEXT', 'PARAM',
								 'RETURN', 'RESTRICTIONS', 'VERSION', 'EXAMPLE', 'LOCATION_OF_EXECUTION');

		// Get list of all methods available to plugins in REDCap class
		$pluginMethods = get_class_methods($class);
		// Sort by method name
		sort($pluginMethods);

		// Set the previous token type in each loop (ignore white spaces)
		$prevToken = $prevValue = $prevDocComment = null;

		// Place all method documentation (i.e. comments) into array
		$pluginMethodDocs = array();

		$tokens = token_get_all(file_get_contents(APP_PATH_CLASSES . $class . ".php"));
		$comments = array();
		foreach ($tokens as $token)
		{
			// Get current token and its value
			$thisToken = $token[0];
			$thisValue = isset($token[1]) ? $token[1] : '';
			// If this token is the method name, get previous doc_comment for it to parse it
			if ($prevToken == T_FUNCTION && $thisToken == T_STRING && in_array($thisValue, $pluginMethods)) {
				if($prevDocComment){
					$pluginMethodDocs[$thisValue] = $prevDocComment;
					$prevDocComment = null; // Prevent the docs from getting assigned to the next function as well.
				}
			}
			// If a doc_comment, store it for future loops
			elseif ($thisToken == T_DOC_COMMENT) {
				// Parse comment into multiple lines
				$thisCommentArray = array();
				$exampleNum = 0;
				foreach (explode("\n", $thisValue) as $thisRow)
				{
					$thisRowOriginal = $thisRow;
					// Trim it
					$thisRow = trim($thisRow);
					// Skip first and last lines
					if ($thisRow == '/**' || $thisRow == '*/') continue;
					// Remove "* " from beginning of line
					if (substr($thisRow, 0, 2) == '* ') $thisRow = trim(substr($thisRow, 2));
					// Get attribute and its value (i.e. description, params)
					if(strstr($thisRow, ': '))
					{
						list ($thisAttr, $thisRow2) = explode(": ", $thisRow, 2);
					}
					else
					{
						$thisAttr = $thisRow;
					}
					if (in_array($thisAttr, $validAttributes)) {
						// Set current row
						$thisRow = trim($thisRow2);
						if ($thisAttr == 'EXAMPLE') $exampleNum++;
					} elseif (isset($prevAttr) && $prevAttr == 'EXAMPLE') {
						// Set row that belongs to previous example row but does NOT begin with "EXAMPLE:"
						$thisAttr = $prevAttr;
						// Get row before being trimmed
						$thisRow = $thisRowOriginal;
					}
					// If a param, then add as an array element
					if ($thisAttr == 'PARAM') {
						// Add nth line for this attribute to array
						$thisCommentArray[$thisAttr][] = $thisRow;
					// If anexample, then add as an array element
					} elseif ($thisAttr == 'EXAMPLE') {
						// Add nth line for this attribute to array
						$thisCommentArray[$thisAttr][$exampleNum][] = $thisRow;
					} else {
						// Add line to array
						$thisCommentArray[$thisAttr] = $thisRow;
					}
					// Set for next loop
					$prevAttr = $thisAttr;
				}
			   $prevDocComment = $thisCommentArray;
			}
			// Set token for next loop (unless current token is whitespace)
			if ($thisToken != T_WHITESPACE) {
				$prevToken = $thisToken;
				$prevValue = $thisValue;
			}
		}
		// Go back and loop through all examples and merge the arrays into single html block
		foreach ($pluginMethodDocs as $method=>$attr) {
			if (isset($attr['EXAMPLE'])) {
				// Loop through all examples
				foreach ($attr['EXAMPLE'] as $exnum=>$exrows) {
					$pluginMethodDocs[$method]['EXAMPLE'][$exnum] = trim(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", implode("\n", $exrows)));
				}
			}
		}
		// Ignore the "call" method for the Hooks class since it is not a hook
		if ($class == self::HOOKS_CLASS) {
			unset($pluginMethodDocs['call']);
		}
		// Sort by method name (key)
		ksort($pluginMethodDocs);
		// Return array of methods and their attributes
		return $pluginMethodDocs;
	}


}
