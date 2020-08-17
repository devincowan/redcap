<?php





/**
 * RENDER TABS
 */
$projectSetupTabs = array();
$projectSetupTabs["index.php"] = array("fa"=>"fas fa-home", "label"=>$lang['bottom_44']);
if ($user_rights['design']) $projectSetupTabs["ProjectSetup/index.php"] = array("fa"=>"fas fa-tasks", "label"=>$lang['app_17']);
// Page-sensitive tabs that appear
if (PAGE == "UserRights/index.php" || PAGE == "DataAccessGroupsController:index")
{
	if ($user_rights['user_rights']) {
		$projectSetupTabs["UserRights/index.php"] = array("fa"=>"fas fa-user", "label"=>$lang['app_05']);
	}
	//
	if ($user_rights['data_access_groups']) {
		$projectSetupTabs["DataAccessGroupsController:index"] = array("fa"=>"fas fa-users", "label"=>$lang['global_22']);
	}
}
elseif (PAGE == "Surveys/edit_info.php" || PAGE == "Surveys/create_survey.php")
{
	$projectSetupTabs["Design/online_designer.php"] = array("fa"=>"fas fa-edit", "label"=>$lang['design_25']);
	if (PAGE == "Surveys/edit_info.php") {
		$projectSetupTabs[PAGE] = array("fa"=>"fas fa-pencil-alt", "label"=>$lang['setup_05']);
	} else {
		$projectSetupTabs[PAGE] = array("fa"=>"fas fa-plus", "label"=>$lang['setup_06']);
	}
}
elseif (PAGE == "ProjectGeneral/edit_project_settings.php")
{
	$projectSetupTabs["ProjectGeneral/edit_project_settings.php"] = array("icon"=>"pencil.png", "label"=>$lang['edit_project_38']);
}
elseif (PAGE == "Design/online_designer.php" || PAGE == "Design/data_dictionary_upload.php" ||
    PAGE == "SharedLibrary/index.php" || PAGE == "Design/data_dictionary_codebook.php")
{
    if ($user_rights['design']) $projectSetupTabs["Design/online_designer.php"] = array("fa"=>"fas fa-edit", "label"=>$lang['design_25']);
    if ($user_rights['design']) $projectSetupTabs["Design/data_dictionary_upload.php"] = array("icon"=>"xls2.png", "label"=>$lang['global_09']);
    $projectSetupTabs["Design/data_dictionary_codebook.php"] = array("fa"=>"fas fa-book fs12", "label"=>$lang['design_482']);
	if ($shared_library_enabled && PAGE == "SharedLibrary/index.php" && $user_rights['design']) {
		$projectSetupTabs["SharedLibrary/index.php"] = array("icon"=>"blogs_arrow.png", "label"=>$lang['design_37']);
	}
}
elseif (PAGE == "ExternalLinks/index.php") {
	$projectSetupTabs["ExternalLinks/index.php"] = array("fa"=>"fas fa-bookmark", "label"=>$lang['app_19']);
}
// Default tabs
else {
	if ($user_rights['design']) {
		$projectSetupTabs["ProjectSetup/other_functionality.php"] = array("fa"=>"fas fa-folder-plus", "label"=>$lang['setup_68']);
		$projectSetupTabs["ProjectSetup/project_revision_history.php"] = array("icon"=>"history_back.png", "label"=>$lang['app_18']);
	}
}


// Display any warnings for Index or Setup pages
if (PAGE == "index.php" || PAGE == "ProjectSetup/index.php" || PAGE == "ProjectSetup/other_functionality.php")
{
	//Custom index page header note
	if (trim($custom_index_page_note) != '') {
		print "<div class='green notranslate' style='font-size:11px;'>" . nl2br(decode_filter_tags($custom_index_page_note)) . "</div>";
	}

	//If system is offline, give message to super users that system is currently offline
	if ($system_offline && $super_user)
	{
		print  "<div class='red'>
					{$lang['index_38']}
					<a href='".APP_PATH_WEBROOT."ControlCenter/general_settings.php'
						style='text-decoration:underline;font-family:verdana;'>{$lang['global_07']}</a>".$lang['period']."
				</div>";
	}

	//If project is offline, give message to super users that project is currently offline
	if (!$online_offline && $super_user) {
		print  "<div class='red'>
					{$lang['index_48']}
					<a href='".APP_PATH_WEBROOT."ControlCenter/edit_project.php?project=$project_id'
						style='text-decoration:underline;font-family:verdana;'>{$lang['global_07']}</a>".$lang['period']."
				</div>";
	}

	// Give warning if beginning survey is used with DDE enabled
	if ($double_data_entry && isset($Proj->forms[$Proj->firstForm]['survey_id']))
	{
		print  "<div class='red'>
					<b>{$lang['global_01']}{$lang['colon']}</b><br>
					{$lang['index_71']}
				</div>";
	}

}


// Display the "Edit project settings link"?
$showEditProjSettings = (UserRights::isSuperUserNotImpersonator() && in_array(PAGE, array('index.php', 'ProjectSetup/index.php', 'ProjectSetup/other_functionality.php', 'ProjectSetup/project_revision_history.php')));

?>
<div id="sub-nav" class="project_setup_tabs d-none d-sm-block">
	<ul>
		<?php
        foreach ($projectSetupTabs as $this_url=>$this_set) {
			// Make sure page gets added to create_survey.php for context
			$this_url_append = "";
			if ($this_url == "Surveys/create_survey.php" && isset($_GET['page'])) {
				$this_url_append = "&page=".$_GET['page']."&view=showform";
			}
        ?>
		<li <?php if ($this_url == PAGE) echo 'class="active"'?>>
            <?php
            $this_url_base = (strpos($this_url, ":") !== false) ? "index.php?route=".$this_url."&" : $this_url . "?";
            ?>
			<a href="<?php echo APP_PATH_WEBROOT . $this_url_base . "pid=" . PROJECT_ID . $this_url_append ?>" style="font-size:13px;color:#393733;padding:7px 9px;">
				<?php if (isset($this_set['fa'])) { ?>
					<i class="<?php echo $this_set['fa'] ?>"></i>
				<?php } elseif (empty($this_set['icon'])) { ?>
					<img src="<?php echo APP_PATH_IMAGES ?>spacer.gif" style="height:16px;width:1px;margin-bottom:-1px;">
				<?php } else { ?>
					<img src="<?php echo APP_PATH_IMAGES . $this_set['icon'] ?>" style="height:16px;width:16px;margin-bottom:-1px;">
				<?php } ?>
				<?php echo $this_set['label'] ?>
			</a>
		</li>
		<?php } ?>
		<?php if ($showEditProjSettings) { ?>
		<li class="d-none d-lg-block"style="background-image:none;border:0;">
			<a href="<?php echo APP_PATH_WEBROOT . "ControlCenter/edit_project.php?project=" . PROJECT_ID ?>" style="border:0;background-image:none;font-weight:normal;font-size:11px;text-decoration:underline;color:#000066;padding:8px 9px 2px 12px;">
				<i class="fas fa-cog" style="margin-right:2px;"></i><?php print $lang['project_settings_49'] ?></a>
		</li>
		<?php } ?>
	</ul>
</div>

<div style="clear:both;"></div>
<div class="btn-group d-block d-md-none" role="group" style="margin-bottom:10px;">
	<button type="button" id="psBtnGroupDrop1" class="btn btn-defaultrc dropdown-toggle active" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<?php
		foreach ($projectSetupTabs as $this_url=>$this_set) {
			if ($this_url != PAGE) continue;
			if (isset($this_set['fa'])) {
				print '<i class="'.$this_set['fa'].'"></i> ' . $this_set['label'];
			} else {
				print '<img src="'. APP_PATH_IMAGES . $this_set['icon'] .'" style="height:16px;width:16px;"> ' . $this_set['label'];
			}
		}
		?>
	</button>
	
	<div class="dropdown-menu" aria-labelledby="psBtnGroupDrop1">
		<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT_PARENT ?>index.php?action=myprojects" style="font-size:15px;color:#393733;padding:7px 9px;">
			<i class="far fa-list-alt" style='text-indent:0;'></i> <?php print $lang['bottom_03'] ?></a>
		    <?php
            foreach ($projectSetupTabs as $this_url=>$this_set) {
                // Make sure page gets added to create_survey.php for context
				$this_url_append = "";
		        if ($this_url == "Surveys/create_survey.php" && isset($_GET['page'])) {
		            $this_url_append = "&page=".$_GET['page']."&view=showform";;
				}
		    ?>
			<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT . $this_url . "?pid=" . PROJECT_ID . $this_url_append ?>" style="font-size:15px;color:#393733;padding:7px 9px;">
				<?php if (isset($this_set['fa'])) { ?>
					<i class="<?php echo $this_set['fa'] ?>"></i>
				<?php } elseif (empty($this_set['icon'])) { ?>
					<img src="<?php echo APP_PATH_IMAGES ?>spacer.gif" style="height:16px;width:1px;">
				<?php } else { ?>
					<img src="<?php echo APP_PATH_IMAGES . $this_set['icon'] ?>" style="height:16px;width:16px;">
				<?php } ?>
				<?php echo $this_set['label'] ?>
			</a>
		<?php } ?>
		<?php if ($showEditProjSettings) { ?>
			<a class="dropdown-item" href="<?php echo APP_PATH_WEBROOT . "ControlCenter/edit_project.php?project=" . PROJECT_ID ?>" style="font-size:15px;color:#393733;padding:7px 9px;">
				<i class="fas fa-cog"></i> <?php print $lang['project_settings_49'] ?></a>
		<?php } ?>
	</div>
</div>
<?php

// If project is in Analysis/Cleanup mode, then give notice if data is locked or not
if ($status == '2' && in_array(PAGE, array('index.php', 'ProjectSetup/index.php', 'ProjectSetup/other_functionality.php')))
{
	$data_locked_text = ($data_locked == '0' ? '<i class="fas fa-edit"></i> '.$lang['bottom_101'] : '<i class="fas fa-lock"></i> '.$lang['bottom_102']);
	$data_locked_color = ($data_locked == '0' ? '#05a005' : '#C00000');
    print  "<div class='yellow mt-3 mb-4'>
                <div>
                	<i class=\"fas fa-exclamation-circle\"></i> {$lang['bottom_99']}
                	<a href='javascript:;' data-toggle=\"popover\" data-placement=\"bottom\" data-trigger=\"hover\" data-content=\"".htmlspecialchars($lang['bottom_105'], ENT_QUOTES)."\" data-title=\"".htmlspecialchars($lang['bottom_106'], ENT_QUOTES)."\">{$lang['bottom_106']}</a>
				</div>
                <div class='mt-3'>
                    <span class='boldish'>{$lang['bottom_100']}</span>
                    <span class='font-weight-bold mx-2' style='color:$data_locked_color;'>$data_locked_text</span>                    
                    <button id='modify-data-locked' class='btn btn-xs btn-defaultrc'>{$lang['design_169']}</button>
                </div>
            </div>";
	?>
    <script type="text/javascript">
        $(function(){
            $('[data-toggle="popover"]').popover();
            $('#modify-data-locked').click(function(){
                simpleDialog('<?=db_escape($lang['bottom_108'])?>', '<?=db_escape($lang['bottom_107'])?>', null, null, null, '<?=db_escape($lang['global_53'])?>', function(){
                    $.post(app_path_webroot+'ProjectGeneral/change_project_status.php?pid='+pid,{ data_locked: '<?=($data_locked == '0' ? '1' : '0')?>' },function(data){
                        window.location.reload();
                    });
                }, '<?=($data_locked == '0' ? db_escape($lang['bottom_110']) : db_escape($lang['bottom_109']))?>');
            });
        });
    </script>
	<?php
}