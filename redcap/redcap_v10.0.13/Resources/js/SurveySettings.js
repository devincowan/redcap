$(function(){
	// Check if need to disable View Survey Results sub-options
	checkViewResults();
	$('#view_results').change(function(){
		checkViewResults();
	});
	// Datetime picker
	$('#survey_expiration').datetimepicker({
		buttonText: 'Click to select a date', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
		showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
	});
	// For PROMIS CATs, disable certain settings that aren't usable
	if (isPromisInstrument) {
		$('tr#save_and_return-tr, tr#view_results-tr, tr#question_by_section-tr, tr#question_auto_numbering-tr').fadeTo(0,0.3);
		$('select[name="save_and_return"], select[name="view_results"], select[name="question_by_section"], select[name="question_auto_numbering"]').prop('disabled', true);
	}
    // Show/hide e-Consent options
    showHideEconsentSigFields();
    $('#select-more-sigs button').click(function(){
        showNewEconsentSigField();
    });
    $('input[name="pdf_auto_archive"]').change(function(){
        var div = $('#pdf_econsent_options');
        if ($(this).val() == '2') {
            div.show('fade');
        } else {
            div.hide('fade');
        }
    });
});

function showHidePDFcompactLabel(show) {
    if (show) {
        $('.econsent-pdf-compact').show();
    } else {
        $('.econsent-pdf-compact').hide();
    }
}

function showHideEconsentSigFields() {
    var thisField;
    var nameBase = 'pdf_econsent_signature_field';
    var selectAnotherBtn = $('#select-more-sigs');
    selectAnotherBtn.show();
    for (var i=numEconsentSignatureFields; i>=1; i--) {
        thisField = $('select[name="'+nameBase+i+'"]');
        if (thisField.val() == '' && i > 1) {
            thisField.parent().hide();
        } else {
            if (i == numEconsentSignatureFields) {
                selectAnotherBtn.hide();
            }
            break;
        }
    }
}

function showNewEconsentSigField() {
    $('.pdf_econsent_signature_field_div:hidden:first').show().find('select').effect('highlight',{},3000);
    if ($('.pdf_econsent_signature_field_div:visible').length == numEconsentSignatureFields) {
        $('#select-more-sigs').hide();
    }
}

// View Results option
function checkViewResults() {
	if ($('#view_results').val() == '0') {
		$('.view_results_options').fadeTo(0,0.3);
		$('.view_results_options input').attr('disabled', true);
	} else {
		$('.view_results_options').fadeTo(500,1);
		$('.view_results_options input').attr('disabled', false);
		$('.view_results_options input').removeAttr('disabled');
	}
}

// Update the content of the survey theme iframe
function updateThemeIframe() {
	var url = app_path_webroot+'Surveys/theme_view.php?pid='+pid+'&iframe=1&font_family='+$('#font_family').val()
			+ '&theme='+($('#theme').prop('disabled') ? '' : $('#theme').val())+'&text_size='+$('#text_size').val()
			+ '&enhanced_choices='+$('#enhanced_choices').val();
	if ($('#theme').prop('disabled')) {
		$('#row_custom_theme input').each(function(){
			url += '&'+$(this).attr('name')+'='+$(this).val().substring(1, 7);
		});
	}
	$('#survey_theme_design').attr('src', url);
}

// Initialize jQuery spectrum widget for themes
function initSpectrum(element, color)
{
	if (color == null || color == '' || color == '#') color = '';
	// Set value
	$(element).val(color);
	// Init widget
	$(element).spectrum({
		showInput: true, 
		preferredFormat: 'hex',
		color: color,
		localStorageKey: 'redcap',
		change: function(color) {
			updateThemeIframe();
		}
	});
}

// Display the custom theme option widgets
function showCustomThemeOptions() {
	var attr = jQuery.parseJSON($('#theme option:selected').attr('attr'));
	$('#theme').prop('disabled',true);
	$.each(attr, function(key,value) {
		var element = 'input[name="'+key+'"]';
		if ($(element).length) {
			initSpectrum(element, value);
		}
	});
	$('#showCustomThemeOptionsBtn').button('option', 'disabled', true);
	$('#cancelCustomThemeOptionsBtn').css('visibility', 'visible');
	highlightTableRowOb($('#row_custom_theme'),1000);
	$('#row_custom_theme').show('fade',{},500);
	updateThemeIframe();
}

// Hide the custom theme option widgets
function cancelCustomThemeOptions() {
	$('#showCustomThemeOptionsBtn').button('option', 'disabled', false);
	$('#cancelCustomThemeOptionsBtn').css('visibility', 'hidden');
	$('#theme').prop('disabled',false);
	$('#row_custom_theme, #save_custom_theme_div').hide();
	$('#row_custom_theme input').each(function(){
		$(this).val('');
	});
	updateThemeIframe();
}

// Display dialog to modify or delete a user's theme
function openManageThemesPopup() {
	$.post(app_path_webroot+'Surveys/theme_manage.php?pid='+pid,{ action: 'view' },function(data){
		if (data == '' || data == '0') { alert(woops); return; }
		initDialog('manage_themes_popup');
		simpleDialog(data,$('#openManageThemePopupBtn').text(),'manage_themes_popup',600);
		fitDialog($('#manage_themes_popup'));
		initButtonWidgets();
	});
}

// Delete user's survey theme
function deleteTheme(theme_id,confirm,ob) {
	var thisrow = $(ob).parents('tr:first');
	if (confirm == '1') {
		simpleDialog(surveySettingsLang06+" \"<b style='font-size:13px;'>"+$('.theme_edit_label', thisrow).text()+"</b>\""+surveySettingsLang07,surveySettingsLang05,null,null,null,surveySettingsLang02,function(){
			deleteTheme(theme_id,0,ob);
		},surveySettingsLang05);
	} else {
		$.post(app_path_webroot+'Surveys/theme_manage.php?pid='+pid,{ action: 'delete', theme_id: theme_id },function(data){
			if (data == '' || data == '0') { alert(woops); return; }
			highlightTableRowOb(thisrow, 800);
			setTimeout(function(){
				thisrow.hide('fade');
			},500);
			// Remove theme from theme drop-down list on page
			if ($('#theme option[value="'+theme_id+'"]:selected').length) $('#theme').val('');
			$('#theme option[value="'+theme_id+'"]').remove();
		});
	}
}

// Make text input visible for user to rename theme
function editThemeName(theme_id,ob) {
	var thisrow = $(ob).parents('tr:first');
	$('.theme_edit_label', thisrow).hide();
	$('.theme_edit_input', thisrow).show();
}

// Rename theme via ajax
function editThemeNameAjax(theme_id,ob) {
	var thisrow = $(ob).parents('tr:first');
	var theme_name = trim($('.theme_edit_input input[type="text"]', thisrow).val());
	if (theme_name == '') {
		$('.theme_edit_input input[type="text"]', thisrow).val('').focus();
		return;
	}
	$.post(app_path_webroot+'Surveys/theme_manage.php?pid='+pid,{ action: 'rename', theme_id: theme_id, theme_name: theme_name },function(data){
		if (data == '' || data == '0') { alert(woops); return; }
		hideEditThemeName(ob);
		$('.theme_edit_label', thisrow).html(theme_name);
		highlightTableRowOb(thisrow, 2500);
		// Replace theme name label in theme drop-down list
		$('#theme option[value="'+theme_id+'"]').html(theme_name);
	});
}

// Hide text input visible for user to rename theme
function hideEditThemeName(ob) {
	var thisrow = $(ob).parents('tr:first');
	$('.theme_edit_label', thisrow).show();
	$('.theme_edit_input', thisrow).hide();
}

// Display dialog to save user's theme
function openSaveThemePopup() {
	var el = $('#save_custom_theme_div');
	if (el.css('display') !== 'none') {
		el.hide();
	} else {
		// Enable input and button
		$('#custom_theme_name_btn').button('enable');
		$('#custom_theme_name').prop('disabled', false);
		$('#custom_theme_icon_progress, #custom_theme_icon_success').hide();
		$('#custom_theme_icon_cancel').show();
		$('#custom_theme_name').val('');
		// Determine where to put the box and then display it
		var cell = $('#openSaveThemePopupBtn');
		var cellpos = cell.offset();
		el.css({ 'left': cellpos.left - (el.outerWidth(true) - cell.outerWidth(true))/2,
				 'top': cellpos.top + cell.outerHeight(true) });
		el.fadeIn('slow');
		$('#custom_theme_name').focus();
	}
}

// Save user theme
function saveUserTheme() {
	var el = $('#custom_theme_name');
	var theme_name = trim(el.val());
	el.val(theme_name);
	if (theme_name == '') {
		el.focus();
		return;
	}
	// Disable input and button
	$('#custom_theme_name_btn').button('disable');
	$('#custom_theme_name').prop('disabled', true);
	$('#custom_theme_icon_progress').show();
	$('#custom_theme_icon_success, #custom_theme_icon_cancel').hide();
	// Set params and do ajax call
	var params = $('form#survey_settings').serializeObject();
	$.extend(params, { theme_name: theme_name, theme: $('#theme').val() });
	$.post(app_path_webroot+'Surveys/theme_save.php?pid='+pid, params, function(data){
		if (data == '0' || data == '') {
			alert(woops);return;
		}
		// Replace survey theme drop-down list with updated options
		$('#theme_parent').html(data);
		$('#custom_theme_icon_progress').hide();
		$('#custom_theme_icon_success, #openManageThemePopupBtn').show();
		//$('#save_custom_theme_div_sub').effect('highlight',{},1000);
		setTimeout(function(){
			$('#save_custom_theme_div').hide('fade');
			$('#theme').effect('highlight',{},2000);
		},1500);
	});
}

// Display dialog to copy survey's design settings
function openCopyDesignSettingsPopup() {
	// Check all the design options checkboxes
	$('#copy_design_text_size, #copy_design_font_family, #copy_design_theme').prop('checked', true);
	// If new logo was just uploaded, then disable that option because we can't process it until this survey has first been saved.
	var show_logo_disabled_msg = ($('#old_logo').val() == '' && $('#logo_id').val() != '');
	if (show_logo_disabled_msg) {
		$('#copy_design_logo_msg').show();
		$('#copy_design_logo').prop('checked', false).prop('disabled', true);
		$('#copy_design_logo_parent').fadeTo(0,0.5);
	} else {
		$('#copy_design_logo_msg').hide();
		$('#copy_design_logo').prop('checked', true).prop('disabled', false);
		$('#copy_design_logo_parent').fadeTo(0,1);
	}
	// Open dialog
	$('#copyDesignSettingsPopup').dialog({ bgiframe: true, modal: true, width: 700, open: function(){ fitDialog(this); },
		buttons: [
			{ text: surveySettingsLang02, click: function () {
				$(this).dialog("close");
			} },
			{ text: surveySettingsLang03, click: function () {
				// Get params on the page
				var params = $('form#survey_settings').serializeObject();
				// Get values selected in popup
				$.extend(params, {
					copy_design_logo: ($('#copy_design_logo').prop('checked') ? 1 : 0),
					copy_design_text_size: ($('#copy_design_text_size').prop('checked') ? 1 : 0),
					copy_design_font_family: ($('#copy_design_font_family').prop('checked') ? 1 : 0),
					copy_design_theme: ($('#copy_design_theme').prop('checked') ? 1 : 0),
					copy_design_enhanced_choices: ($('#copy_design_enhanced_choices').prop('checked') ? 1 : 0)
				});
				// Get selected surveys
				if (!$('#copy_design_survey_select input:checked').length) {
					simpleDialog(surveySettingsLang04);
					return;
				}
				var copy_design_survey_ids = new Array();
				var i = 0;
				$('#copy_design_survey_select input:checked').each(function(){
					copy_design_survey_ids[i++] = $(this).attr('sid');
				});
				$.extend(params, { copy_design_survey_ids: copy_design_survey_ids.join(',') });
				// Save settings
				$.post(app_path_webroot+'Surveys/copy_design_settings.php?pid='+pid, params,function(data){
					$('#copyDesignSettingsPopup').dialog("close");
					if (data == '' || data == '0') {
						alert(woops);
					} else {
						simpleDialog(data,surveySettingsLang01);
					}
				});
			} }
		]
	});
}