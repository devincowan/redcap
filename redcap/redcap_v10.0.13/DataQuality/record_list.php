<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must have DQ Execute rights AND must be called via AJAX
if (!$isAjax || !$user_rights['data_quality_execute'] || $_SERVER['REQUEST_METHOD'] != 'POST') exit('ERROR!');

// Get list of all records
$recordNames = Records::getRecordList(PROJECT_ID, $user_rights['group_id'], false, false, null, 10000);
$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordNames);
// Build drop-down list
$recordList = "<option value=''>-- {$lang['reporting_37']} --</option>";;
if (empty($extra_record_labels)) {
	foreach ($recordNames as $this_record) {
		$recordList .= "<option value='$this_record'>$this_record</option>";
		unset($recordNames[$this_record]);
	}
} else {
	foreach ($recordNames as $this_record) {
		$recordList .= "<option value='$this_record'>$this_record {$extra_record_labels[$this_record]}</option>";
		unset($recordNames[$this_record]);
	}
}
// Output the list
print "<select id='dqRuleRecord' class='x-form-text x-form-field' style='max-width:300px;font-size:11px;margin-left:2px;padding-right:0;padding-top: 1px;height:19px;'>$recordList</select>";
