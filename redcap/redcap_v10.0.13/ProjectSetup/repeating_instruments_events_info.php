<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if (!$isAjax) {	
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();	
}
print 	RCView::div(array('style'=>($isAjax ? 'display:none;' : '')),
			RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:10px 0 30px;'),
				$lang['setup_146']
			) .
			RCView::div(array('style'=>'text-align:right;float:right;'),
				RCView::img(array('src'=>'redcap-logo.png'))
			) .
			RCView::div(array('class'=>'clear'), '')
		) .
		RCView::div('',
			RCView::div(array('style'=>'float:left;'),
				RCView::span(array('class'=>'fas fa-film'), '') . 
				RCView::a(array('href'=>'javascript:', 'style'=>'margin-left:5px;text-decoration:underline;', 'onclick'=>"popupvid('repeating_forms_events01.mp4')"),
					$lang['data_entry_293'] . ' (33 ' . $lang['survey_428'] . ')'
				)
			) .
			RCView::div(array('style'=>'text-align:right;float:right;'.(!$isAjax ? 'display:none;' : '')),
				RCView::a(array('href'=>APP_PATH_WEBROOT."ProjectSetup/repeating_instruments_events_info.php", 'target'=>'_blank', 'style'=>'color:#800000;text-decoration:underline;'),
					$lang['survey_977']
				)
			) .
			RCView::div(array('class'=>'clear'), '')
		) .
		// Paragraphs
		RCView::div(array('style'=>'margin-top:20px;'), $lang['data_entry_303']) .
		RCView::div(array('style'=>'margin-top:20px;font-weight:bold;'), $lang['data_entry_304']) .
		RCView::div(array('style'=>'margin-top:5px;'), $lang['data_entry_305']) .
		RCView::div(array('style'=>'margin-top:20px;font-weight:bold;'), $lang['data_entry_306']) .
		RCView::div(array('style'=>'margin-top:5px;'), $lang['data_entry_307']) .
		RCView::div(array('style'=>'margin-top:20px;font-weight:bold;'), $lang['data_entry_308']) .
		RCView::div(array('style'=>'margin-top:5px;'), $lang['data_entry_309']) .
		RCView::div(array('style'=>'margin-top:20px;font-weight:bold;'), $lang['data_entry_310']) .
		RCView::div(array('style'=>'margin-top:5px;'), $lang['data_entry_311']);
if (!$isAjax) {	
	?><style type="text/css">#footer { display: block; }</style><?php
	$objHtmlPage->PrintFooterExt();
}