<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Kick out if project is not in production status yet
if ($status < 1)
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
	exit;
}

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Super User Instructions
if ($super_user && $draft_mode == "2")
{
	renderPageTitle("<img src='".APP_PATH_IMAGES."find.png'> {$lang['database_mods_01']}");
	?>
	<p style='margin:20px 0 5px;'>
		<b><?php echo $lang['global_24'] . $lang['colon'] ?></b><br>
		<?php echo $lang['database_mods_03'] ?>
	</p>
	<?php
}
// Normal User Instructions
elseif (!$super_user && $draft_mode == "1")
{
	renderPageTitle("<img src='".APP_PATH_IMAGES."find.png'> {$lang['database_mods_04']}");
	?>
	<p style='margin:20px 0 5px;'>
		<?php echo $lang['database_mods_05'] ?>
	</p>
	<?php
}
// Should not be here
elseif ($draft_mode == "0")
{
	renderPageTitle("<img src='".APP_PATH_IMAGES."find.png'> {$lang['database_mods_01']}");
	?>
	<div class="yellow" style="margin:20px 0;">
		<b><?php echo $lang['global_01'].$lang['colon'] ?></b> <?php echo $lang['database_mods_184'] ?>
	</div>
	<?php
	renderPrevPageBtn('Design/online_designer.php');
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}

// Link to return to design page
print "<p style='margin:20px 0;'>";
renderPrevPageBtn();
print "</p>";

// Get counts of fields added/deleted and HTML for metadata diff table
list ($num_records, $fields_added, $field_deleted, $field_with_data_deleted, $count_new, $count_existing) = Design::renderCountFieldsAddDel2();
list ($newFields, $delFields, $fieldsAddDelText) = Design::renderFieldsAddDel();
list ($num_metadata_changes, $num_fields_changed, $record_id_field_changed, $num_critical_issues, $metadataDiffTable) = Design::getMetadataDiff($num_records);
$newFieldsIdentifierKeywordMatch = ($auto_prod_changes_check_identifiers == '1') ? IdentifierCheck::getNewFieldsMatchingKeywords($project_id, $status) : array();
$countUsersInitMobileApp = MobileApp::countUsersInitProject($project_id);

// Retrieve email address of requestor of changes
$sql = "select i.user_email from redcap_user_information i, redcap_metadata_prod_revisions r
		where r.project_id = $project_id and r.ui_id_requester = i.ui_id and r.ts_approved is null
		order by r.pr_id desc limit 1";
$q = db_query($sql);
$requestor_email = ($q && db_num_rows($q)) ? db_result($q, 0) : '';

// See if auto changes can be made (if enabled)
$willBeAutoApproved = (
		defined("AUTOMATE_ALL")
		// If the ONLY changes are that new fields were added
		|| ($auto_prod_changes == '2' && $num_fields_changed == 0 && $field_deleted == 0 && $num_critical_issues == 0 && empty($newFieldsIdentifierKeywordMatch))
		// If the ONLY changes are that new fields were added OR if there is no data
		|| ($auto_prod_changes == '3' && ($num_records == 0 || ($num_fields_changed == 0 && $field_deleted == 0 && $num_critical_issues == 0)) && empty($newFieldsIdentifierKeywordMatch))
		// OR if there are no critical issues AND no fields deleted (regardless of whether or not project has data)
		|| ($auto_prod_changes == '4' && $field_with_data_deleted == 0 && $num_critical_issues == 0 && empty($newFieldsIdentifierKeywordMatch))
		// OR if there are (no critical issues AND no fields deleted) OR if there is no data
		|| ($auto_prod_changes == '1' && ($num_records == 0 || ($field_with_data_deleted == 0 && $num_critical_issues == 0)) && empty($newFieldsIdentifierKeywordMatch))
	)
	? "<span style='color:green;font-size:13px;'>{$lang['design_100']}</span> <img src='".APP_PATH_IMAGES."tick.png'>"
	: "<span style='color:red;'>{$lang['design_292']}</span>";

// Render descriptive summary text about field changes
print  "<p style='width:100%;max-width:850px;'>
			<u><b>{$lang['database_mods_131']}</b></u><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&bull; {$lang['index_22']}{$lang['colon']} <b>$num_records</b><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&bull; <span style='color:green;'>{$lang['database_mods_88']} <b>$fields_added</b></span><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&bull; <span style='color:brown;'>{$lang['database_mods_112']} <b>$num_fields_changed</b></span><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&bull; <span style='color:#F00000;'>{$lang['database_mods_130']} <b>".($field_with_data_deleted+$num_critical_issues)."</b></span><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - <span style='color:#F00000;font-size:11px;'>".$lang['database_mods_134']." <b>$field_with_data_deleted</b></span><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - <span style='color:#F00000;font-size:11px;'>".$lang['database_mods_135']." <b>$num_critical_issues</b></span><br>
			".(!($record_id_field_changed && $num_records > 0) ? '' : "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - <span style='color:red;".($num_records > 0 ? "font-size:13px;font-weight:bold;" : "font-size:11px;")."'>".$lang['database_mods_161']." ".($num_records > 0 ? $lang['database_mods_179'] : "")."</span><br>")."
			&nbsp;&nbsp;&nbsp;&nbsp;&bull; {$lang['database_mods_111']} <b>$count_existing</b><br>
			&nbsp;&nbsp;&nbsp;&nbsp;&bull; {$lang['database_mods_110']} <b>$count_new</b><br>" .
			(($countUsersInitMobileApp > 0 && $num_fields_changed+$field_deleted > 0) ? "<span style='display:block;' class='hang'> &nbsp;&nbsp;&nbsp;&nbsp;&bull; <span style='color:#C00000;'><b>{$lang['database_mods_169']}</b> $countUsersInitMobileApp ".($countUsersInitMobileApp == 1 ? $lang['database_mods_170'] : $lang['database_mods_171'])." {$lang['database_mods_172']}</span></span>" : "");
if (defined("AUTOMATE_ALL") || ($auto_prod_changes > 0 && $draft_mode == '1')) {
	print "	&nbsp;&nbsp;&nbsp;&nbsp;&bull; <b>{$lang['database_mods_114']}&nbsp; $willBeAutoApproved</b>";
	if ($super_user) {
		print "<span style='color:gray;margin-left:10px;'>(<a style='text-decoration:underline;font-size:11px;' href='".APP_PATH_WEBROOT."ControlCenter/user_settings.php#tr-auto_prod_changes' target='_blank'>{$lang['design_438']}</a>)</span>";
	}
}
print  "</p>";

// Display fields to be added and deleted
 print  "<table style='width:100%;max-width:850px;'>
			<tr>
				<td valign='top'>$fieldsAddDelText</td>";
// Display key for metadata changes
print  "		<td valign='bottom'>";
Design::renderMetadataCompareKey();
print  "		</td>
			</tr>
		</table>";


## DTS: Check for any field changes that would cause DTS to break
if ($dts_enabled_global && $dts_enabled)
{
	// Get fields used by DTS
	$dtsFields = array_keys(Event::getDtsFields());
	// Get fields used by DTS that are being deleted
	$dtsDelFields = array_intersect($dtsFields, $delFields);
	// Get fields used by DTS that have had their field type changed to invalid type (i.e. not text or textarea)
	$dtsFieldsTypeChange = array();
	$sql = "select m.field_name from redcap_metadata m, redcap_metadata_temp t where m.project_id = t.project_id
			and m.field_name = t.field_name and m.element_type in ('text', 'textarea')
			and t.element_type not in ('text', 'textarea') and m.project_id = " . PROJECT_ID . "
			and m.field_name in ('" . implode("', '", $dtsFields) . "')";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		$dtsFieldsTypeChange[] = $row['field_name'];
	}
	// Give warning message if DTS fields are being deleted or have their field type changed or (if longitudinal) moved to different form
	if (!empty($dtsDelFields) || !empty($dtsFieldsTypeChange))
	{
		?>
		<div class="red" style="margin:20px 0;">
			<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
			<b><?php echo $lang['define_events_64'] ?></b><br>
			<?php
			echo $lang['database_mods_101'];
			if (!empty($dtsDelFields)) {
				echo "<br><br>" . $lang['database_mods_102'] . " <b>" . implode("</b>, <b>", $dtsDelFields) . "</b>";
			}
			if (!empty($dtsFieldsTypeChange)) {
				echo "<br><br>" . $lang['database_mods_103'] . " <b>" . implode("</b>, <b>", $dtsFieldsTypeChange) . "</b>";
			}
			?>
		</div>
		<?php
	}
}


// SURVEY QUESTION NUMBERING: Detect if any forms are a survey, and if so, if has any branching logic.
// If so, disable question auto numbering.
foreach (array_keys($Proj->surveys) as $this_survey_id)
{
	$this_form = $Proj->surveys[$this_survey_id]['form_name'];
	if ($Proj->surveys[$this_survey_id]['question_auto_numbering'] && Design::checkSurveyBranchingExists($this_form,"redcap_metadata_temp"))
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



// Render table to display metadata changes
print $metadataDiffTable;

// Buttons for committing/undoing changes
if ($super_user && $status > 0 && $draft_mode == 2)
{
	print  "<div class='blue' id='commitBtns' style='margin-bottom:50px;margin-top:20px;padding-bottom:15px;padding-top:5px;'>
				<div style='margin:0 0 20px;font-weight:bold;'>
					<img src='".APP_PATH_IMAGES."gear.png' style='margin-right:2px;'>
					{$lang['database_mods_149']}
				</div>

				{$lang['database_mods_136']}
				<div style='margin:4px 0 20px;'>
					<button class='jqbuttonmed' onclick=\"
							simpleDialog(null,null,'userConfirmEmail',600,null,'".js_escape($lang['global_53'])."',
							'sendSingleEmail($(\'#emailFrom option:selected\').text(),$(\'#emailTo\').val(),$(\'#emailTitle\').val(),$(\'#emailCont\').val(),true)',
							'" . js_escape($lang['database_mods_150']). "');
						\"><img src='".APP_PATH_IMAGES."email.png' style='vertical-align:middle;'>
						<span style='vertical-align:middle;'>{$lang['database_mods_137']}</span></button>
				</div>

				{$lang['database_mods_07']}
				<div style='margin:4px 0 20px;'>
					<div style='margin:15px 0;'>
						<button class='jqbuttonmed' onclick=\"
							simpleDialog('" . js_escape($lang['database_mods_09']) . "<br>" . js_escape($lang['database_mods_10']) . "',
								'" . js_escape($lang['database_mods_08']). "',null,null,null,
								'".js_escape($lang['global_53'])."',
								'$(\'#commitBtns :button\').button(\'disable\');showProgress(1);window.location.href = app_path_webroot+\'Design/draft_mode_approve.php?pid=$project_id\';',
								'".js_escape($lang['database_mods_138'])."');
						\"><img src='".APP_PATH_IMAGES."tick.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'>{$lang['database_mods_138']}</span></button>
						<span style='margin-left:10px;color:#800000;font-size:12px;'>{$lang['database_mods_180']}</span>
					</div>
					<div style='margin:15px 0;'>
						<button class='jqbuttonmed' onclick=\"
							simpleDialog('" . js_escape($lang['database_mods_12']) . "',
								'" . js_escape($lang['database_mods_11']). "',null,null,null,
								'".js_escape($lang['global_53'])."',
								'$(\'#commitBtns :button\').button(\'disable\');showProgress(1);window.location.href = app_path_webroot+\'Design/draft_mode_reject.php?pid=$project_id\';',
								'".js_escape($lang['database_mods_139'])."');
						\"><i class=\"fas fa-chevron-circle-left\"></i> {$lang['database_mods_139']}</button>
						<span style='margin-left:10px;color:#800000;font-size:12px;'>{$lang['database_mods_181']}</span>
					</div>
					<div style='margin:15px 0;'>
						<button class='jqbuttonmed' onclick=\"
							simpleDialog('" . js_escape($lang['database_mods_14']) . "',
								'" . js_escape($lang['database_mods_183']). "',null,null,null,
								'".js_escape($lang['global_53'])."',
								'$(\'#commitBtns :button\').button(\'disable\');showProgress(1);window.location.href = app_path_webroot+\'Design/draft_mode_reset.php?pid=$project_id\';',
								'".js_escape($lang['database_mods_140'])."');
						\"><img src='".APP_PATH_IMAGES."cross.png' style='vertical-align:middle;'> <span style='vertical-align:middle;'>{$lang['database_mods_140']}</span></button>
						<span style='margin-left:10px;color:#800000;font-size:12px;'>{$lang['database_mods_182']}</span>
					</div>
				</div>
			</div>

			<!-- Hidden div for emailing user confirmation email -->
			<div id='userConfirmEmail' class='simpleDialog' style='background-color:#F3F5F5;' title=\"" . js_escape2($lang['database_mods_137']). "\">
				<div style='padding-bottom:10px;margin-bottom:15px;border-bottom:1px solid #ccc;'>
					{$lang['database_mods_151']}
				</div>
				<table border=0 cellspacing=0 width=100%>
					<tr>
						<td style='vertical-align:middle;width:60px;font-weight:bold;'>{$lang['global_37']}</td>
						<td style='vertical-align:middle;color:#555;'>
						" . User::emailDropDownList() . "
					</tr>
					<tr>
						<td style='vertical-align:middle;width:60px;padding-top:10px;font-weight:bold;'>{$lang['global_38']}</td>
						<td style='vertical-align:middle;padding-top:10px;color:#555;'>
							<input class='x-form-text x-form-field' style='width:50%;' type='text' id='emailTo' name='emailTo' onkeydown='if(event.keyCode == 13){return false;}' onblur=\"redcap_validate(this,'','','soft_typed','email')\" value='".js_escape($requestor_email)."'>
							&nbsp; {$lang['database_mods_142']}
						</td>
					</tr>
					<tr>
						<td style='vertical-align:middle;width:60px;padding:10px 0;font-weight:bold;'>{$lang['survey_103']}</td>
						<td style='vertical-align:middle;padding:10px 0;'><input class='x-form-text x-form-field' style='width:80%;' type='text' id='emailTitle' name='emailTitle' onkeydown='if(event.keyCode == 13){return false;}' value='".js_escape("[REDCap] ".$lang['database_mods_141'])." (PID ".PROJECT_ID.")'></td>
					</tr>
					<tr>
						<td colspan='2' style='padding:5px 0 10px;'>
							<textarea class='x-form-field notesbox' id='emailCont' name='emailCont' style='height:310px;width:95%;'>".
								remBr($lang['database_mods_143'])."\n\n".remBr($lang['database_mods_144'])." \"<b>".strip_tags(label_decode($app_title))."</b>\"{$lang['period']}\n\n".
								remBr($auto_prod_changes == '1' ? $lang['database_mods_162'] : $lang['database_mods_163'])."\n\n".
								remBr($lang['database_mods_166'])."\n\n".remBr($lang['database_mods_167'])."\n\n".remBr($lang['database_mods_168'])."\n\n".
								remBr($lang['database_mods_164'])."\n\n".remBr($lang['database_mods_165'])."\n".APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/".PAGE."?pid=$project_id\n\n".remBr($lang['database_mods_147'])."\n".remBr($lang['database_mods_148'])."</textarea>
						</td>
					</tr>
					</table>
			</div>";
}


// Link to return to design page (don't show if no changes - real short page doesn't need two buttons to go back)
if ($num_metadata_changes > 0)
{
	print "<p style='margin:20px 0;'>";
	renderPrevPageBtn();
	print "</p>";
}

// Choices Change Diff dialog
?>
<div id="diff-display" class="simpleDialog" title="<?php print js_escape2($lang['design_674']) ?>">
	<p><?php print $lang['design_678'] ?></p>
	<table class="table table-bordered table-condensed table-striped">
		<thead><tr style="font-weight:bold;color:#800000;"><td><?php print $lang['design_679'] ?></td><td><?php print $lang['design_675'] ?></td>
			<td><?php print $lang['design_676'] ?></td><td><?php print $lang['design_677'] ?></td><td><?php print $lang['home_33'] ?></td>
			<td><?php print $lang['design_684'] ?></td></tr></thead>
		<tbody></tbody>
	</table>
</div>
<script type='text/javascript'>
$(function() { 
	// Display the 'Show all text' link
	if ($('.meta-diff-show-more').length) {
		$('#ShowMoreAll').css('visibility','visible');
	}
});

// function to make objects to pass to dialog
var textChoicesToObject = function (text) {
	var rtnObj = {};
	var choices = text.split('\n');
	var code;
	var label;
	for (var i=0; i < choices.length; i++) {
		var choice = choices[i].split(',');
		if (choice[1] != null) {
			code = trim(choice[0]);
			choice[0] = "";
			label = trim(choice.join(','));
			label = label.substring(1);
			rtnObj[code] = label;
		}
	}
	return ksort(rtnObj);
};
function ksort(obj){
  var keys = Object.keys(obj).sort()
    , sortedObj = {};
  for(var i in keys) {
    sortedObj[keys[i]] = obj[keys[i]];
  }
  return sortedObj;
}
// function to open dialog to compare choices
function choicesCompareBtnClick(field) {
	// Compare Choice Changes dialog
	var dialog = $('#diff-display').dialog({
		autoOpen: false,
		bgiframe: true, 
		modal: true, 
		width: 750,
		buttons: {
			'<?php echo js_escape($lang['calendar_popup_01']) ?>': function() {
				$(this).dialog("close");
			}
		},
		beforeClose: function(){
			if (inIframe()) {
				window.self.$('#diff-display').dialog('destroy');
				event.preventDefault();
				event.stopImmediatePropagation();
				event.stopPropagation();
				return false;
			}
		},
		open: function(event, ui) {
			// dialog opened - get the content of the td opened for 
			// and make a table showing the diffs
			var newChoices = $(this).data('newChoices');
			var oldChoices = $(this).data('oldChoices');
			
			// Get record counts where value is used
			var valueRecords = $(this).data('recs');
			var json_data = $.parseJSON(valueRecords);
			
			// new values added
			var addedChoiceVals = [];
			for (var key in newChoices) {
				if (!oldChoices.hasOwnProperty(key)) {
					addedChoiceVals.push(key);
				}
			}

			var trs = [];

			// make rows to show status of existing choices
			for (var oldChoiceVal in oldChoices) {
				var rowItems = [];
				var status = '';
				rowItems.push('<span style="color:#aaa;">'+oldChoiceVal+'</span>');
				rowItems.push('<span style="color:#aaa;">'+oldChoices[oldChoiceVal]+'</span>');
				if (newChoices.hasOwnProperty(oldChoiceVal)) {
					rowItems.push(oldChoiceVal);
					rowItems.push(newChoices[oldChoiceVal]);
					status = (oldChoices[oldChoiceVal] === newChoices[oldChoiceVal])
								? '<?php echo js_escape($lang['design_680']) ?>' : '<div class="red"><?php echo js_escape($lang['design_681']) ?></div>';
					rowItems.push(status);
				} else {
					rowItems.push('-');
					rowItems.push('-');
					rowItems.push('<div class="red"><?php echo js_escape($lang['design_682']) ?></div>');
				}
				if (newChoices[oldChoiceVal] != null && json_data[newChoices[oldChoiceVal]] != null) {
					rowItems.push('<span style="color:#C00000;font-size:15px;">'+json_data[newChoices[oldChoiceVal]]+'</span>');
				} else if (json_data[oldChoiceVal] != null) {
					rowItems.push('<span style="color:#C00000;font-size:15px;">'+json_data[oldChoiceVal]+'</span>');
				} else {
					rowItems.push('<span style="font-size:13px;">0</span>');
				}
				trs.push(rowItems);
			}

			// make rows for added choices
			for (var i=0; i < addedChoiceVals.length; i++) {
				var rowItems = [];
				rowItems.push('<span style="color:#aaa;">-</span>');
				rowItems.push('<span style="color:#aaa;">-</span>');
				rowItems.push(addedChoiceVals[i]);
				rowItems.push(newChoices[addedChoiceVals[i]]);
				rowItems.push('<div class="darkgreen"><?php echo js_escape($lang['design_683']) ?></div>');
				rowItems.push('<span style="color:#aaa;">-</span>');
				trs.push(rowItems);
			}

			var renderRows = function (trs) {
				var html = '';
				for (var i=0; i < trs.length; i++) {
					html += '<tr>';
					for (var j=0; j < trs[i].length; j++) {
						html += '<td>'+trs[i][j]+'</td>';
					}
					html += '</tr>';
				}
				return html;
			};
			
			$(this).find('tbody').empty().append(renderRows(trs));
		}
	});
	if ($('#'+field+'-element_enum-whole').length) {
		var textDiv = $('<div>'+$('#'+field+'-element_enum-whole').html()+'</div>');
	} else {
		var textDiv = $('<div>'+$('#'+field+'-element_enum').html()+'</div>');
	}		
	var newText = textDiv.clone().children().remove().end().text();
	var oldText = textDiv.children('div:first').text();
	var newChoices = textChoicesToObject(newText);
	var oldChoices = textChoicesToObject(oldText);	
	dialog.data('newChoices', newChoices);
	dialog.data('oldChoices', oldChoices);
	dialog.data('recs', $('#'+field+'-element_enum').attr('recs'));
	dialog.dialog('open');
	dialog.dialog( "option", "position", { my: "center", at: "center", of: window } );
	fitDialog(dialog);
}
function metaDiffShowMore(ob,fieldkey) {
	$(ob).remove();
	$('#'+fieldkey+'-trunc').remove();
	$('#'+fieldkey+'-whole').show();
}
function metaDiffShowAll() {
	$('.meta-diff-show-more').each(function(){
		$(this).trigger('click');
	});
	$('#ShowMoreAll').css('visibility','hidden');
}
</script>
<?php

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
