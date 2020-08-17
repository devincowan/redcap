<?php
global $post;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}

// Get user's user rights
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];

// Get project's primary key
$Proj = new Project(PROJECT_ID);
$table_pk = $Proj->table_pk;

$content = DataEntry::getAutoId();

# Send the response to the requestor
RestUtility::sendResponse(200, $content);