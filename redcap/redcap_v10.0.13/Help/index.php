<?php


// Give link to go back to previous page if coming from a project page
$prevPageLink = "";
if (!isset($_GET['newwin']) && isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], "pid=") !== false) {
	$prevPageLink = "<div style='margin:0 0 5px;'>
						<img src='" . APP_PATH_IMAGES . "arrow_skip_180.png'>
						<a href='".htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES)."' style='color:#2E87D2;font-weight:bold;'>{$lang['help_01']}</a>
					 </div>";
}

// If site has set custom text to be displayed at top of page, then display it
$helpfaq_custom_html = '';
if (trim($helpfaq_custom_text) != '')
{
	// Set html for div
	$helpfaq_custom_html = "<div id='helpfaq_custom_text'><div class='blue' style='max-width:800px;margin:130px 10px 10px 0;padding:10px;'>".nl2br(decode_filter_tags($helpfaq_custom_text))."</div></div>";
}

print $helpfaq_custom_html . $prevPageLink;

// Include help content scraped from End-User FAQ Community space
include APP_PATH_DOCROOT . 'Help/help_content.php';