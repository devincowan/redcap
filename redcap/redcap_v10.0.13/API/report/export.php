<?php
global $format, $returnFormat, $post;



// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}

// Get project attributes
$Proj = new Project();

// Get user rights
$user_rights_proj_user = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights_proj_user[PROJECT_ID][strtolower(USERID)];
unset($user_rights_proj_user);

// Does user have De-ID rights?
$deidRights = ($user_rights['data_export_tool'] == '2');
// De-Identification settings
$hashRecordID = ($deidRights);
$removeIdentifierFields = ($user_rights['data_export_tool'] == '3' || $deidRights);
$removeUnvalidatedTextFields = ($deidRights);
$removeNotesFields = ($deidRights);
$removeDateFields = ($deidRights);

// Export the data for this report
$content = DataExport::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'),
								false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields,
								$removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel'], 
								false, true, true, "", "", "", false, $post['csvDelimiter'], $post['decimalCharacter']);

// Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);