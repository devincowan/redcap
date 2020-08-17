<?php


require dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!SUPER_USER && !ACCOUNT_MANAGER) exit('0');

if (isset($_POST['username']) && isset($_POST['suspend']) && ($_POST['suspend'] == '0' || $_POST['suspend'] == '1'))
{
	// Set values
	if ($_POST['suspend'] == '1') {
		$suspend = NOW;
		$logmsg = "Suspend user from REDCap";
	} else {
		$suspend = "";
		$logmsg = "Unsuspend user from REDCap";
	}
	// Update the user info table
	$sql = "update redcap_user_information set user_suspended_time = ".checkNull($suspend)."
			where username = '".db_escape($_POST['username'])."'";
	if (db_query($sql)) {
		// Logging
		Logging::logEvent($sql,"redcap_user_information","MANAGE",$_POST['username'],"username = '{$_POST['username']}'",$logmsg);
		// Give positive response
		exit("1");
	}
}

exit("0");