<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

$user = User::getUserInfo(USERID);


// re-sort drag and drop folders
if(isset($_POST['re_sort']))
{
	$data = isset($_POST['data']) ? $_POST['data'] : '';
	if($data)
	{
		ProjectFolders::reSort($user, $data);
		// Logging
		Logging::logEvent("","redcap_folders","MANAGE",'',"","Resort project folders");
	}
}


// handle folder collapse/expand
if(isset($_GET['toggle_folder_collapse']))
{
	$folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : 0;
	$folder_id = $folder_id === "all" ? "all" : (int)$folder_id;

	if($folder_id)
	{
		ProjectFolders::toggleFolderCollapse($user, $folder_id);
	}
}


// handle project search persistence
if(isset($_POST['toggle_projectsearchpersist']))
{
	$persist = isset($_POST['persist']) ? (int)$_POST['persist'] : 0;
	UIState::saveUIStateValue(null, "my_projects", "search_persist", $persist === 0 ? 0 : 1);
	if ($persist) {
		// Set search value
		$content = isset($_POST['content']) ? trim($_POST['content']) : "";
		UIState::saveUIStateValue(null, "my_projects", "search_string", $content);
	}
	else {
		// Clear stored search value
		UIState::saveUIStateValue(null, "my_projects", "search_string", "");
	}
}

if(isset($_POST['persist_projectsearch'])) {
	$content = isset($_POST['content']) ? trim($_POST['content']) : "";
	UIState::saveUIStateValue(null, "my_projects", "search_string", $content);
}

// handle check all projects checkbox
if(isset($_POST['check_all_projects']))
{
	$folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;

	if($folder_id)
	{
		$ids = explode(',', $_POST['ids']);

		if(count($ids))
		{
			$checkAll = (isset($_POST['checkAll']) && $_POST['checkAll'] == 'true');

			foreach($ids as $project_id)
			{
				$project_id = (int)$project_id;
				if($checkAll)
				{
					if(!ProjectFolders::projectFolderExists($user, $folder_id, $project_id))
					{
						ProjectFolders::createProjectFolder($user, $folder_id, $project_id);
					}
				}
				else
				{
					ProjectFolders::deleteProjectFolder($user, $folder_id, $project_id);
				}
			}
			// Logging
			Logging::logEvent("","redcap_folders","MANAGE","","folder_id in (".implode(",", $ids).")",($checkAll ? "Assign multiple projects to project folder" :  "Remove multiple projects from project folder"));
		}
	}
}


// get project folders
if(isset($_GET['get_project_folders']))
{
	$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
	$projects = '';

	if($folder_id)
	{
		$projects = ProjectFolders::getProjects($user, $folder_id);

		if(count($projects))
		{
			$ids = implode(',', array_keys($projects));
			$input = RCView::input(
				array(
					'type'    => 'checkbox',
					'id'      => 'checkAll',
					'onclick' => "checkAllProjects($folder_id, '$ids');"
				)
			);

			$header = RCView::tr(array(),
				RCView::td(array('class'=>'header', 'style'=>'text-align:center;width:20px;'), $input) .
				RCView::td(array('class'=>'header'),
					$lang['bottom_03'].$lang['colon']." ".
					RCView::span(array('style'=>'font-weight:normal;padding-left:2px;'), $lang['folders_06'])
				)
			);

			$projects = RCView::table(array('class'=>'form_border', 'style'=>'width:100%;'),
				$header . ProjectFolders::toCheckboxTRs($projects)
			);
		}
		else
		{
			$projects = '';
		}
	}
	print $projects;
}


// get select folders
if(isset($_GET['get_select_folders']))
{
	$selected = isset($_GET['selected']) ? (int)$_GET['selected'] : 0;

	$select = RCView::select(
		array(
			'id'=>'folder_id',
			'class'=>'x-form-text x-form-field',
			'style'=>'margin-top:8px;max-width:200px;'
		),
		ProjectFolders::forSelectOpts(ProjectFolders::getAll($user)),
		$selected,
		200
	);
	print $select;
}


// save modified folder
if(isset($_POST['save_folder']))
{
	$folder_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

	$name = strip_tags(label_decode(isset($_POST['name']) ? $_POST['name'] : ''));

	$fg = isset($_POST['fg']) ? $_POST['fg'] : '';
	$fg = substr(preg_replace('/[^a-fA-F0-9]/', '', $fg), 0, 6);

	$bg = isset($_POST['bg']) ? $_POST['bg'] : '';
	$bg = substr(preg_replace('/[^a-fA-F0-9]/', '', $bg), 0, 6);

	ProjectFolders::update($user, $folder_id, $name, $fg, $bg);

	// Logging
	Logging::logEvent("","redcap_folders","MANAGE",$folder_id,"folder_id = $folder_id","Modify project folder");
}


// load edit folder popup
if(isset($_GET['edit_folder']))
{
	$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
	$created = (isset($_GET['created']) && $_GET['created'] == '1');

	if($folder_id)
	{
		$folder = ProjectFolders::get($user, $folder_id);

		$name_field = RCView::input(array(
			'class' => 'x-form-text x-form-field',
			'style' => 'font-size:13px;width:250px;',
			'id'    => 'edit_folder_name',
			'value' => $folder['name'],
			'maxlength'  => 64,
			'onkeyup'  => "$('#sample_folder').val(this.value);"
		));

		$name_label_td = RCView::td(array('style'=>'padding-bottom:10px;'), RCView::b($lang['folders_07']));
		$name_field_td = RCView::td(array('style'=>'padding-bottom:10px;'), $name_field);
		$name_tr = RCView::tr(array(), $name_label_td . $name_field_td);

		$fg_field = RCView::input(array(
			'type'     => 'text',
			'id'       => 'edit_folder_fg',
			'size'     => 4
		));

		$fg_label_td = RCView::td(array(), RCView::b($lang['folders_08']));
		$fg_field_td = RCView::td(array('style'=>'padding-bottom:10px;'), $fg_field);
		$fg_tr = RCView::tr(array(), $fg_label_td . $fg_field_td);

		$bg_field = RCView::input(array(
			'type'     => 'text',
			'id'       => 'edit_folder_bg',
			'size'     => 4
		));

		$bg_label_td = RCView::td(array('style'=>'width:120px;'), RCView::b($lang['folders_09']));
		$bg_field_td = RCView::td(array('style'=>'padding-bottom:10px;'), $bg_field);
		$bg_tr = RCView::tr(array(), $bg_label_td . $bg_field_td);

		$sample_field = RCView::input(array(
			'style'    => 'font-size:12px; font-weight:bold; padding:5px 3px; width:270px;',
			'id'       => 'sample_folder',
			'value'    => $folder['name'],
			'readonly' => 'readonly'
		));

		$sample_tr = RCView::tr(array(),
						RCView::td(array('colspan'=>2),
							RCView::div(array('style'=>'margin:15px 0 5px;'),
								$lang['folders_25']
							) .
							$sample_field
						)
					);

		$folder_id_field = RCView::input(array('type'=>'hidden', 'id'=>'edit_folder_id', 'value'=>$folder_id));

		$edit_folder = 	RCView::div(array('style'=>'margin-bottom:15px;'),
							($created ? $lang['folders_30'] : $lang['folders_29'])
						) .
						RCView::table(array(), $name_tr . $fg_tr . $bg_tr . $sample_tr . $folder_id_field);
		?>
		simpleDialog('<?php echo js_escape($edit_folder); ?>', '<?php echo js_escape($lang['folders_10']); ?>', 'edit_folder_popup', 450, '', '<?php echo js_escape($lang['global_53']); ?>', 'saveFolder();', '<?php echo js_escape($lang['folders_11']); ?>');
		initSpectrum('#edit_folder_fg', '<?php echo $folder["foreground"]; ?>');
		initSpectrum('#edit_folder_bg', '<?php echo $folder["background"]; ?>');
		updateFolderColors();
		<?php
	}
}


// handle hide assigned checkbox
if(isset($_POST['hide_assigned']))
{
	$_SESSION['hide_assigned'] = isset($_POST['hide']) ? (int)$_POST['hide'] : 0;
}


// assign project to project folder
if(isset($_POST['toggle_project_folder']))
{
	$folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
	$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
	// Get rights for this user to ensure they have access to the project
	$rights = UserRights::getPrivileges($project_id, USERID);
	if (empty($rights)) exit("0");
	// Assign/remove project from folder
	if($folder_id && $project_id)
	{
		$assigned = ProjectFolders::toggleProjectFolder($user, $folder_id, $project_id);
		// Logging
		Logging::logEvent("","redcap_folders","MANAGE",$folder_id,"folder_id = $folder_id",($assigned ? "Assign project to project folder" : "Remove project from project folder"));
	}
}


// delete folder
if(isset($_POST['del_folder']))
{
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

	if($id)
	{
		ProjectFolders::delete($user, $id);
		// Logging
		Logging::logEvent("","redcap_folders","MANAGE",$folder_id,"folder_id = $folder_id","Delete project folder");
	}
}


// get folders
if(isset($_GET['get_folders']))
{
	$folders = ProjectFolders::getAll($user);

	// Add "archived"/hidden projects as a built-in folder
	$folders[] = array(
		'name'       => $lang['folders_31'],
		'collapsed'  => 1,
		'foreground' => 'ffffff',
		'background' => '000000',
		'position'   => max(array_keys($folders))+1,
        'noedit'       => 1
	);

	if(count($folders))
	{
		$folders = RCView::table(array('id'=>'folders_list', 'class'=>'form_border', 'style'=>'width:100%;'),
			ProjectFolders::toTRs($folders)
		);
	}
	else
	{
		$folders = RCView::p(array('style'=>'color:#777;margin:15px 5px;font-weight:normal;'), $lang['folders_13']);
	}
	print $folders;
}


// add new folder
if(isset($_POST['new_folder']))
{
	$name = strip_tags(label_decode(isset($_POST['name']) ? $_POST['name'] : ''));

	$exists = ProjectFolders::alreadyExists($user, $name);

	$folder_id = 0;

	if(strlen($name) && !$exists)
	{
		$folder_id = ProjectFolders::create($user, $name);
		// Logging
		Logging::logEvent("","redcap_folders","MANAGE",$folder_id,"folder_id = $folder_id","Create project folder");
	}


	if(!strlen($name))
	{
		print json_encode ( array (
			'status' => 0,
			'msg'   => js_escape($lang['folders_14']),
		) );
	}
    elseif($exists)
	{
		print json_encode ( array (
			'status' => 0,
			'msg'   => js_escape($lang['folders_15']),
		) );
	}
	else
	{
		if($folder_id) {
			print json_encode ( array (
				'status' => 1,
				'msg'   => js_escape($folder_id),
			) );
		} else {
			print json_encode ( array (
				'status' => 1,
				'msg'   => -1,
			) );
		}
	}
}
