<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Default response
$response = '0';

## Validate the fields in the logic
if (isset($_POST['logic']))
{
	// Demangle (if needed)
	$_POST['logic'] = html_entity_decode($_POST['logic'], ENT_QUOTES);

	// Check if calculation is valid
	$_POST['logic'] = Piping::pipeSpecialTags($_POST['logic'], PROJECT_ID, null, null, null, USERID, true);

	// Obtain array of error fields that are not real fields
	$error_fields = Design::validateBranchingCalc($_POST['logic'], true);

	// If longitudinal, make sure that each field references an event and that the event is valid
	if ($longitudinal) {
		// Initialize array to capture invalid event names
		$invalid_event_names = $specialPipingTags = array();
		$specialPipingTagsFormatted = Piping::getSpecialTagsFormatted(false);
		// For a bracket count check later, replace all special tags in logic with blank string
		$logicReplacedSpecialTags = $_POST['logic'];
		foreach (Piping::getSpecialTags() as $tag) {
			$tagComp = explode(":", $tag);
			$tag = "[".$tagComp[0]."]";
			$specialPipingTags[] = $tag;
			$logicReplacedSpecialTags = str_replace($tag, "", $logicReplacedSpecialTags);
		}
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
		$response = "<b>{$lang['dataqueries_47']}{$lang['colon']}</b><br>{$lang['dataqueries_45']}<br><br><b>{$lang['dataqueries_46']}</b><br>- "
				  . implode("<br>- ", $error_fields);
	}

	// If longitudinal, then must be referencing events for variable names
	elseif ($longitudinal && ($eventsNotReferenced || ($logicReplacedSpecialTags > 0 && (substr_count($logicReplacedSpecialTags, '][')*2 != substr_count($logicReplacedSpecialTags, '[')
		|| substr_count($logicReplacedSpecialTags, '][')*2 != substr_count($logicReplacedSpecialTags, ']')))))
	{
		$response = $lang['dataqueries_110'];
	}

	// If longitudinal and some unique event names are invalid
	elseif ($longitudinal && !empty($invalid_event_names))
	{
		$response = "{$lang['dataqueries_111']}<br><br><b>{$lang['dataqueries_112']}</b><br>- "
				  . implode("<br>- ", $invalid_event_names);
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
				$response = "<b>{$lang['dataqueries_47']}{$lang['colon']}</b><br>{$lang['dataqueries_109']}<br><br><b>{$lang['dataqueries_48']}</b><br>- "
						  . implode("<br>- ",  $parser->illegalFunctionsAttempted);
			}
		}
	}
}

// Send response
exit($response);
