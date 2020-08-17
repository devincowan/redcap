<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Instantiate DataQuality object
$dq = new DataQuality();

// Output the html for the resolution log table
$resTableHtml = $dq->renderResolutionTable($_GET['status_type'], $_GET['field_rule_filter'],
					$_GET['event_id'], $_GET['group_id'], $_GET['assigned_user_id']);

// Get a count of unresolved issues
$queryStatuses = $dq->countDataResIssues();

// Return as JSON
print json_encode_rc(array('html'=>$resTableHtml, 'num_issues'=>$queryStatuses['OPEN']));