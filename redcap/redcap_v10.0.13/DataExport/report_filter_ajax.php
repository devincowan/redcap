<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Create array of all field validation types and their attributes
$allValTypes = getValTypes();
// Operator drop-down list (>, <, =, etc.)
print DataExport::outputLimiterOperatorDropdown($_POST['field_name'], '', $allValTypes);
// Value text box OR drop-down list (if multiple choice)
print DataExport::outputLimiterValueTextboxOrDropdown($_POST['field_name'], '');