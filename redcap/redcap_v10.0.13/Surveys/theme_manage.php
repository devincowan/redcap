<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

if (!isset($_POST['action'])) exit("0");

// Verify that theme_id belongs to user
if (isset($_POST['theme_id'])) {
	$_POST['theme_id'] = (int)$_POST['theme_id'];
	if (!Survey::userHasCustomThemes($_POST['theme_id'])) exit("0");
}

// Display table of user's saved themes
if ($_POST['action'] == 'view')
{
	// Get utilization counts of this user's themes
	$themeUtilCount = Survey::getUserThemeUtilization(USERID);
	// Header
	$html = RCView::tr(array(),
				RCView::td(array('style'=>'', 'class'=>'header', 'colspan'=>4),
					RCView::img(array('src'=>'themes.png', 'style'=>'vertical-align:middle;width:16px;height:16px;')) .
					RCView::span(array('style'=>'vertical-align:middle;margin-left:2px;color:#800000;'), $lang['survey_1039'])
				)
			);
	// Icons
	$edit   = RCView::img(array('src'=>'pencil.png', 'style'=>'vertical-align:middle;padding:3px;'));
	$delete = RCView::img(array('src'=>'cross.png', 'style'=>'vertical-align:middle;'));
	// Get user's themes
	$user_themes = Survey::getUserThemes();
	foreach ($user_themes as $this_theme_id=>$attr)
	{
		// Utilization count
		$thisUtilCount = (isset($themeUtilCount[$this_theme_id]) ? $themeUtilCount[$this_theme_id] : 0);
		// Get theme name
		$td1 = RCView::td(array(
					'class'       => 'data',
					'style'       => "font-size:12px; padding:3px 0 3px 4px; width:330px;",
				),
					RCView::div(array('class'=>'theme_edit_label', 'style'=>'line-height:22px;'),
						RCView::escape($attr['theme_name'])
					) .
					RCView::div(array('class'=>'theme_edit_input', 'style'=>'display:none;'),
						RCView::text(array('class'=>'x-form-text x-form-field', 'style'=>'width:250px;', 'maxlength'=>'50', 'value'=>RCView::escape($attr['theme_name']))) .
						RCView::button(array('class'=>'jqbuttonsm', 'style'=>'margin-left:5px;color:#222;', 'onclick'=>"editThemeNameAjax($this_theme_id,this);"), $lang['folders_11']) .
						RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:10px;text-decoration:underline;font-size:11px;', 'onclick'=>"hideEditThemeName(this);"), $lang['global_53'])
					)
				);
		$a2 = RCview::a(array(
			'title'   => $lang['folders_22'],
			'href'    => 'javascript:;',
			'onclick' => "editThemeName($this_theme_id,this);"
		), $edit);
		$td2 = RCView::td(array('style'=>'text-align:center;width:20px;', 'class'=>'data'), $a2);
		$a3 = RCview::a(array(
			'title'   => $lang['folders_23'],
			'href'    => 'javascript:;',
			'onclick' => "deleteTheme($this_theme_id,1,this);"
		), $delete);
		$td3 = 	RCView::td(array('style'=>'text-align:center;width:20px;', 'class'=>'data'), $a3);
		$td4 = 	RCView::td(array('style'=>'text-align:center;width:90px;font-size:11px;color:#C00000;', 'class'=>'data'),
					($thisUtilCount < 1
						? 	RCView::span(array('style'=>'color:#999;'), $lang['survey_1061'])
						: 	$lang['survey_1058'] . " " .
							RCView::span(array('style'=>'font-weight:bold;'), $thisUtilCount) .
							" " . ($thisUtilCount == 1 ? $lang['survey_1060'] : $lang['survey_1059'])
					)
				);
		$html .= RCView::tr(array(), $td1 . $td4 . $td2 . $td3);
	}
	// Output instructions and table
	print RCView::div(array('style'=>''), $lang['survey_1057']);
	print RCView::table(array('class'=>'form_border', 'style'=>'margin:15px 0 5px;width:100%;', 'cellspacing'=>0), $html);
}

// Rename a user's saved theme
elseif ($_POST['action'] == 'rename')
{
	$sql = "update redcap_surveys_themes set theme_name = '".db_escape($_POST['theme_name'])."'
			where theme_id = " . $_POST['theme_id'];
	print (db_query($sql) ? '1' : '0');
}

// Delete a user's saved theme
elseif ($_POST['action'] == 'delete')
{
	$sql = "delete from redcap_surveys_themes where theme_id = " . $_POST['theme_id'];
	print (db_query($sql) ? '1' : '0');
}
