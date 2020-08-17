<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// For reasons we can't explain, we need to flush the buffer now otherwise any fatal errors will return a blank response
print " ";
ob_flush();

## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
System::killConcurrentRequests(30, 5);

// Get first rule_id in the list that was sent
if (strstr($_POST['rule_ids'], ','))
{
	list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
}
// no comma, must be last rule
else
{
	list ($rule_id,) = explode(",", $_POST['rule_ids'], 2);
	$rule_ids = '';
}

// Make sure the rule_id is numeric
if (!is_numeric($rule_id) && !preg_match("/pd-\d{1,2}/", $rule_id)) exit('[]');

// Instantiate DataQuality object
$dq = new DataQuality();

// Get rule info
$rule_info = $dq->getRule($rule_id);

// Execute this rule
$dq->executeRule($rule_id, $_POST['record']);

// If fixing values
if (isset($_POST['action'])) {
	print json_encode_rc(array('title'=>$lang['dataqueries_294'],
			'payload'=>	RCView::div(array('style'=>'color:green;font-weight:bold;font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$dq->valuesFixed . " " . $lang['dataqueries_295']
						) .
						RCView::div(array('style'=>'font-size:13px;margin-top:20px;'),
							$lang['dataqueries_296']
						)
		  ));
	exit;
}

// Get the html for the results table data
list ($num_discrepancies, $resultsTableHtml, $resultsTableTitle) = $dq->displayResultsTable($rule_info);

// Check if any DAGs have discrepancies
$dag_json = array();
foreach ($dq->dag_discrepancies[$rule_id] as $group_id=>$count)
{
	$dag_json[] = $group_id.','.$count;
}

// Set formatting of discrepancy count
$num_discrepancies_formatted = User::number_format_user($num_discrepancies);
if ($num_discrepancies >= $dq->resultLimit) $num_discrepancies_formatted .= "+";

// Send back JSON
$json_array = array('rule_id'=>$rule_id, 'next_rule_ids'=>($rule_ids === null ? '' : $rule_ids), 'discrepancies'=>$num_discrepancies,
					'discrepancies_formatted'=>$num_discrepancies_formatted, 'dag_discrepancies'=>$dag_json,
					'title'=>($_POST['show_exclusions'] ? $resultsTableTitle : js_escape($resultsTableTitle)), 'payload'=>$resultsTableHtml);
print json_encode_rc($json_array);

// Log the event
if ($rule_ids == "") {
	// Only log this event for the LAST ajax request sent (since sometimes multiple serial requests are sent)
	Logging::logEvent(isset($sql_all) ? $sql_all : '',"redcap_data_quality_rules","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID,"Execute data quality rule(s)");
}
