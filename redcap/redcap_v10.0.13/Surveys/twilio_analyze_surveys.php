<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Initialize vars
$popupContent = "";


// Show column for voice call surveys (if enabled)
$showPhone = ($twilio_option_voice_initiate || $twilio_option_sms_invite_make_call || $twilio_option_sms_invite_receive_call);
// Show column for SMS surveys (if enabled)
$showSms = ($twilio_option_sms_initiate);

// Array to store issues for each survey
$survey_issues = array();
// Loop through medium types
foreach (array('SMS', 'VOICE') as $type) {
	// Don't check for SMS or VOICE if not applicable
	// if (($type == 'SMS' && !$showSms) || ($type == 'VOICE' && !$showPhone)) continue;
	// Loop through surveys
	foreach ($Proj->forms as $form=>$form_attr) {
		if (!isset($form_attr['survey_id'])) continue;
		$survey_id = $form_attr['survey_id'];
		// Add to $survey_issues
		$survey_issues[$survey_id][$type] = array();
		// Loop through fields
		foreach (array_keys($form_attr['fields']) as $field) {
			// Skip record ID field and form status field
			if ($field == $table_pk || $field == $form."_complete") continue;
			// Determine if field is viable for this medium type
			$isViable = TwilioRC::fieldUsageIVR($type, $field);
			// If not viable, then display why
			if ($isViable !== true) {
				// Add to array
				$survey_issues[$survey_id][$type][$field] = $isViable;
			}
		}
	}
}


// Now loop through all issues and display (if any)
$rows = "";
foreach ($survey_issues as $survey_id=>$attr) {
	// HTML for survey title row
	$this_survey_row = "";
	// Show column for voice call surveys (if enabled)
	if ($showPhone) {
		// Get issue count
		$issue_count = count($attr['VOICE']);
		// Issue count style
		if ($issue_count > 0) {
			$issue_style = 'background:#FFE1E1 url("'.APP_PATH_IMAGES.'exclamation.png") no-repeat 10px center;font-weight:bold;color:red;';
		} else {
			$issue_style = 'background:#EFF6E8 url("'.APP_PATH_IMAGES.'tick.png") no-repeat 10px center;color:green;';
		}
		$this_survey_row .= RCView::td(array('class'=>'data', 'style'=>$issue_style.'margin:0;font-size:15px;text-align:right;width:100px;'),
								$issue_count .
								RCView::a(array('class'=>'opacity75', 'style'=>($issue_count > 0 ? '' : 'visibility:hidden;').'margin:0 5px 0 30px;font-weight:normal;text-decoration:underline;font-size:11px;',
									'href'=>'javascript:;', 'onclick'=>"$('#tasrow_$survey_id').toggle(); fitDialog($('#tas_dlg')); if ($('#tasrow_$survey_id').is(':visible')) { highlightTableRow('tasrow_$survey_id',1500); }"), $lang['dataqueries_92'])
							);
	}
	// Show column for SMS surveys (if enabled)
	if ($showSms) {
		// Get issue count
		$issue_count = count($attr['SMS']);
		// Issue count style
		if ($issue_count > 0) {
			$issue_style = 'background:#FFE1E1 url("'.APP_PATH_IMAGES.'exclamation.png") no-repeat 10px center;font-weight:bold;bold;color:red;';
		} else {
			$issue_style = 'background:#EFF6E8 url("'.APP_PATH_IMAGES.'tick.png") no-repeat 10px center;color:green;';
		}
		$this_survey_row .= RCView::td(array('class'=>'data', 'style'=>$issue_style.'margin:0;font-size:15px;text-align:right;width:100px;'),
								$issue_count .
								RCView::a(array('class'=>'opacity75', 'style'=>($issue_count > 0 ? '' : 'visibility:hidden;').'margin:0 5px 0 30px;font-weight:normal;text-decoration:underline;font-size:11px;',
									'href'=>'javascript:;', 'onclick'=>"$('#tasrow_$survey_id').toggle(); fitDialog($('#tas_dlg')); if ($('#tasrow_$survey_id').is(':visible')) { highlightTableRow('tasrow_$survey_id',1500); }"), $lang['dataqueries_92'])
							);
	}
	// Add this survey's title
	$rows .= RCView::tr(array(),
				$this_survey_row .
				RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;font-size:13px;'),
					RCView::escape($Proj->surveys[$survey_id]['title'])
				)
			 );

	// Loop through medium types and fields
	$this_issue_row = "";
	foreach ($attr as $type=>$fields) {
		// Header
		if (!empty($fields) && $showSms && $showPhone) {
			$this_issue_row .= 	RCView::div(array('style'=>'font-weight:bold;font-size:13px;padding:7px 5px 0 30px;'),
									($type == 'SMS' ? $lang['survey_880'] : $lang['survey_885'])
								);
		}
		// Loop through fields
		foreach ($fields as $field=>$issue) {
			$this_issue_row .= 	RCView::div(array('style'=>'padding:2px 10px 0 50px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;'),
									" &bull; $field &nbsp;(\"<i>{$Proj->metadata[$field]['element_label']}</i>\")"
								) .
								RCView::div(array('style'=>'margin:0 10px 2px 93px;text-indent:-19px;color:#800000;'),
									" &nbsp; " .
									RCView::img(array('src'=>'bullet_delete.png')) .
									RCView::b($lang['survey_893']). " $issue"
								);
		}
	}
	if ($this_issue_row != "") {
		// Add invisible rows for each survey, which can be made visible
		$rows .= RCView::tr(array('id'=>"tasrow_$survey_id", 'class'=>"data", 'style'=>'display:none;'),
					RCView::td(array('colspan'=>($showSms && $showPhone ? '3' : '2'), 'class'=>'data', 'style'=>'background:#eee;padding-bottom:5px;'),
						$this_issue_row
					)
				 );
	}
}

// If no surveys, then display message
if (empty($survey_issues)) {
	$rows .= RCView::tr(array('class'=>"data"),
				RCView::td(array('colspan'=>($showSms && $showPhone ? '3' : '2'), 'class'=>'data', 'style'=>'color:#555;padding:10px;'),
					$lang['survey_894']
				)
			 );
}

// Build html output
$popupContent = RCView::div('',
					$lang['survey_895']
				) .
				RCView::table(array('class'=>'form_border', 'cellspacing'=>'0', 'style'=>'margin:15px 0 10px;table-layout:fixed;width:100%;'),
					// Header row
					RCView::tr(array(),
						(!$showPhone ? '' :
							RCView::td(array('class'=>'header', 'style'=>'text-align:center;width:100px;'),
								$lang['survey_885']
							)
						) .
						(!$showSms ? '' :
							RCView::td(array('class'=>'header', 'style'=>'text-align:center;width:100px;'),
								$lang['survey_880']
							)
						) .
						RCView::td(array('class'=>'header', 'style'=>'padding-left:10px;'),
							$lang['survey_49']
						)
					) .
					// Rows
					$rows
				);

// Send back JSON response
print json_encode_rc(array('popupContent'=>$popupContent, 'popupTitle'=>$lang['survey_869']));