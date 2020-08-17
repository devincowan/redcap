<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
// Check if any notices need to be displayed regarding Draft Mode
include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";

// Video link
print 	RCView::div(array('style'=>'max-width:800px;text-align:right;margin:5px 0 15px;'),
			'<i class="fas fa-film"></i> ' .
			RCView::a(array('href'=>'javascript:;','style'=>'vertical-align:middle;font-size:12px;text-decoration:underline;font-weight:normal;','onclick'=>"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=intro_instrument_dev.mp4&referer=".SERVER_NAME."&title=".js_escape($lang['training_res_101'])."','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"), $lang['design_02'])
		);


// If data dictionary upload was successful, give user a confirmation
if (isset($_GET['upload_success']))
{
	// Give user confirmation of successful changes to project
	print  "<p></p>
			<div class='green' style='margin:30px 0;padding-bottom:15px;'>
				<img src='".APP_PATH_IMAGES."accept.png'>
				<b>" . ($status == 0 ? $lang['design_03'] : $lang['design_730']) . "</b><br><br>
				{$lang['design_04']}
				" . ($status == 0 ? "" : "<br><br>
										  <b>" . $lang['design_05'] . "</b> ".$lang['design_167']."
										  <a style='text-decoration:underline;font-family:verdana;' href='".APP_PATH_WEBROOT."Design/project_modifications.php?pid=$project_id&ref='+page>".
										  $lang['design_18']."</a> ".$lang['design_168'])
				. "
			</div>";
	// Link to return to previous page
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}




// Instructions (but not while editing a form)
if (!isset($_GET['page']))
{
	print  "<p>{$lang['design_07']}</p>";
}



//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";




/**
 * UPLOAD DATA DICTIONARY
 */

#Set official upload directory
$upload_dir = APP_PATH_TEMP;
if (!is_writeable($upload_dir)) {
	print "<br><br><div class='red'>
		<img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_01']}:</b><br>
		{$lang['design_106']} <b>$upload_dir</b> {$lang['design_107']}</div>";
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit();
}

# Define the sheet name. Here it automatically give it the name of the file, but is limited to the first 31 characters. Use if as default.
$importsheetname = 'Sheet1';

// First page
if (!isset($_POST['submit']) && !isset($_POST['commit']))
{
	//Print instructions
	print  "<p>
				{$lang['design_109']}
			</p>
			<p>
				<font color=#800000>{$lang['design_110']}</font>
				{$lang['design_111']}
				{$lang['design_259']}
				<b>{$lang['design_692']}</b> <font color=#800000>{$lang['design_691']}</font>
			</p>
			<p>
				<b>{$lang['design_261']}</b><br>
				{$lang['design_260']}
				<a href='" . APP_PATH_WEBROOT . "Design/data_dictionary_demo_download.php' style='text-decoration:underline;' onclick=\"
					return alert('".remBr($lang['design_113'])."\\n\\n".remBr($lang['design_114'])."');
				\">{$lang['design_115']}</a>, {$lang['design_116']}
				<a onclick=\"popupvid('redcap_data_dictionary02.mp4','The Data Dictionary');\" href=\"javascript:;\"
					style=\"text-decoration:underline;\">{$lang['design_117']}</a>{$lang['period']}
				{$lang['design_258']} <a style='text-decoration:underline;' href='javascript:;' onclick=\"window.open('".APP_PATH_WEBROOT_PARENT."index.php?action=help#ss49','myWin','width=850, height=600, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\">{$lang['bottom_27']}</a>{$lang['period']}
			</p>
			<div style='padding-top:15px;'>
				<b>{$lang['design_118']}</b><br>
				<div style='padding:0px 0 5px 15px;line-height:1.5em;'>
					1.) <a href='javascript:;' onclick='downloadDD(0,{$Proj->formsFromLibrary()});'
						style='text-decoration:underline;color:green;'>{$lang['design_119']} {$lang['global_09']}</a>
						<img src='".APP_PATH_IMAGES."xls.gif'>" .
						(($status > 0 && $draft_mode > 0)
							? "&nbsp;&nbsp;<b style='font-size:13px;'>{$lang['global_46']}</b>&nbsp;&nbsp;
								<a href='javascript:;' onclick='downloadDD(1,{$Proj->formsFromLibrary()});'
									style='text-decoration:underline;color:green;'>{$lang['design_121']} {$lang['global_09']} {$lang['design_122']}</a>
								<img src='".APP_PATH_IMAGES."xls.gif'>"
							: ""
						) .
						"<br>
					2.) {$lang['design_123']} {$lang['design_262']} <a style='text-decoration:underline;' href='javascript:;' onclick=\"window.open('".APP_PATH_WEBROOT_PARENT."index.php?action=help#ss49','myWin','width=850, height=600, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\">{$lang['bottom_27']}</a> {$lang['design_263']}<br>
					3.) {$lang['design_124']}<br>
					4.) {$lang['design_125']}
				</div>
			</div>";

	// Display form for user to upload a file (unless user just uploaded it)
	print  "<div class='round' style='background-color:#EFF6E8;max-width:700px;margin:20px 0;padding:15px 25px;border:1px solid #A5CC7A;'>";
	if ($draft_mode == 0 && $status > 0)
	{
		// If in production but not in Draft Mode yet, give notice that cannot yet upload DD
		print  RCView::b($lang['global_03'].$lang['colon']). RCView::SP . $lang['design_370'];
	}
	elseif ($draft_mode == 2 && $status > 0)
	{
		// If in production and drafted changes are awaiting review by admin
		print  RCView::b($lang['global_03'].$lang['colon']). RCView::SP . $lang['design_632'];
	}
	else
	{
		// Display file uplaod form
		print  "<form action='{$_SERVER['REQUEST_URI']}' method='POST' name='form' enctype='multipart/form-data'>
					<div id='uploadmain'>
						<div style='padding-bottom:5px;font-size:13px;'>
							{$lang['design_126']}
						</div>
						<div style='padding-bottom:10px;'>
							{$lang['data_import_tool_187']}&nbsp;
							<select name='date_format' class='x-form-text x-form-field' style='font-family:tahoma;'>
								<option value='MDY' ".((!isset($_POST['date_format']) && DateTimeRC::get_user_format_base() != 'DMY') || (isset($_POST['date_format']) && $_POST['date_format'] == 'MDY') ? "selected" : "").">MM/DD/YYYY {$lang['global_47']} YYYY-MM-DD</option>
								<option value='DMY' ".((!isset($_POST['date_format']) && DateTimeRC::get_user_format_base() == 'DMY') || (isset($_POST['date_format']) && $_POST['date_format'] == 'DMY') ? "selected" : "").">DD/MM/YYYY {$lang['global_47']} YYYY-MM-DD</option>
							</select>
						</div>
						<input type='file' name='uploadedfile' size='50'
							onchange=\"document.forms['form'].elements['filepath'].value = document.forms['form'].elements['uploadedfile'].value;\">
						<input type='hidden' name='filepath' value=''>
						<div style='padding-top:5px;'>
							<input type='submit' id='submit' name='submit' value='{$lang['design_127']}' onclick=\"
								if (document.forms['form'].elements['uploadedfile'].value.length < 1) {
									alert('".js_escape($lang['design_128'])."');
									return false;
								}
								var file_ext = getfileextension(trim(document.forms['form'].elements['uploadedfile'].value.toLowerCase()));
								if (file_ext != 'csv') {
									$('#filetype_mismatch_div').dialog({ bgiframe: true, modal: true, width: 530, zIndex: 3999, buttons: {
										Close: function() { $(this).dialog('close'); }
									}});
									return false;
								}
								document.getElementById('uploadmain').style.display='none';
								document.getElementById('progress').style.display='block';\">
						</div>
					</div>
					<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
						<table cellpadding=10><tr>
						<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
						<td valign=top style='padding-top:20px;'>
							<b>{$lang['design_129']}</b><br>{$lang['design_130']}<br>{$lang['design_131']}</td>
						</tr></table>
					</div>
				</form>";
	}
	print  "</div><br><br>";

	// Div for displaying popup dialog for file extension mismatch (i.e. if XLS or other)
	?>
	<div id="filetype_mismatch_div" title="Only CSV files can be uploaded" style="display:none;">
		<p>
			<?php echo $lang['design_132'] ?>
			<a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
				style="text-decoration:underline;"><?php echo $lang['data_import_tool_116'] ?></a>
			<?php echo $lang['design_134'] ?>
		</p>
	</div>
	<?php

}



/**
 * Excel file was uploaded
 */
if (isset($_POST['submit']))
{
	print "<div style='clear:both;max-width:700px;'>";

	# Save the file details that are passed to the page in the _FILES array
	foreach ($_FILES as $fn=>$f) {
		$$fn = $f;
		foreach ($f as $k=>$v) {
			$name = $fn . "_" . $k;
			$$name = $v;
		}
	}

	# If filename is blank, reload the page
	if ($uploadedfile_name == "") {
		redirect(PAGE_FULL."?pid=$project_id");
		exit;
	}

	// Get field extension
	$filetype = strtolower(substr($uploadedfile_name,strrpos($uploadedfile_name,".")+1,strlen($uploadedfile_name)));

	// If not CSV, print message, exit
	if ($filetype != "csv"){
		// If uploaded as XLSX or CSV, tell user to save as XLS and re-uploade
		$msg = ($filetype == "xls" || $filetype == "xlsx") ? $lang['design_135'] : $lang['design_136'];
		// Display error message
		print  '<div class="red" style="margin:30px 0;">
					<img src="'.APP_PATH_IMAGES.'exclamation.png"> <b>'.$lang['global_01'].':</b><br>'.$msg.'
				</div>';
		// Link to go back
		renderPrevPageBtn(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	# If Excel file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
	$uploadedfile_name = date('YmdHis') . "_pid" . PROJECT_ID . "_uploaddatadict." . $filetype;
	$uploadedfile_name = str_replace("\\", "\\\\", $upload_dir . $uploadedfile_name);

	# If moving or copying the uploaded file fails, print error message and exit
	if (!move_uploaded_file($uploadedfile_tmp_name, $uploadedfile_name))
	{
		if (!copy($uploadedfile_tmp_name, $uploadedfile_name))
		{
			print '<p><br><table width=100%><tr><td class="comp_new_error"><font color=#800000><b>' .
				 "{$lang['design_137']}</b><br>{$lang['design_138']} <span class='notranslate'>$project_contact_name " .
				 "{$lang['global_15']} <a href=\"mailto:$project_contact_email\">$project_contact_email</a></span>
				 {$lang['design_140']}</b></font></td></tr></table>";
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}
	}
	
	// Process uploaded Excel file
	$dictionary_array = Design::excel_to_array($uploadedfile_name);

	// If DD returns false, then it's because the legacy column Field Units exists. If so, tell user to remove it.
	// It is no longer supported but old values defined prior to 4.0 will be preserved.
	if ($dictionary_array === false)
	{
		print 	RCView::div(array('class'=>'red','style'=>'margin:20px;'),
					RCView::img(array('src'=>'exclamation.png')) .
					RCView::b($lang['design_220']) . RCView::SP . $lang['design_219']
				);
		// Link to go back
		renderPrevPageBtn(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	// If an empty DD is uploaded, give an error
	if (empty($dictionary_array['A']))
	{
		print 	RCView::div(array('class'=>'red','style'=>'margin:20px;'),
					RCView::img(array('src'=>'exclamation.png')) .
					RCView::b($lang['global_01'].$lang['colon']) . RCView::SP . $lang['design_388']
				);
		// Link to go back
		renderPrevPageBtn(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	// Return warnings and errors from file (and fix any correctable errors)
	list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

	// If errors exist, display them and stop
	if (count($errors_array) > 0) {

		// Display RETURN arrow
		renderPrevPageBtn(PAGE);

		//Display errors
		Design::renderErrors($errors_array);

		//Display warnings
		Design::renderWarnings($warnings_array);

		print "<br>";
		renderPrevPageBtn(PAGE);

		// Delete uploaded file from web server since it is not usable due to errors
		unlink($uploadedfile_name);

	// If no errors exist, give user the option to commit changes
	} else {
		// Display confirmation that file was uploaded successfully without errors
		print  "<div class='darkgreen' style='margin:30px 0;'>
					<img src='".APP_PATH_IMAGES."accept.png'>
					<b>{$lang['design_141']}</b><br>
					<div style='margin-left:2em;text-indent:-1em;padding:10px 3px 3px 5px;'>";
		if (count($warnings_array) > 0) {
			// Some warnings exist
			print  "&bull; {$lang['design_142']}";
		} else {
			// No warnings exist
			print  "&bull; {$lang['design_143']}";
		}
		// Get count of fields (whether from metadata or metadata temp), excluding Form Status Fields
		$q = db_query("select count(1) from $metadata_table where project_id = $project_id and field_name != concat(form_name,'_complete')");
		$currentMetadataCount = db_result($q,0);
		print  "</div>
				<div style='margin-left:2em;text-indent:-1em;padding:10px 3px 15px 5px;'>
					&bull; {$lang['design_144']} " . count($dictionary_array['A']) . " {$lang['design_145']}
					$currentMetadataCount {$lang['design_146']} " . (($status < 1) ? $lang['design_147'] : $lang['design_148']) . "
					{$lang['design_149']}
				</div>";
		print  "</div>";

		// Display warnings
		Design::renderWarnings($warnings_array);


		// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if any forms are a survey, and if so, if has any branching logic.
		// If so, disable question auto numbering.
		if ($status < 1)
		{
			foreach (array_keys($Proj->surveys) as $this_survey_id)
			{
				$this_form = $Proj->surveys[$this_survey_id]['form_name'];
				if ($Proj->surveys[$this_survey_id]['question_auto_numbering'])
				{
					// Loop through fields in uploaded DD to see if any has branching
					$formHasBranching = false;
					foreach ($dictionary_array['B'] as $this_row=>$this_value)
					{
						if ($this_value == $this_form && $dictionary_array['L'][$this_row] != "")
						{
							$formHasBranching = true;
						}
					}
					// Survey is using auto question numbering and has branching, so set to custom numbering
					if ($formHasBranching)
					{
						// Give user a prompt as notice of this change
						?>
						<div class="yellow" style="margin:20px 0;">
							<img src="<?php echo APP_PATH_IMAGES ?>exclamation_orange.png">
							<?php echo "<b>{$lang['survey_08']} \"<span style='color:#800000;'>".strip_tags(label_decode($Proj->surveys[$this_survey_id]['title']))."</span>\"</b><br>{$lang['survey_07']} {$lang['survey_10']}" ?>
						</div>
						<?php
					}
				}
			}
		}


		// Render button to commit changes
		print  "<br><form action='{$_SERVER['REQUEST_URI']}' method='POST' name='form' enctype='multipart/form-data'>
				<div class='blue' style='padding:20px;margin:20px 0 0;'>
					<div id='uploadmain'>
						<div style='padding-bottom:5px;'>
							<b>{$lang['design_153']}</b><br>
							{$lang['design_154']}
						</div>
						<input type='hidden' name='fname' value='".htmlspecialchars(basename($uploadedfile_name), ENT_QUOTES)."'>
						<input type='hidden' name='date_format' value='".((isset($_POST['date_format']) && $_POST['date_format'] == 'DMY') ? "DMY" : "MDY")."'>
						<div style='padding-top:8px;'>
							<input type='submit' name='commit' value='".js_escape($lang['design_690'])."' onclick=\"
								document.getElementById('uploadmain').style.display='none';
								document.getElementById('progress').style.display='block';
							\">
							<a href='{$_SERVER['REQUEST_URI']}' style='margin-left:15px;'>{$lang['global_53']}</a>
						</div>
					</div>
					<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #aaa;color:#800000;'>
						<table cellpadding=10><tr>
						<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
						<td valign=top style='padding-top:20px;'>
							<b>{$lang['design_155']}</b><br>
							{$lang['design_156']}<br>
							{$lang['design_157']}
						</td>
						</tr></table>
					</div>
				</div>
				</form><br>
				</div>";
	}





// User clicked button to Commit changes
} elseif (isset($_POST['commit'])) {

	// Process uploaded Excel file
	$dictionary_array = Design::excel_to_array(APP_PATH_TEMP . $_POST['fname']);

	// Return warnings and errors from file (and fix any correctable errors)
	list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

	// If errors exist, display them and stop
	if (count($errors_array) > 0) {

		// Display RETURN arrow
		renderPrevPageBtn(PAGE);

		//Display errors
		Design::renderErrors($errors_array);

		//Display warnings
		Design::renderWarnings($warnings_array);

		print "<br>";
		renderPrevPageBtn(PAGE);

		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	// Set up all actions as a transaction to ensure everything is done here
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");
	
	// Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table
	MetaData::createDataDictionarySnapshot();

	// Save data dictionary in metadata table
	$sql_errors = MetaData::save_metadata($dictionary_array);

	// Display any failed queries to Super Users, but only give minimal info of error to regular users
	if (count($sql_errors) > 0) {

		// ERRORS OCCURRED, so undo any changes made
		db_query("ROLLBACK");
		// Set back to previous value
		db_query("SET AUTOCOMMIT=1");

		print  "<div class='red'>
					<b>{$lang['global_01']}:</b><br>
					{$lang['design_158']}";
		// Display failed queries only to super users for troubleshooting
		if (SUPER_USER)
		{
			print  "<br><br>{$lang['design_159']}<br>";

			foreach ($sql_errors as $this_query)
			{
				print "<p>".htmlspecialchars($this_query, ENT_QUOTES).";</p>";
			}
		}
		print  "</div>";

		renderPrevPageBtn(PAGE);

	}
	else
	{		
		// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if any forms are a survey, and if so, if has any branching logic.
		// If so, disable question auto numbering.
		if ($status < 1)
		{
			foreach (array_keys($Proj->surveys) as $this_survey_id)
			{
				$this_form = $Proj->surveys[$this_survey_id]['form_name'];
				if ($Proj->surveys[$this_survey_id]['question_auto_numbering'] && Design::checkSurveyBranchingExists($this_form))
				{
					// Survey is using auto question numbering and has branching, so set to custom numbering
					$sql = "update redcap_surveys set question_auto_numbering = 0 where survey_id = $this_survey_id";
					db_query($sql);
				}
			}
		}

		// COMMIT CHANGES
		db_query("COMMIT");
		// Set back to previous value
		db_query("SET AUTOCOMMIT=1");

		// SUCCESS - reload page so that menu form names will update (for development projects)
		// Delete the uploaded file from the server now that its data has been imported
		unlink($_POST['fname']);
		print  "<br><br><img src='" . APP_PATH_IMAGES . "progress_circle.gif'> &nbsp;<b>{$lang['design_160']}</b><br>";
		print  "<script type='text/javascript'>
				$(function(){
					window.location.href = app_path_webroot+page+'?pid=$project_id&upload_success&view=ddupload';
				});
				</script>";

	}

}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
