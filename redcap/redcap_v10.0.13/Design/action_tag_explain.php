<?php


if (isset($_GET['pid'])) {
	include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

// Build list of all action tags
$action_tag_descriptions = "";
foreach (Form::getActionTags() as $tag=>$description) {
	$action_tag_descriptions .=
		RCView::tr(array(),
			RCView::td(array('class'=>'nowrap', 'style'=>'text-align:center;background-color:#f5f5f5;color:#912B2B;padding:7px 15px 7px 12px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-right:0;'),
				((!$isAjax || (isset($_POST['hideBtns']) && $_POST['hideBtns'] == '1')) ? '' :
					RCView::button(array('class'=>'btn btn-xs btn-rcred', 'style'=>'', 'onclick'=>"$('#field_annotation').val(trim('".js_escape($tag)." '+$('#field_annotation').val())); highlightTableRowOb($(this).parentsUntil('tr').parent(),2500);"), $lang['design_171'])
				)
			) .
			RCView::td(array('class'=>'nowrap', 'style'=>'background-color:#f5f5f5;color:#912B2B;padding:7px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-left:0;border-right:0;'),
				$tag
			) .
			RCView::td(array('style'=>'font-size:12px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;'),
				$description
			)
		);
}

// Content
$content  = (!$isAjax ? '' :
				RCView::div(array('class'=>'clearfix'),
					RCView::div(array('style'=>'color:#A00000;font-size:18px;font-weight:bold;float:left;padding:10px 0;'),
						'@ ' . $lang['global_132']
					) .
					RCView::div(array('style'=>'text-align:right;float:right;'),
						RCView::a(array('href'=>PAGE_FULL, 'target'=>'_blank', 'style'=>'text-decoration:underline;'),
							$lang['survey_977']
						)
					)
				)
			) . 
			RCView::div(array('style'=>''),
				$lang['design_724'] . " " . $lang['design_723']
			) .
			// If Twilio telephony for surveys is enabled, then add text that Action Tags do not work with SMS/Voice surveys
			(!(isset($_GET['pid']) && $twilio_enabled && $Proj->twilio_enabled_surveys) ? '' :
				RCView::div(array('class'=>'yellow', 'style'=>'margin-top:10px;'),
					RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['survey_1154']
				)
			) .
			RCView::div(array('style'=>'margin:10px 0 5px;'),
				RCView::b($lang['design_608']) .
				RCView::table(array('style'=>'margin-top:1px;width:100%;border-bottom:1px solid #ccc;line-height:13px;'),
					$action_tag_descriptions
				)
			);

if ($isAjax) {	
	// Return JSON
	print json_encode_rc(array('content'=>$content, 'title'=>$lang['design_606']));
} else {
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div('',
				RCView::div(array('style'=>'color:#A00000;font-size:18px;font-weight:bold;float:left;padding:10px 0 0;'),
					'@ ' . $lang['global_132']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::img(array('src'=>'redcap-logo.png'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin:10px 0;font-size:13px;'),
				$content
			);
	?><style type="text/css">#footer { display: block; }</style><?php
	$objHtmlPage->PrintFooterExt();
}
