<?php

// Add Messenger system notification
$title = "Survey-specific email invitation fields";
$msg = "A new feature has been added to the Survey Settings page in the Online Designer: survey-specific email invitation fields. This can be enabled for any given survey, in which you can designate any email field in your project to use for sending survey invitations for that particular survey. Thus, you can collect several email addresses (e.g., for a student, a parent, and a teacher) and utilize each email for a different survey in the project. Then you can send each person an invitation to their own survey, after which all the survey responses get stored as one single record in the project.

The survey-specific email field is similar to the project-level email invitation field except that it is employed only for the survey where it has been enabled. In this way, the survey-level email can override an existing email address originally entered into the Participant List or the project-level email field (if used). This new feature allows users to have data entry workflows that require multiple surveys where the participant is different for each survey. (Note: The email field can exist on any instrument in the project, and you may use a different email field on each survey. You may also use the same email field for multiple surveys.)

See the Survey Settings page in the Online Designer to enable this feature for any of your surveys.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);