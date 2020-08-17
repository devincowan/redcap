<?php

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

$logic = $_POST['logic'];

// Split record-event_id
$posLastDash = strrpos($_POST['record'], '||');
if ($posLastDash !== false) {
    $record = substr($_POST['record'], 0, $posLastDash);
    $event_id = substr($_POST['record'], $posLastDash + 2);
} else {
    $record = $_POST['record'];
    $event_id = null;
}

// If receiving record-event_id, then split it up to prepend variables in the logic with unique events names (to apply it to a specific event)
if ($longitudinal && isset($_POST['hasrecordevent']) && $_POST['hasrecordevent'] == '1') {
    $logic = Piping::pipeSpecialTags($logic, $project_id, $record, $event_id, 1, USERID, true);
    if (trim($logic) != '') {
        $logic = LogicTester::logicPrependEventName($logic, $Proj->getUniqueEventNames($event_id), $Proj);
    }
}

// Obtain array of error fields that are not real fields
$error_fields = Design::validateBranchingCalc($logic, true);

if (empty($error_fields))
{
	$newBranchingIsValid = LogicTester::isValid($logic);
	if ($logic !== "" && !$newBranchingIsValid)
	{
		// Default: Contains syntax errors (general)
		$response = $lang['dataqueries_47'].$lang['colon'].$lang['dataqueries_99'];
		// Check the logic for illegal functions
		$parser = new LogicParser();
		try {
			$parser->parse($logic, null, true, false, false, true);
		} catch (LogicException $e) {
			if (count($parser->illegalFunctionsAttempted) !== 0) {
				// Contains illegal functions
				$response = "Illegal functions:\n-".implode("\n- ", $parser->illegalFunctionsAttempted);
			}
		}
		// Check if the previous branching logic was valid (if existed)
		$response2 = "";
		if ($branching_logic != "")
		{
			$response_text = "";
			if ($branching_logic == $logic) {
				// Branching logic has NOT changed, but it is NOW considered invalid because of security measures.
				// User can keep it as is or remove the branching.
				$response_text .= $lang['design_439'];
			} else {
				// Branching HAS changed but has incorrect syntax.
				$response_text .= $lang['design_440'];
			}
			$response2 = $response_text;
		}
		// Return error message
		echo $lang['global_01'].$lang['colon']." " . $response . $response2;
	}
	else if ($newBranchingIsValid)
	{
		echo (LogicTester::evaluateLogicSingleRecord($logic, $record) ? "show" : "hide");
	}
}