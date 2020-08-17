<?php
global $format, $returnFormat, $post;

$Proj = new Project(PROJECT_ID);
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$ur->setFormLevelPrivileges();

# Get REDCap XML file/string
$post['returnMetadataOnly'] = (isset($post['returnMetadataOnly']) && ($post['returnMetadataOnly'] == '1' || strtolower($post['returnMetadataOnly']."") === 'true'));
$post['exportDataAccessGroups'] = (isset($post['exportDataAccessGroups']) && ($post['exportDataAccessGroups'] == '1' || strtolower($post['exportDataAccessGroups']."") === 'true'));
$post['exportSurveyFields'] = (isset($post['exportSurveyFields']) && ($post['exportSurveyFields'] == '1' || strtolower($post['exportSurveyFields']."") === 'true'));
$post['exportFiles'] = (isset($post['exportFiles']) && ($post['exportFiles'] == '1' || strtolower($post['exportFiles']."") === 'true'));
$content = Project::getProjectXML($post['returnMetadataOnly'], $post['records'], $post['fields'], $post['events'], $user_rights['group_id'],
								  $post['exportDataAccessGroups'], $post['exportSurveyFields'], $post['filterLogic'], $post['exportFiles']);

# Logging
Logging::logEvent("", "redcap_projects", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export project XML (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);