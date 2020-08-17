<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Check hash
if (!isset($_GET['hash'])) exit('0');
$hash = $_GET['hash'];

// Get participant_id from the hash
$participant_id = Survey::getParticipantIdFromHash($hash);
if (!is_numeric($participant_id)) exit('0');

// Get survey_id
$sql = "select survey_id from redcap_surveys_participants where participant_id = $participant_id";
$q = db_query($sql);
$survey_id = db_result($q, 0);

// Does it have short code?
$hasShortCode = (isset($_GET['shortCode']) && $_GET['shortCode']);

// Generate survey access code OR short code
$code = Survey::getAccessCode($participant_id, $hasShortCode);

// If just generating a short code, then return it only
if ($hasShortCode) {
	// Get expiration time of code
	$expiration = DateTimeRC::format_ts_from_ymd(date("H:i:s", mktime(date("H"),date("i")+Survey::SHORT_CODE_EXPIRE,date("s"),date("m"),date("d"),date("Y"))));
	// Output JSON
	exit(json_encode_rc(array('code'=>$code, 'expiration'=>$expiration)));
}

// Set dialog title
$t = RCView::img(array('src'=>'ticket_arrow.png','style'=>'vertical-align:middle;')) .
	 RCView::span(array('style'=>'margin-left:3px;vertical-align:middle;'),
		(gd2_enabled()
			? $lang['survey_621'] .
			  RCView::img(array('src'=>'qrcode.png','style'=>'vertical-align:middle;margin-left:5px;')) .
			  $lang['survey_664']
			: $lang['survey_629'])
	 );

// Set dialog content
$c = RCView::div(array('style'=>'font-size:'.($isAjax ? '14px' : '16px').';color:#800000;'),
		$lang['survey_310'] . " \"" . RCView::b(RCView::escape($Proj->surveys[$survey_id]['title'])) . "\""
	) .
	($isAjax ?
		// Dialog
		RCView::div(array('style'=>'font-size:14px;margin:15px 0 20px;'),
			(gd2_enabled() ? $lang['survey_630'] : $lang['survey_631']) . " " . $lang['survey_648']
		) :
		// "Print" page
		RCView::div(array('style'=>'font-size:14px;margin:15px 0 20px;'),
			$lang['survey_626'] . " " . (gd2_enabled() ? $lang['survey_633'] : '')
		)
	).
	RCView::table(array('style'=>'table-layout:fixed;border-top:1px solid #ccc;padding-top:10px;width:100%;'),
		RCView::tr(array(),
			RCView::td(array('valign'=>'top', 'style'=>'padding-right:20px;padding-top:10px;'),
				## SURVEY ACCESS CODE
				(!gd2_enabled() ? '' :
					RCView::div(array('style'=>'color:#800000;font-weight:bold;font-size:15px;margin-bottom:10px;'),
						RCView::img(array('src'=>'ticket_arrow.png','style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_652'])
					)
				) .
				RCView::div(array('style'=>''),
					($isAjax ? $lang['survey_623'] : $lang['survey_650'])
				) .
				RCView::div(array('style'=>'font-weight:bold;font-size:14px;'),
					RCView::div(array('style'=>'margin:20px 0 8px;'),
						$lang['survey_625']
					) .
					RCView::textarea(array('class'=>'staticInput', 'style'=>'margin-left:22px;white-space:normal;color:#111;font-size:16px;width:'.(gd2_enabled() ? '420px;' : '95%;'), 'readonly'=>'readonly', 'onclick'=>'this.select();'),
						APP_PATH_SURVEY_FULL
					)
				) .
				RCView::div(array('style'=>'font-weight:bold;font-size:14px;margin-bottom:10px;'),
					RCView::div(array('style'=>'margin:20px 0 8px;'),
						$lang['survey_624']
					) .
					RCView::text(array('class'=>'staticInput', 'style'=>'letter-spacing:1px;margin-left:22px;color:#111;font-size:16px;width:130px;', 'value'=>$code, 'readonly'=>'readonly', 'onclick'=>'this.select();'))
				) .
				## SHORT CODE
				(!$isAjax ? '' :
					RCView::fieldset(array('style'=>'margin:25px 0 0;padding:10px 10px 6px;border:0;border-top:1px solid #ccc;'),
						RCView::legend(array('style'=>'padding:0 3px;margin-left:10px;color:#666;font-size:15px;'),
							$lang['global_46']
						)
					) .
					RCView::div(array('style'=>'color:#800000;font-weight:bold;font-size:15px;margin-bottom:10px;'),
						RCView::img(array('src'=>'clock_fill.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_653'])
					) .
					RCView::div(array('style'=>''),
						RCView::div(array('style'=>'margin-bottom:10px;'), $lang['survey_654']) .
						RCView::div(array('id'=>'gen_short_access_code_div', 'style'=>'margin-bottom:15px;'),
							RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-size:14px;color:#333;', 'onclick'=>"$(this).button('disable');getAccessCode('$hash',1);"),
								$lang['survey_653']
							) .
							RCView::img(array('id'=>'gen_short_access_code_img', 'src'=>'progress_circle.gif', 'style'=>'display:none;margin-left:10px;position:relative;top:3px;'))
						) .
						RCView::div(array('id'=>'short_access_code_div', 'style'=>'display:none;padding:5px 0;'),
							RCView::div(array('style'=>'margin:0 0 8px;font-weight:bold;font-size:14px;'),
								$lang['survey_655']
							) .
							RCView::text(array('id'=>'short_access_code', 'class'=>'staticInput', 'style'=>'letter-spacing:1px;margin:0 20px 0 22px;color:#111;font-size:16px;width:80px;', 'value'=>'', 'readonly'=>'readonly', 'onclick'=>'this.select();')) .
							RCView::span(array('style'=>'color:#C00000;'),
								$lang['survey_656'] . RCView::SP .
								RCView::span(array('id'=>'short_access_code_expire', 'style'=>'font-weight:bold;'), '')
							)
						)
					)
				)
			) .
			(!gd2_enabled() ? '' :
				RCView::td(array('id'=>'qrcode-info', 'valign'=>'top', 'style'=>'width:225px;padding-left:20px;padding-top:10px;border-left:1px solid #ccc;'),
					## QR CODE
					RCView::div(array('style'=>'color:#800000;font-weight:bold;font-size:15px;margin-bottom:10px;'),
						RCView::img(array('src'=>'qrcode.png','style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_620'])
					) .
					RCView::div(array('style'=>''),
						($isAjax ? $lang['survey_632'] : $lang['survey_651'])
					) .
					RCView::div(array('style'=>'text-align:center;margin-top:20px;'),
						"<img src='".APP_PATH_WEBROOT."Surveys/survey_link_qrcode.php?pid=$project_id&hash=$hash'>"
					)
				)
			)
		)
	) .
	($isAjax ? '' :
		// Dialog
		RCView::div(array('class'=>'d-print-none', 'style'=>'text-align:center;margin:50px 0 0;'),
			RCView::button(array('style'=>'color:#800000;font-size:15px;padding:4px 8px;', 'onclick'=>"var currentWin = window.self;currentWin.close();"),
				$lang['data_export_tool_160']
			)
		)
	);

// Output JSON if AJAX
if ($isAjax) {
	print json_encode_rc(array('content'=>$c, 'title'=>$t));
} else {
	// Displaying on the "print" page
	print $c;
}