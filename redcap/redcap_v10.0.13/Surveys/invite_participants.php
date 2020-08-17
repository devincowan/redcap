<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If not using a type of project with surveys, then don't allow user to use this page.
if (!$surveys_enabled) redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");

// Are we on public survey link page?
$publicSurveyLinkPage = !(isset($_GET['participant_list']) || isset($_GET['email_log']));

// If no survey id in URL, then determine what it should be here (first available survey_id)
if (!isset($_GET['survey_id']))
{
	if ($Proj->firstFormSurveyId != null) {
		// Get first form's survey_id
		$_GET['survey_id'] = Survey::getSurveyId();
	} elseif (!empty($Proj->surveys)) {
		// Surveys exist, but the first form is not a survey. So get the first available survey_id and the first available
		// event (exclude any "deleted"/orphaned survey instruments)
		foreach ($Proj->eventsForms as $these_forms) {
			foreach ($these_forms as $form_name) {
				if (!isset($Proj->forms[$form_name]['survey_id'])) continue;
				$_GET['survey_id'] = $Proj->forms[$form_name]['survey_id'];
				break 2;
			}
		}
		// If first form isn't a survey and user didn't explicity click the Public Survey Link tab, then redirect on to Participant List
		if (!isset($_GET['public_survey']) && !isset($_GET['participant_list']) && !isset($_GET['email_log'])) {
			redirect(PAGE_FULL . "?pid=$project_id&participant_list=1");
		}
	} elseif (empty($Proj->surveys)) {
		// If no surveys have been enabled, then redirect to Online Designer to enable them
        if ($user_rights['design']) {
            redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&dialog=enable_surveys");
		} else {
			include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
			print "<div class='yellow'>{$lang['survey_1273']}</div>";
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
        }
	}
}

// Ensure the survey_id belongs to this project
if (!$Proj->validateSurveyId($_GET['survey_id']))
{
	redirect(APP_PATH_WEBROOT . "Surveys/create_survey.php?pid=$project_id&view=showform&redirectInvite=1");
}

// Obtain current event_id
if (!isset($_GET['event_id'])) {
	$_GET['arm_id']   = getArmId();
	$_GET['event_id'] = $Proj->getFirstEventIdArmId($_GET['arm_id']);
}

$arm = getArm();

// Retrieve survey info
$q = db_query("select * from redcap_surveys where project_id = $project_id and survey_id = " . $_GET['survey_id']);
foreach (db_fetch_assoc($q) as $key => $value)
{
	$$key = trim(html_entity_decode($value, ENT_QUOTES));
}

// VALIDATE EVENT_ID FOR SURVEY: If event_id isn't applicable for the survey_id that we have here, then get first event_id that is applicable.
if (!$publicSurveyLinkPage && !$Proj->validateEventIdSurveyId($_GET['event_id'], $_GET['survey_id']))
{
	// Get first event for this survey
	foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
		if (in_array($Proj->surveys[$_GET['survey_id']]['form_name'], $these_forms)) {
			$_GET['event_id'] = $this_event_id;
			break;
		}
	}
}

// Check if this is a follow-up survey
$isFollowUpSurvey = $Proj->isFollowUpSurvey($_GET['survey_id']);

// Get all previously sent emails to put into hidden Dropdown Menu in pop-up dialog for sending participant emails
if (isset($_GET['participant_list']))
{
    $emailSelect = array();
    $oneYearAgo = date("Y-m-d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") - 1));
    $sql = "select email_content, email_sent from redcap_surveys_emails 
		where survey_id = {$_GET['survey_id']} and (email_sent is null or email_sent > '$oneYearAgo')
		order by email_id desc limit 5000";
    $q = db_query($sql);
    $divDispPrevEmails_display = (!db_num_rows($q) ? "display:none;" : "");
    //Loop through query
    while ($row = db_fetch_array($q)) {
        $row['email_content'] = label_decode($row['email_content']);
        // Match and replace all static survey URLs
        $regex = "/(?:(?:https?):\/\/|www\.)(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[-A-Z0-9+&@#\/%=~_|$?!:,.])*(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[A-Z0-9+&@#\/%=~_|$])/im";
        preg_match_all($regex, $row['email_content'], $matches);
        if (isset($matches[0]) && !empty($matches[0])) {
            foreach (array_unique($matches[0]) as $key => $url) {
                if (strpos($url, APP_PATH_WEBROOT_FULL . 'surveys/') === 0) {
                    $urlReplace[$url] = "[survey-url]";
                }
            }
            if (!empty($urlReplace)) {
                $row['email_content'] = str_replace(array_keys($urlReplace), $urlReplace, $row['email_content']);
            }
        }
        //Remove HTML tags and quotes because they cause problems
        $row['email_content'] = str_replace("\"", "&quot;", RCView::escape($row['email_content'], false));
        //Do not show repeating emails (if same email was sent more than once)
        if (!isset($emailSelect[$row['email_content']])) {
            // Make sure text is not too long and format timestamp
            $this_val = DateTimeRC::format_ts_from_ymd($row['email_sent']) . ' - ' . strip_tags(label_decode($row['email_content']));
            if (strlen($this_val) >= 100) $this_val = substr($this_val, 0, 98) . '...';
            // Store in array
            $emailSelect[$row['email_content']] = $this_val;
        }
    }
    $emailSelect = array_reverse($emailSelect);
    // Keep only 50 most recent
    if (count($emailSelect) > 50) {
        $emailSelect = array_slice($emailSelect, 50, null, true);
    }
}


// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Call JS files
loadJS('Libraries/clipboard.js');
loadJS('InviteParticipants.js');

// Title
renderPageTitle("<i class='fas fa-chalkboard-teacher'></i> ".$lang['app_24']);



// TABS
?>
<div id="sub-nav" class="d-none d-sm-block" style="margin:5px 0 20px;">
	<ul>
		<li<?php echo ((isset($_GET['public_survey']) || (!isset($_GET['email_log']) && !isset($_GET['participant_list']))) ? ' class="active"' : '') ?>>
			<a href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?public_survey=1&pid=<?php echo $project_id ?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><i class="fas fa-link"></i> <?php echo $lang['survey_279'] ?></a>
		</li>
		<li<?php echo (isset($_GET['participant_list']) ? ' class="active"' : '') ?>>
			<a href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?participant_list=1&pid=<?php echo $project_id ?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><i class="fas fa-user-friends"></i> <?php echo $lang['survey_37'] ?></a>
		</li>
		<li<?php echo (isset($_GET['email_log']) ? ' class="active"' : '') ?>>
			<a href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?email_log=1&pid=<?php echo $project_id ?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><i class="fas fa-mail-bulk"></i> <?php echo $lang['survey_350'] ?></a>
		</li>
	</ul>
</div>
<div class="clear"></div>

<div class="btn-group d-block d-sm-none" role="group" style="margin:10px 0;">
	<button type="button" class="btn btn-defaultrc dropdown-toggle active" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<?php
		if (isset($_GET['public_survey']) || (!isset($_GET['email_log']) && !isset($_GET['participant_list']))) {
			?><i class="fas fa-link"></i> <?php echo $lang['survey_279'];
		} elseif (isset($_GET['participant_list'])) {
			?><i class="fas fa-user-friends"></i> <?php echo $lang['survey_37'];
		} elseif (isset($_GET['email_log'])) {
			?><i class="fas fa-mail-bulk"></i> <?php echo $lang['survey_350'];
		}
		?>
	</button>
	<div class="dropdown-menu">
		<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?public_survey=1&pid=<?php echo $project_id ?>" style="font-size:15px;color:#393733;padding:6px 9px 7px 10px;"><i class="fas fa-link"></i> <?php echo $lang['survey_279'] ?></a>
		<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?participant_list=1&pid=<?php echo $project_id ?>" style="font-size:15px;color:#393733;padding:6px 9px 7px 10px;"><i class="fas fa-user-friends"></i> <?php echo $lang['survey_37'] ?></a>
		<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT ?>Surveys/invite_participants.php?email_log=1&pid=<?php echo $project_id ?>" style="font-size:15px;color:#393733;padding:6px 9px 7px 10px;"><i class="fas fa-mail-bulk"></i> <?php echo $lang['survey_350'] ?></a>
	</div>
</div>


<style type="text/css">
.wrapemail { line-height:13px; overflow: visible !important; white-space: normal !important; word-break: break-all !important; word-wrap: break-word !important; }
</style>
<?php










## SURVEY INVITATION EMAIL LOG
if (isset($_GET['email_log']))
{
	// Instantiate object
	$surveyScheduler = new SurveyScheduler();
	// Instructions
	print RCView::p(array('style'=>'margin-bottom:20px;'),
			$lang['survey_399']." \"".getTimeZone()."\"".$lang['survey_297']." ".DateTimeRC::format_ts_from_ymd(NOW).$lang['period']
		  );
	// Display a table listing all survey invitations (past, present, and future)
	print $surveyScheduler->renderSurveyInvitationLog();
	?>
	<script type="text/javascript">
	var langInvLog01 = '<?php print js_escape($lang['survey_1213']) ?>';
	var langInvLog02 = '<?php print js_escape($lang['survey_1214']) ?>';
	var langInvLog03 = '<?php print js_escape($lang['survey_1215']) ?>';
	var langInvLog04 = '<?php print js_escape($lang['']) ?>';
	$(function(){
		// Set datetime pickers
		$('.filter_datetime_mdy').datetimepicker({
			buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
			hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
			timeFormat: 'hh:mm', constrainInput: true
		});
		// Add fade mouseover for "delete scheduled invitation" icons
		$(".inviteLogDelIcon").mouseenter(function() {
			$(this).removeClass('opacity50');
		}).mouseleave(function() {
			$(this).addClass('opacity50');
		});
		// Add trigger to "send time" column click when sorting to change image
		$('div#email_log_table .hDivBox table tr th:first').click(); // pre-click so the next will change it to descending
		$('div#email_log_table .hDivBox table tr th:first').click(function(){
			$('.survlogsendarrow').toggle();
		});
		// If user clicks other header other than first header, hide the arrow icon because they don't make sense anymore
		$('div#email_log_table .hDivBox table tr th:not(:first,:last)').click(function(){
			$('.survlogsendarrow').remove();
		});
		// Disable onclick for last table header on Invitation Log
		$('div#email_log_table .hDivBox table th:last').css('cursor','initial').attr('onclick','');
	})
	</script>
	<?php
}









## PUBLIC SURVEY LINK
elseif ($publicSurveyLinkPage)
{
	// Obtain the public survey hash [for current arm]
	$hash = Survey::getSurveyHash($_GET['survey_id'], $_GET['event_id']);

	// Build drop-down list of FIRST surveys/events for EACH arm
	if ($multiple_arms) {
		// Create drop-down of ALL surveys and, if longitudinal, the events for which they're designated
		$surveyEventOptions = array();
		foreach ($Proj->events as $this_arm=>$arm_attr)
		{
			$this_event_id = array_shift(array_keys($arm_attr['events']));
			// Add event name
			$event_name = $Proj->eventInfo[$this_event_id]['name_ext'];
			// Truncate survey title if too long
			$survey_title = $Proj->surveys[$Proj->firstFormSurveyId]['title'];
			if (strlen($survey_title.$event_name) > 70) {
				$survey_title = substr($survey_title, 0, 67-strlen($event_name)) . "...";
			}
			// Add this survey/event as drop-down option
			$surveyEventOptions[$arm_attr['id']] = "\"$survey_title\" - $event_name";
		}
		// Collect HTML
		$surveyEventDropdown = RCView::select(array('class'=>"x-form-text x-form-field",
			'style'=>'max-width:400px;font-weight:bold;font-size:12px;color:#A00000;',
			'onchange'=>"var val=this.value;showProgress(1);setTimeout(function(){window.location.href=app_path_webroot+page+'?pid='+pid+'&public_survey=1&arm_id='+val;},300);"),
				$surveyEventOptions, (isset($_GET['arm_id']) ? $_GET['arm_id'] : $Proj->firstArmId), 500
			);
	}

	?>
	<!-- Public survey link -->
	<div style="font-size:11px;max-width:700px;">

		<p><?php echo $lang['survey_165'] ?></p>

		<?php
		if ($Proj->firstFormSurveyId == null) {
			// If first form is not yet a survey, then cannot display public survey link, so inform user to enable form as survey
			print 	RCView::div(array('class'=>'yellow','style'=>'padding:10px;'),
						RCView::div(array('style'=>'font-weight:bold;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							$lang['survey_352']
						) .
						$lang['survey_353'] .
						RCView::div(array('style'=>'padding-top:15px;'),
							RCView::button(array('class'=>'jqbuttonmed','style'=>'','onclick'=>"window.location.href=app_path_webroot+'Surveys/create_survey.php?pid=$project_id&view=showform&page={$Proj->firstForm}&redirectInvite=1';"),
								$lang['survey_354']
							)
						)
					);
		} elseif ($longitudinal && !in_array($Proj->firstForm, $Proj->eventsForms[$Proj->firstEventId])) {
			// If first form is not designated for the first event, then cannot display public survey link, so inform user to designate form for event
			print 	RCView::div(array('class'=>'yellow','style'=>'padding:10px;'),
						RCView::div(array('style'=>'font-weight:bold;'),
							RCView::img(array('src'=>'exclamation_orange.png')) .
							$lang['survey_567']
						) .
						$lang['survey_568'] .
						RCView::div(array('style'=>'padding-top:15px;'),
							RCView::button(array('class'=>'jqbuttonmed','style'=>'','onclick'=>"window.location.href=app_path_webroot+'Design/designate_forms.php?pid=$project_id';"),
								$lang['survey_569']
							)
						)
					);
		} else { ?>

			<p>
				<font style="color:#800000;"><?php echo $lang['survey_72'] ?></font> <?php echo $lang['survey_73'] ?>
			</p>


			<?php if ($multiple_arms) { ?>
				<!-- Drop-down for changing arm -->
				<p style="font-weight:bold;">
					<?php echo $lang['survey_76'] ?>&nbsp; <?php echo $surveyEventDropdown ?>
				</p>
			<?php } ?>

			<!-- Public survey URL -->
			<div style="padding:5px 0px 6px;">
				<div style="float:left;font-weight:bold;font-size:12px;line-height:1.8;"><?php echo $lang['survey_233'] ?></div>
				<?php $flashObjectName = 'longurl'; ?>
				<input id="<?php echo $flashObjectName ?>" value="<?php echo ($hash == '' ? '' : APP_PATH_SURVEY_FULL . "?s=$hash") ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="float:left;width:80%;max-width:400px;margin-bottom:5px;margin-right:5px;">
				<button class="btn btn-defaultrc btn-xs btn-clipboard" title="<?php print js_escape2($lang['global_137']) ?>" data-clipboard-target="#<?php echo $flashObjectName ?>" style="padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>				
			</div>
			<div class="clear"></div>
			
			<?php
			// If first form/first event are not designated, give warning
			if ($hash == '' && !in_array($Proj->surveys[$_GET['survey_id']]['form_name'], $Proj->eventsForms[$_GET['event_id']])) {
				print 	RCView::div(array('class'=>'red', 'style'=>'margin-top:10px;'),
							$lang['survey_1227']
						);
			}
			?>

			<!-- Custom URL -->
			<?php
			$this_custom_survey_url = $custom_survey_link_btn_disabled = '';
			if (trim($custom_public_survey_links) != '') 
			{
				$custom_links_array = json_decode($custom_public_survey_links, true);
				foreach ($custom_links_array as $attr) {
					if ($attr['arm_number'] == $arm) {
						$this_custom_survey_url = $attr['custom_url'];
						break;
					}
				}
			}
			if ($this_custom_survey_url != '') {
				$custom_survey_link_btn_disabled = 'disabled';
				?>
				<div class="customurl-container" style="padding:5px 0px 6px;">
					<div style="float:left;font-weight:bold;font-size:12px;line-height:1.8;"><?php echo $lang['control_center_4566'] ?></div>
					<?php $flashObjectName = 'customurl'; ?>
					<input id="<?php echo $flashObjectName ?>" value="<?php echo $this_custom_survey_url ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="float:left;width:80%;max-width:270px;margin-bottom:5px;margin-right:5px;">
					<button class="btn btn-defaultrc btn-xs btn-clipboard" title="<?php print js_escape2($lang['global_137']) ?>" data-clipboard-target="#<?php echo $flashObjectName ?>" style="padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>				
					<a href="javascript:;" onclick="simpleDialog('<?php print js_escape($lang['control_center_4567']) ?>',null,null,500,null,'<?php print js_escape($lang['global_53']) ?>',function(){ confirmDeleteCustomUrl(<?php print $arm; ?>); },'<?php print js_escape($lang['global_19']) ?>');" onmouseover="$(this).removeClass('opacity50');" onmouseout="$(this).addClass('opacity50');" class="opacity50 delete-btn" style="margin-left:10px;" data_pid="<?php echo $project_id ?>" data_armnum="<?php echo $arm ?>"><img class="delete-icon" src="<?php echo APP_PATH_IMAGES ?>cross.png" style="position:relative;top:2px;"></a>
				</div>
				<div class="clear"></div>
				<?php
			}
			?>
			

			<!-- Short URL -->
			<div id="shorturl_loading_div" style="font-size:12px;display:none;padding:10px 0px;font-weight:bold;">
				<img src="<?php echo APP_PATH_IMAGES ?>progress_circle.gif">&nbsp; <?php echo $lang['survey_79'] ?><br><br>
			</div>
			<div id="shorturl_div" style="display:none;font-size:12px;padding:10px 0 10px;">
				<div style="float:left;padding:0px 0px 4px 10px;color:#444;font-size:12px;line-height:1.8;"><?php echo $lang['global_46'] ?></div>
				<div style="float:left;font-weight:bold;font-size:12px;line-height:1.8;margin-left:5px;"><?php echo $lang['survey_234'] ?></div>
				<?php $flashObjectName = 'shorturl'; ?>
				<input id="<?php echo $flashObjectName ?>" value="" onclick="this.select();" readonly="readonly" class="staticInput" style="float:left;width:80%;max-width:230px;margin-bottom:5px;margin-right:5px;">
				<button class="btn btn-defaultrc btn-xs btn-clipboard" title="<?php print js_escape2($lang['global_137']) ?>" data-clipboard-target="#<?php echo $flashObjectName ?>" style="padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>				
			</div>
			<div class="clear"></div>

			<!-- Embed code for URL -->
			<div id='embed_div' style="font-size:12px;display:none;padding:0 0 5px;">
				<p><?php echo $lang['survey_240'] ?></p>
				<div>
					<div style="float:left;font-weight:bold;font-size:12px;line-height:1.8;"><?php echo $lang['survey_235'] ?> <span style="font-size:12px;">&lt; &gt;</span></div>
					<?php $flashObjectName = 'embedurl'; ?>
					<input id="<?php echo $flashObjectName ?>" value="<a href=&quot;<?php echo APP_PATH_SURVEY_FULL . "?s=$hash" ?>&quot;><?php echo $lang['survey_83'] ?></a>" onclick="this.select();" readonly="readonly" class="staticInput" style="float:left;width:80%;max-width:300px;margin-bottom:5px;margin-right:5px;">
					<button class="btn btn-defaultrc btn-xs btn-clipboard" title="<?php print js_escape2($lang['global_137']) ?>" data-clipboard-target="#<?php echo $flashObjectName ?>" style="padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>				
				</div>
			</div>
			<div class="clear"></div>

            <!-- Google reCaptcha -->
            <?php if ($google_recaptcha_site_key != '' && $google_recaptcha_secret_key != '') { ?>
            <div class="mt-3 p-2 px-4 float-left" style="min-width:550px;font-size:13px;border:#ddd 1px solid;background-color:#f5f5f5;">
                <input id="google_recaptcha_enabled" type="checkbox" <?php if ($google_recaptcha_enabled == '1') print "checked"; ?> style="position:relative;top:2px;" onclick="enableCaptcha($(this).prop('checked'));">
                <label for="google_recaptcha_enabled" class="mb-0"><?php echo $lang['survey_1252'] ?></label>
                <a href='javascript:;' class='help' style='font-size:10px;margin-left:5px;' onclick="simpleDialog(null,null,'captchaExplainPopup',650);">?</a>
            </div>
            <div class="mt-3 p-2 pl-1 float-left" id='captchaSavedMsg' style='display:none;font-weight:bold;text-align:center;font-size:13px;color:red;'><?php echo $lang['design_243'] ?></div>
            <div id="captchaExplainPopup" class="simpleDialog" title="<?php echo js_escape2($lang['survey_1252']) ?>">
                <?php echo $lang['survey_1253'] ?><br><br>
                <img src="<?php echo APP_PATH_IMAGES ?>google_recaptcha.png" style="width:550px;">
            </div>
            <?php } ?>

			<!-- Buttons to open or email survey -->
			<div class="link-actions-container">
				<h5 class='link-actions'><?php print $lang['control_center_4561'] ?></h5>
				<button class="jqbuttonmed" onclick="surveyOpen($('#longurl').val(),0);"
					><i class="fas fa-share"></i> <span style="margin-left:1px;vertical-align:middle;"> <?php echo $lang['survey_236'] ?></span></button>
				&nbsp;
				<button class="jqbuttonmed" onclick="surveyOpen($('#longurl').val(),0);window.location.href=window.location.href+'&logout=1';"
					><i class="fas fa-share"></i> <span style="margin-left:1px;vertical-align:middle;"> <?php echo $lang['survey_236'] ?></span><span style="margin:0 5px 0 4px;vertical-align:middle;">+</span><span class="fas fa-sign-out-alt" style="vertical-align:middle;"></span> <span style="vertical-align:middle;"><?php echo $lang['bottom_02'] ?></span></button>
				&nbsp;
				<button class="jqbuttonmed" onclick="sendSelfEmail(<?php echo $_GET['survey_id'] ?>,$('#longurl').val());"
					><i class="fas fa-envelope"></i> <span style="margin-left:2px;vertical-align:middle;"> <?php echo $lang['survey_237'] ?></span></button>
				&nbsp;
				<?php if (gd2_enabled()) { ?>
					<button class="jqbuttonmed" onclick="getAccessCode('<?php echo $hash ?>');"
						><img src="<?php echo APP_PATH_IMAGES ?>ticket_arrow.png" style="vertical-align:middle;position:relative;top:-1px;">
						<span style="margin-left:1px;vertical-align:middle;"> <?php echo $lang['survey_621'] ?></span>
						<img src="<?php echo APP_PATH_IMAGES ?>qrcode.png" style="margin-left:2px;vertical-align:middle;position:relative;top:-1px;">
						<span style="vertical-align:middle;"> <?php echo $lang['survey_664'] ?></span></button>
				<?php } else { ?>
					<button class="jqbuttonmed" style="margin-top:10px;" onclick="getAccessCode('<?php echo $hash ?>');"
						><img src="<?php echo APP_PATH_IMAGES ?>ticket_arrow.png" style="vertical-align:middle;"><span style="margin-left:2px;vertical-align:middle;"> <?php echo $lang['survey_629'] ?></span></button>
				<?php } ?>
			</div>
			<div class='url-actions-container'>
				<h5 class='link-actions'><?php print $lang['control_center_4560'] ?></h5>
				<?php if ($enable_url_shortener) { ?>
				<button <?php if ($hash == '') print "disabled"; ?> class="jqbuttonmed url-actions-btn short-survey-link-btn" onclick="getShortUrl('<?php echo $hash ?>', <?php echo $_GET['survey_id'] ?>)"><img src="<?php echo APP_PATH_IMAGES ?>icon_link.gif" style="vertical-align:middle;"><span style="vertical-align:middle;"> <?php echo $lang['control_center_4564'] ?></span></button>
				<button <?php if ($hash == '') print "disabled"; ?> <?php print $custom_survey_link_btn_disabled ?> class="jqbuttonmed url-actions-btn custom-survey-link-btn" onclick="customizeShortUrl('<?php echo $hash ?>', <?php echo $_GET['survey_id'] ?>, <?php echo $_GET['arm_id'] ?>)"><img src="<?php echo APP_PATH_IMAGES ?>insert-link.png" style="vertical-align:middle;"><span style="margin-left:2px;vertical-align:middle;"> <?php echo $lang['control_center_4563'] ?></span></button>
				<?php } ?>
				<button <?php if ($hash == '') print "disabled"; ?> class="jqbuttonmed url-actions-btn embed-survey-btn" onclick="if($('#embed_div').css('display') == 'none'){ $('#embed_div').show('fade','fast'); } $('#embed_div').effect('highlight', 'slow');"><img src="<?php echo APP_PATH_IMAGES ?>code.png" style="vertical-align:middle;"><span style="margin-left:2px;vertical-align:middle;"> <?php echo $lang['control_center_4562'] ?></span></button>
			</div>
			<div id="custom_url_dialog" title="<?php print js_escape2($lang['control_center_4563']) ?>" class="simpleDialog">
				<div><?php print $lang['control_center_4565'] ?></div>
				<div class="input-group clearfix" style="margin-top:15px;">
					<span class="input-group-addon float-left" style="margin-top:5px;font-size:16px;font-weight:bold;letter-spacing: 1px;">
                        <?php echo $GLOBALS['enable_url_shortener_redcap'] ? "https://redcap.link/" : "https://is.gd/" ?>
                    </span>
					<input class="form-control customurl-input float-left" style="max-width:200px;margin-left:8px;font-size:15px;letter-spacing: 1px;" type="text">
				</div>
                <div class="mt-3 text-secondary"><?php print $lang['global_03'].$lang['colon']." ".$lang['survey_1272'] ?></div>
			</div>
			<div class="clear"></div>

            <!-- Google reCaptcha (Super User reminder to enable) -->
            <?php if (SUPER_USER && ($google_recaptcha_site_key == '' || $google_recaptcha_secret_key == '')) { ?>
            <div class="mt-3 alert alert-success" style="font-size:13px;border-color: #c3e6cb !important;">
                <i class="fas fa-info-circle"></i> <?php echo $lang['survey_1250'] ?>
                <a target="_blank" style="text-decoration: underline;" href="<?php echo APP_PATH_WEBROOT ?>ControlCenter/modules_settings.php"><?php echo $lang['control_center_4604'] ?></a> <?php echo $lang['survey_1251'] ?>
            </div>
            <?php } ?>

			<?php
			if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
				$twilio_option_num = 1;
				$access_code_numeral = Survey::getAccessCode(Survey::getParticipantIdFromHash($hash), false, false, true);
				?>
				<!-- Buttons to take survey by voice call or SMS -->
				<fieldset style="margin:25px 0 20px 5px;padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;">
					<legend style="font-size:13px;font-weight:bold;color:#333;">
						<img src="<?php echo APP_PATH_IMAGES ?>phone.gif">
						<?php echo $lang['survey_823'] ?>
					</legend>
					<div style="padding:15px 15px 15px 10px;font-size:12px;line-height: 1.4em;">
						<div><?php echo $lang['survey_816'] ?></div>
						<div style="margin:12px 0 20px 20px;font-weight:bold;">
							<?php echo $twilio_option_num++ . ") " . $lang['survey_825'] ?>
							<button class="jqbuttonmed" style="margin-left:7px;color:#800000;" onclick="initCallSMS('<?php echo $hash ?>');"><?php echo $lang['survey_815'] ?></button>
						</div>
						<div><?php echo $lang['survey_821'] ?></div>
						<?php if ($twilio_option_voice_initiate) { ?>
							<div style="margin:15px 0 4px 20px;font-weight:bold;">
								<?php echo $twilio_option_num++ . ") " . $lang['survey_881'] ?>
							</div>
							<div style="margin-left:34px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_817'] ?></span>
								<input value="<?php echo formatPhone($twilio_from_number) ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:120px;margin:0 2px 0 3px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_820'] ?></span>
								<input value="<?php echo $access_code_numeral ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:90px;margin:0 2px 0 3px;">
							</div>
						<?php } ?>
						<?php if ($twilio_option_sms_invite_receive_call) { ?>
							<div style="margin:15px 0 4px 20px;font-weight:bold;">
								<?php echo $twilio_option_num++ . ") " . $lang['survey_882'] ?>
							</div>
							<div style="margin-left:34px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_818'] ?></span>
								<input value="<?php echo Survey::PREPEND_ACCESS_CODE_NUMERAL . $access_code_numeral ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:97px;margin:0 2px 0 3px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_819'] ?></span>
								<input value="<?php echo formatPhone($twilio_from_number) ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:120px;margin:0 2px 0 3px;">
							</div>
						<?php } ?>
						<?php if ($twilio_option_sms_initiate) { ?>
							<div style="margin:15px 0 4px 20px;font-weight:bold;">
								<?php echo $twilio_option_num++ . ") " . $lang['survey_883'] ?>
							</div>
							<div style="margin-left:34px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_818'] ?></span>
								<input value="<?php echo $access_code_numeral ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:90px;margin:0 2px 0 3px;">
								<span style="vertical-align:middle;font-size:12px;"><?php echo $lang['survey_819'] ?></span>
								<input value="<?php echo formatPhone($twilio_from_number) ?>" onclick="this.select();" readonly="readonly" class="staticInput" style="width:120px;margin:0 2px 0 3px;">
							</div>
						<?php } ?>
					</div>
				</fieldset>
				<?php
			}

			## AUTOMATED INVITES CHECK
			// If auto invites are enabled for the first event-first instrument but the email field has not been designated yet,
			// then give a warning.
			// Check if ASIs are enabled for the first event-first instrument
			$sql = "select 1 from redcap_surveys_scheduler where condition_surveycomplete_survey_id = {$Proj->firstFormSurveyId}
					and condition_surveycomplete_event_id = {$Proj->firstEventId} and active = 1 limit 1";
			$q = db_query($sql);
			if (db_num_rows($q)) {
				// Yes, they are enabled
				if ($survey_email_participant_field == '') {
					// Email field is not designated. Tell user to designate one.
					print 	RCView::div(array('class'=>'red','style'=>'padding:10px;margin-top:15px;'),
								RCView::div(array('style'=>'font-weight:bold;'),
									RCView::img(array('src'=>'exclamation.png')) .
									$lang['global_48'].$lang['colon']." ".$lang['survey_481']
								) .
								$lang['survey_480'] . RCView::br() . RCView::br() .
								$lang['survey_482']
							);
				} elseif ($survey_email_participant_field != '' && !isset($Proj->forms[$Proj->firstForm]['fields'][$survey_email_participant_field])) {
					// Email field is designated but does not exist on first instrument (problematic)
					print 	RCView::div(array('class'=>'red','style'=>'padding:10px;margin-top:15px;'),
								RCView::div(array('style'=>'font-weight:bold;'),
									RCView::img(array('src'=>'exclamation.png')) .
									$lang['global_48'].$lang['colon']." ".$lang['survey_483']
								) .
								$lang['survey_484'] . ' ' .
								RCView::b($survey_email_participant_field) . ' ("' . $Proj->metadata[$survey_email_participant_field]['element_label'] . '")' .
								$lang['period'] . RCView::br() . RCView::br() .
								$lang['survey_482']
							);
				}
			}
		}
		?>

	</div>
	<?php
}








## PARTICIPANT LIST
elseif (isset($_GET['participant_list']))
{
	?>

	<!-- Participant List Section -->
	<div class="p" style="margin:0 0 20px;">
		<div>
			<?php echo $lang['survey_355'] . " " . ($isFollowUpSurvey ? "" : $lang['survey_1086']) ?>
			<a href="javascript:;" onclick="$(this).hide();$('#partListInstrMore').show('fade');" style="text-decoration:underline;"><?php echo $lang['survey_86'] ?></a>
		</div>
		<div id="partListInstrMore" class="p" style="display:none;">
			<?php echo $lang['survey_87'] ?>
			<b><?php echo $lang['survey_88'] ?></b> <img src="<?php echo APP_PATH_IMAGES ?>circle_green_tick.png"> <?php echo $lang['global_47'] ?>
			<b><?php echo $lang['survey_89'] ?></b> <img src="<?php echo APP_PATH_IMAGES ?>circle_orange_tick.png"><?php echo $lang['survey_91'] ?>
			<b><?php echo $lang['survey_90'] ?></b> <img src="<?php echo APP_PATH_IMAGES ?>stop_gray.png"><?php echo $lang['period'] ?>
			<u><?php echo $lang['survey_92'] ?></u>
			<?php echo $lang['survey_93'] ?>
		</div>
	</div>

	<!-- Participant List -->
	<div id="partlist_outerdiv" style="margin-bottom:20px;">
		<?php
		// Build Participant List
		include APP_PATH_DOCROOT . 'Surveys/participant_list.php';
		?>
	</div>

	<!-- Hidden "Add Participants" dialog -->
	<div id="emailAdd" title="<?php echo js_escape2($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_774'] : $lang['survey_770']) ?>" style="display:none;">
		<p>
			<?php echo ($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_775'] : $lang['survey_267']) ?>
			<span class="partIdentInstrText"><?php echo $lang['survey_268'] ?></span>
			<?php if ($multiple_arms) { ?>
				<br><br><?php echo $lang['survey_97'] ?> <b><?php echo $armNameFull ?></b>.
			<?php } ?>
		</p>
		<div style="">
			<textarea id="newPart" class="x-form-field notesbox" style="width:500px;height:130px;"></textarea>
		</div>
		<?php
		if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
			// Set the participant's preference for delivery method
			print 	RCView::div(array('style'=>'border:1px solid #ccc;background-color:#f5f5f5;padding:5px 5px 7px;margin-bottom:15px;width:496px;'),
						RCView::div(array('style'=>''),
							RCView::img(array('src'=>'arrow_right_curve.png')) .
							$lang['survey_778']
						) .
						RCView::div(array('style'=>'margin:3px 0 0 20px;'),
							RCView::select(array('id'=>'delivery_preference', 'class'=>'x-form-text x-form-field', 'style'=>''),
								Survey::getDeliveryMethods(false, true), $twilio_default_delivery_preference) .
							RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;',
								'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
						)
					);
		}
		?>
		<div style="color:#111111;margin:5px 0 0;line-height:15px;">
			<div style="color:#C00000;margin-bottom:10px;"><?php echo $lang['survey_771'] ?></div>
			<div class="partIdentInstrText">
				<b><?php echo $lang['survey_98'] ?></b>&nbsp;
				<?php echo ($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_772'] : $lang['survey_99']) ?>
			</div>
			<div style="border-top:1px solid #bbb;padding-top:10px;margin-top:25px;font-size:12px;color:#666;">
				<?php if ($twilio_enabled && $Proj->twilio_enabled_surveys) { ?>
					<b><?php echo $lang['survey_101'] ?> #1:</b>&nbsp; john.williams@hotmail.com, 615-123-4567<br>
					<b><?php echo $lang['survey_101'] ?> #2:</b>&nbsp; (270) 398-1111<span class="partIdentInstrText">, Jim Taylor</span><br>
					<b><?php echo $lang['survey_101'] ?> #3:</b>&nbsp; putnamtr@gmail.com, 365 908 7283<span class="partIdentInstrText">, ID 4930-72</span><br>
					<b><?php echo $lang['survey_101'] ?> #4:</b>&nbsp; +16158877747<br>
					<div style="margin-top:10px;font-size:11px;line-height:12px;"><?php echo $lang['survey_907'] ?></div>
				<?php } else { ?>
					<b><?php echo $lang['survey_101'] ?> #1:</b>&nbsp; john.williams@hotmail.com<br>
					<b><?php echo $lang['survey_101'] ?> #2:</b>&nbsp; jimtaylor@yahoo.com<span class="partIdentInstrText">, Jim Taylor</span><br>
					<b><?php echo $lang['survey_101'] ?> #3:</b>&nbsp; putnamtr@gmail.com<span class="partIdentInstrText">, ID 4930-72</span><br>
				<?php } ?>
			</div>
		</div>
	</div>

	<!-- Hidden pop-up div to display warning message about trying to click identifiers when they are disabled -->
	<div id='tooltipIdentDisabled' class='tooltip1' style='max-width:300px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['global_23']}{$lang['colon']}</b><br>" . ($status < 1 ? $lang['survey_262'] : $lang['survey_263']) ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email/identifier -->
	<div id='tooltipEdit' class='tooltip1' style='max-width:300px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_264']}</b><br>{$lang['survey_859']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email/identifier -->
	<div id='tooltipNoEditIdentFollowup' class='tooltip1' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_498']}</b><br>{$lang['survey_501']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email/identifier -->
	<div id='tooltipNoEditEmailFollowup' class='tooltip1' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_502']}</b><br>{$lang['survey_503']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing phone -->
	<div id='tooltipNoEditPhoneFollowup' class='tooltip1' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_857']}</b><br>{$lang['survey_858']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing email for response from Public Survey -->
	<div id='tooltipNoEditEmailPublic' class='tooltip1' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_502']}</b><br>{$lang['survey_854']}" ?>
	</div>

	<!-- Hidden pop-up div to display warning message about editing phone number for response from Public Survey -->
	<div id='tooltipNoPhoneEmailPublic' class='tooltip1' style='max-width:350px;padding:3px 6px;z-index:9999;'>
		<?php echo "<b>{$lang['survey_806']}</b><br>{$lang['survey_807']}" ?>
	</div>

	<!-- Hidden pop-up div to enable/disable Participant Identifiers -->
	<div id='popupEnablePartIdent' style='display:none;'></div>
	<?php
}
?>



<!-- Hidden pop-up div to display warning message about clicking responded icon -->
<div id='tooltipViewResp' class='tooltip1' style='max-width:300px;padding:3px 6px;z-index:9999;'>
	<?php echo "<b>{$lang['survey_244']}</b><br>{$lang['survey_243']}" ?>
</div>



<script type="text/javascript">
// Copy-to-clipboard action
var clipboard = new Clipboard('.btn-clipboard');
// Language vars
var langSave = '<?php echo js_escape($lang['designate_forms_13']) ?>';
var langCancel = '<?php echo js_escape($lang['global_53']) ?>';
var langLinkExpire1 = '<?php echo js_escape($lang['survey_1121']) ?>';
// Note the first form's survey_id (referenced in order to disable editing email/identifier for followup surveys)
var firstFormSurveyId = <?php echo is_numeric($Proj->firstFormSurveyId) ? $Proj->firstFormSurveyId : "''" ?>;
var firstEventId = <?php echo $Proj->firstEventId ?>;
var isFollowUpSurvey = <?php print ($isFollowUpSurvey ? 'true' : 'false'); ?>;
var survey_id = <?php echo $_GET['survey_id'] ?>;
var event_id = <?php echo $_GET['event_id'] ?>;

$(function(){
	// Copy-to-clipboard action
	$('.btn-clipboard').click(function(){
		copyUrlToClipboard(this);
	});
	// If "Add Participants" button is disabled but user tried to click it, then provide user with message why they cannot add participants
	$('#addPartsBtnSpan').click(function(){
		if ($('#addPartsBtn').prop('disabled')) {
			simpleDialog('<?php echo js_escape($lang['survey_389']) ?>','<?php echo js_escape($lang['survey_388']) ?>');
		}
	});
});

// Delete ALL participants from list
function deleteParticipants(survey_id,event_id) {
	simpleDialog('<?php echo js_escape($lang['survey_795']) ?>','<?php echo js_escape($lang['survey_358']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',
		"deleteParticipants2("+survey_id+","+event_id+");",'<?php echo js_escape($lang['survey_368']) ?>');
}
function deleteParticipants2(survey_id,event_id) {
	simpleDialog('<?php echo js_escape($lang['survey_796']) ?>','<?php echo js_escape($lang['survey_369']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',
		"deleteParticipantsDo("+survey_id+","+event_id+");",'<?php echo js_escape($lang['survey_368']) ?>');
}
function deleteParticipantsDo(survey_id,event_id) {
	$.post(app_path_webroot+'Surveys/delete_participants.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { }, function(data){
		if (data == '1') {
			loadPartList(survey_id,event_id,1,'<?php echo js_escape($lang['survey_798']) ?>','<?php echo js_escape($lang['survey_797']) ?>');
		} else {
			alert(woops);
		}
	});
}

// Delete a participant from list
function deleteParticipant(survey_id,event_id,part_id) {
	simpleDialog('<?php echo js_escape($lang['survey_361']) ?>','<?php echo js_escape($lang['survey_360']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',
		"deleteParticipantDo("+survey_id+","+event_id+","+part_id+");",'<?php echo js_escape($lang['scheduling_57']) ?>');
}
function deleteParticipantDo(survey_id,event_id,part_id) {
	$.post(app_path_webroot+'Surveys/delete_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { participant_id: part_id }, function(data){
		if (data == '1') {
			var pagenum = $('#pageNumSelect').val();
			if (!isNumeric(pagenum)) pagenum = 1;
			loadPartList(survey_id,event_id,pagenum,'<?php echo js_escape($lang['survey_363']) ?>','<?php echo js_escape($lang['survey_362']) ?>');
		} else {
			alert(woops);
		}
	});
}

// Dialog for adding new participants
function addPart(survey_id,event_id) {
	$('#emailAdd').dialog({ bgiframe: true, modal: true, width: 550, buttons: {
		'<?php echo js_escape($lang['global_53']) ?>': function() { $('#newPart').val(''); $(this).dialog('close'); },
		'<?php echo js_escape($lang['survey_230']) ?>': function () {
			$('#newPart').val( trim($('#newPart').val()) );
			if ($('#newPart').val().length < 1) {
				simpleDialog('<?php echo js_escape($lang['survey_776'] . ($twilio_enabled && $Proj->twilio_enabled_surveys ? " ".$lang['survey_777'] : "")) ?>');
				return;
			}
			showProgress(1);
			$.post(app_path_webroot+'Surveys/add_participants.php?pid='+pid+'&event_id='+event_id+'&survey_id='+survey_id, { delivery_preference: $('#delivery_preference').val(), participants: $('#newPart').val() }, function(data){
				showProgress(0);
				if (data == '1') {
					$('#newPart').val('');
					$('#emailAdd').dialog('destroy');
					loadPartList(survey_id,event_id,1,'<?php echo js_escape($lang['survey_855']) ?>','<?php echo js_escape($lang['survey_856']) ?>');
				} else if (data == '0') {
					alert(woops);
				} else {
					simpleDialog(data);
				}
			});
		}
	} });
}


// Set onclick event for each checkbox to count num checkboxes checked
function plsetcount() {
	$('#plist_selected').html( $('#table-participant_table_email input[type="checkbox"]:checked').length );
};

// Open email-sending dialog
function sendEmails(survey_id,event_id) {
	$('#emailSendList_div').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; <?php echo js_escape($lang['survey_287']) ?>');
	// Open dialog
	$('#emailPart').dialog({ bgiframe: true, modal: true, width: 1270, open: function(){ fitDialog(this); }, buttons: {
		'<?php echo js_escape($lang['global_53']) ?>': function() { $(this).dialog('close'); },
		'<?php echo js_escape($lang['survey_792']) ?>': function() {
			// Trim email subject/message
			$('#emailTitle').val( trim($('#emailTitle').val()) );
			$('#emailCont').val( trim($('#emailCont').val()) );
			// If set exact time in future to send surveys, make sure time doesn't exist in the past
			var now_mdyhm = '<?php echo date('m-d-Y H:i') ?>';
			var now_ymdhm = '<?php echo date('YmdHi') ?>';
			var eTs = $('form#emailPartForm #emailSendTimeTS').val();
			if (user_date_format_validation == 'mdy') {
				var emailSendTimeTs_ymdhm = eTs.substr(6,4)+eTs.substr(0,2)+eTs.substr(3,2)+eTs.substr(11,2)+eTs.substr(14,2);
			} else if (user_date_format_validation == 'dmy') {
				var emailSendTimeTs_ymdhm = eTs.substr(6,4)+eTs.substr(3,2)+eTs.substr(0,2)+eTs.substr(11,2)+eTs.substr(14,2);
			} else {
				var emailSendTimeTs_ymdhm = eTs.substr(0,4)+eTs.substr(5,2)+eTs.substr(8,2)+eTs.substr(11,2)+eTs.substr(14,2);
			}
			if ($('form#emailPartForm #emailSendTimeTS').length && $('form#emailPartForm input[name="emailSendTime"]:checked').val() == 'EXACT_TIME') {
				if ($('form#emailPartForm #emailSendTimeTS').val().length < 1) {
					simpleDialog('<?php echo js_escape($lang['survey_325']) ?>',null,null,null,"$('form#emailPartForm #emailSendTimeTS').focus();");
					return;
				} else if (!redcap_validate(document.getElementById('emailSendTimeTS'),'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)) {
					return;
				} else if (emailSendTimeTs_ymdhm < now_ymdhm) {
					simpleDialog('<?php echo js_escape($lang['survey_326']." <b>".DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true)."</b>".$lang['period']) ?>','<?php echo js_escape($lang['survey_327']) ?>');
					return;
				}
			}

			// Check reminder options
			if (!validateSurveyRemindersOptions()) return;
			// If reminder option is unchecked, then uncheck the reminder option radio that is hidden
			if (!$('#enable_reminders_chk').prop('checked')) {
				$('#reminders_choices_div input[name="reminder_type"]:checked').prop('checked', false);
			}

			// Determine if Voice/SMS options are enabled
			var voiceSmsEnabled = ($('form#emailPartForm select[name="delivery_type"]').length);
			var delivery_type = (voiceSmsEnabled) ? $('form#emailPartForm select[name="delivery_type"]').val() : '';
			var email_delivery = (delivery_type == 'EMAIL');
			var use_part_pref = (delivery_type == 'PARTICIPANT_PREF');
			var this_email_delivery;

			// Gather participant_ids
			var invalidInviteType = 0;
			var participants = new Array();
			var i = 0;
			$('input.chk_part:checked').each(function(){
				// Make sure we can successfully send the invitations in their desired delivery method
				if (voiceSmsEnabled) {
					if (use_part_pref) {
						// Send using participant's preference
						this_email_delivery = ($(this).attr('partpref') == 'EMAIL');
						if ((this_email_delivery && $(this).attr('hasemail') != '1') || (!this_email_delivery && $(this).attr('hasphone') != '1')) {
							invalidInviteType++;
						}
					} else {
						// Send using the manually set invitation type
						if ((email_delivery && $(this).attr('hasemail') != '1') || (!email_delivery && $(this).attr('hasphone') != '1')) {
							invalidInviteType++;
						}
					}
				}
				// Add participant to array
				participants[i] = $(this).prop('id').substring(8);
				i++;
			});
			// If there are invitation types that are invalid and won't send because of missing email or phone, then display error
			if (invalidInviteType > 0) {
				simpleDialog('<?php echo js_escape($lang['survey_827']) ?> '+invalidInviteType+' <?php echo js_escape($lang['survey_828']) ?>',
					'<?php echo js_escape($lang['survey_826']) ?>', null, 550, null,
					'<?php echo js_escape($lang['global_53']) ?>', function(){
						$('input.chk_part:checked').each(function(){
							if (use_part_pref) {
								// Send using participant's preference
								this_email_delivery = ($(this).attr('partpref') == 'EMAIL');
								if ((this_email_delivery && $(this).attr('hasemail') != '1') || (!this_email_delivery && $(this).attr('hasphone') != '1')) {
									$(this).prop('checked', false);
								}
							} else {
								// Send using the manually set invitation type
								if ((email_delivery && $(this).attr('hasemail') != '1') || (!email_delivery && $(this).attr('hasphone') != '1')) {
									$(this).prop('checked', false);
								}
							}
						});
						// Click the Send Invitations button
						$('#emailPart').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).trigger('click');
					}, '<?php echo js_escape($lang['survey_829']) ?>');
				return;
			}
			// Give error message if no participants are selected
			if (participants.length < 1) {
				simpleDialog('<?php echo js_escape($lang['survey_286']) ?>');
				return;
			}
			// Set all checked participant_id's as a single input field
			$('#emailPartForm').append('<input type="hidden" name="participants" value="'+participants.join(",")+'">');
			// Give confirmation message if any participants are about to have their invitations rescheduled
			var numScheduled = $('input.sched:checked').length;
			if (numScheduled > 0) {
				// Display pop-up with confirmation about rescheduling invites
				$('#reschedule-reminder-dialog-resched-count').html(numScheduled);
				$('#reschedule-reminder-dialog').dialog({ bgiframe: true, modal: true, width: 500, buttons: {
					'<?php echo js_escape($lang['global_53']) ?>': function() { $(this).dialog('close'); },
					'<?php echo js_escape($lang['survey_285']) ?>': function () {
						// Submit the form
						sendEmailsSubmit(survey_id,event_id);
					}
				}});
				return;
			}
			// Submit the form
			sendEmailsSubmit(survey_id,event_id);
		}
	} });
    // Set rich text editor
    var select = $('select[name="delivery_type"]');
    var suggestLink = (!select.length || select.val() == 'EMAIL' || select.val() == 'SMS_INVITE_WEB');
    if (isIE && vIE() < 11) {
        var initComposeText = "<?php echo js_escape2($lang['survey_1224']) ?>\n\n<?php echo js_escape2($lang['survey_134']) ?>\n[survey-link]\n\n<?php echo js_escape2($lang['survey_135']) ?>\n[survey-url]\n\n<?php echo js_escape2($lang['survey_137']) ?>";
    } else {
        var initComposeText = "<p><?php echo js_escape2($lang['survey_1224']) ?></p><p><?php echo js_escape2($lang['survey_134']) ?><br/>[survey-link]</p><p><?php echo js_escape2($lang['survey_135']) ?><br/>[survey-url]</p><p><?php echo js_escape2($lang['survey_137']) ?></p>";
    }
    if (suggestLink) $('#emailCont').val(initComposeText);
    if (REDCap.isTinyMCESupported()) {
        initTinyMCEglobal('mceEditor');
        try{
            tinymce.get('emailCont').setContent($('#emailCont').val());
        }catch (e) { }
    }

	// After opening "compose email" dialog, load participant list via ajax
	$.get(app_path_webroot+'Surveys/participant_list.php?emailformat=1&survey_id='+survey_id+'&event_id='+event_id+'&pid='+pid, { }, function(data){
		$('#emailSendList_div').html(data);
		// Disable onclick attribute for the "check all" checkbox in table header
		$('#emailSendList_div div#participant_table_email div.hDiv table th:first').attr('onclick', '');
	});
}
</script>

<style type="text/css">
.edit_active { background: #fafafa url(<?php echo APP_PATH_IMAGES ?>pencil.png) no-repeat right; }
.edit_saved  { background: #C1FFC1 url(<?php echo APP_PATH_IMAGES ?>tick.png) no-repeat right; }
</style>


<!-- Reschedule Participants Dialog -->
<div id="reschedule-reminder-dialog" title='<?php echo js_escape($lang['survey_343']) ?>' class="simpleDialog">
	<?php echo $lang['survey_344'] ?>
	<span id="reschedule-reminder-dialog-resched-count">0</span>
	<?php echo $lang['survey_345'] ?>
</div>

<!-- Popup for changing a participant's Twilio invitation preference -->
<?php
print 	RCView::div(array('id'=>'invPrefPopup', 'style'=>'display:none;'),
			RCView::div(array('id'=>'invPrefPopupSub'),
				RCView::img(array('src'=>'arrow_right_curve.png')) .
				RCView::b($lang['survey_860']) .
				RCView::div(array('style'=>'margin:6px 0 8px;'),
					RCView::hidden(array('id'=>'partInvPrefPartId')) .
					RCView::hidden(array('id'=>'partInvPrefRecord')) .
					RCView::select(array('id'=>'partInvPref', 'class'=>'x-form-text x-form-field', 'style'=>'', 'onchange'=>"setInviteDeliveryMethod(this)"),
						Survey::getDeliveryMethods(false, true), 'EMAIL')
				) .
				RCView::div(array('style'=>'max-width:320px;margin:5px 0 8px;font-size:11px;line-height:12px;color:#666;'),
					$lang['survey_940']
				) .
				RCView::div(array('style'=>'text-align:right;margin-right:10px;'),
					RCView::span(array('id'=>'partInvPrefSaved', 'style'=>'font-weight:bold;color:#C00000'),
						$lang['design_243']
					) .
					RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#800000;margin:0 10px 0 20px;', 'onclick'=>"changeInvPref({$_GET['survey_id']},{$_GET['event_id']});"), $lang['designate_forms_13']) .
					RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;text-decoration:underline;',
						'onclick'=>"enableEditInvPref($('#editinvpref_'+$('#partInvPrefPartId').val()));$('#invPrefPopup').hide();"), $lang['global_53'])
				)
			)
		);
?>


<!-- Email Participants Dialog -->
<?php if (isset($_GET['participant_list'])) { ?>
<div id="emailPart" title='<?php echo js_escape('<i class="fas fa-envelope"></i> ' . $lang['survey_791']) ?>' style="display:none;">
	<form id="emailPartForm">
	<table cellspacing=0 border=0 style="table-layout:fixed;"><tr>
		<td valign="top" align="left" style="width:650px;padding:2px 10px 0 0;">
			<?php
			// Check response limit (if enabled)
			if ($Proj->surveys[$_GET['survey_id']]['response_limit'] > 0 && Survey::reachedResponseLimit($project_id, $_GET['survey_id'], $_GET['event_id']))
			{
				print 	RCView::div(array('class'=>'red', 'style'=>'margin-bottom:10px;'),
							$lang['survey_1113']
						);
			}
			?>
			<fieldset style="padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;margin-bottom:10px;">
				<legend style="font-weight:bold;color:#333;">
                    <i class="fas fa-info-circle"></i>
					<?php echo $lang['survey_340'] ?>
				</legend>
				<?php
				print 	RCView::div(array('style'=>'padding:3px 8px 8px 2px;'),
							// Survey title
							RCView::div(array('style'=>'color:#800000;'),
								RCView::b($lang['survey_310']) .
								RCView::span(array('style'=>'font-size:13px;margin-left:8px;'),
									// If survey title is blank (because using a logo instead), then insert the instrument name
									RCView::escape($Proj->surveys[$_GET['survey_id']]['title'] == ""
										? $Proj->forms[$Proj->surveys[$_GET['survey_id']]['form_name']]['menu']
										: $Proj->surveys[$_GET['survey_id']]['title']
									)
								)
							) .
							// Event name (if longitudinal)
							RCView::div(array('style'=>'color:#000066;padding-top:3px;' . ($longitudinal ? '' : 'display:none;')),
								RCView::b($lang['bottom_23']) .
								RCView::span(array('style'=>'font-size:13px;margin-left:8px;'),
									RCView::escape($Proj->eventInfo[$_GET['event_id']]['name_ext'])
								)
							)
						);
				?>
			</fieldset>

			<?php
			// If Twilio is enabled, give option to send as SMS or VOICE
			if ($twilio_enabled && $Proj->twilio_enabled_surveys) {
				print 	RCView::fieldset(array('style'=>'padding:0 0 2px 8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom:10px;'),
							RCView::legend(array('style'=>'color:#333;'),
								'<i class="fas fa-share"></i> ' .
								RCView::b($lang['survey_687']). " " . $lang['survey_691']
							) .
							RCView::div(array('class'=>'nowrap', 'style'=>'padding:6px 2px 6px 2px;'),
								RCView::select(array('name'=>'delivery_type', 'class'=>'x-form-text x-form-field', 'style'=>'', 'onchange'=>"setInviteDeliveryMethod(this);$('#emailCont').trigger('blur');"),
									Survey::getDeliveryMethods(true, true), 'PARTICIPANT_PREF') .
								RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 12px;',
									'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
							)
						);
			}
			?>

			<fieldset style="padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom:10px;">
				<legend style="font-weight:bold;color:#333;">
                    <i class="fas fa-clock"></i>
					<?php echo $lang['survey_322'] ?>
				</legend>
				<?php
				print 	RCView::div(array('style'=>'padding:5px 8px 7px 2px;'),
							RCView::radio(array('name'=>'emailSendTime','value'=>'IMMEDIATELY','style'=>'','checked'=>'checked')) .
							$lang['survey_323'] . RCView::br() .
							RCView::radio(array('name'=>'emailSendTime','value'=>'EXACT_TIME','style'=>'','onclick'=>"if ($('#emailSendTimeTS').val().length<1) $('#emailSendTimeTS').focus();")) .
							$lang['survey_324'] .
							RCView::input(array('name'=>'emailSendTimeTS', 'id'=>'emailSendTimeTS', 'type'=>'text', 'class'=>'x-form-text x-form-field',
								'style'=>'width:102px;font-size:11px;margin-left:7px;padding-bottom:1px;','onkeydown'=>"if(event.keyCode==13){return false;}",
								'onfocus'=>"$('form#emailPartForm input[name=\"emailSendTime\"][value=\"EXACT_TIME\"]').prop('checked',true); this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}",
								'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
							RCView::span(array('class'=>'df','style'=>'padding-left:5px;'), DateTimeRC::get_user_format_label().' H:M') .
							// Get current time zone, if possible
							RCView::div(array('style'=>'margin:4px 0 0 22px;font-size:10px;line-height:10px;color:#777;'),
								"{$lang['survey_296']} <b>".getTimeZone()."</b>{$lang['survey_297']} <b>" .
								DateTimeRC::format_user_datetime(NOW, 'Y-M-D_24', null, true) . "</b>{$lang['period']}"
							)
						);
				?>
			</fieldset>

			<?php
			## REMINDERS
			print 	RCView::fieldset(array('style'=>'padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;margin-bottom: 10px;'),
						RCView::legend(array('style'=>'font-weight:bold;color:#333;'),
							'<i class="fas fa-bell"></i> ' .
							$lang['survey_733']
						) .
						RCView::div(array('style'=>'padding:5px 0 10px 2px;'),
							// Instructions
							RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;padding:3px 5px 3px 0;color:#444;'),
								RCView::checkbox(array('id'=>"enable_reminders_chk", 'style'=>'margin-right:3px;')) .
								$lang['survey_734'] .
								RCView::span(array('id'=>'reminders_text1'), $lang['survey_749'])
							) .
							## When to send once condition is met
							RCView::div(array('id'=>"reminders_choices_div", 'style'=>'margin-left:20px;display:none;'),
								// Next occurrence of (e.g., Work day at 11:00am)
								RCView::div(array('style'=>'padding:4px 0 1px;'),
									RCView::radio(array('name'=>"reminder_type",'value'=>'NEXT_OCCURRENCE')) .
									$lang['survey_735'] . RCView::SP . RCView::SP .
									RCView::select(array('name'=>"reminder_nextday_type",'style'=>'font-size:11px;', 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }"), SurveyScheduler::daysofWeekOptions(), '') . RCView::SP .
									$lang['survey_424'] . RCView::SP . RCView::SP .
									RCView::input(array('name'=>"reminder_nexttime",'type'=>'text', 'class'=>'x-form-text x-form-field time2',
										'style'=>'text-align:right;font-size:11px;width:40px;', 'onblur'=>"redcap_validate(this,'','','soft_typed','time',1)",
										'onfocus'=>"if( $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}",
										'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=NEXT_OCCURRENCE]').prop('checked',true).trigger('change'); }")) .
									RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), 'H:M')

								).
								// Time lag of X amount of days/hours/minutes
								RCView::div(array('style'=>'padding:1px 0;'),
									RCView::radio(array('name'=>"reminder_type",'value'=>'TIME_LAG')) .
									$lang['survey_735'] . RCView::SP . RCView::SP .
									RCView::span(array('style'=>'font-size:11px;'),
										RCView::input(array('name'=>"reminder_timelag_days",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:31px;', 'value'=>'', 'maxlength'=>'3', 'onblur'=>"redcap_validate(this,'0','999','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
										$lang['survey_426'] . RCView::SP . RCView::SP .
										RCView::input(array('name'=>"reminder_timelag_hours",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;', 'value'=>'', 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
										$lang['survey_427'] . RCView::SP . RCView::SP .
										RCView::input(array('name'=>"reminder_timelag_minutes",'type'=>'text', 'class'=>'x-form-text x-form-field', 'style'=>'text-align:center;font-size:11px;width:25px;', 'value'=>'', 'maxlength'=>'2', 'onblur'=>"redcap_validate(this,'0','99','hard','int');", 'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=TIME_LAG]').prop('checked',true).trigger('change'); }")) .
										$lang['survey_428']
									)
								) .
								// Exact time
								RCView::div(array('style'=>'padding:1px 0;'),
									RCView::radio(array('name'=>"reminder_type",'value'=>'EXACT_TIME')) .
									$lang['survey_429'] . RCView::SP . RCView::SP .
									RCView::input(array('name'=>"reminder_exact_time", 'type'=>'text', 'class'=>'reminderdt x-form-text x-form-field',
										'value'=>'', 'style'=>'width:102px;font-size:11px;padding-bottom:1px;',
										'onkeydown'=>"if(event.keyCode==13){return false;}",
										'onfocus'=>"this.value=trim(this.value); if(this.value.length == 0 && $('.ui-datepicker:first').css('display')=='none'){ $(this).next('img').trigger('click');}" ,
										'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);",
										'onchange'=>"if ($(this).val() != '') { $('#reminders_choices_div input[name=reminder_type][value=EXACT_TIME]').prop('checked',true).trigger('change'); }")) .
									RCView::span(array('class'=>'df', 'style'=>'padding-left: 5px;'), DateTimeRC::get_user_format_label().' H:M')
								) .
								// Recurrence
								RCView::div(array('style'=>'margin:4px 0 5px -15px;color:#999;'),
									"&ndash; " . $lang['global_87'] . " &ndash;"
								) .
								RCView::div(array('style'=>''),
									$lang['survey_739'] . RCView::SP . RCView::SP .
									RCView::select(array('name'=>"reminder_num",'style'=>'font-size:11px;'), array('1'=>$lang['survey_736'], '2'=>"{$lang['survey_737']} 2 {$lang['survey_738']}",
										'3'=>"{$lang['survey_737']} 3 {$lang['survey_738']}", '4'=>"{$lang['survey_737']} 4 {$lang['survey_738']}",
										'5'=>"{$lang['survey_737']} 5 {$lang['survey_738']}", ), '1')
								)
							)
						)
					);
			?>

			<!-- Email form -->
			<fieldset id="compose_email_form_fieldset" style="padding-left:8px;border:1px solid #ccc;background-color:#F3F5F5;">
				<legend style="font-weight:bold;color:#333;">
                    <i class="fas fa-envelope"></i>
					<?php echo $lang['survey_692'] ?>
				</legend>
				<div style="padding:10px 0 10px 2px;">
					<table border=0 cellspacing=0 width=100%>
					<tr id="compose_email_from_tr">
						<!-- from drop-down -->
						<td style="vertical-align:top;width:50px;"><?php echo $lang['global_37'] ?></td>
						<td style="vertical-align:middle;color:#555;">
						<?php echo  '<div class="clearfix nowrap">
                                    <div class="float-left" style="width:180px;margin-right:3px;">
                                        '.RCView::select(array('class'=>'x-form-text x-form-field'.($GLOBALS['use_email_display_name']?'':' hidden'),'style'=>'width:100%;','id'=>'email_sender_display','name'=>'email_sender_display', 'placeholder'=>$lang['survey_1270']),
                                        array($lang['survey_1271']=>(SurveyScheduler::getDisplayNamesParticipantList()+array(''=>'-- clear --'))), '', 500
                                         ).'
                                    </div>
                                    <div class="float-left" style="width:75%;max-width:300px;">' .
                                        User::emailDropDownListAllUsers($user_email) .
                                        '</div>
                                    </div>' .
                                    RCView::div(array('style'=>'padding:2px 0 0 2px;font-size:11px;color:#777;'),
                                        $lang['survey_417']
                                    )
						?>
					</tr>
					<tr>
						<td style="vertical-align:middle;width:50px;padding-top:10px;"><?php echo $lang['global_38'] ?></td>
						<td style="vertical-align:middle;padding-top:10px;color:#666;font-weight:bold;"><?php echo $lang['survey_693'] ?></td>
					</tr>
					<tr id="compose_email_subject_tr">
						<!-- email subject -->
						<td valign="top" style="width:50px;padding:13px 0 0;"><?php echo $lang['survey_103'] ?></td>
						<td valign="top" style="padding:10px 0 0;">
							<input class="x-form-text x-form-field" style="width:380px;" type="text" id="emailTitle" name="emailTitle" onkeydown="if(event.keyCode == 13){return false;}" />
							<?php if ($twilio_enabled && $Proj->twilio_enabled_surveys) { ?>
								<div class="show_for_part_pref show_for_sms show_for_voice" style="color:#000066;font-size:11px;"><?php print $lang['survey_917'] ?></div>
							<?php } ?>
						</td>
					</tr>
					<tr>
						<!-- email message -->
						<td colspan="2" style="padding:3px 0 10px;">
                            <div class="text-right mb-1 mr-5">
                                <a href="javascript:;" class="fs11" onclick="textareaTestPreviewEmail('#emailCont',1,'#emailTitle','#emailFrom option:selected');"><?=$lang['design_700']?></a>
                            </div>
							<textarea class="x-form-field notesbox mceEditor" id="emailCont" name="emailCont"  onblur="checkComposeForSurveyLink(this);" style="margin-bottom:4px;height:280px;width:95%;"></textarea>
							<?php if ($twilio_enabled && $Proj->twilio_enabled_surveys) { ?>
								<div class="show_for_voice show_for_part_pref" style="color:#000066;font-size:11px;margin-bottom:2px;"><?php print $lang['survey_918'] ?></div>
							<?php } ?>
						</td>
					</tr>
					</table>
					<!-- Text below email form -->
					<div style="padding:0 5px;">
						<div style="font-size:11px;color:#D00000;padding-bottom:8px;line-height:13px;">
							<i class="far fa-lightbulb"></i> <b><?php echo $lang['survey_105'] ?></b> <?php echo ($twilio_enabled && $Proj->twilio_enabled_surveys ? $lang['survey_1226'] : $lang['survey_1225']) ?>
						</div>

						<div style="font-size:11px;color:#555;padding:0 5px 0 0;">
							<a href="javascript:;" style="font-size:11px;color:#3E72A8;text-decoration:underline;" onclick="pipingExplanation();"><?php echo $lang['design_468'] ?></a>
						</div>
						<select id="selectDispPrevEmails" class="x-form-text x-form-field" style="<?php echo $divDispPrevEmails_display ?>margin:20px 0;font-size:11px;width:550px !important;max-width:550px !important;" onchange="if(this.value=='')return;$('#emailCont').val(this.value);tinyMCE.activeEditor.setContent(this.value);">
							<option value=""> - <?php echo $lang['survey_1235'] ?> - </option>
							<?php foreach ($emailSelect as $key=>$val) { ?>
							<option value="<?php echo $key ?>"><?php echo $val ?></option>
							<?php } ?>
						</select>
					</div>
				</div>
			</fieldset>
		</td>
		<td style="padding:10px 0 0 10px;width:530px;" id="emailSendList_div" valign="top"></td>
	</tr></table>
	</form>
</div>
<?php
}

Survey::renderCheckComposeForSurveyLink();

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
