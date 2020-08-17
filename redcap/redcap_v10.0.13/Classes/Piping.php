<?php



/**
 * Piping Class
 */
class Piping
{
	// Set string as the missing data replacement (underscores)
	const missing_data_replacement = "______";
	// Set piping receiver field CSS class
	const piping_receiver_class = "piping_receiver";
	// Set piping receiver field CSS class *if* the field is an Identifier field
	const piping_receiver_identifier_class = "piping_receiver_identifier";
	// Set piping receiver field CSS class for prepending to field_name
	const piping_receiver_class_field = "piperec-";
	// Regex used for finding special piping tags
	const special_tag_regex = '/((\[(?\'event_name\'[^\]]*)\])?\[(?\'command\'[A-Za-z0-9\(\)\._-]*):?(?\'param1\'[^\]:]*):?(?\'param2\'[^\]:]*):?(?\'param3\'[^\]:]*)(\]\[(\d+|first-instance|last-instance|previous-instance|next-instance|current-instance))?\])/m';
	// Regex used for replacing non-event tags where previous/next-event-name do not exist
	const nonevent_regex = '/(\[NONEVENT\])(\[)([^\]]*)(\])(\[(\d+|previous-instance|current-instance|next-instance|first-instance|last-instance)\])?/m';
	
	// Return array of formatted special piping tags
	public static function getSpecialTags($beginsWith=null)
	{
		global $smartVariablesList;
		if (!isset($smartVariablesList) || empty($smartVariablesList)) {
			$smartVariablesList = array();
			foreach (self::getSpecialTagsInfo() as $attr0) {
				$smartVariablesList = array_merge($smartVariablesList, array_keys($attr0));
			}
		}
		if ($beginsWith != null) {
			$beginsWith = trim($beginsWith);
			$smartVariablesListBegins = array();
			foreach ($smartVariablesList as $var) {
				if (strpos($var, $beginsWith) === 0) {
					$smartVariablesListBegins[] = $var;
				}
			}
			return $smartVariablesListBegins;
		}		
		return $smartVariablesList;
	}
	
	// Return array of formatted special piping tags
	public static function getSpecialTagsInfo()
	{
		global $lang;
		$tags = array(
			$lang['global_17']=>array(
				'user-name' => array($lang['piping_01'], array("<code>[user-name]</code>", "jane_doe")),
                'user-fullname' => array($lang['piping_63'], array("<code>[user-fullname]</code>", "Jane Doe")),
                'user-email' => array($lang['piping_64'], array("<code>[user-email]</code>", "jane.doe@example.edu")),
				'user-dag-name' => array($lang['piping_02'], array("<code>[user-dag-name]</code>", "vanderbilt_group")),
				'user-dag-id' => array($lang['piping_03'], array("<code>[user-dag-id]</code>", "324")),
				'user-dag-label' => array($lang['piping_46'], array("<code>[user-dag-label]</code>", "Vanderbilt Group"))
			),
			$lang['global_49']=>array(
				'record-name' => array($lang['piping_04'], array("<code>[record-name]</code>", "108")),
				'record-dag-name' => array($lang['piping_05'], array("<code>[record-dag-name]</code>", "harvard_site")),
				'record-dag-id' => array($lang['piping_06'], array("<code>[record-dag-id]</code>", "96")),
				'record-dag-label' => array($lang['piping_47'], array("<code>[record-dag-label]</code>", "Harvard Site"))
			),
			$lang['global_54']=>array(
				'is-form' => array($lang['piping_23'], array("<code>[is-form]</code>", "1")),
				'form-url:instrument' => array($lang['piping_10'], array("<code>[form-url:visit_data_form]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/DataEntry/index.php?pid=example&event_id=example&id=example&instance=example&page=visit_data_form</div>"), array("<code>[baseline_arm_1][form-url:visit_data_form]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/DataEntry/index.php?pid=example&event_id=example&id=example&instance=example&page=visit_data_form</div>")),
				'form-link:instrument:Custom Text' => array($lang['piping_11'], array("<code>[form-link:visit_data_form]</code>", "<a href='http://example.com?page=visit_data_form' target='_blank' style='font-size:11px;text-decoration:underline;'>Visit Data Form</a>"), array("<code>[next-event-name][form-link:visit_data_form]</code>", "<a href='http://example.com?page=visit_data_form' target='_blank' style='font-size:11px;text-decoration:underline;'>Visit Data Form</a>"), array("<code>[form-link:demography:Click here to view Demographics]</code>", "<a href='http://example.com?page=demography' target='_blank' style='font-size:11px;text-decoration:underline;'>Click here to view Demographics</a>")),
				'instrument-name' => array($lang['piping_65'], array("<code>[instrument-name]</code>", "demographics"), array("<code>[instrument-name]</code>", "prescreening_survey")),
				'instrument-label' => array($lang['piping_66'], array("<code>[instrument-label]</code>", "Demographics"), array("<code>[instrument-label]</code>", "Pre-Screening Survey"))
			),
			$lang['survey_437']=>array(
				'is-survey' => array($lang['piping_22'], array("<code>[is-survey]</code>", "0")),
				'survey-url:instrument' => array($lang['piping_12'], array("<code>[survey-url:followup_survey]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?s=fake</div>"), array("<code>[previous-event-name][survey-url:followup_survey]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?s=fake</div>")),
				'survey-link:instrument:Custom Text' => array($lang['piping_13'], array("<code>[survey-link:followup_survey]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>Follow-up Survey</a>"), array("<code>[next-event-name][survey-link:followup_survey]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>Follow-up Survey</a>"), array("<code>[survey-link:prescreening:Take the pre-screening survey]</code>", "<a href='http://example.com?s=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>Take the pre-screening survey</a>")),
				'survey-queue-url' => array($lang['piping_14'], array("<code>[survey-queue-url]</code>", "<div style='word-break:break-word;font-size:10px;line-height:11px;'>".APP_PATH_SURVEY_FULL."?sq=fake</div>")),
				'survey-queue-link:Custom Text' => array($lang['piping_15'], array("<code>[survey-queue-link]</code>", "<a href='http://example.com?sq=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>".$lang['piping_16']."</a>"), array("<code>[survey-queue-link:View your survey progress]</code>", "<a href='http://example.com?sq=FAKE' target='_blank' style='font-size:11px;text-decoration:underline;'>View your survey progress</a>")),
				'survey-time-completed:instrument' => array($lang['piping_41'], array("<code>[survey-time-completed:followup]</code>", "12/25/2018 09:00am"), array("<code>[survey-time-completed:followup:value]</code>", "2018-12-25 09:00:00"), array("<code>[survey-time-completed:followup][last-instance]</code>", "12/25/2018 09:00am"), array("<code>[survey-time-completed:followup:value][current-instance]</code>", "2018-12-25 09:00:00")),
				'survey-date-completed:instrument' => array($lang['piping_42'], array("<code>[survey-date-completed:prescreener]</code>", "12/25/2018"), array("<code>[survey-date-completed:prescreener:value]</code>", "2018-12-25"), array("<code>[survey-date-completed:prescreener][last-instance]</code>", "12/25/2018"), array("<code>[survey-date-completed:prescreener:value][current-instance]</code>", "2018-12-25")),
                'survey-title:instrument' => array($lang['piping_67'], array("<code>[survey-title]</code>", "Enter to Win a New Car"), array("<code>[survey-title:prescreening_survey]</code>", "Cardiology Study: Pre-Screening Survey"))
			),
			$lang['piping_38']=>array(
				'event-name' => array($lang['piping_07']." ".$lang['piping_37'], array("<code>[event-name]</code>", "event_2_arm_1"), array("<code>[event-name][weight]</code>", "125")),
				'event-label' => array($lang['piping_17'], array("<code>[event-label]</code>", "Event 2")),
				'previous-event-name' => array($lang['piping_08']." ".$lang['piping_37']." ".$lang['piping_44'], array("<code>[previous-event-name]</code>", "visit_4_arm_2"), array("<code>[previous-event-name][heart_rate]</code>", "62")),
				'previous-event-label' => array($lang['piping_18'], array("<code>[previous-event-label]</code>", "Visit 4")),
				'next-event-name' => array($lang['piping_09']." ".$lang['piping_37']." ".$lang['piping_45'], array("<code>[next-event-name]</code>", "event_3_arm_5"), array("<code>[next-event-name][provider]</code>", "Taylor")),
				'next-event-label' => array($lang['piping_19'], array("<code>[next-event-label]</code>", "Third Timepoint")),
				'first-event-name' => array($lang['piping_54']." ".$lang['piping_37']." ".$lang['piping_52'], array("<code>[first-event-name]</code>", "visit_1_arm_2"), array("<code>[first-event-name][heart_rate]</code>", "74")),
				'first-event-label' => array($lang['piping_56'], array("<code>[first-event-label]</code>", "Visit 1")),
				'last-event-name' => array($lang['piping_55']." ".$lang['piping_37']." ".$lang['piping_53'], array("<code>[last-event-name]</code>", "week_22_arm_1"), array("<code>[last-event-name][provider]</code>", "Minor")),
				'last-event-label' => array($lang['piping_57'], array("<code>[last-event-label]</code>", "Week 22")),
				'arm-number' => array($lang['piping_20'], array("<code>[arm-number]</code>", "2")),
				'arm-label' => array($lang['piping_29'], array("<code>[arm-label]</code>", "Drug B"))
			),
			$lang['rep_forms_events_01']=>array(
				'previous-instance' => array($lang['piping_24']." ".$lang['piping_31']." ".$lang['piping_36'], array("<code>[previous-instance]</code>", "3"), array("<code>[weight][previous-instance]</code>", "145")),
				'current-instance' => array($lang['piping_24']." ".$lang['piping_33']." ".$lang['piping_36'], array("<code>[current-instance]</code>", "2"), array("<code>[heart_rate][current-instance]</code>, which is the same as <code>[heart_rate]</code>", "84")),
				'next-instance' => array($lang['piping_24']." ".$lang['piping_32']." ".$lang['piping_36'], array("<code>[next-instance]</code>", "7"), array("<code>[provider][next-instance]</code>", "Harris")),
				'first-instance' => array($lang['piping_24']." ".$lang['piping_34']." ".$lang['piping_36'], array("<code>[first-instance]</code>", "1"), array("<code>[age][first-instance]</code>", "24")),
				'last-instance' => array($lang['piping_24']." ".$lang['piping_35']." ".$lang['piping_36'], array("<code>[last-instance]</code>", "6"), array("<code>[glucose][last-instance]</code>", "119"))
			),
            $lang['global_156']=>array(
                'project-id' => array($lang['piping_58'], array("<code>[project-id]</code>", "39856")),
                'redcap-base-url' => array($lang['piping_59'], array("<code>[redcap-base-url]</code>", APP_PATH_WEBROOT_FULL)),
                'redcap-version' => array($lang['piping_60'], array("<code>[redcap-version]</code>", REDCAP_VERSION)),
                'redcap-version-url' => array($lang['piping_61'], array("<code>[redcap-version-url]</code>", APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/")),
                'survey-base-url' => array($lang['piping_62'], array("<code>[survey-base-url]</code>", APP_PATH_SURVEY_FULL))
            )
		);
		return $tags;		
	}
	
	// Return array of formatted special piping tags
	public static function getSpecialTagsFormatted($addBrackets=true, $returnParameters=true)
	{
		global $SpecialPipingTags, $SpecialPipingTagsBrackets;
		// Build arrays if not cached already
		if (!isset($SpecialPipingTags) || empty($SpecialPipingTags))
		{
			$SpecialPipingTags = $SpecialPipingTagsBrackets = array();
			foreach (self::getSpecialTags() as $tag) {
				// Set tag with bracket
				$tagbracket = "[$tag";
				if (substr($tagbracket, -1) != ":") $tagbracket .= "]";
				// Add to arrays
				$SpecialPipingTags[] = $tag;
				$SpecialPipingTagsBrackets[] = $tagbracket;
			}
		}
		// Remove parameters? Remove all after colon.
		if (!$returnParameters) {
			$SpecialPipingTags2 = $SpecialPipingTagsBrackets2 = array();
			foreach ($SpecialPipingTags as $tag) {
				// Remove parameters
				if (strpos($tag, ":") !== false) {
					$tag_parts = explode(":", $tag, 2);
					$tag = $tag_parts[0];
				}
				// Set tag with bracket
				$tagbracket = "[$tag";
				if (substr($tagbracket, -1) != ":") $tagbracket .= "]";
				// Add to arrays
				$SpecialPipingTags2[] = $tag;
				$SpecialPipingTagsBrackets2[] = $tagbracket;
			}
			// Return arrays
			return ($addBrackets ? $SpecialPipingTagsBrackets2 : $SpecialPipingTags2);
		}
		// Return arrays
		return ($addBrackets ? $SpecialPipingTagsBrackets : $SpecialPipingTags);
	}
	
	// Return boolean regarding whether or not the string contains special EVENT piping tags 
	public static function containsEventSpecialTags($input)
	{
		return (strpos($input, "[event-name]") !== false || strpos($input, "[event-label]") !== false
                || strpos($input, "-event-name]") !== false || strpos($input, "-event-label]") !== false);
	}
	
	// Return boolean regarding whether or not the string contains special EVENT piping tags 
	public static function containsInstanceSpecialTags($input)
	{
		return (strpos($input, "-instance]") !== false);
	}
	
	// Return boolean regarding whether or not the string contains special piping tags (passes regex)
	public static function containsSpecialTags($input)
	{
		if (self::containsEventSpecialTags($input)) return true; // Check here independently for event-X smart variables because the regex below alone doesn't find them
		$foundTags = (preg_match_all(self::special_tag_regex, $input, $matches, PREG_PATTERN_ORDER) > 0);
		if ($foundTags) {
			foreach (self::getSpecialTags() as $tag) {
				$tags = explode(":", $tag);
				$thisTag = "[" . $tags[0];
				foreach ($matches[0] as $key=>$match) {
					if (strpos($match, $thisTag) !== false) return true;
				}
			}
		}
		return false;
	}
	
	// Pipe special tags that function as variables: e.g., [survey-link:instrument:My Survey Link], [form-link:instrument].
	// Param $wrapInQuotes is used to wrap quotes around specific tags that might be injected into logic (because we don't need to wrap them for normal piping into labels, etc.).
    public static function pipeSpecialTags($input, $project_id=null, $record=null, $event_id=null, $instance=null, 
										   $user=null, $wrapInQuotes=false, $participant_id=null, $form=null, 
										   $replaceWithUnderlineIfMissing=false, $escapeSql=false) 
	{
		global $lang;

		$orig_input = $input;
        $default_event_id = $context_event_id = $event_id;
        $context_form = $form;
		$Proj = new Project($project_id);
		if ($user === null && defined("USERID")) $user = USERID;
		$user = strtolower($user);
		$wrapper = $wrapInQuotes ? "'" : "";
		if ($instance."" === "0") $instance = null;
		
		// There might be some [field_name][XXXX-instance] instances, so replace these prior to further processing
		$haveBothEventAndForm = ($event_id != null && $form != null);
		$canReferenceRelativeInstance = ($Proj->hasRepeatingFormsEvents() && is_numeric($instance) && strpos($input, '-instance]') !== false
										&& (!$haveBothEventAndForm || ($haveBothEventAndForm && $Proj->isRepeatingForm($event_id, $form)) || ($event_id != null && $Proj->isRepeatingEvent($event_id))));
		if ($canReferenceRelativeInstance) {
			$instance_repl = array();
			$instance_repl['][previous-instance]'] = ']['.($instance-1).']';
			$instance_repl['][current-instance]'] = ']['.($instance).']';
			$instance_repl['][next-instance]'] = ']['.($instance+1).']';
			$input = str_replace(array_keys($instance_repl), $instance_repl, $input);
		}

		// There might still be some [XXXX-event][field_name] instances, so replace these (note: for prev/next, get designated event for this form)
		if ($Proj->longitudinal && $event_id != null && strpos($input, 'event-name][') !== false)
		{
			$replaceNonEvents = false;
			// Current event name
			$event_repl = array('[event-name]['=>'['.$Proj->getUniqueEventNames($event_id).'][');
			// Previous event name
			if ($form != null && strpos($input, '[previous-event-name][') !== false) {
				$prev_event_id = $Proj->getPrevEventId($event_id, $form);
				if (!is_numeric($prev_event_id)) {
					// We are on the first event, so replace entire [event][field][instance] with nothing or underscores
					$event_repl['[previous-event-name]['] = '[NONEVENT][';
					$replaceNonEvents = true;
				}
			}
			// Next event name
			if ($form != null && strpos($input, '[next-event-name][') !== false) {
				$next_event_id = $Proj->getNextEventId($event_id, $form);
				if (!is_numeric($next_event_id)) {
					// We are on the last event, so replace entire [event][field][instance] with nothing or underscores
					$event_repl['[next-event-name]['] = '[NONEVENT][';
					$replaceNonEvents = true;
				}
			}
			$input = str_replace(array_keys($event_repl), $event_repl, $input);
			// Replace any non-events
			if ($replaceNonEvents) {
				$foundTags = preg_match_all(self::nonevent_regex, $input, $matchesNonEvents, PREG_PATTERN_ORDER);
				if ($foundTags) {
					$matchesNonEventsReplace = array_fill(0, count($matchesNonEvents[0]), ($replaceWithUnderlineIfMissing ? self::missing_data_replacement : $wrapper.$wrapper));
					$input = str_replace($matchesNonEvents[0], $matchesNonEventsReplace, $input);
				}
			}
		}
        
        // grep for the smart variables
        $foundTags = preg_match_all(self::special_tag_regex, $input, $matches, PREG_PATTERN_ORDER);
		
		// find all the tags that match the above reg expression
        if ($foundTags) 
		{
			$specialTagListNoParams = self::getSpecialTagsFormatted(false, false);
			$specialTagList = self::getSpecialTagsFormatted(false);

			// First do some pre-processing cleanup
			foreach ($matches['event_name'] as $key => $value) 
			{
				// Add instance to all sub-arrays
				$matches['instance'][$key] = "";
				// Fix event
				if (strpos($matches['event_name'][$key], '][') !== false) {
					list ($this_event_name, $new_command) = explode('][', $matches['event_name'][$key], 2);
					$event_id = $Proj->getEventIdUsingUniqueEventName($this_event_name);
					if (is_numeric($event_id)) {
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $matches['command'][$key];
						$matches['command'][$key] = $new_command;
						$matches['event_name'][$key] = $this_event_name;
					}
				}
				if (strpos($matches['event_name'][$key], ':') !== false && !in_array($matches['command'][$key], $specialTagList)) {
					list ($new_command, $param) = explode(':', $matches['event_name'][$key], 2);
					// If the colon was simply from :value or :label, then skip this
					if ($param != "value" && $param != "label") {
						$matches['instance'][$key] = $matches['param3'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $param;
						$matches['command'][$key] = $new_command;
						$matches['event_name'][$key] = "";
					}
				}
				// If the command mistakenly ends up in the event_name slot, move it down to command
				if (in_array($matches['event_name'][$key], $specialTagListNoParams) && !self::containsEventSpecialTags("[".$matches['event_name'][$key]."]")) {
					$matches['instance'][$key] = $matches['param3'][$key];
					$matches['param3'][$key] = $matches['param2'][$key];
					$matches['param2'][$key] = $matches['param1'][$key];
					$matches['param1'][$key] = $matches['command'][$key];
					$matches['command'][$key] = $matches['event_name'][$key];
					$matches['event_name'][$key] = "";
				}
				// Fix command
				if (strpos($matches['command'][$key], ':') !== false) {
					list ($new_command, $param) = explode(':', $matches['command'][$key], 2);
					// If the colon was simply from :value or :label, then skip this
					if ($param != "value" && $param != "label") {
						$matches['instance'][$key] = $matches['param3'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $param;
						$matches['command'][$key] = $new_command;
					}
				}
				// Fix param1
				if (strpos($matches['param1'][$key], ':') !== false) {
					list ($new_param1, $param) = explode(':', $matches['param1'][$key], 2);
					// If the colon was simply from :value or :label, then skip this
					if ($param != "value" && $param != "label") {
						$matches['instance'][$key] = $matches['param3'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $param;
						$matches['param1'][$key] = $new_param1;
					}
				}
                // Fix command with instance in it
                if (strpos($matches['event_name'][$key], ':') !== false && strpos($matches['command'][$key], '-instance') !== false) {
                    list ($new_command, $param) = explode(':', $matches['event_name'][$key], 2);
                    // If the colon was simply from :value or :label, then skip this
                    if ($param != "value" && $param != "label") {
						$matches['event_name'][$key] = "";
						$matches['instance'][$key] = $matches['command'][$key];
						$matches['param3'][$key] = $matches['param2'][$key];
						$matches['param2'][$key] = $matches['param1'][$key];
						$matches['param1'][$key] = $param;
						$matches['command'][$key] = $new_command;
					}
                }
				// Place instance in proper place
				$paramVals = array('param3', 'param2', 'param1', '4', '9');
				foreach ($paramVals as $thisParamName) {
					if ($matches['instance'][$key] != "") continue;
					if (is_numeric($matches[$thisParamName][$key]) || substr($matches[$thisParamName][$key], -9) == '-instance') {
						$matches['instance'][$key] = $matches[$thisParamName][$key];
						$matches[$thisParamName][$key] = "";
					}
				}
			}
				
            // look up the survey link for each tagged and store in array under '99'
            // 0 = full, 2=event_id, 3=type (survey/file), 4, file_name
            foreach ($matches['command'] as $key => $value) 
			{
				$wrapThisItem = false; // default
				// Set local instance
				$this_instance = $instance;
				if ($matches[9][$key] != null && is_numeric($matches[9][$key])) {
					$this_instance = $matches['instance'][$key] = $matches[9][$key];
				} elseif ($matches[9][$key] != null  && strpos($matches[9][$key], '-instance') !== false) {
					$value = $matches[9][$key];
				} elseif ($matches['instance'][$key] != null && is_numeric($matches['instance'][$key])) {
					$this_instance = $matches['instance'][$key];
				}
                //reset the event id the default passed in.
                $event_id = $default_event_id;
                $matches['pre-pipe'][$key] = "/" . preg_quote($matches[0][$key], '/') . "/";
                $hasMatch = true;
                switch ($value) 
				{
                    case "instrument-name" :
                    case "instrument-label" :
                    case "survey-title" :
                        $wrapThisItem = true;
                        $this_form = $form;
                        // If we have participant_id, use it to determine $form
                        if (is_numeric($participant_id)) {
                            $sql = "select s.form_name from redcap_surveys s, redcap_surveys_participants p 
									where p.survey_id = s.survey_id and p.participant_id = $participant_id";
                            $q = db_query($sql);
                            if (db_num_rows($q)) {
                                $this_form = db_result($q, 0);
                            }
                        }
                        if ($value == 'instrument-name') {
                            $matches['post-pipe'][$key] = $this_form."";
                        } else if ($value == 'instrument-label') {
                            $matches['post-pipe'][$key] = isset($Proj->forms[$this_form]) ? $Proj->forms[$this_form]['menu'] : "";
                        } else if ($value == 'survey-title') {
                            if (isset($Proj->forms[$matches['param1'][$key]])) {
                                $this_form = $matches['param1'][$key];
                            }
                            $matches['post-pipe'][$key] = isset($Proj->forms[$this_form]['survey_id']) ? $Proj->surveys[$Proj->forms[$this_form]['survey_id']]['title'] : "";
                        }
                        break;
                    case "previous-instance" :
                    case "current-instance" :
                    case "next-instance" :
                    case "first-instance" :
                    case "last-instance" :
						$wrapThisItem = true;
						$this_form = $form;
						// If we have participant_id, use it to determine $form
						if (is_numeric($participant_id)) {
							$sql = "select s.form_name from redcap_surveys s, redcap_surveys_participants p 
									where p.survey_id = s.survey_id and p.participant_id = $participant_id";
							$q = db_query($sql);
							if (db_num_rows($q)) {
								$this_form = db_result($q, 0);
							}
						}
						// Deal with field being in instance's place (occurs when event is prepended to variable)
						 $event_name = "";
						if ($matches['command'][$key] != $value && $matches['event_name'][$key] != "") {
							$fieldVar = $fieldVarFull = $matches['command'][$key];
                            $event_name = $matches['event_name'][$key];
							$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
						} else {						
							// Determine if we have a field variable name
							$fieldVar = $fieldVarFull = $matches['event_name'][$key];
						}
						if ($fieldVar != "") {
							// Isolate the variable name (remove parentheses and colons)
							list ($fieldVar, $nothing) = explode("(", $fieldVar, 2);
							list ($fieldVar, $nothing) = explode(":", $fieldVar, 2);
							if (!isset($Proj->metadata[$fieldVar])) break;
							$this_form = $Proj->metadata[$fieldVar]['form_name'];
						}
						// Requires record, event, and form
						if ($this_form == null || $record == null || !is_numeric($event_id)) {
							$matches['post-pipe'][$key] = "";
						}
						elseif ($value == "first-instance" || $value == "last-instance") {
							// For first/last-instance, we need $this_form for context							
							$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
							if (empty($formInstances)) {
                                $newInstance = 1;
                            } else {
                                $newInstance = ($value == "first-instance" ? min($formInstances) : max($formInstances));
                            }
							if ($fieldVar == "") {
								// Stand-alone
								$matches['post-pipe'][$key] = $newInstance;
							} else {
								$wrapThisItem = false;
								$matches['post-pipe'][$key] = ($event_name == "" ? "" : "[$event_name]") . "[$fieldVarFull][$newInstance]";
							}
						} 
						elseif (is_numeric($instance) && $canReferenceRelativeInstance) {
							// For previous/current/next-instance, we get $this_form from the associate REDCap field
							$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
							$increment = ($value == "previous-instance" ? -1 : ($value == "next-instance" ? 1 : 0));
							$newInstance = $instance+$increment;
							if (in_array($newInstance, $formInstances) || $value == "current-instance") {
								$matches['post-pipe'][$key] = $newInstance;
							} else {
								$matches['post-pipe'][$key] = "";
							}
						} elseif ($value == "current-instance" && (!$Proj->longitudinal || ($Proj->longitudinal && $event_name != '')) && $fieldVar != ''
							&& $instance == '' && !$Proj->isRepeatingFormOrEvent($event_id, $this_form)) {
							// If [current-instance] is appended to a field that is not repeating in the current context, then remove it and return [event][field] back
							$wrapThisItem = false;
							$matches['post-pipe'][$key] = ($event_name == "" ? "" : "[$event_name]") . "[$fieldVarFull]";
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "project-id" :
                        $matches['post-pipe'][$key] = $project_id;
                        break;
                    case "redcap-base-url" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = APP_PATH_WEBROOT_FULL;
                        break;
                    case "redcap-version" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = REDCAP_VERSION;
                        break;
                    case "redcap-version-url" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/";
                        break;
                    case "survey-base-url" :
                        $wrapThisItem = true;
                        $matches['post-pipe'][$key] = APP_PATH_SURVEY_FULL;
                        break;
                    case "user-name" :
                    	if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
						$wrapThisItem = true;
						$matches['post-pipe'][$key] = $user;
						break;
                    case "user-fullname" :
						if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
                        $wrapThisItem = true;
                        $user_info = $user != '' ? User::getUserInfo($user) : false;
                        $matches['post-pipe'][$key] = is_array($user_info) ? trim($user_info['user_firstname'])." ".trim($user_info['user_lastname']) : "";
                        break;
                    case "user-email" :
						if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
                        $wrapThisItem = true;
                        $user_info = $user != '' ? User::getUserInfo($user) : false;
                        $matches['post-pipe'][$key] = is_array($user_info) ? $user_info['user_email'] : "";
                        break;
                    case "user-dag-id" :
                    case "user-dag-name" :
                    case "user-dag-label" :
						if ($user == USERID && UserRights::isImpersonatingUser()) {
							$user = UserRights::getUsernameImpersonating();
						}
						$wrapThisItem = true;
						$userRights = UserRights::getPrivileges($project_id, $user);
						$dag_id = isset($userRights[$project_id][$user]) ? $userRights[$project_id][$user]['group_id'] : "";
						if (!is_numeric($dag_id)) {
							$matches['post-pipe'][$key] = "";
						} elseif ($value == 'user-dag-id') {
							$matches['post-pipe'][$key] = $dag_id;
						} elseif ($value == 'user-dag-label') {
							$dag_name = $Proj->getGroups($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						} else {
							$dag_name = $Proj->getUniqueGroupNames($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						}
						break;
					case "record-name" :
						$wrapThisItem = true;
						$matches['post-pipe'][$key] = $record;
						break;
                    case "record-dag-id" :
                    case "record-dag-name" :
                    case "record-dag-label" :
						$wrapThisItem = true;
						$dag_id = Records::getRecordGroupId($project_id, $record);
						if (!is_numeric($dag_id)) {
							$matches['post-pipe'][$key] = "";
						} elseif ($value == 'record-dag-id') {
							$matches['post-pipe'][$key] = $dag_id;
						} elseif ($value == 'record-dag-label') {
							$dag_name = $Proj->getGroups($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						} else {
							$dag_name = $Proj->getUniqueGroupNames($dag_id);
							$matches['post-pipe'][$key] = ($dag_name != "") ? $dag_name : "";
						}						
						break;
                    case "arm-number" :
                    case "arm-label" :
						$wrapThisItem = true;
						if (is_numeric($event_id)) {
							if ($value == 'arm-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->events[$Proj->eventInfo[$event_id]['arm_num']]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->eventInfo[$event_id]['arm_num'];
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "event-name" :
                    case "event-label" :
						$wrapThisItem = true;
						if (is_numeric($event_id)) {
							if ($value == 'event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$event_id]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($event_id);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "first-event-name" :
                    case "first-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$firstEventId = $Proj->getFirstEventIdInArmByEventId($event_id);
						if (is_numeric($firstEventId)) {
							if ($value == 'first-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$firstEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($firstEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "last-event-name" :
                    case "last-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$lastEventId = $Proj->getLastEventIdInArmByEventId($event_id);
						if (is_numeric($lastEventId)) {
							if ($value == 'last-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$lastEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($lastEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "previous-event-name" :
                    case "previous-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$prevEventId = $Proj->getPrevEventId($event_id);
						if (is_numeric($prevEventId)) {
							if ($value == 'previous-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$prevEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($prevEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "next-event-name" :
                    case "next-event-label" :
						// note: since this is a stand-along event var, get literal immediate event, not designated event for this form
						$wrapThisItem = true;
						$nextEventId = $Proj->getNextEventId($event_id);
						if (is_numeric($nextEventId)) {
							if ($value == 'next-event-label') {
								$matches['post-pipe'][$key] = RCView::escape($Proj->eventInfo[$nextEventId]['name']);
							} else {
								$matches['post-pipe'][$key] = $Proj->getUniqueEventNames($nextEventId);
							}
						} else {
							$matches['post-pipe'][$key] = "";
						}
						break;
                    case "form-url" :
                    case "form-link" :
                        if ($matches['event_name'][$key] != null) {
                            $event_name = $matches['event_name'][$key];
                            if ($Proj->longitudinal) {
								if ($event_name == 'previous-event-name') {
									$event_id = $Proj->getPrevEventId($event_id);
								} elseif ($event_name == 'next-event-name') {
									$event_id = $Proj->getNextEventId($event_id);
								} elseif ($event_name != 'event-name') {
									$event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
								}
                            }
                        }
						// Get form
						if ($form == null && $matches['param1'][$key] != '' && isset($Proj->forms[$matches['param1'][$key]])) {
							$form = $matches['param1'][$key];
						}
						// Fix custom text if "instrument" param is not included
						elseif ($form != null && $matches['param1'][$key] != '' && !isset($Proj->forms[$matches['param1'][$key]])) {
							$matches['param2'][$key] = trim($matches['param1'][$key] . " " . $matches['param2'][$key]);							
							$matches['param1'][$key] = $form;
						}
						elseif ($form != null && $matches['param1'][$key] == '' && isset($Proj->forms[$form])) {						
							$matches['param1'][$key] = $form;
						}
						$this_form = $matches['param1'][$key];
                        // If target is not a repeating form or repeating event, then set instance to 1
                        if (!$Proj->isRepeatingFormOrEvent($event_id, $this_form)) $this_instance = 1;
                        if ($context_event_id != '') {
                            // If we're leaving a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($context_event_id, $context_form)) $this_instance = 1;
                            // If we're navigating to a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($event_id, $this_form)) $this_instance = 1;
                            // If we're switching events and one of them is a repeating event, then set instance to 1
                            if ($event_id != $context_event_id && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingEvent($context_event_id))) $this_instance = 1;
                        }
                        // If we have first/last-instance in instance matching position, then fetch value for this context
                        if ($matches['instance'][$key] == "first-instance" || $matches['instance'][$key] == "last-instance") {
                            // For first/last-instance, we need $this_form for context
                            $formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
                            $this_instance = ($matches['instance'][$key] == "first-instance" ? min($formInstances) : max($formInstances));
                        }
                        // Construct target URL
                        $url = APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/DataEntry/index.php?pid={$project_id}&page={$this_form}&id={$record}&event_id={$event_id}&instance={$this_instance}";
						//if there is a param2 set that as title
						if ($value == "form-link") {
							$title = (($matches['param2'][$key] == null) ? $Proj->forms[$this_form]['menu'] : $matches['param2'][$key]);
							$url = "<a href=\"$url\" target=\"_blank\">" . RCView::escape($title) . "</a>";
						}
						$matches['post-pipe'][$key] = $url;
                        break;
                    case "is-survey" :
						$matches['post-pipe'][$key] = (PAGE == 'surveys/index.php') ? '1' : '0';
						break;
                    case "is-form" :
						$matches['post-pipe'][$key] = (PAGE == 'DataEntry/index.php' && isset($_GET['id']) && isset($_GET['page'])) ? '1' : '0';
						break;
                    case "survey-url" :
                    case "survey-link" :
                        // render the survey as a href tag with the survey name as the text.
						// Get form
						if ($form == null && $matches['param1'][$key] != '' && isset($Proj->forms[$matches['param1'][$key]])) {
							$form = $matches['param1'][$key];
						}
						// Fix custom text if "instrument" param is not included
						elseif ($form != null && $matches['param1'][$key] != '' && !isset($Proj->forms[$matches['param1'][$key]])) {
							$matches['param2'][$key] = trim($matches['param1'][$key] . " " . $matches['param2'][$key]);							
							$matches['param1'][$key] = $form;
						}
						elseif ($form != null && $matches['param1'][$key] == '') {							
							$matches['param1'][$key] = $form;
						}
						$this_form = $matches['param1'][$key];
						// Get event
                        $event_name = $survey_id = "";
                        if ($matches['event_name'][$key] != null) {
                            $event_name = $matches['event_name'][$key];
                            if ($Proj->longitudinal) {
                                if ($event_name == 'previous-event-name') {
                                    $event_id = $Proj->getPrevEventId($event_id);
                                } elseif ($event_name == 'next-event-name') {
                                    $event_id = $Proj->getNextEventId($event_id);
                                } elseif ($event_name == 'first-event-name') {
                                    $event_id = $Proj->getFirstEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name == 'last-event-name') {
                                    $event_id = $Proj->getLastEventIdInArmByEventId($context_event_id, $this_form);
                                } elseif ($event_name != 'event-name') {
                                    $event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
                                }
                            }
                        }
                        // If target is not a repeating form or repeating event, then set instance to 1
                        if (!$Proj->isRepeatingFormOrEvent($event_id, $this_form)) $this_instance = 1;
                        if ($context_event_id != '') {
                            // If we're leaving a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($context_event_id, $context_form)) $this_instance = 1;
                            // If we're navigating to a repeating form, then set instance to 1
                            if ((($context_form != '' && $context_form != $this_form) || $event_id != $context_event_id) && $Proj->isRepeatingForm($event_id, $this_form)) $this_instance = 1;
                            // If we're switching events and one of them is a repeating event, then set instance to 1
                            if ($event_id != $context_event_id && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingEvent($context_event_id))) $this_instance = 1;
                        }
                        // If we have first/last-instance in instance matching position, then fetch value for this context
                        if ($matches['instance'][$key] == "first-instance" || $matches['instance'][$key] == "last-instance") {
                            // For first/last-instance, we need $this_form for context
                            $formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
                            $this_instance = ($matches['instance'][$key] == "first-instance" ? min($formInstances) : max($formInstances));
                        }
						// Determine what parts to use
						if (is_numeric($participant_id) && $form == $this_form && $event_id == $context_event_id) {
							// Get link using only participant_id from back-end (only if target form/event is same as context form/event)
							$link = Survey::getSurveyLinkFromParticipantId($participant_id);
							$survey_id = Survey::getSurveyIdFromParticipantId($participant_id);
						} elseif ($record != null) {
							// Get link using record
							$link = REDCap::getSurveyLink($record, $this_form, $event_id, $this_instance, $Proj->project_id);
						} elseif ($record == null && $this_form == $Proj->firstForm && $event_id == $Proj->firstEventId) {
							// Get public survey link
							$link = APP_PATH_SURVEY_FULL . "?s=" . Survey::getSurveyHash($Proj->forms[$this_form]['survey_id'], $event_id);
						}
						if ($link == null) $link = "";
                        //if there is a param2 set that as title
						if ($value == "survey-link" && $link != "") {
							if (is_numeric($survey_id) && isset($Proj->surveys[$survey_id])) {
								$survey_title = (($matches['param2'][$key] == null) ? $Proj->surveys[$survey_id]['title'] : $matches['param2'][$key]);
							} else {
								$survey_title = (($matches['param2'][$key] == null) ? $Proj->surveys[$Proj->forms[$this_form]['survey_id']]['title'] : $matches['param2'][$key]);
							}
							$link = "<a href=\"$link\" target=\"_blank\">" . RCView::escape($survey_title) . "</a>";
						}
						$matches['post-pipe'][$key] = $link;
                        break;
                    case "survey-queue-url" :
						if ($record == '' && is_numeric($participant_id)) {
							$record = Survey::getRecordFromParticipantId($participant_id);
						}
                        $link = REDCap::getSurveyQueueLink($record, $Proj->project_id);
                        $matches['post-pipe'][$key] = $link;
                        break;
                    case "survey-queue-link" :
						if ($record == '' && is_numeric($participant_id)) {
							$record = Survey::getRecordFromParticipantId($participant_id);
						}
						$link = REDCap::getSurveyQueueLink($record, $Proj->project_id);
						if ($link == null) {
							$matches['post-pipe'][$key] = "";
						} else {
							$text = ($matches['param1'][$key] == null) ? $lang['piping_16'] : $matches['param1'][$key];
							$matches['post-pipe'][$key] = "<a href=\"$link\" target=\"_blank\">" . RCView::escape($text) . "</a>";
						}
                        break;
                    case "survey-date-completed" :
                    case "survey-time-completed" :
						// Is :value appended? If so, return raw Y-M-D H:M:S format.
						$returnRawValue = ($matches['param1'][$key] == 'value' || $matches['param2'][$key] == 'value' || $matches['param3'][$key] == 'value');
						// Determine event, if prepended
                        if ($matches['event_name'][$key] != null) {
                            $event_name = $matches['event_name'][$key];
                            if ($Proj->longitudinal) {
                                $event_id = $Proj->getEventIdUsingUniqueEventName($event_name);
                            }
                        }
                        // If instance is appended
                        if (is_numeric($matches['instance'][$key])) {
                            $this_instance = $matches['instance'][$key];
                        }
                        elseif ($matches['instance'][$key] != '' && strpos($matches['instance'][$key], '-instance') !== false)
                        {
							if ($matches['instance'][$key] == 'current-instance') {
								$this_instance = $instance;
							} else {
								$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $event_id, $matches['param1'][$key], $Proj));
								if ($matches['instance'][$key] == 'first-instance') {
									$this_instance = min($formInstances);
								} else if ($matches['instance'][$key] == 'last-instance') {
									$this_instance = max($formInstances);
								} else if ($matches['instance'][$key] == 'previous-instance') {
									$this_instance = in_array($this_instance - 1, $formInstances) ? $this_instance - 1 : '';
								} else if ($matches['instance'][$key] == 'next-instance') {
									$this_instance = in_array($this_instance + 1, $formInstances) ? $this_instance + 1 : '';
								}
                            }
                        }
						// Get the timestamp, if exists
						$surveyCompleted = self::getSurveyTimestamp($Proj, $record, $matches['param1'][$key], $event_id, $this_instance);
						// If returning only date, then remove time component
						if ($value == "survey-date-completed") {
							list ($surveyCompleted, $nothin) = explode(" ", $surveyCompleted, 2);
						}
						// Format the date/time to user preference if we're in a piping context (as opposed to calc/logic context, which would use raw value)
						if ($replaceWithUnderlineIfMissing && !$returnRawValue) {
							$surveyCompleted = DateTimeRC::format_ts_from_ymd($surveyCompleted);
						}
						// Set value
						$matches['post-pipe'][$key] = $wrapper . $surveyCompleted . $wrapper;
                        break;
                    default :
						unset($matches['pre-pipe'][$key], $matches['post-pipe'][$key]);
						$hasMatch = false;
						break;
                }
                if (!$hasMatch) continue;
				// Wrap the value in quotes or do SQL escape?
				if ($escapeSql) {
					$matches['post-pipe'][$key] = db_escape($matches['post-pipe'][$key]);
				}
				if ($wrapThisItem) {
					$matches['post-pipe'][$key] = $wrapper . $matches['post-pipe'][$key] . $wrapper;
				}
            }
			
			// Deal with prepended X-event-name
			foreach ($matches['event_name'] as $key => $value) 
			{
				if ($value == 'first-event-name' || $value == 'last-event-name' || $value == 'previous-event-name' || $value == 'next-event-name' || $value == 'event-name') {
					$this_field = $matches['command'][$key];
					if (isset($Proj->metadata[$this_field])) {
						if ($value == 'first-event-name') {
                            $this_event_id = $Proj->getFirstEventIdInArmByEventId($event_id, $Proj->metadata[$this_field]['form_name']);
                        } else if ($value == 'last-event-name') {
                            $this_event_id = $Proj->getLastEventIdInArmByEventId($event_id, $Proj->metadata[$this_field]['form_name']);
                        } else if ($value == 'previous-event-name') {
                            $this_event_id = $Proj->getPrevEventId($event_id, $Proj->metadata[$this_field]['form_name']);
                        } else if ($value == 'next-event-name') {
                            $this_event_id = $Proj->getNextEventId($event_id, $Proj->metadata[$this_field]['form_name']);
                        } else if ($value == 'event-name') {
                            $this_event_id = $context_event_id;
                        }
						$p1 = $matches['param1'][$key]; 
						$p2 = $matches['param3'][$key]; 
						$p3 = $matches['param2'][$key]; 
						if ($p1 !== "") {
							if (substr($p1, 0, 1) === "(") 
								$this_field = $this_field.$p1;
							else
								$this_field = $this_field.":".$p1;
						}
						if ($p2 !== "") $this_field = $this_field.":".$p2;
						if ($p3 !== "") $this_field = $this_field.":".$p3;
						$matches['post-pipe'][$key] = is_numeric($this_event_id) ? '['.$Proj->getUniqueEventNames($this_event_id).']['.$this_field.']' : ($replaceWithUnderlineIfMissing ? $wrapper.self::missing_data_replacement.$wrapper : $wrapper.$wrapper);
						$matches['pre-pipe'][$key] = "/" . preg_quote($matches[0][$key], '/') . "/";
						// Wrap the value in quotes or do SQL escape?
						if ($escapeSql) {
							$matches['post-pipe'][$key] = db_escape($matches['post-pipe'][$key]);
						}
						// if ($wrapThisItem) {
						// 	$matches['post-pipe'][$key] = $wrapper . $matches['post-pipe'][$key] . $wrapper;
						// }
					}
				} 
			}

            // Deal with appended X-instance
            foreach ($matches['instance'] as $key => $value) {
                if (is_numeric($value) || $value == 'first-instance' || $value == 'last-instance' || $value == 'previous-instance' || $value == 'next-instance' || $value == 'current-instance') {
                    $this_field = $matches['command'][$key];
                    if (isset($Proj->metadata[$this_field])) {
                        $this_event_id = $matches['event_name'][$key] == '' ? $context_event_id : $matches['event_name'][$key];
                        if (!is_numeric($this_event_id)) {
							$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event_id);
						}
                        $this_prepended_event = ($matches['event_name'][$key] != '' && !is_numeric($matches['event_name'][$key])) ? "[".$matches['event_name'][$key]."]" : '';
						if ($value == 'current-instance') {
							$this_instance = $instance;
						} elseif (is_numeric($value)) {
							$this_instance = $value;
						} else {
							$formInstances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $this_event_id, $Proj->metadata[$this_field]['form_name'], $Proj));
							if ($value == 'first-instance') {
								$this_instance = min($formInstances);
							} else if ($value == 'last-instance') {
								$this_instance = max($formInstances);
							} else if ($value == 'previous-instance') {
								$this_instance = in_array($this_instance - 1, $formInstances) ? $this_instance - 1 : '';
							} else if ($value == 'next-instance') {
								$this_instance = in_array($this_instance + 1, $formInstances) ? $this_instance + 1 : '';
							}
						}
                        $p1 = $matches['param1'][$key];
                        $p2 = $matches['param3'][$key];
                        $p3 = $matches['param2'][$key];
                        if ($p1 !== "") {
                            if (substr($p1, 0, 1) === "(")
                                $this_field = $this_field.$p1;
                            else
                                $this_field = $this_field.":".$p1;
                        }
                        if ($p2 !== "") $this_field = $this_field.":".$p2;
                        if ($p3 !== "") $this_field = $this_field.":".$p3;
                        $matches['post-pipe'][$key] = is_numeric($this_instance) ? $this_prepended_event.'['.$this_field.']['.$this_instance.']' : ($replaceWithUnderlineIfMissing ? $wrapper.self::missing_data_replacement.$wrapper : $wrapper.$wrapper);
                        $matches['pre-pipe'][$key] = "/" . preg_quote($matches[0][$key], '/') . "/";
                        // Wrap the value in quotes or do SQL escape?
                        if ($escapeSql) {
                            $matches['post-pipe'][$key] = db_escape($matches['post-pipe'][$key]);
                        }
                    }
                }
            }

			// Re-sort arrays by key in case new one was just added in previous foreach that wasn't there originally
			ksort($matches['pre-pipe']);
            ksort($matches['post-pipe']);
        }
        
        //sometimes there is nothing to pipe
        if ($matches['pre-pipe'] != null && !empty($matches['pre-pipe'])) {
			// If replacing all blanks with underscores, do so now
			if ($replaceWithUnderlineIfMissing) {
				foreach ($matches['post-pipe'] as &$thisPostPipe) {
					if ($thisPostPipe == "") $thisPostPipe = self::missing_data_replacement;					
				}
				unset($thisPostPipe);
			}
			// Escape any $ in the text being piped
			foreach ($matches['post-pipe'] as &$thisPostPipe) {
				$thisPostPipe = self::escape_backreference($thisPostPipe);					
			}
			// Replace
            $input = preg_replace($matches['pre-pipe'], $matches['post-pipe'], $input, 1);
        }
		
        return $input;    
    }
    
	// Obtain the survey completion timestamp
    public static function getSurveyTimestamp($Proj, $record, $form_name, $event_id, $instance=1) 
	{
		$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $form_name);
		if (!is_numeric($instance) || !$isRepeatingFormOrEvent) $instance = 1;
        $timestamp_field = $form_name . '_timestamp';
        $fields = array($Proj->table_pk, $timestamp_field, $form_name . '_complete');
        $q = Records::getData($Proj->project_id, 'json', $record, $fields, $event_id, NULL, FALSE, FALSE, TRUE);
        $results = json_decode($q, true);
		if ($isRepeatingFormOrEvent) {
			$repeat_instrument = $Proj->isRepeatingForm($event_id, $form_name) ? $form_name : "";
			foreach ($results as $row) {
				if (isset($row[$timestamp_field]) && $row['redcap_repeat_instrument'] == $repeat_instrument 
					&& $row['redcap_repeat_instance'] == $instance) 
				{
					return $row[$timestamp_field];
				}
			}
		} elseif (isset($results[0][$timestamp_field])) {
			return $results[0][$timestamp_field];
		}
		return "";
    }


    // Replace all {field} embed variables in a label with a SPAN w/ specific class to allow JS to pipe the whole input field
	public static function replaceEmbedVariablesInLabel($label='', $project_id=null, $form=null, $replaceCurlyBracketsWithSquare=false, $replaceCurlyBracketWithUnderscore=false)
	{
		global $lang, $user_rights, $missingDataCodes;

		// Decode label, just in case
		$label = $labelOrig = html_entity_decode($label, ENT_QUOTES, 'UTF-8');

		// If label does not contain at least one { and one }, then return the label as-is
		if ($form == null || !is_numeric($project_id) || strpos($label, '{') === false || strpos($label, '}') === false) return $label;

		$Proj = new Project($project_id);

		// Use regex to match field parts
		if (!preg_match_all('/(\{)([a-z0-9][_a-z0-9]*)(:icons)?(\})/', $label, $fieldMatches, PREG_PATTERN_ORDER)) {
			return $label;
		}

		$original_to_replace = $fieldMatches[0];
		$fields = $fieldMatches[2];

		// Loop through fields and replace (if a valid field on this instrument)
		$embeddedFields = array();
		foreach ($fields as $key=>$this_field) {
			if (!isset($Proj->metadata[$this_field])) continue;
			$embeddedFields[] = $this_field;
			if ($replaceCurlyBracketWithUnderscore) {
				if ($Proj->metadata[$this_field]['form_name'] != $form) continue;
				$label = str_replace($original_to_replace[$key], self::missing_data_replacement, $label);
			} elseif ($replaceCurlyBracketsWithSquare) {
				if ($Proj->metadata[$this_field]['form_name'] != $form) continue;
				if ($Proj->metadata[$this_field]['element_type'] == "file" &&  $Proj->metadata[$this_field]['element_validation_type'] == "signature") {
					// PDFs only: We can't just replace and pipe signatures, so replace field with [signature] instead
					$label = str_replace($original_to_replace[$key], $lang['data_entry_248'], $label);
				} else {
					// Normal: Replace with piped version of variable
					$label = str_replace($original_to_replace[$key], '['.$this_field.']', $label);
				}
			} else {
				// Display icons?
				$iconsClass = ($fieldMatches[3][$key] == ':icons') ? ' embed-show-icons' : '';
				// Is the field from another form? If so, then add extra class so that an error msg is displayed to the user
				$otherFormClass = ($Proj->metadata[$this_field]['form_name'] != $form) ? ' embed-other-form' : '';
				// This is a legit field on this form, so replace with span
				$label = str_replace($original_to_replace[$key], '<span class="rc-field-embed'.$iconsClass.$otherFormClass.'" var="'.$this_field.'" req="'.$Proj->metadata[$this_field]['field_req'].'"></span>', $label);
			}
		}
		if ($label == $labelOrig) return $label;

		// Return string and array of embedded variables
		if ($replaceCurlyBracketsWithSquare || $replaceCurlyBracketWithUnderscore) {
			return $label;
		} else {
			return array($label, $embeddedFields);
		}
	}

	// Determine if ANY instruments in a project have embedded fields
	public static function projectHasEmbeddedVariables($project_id=null)
	{
		if (!isinteger($project_id)) return false;
		$Proj = new Project($project_id);
		foreach (array_keys($Proj->forms) as $this_form) {
			if (self::instrumentHasEmbeddedVariables($project_id, $this_form)) {
				return true;
			}
		}
		return false;
	}

	// Determine if ANY variables are embedded on a given instrument
	public static function instrumentHasEmbeddedVariables($project_id=null, $form=null)
	{
		$embeddedFields = self::getEmbeddedVariables($project_id, $form);
		return !empty($embeddedFields);
	}

	// Get array of variables embedded in a certain field
	public static function getEmbeddedVariablesForField($project_id=null, $field=null, $useDraftMode=false)
	{
		return self::getEmbeddedVariables($project_id, null, $field, $useDraftMode);
	}

	// Find all variables that are embedded on a given instrument
	public static function getEmbeddedVariables($project_id=null, $form=null, $field=null, $useDraftMode=false)
	{
		if (!is_numeric($project_id)) return array();
		$Proj = new Project($project_id);
		$ProjForms = ($useDraftMode && $Proj->project['status'] > 0 && $Proj->project['draft_mode']) ? $Proj->forms_temp : $Proj->forms;
		$ProjMetadata = ($useDraftMode && $Proj->project['status'] > 0 && $Proj->project['draft_mode']) ? $Proj->metadata_temp : $Proj->metadata;

		if ($form != null && !isset($ProjForms[$form])) return array();
		if ($field != null && !isset($ProjMetadata[$field])) return array();

		// Attributes to look for embedding
		$attr_to_check = array('element_label', 'element_enum', 'element_note', 'element_preceding_header');
		// List of embedded fields
		$embeddedFields = array();
		// Loop through fields and put ALL attribute text into single string
		$all_attr_string = "";
		if ($field != null) {
			foreach ($attr_to_check as $this_attr) {
				$attr = $ProjMetadata[$field];
				$all_attr_string .= " " . $attr[$this_attr];
			}
			$allFields = array($field);
		} else {
			foreach ($ProjMetadata as $this_field=>$attr) {
				if ($this_field == $Proj->table_pk || $this_field == $attr['form_name']."_complete") continue;
				if ($form != null && $attr['form_name'] != $form) continue;
				foreach ($attr_to_check as $this_attr) {
					$all_attr_string .= " " . $attr[$this_attr];
				}
			}
			// Gather all variable names
			$allFields = ($form == null) ? array_keys($ProjMetadata) : array_keys($ProjForms[$form]['fields']);
		}

		// Build regex
		$regex = '/(\{)('.implode('|', $allFields).')(:icons)?(\})/';

		// Perform the regex
		if (!preg_match_all($regex, $all_attr_string, $fieldMatches, PREG_PATTERN_ORDER)) {
			return array();
		}

		// Validate the variables found
		$fields = array_unique($fieldMatches[2]);
		foreach ($fields as $key=>$this_field) {
			if (!isset($Proj->metadata[$this_field])) {
				unset($fields[$key]);
			}
		}
		$fields = array_values($fields);

		// Return the valid fields
		return $fields;
	}


	/**
	 * REPLACE VARIABLES IN LABEL
	 * Provide any test string and it will replace a [field_name] with its stored data value.
	 * @param array $record_data - Array of record data (record is 1st key, event_id is 2nd key, field is 3rd key) to be used for the replacement.
	 * @param int $event_id - The current event_id for the form/survey.
	 * @param string $record - The name of the record. If $record_data is empty/null, it will use $record to pull all relevant data for
	 * that record to create $record_data on the fly.
	 * @param boolean $replaceWithUnderlineIfMissing - If true, replaces data value with 6 underscores, else does NOT replace anything.
	 * Returns the string with the replacements.
	 */
	public static function replaceVariablesInLabel($label='', $record=null, $event_id=null, $instance=1, $record_data=array(),
											$replaceWithUnderlineIfMissing=true, $project_id=null, $wrapValueInSpan=true, 
											$repeat_instrument="", $recursiveCount=1, $simulation=false, $applyDeIdExportRights=false,
											$form=null, $participant_id=null, $returnDatesAsYMD=false, $ignoreIdentifiers=false)
	{
		global $lang, $user_rights, $missingDataCodes;
		// Set global vars that we can use in a callback function for replacing values inside HREF attributes of HTML link tags
		global $piping_callback_global_string_to_replace, $piping_callback_global_string_replacement;
		// Decode label, just in case
		$label = $labelOrig = html_entity_decode($label, ENT_QUOTES, 'UTF-8');
		
		// If label does not contain at least one [ and one ], then return the label as-is
		if (strpos($label, '[') === false || strpos($label, ']') === false) return $label;

		// If no record name nor data provided
		if (empty($record_data) && !$simulation && ($record == null || $record == '')) return $label;

		// If we're not in a project-level script but have a project_id passed as a parameter, then instantiate $Proj
		if (defined('PROJECT_ID') && !is_numeric($project_id)) {
			$project_id = PROJECT_ID;
		}
		$Proj = new Project($project_id);
		
		// Pipe special tags that function as variables
		$label = self::pipeSpecialTags($label, $project_id, $record, $event_id, $instance, null, false, $participant_id, $form, $replaceWithUnderlineIfMissing);
		
		// Use regex to match field parts
		if (!preg_match_all('/(?:\[([a-z0-9][_a-z0-9]*)\])?\[([a-z][_.a-zA-Z0-9:\(\)-]*)\](\[(\d+)\])?/', $label, $fieldMatches, PREG_PATTERN_ORDER)) {
			return $label;
		}
		
		$original_to_replace = $fieldMatches[0];
		$field_events = $fieldMatches[1];
		$fields = $fieldMatches[2];
		$repeating_instances = $fieldMatches[4];
		$repeating_instruments = $mc_field_params = $checkbox_codes = array();
		// Replace events with event_id
		foreach ($field_events as $key=>$this_event) {
			if ($this_event == '') {
				$field_events[$key] = is_numeric($event_id) ? $event_id : $Proj->firstEventId;
			} else {
				$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
				$field_events[$key] = is_numeric($this_event_id) ? $this_event_id : $Proj->firstEventId;
			}
		}
		// Validate that the fields matched actually do exist on repeating forms or events
		foreach ($fields as $key=>$this_field) {
			// Remove anything after a colon, which would be a parameter
			list ($this_field_temp, $params) = explode(":", $this_field, 2);
			list ($this_field, $checkboxCode) = explode("(", $this_field_temp, 2);
			if (substr($checkboxCode, -1) == ')') $checkboxCode = substr($checkboxCode, 0, -1);
			$fields[$key] = $this_field;				
			$this_form = $Proj->metadata[$this_field]['form_name'];
			$field_type = $Proj->metadata[$this_field]['element_type'];
			if (!isset($Proj->metadata[$this_field]) || $field_type == 'descriptive') {
				unset($original_to_replace[$key], $field_events[$key], $fields[$key], $repeating_instances[$key]);
				continue;
			}
			// Determine the repeating instrument (or lack thereof if a repeating event)
			if ($Proj->isRepeatingForm($field_events[$key], $this_form)) {
				$repeating_instruments[$key] = $this_form;
			} else {
				$repeating_instruments[$key] = '';
			}
			// Add any checkbox codes or MC params
			$mc_field_params[$key] = empty($params) ? array() : explode(":", $params);
			$checkbox_codes[$key] = $checkboxCode;
			$original_to_replace[$key] = "/" . preg_quote($original_to_replace[$key], '/') . "/";
		}
		
		// print "<hr>#########";
		// print_array($original_to_replace);
		// print_array($field_events);
		// print_array($fields);
		// print_array($mc_field_params);
		// print_array($repeating_instances);
		// print_array($repeating_instruments);	

		// If no fields were found in string, then return the label as-is
		if (empty($fields)) return $label;

		// Check upfront to see if the label contains a link
		$regex_link = "/(<)([^<]*)(href\s*=\s*)(\"|')([^\"']+)(\"|')([^<]*>)/i";
		$label_contains_link = preg_match($regex_link, $label);
		
		// If a simulation, then create fake data
		if ($simulation) {
			$fieldsFakeData = array_fill_keys($fields, $lang['survey_1082']);
			$record_data = array($record=>array($event_id=>$fieldsFakeData));
		}

		// If $record_data is not provided, obtain it via $record
		if (empty($record_data)) {
			$record_data = Records::getData($project_id, 'array', $record, $fields, $field_events);
		}
		
		// If field should be removed due to De-ID/Remove Identifier data export rights
		$deidFieldsToRemove = array();
		if ($applyDeIdExportRights && isset($user_rights) && is_array($user_rights) && $user_rights['data_export_tool'] != '1') {
			$deidFieldsToRemove = DataExport::deidFieldsToRemove($fields, ($user_rights['data_export_tool'] == '3'));
		}
 
		// Loop through all event-fields/fields and replace them with data in the label string
		$replacements = array();
		foreach ($fields as $key=>$this_field)
		{
			$this_event_id = $field_events[$key];
			$string_to_replace = $original_to_replace[$key];
			// Set field type
			$field_type = $Proj->metadata[$this_field]['element_type'];
			// Get the field's form
			$this_field_form = $Proj->metadata[$this_field]['form_name'];
			// Set data_value
			$data_value = ''; // default
			$this_instance = '';
			if (isset($record_data[$record])) {
				// Get repeat instrument (if applicable)
				$repeat_instrument = $repeating_instruments[$key];
				$this_instance = $repeating_instances[$key];
				if (is_numeric($this_instance)) {
					// Dealing with repeating forms/events
					$data_value = isset($record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$this_instance][$this_field]) ? $record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$this_instance][$this_field] : '';
				} elseif (is_numeric($instance) && ($repeat_instrument != '' || $Proj->isRepeatingEvent($this_event_id))) {
					// Dealing with repeating forms/events (when $instance is passed as a param to this method)
					$data_value = isset($record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$instance][$this_field]) ? $record_data[$record]['repeat_instances'][$this_event_id][$repeat_instrument][$instance][$this_field] : '';
				} else {
					// Normal non-repeating data
					$data_value = isset($record_data[$record][$this_event_id][$this_field]) ? $record_data[$record][$this_event_id][$this_field] : '';
				}
			}
			// If not data exists for this field AND the flag is set to not replace anything when missing, then stop this loop.
			$has_data_value = false;
			$isCheckbox = $Proj->isCheckbox($this_field);
			if ($isCheckbox && implode("", $data_value) == "") {
				$data_value = array();
				foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $thisCode) {
					$data_value[$thisCode] = '0';
				}
				// Check all values to see if all are 0s
				$has_data_value = true;
			} else {
				// If \n (not a line break), then replace the backslash with its corresponding HTML character code) to
				// prevent parsing issues with MC field options that are piping receivers.
				$data_value = str_replace("\\n", "&#92;n", $data_value);
				if ($data_value != '') $has_data_value = true;
			}
			// Obtain data value for replacing
			$chkboxType = $mcKey = $mcType = "";
			if ($has_data_value) 
			{
			    if ($ignoreIdentifiers && $Proj->metadata[$this_field]['field_phi']) {
                    $data_value = "[*DATA REMOVED*]";
                }
				// If field should be removed due to De-ID/Remove Identifier data export rights, then replace value with redacted text
				elseif ($applyDeIdExportRights && !$Proj->isCheckbox($this_field) && in_array($this_field, $deidFieldsToRemove)) {
					$data_value = "[*DATA REMOVED*]";
				}
				// Get field's validation type and enum
				$field_validation = $Proj->metadata[$this_field]['element_validation_type'];
				$field_enum = $Proj->metadata[$this_field]['element_enum'];
				$isMCfield = $Proj->isMultipleChoice($this_field);
				// Ontology search field?
				$isOntologyAutoSuggestField = ($Proj->metadata[$this_field]['element_type'] == 'text' 
												&& $field_enum != '' && strpos($field_enum, ":") !== false);
				// Missing data codes (non-checkbox)
				if (!$isCheckbox && !empty($missingDataCodes) && isset($missingDataCodes[$data_value])) {
					// Set value as label for MC field, otherwise output raw value
					$replaceWithValue = in_array('value', $mc_field_params[$key]);
					if (!$replaceWithValue) {
						$data_value = $missingDataCodes[$data_value];
					}
					$mcType = $replaceWithValue ? 'value' : 'label';
				// MC FIELD: If field is multiple choice, then replace using its option label and NOT its raw value
				} elseif ($field_type == 'sql' || $isMCfield || $isOntologyAutoSuggestField) {
					// Parse enum choices into array
					if ($field_type == 'sql') {
						if (self::containsSpecialTags($field_enum)) {
							$field_enum = getSqlFieldEnum($field_enum, $project_id, $record, $event_id, $instance, (defined("USERID") ? USERID : null), null, $form);
						} else {
							$field_enum = $Proj->getExecutedSql($this_field);
						}
					}
					//add missing Data Codes to piping choices for multiple choice fields
					$choices = parseEnum($field_enum);
					// Replace ontology data value with its label
					if ($isOntologyAutoSuggestField) {
						// Get the name of the name of the web service API and the category (ontology) name
						list ($autosuggest_service, $autosuggest_cat) = explode(":", $field_enum, 2);
						$replaceWithValue = in_array('value', $mc_field_params[$key]);
						$mcType = $replaceWithValue ? 'value' : 'label';
						if (!$replaceWithValue) {
							$data_value = Form::getWebServiceCacheValues(PROJECT_ID, $autosuggest_service, $autosuggest_cat, $data_value);
						}
					// Replace data value with its option label
					} elseif ($Proj->isCheckbox($this_field)) {
						// Missing data codes
						if (!empty($missingDataCodes) && self::enumArrayHasMissingValue($data_value)) {
							$choices = $choices+$missingDataCodes;
						}
						// Set value as comma-delimited labels for Checkbox field
						$data_value2 = array();
						if (in_array('checked', $mc_field_params[$key])) {
							$chkboxType = "checked";
						} elseif (in_array('unchecked', $mc_field_params[$key])) {
							$chkboxType = "unchecked";
						} elseif ($checkbox_codes[$key] != '') {
							$mcKey = $checkbox_codes[$key]."";
							$chkboxType = "choice";
						} else {
							$chkboxType = "checked";
						}
						$replaceWithValue = in_array('value', $mc_field_params[$key]);
						$mcType = $replaceWithValue ? 'value' : 'label';
						$allUnchecked = (array_sum($data_value) < 1);
						foreach ($choices as $this_code=>$this_label) {
							$this_code .= "";
							// Skip checked or unchecked options
							if ($chkboxType == "checked") {
								if ($data_value[$this_code] == '0') continue;
							} elseif ($chkboxType == "unchecked") {
								if ($data_value[$this_code] == '1') continue;
							} elseif ($chkboxType == "choice" && $mcKey !== $this_code) {
								continue;
							} elseif ($chkboxType == "choice" && $mcKey === $this_code) {
								// Display "checked" or "unchecked" text
								if ($replaceWithValue) {
									$this_code = $data_value[$this_code];
								} else {
									$this_label = (isset($data_value[$this_code]) && $data_value[$this_code] == '1') ? $lang['global_143'] : $lang['global_144'];
								}
							}
							$data_value2[] = $replaceWithValue ? $this_code : $this_label;
						}
						// Format the text as comma delimited
						$data_value = implode($lang['comma']." ", $data_value2);
						// If value is empty, replace with underlines (if appropriate)
						if ($data_value == '' && $replaceWithUnderlineIfMissing 
							&& (($allUnchecked && $chkboxType == 'checked') || (!$allUnchecked && $chkboxType == 'unchecked'))) {
							$data_value = self::missing_data_replacement;
						}
					} elseif (isset($choices[$data_value]) || $missingDataCodes[$data_value]) {
						// Set value as label for MC field, otherwise output raw value
						$replaceWithValue = in_array('value', $mc_field_params[$key]);
						$mcType = $replaceWithValue ? 'value' : 'label';
						if (!$replaceWithValue) {
							$data_value = $choices[$data_value];
						}
					} else {
						// If value is blank or orphaned (not a valid coded value), then set as blank
						$data_value = self::missing_data_replacement;
					}
				}
				// If data value is a formatted date (date[time] MDY or DMY), then reformat it from YMD to specified format
				elseif (!$returnDatesAsYMD && substr($field_validation, 0, 4) == 'date' && (substr($field_validation, -4) == '_mdy' || substr($field_validation, -4) == '_dmy') && $data_value != DEID_TEXT) {
					$data_value = DateTimeRC::datetimeConvert($data_value, 'ymd', substr($field_validation, -3));
				}
			} else {
				// No data value saved yet
				$data_value = ($replaceWithUnderlineIfMissing) ? self::missing_data_replacement : '';
				// MC fields
				$replaceWithValue = in_array('value', $mc_field_params[$key]);
				$mcType = $replaceWithValue ? 'value' : 'label';
			}
			
			// Add extra piping class param for checkboxes
			$this_mc_param = "";
			if ($chkboxType == "choice") {
				$this_mc_param = "-choice-" . $mcKey;
			} elseif ($chkboxType == "checked" || $chkboxType == "unchecked") {
				$this_mc_param = "-checked-" . $chkboxType;
			}
			if ($this_mc_param != "" || $mcType != "") {
				$this_mc_param .= "-" . $mcType;
			}
			
			// Set string replacement text
			$string_replacement = 	// For text/notes fields, make sure we double-html-encode these + convert new lines
									// to <br> tags to make sure that we end up with EXACTLY the same value and also to prevent XSS via HTML injection.
									(($field_type == 'textarea' || $field_type == 'text')
										? filter_tags(str_replace(array("\r","\n"), array("",""), nl2br($data_value))) //htmlspecialchars(nl2br(htmlspecialchars($data_value, ENT_QUOTES)), ENT_QUOTES)
										: $data_value
									);
			$string_replacement_span = 	RCView::span(array('class'=>
											// Class to all piping receivers
											self::piping_receiver_class." ".
											// If field is an identifier, then add extra class to denote this
											($Proj->metadata[$this_field]['field_phi'] == '1' ? self::piping_receiver_identifier_class." " : "") .
											// Add field/event-level class to span
											self::piping_receiver_class_field."$this_event_id-$this_field".$this_mc_param),
											$string_replacement
										);

			// Before doing a general replace, let's first replace anything in the HREF attribute of a link.
			// Do a direct replace without the SPAN tag (because it won't work any other way), but this means that it can
			// never get updated dynamically via JavaScript if changed on the page (probably an okay assumption).
			if ($label_contains_link) {
				// Set global vars to be used in the callback function
				$piping_callback_global_string_to_replace = $string_to_replace;
				$piping_callback_global_string_replacement = $string_replacement;
				$label = preg_replace_callback($regex_link, "Piping::replaceVariablesInLabelCallback", $label);
			}
			
			// Note that this value is from a whole other instance/form/event. Thus its field is not on the current page, so it doesn't need to be wrapped in a Span for real-time piping.
			$fromOtherInstance = (is_numeric($this_instance) && $form != null && ($this_instance != $instance || $event_id != $this_event_id || $form != $this_field_form));

			// Add to replacements array	
			$replacements[$key] = self::escape_backreference(($wrapValueInSpan && !$fromOtherInstance) ? $string_replacement_span : $string_replacement);
		}
		
		// Replace all
		$label = preg_replace($original_to_replace, $replacements, $label, 1);		
		
		// RECURSIVE: If label appears to still have more piping to do, try again recursively
		if (strpos($label, '[') !== false && strpos($label, ']') !== false && $recursiveCount <= 10) {
			$recursiveLabel = self::replaceVariablesInLabel($label, $record, $event_id, $instance, array(),
									$replaceWithUnderlineIfMissing, $project_id, $wrapValueInSpan, $repeat_instrument, ++$recursiveCount,
									$simulation, $applyDeIdExportRights, $form, $participant_id, $returnDatesAsYMD, $ignoreIdentifiers);
			if ($label != $recursiveLabel) {
				$label = $recursiveLabel;
			}
		}

		// Return the label
		return $label;
	}


	// Callback function for replaceVariablesInLabel()
	public static function replaceVariablesInLabelCallback($matches)
	{
		// Set global vars that we can use in a callback function for replacing values inside HREF attributes of HTML link tags
		global $piping_callback_global_string_to_replace, $piping_callback_global_string_replacement;
		// Remove first element (because we just need to return the sub-elements)
		unset($matches[0]);
		// If label does not contain at least one [ and one ], then return the label as-is
		if (strpos($matches[5], '[') !== false && strpos($matches[5], ']') !== false) {
			// Now replace the event/field in the string
			$matches[5] = preg_replace($piping_callback_global_string_to_replace, $piping_callback_global_string_replacement, $matches[5], 1);
		}
		// Return the matches array as a string with replaced text
		return implode("", $matches);
	}

	// Escape dollar signs in string that will replace text via preg_replace
	public static function escape_backreference($x){
		return preg_replace('/\$(\d)/', '\\\$$1', $x);
	}

	// Pass an enum array for a CHECKBOX (key=raw code, value=0 or 1), and return boolean if at least ONE choice
	// is a missing data code with a value of "1"
	public static function enumArrayHasMissingValue($enum_array=array())
	{
		global $missingDataCodes;
		// Loop through enum array
		foreach ($enum_array as $code=>$val) {
			if ($val == '0') continue;
			if (isset($missingDataCodes[$code])) return true;
		}
		// If we made it this far, then return false
		return false;
	}

	/**
	 * PIPING EXPLANATION
	 * Output general instructions and documentation on how to utilize the piping feature.
	 */
	public static function renderPipingInstructions()
	{
		global $lang, $isAjax;
		// Place all HTML into $h
		$h = '';
		//
		$h .= 	RCView::div(array('class'=>'clearfix'),
					RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:0 0 10px;'),
						RCView::img(array('src'=>'pipe.png','style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_456'])
					) .
					RCView::div(array('style'=>'text-align:right;float:right;'),
						($isAjax 
						?	RCView::a(array('href'=>PAGE_FULL, 'target'=>'_blank', 'style'=>'text-decoration:underline;'),
								$lang['survey_977']
							)
						: 	RCView::img(array('src'=>'redcap-logo.png'))
						)
					)
				) .
				RCView::div('',
					$lang['design_457'] . " " .
					RCView::a(array('href'=>'https://redcap.vanderbilt.edu/surveys/?s=ph9ZIB', 'target'=>'_blank', 'style'=>'text-decoration:underline;'), $lang['design_476']) .
					$lang['period']
				) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_458']) .				
				RCView::div('', $lang['design_459']) .
				RCView::ul(array('style'=>'margin:5px 0;'),
					RCView::li(array(), $lang['global_40']) .
					RCView::li(array(), $lang['database_mods_69']) .
					RCView::li(array(), $lang['database_mods_65']) .
					RCView::li(array(), $lang['design_461']) .
					RCView::li(array(), $lang['design_462']) .
					RCView::li(array(), $lang['design_460']) .
					RCView::li(array(), $lang['design_568']) .
					RCView::li(array(), $lang['survey_65']) .
					RCView::li(array(), $lang['survey_747']) .
					RCView::li(array(), $lang['design_464']) .
					RCView::li(array(), $lang['design_506']) .
					RCView::li(array(), $lang['design_513']) .
					RCView::li(array(), $lang['piping_43'])
				) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_470']) .
				RCView::div('', $lang['design_753']) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_465']) .
				RCView::div('', $lang['design_466']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_756']) .
				RCView::div(array('style'=>'margin:10px 0 0;'), $lang['design_467']) .
				RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_754']) .
				RCView::div('', $lang['design_755']) .
				RCView::ul(array('style'=>'margin:5px 0;'),
					RCView::li(array('style'=>''), "<b>[my_checkbox:checked]</b> - " . $lang['design_757']) .
					RCView::li(array('style'=>''), "<b>[my_checkbox:unchecked]</b> - " . $lang['design_758']) .
					RCView::li(array('style'=>''), "<b>[my_checkbox(code)]</b> - " . $lang['design_759'])
				) .
				RCView::div('', $lang['design_760']) .
				## Example images
				// Example 1
				RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
					$lang['design_472'] . " 1"
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_475']) .
					RCView::img(array('src'=>'piping_example_mc1c.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_473']) .
					RCView::img(array('src'=>'piping_example_mc1a.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_474']) .
					RCView::img(array('src'=>'piping_example_mc1b.png', 'style'=>'border:1px solid #666;'))
				) .
				// Example 2
				RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
					$lang['design_472'] . " 2"
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_475']) .
					RCView::img(array('src'=>'piping_example_text1a.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_473']) .
					RCView::img(array('src'=>'piping_example_text1b.png', 'style'=>'border:1px solid #666;'))
				) .
				RCView::div(array('style'=>'margin:5px 0 0;'),
					RCView::div(array('style'=>'font-weight:bold;font-size:13px;'), $lang['design_474']) .
					RCView::img(array('src'=>'piping_example_text1c.png', 'style'=>'border:1px solid #666;'))
				)
				;
		// Return HTML
		return $h;
	}


	/**
	 * FIELD EMBEDDING EXPLANATION
	 * Output general instructions and documentation on how to utilize the field embedding feature.
	 */
	public static function renderFieldEmbedInstructions()
	{
		global $lang, $isAjax;
		// Place all HTML into $h
		$h = '';
		//
		$h .= 	RCView::div(array('class'=>'clearfix'),
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:0 0 10px;'),
					"<i class='fas fa-level-down-alt'></i> " .
					RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_795'])
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					($isAjax
						?	RCView::a(array('href'=>PAGE_FULL, 'target'=>'_blank', 'style'=>'text-decoration:underline;'),
							$lang['survey_977']
						)
						: 	RCView::img(array('src'=>'redcap-logo.png'))
					)
				)
			) .
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_806']) .
			RCView::div('', $lang['design_805']) .
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_804']) .
			RCView::div('', $lang['design_807']) .
			RCView::ol(array('style'=>'margin:5px 0;'),
				RCView::li(array(), $lang['design_808']) .
				RCView::li(array(), $lang['design_809']) .
				RCView::li(array(), $lang['design_811'])
			) .
			RCView::div(array('style'=>'color:#800000;margin:20px 0 5px;font-size:14px;font-weight:bold;'), $lang['design_812']) .
			RCView::ul(array('style'=>'margin:5px 0;'),
				RCView::li(array(), $lang['design_814']) .
				RCView::li(array(), $lang['design_818']) .
				RCView::li(array(), $lang['design_817']) .
				RCView::li(array(), $lang['design_813']) .
				RCView::li(array(), $lang['design_816']) .
				RCView::li(array(), $lang['design_819']) .
				RCView::li(array(), $lang['design_815']) .
				RCView::li(array(), $lang['design_824']) .
				RCView::li(array(), $lang['survey_105']." ".$lang['design_823'])
			) .
			## Example images
			// Setup
			RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
				$lang['design_810']
			) .
			RCView::div(array('style'=>'margin:5px 0 0;'),
				RCView::img(array('src'=>'field_embed_example3.png', 'style'=>'border:1px solid #666;max-width:650px;'))
			) .
			// Example 1
			RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
				$lang['design_472'] . " 1"
			) .
			RCView::div(array('style'=>'margin:5px 0 0;'),
				RCView::img(array('src'=>'field_embed_example1.png', 'style'=>'border:1px solid #666;'))
			) .
			// Example 2
			RCView::div(array('style'=>'color:#800000;margin:40px 0 10px;font-size:14px;font-weight:bold;'),
				$lang['design_472'] . " 2"
			) .
			RCView::div(array('style'=>'margin:5px 0 0;'),
				RCView::img(array('src'=>'field_embed_example2.png', 'style'=>'border:1px solid #666;'))
			)
		;
		// Return HTML
		return $h;
	}
}
