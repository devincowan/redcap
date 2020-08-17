<?php

// Add Messenger system notification
$title = "Introducing \"Smart Variables\"";
$msg = "Smart Variables are dynamic variables that can be used in calculated fields, conditional/branching logic, and piping. Similar to using project variable names inside square brackets - e.g., [heart_rate], Smart Variables are also represented inside brackets - e.g., [user-name], [survey-link], [previous-event-name][weight], or [heart_rate][previous-instance]. But instead of pointing to data fields, Smart Variables are context-aware and thus adapt to the current situation. Some can be used with field variables or other Smart Variables, and some are meant to be used as stand-alone. There are many possibilities.

Smart Variables can reference things with regard to users, records, forms, surveys, events/arms, or repeating instances. To learn more, visit the <a href=\"".APP_PATH_WEBROOT."Design/smart_variable_explain.php\">Smart Variables informational page</a>.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);


## APPEND TEXT TO ALL EXISTING ASI SETUPS (for 'EMAIL' and 'SMS_INVITE_WEB' delivery type only)
// Get the client charset to deal with UTF-8 text
$charset = mysqli_get_charset($GLOBALS['rc_connection']);
$charset = $charset->charset;
if ($charset == "") $charset = "utf8";
// Gather all languages used by projects with ASIs
$sql = "select distinct p.project_language from redcap_surveys_scheduler ss, redcap_surveys s, redcap_projects p 
		where p.project_id = s.project_id and s.survey_id = ss.survey_id and ss.delivery_type in ('SMS_INVITE_WEB', 'EMAIL')";
$q = db_query($sql);
$allAsiLangs = $allAsiLangs2 = array();
while ($row = db_fetch_assoc($q)) {
	$thislang = Language::getLanguage($row['project_language']);
	// Store in an array the phrase for this language
	$allAsiLangs[$row['project_language']] = "\n\n{$thislang['survey_134']}\n[survey-link]\n\n{$thislang['survey_135']}\n[survey-url]\n\n{$thislang['survey_137']}";
	$allAsiLangs2[$row['project_language']] = " -- {$thislang['survey_956']} [survey-url]";
}
unset($thislang);
foreach ($allAsiLangs as $thislang=>$thisphrase) 
{
	print  "update redcap_surveys_scheduler ss, redcap_surveys s, redcap_projects p 
			set ss.email_content = concat(if(ss.email_content is null,'',ss.email_content), 
			convert(cast('".db_escape($thisphrase)."' as binary) using $charset))
			where p.project_id = s.project_id and s.survey_id = ss.survey_id 
			and p.project_language = '".db_escape($thislang)."' and ss.delivery_type = 'EMAIL';\n";
	print  "update redcap_surveys_scheduler ss, redcap_surveys s, redcap_projects p 
			set ss.email_content = concat(if(ss.email_content is null,'',ss.email_content), 
			convert(cast('".db_escape($allAsiLangs2[$thislang])."' as binary) using $charset))
			where p.project_id = s.project_id and s.survey_id = ss.survey_id 
			and p.project_language = '".db_escape($thislang)."' and ss.delivery_type = 'SMS_INVITE_WEB';\n";
}

## Make longitudinal custom record labels compatible with new piping
$pids840 = pre_query("select distinct p.project_id from redcap_projects p, redcap_events_arms a, redcap_events_metadata e 
where p.repeatforms = 1 and p.project_id = a.project_id and a.arm_id = e.arm_id and p.custom_record_label is not null and p.custom_record_label != ''
group by p.project_id having count(e.event_id) > 1");
print  "update redcap_projects set custom_record_label = replace(custom_record_label, '[', '[first-event-name][')
where project_id in ($pids840);\n";
