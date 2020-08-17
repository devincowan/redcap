<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

$text = <<<EOF
<b>{$lang['messaging_53']}</b>
{$lang['messaging_21']} {$lang['messaging_22']}

<b>{$lang['messaging_54']}</b>
{$lang['messaging_55']}

<b>{$lang['messaging_56']}</b>
{$lang['messaging_57']}

<b>{$lang['messaging_58']}</b>
{$lang['messaging_59']}

<b>{$lang['messaging_60']}</b>
{$lang['messaging_61']}

<b>{$lang['messaging_62']}</b>
{$lang['messaging_63']}

<b>{$lang['messaging_64']}</b>
{$lang['messaging_65']}

<b>{$lang['messaging_66']}</b>
{$lang['messaging_67']}

<b>{$lang['messaging_68']}</b>
{$lang['messaging_69']}

<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['messaging_186']}
EOF;

$objHtmlPage = new HtmlPage();
if (!$isAjax) $objHtmlPage->PrintHeaderExt();
print 	RCView::div('',
    ($isAjax ? '' :
			RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:30px 0 0;'),
				$lang['messaging_09']
			) .
			RCView::div(array('style'=>'text-align:right;float:right;'),
				RCView::img(array('src'=>'redcap-logo.png'))
			) .		
			RCView::div(array('class'=>'clear'), '')
          ) .
			RCView::div(array('style'=>'margin:15px 0;'),
                '<i class="fas fa-film"></i> ' .
				RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('messenger01.mp4')"), $lang['global_80'] . " " .$lang['messaging_28'])
			)
		) .
		RCView::div(array('style'=>'font-size:13px;'),
			nl2br($text)
		);
?><style type="text/css">#footer { display: block; }</style><?php
if (!$isAjax) $objHtmlPage->PrintFooterExt();