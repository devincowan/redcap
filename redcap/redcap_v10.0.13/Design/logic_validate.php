<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Default response
$response = '0';

## Validate the fields in the logic
if (isset($_POST['logic']))
{
	// Demangle (if needed)
	$_POST['logic'] = html_entity_decode($_POST['logic'], ENT_QUOTES);
	
	// Should we show draft mode fields instead of live fields
	$forceMetadataTable = ((isset($_POST['forceMetadataTable']) && $_POST['forceMetadataTable']) || $status < 1 || ($status > 0 && $draft_mode < 1));

	// Check if calculation is valid
	$_POST['logic'] = Piping::pipeSpecialTags($_POST['logic'], PROJECT_ID, null, null, null, USERID, true);

	// Obtain array of error fields that are not real fields
	$error_fields = Design::validateBranchingCalc($_POST['logic'], $forceMetadataTable);

	// If longitudinal, make sure that each field references an event and that the event is valid
	if ($longitudinal) {
	    // Gather smart variables to process when parsing
        $specialPipingTagsFormatted = Piping::getSpecialTagsFormatted(false);
        $specialPipingTags = array();
        foreach (Piping::getSpecialTags() as $tag) {
            $tagComp = explode(":", $tag);
            $tag = "[".$tagComp[0]."]";
            $specialPipingTags[] = $tag;
        }
		// Initialize array to capture invalid event names
		$invalid_event_names = array();
		// Set default value for not referencing events with fields
		$eventsNotReferenced = false;
		foreach (array_keys(getBracketedFields(cleanBranchingOrCalc($_POST['logic']), true, true)) as $eventDotfield) {
			// If lacks a dot, then the event name is missing. Flag it
			if (in_array($eventDotfield, $specialPipingTagsFormatted)) continue;
			list ($unique_event, $field) = explode('.', $eventDotfield, 2);
			if (in_array($field, $specialPipingTagsFormatted)) continue;
			if (strpos($eventDotfield, '.') === false) {
				$eventsNotReferenced = true;
			} else {
				// Validate the unique event name and ignore Smart Variables
				if (!$Proj->uniqueEventNameExists($unique_event) && !in_array("[".$unique_event."]", $specialPipingTags)) {
					// Invalid event name, so place in array
					$invalid_event_names[] = $unique_event;
				}
			}
		}
	}
	
	// Return list of fields that do not exist (i.e. were entered incorrectly), else continue.
	if (!empty($error_fields))
	{
		$response = js_escape2("{$lang['dataqueries_47']}{$lang['colon']} {$lang['dataqueries_45']}")."\n\n".js_escape2($lang['dataqueries_46'])."\n- "
				  . implode("\n- ", $error_fields);
	}

	// If longitudinal, then must be referencing events for variable names
	elseif ($longitudinal && !empty($invalid_event_names) && $eventsNotReferenced)
	{
		$response = js_escape2($lang['dataqueries_111'])."\n\n".js_escape2($lang['dataqueries_112'])."\n- "
				  . implode("\n- ", $invalid_event_names);
	}

	// Check for any formatting issues or illegal functions used
	else
	{
		// All is good (no errors)
		$response = '1';
		// Check the logic
		$parser = new LogicParser();
		try {
			$parser->parse($_POST['logic'], null, true, false, false, true);
		}
		catch (LogicException $e) {
			if (count($parser->illegalFunctionsAttempted) === 0) {
				// Contains syntax errors
				$response = $lang['dataqueries_99'];
			}
			else {
				// Contains illegal functions
				$response = js_escape2("{$lang['dataqueries_47']}{$lang['colon']} {$lang['dataqueries_109']}")."\n\n".js_escape2($lang['dataqueries_48'])."\n- "
						  . implode("\n- ", $parser->illegalFunctionsAttempted);
			}
		}
	}
}

// Send response
exit($response);
