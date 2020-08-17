<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

loadJS('Calendar.js');

renderPageTitle("<div style='float:left;'>
					<i class=\"far fa-calendar-alt\"></i> {$lang['app_08']}
				 </div>
				 <div style='float:right;'>
					<i class=\"fas fa-film\"></i>
					<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=calendar02.mp4&referer=".SERVER_NAME."&title=Calendar','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">{$lang['calendar_11']} (7 {$lang['calendar_12']})</a>
				 </div><br><br>");

print  "<p>".$lang['calendar_02'];

//If multiple events exist, explain how participants may be scheduled and added to calendar
if ($longitudinal && $scheduling) {
	print  $lang['calendar_03']."<a href='".APP_PATH_WEBROOT."Calendar/scheduling.php?pid=$project_id' style='text-decoration:underline;'>".$lang['calendar_04']."</a> "
			. $lang['calendar_05'];
}
print  "</p>";

//If user is in DAG, only show calendar events from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['calendar_06']}</p>";
}

// IE CSS Hack - Render the following CSS if using IE
if ($isIE) {
	print '<style type="text/css">.toprightnumber {width: 107%}</style>';
}

// Render calendar table
include APP_PATH_DOCROOT . "Calendar/calendar_table.php";

print "<br><br>";

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';


// Function to add URL variables to tab links
function appendVarTab() {
	$val = "";
	if (isset($_GET['month']) && isset($_GET['year'])) {
		$val .= "&month={$_GET['month']}&year={$_GET['year']}";
	}
	if (isset($_GET['day']) && (!isset($_GET['view']) || $_GET['view'] != "month")) {
		$val .= "&day={$_GET['day']}";
	}
	return $val;
}
