<?php



include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Obtain form names and e-signature label text for each
$sql = "select m.form_name, m.form_menu_description, f.label, if(f.display is null, 1, f.display) as display,
		if(f.display_esignature is null, 0, f.display_esignature) as display_esignature
		from redcap_metadata m left outer join redcap_locking_labels f
		on f.form_name = m.form_name and m.project_id = f.project_id where m.project_id = $project_id and
		m.form_menu_description is not null order by m.field_order";
$q = db_query($sql);
$forms = array();
while ($row = db_fetch_assoc($q))
{
	// Check if should be displayed on data entry form
	$row['class'] = $row['display'] ? "datagreen" : "datared";

	// Add flags if label exists or not
	if ($row['label'] == "")
	{
		// E-sign label is NOT set
		$row['defined'] = false;
		$row['icon']    = 'display:none;';
		$row['label']   = '<textarea id="label-' . $row['form_name'] . '" class="notesp11" ' . (!$row['display'] ? "disabled" : "") . '></textarea>'
						. '<br><input type="button" style="font-size:11px;" value="Save" onclick="addSaveLockLabel(\''.$row['form_name'].'\')" ' . (!$row['display'] ? "disabled" : "") . '>';
	}
	else
	{
		// E-sign label is set
		$row['defined'] = true;
		$row['icon']    = 'display:block;';
		$row['label']	= nl2br(filter_tags($row['label']));
	}
	// Add to array
	$forms[] = $row;
}




// Set page header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

renderPageTitle("<i class=\"fas fa-lock\"></i> {$lang['locking_35']}");
$tabs = array();
if ($user_rights['lock_record_customize'] > 0) $tabs['Locking/locking_customization.php'] = '<i class="fas fa-user-lock"></i> ' . $lang['app_11'];
if ($user_rights['lock_record'] > 0) $tabs['Locking/esign_locking_management.php'] = '<i class="fas fa-unlock-alt"></i> ' . $lang['esignature_01'];
RCView::renderTabs($tabs);
?>

<!-- Instructions -->
<p><?php echo $lang['locking_02'] ?></p>

<p style="margin-bottom:30px;">
	<?php echo $lang['locking_03'] ?> <span class="datagreen" style="padding:0 4px;"><?php echo $lang['locking_04'] ?></span>
	<?php echo $lang['global_47'] ?> <span class="datared" style="padding:0 4px;"><?php echo $lang['locking_06'] ?></span>
	<?php echo $lang['locking_07'] ?>
	<span style="color:#A86700;font-family:tahoma;font-size:11px;"><?php echo $lang['locking_08'] ?></span>
	<?php echo $lang['locking_09'] ?> <img src="<?php echo APP_PATH_IMAGES ?>pencil.png">
	<?php echo $lang['locking_10'] ?> <img src="<?php echo APP_PATH_IMAGES ?>cross.png">
	<?php echo $lang['locking_11'] ?>
</p>


<?php
// WARNING DIALOG POPUP: Only allow users to make changes on this page if project is in development or if they are a super user
if ($status > 0) {
	?>

	<div id="labelProdChanges" style="display:none;" title="<?php echo js_escape2($lang['locking_33']) ?>"><p>
	<?php  print $lang['locking_34']; ?>
	</p></div>

	<script type="text/javascript">
	$(function(){
		$('#labelProdChanges').dialog({ bgiframe: true, modal: true, width: 600, buttons: {
            '<?php print js_escape($lang['locking_30']) ?>': function () {
                $('#part11_forms').show('slide', 'slow');
                $(this).dialog('close');
            },
            '<?php print js_escape($lang['locking_31']) ?>': function () {
                $('#part11_forms input, #part11_forms textarea').prop('disabled', true);
                $('#part11_forms textarea').css('background', '#eee');
                $('#part11_forms input[type="button"]').hide();
                $('#part11_forms').show();
                $(this).dialog('close');
            }
		} });
	});
	</script>
	<?php
}
?>

<!-- Table -->
<table id="part11_forms" class="form_border" <?php echo ($status > 0 ? 'style="display:none;"' : '') ?>>
	<!-- Header -->
	<tr>
		<td class="labelrc" style="padding:5px;font-family:tahoma;font-size:10px;background-color:#EEE;width:80px;text-align:center;">
			<?php echo $lang['locking_16'] ?>
		</td>
		<td class="labelrc" style="background-color:#EEE;padding:5px 10px;">
			<?php echo $lang['global_35'] ?>
		</td>
		<td class="labelrc" style="padding:5px;font-family:tahoma;font-size:10px;background-color:#EEE;width:80px;text-align:center;">
			<?php echo $lang['locking_18'] ?>
		</td>
		<td class="labelrc" style="background-color:#EEE;padding:5px 10px;width:310px;">
			<?php echo $lang['locking_19'] ?>
		</td>
		<td class="labelrc" style="font-family:tahoma;font-size:10px;text-align:center;background-color:#EEE;width:60px;font-weight:normal;">
			<?php echo $lang['locking_20'] ?>
		</td>
	</tr>
	<!-- Rows -->
<?php foreach ($forms as $row) { ?>
	<tr id="row-<?php echo $row['form_name'] ?>">
		<td class="data <?php echo $row['class'] ?>" style="text-align:center;">
			<div style="font-size:10px;">&nbsp;</div>
			<div><input id="dispchk-<?php echo $row['form_name'] ?>" type="checkbox" <?php echo !$row['display'] ? "" : "checked" ?> onclick="setDisplay('<?php echo $row['form_name'] ?>',this.checked)"></div>
			<div id="saved-<?php echo $row['form_name'] ?>" style="color:red;font-size:10px;visibility:hidden;"><?php echo $lang['global_39'] ?></div>
		</td>
		<td id="name-<?php echo $row['form_name'] ?>" class="data notranslate <?php echo $row['class'] ?>" style="font-weight:bold;color:#444;padding:5px 10px;">
			<?php echo RCView::escape($row['form_menu_description']) ?>
		</td>
		<td class="data <?php echo $row['class'] ?>" style="text-align:center;">
			<div style="font-size:10px;">&nbsp;</div>
			<div><input type="checkbox" <?php echo !$row['display'] ? "disabled" : "" ?> <?php echo !$row['display_esignature'] ? "" : "checked" ?> onclick="setDisplayEsign('<?php echo $row['form_name'] ?>',this.checked)"></div>
			<div id="savedEsign-<?php echo $row['form_name'] ?>" style="color:red;font-size:10px;visibility:hidden;"><?php echo $lang['global_39'] ?></div>
		</td>
		<td id="cell-<?php echo $row['form_name'] ?>" class="data notranslate <?php echo $row['class'] ?>" style="padding:5px 10px;width:375px;color:<?php echo !$row['display'] ? "#999999" : "#000000" ?>;">
			<?php echo $row['label'] ?>
		</td>
		<td class="data <?php echo $row['class'] ?>" style="text-align:center;padding:5px 10px;width:60px;">
			<div id="edit-<?php echo $row['form_name'] ?>" style="<?php echo $row['icon'] ?>;visibility:<?php echo !$row['display'] ? "hidden" : "visible" ?>;">
				<a href="javascript:;" onclick="editLockLabel('<?php echo $row['form_name'] ?>')"><img src="<?php echo APP_PATH_IMAGES ?>pencil.png" title="Edit"></a>&nbsp;
				<a href="javascript:;" onclick="delLabel('<?php echo $row['form_name'] ?>')"><img src="<?php echo APP_PATH_IMAGES ?>cross.png" title="Remove"></a>
			</div>
		</td>
	</tr>
<?php } ?>
</table>


<?php if (empty($forms)) { // If no forms exist, give warning ?>
	<div class="red" style="margin-top:30px;">
		<b><?php echo $lang['global_01'].$lang['colon'] ?></b> <?php echo $lang['locking_22'] ?>
		<a style="text-decoration:underline;font-family:verdana;"
		   href="<?php echo APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id" ?>"><?php echo $lang['locking_23'] ?></a>
		<?php echo $lang['global_14'] . $lang['period'] ?>
	</div>
<?php }

?>
<script type="text/javascript">
var lockAjaxErrorMsg = woops;
// Create textarea box
function createLabelTextarea(form,existing_label,content) {
	var funct = (existing_label) ? "editSaveLabel" : "addLockLabel";
	return '<textarea class="notesp11" id="label-'+form+'">'+content+'</textarea><br>'
		 + '<input type="button" style="font-size:11px;" value="Save" onclick="'+funct+'(\''+form+'\')">';
}
// Add/remove class from table row
function addRemoveTableRowClass(row_id,class1,addOrRemove) {
	var drow = document.getElementById(row_id);
	for (var j=0; j<drow.cells.length; j++) {
		if (addOrRemove) {
			$(drow.cells[j]).addClass(class1);
		} else {
			$(drow.cells[j]).removeClass(class1);
		}
	}
}
// Begin editing locking text for a form
function editLockLabel(form) {
	var label = $('#cell-'+form).html();
	label = label.replace(new RegExp("\\r","g"),'');
	label = label.replace(new RegExp("\\n","g"),'');
	label = label.replace(new RegExp("<br>","ig"),'\r\n');
	label = label.replace(new RegExp("&lt;br&gt;","ig"),'\r\n');
	$('#cell-'+form).html(createLabelTextarea(form,1,trim(label)));
	$('#edit-'+form).css('display','none');
	$('#icon-'+form).css('display','none');
	$('#btnsave-'+form).css('display','block');
}
// Save edits for locking text for a form
function editSaveLabel(form) {
	var label = $('#label-'+form).val();
	$.post(app_path_webroot+'Locking/locking_customization_ajax.php?pid='+pid, { form: form, action: 'edit', label: label }, function(data) {
		if (data != "0") {
			$('#icon-'+form).css('display','block');
			$('#btnsave-'+form).css('display','none');
			$('#edit-'+form).css('display','block');
			$('#cell-'+form).html(filter_tags(label.replace(new RegExp("\\n","g"),"<br>")));
			highlightTableRow('row-'+form,2000);
		} else {
			alert(lockAjaxErrorMsg);
			window.location.reload();
		}
	});
}
// Add locking text for a form
function addSaveLockLabel(form) {
	var label = trim($('#label-'+form).val());
	if (label.length < 1) {
		alert('<?php echo js_escape($lang['locking_27']) ?>');
		$('#label-'+form).val('');
		return;
	}
	$.post(app_path_webroot+'Locking/locking_customization_ajax.php?pid='+pid, { form: form, action: 'add', label: label }, function(data) {
		if (data != "0") {
			$('#icon-'+form).css('display','block');
			$('#edit-'+form).css('display','block');
			$('#cell-'+form).html(filter_tags(label.replace(new RegExp("\\n","g"),"<br>")));
			highlightTableRow('row-'+form,2000);
		} else {
			alert(lockAjaxErrorMsg);
			window.location.reload();
		}
	});
}
// Set display value
function setDisplay(form,isChecked) {
	$.post(app_path_webroot+'Locking/locking_customization_ajax.php?pid='+pid, { form: form, action: 'set_display', display: (isChecked ? 1 : 0) }, function(data) {
		if (data == "1") {
			$('#saved-'+form).css('visibility','visible');
			setTimeout(function(){
				$('#saved-'+form).css('visibility','hidden');
			},700);
			if (isChecked) {
				addRemoveTableRowClass('row-'+form,'datared',0);
				addRemoveTableRowClass('row-'+form,'datagreen',1);
				// Enable all inputs in this row
				$('#row-'+form+' :input').each(function() {
					$(this).prop('disabled',false);
				});
				$('#edit-'+form).css('visibility','visible');
				$('#cell-'+form).css('color','#000000');
			} else {
				addRemoveTableRowClass('row-'+form,'datagreen',0);
				addRemoveTableRowClass('row-'+form,'datared',1);
				// Disable all inputs in this row
				$('#row-'+form+' :input').each(function() {
					if ($(this).prop('id') != 'dispchk-'+form) $(this).prop('disabled',true);
				});
				$('#edit-'+form).css('visibility','hidden');
				$('#cell-'+form).css('color','#999999');
			}
			highlightTableRow('row-'+form,1000);
		} else {
			alert(lockAjaxErrorMsg);
			window.location.reload();
		}
	});
}
// Set display for e-signature
function setDisplayEsign(form,isChecked) {
	$.post(app_path_webroot+'Locking/locking_customization_ajax.php?pid='+pid, { form: form, action: 'set_display_esign', display: (isChecked ? 1 : 0) }, function(data) {
		if (data == "1") {
			$('#savedEsign-'+form).css('visibility','visible');
			setTimeout(function(){
				$('#savedEsign-'+form).css('visibility','hidden');
			},700);
		} else {
			alert(lockAjaxErrorMsg);
			window.location.reload();
		}
	});
}
// Delete esignature label text for a form
function delLabel(form) {
	if (confirm("<?php echo $lang['locking_28'] ?>\n\n<?php echo $lang['locking_29'] ?> \""+trim($('#name-'+form).html())+"\"?")) {
		$.post(app_path_webroot+'Locking/locking_customization_ajax.php?pid='+pid, { form: form, action: 'delete' }, function(data) {
			if (data != "0") {
				$('#icon-'+form).css('display','none');
				$('#edit-'+form).css('display','none');
				$('#cell-'+form).html(createLabelTextarea(form,1,''));
				highlightTableRow('row-'+form,2000);
			} else {
				alert(lockAjaxErrorMsg);
				window.location.reload();
			}
		});
	}
}
</script>


<?php

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';