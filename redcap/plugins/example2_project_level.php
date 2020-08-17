<?php
/**
 * PLUGIN NAME: Name Of The Plugin
 * DESCRIPTION: A brief description of the Plugin.
 * VERSION: The Plugin's Version Number, e.g.: 1.0
 * AUTHOR: Name Of The Plugin Author
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// OPTIONAL: Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().


// OPTIONAL: Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Your HTML page content goes here
?>
<h3 style="color:#800000;">
	Plugin Example Page (Project-Level)
</h3>
<p>
	Hello, <?php echo USERID ?>!<br><br>
	This is an example <b>project-level plugin</b> page. Project-level pages can be accessed properly by an
	authenticated user only if the project_id ("pid") exists in the URL (e.g. .../plugins/example_project.php?pid=2).
	You may place any content into the main window of this page via HTML. All project-level plugin pages will implement
	the user authentication method utilized by this particular project, as well as maintain the user's user rights when 
	accessing REDCap's standard modules.
	<br><br>
	To view a list of all PHP constants/variables that may be utilized by REDCap plugins, run the PHP function redcap_info(),
	as seen in the <a target="_blank" style="text-decoration:underline;" href="example1_redcap_info.php">example1_redcap_info.php</a> example file.
	<br><br> 
	To access REDCap's database tables in your plugin's PHP code, 
	you may use PHP's MySQLi extension (e.g. mysqli_* functions or mysqli object-oriented methods - see 
	<a target="_blank" style="text-decoration:underline;" href="http://php.net/manual/en/book.mysqli.php">http://php.net/manual/en/book.mysqli.php</a>).
	<?php if (version_compare(PHP_VERSION, '5.5.0', '<')) { ?>
		Also, since you are on a PHP version lower than PHP 5.5.0, you may alternatively use PHP's MySQL extension 
		(e.g. mysql_* functions - see <a target="_blank" style="text-decoration:underline;" href="http://php.net/manual/en/ref.mysql.php">http://php.net/manual/en/ref.mysql.php</a>).
		However, please be advised that the MySQL extension is deprecated in PHP 5.5.0 and later versions, so
		utilizing the MySQLi extension is more preferred for plugin development.
	<?php } ?>
</p>
<?php

// OPTIONAL: Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';