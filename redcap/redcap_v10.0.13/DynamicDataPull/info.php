<?php


// Disable authentication so we can use this page as an information page to anyone
define("NOAUTH", true);
// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
// Is FHIR or custom?
$fhir = (isset($_GET['type']) && $_GET['type'] == 'fhir');

// Dynamic Data Pull explanation - hidden dialog text
if ($isAjax) {
	print json_encode_rc(array('content'=>DynamicDataPull::getDdpExplanationText($fhir),
							'title'=>'<i class="fas fa-database"></i> ' .
									 RCView::span(array('style'=>'vertical-align:middle;'), 
										($fhir ? $lang['ws_210'] : $lang['ws_51']) . 
										" " . DynamicDataPull::getSourceSystemName($fhir)
									 )
							));
}

// If non and AJAX request, display as regular page with header/footer
else
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div('',
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'),
					'<i class="fas fa-database"></i> ' .
					($fhir ? $lang['ws_210'] : $lang['ws_51']) . 
					" " . DynamicDataPull::getSourceSystemName($fhir)
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::img(array('src'=>'redcap-logo.png'))
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			RCView::div(array('style'=>'margin:15px 0 50px;'), DynamicDataPull::getDdpExplanationText($fhir));
	$objHtmlPage->PrintFooterExt();
}