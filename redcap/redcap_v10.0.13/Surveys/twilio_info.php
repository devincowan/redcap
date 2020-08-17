<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if ($isAjax) {
	print 	RCView::div('',
				RCView::div(array('style'=>'font-size:16px;font-weight:bold;float:left;'),
					RCView::img(array('src'=>'twilio.png')) .
					$lang['survey_1284']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::a(array('href'=>APP_PATH_WEBROOT."Surveys/twilio_info.php", 'style'=>'text-decoration:underline;'),
						$lang['survey_977']
					)
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1276']) .
			RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_969']) .
		    RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_1282']) .
			RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_970']) .
			RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_973'] . " " . $lang['survey_974']) .
			RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_972']) .
			RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_975']) .
			RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_967']) .
			RCView::div(array('style'=>'margin-top:10px;'), ($twilio_enabled_by_super_users_only ? $lang['survey_937'] : $lang['survey_938']));
} else {
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div('',
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'),
					RCView::img(array('src'=>'twilio.png')) .
					$lang['survey_1284']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::img(array('src'=>'redcap-logo.png'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin:30px 0;font-size:13px;'),
				$lang['survey_1276'] .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_969']) .
				RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_1282']) .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_970']) .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_973'] . " " . $lang['survey_974']) .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_972']) .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_975']) .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_967']) .
				RCView::div(array('style'=>'margin-top:10px;'), $lang['survey_971'])
			);
	?><style type="text/css">#footer { display: block; }</style><?php
	$objHtmlPage->PrintFooterExt();
}