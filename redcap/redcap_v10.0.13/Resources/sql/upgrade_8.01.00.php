<?php

// Add Messenger system notification
$title = "New action tags";
$msg = "REDCap now has 2 new action tags that you may utilize: @MAXCHECKED (limit the number of choices that can be selected at a given time for a checkbox field) and @MAXCHOICE (causes one or more specified choices to be disabled/non-usable for a checkbox, radio button, or drop-down field after a specified amount of records have been saved with that choice). To learn more about Action Tags, <a href=\"".APP_PATH_WEBROOT."Design/action_tag_explain.php\" target=\"_blank\">view the full list of Action Tags</a> to see descriptions and examples.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);

// Add Messenger system notification
$title = "Save & Return Later w/o Return Code";
$msg = "Surveys using the \"Save & Return Later\" feature may now allow respondents to return where they left off on their survey without having to provide a return code. This option adds a great convenience, and can be enabled on the Survey Settings page for any survey instrument. Note: For privacy reasons, it is HIGHLY recommended that you do not use this feature if you are administering a survey that collects identifying information (e.g., PII, PHI).";
print Messenger::generateNewSystemNotificationSQL($title, $msg);

print "-- Update to external modules
update redcap_external_module_settings set type='json-array' where type='json';";