<?php

// Add REDCap Messenger system notification
$title = "Introducing REDCap Messenger";
$msg = "REDCap Messenger is a communication platform built directly into REDCap. It allows REDCap users to communicate easily and efficiently with each other in a secure manner. At its core, REDCap Messenger is a chat application that enables REDCap users to send one-on-one direct messages or to organize group conversations with other REDCap users.\n\nWe invite you to go ahead and try it out now. Just click the + icon next to \"Conversations\" above to create your first conversation. To learn more, please visit the <a href=\"".APP_PATH_WEBROOT."Messenger/info.php\">REDCap Messenger informational page</a>.";
if (!isVanderbilt()) print Messenger::generateNewSystemNotificationSQL($title, $msg);