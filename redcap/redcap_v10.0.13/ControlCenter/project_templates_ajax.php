<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

// Default response
$response = '0';
$content  = '';
$title	  = '';
$db = new RedCapDB();


// Validate request first
if (!$super_user || !isset($_POST['action'])) exit($response);


// Get array of all existing templates
$templates = ProjectTemplates::getTemplateList();


// If project_id is provided, then retrieve the tempate info and project info
$tempTitle = $tempDescription = '';
if (isset($_POST['project_id']) && is_numeric($_POST['project_id']))
{
	// Get project info
	$projAttr = $db->getProject($_POST['project_id']);
	// Get project's current template info, if exists yet
	if (isset($templates[$_POST['project_id']])) {
		$tempTitle = $templates[$_POST['project_id']]['title'];
		$tempDescription = $templates[$_POST['project_id']]['description'];
	}
}


## Prompt with dialog to add/edit template
if ($_POST['action'] == 'prompt_addedit')
{
	// Set dialog title and content
	if (isset($_POST['project_id']) && is_numeric($_POST['project_id']))
	{
		// Check if project has been set as a template yet
		$isTemplate = (isset($templates[$_POST['project_id']]));
		$enabledRadioChecked  = ($isTemplate && !$templates[$_POST['project_id']]['enabled']) ? '' : 'checked';
		$disabledRadioChecked = ($isTemplate && !$templates[$_POST['project_id']]['enabled']) ? 'checked' : '';
		// Set dialog content/title
		$content = 	RCView::div(array('style'=>''), $lang['create_project_88']) .
					RCView::div(array('class'=>'chklist','style'=>'margin-top:15px;padding:2px 15px 15px;'),
						// If not a template yet, give link option to choose another project from drop-down to set as template
						(($isTemplate || (isset($_POST['hideChooseAnother']) && $_POST['hideChooseAnother'])) ?
							RCView::div(array('class'=>'space'), '') :
							RCView::div(array('style'=>'text-align:right;color:#555;'),
								"(" . RCView::a(array('href'=>'javascript:;','style'=>'text-decoration:underline;color:#800000;font-size:11px;','onclick'=>"projectTemplateAction('prompt_addedit');"),
								$lang['create_project_85']).")"
							)
						) .
						// Link to project
						RCView::div(array('style'=>''),
							RCView::b($lang['create_project_87']) .
							RCView::a(array('target'=>'_blank','href'=>APP_PATH_WEBROOT.'index.php?pid='.$_POST['project_id'],'style'=>'margin-left:5px;text-decoration:underline;'),
								// If lacks a title, add filler text
								($projAttr->app_title == '' ? $lang['create_project_82'] : RCView::escape($projAttr->app_title))
							)
						) .
						// Title row
						RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['create_project_73']) .
						RCView::div(array('style'=>''),
							RCView::text(array('id'=>'projTemplateTitle','class'=>'x-form-text x-form-field ','style'=>'width:70%;','value'=>$tempTitle))
						) .
						// Description row
						RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['create_project_69']) .
						RCView::div(array('style'=>''),
							RCView::textarea(array('id'=>'projTemplateDescription','class'=>'x-form-field notesbox','style'=>'width:90%;'), $tempDescription)
						) .
						// Enabled/disabled
						RCView::div(array('style'=>'margin-top:15px;'),
							RCView::b($lang['create_project_105']) .
							// Enabled
							RCView::div(array('style'=>''),
								RCView::radio(array('name'=>'projTemplateEnabled','value'=>'1','style'=>'',$enabledRadioChecked=>$enabledRadioChecked)) .
								RCView::img(array('src'=>'star.png')) .
								$lang['index_30'] .
								// Disabled
								RCView::radio(array('name'=>'projTemplateEnabled','value'=>'0','style'=>'margin-left:25px;',$disabledRadioChecked=>$disabledRadioChecked)) .
								RCView::img(array('src'=>'star_empty.png')) .
								$lang['global_23'] .
								RCView::span(array('style'=>'color:#777;margin-left:5px;font-size:11px;'), $lang['create_project_106'])
							)
						)
					);
		// Title (add or edit)
		$titleIcon = (!$isTemplate) ? "add.png" : "pencil.png";
		$titleText = (!$isTemplate) ? $lang['create_project_83'] : $lang['create_project_84'];
		$title = RCView::img(array('src'=>$titleIcon,'style'=>'vertical-align:middle')) . RCView::span(array('style'=>'vertical-align:middle'), $titleText);
	}
	// Choose project because it hasn't been selected yet
	else
	{
		// Collect all projects into an array for the drop-down list
		$projList = array(''=>'-- '.$lang['control_center_52'].' --');
		foreach ($db->getProjects() as $this_proj) {
			// Ignore deleted projects
			if ($this_proj->date_deleted != '') continue;
			// Check if project has been set as a template yet. If so, skip it.
			if (isset($templates[$this_proj->project_id])) continue;
			// If lacks a title, add filler text
			if ($this_proj->app_title == '') $this_proj->app_title = $lang['create_project_82'];
			// Add to array
			$projList[$this_proj->project_id] = $this_proj->app_title;
		}
		$content = 	RCView::div(array('style'=>'padding-bottom:10px;'),
						$lang['create_project_100']
					) .
					RCView::div(array('style'=>'padding-bottom:20px;'),
						RCView::select(array('style'=>'width:95%;','onchange'=>"if(this.value==''){return;} projectTemplateAction('prompt_addedit',this.value);"), $projList, '', 200)
					);
		$title = RCView::img(array('src'=>"add.png",'style'=>'vertical-align:middle')) .
				 RCView::span(array('style'=>'vertical-align:middle'), $lang['create_project_83']);
	}
	// Set response text
	$response = json_encode_rc(array('content'=>$content, 'title'=>$title));
}


## Prompt with dialog to delete template
if ($_POST['action'] == 'prompt_delete' && isset($_POST['project_id']) && is_numeric($_POST['project_id']))
{
	$content =  RCView::div(array('style'=>''), $lang['create_project_95']) .
				RCView::div(array('class'=>'chklist','style'=>'margin-top:15px;'),
					// Link to project
					RCView::div(array('style'=>''),
						RCView::b($lang['create_project_87']) .
						RCView::a(array('target'=>'_blank','href'=>APP_PATH_WEBROOT.'index.php?pid='.$_POST['project_id'],'style'=>'margin-left:5px;text-decoration:underline;'), RCView::escape($projAttr->app_title))
					) .
					// Title row
					RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['create_project_73']) .
					RCView::div(array('style'=>''), $tempTitle) .
					// Description row
					RCView::div(array('style'=>'margin-top:15px;font-weight:bold;'), $lang['create_project_69']) .
					RCView::div(array('style'=>''), $tempDescription)
				);
	$title = RCView::img(array('src'=>'cross.png','style'=>'vertical-align:middle')) .
			 RCView::span(array('style'=>'vertical-align:middle'), $lang['create_project_93']);
	// Set response text
	$response = json_encode_rc(array('content'=>$content, 'title'=>$title));
	
}


## Add new template OR edit existing template
elseif ($_POST['action'] == 'addedit' && isset($_POST['project_id']) && is_numeric($_POST['project_id']))
{
	// Clean values
	$_POST['title'] = trim(strip_tags(html_entity_decode($_POST['title'], ENT_QUOTES)));
	$_POST['description'] = trim(strip_tags(html_entity_decode($_POST['description'], ENT_QUOTES)));
	$_POST['enabled'] = ($_POST['enabled'] == '1') ? '1' : '0';
	// Add/edit in table
	$sql = "insert into redcap_projects_templates (project_id, title, description, enabled) values
			({$_POST['project_id']}, '".db_escape($_POST['title'])."', '".db_escape($_POST['description'])."', {$_POST['enabled']})
			on duplicate key update title = '".db_escape($_POST['title'])."', description = '".db_escape($_POST['description'])."',
			enabled = {$_POST['enabled']}";
	if (db_query($sql)) {
		// Set dialog content/title
		if (db_affected_rows() != 1) {
			$content = $lang['create_project_98']." ".$lang['create_project_97'];
			$titleTxt = $lang['create_project_101'];
		} else {
			$content = $lang['create_project_99']." ".$lang['create_project_97'];
			$titleTxt = $lang['create_project_86'];
		}
		$title = RCView::img(array('src'=>'tick.png','style'=>'vertical-align:middle')) .
				 RCView::span(array('style'=>'vertical-align:middle'), $titleTxt);
		// Set response text
		$response = json_encode_rc(array('content'=>$content, 'title'=>$title));	
	}
}


## Remove template
elseif ($_POST['action'] == 'delete' && isset($_POST['project_id']) && is_numeric($_POST['project_id']))
{
	// Remove from table
	$sql = "delete from redcap_projects_templates where project_id = ".$_POST['project_id'];
	if (db_query($sql)) {
		// Set dialog content/title
		$content = $lang['create_project_96']." ".$lang['create_project_97'];
		$title = RCView::img(array('src'=>'tick.png','style'=>'vertical-align:middle')) .
				 RCView::span(array('style'=>'vertical-align:middle'), $lang['create_project_102']);
		// Set response text
		$response = json_encode_rc(array('content'=>$content, 'title'=>$title));
	}
}


// Output response
print $response;