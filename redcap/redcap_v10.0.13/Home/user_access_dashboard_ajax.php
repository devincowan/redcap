<?php


// include config file
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

## For a given project_id, get last page view of each user in the log_view table
// Check for post parameter
if (!isset($_POST['username']) || !isset($_POST['pid']) || !is_numeric($_POST['pid'])) exit('[]');
// Query the log_view table for timestamps
$usersTs = array();
$sql = "select u.username, max(l.ts) as ts from redcap_log_view l, redcap_user_rights u
		where u.project_id = {$_POST['pid']} and u.username = l.user and l.event = 'PAGE_VIEW'
		and u.username = '".db_escape($_POST['username'])."' and l.project_id = u.project_id group by u.username";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
	$usersTs[] = array( 'user'=>strtolower($row['username']), 'time'=>str_replace('/','-',DateTimeRC::format_ts_from_ymd($row['ts'])),
						'time_ymd'=>$row['ts']);
}
if (empty($usersTs)) {
	$usersTs[] = array( 'user'=>strtolower($_POST['username']), 'time'=>$lang['index_37'],
						'time_ymd'=>$lang['index_37']);
}
// Return JSON
print json_encode_rc(array('timestamps'=>$usersTs));