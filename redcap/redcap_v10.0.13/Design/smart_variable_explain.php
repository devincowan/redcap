<?php


define("NOAUTH", true);
if (isset($_GET['pid'])) {
	include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} else {
	include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

$replaceSeparator = "|-RC-COLON-|";
$smartVarsInfo = Piping::getSpecialTagsInfo();

// Build list of all action tags
$smart_var_descriptions = 
	RCView::tr(array(),
		RCView::td(array('rowspan'=>'2', 'class'=>'nowrap', 'style'=>'width:270px;background-color:#e5e5e5;padding:7px;font-weight:bold;border:1px solid #bbb;border-bottom:0;'),
			$lang['piping_25']
		) .
		RCView::td(array('rowspan'=>'2', 'style'=>'background-color:#e5e5e5;padding:7px;font-weight:bold;border:1px solid #bbb;border-bottom:0;border-left:0;'),
			$lang['global_20']
		) .
		RCView::td(array('colspan'=>'2', 'style'=>'width:300px;text-align:center;background-color:#e5e5e5;padding:7px;font-weight:bold;border:1px solid #bbb;border-bottom:0;border-left:0;'),
			$lang['piping_26']
		)
	) .
	RCView::tr(array(),
		RCView::td(array('style'=>'width:180px;text-align:center;background-color:#e5e5e5;padding:7px;border:1px solid #bbb;border-bottom:0;border-left:0;'),
			$lang['piping_27']
		) .
		RCView::td(array('style'=>'width:120px;text-align:center;background-color:#e5e5e5;padding:7px;border:1px solid #bbb;border-bottom:0;border-left:0;'),
			$lang['piping_28']
		)
	);
foreach ($smartVarsInfo as $catname=>$attr0) 
{
	// Category header
	$smart_var_descriptions .=
			RCView::tr(array(),
				RCView::td(array('colspan'=>'4', 'class'=>'header', 'style'=>'padding:10px;font-size:14px;color:#800000;'),
					$catname
				)
			);	
	foreach ($attr0 as $tag=>$attr) 
	{
		$description = array_shift($attr);
		$examplesCount = count($attr);
		$example = array_shift($attr);
		// Make the parameters that follow the colon a lighter color
		$tag = str_replace(":", $replaceSeparator, $tag);
		$tagParts = explode($replaceSeparator, $tag);
		$tag = array_shift($tagParts);
		if (count($tagParts) > 0) {
			$tag .= "<span style='color:#ca8a00;'>$replaceSeparator" . implode($replaceSeparator, $tagParts) . "</span>";
		}
		// Make "Custom Text" a different color text
		$tag = str_replace($replaceSeparator."Custom Text", "<span style='color:rgba(128, 0, 0, 0.70);'>".$replaceSeparator."Custom Text</span>", $tag);
		// Put some spacing around colons for easier reading
		$tag = str_replace($replaceSeparator, "<span style='margin:0 1px;'>:</span>", $tag);
		// Output row
		$example[0] = implode("-<wbr>", explode("-", $example[0]));
		$example[0] = str_replace("][", "<span class='nowrap'>][</span>", $example[0]);
		$smart_var_descriptions .=
			RCView::tr(array(),
				RCView::td(array('rowspan'=>$examplesCount, 'class'=>'nowrap', 'style'=>'width:270px;background-color:#f5f5f5;color:green;padding:7px;font-weight:bold;border:1px solid #ccc;border-bottom:0;'),
					RCView::span(array('style'=>'margin-right:1px;'), "[") . $tag . RCView::span(array('style'=>'margin-left:1px;'), "]")
				) .
				RCView::td(array('rowspan'=>$examplesCount, 'style'=>'font-size:12px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;'),
					$description
				) .
				RCView::td(array('style'=>'width:180px;font-size:11px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;color:#666;'),
					$example[0]
				) .
				RCView::td(array('class'=>'wrap','style'=>'word-break: break-word;width:120px;font-size:11px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;color:#666;'),
					$example[1]
				)
			);
		// Add extra examples
		foreach ($attr as $example) {
			$example[0] = implode("-<wbr>", explode("-", $example[0]));
			$example[0] = str_replace("][", "<span class='nowrap'>][</span>", $example[0]);
			$smart_var_descriptions .=
				RCView::tr(array(),
					RCView::td(array('style'=>'width:180px;font-size:11px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;color:#666;'),
						$example[0]
					) .
					RCView::td(array('class'=>'wrap','style'=>'word-break: break-word;width:120px;font-size:11px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;color:#666;'),
						$example[1]
					)
				);
		}
	}
}

// Content
$content  = (!$isAjax ? '' :
				RCView::div(array('class'=>'clearfix'),
					RCView::div(array('style'=>'color:green;font-size:18px;font-weight:bold;float:left;padding:10px 0;'),
						"[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] " . $lang['global_146']
					) .
					RCView::div(array('style'=>'text-align:right;float:right;'),
						RCView::a(array('href'=>PAGE_FULL, 'target'=>'_blank', 'style'=>'text-decoration:underline;'),
							$lang['survey_977']
						)
					)
				)
			) . 
			// Instructions
			RCView::div(array('style'=>'font-weight:bold;font-size:14px;margin:10px 0 5px;'),
				$lang['design_737']
			) .
			RCView::div(array('style'=>''),
				$lang['design_738']
			) .
			RCView::div(array('style'=>'font-weight:bold;font-size:14px;margin:20px 0 5px;'),
				$lang['design_739']
			) .
			RCView::div(array('style'=>''),
				$lang['design_740'] .
				RCView::div(array('style'=>'margin-top:10px;'),
					$lang['design_746'] .
					RCView::ul(array('style'=>'margin-top:5px;'),
						RCView::li(array('style'=>''), $lang['design_743']) .
						RCView::li(array('style'=>''), $lang['design_744']) .
						RCView::li(array('style'=>''), $lang['design_745'])
					)
				)
			) .
			RCView::div(array('style'=>'font-weight:bold;font-size:14px;margin:20px 0 5px;'),
				$lang['design_741']
			) .
			RCView::div(array('style'=>''),
				$lang['design_742'] .
				RCView::div(array('style'=>'margin-top:10px;'),
					$lang['design_752'] .
					RCView::ul(array('style'=>'margin-top:5px;'),
						RCView::li(array('style'=>''), $lang['design_749']) .
						RCView::li(array('style'=>''), $lang['design_767']) .
						RCView::li(array('style'=>''), $lang['design_751'])
					)
				)
			) .
			RCView::div(array('style'=>'margin:10px 0 5px;'),
				$lang['design_766']
			) .
			(!SUPER_USER ? '' : 
				RCView::div(array('style'=>'margin:10px 0 5px;'),
					$lang['design_765']
				)
			) .
			RCView::div(array('style'=>'font-weight:bold;font-size:14px;margin:20px 0 5px;'),
				$lang['piping_39']
			) .
			RCView::div(array('style'=>''),
				$lang['piping_40']
			) .
			// Table
			RCView::div(array('style'=>''),
				RCView::table(array('style'=>'table-layout:fixed;margin-top:20px;width:100%;border-bottom:1px solid #ccc;line-height:13px;'),
					$smart_var_descriptions
				)
			);

if ($isAjax) {	
	// Return JSON
	print json_encode_rc(array('content'=>$content, 'title'=>$lang['global_146']));
} else {
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->PrintHeaderExt();
	print 	RCView::div(array('class'=>'clearfix'),
				RCView::div(array('style'=>'font-size:18px;font-weight:bold;float:left;padding:10px 0 0;color:green;'),
					"[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] " . $lang['global_146']
				) .
				RCView::div(array('style'=>'text-align:right;float:right;'),
					RCView::img(array('src'=>'redcap-logo.png'))
				)
			) .
			RCView::div(array('style'=>'margin:10px 0;font-size:13px;'),
				$content
			);
	?><style type="text/css">#footer { display: block; }</style><?php
	$objHtmlPage->PrintFooterExt();
}
