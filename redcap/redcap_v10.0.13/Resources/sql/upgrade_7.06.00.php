<?php

// Add Messenger system notification
$title = "2 new save buttons";
$msg = "Two new save buttons have been added to data entry forms: 'Save & Exit Record' and 'Save & Go To Next Record'. The first will allow you to save a record and then quickly exit it so that you may choose another. The second will take you to the Record Home Page for the next record in the project after saving the current record ('next record' = the record that follows the current record as seen on the Record Status Dashboard).";
print Messenger::generateNewSystemNotificationSQL($title, $msg);

// Add Messenger system notification
$title = "Improved accessibility for surveys";
$msg = "Survey pages have now been improved in order to work better with assistive technology, such as screen readers. This will allow survey participants with visual impairments to take surveys much more easily.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);

// Add Messenger system notification
$title = "5 new action tags";
$msg = "REDCap now has 5 new action tags that you may utilize: @CHARLIMIT and @WORDLIMIT (limit the number of characters or words in a text/notes field), @RANDOMORDER (randomize the displayed order of multiple choice options), @HIDECHOICE (hide a multiple choice option that you wish to retire from use), and @NONEOFTHEABOVE (force a multiple choice option to be an exclusive option so that no other choices can be selected with it). Action tags are a great way to customize the data entry experience on forms and surveys. To learn more about them, <a href=\"".APP_PATH_WEBROOT."Design/action_tag_explain.php\" target=\"_blank\">view the full list of Action Tags</a> to see descriptions and examples.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);