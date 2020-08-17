<?php



// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

//If user is not a super user, go back to Home page
if (!$super_user) redirect(APP_PATH_WEBROOT);

// Send email reminder to all users using weaker hash telling them to log in soon
if (isset($_POST['action']) && $_POST['action'] == 'reminder')
{
	// Enable the cron
	$sql = "update redcap_crons set cron_enabled = 'ENABLED', cron_last_run_start = null,
			cron_last_run_end = null where cron_name = 'UpdateUserPasswordAlgo'";
	if (db_query($sql)) {
		Logging::logEvent($sql,"redcap_crons","MANAGE","","","Email all users with weaker password hash");
		print '1';
	}
}

// Suspend all users using weaker hash
elseif (isset($_POST['action']) && $_POST['action'] == 'suspend')
{
	// Suspend them
	$sql = "update redcap_auth a, redcap_user_information i set i.user_suspended_time = '".NOW."'
			where a.username = i.username and a.legacy_hash = 1 and i.user_suspended_time is null";
	if (db_query($sql)) {
		Logging::logEvent($sql,"redcap_user_information","MANAGE","","","Suspend all users with weaker password hash");
		print '1';
	}

}