// On pageload
var clickedPdfDownload = false;
$(function(){

	// Make all text fields submit form when click Enter on them
	$(':input').keydown(function(e) {
		if (this.type == 'checkbox' && e.which == 13) {
			return false;
		} else if (this.type == 'text' && e.which == 13) {
			// First check secondary id field (if exists on page) and don't allow form submission since we need to wait for ajax response
			if (secondary_pk != '' && $('#form :input[name="'+secondary_pk+'"]').length && this.name == secondary_pk) {
				$('#form :input[name="'+secondary_pk+'"]').trigger('blur');
				return false;
			}
			// Do not submit the form is this is an auto-suggest field being clicked Enter on
			else if ($(this).hasClass('autosug-search') || $(this).hasClass('rc-autocomplete')) {
				return false;
			} else {
				// Make sure we validate the field first, if has validation, before submitting the form. This will not fix the value in
				// all cases if the value has incorrect format, but it will sometimes.
				$(this).trigger('blur');
				// Submit form normally when pressing Enter key in text field
				if ($('#field_validation_error_state').val() == '0') {
					dataEntrySubmit($('form#form :input[name="submit-btn-saverecord"]'));
				}
			}
		}
	});
	
	// Hack to un-truncate long select options: http://stackoverflow.com/a/19734474/1402028
	if (isIOS) $("#questiontable select").append('<optgroup label=""></optgroup>');

	// Enable action tags
	enableActionTags();

	// Enable auto-complete for drop-downs (unless Field Embedding is used, and if so, we'll run after doFieldEmbedding())
	if (!$('#questiontable .rc-field-embed').length) enableDropdownAutocomplete();

	// Scroll to position on page if scrollTop provided in query string
	var scrollTopNum = getParameterByName('scrollTop');
	if (isNumeric(scrollTopNum)) $(window).scrollTop(scrollTopNum);

	// Enable green row highlight for data entry form table
	enableDataEntryRowHighlight();

	// Open Save button tooltip	fixed at top-right of data entry forms
	displayFormSaveBtnTooltip();

	// PUT FOCUS ON FIRST FIELD IN FORM (but not if we're putting focus on another field first)
	setTimeout(function(){ // Do a slight delay to deal with some issues where jQuery will be moved fields around after this (e.g., randomization button)
		// Do not do this for the mobile view since it causes the keyboard to open on text fields
		if (getParameterByName('fldfocus') == '') {
			// Do not put focus if a dialog is open
			var dqDialogOpen = ($("#dq_rules_violated").length && $("#dq_rules_violated").hasClass("ui-dialog-content") && $("#dq_rules_violated").dialog("isOpen"));
			var reqFldDialogOpen = ($("#reqPopup").length && $("#reqPopup").hasClass("ui-dialog-content") && $("#reqPopup").dialog("isOpen"));
			if (dqDialogOpen || reqFldDialogOpen) return false;
			// Loop through fields to find the first
			$('form#form .slider, form#form input:visible, form#form textarea:visible, form#form select:visible, form#form a.fileuploadlink:visible').each(function(){
				var thisfld = $(this);
				// Skip the DAG drop-down, calc fields, and the invisible input companions for radios
				if (thisfld.attr('name') != '__GROUPID__' && !thisfld.hasClass('hiddenradio') && !(thisfld.attr('type') == 'text' && thisfld.attr('readonly') == 'readonly')) {
					try {
						if (thisfld.hasClass('slider')) {
							thisfld.find(':first-child').trigger('focus');
						} else {
							thisfld.trigger('focus');
						}
						// If a drop-down autocomplete field is selected, then hide the drop-down options
						if (thisfld.hasClass('rc-autocomplete') && thisfld.val() == '') {
							$('ul.ui-autocomplete').hide();
						}
					} catch (e) { }
					return false;
				}
			});
		}
	},10);

	// Hide or disable fields if using special annotation
	triggerActionTags();

	// If user modifies any values on the data entry form, set flag to TRUE
	$('form#form').change(function(e){
		dataEntryFormValuesChanged = true;
	});

	// If user tries to navigate off page after modifying any values on the data entry form, then stop and prompt user if they really want to leave page
	$('a').click(function(e){
		// If the user has the ability to modify the form
		var userCanModifyData = ( $('#form :input[name="submit-btn-saverecord"]').length && (!$('#__LOCKRECORD__').length || ($('#__LOCKRECORD__').length && !$('#__LOCKRECORD__').prop('disabled'))) );
		// If form values have changed...
		if (dataEntryFormValuesChanged && userCanModifyData) {
			// Ignore if has 'rc_attach' class, which is an attachment link
			if ($(this).hasClass('rc_attach')) {
				// Temporarily set to false, then back to true right afterward (to allow us to bypass the window.onbeforeunload function that would otherwise catch it)
				dataEntryFormValuesChanged = false;
				setTimeout(function(){
					dataEntryFormValuesChanged = true;
				}, 1000);
				return true;
			}
			// If is not a proper link but is mailto: or javascript:, then stop here
			var link = this;
			var href = trim(link.href.toLowerCase());
			var target = ($(link).attr('target') == null) ? '' : trim($(link).attr('target').toLowerCase());
			var onclick = ($(link).attr('onclick') == null) ? '' : trim($(link).attr('onclick'));
			// If link is pointing to anchor on same page
			var isAnchorSamePage = (href != '#' && href.indexOf('#') > -1 && href.toLowerCase().substr(0, href.indexOf('#')) == window.location.href.toLowerCase().substr(0, href.indexOf('#')));
			// If just clicked PDF download link, then do not give a prompt
			if (showEraseValuePrompt == 1 && onclick.indexOf('PdfController:index') > 0) {
				clickedPdfDownload = true;
			} else if (showEraseValuePrompt == 1 && href != '#' && !isAnchorSamePage && href.indexOf(window.location.href.toLowerCase()+'#') !== 0 && href.indexOf('javascript:') !== 0
				&& href.indexOf('mailto:') !== 0 && target != '_blank') {
				// Prevent navigating to page
				e.preventDefault();
				// Display confirmation dialog
				$('#stayOnPageReminderDialog').dialog({ bgiframe: true, modal: true, width: 650,
					title: '<img src="'+app_path_images+'exclamation_red.png" style="vertical-align:middle;"> <span style="color:#800000;vertical-align:middle;">'+langDlgSaveDataTitle+'</span>',
					buttons: [{
						text: langStayOnPage,
						click: function() { $(this).dialog("close"); }
					},{
						text: langLeavePage,
						"class": 'dataEntryLeavePageBtn',
						click: function() {
							// Disable the onbeforeunload so that we don't get an alert before we leave
							window.onbeforeunload = function() { }
							// Redirect to next page
							window.location.href = link.href;
						}
					},{
						text: langSaveLeavePage,
						"class": 'dataEntrySaveLeavePageBtn',
						click: function() {
							// Add element to form to denote how to redirect after saving
							appendHiddenInputToForm('save-and-redirect',link.href);
							// Save form
							dataEntrySubmit($('form#form :input[name="submit-btn-savecontinue"]'));
							return false;
						}
					}]
				});
			}
		}
	});

	// Set autocomplete for BioPortal ontology search for ALL fields on a page
	initAllWebServiceAutoSuggest();

	// Make sure dropdowns don't get too wide so that they create horizontal scrollbar
	shrinkWideDropDowns();
	
	// Enable repeating forms buttons
	$('.formMenuList .btnAddRptEv').on({
		mouseenter: function(){
			$(this).removeClass('btn-defaultrc').addClass('btn-success');
		},
		mouseleave: function () {
			$(this).removeClass('btn-success').addClass('btn-defaultrc');
		}
	});
	
	// Set click action for repeating forms drop-down list
	$('#repeatInstanceDropdownDiv ul li a').click(function(){
		$('#repeatInstanceDropdown').html( $(this).html() + '<img src="'+app_path_images+'arrow_state_grey_expanded.png" style="margin-left:6px;">' );
		$('#repeatInstanceDropdownDiv').hide();
	});
	
	// Set "Save and ..." button trigger for popup
	$('.btn-saveand').popover({ container: 'body', html: true });
	
	// Improve onchange event of text/notes fields to trigger branching logic before leaving the field
	// (so that we don't skip over the next field, which becomes displayed)
	improveBranchingOnchange();
});

// If user tries to close the page after modifying any values on the data entry form, then stop and prompt user if they really want to leave page
window.onbeforeunload = function() {
	// Check if PDF download button was clicked
	try {
		if (!clickedPdfDownload && typeof document.activeElement != "undefined" && document.activeElement.getAttribute('id') == 'pdfExportDropdownTrigger') {
			clickedPdfDownload = true;
		}
	} catch(e) { }
	if (clickedPdfDownload) {
		clickedPdfDownload = false;
		return;
	}
	// If form values have changed (and we're not viewing a read-only survey response)...
	if (dataEntryFormValuesChanged && showEraseValuePrompt == 1) {
		var separator = "#########################################\n";
		// Prompt user with confirmation
		return separator + langDlgSaveDataTitleCaps + "\n\n" + langDlgSaveDataMsg + "\n" + separator;
	}
}

// Open Save button tooltip	fixed at top-right of data entry forms
function displayFormSaveBtnTooltip() {
	// If save buttons are not displayed (e.g., form is locked), then don't display tooltip
	if ($('#__SUBMITBUTTONS__-div').length == 0 || $('#__SUBMITBUTTONS__-div').css('display') == 'none') return;
	// Hide if showing mobile friendly page
	var scrollBarWidth = ($(document).height() > $(window).height()) ? getScrollBarWidth() : 0;
	var windowWidth = $(window).width();
	if (windowWidth+scrollBarWidth <= maxMobileWidth) {
		$('#formSaveTip').hide();
		return;
	}
	// Copy all the buttons from bottom of page and put in div
	$('#formSaveTip').html( $('#__SUBMITBUTTONS__-div').html() );
	$('#formSaveTip').find('button.btn-primaryrc').css({'font-size':'13px'});
	$('#formSaveTip .btn-group').css({'display':'block'});
	$('#formSaveTip .btn-saveand').attr('data-placement','bottom');
	var widthBuffer = windowWidth >= 1215 ? 5 : (windowWidth >= 1115 ? -50 : -100);
	// Open tooltip	fixed at top-right of page
	$('#formSaveTip').css({
		'position': "fixed",
		'left': ($('form#form #questiontable').offset().left + $('form#form #questiontable').outerWidth() + widthBuffer) + "px"
	}).show();
}

// Fetch list of contributors to a response
function getResponseContributors(response_id, is_completed) {
	showProgress(1);
	$.post(app_path_webroot+'index.php?route=DataEntryController:getResponseContributors&pid='+pid,{ response_id: response_id, is_completed: is_completed },function(data){
		showProgress(0, 0);
		simpleDialog(data,langContribTitle,null,570);
	});
}

// Data history icon onmouseover/out actions
function dh1(ob) {
	ob.src = app_path_images+'history_active.png';
}
function dh2(ob) {
	ob.src = app_path_images+'history.png';
}

// Open pop-up dialog for viewing data history of a field
var lastDataHistWidth = 900;
function dataHist(field,event_id,popup_width) {
	lastDataHistWidth = popup_width;
	// Get window scroll position before we load dialog content
	var windowScrollTop = $(window).scrollTop();
	var record = decodeURIComponent(getParameterByName('id'));
	if ($('#data_history').hasClass('ui-dialog-content')) $('#data_history').dialog('destroy');
	$('#dh_var').html(field);
	$('#data_history2').html('<p><img src="'+app_path_images+'progress_circle.gif"> Loading...</p>');
	$('#data_history').dialog({ bgiframe: true, title: 'Data History for variable "'+field+'" for record "'+record+'"', modal: true, width: popup_width, zIndex: 3999, buttons: {
			Close: function() { $(this).dialog('destroy'); } }
	});
	$.post(app_path_webroot+"DataEntry/data_history_popup.php?pid="+pid, {field_name: field, event_id: event_id, record: record, instance: getParameterByName('instance') }, function(data){
		$('#data_history2').html(data);
		// Adjust table height within the dialog to fit
		var tableHeightMax = 300;
		if ($('#data_history3').height() > tableHeightMax) {
			$('#data_history3').height(tableHeightMax);
			$('#data_history3').scrollTop( $('#data_history3')[0].scrollHeight );
			// Reset window scroll position, if got moved when dialog content was loaded
			$(window).scrollTop(windowScrollTop);
		}
		// Re-center dialog
		$('#data_history').dialog('option', 'position', { my: "center", at: "center", of: window });
		// Highlight the last row in DH table
		if ($('table#dh_table tr').length > 1) {
			setTimeout(function(){
				highlightTableRowOb($('table#dh_table tr:last'), 3500);
			},300);
		}
	});
}

// Data Cleaner icon onmouseover/out actions
function dc1(ob) {
	ob.src = app_path_images+'balloon_left.png';
}
function dc2(ob) {
	ob.src = app_path_images+'balloon_left_bw2.gif';
}

//Javascript to position Missing data menu  and set onclick functions
var fieldToUpdate = "";
var fieldName = "";
$(function() {
	// Set the onclick function of all the missing data buttons
	var MDB=$('[name=missingDataButton]');
	MDB.click(function(){
		// Get relevant form elements for selected field
		fieldName=$(this).attr('fieldName');
		qtype=$(this).attr('qtype');
		//fieldname to update for most questions
		fieldToUpdate = $('[name=' + fieldName + ']');
		// Reveal menu of missing data options
		$('#MDMenu').insertAfter(this);
		$('#MDMenu').toggle();
	});

	// Click function when option is selected from menu
	$('.set_btn').on('click', function () {

		// Get code for selected option
		var code=$(this).attr('code');
		var label=$(this).text();
		var labelOnly=$(this).attr('label');
		//set current row as a variable
		var tr = fieldToUpdate.parent().parent();
		//set this missing data button
		var thisMDB =$('[name="missingDataButton"][fieldName="' + fieldName + '"]');
		var greyPic=app_path_images+"missing.png";
		var activePic=app_path_images+"missing_active.png"

		if (code=="")
		{thisMDB.prop('src', greyPic);
			thisMDB.prop('missing', false);}
		else {thisMDB.prop('src', activePic);
			thisMDB.prop('missing', true);}

		if(qtype=='checkbox')
		{
			//id-__chk__test_checkbox_RC_MSNG
			if (code=="")
			{
				//deselect and enable all checkboxes
				$('[id*="id-__chk__' + fieldName + '_RC_"]').prop('checked', false);
				$('[name*="__chk__' + fieldName + '_RC_"]').val("");
				$('[id*="id-__chk__' + fieldName + '_RC_"]').prop('disabled', false);
				$('[id*="id-__chk__' + fieldName + '_RC_"').parent().removeClass("opacity35");
				$('[id*="id-__chk__' + fieldName + '_RC_"]').parent().attr('onclick','sr(this,event)');
				//clear missing data code label
				$('[id="' + fieldName +'_MDLabel"]').text("");
				$('[id="' + fieldName +'_MDLabel"]').hide();
			}else
			{
				//deselect and disable all the checkboxes
				$('[id*="id-__chk__' + fieldName + '_RC_"]').prop('checked', false);
				$('[id*="id-__chk__' + fieldName + '_RC_"]').prop('disabled', true);
				$('[id*="id-__chk__' + fieldName + '_RC_"').parent().addClass("opacity35");
				$('[id*="id-__chk__' + fieldName + '_RC_"]').parent().attr('onclick','');
				//clear all hidden fields for this checkbox group
				$('[name*="__chk__' + fieldName + '_RC_"]').val("");
				//set the hidden MDC field value to the MDC
				$('[name="__chk__'+ fieldName + '_RC_' + replaceDotInCheckboxCoding(code) + '"]').val(code);
				//set MD label value
				$('[id="' + fieldName +'_MDLabel"]').text(label).attr('label',labelOnly).attr('code',code);
				$('[id="' + fieldName +'_MDLabel"]').show();
			}
		}else
		{
			//handle all other fields
			//if code ="", ie 'clear' option clicked, enable input and show date buttons. Otherwise make input field read-only.
			if (code==""){
				fieldToUpdate.prop('disabled', false);
				//show datepicker and 'now' buttons if applicable
				$('img.ui-datepicker-trigger, button.today-now-btn', tr).show();
				// show slider if there is one
				$('[id="slider-' + fieldName +'"]').show();
				$('[id="sldrmsg-' + fieldName +'"]').show();
				//clear missing data code label
				$('[id="' + fieldName +'_MDLabel"]').text("");
				$('[id="' + fieldName +'_MDLabel"]').hide();
				// if slider, display and reset
				$('#'+fieldName+'-tr .sldrparent').removeClass('hidden');
				resetSlider(fieldName);
				//if ontology field, enable the text entry box
				$('[id="' + fieldName +'-autosuggest-span"]').prop('disabled', false);
				//if radio button, enable genuine options
				$('[name="'+ fieldName + '___radio"]').prop('disabled', false);
				$('[name="'+ fieldName + '___radio"]').parent().removeClass("opacity35");
				$('[name="'+ fieldName + '___radio"]').parent().attr('onclick','sr(this,event)');
				// If a drop-down
				$('select[name="'+ fieldName + '"]').removeClass("opacity65");
				// file/signature
				if (qtype == 'file') {
					$('#'+fieldName+'-tr .fileupload-container').removeClass('hidden');
					// If deleting a file/signature, then give delete prompt so that the user understands they are deleting a file
					$('#'+fieldName+'-tr .deletedoc-lnk').trigger('click');
				}
				// Show field
				if (qtype == 'select' && $(':input#rc-ac-input_'+ fieldName).length && $(':input#rc-ac-input_'+ fieldName).hasClass("rc-autocomplete")) {
					// Auto-complete drop-down (keep it hidden and update it)
					$(':input#rc-ac-input_'+ fieldName).val('');
				}
			}else{
				// If deleting a file/signature, then give delete prompt so that the user understands they are deleting a file
				if (qtype == 'file' && $('#'+fieldName+'-tr .deletedoc-lnk').length) {
					codeUpdateAfterDeleteFile = code;
					$('#'+fieldName+'-tr .deletedoc-lnk').trigger('click');
					return;
				}
				//fieldToUpdate.readOnly = true;
				fieldToUpdate.prop('disabled', true);
				//hide datepicker and 'now' buttons if applicable
				$('img.ui-datepicker-trigger, button.today-now-btn', tr).hide();
				// hide slider if there is one
				$('[id="slider-' + fieldName +'"]').hide();
				$('[id="sldrmsg-' + fieldName +'"]').hide();
				$('#'+fieldName+'-tr .sldrparent').addClass('hidden');
				//set MD label value
				$('[id="' + fieldName +'_MDLabel"]').text(label).attr('label',labelOnly).attr('code',code);
				$('[id="' + fieldName +'_MDLabel"]').show();
				$('[id="' + fieldName +'-autosuggest-span"]').prop('disabled', true);
				//if radio button, disable genuine options
				$('[name="'+ fieldName + '___radio"]').prop('disabled', true);
				$('[name="'+ fieldName + '___radio"]').parent().addClass("opacity35");
				$('[name="'+ fieldName + '___radio"]').parent().attr('onclick','');
				// If a drop-down
				$('select[name="'+ fieldName + '"]').addClass("opacity65");
				// file/signature
				if (qtype == 'file') {
					$('#'+fieldName+'-tr .fileupload-container').addClass('hidden');
				}
				// Show field
				if (qtype == 'select' && $(':input#rc-ac-input_'+ fieldName).length && $(':input#rc-ac-input_'+ fieldName).hasClass("rc-autocomplete")) {
					// Auto-complete drop-down (keep it hidden and update it)
					$(':input#rc-ac-input_'+ fieldName).val(label);
				} else {
					fieldToUpdate.show();
				}
			}

			// Set new value of related field
			fieldToUpdate.val(code);
			//hide or display missing data label
			if (fieldToUpdate.hasClass('hiddenradio')) {
				var MDLabel=$('#' + fieldName + '_MDLabel');
				MDLabel.text(label);
				if (code==""){
					MDLabel.hide();
				}else{
					MDLabel.show();
				}
				fieldToUpdate.parent().find('input[type="radio"]').prop('checked', false);
			}
		}
		// If user modifies any values on the data entry form, set flag to TRUE
		dataEntryFormValuesChanged = true;
		// Trigger branching/calc fields, in case fields affected
		setTimeout(function(){try{calculate(fieldName);doBranching(fieldName);}catch(e){}},50);
		//Trigger piping update
		if (qtype == 'checkbox') {
			fieldToUpdate.click();
			updatePipingCheckboxes($('[id*="id-__chk__' + fieldName + '_RC_"]'));
		} else if (qtype == 'radio') {
			if (code == '') {
				labelOnly = code = missing_data_replacement_js;
			}
			updatePipingRadiosDoValLabel(fieldName, code, labelOnly);
		} else if (qtype == 'text') {
			fieldToUpdate.blur();
		} else {
			fieldToUpdate.blur();
			fieldToUpdate.change();
			fieldToUpdate.click();
		}
		// Hide the revealed menu
		$('#MDMenu').hide();
	});
});



// Open pop-up for inviting participant to finish a follow-up survey
function inviteFollowupSurveyPopup(survey_id,form,record,event_id,instance) {
	if (!$('#inviteFollowupSurvey').length) $('body').append('<div id="inviteFollowupSurvey" style="display:none;"></div>');
	// Get the dialog content via ajax first
	$.post(app_path_webroot+'Surveys/invite_participant_popup.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id+'&instance='+instance, { action: 'popup', form: form, record: record }, function(data){
		if (data == '0') {
			alert(woops);
			return;
		}
		$('#inviteFollowupSurvey').html(data);
		initWidgets();
		initSurveyReminderSettings();
		$('#inviteFollowupSurvey').dialog({ bgiframe: true, modal: true, width: 750, open: function(){fitDialog(this)},
			title: 'Send Survey Invitation to Participant "'+record+'"',
			buttons: {
				'Cancel': function() {
					$(this).dialog('close');
				},
				'Send Invitation': function() {
					// Trim email subject/message
					$('#followupSurvEmailSubject').val( trim($('#followupSurvEmailSubject').val()) );
					$('#followupSurvEmailMsg').val( trim($('#followupSurvEmailMsg').val()) );
					// If set exact time in future to send surveys, make sure time doesn't exist in the past
					var now_ymdhm = now.replace(/ /g, '').replace(/-/g, '').replace(/:/g, '');
					now_ymdhm = now_ymdhm.substring(0, now_ymdhm.length-2)*1;
					var eTs = $('#inviteFollowupSurvey #emailSendTimeTS').val();
					if (user_date_format_validation == 'mdy') {
						var emailSendTimeTs_ymdhm = eTs.substr(6,4)+eTs.substr(0,2)+eTs.substr(3,2)+eTs.substr(11,2)+eTs.substr(14,2);
					} else if (user_date_format_validation == 'dmy') {
						var emailSendTimeTs_ymdhm = eTs.substr(6,4)+eTs.substr(3,2)+eTs.substr(0,2)+eTs.substr(11,2)+eTs.substr(14,2);
					} else {
						var emailSendTimeTs_ymdhm = eTs.substr(0,4)+eTs.substr(5,2)+eTs.substr(8,2)+eTs.substr(11,2)+eTs.substr(14,2);
					}
					if ($('#inviteFollowupSurvey input[name="emailSendTime"]:checked').val() == 'EXACT_TIME') {
						if ($('#inviteFollowupSurvey #emailSendTimeTS').val().length < 1) {
							simpleDialog($('#langFollowupProvideTime').html(),null,null,null,"$('#inviteFollowupSurvey #emailSendTimeTS').focus();");
							return;
						} else if (!redcap_validate(document.getElementById('emailSendTimeTS'),'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter)) {
							return;
						} else if (emailSendTimeTs_ymdhm < now_ymdhm) {
							simpleDialog($('#langFollowupTimeInvalid').html(),$('#langFollowupTimeExistsInPast').html());
							return;
						}
					}
					// Determine delivery method
					var delivery_type = ($('#inviteFollowupSurvey select[name="delivery_type"]').length) ?
						$('#inviteFollowupSurvey select[name="delivery_type"]').val() : 'EMAIL';
					// Make sure we have email address. Typed email overrides the drop-down selection email
					var email = trim($('#followupSurvEmailTo').val());
					if (email == '' && $('#followupSurvEmailToDD').length) {
						email = trim($('#followupSurvEmailToDD').val());
					}
					// Email is a valid email address OR is an integer (i.e. participant_id)
					if (delivery_type == 'EMAIL' && !isEmail(email) && !isNumeric(email)) {
						simpleDialog('Please provide a valid email address');
						return;
					}
					// Make sure we have phone number. Typed phone overrides the drop-down selection phone
					var phone = trim($('#followupSurvPhoneTo').val());
					if (phone == '' && $('#followupSurvPhoneToDD').length) {
						phone = trim($('#followupSurvPhoneToDD').val());
					}
					// phone is a valid email address OR is an integer (i.e. participant_id)
					if (delivery_type != 'EMAIL' && (!isNumeric(phone) || phone == '')) {
						simpleDialog('Please provide a valid phone number');
						return;
					}
					// Validate the surveys reminders options
					if (!validateSurveyRemindersOptions()) return;
					// Set initial values
					var reminder_type = $('#reminders_choices_div input[name="reminder_type"]:checked').val();
					if (reminder_type == null || !$('#enable_reminders_chk').prop('checked')) reminder_type = '';
					var reminder_timelag_days = '';
					var reminder_timelag_hours = '';
					var reminder_timelag_minutes = '';
					var reminder_nextday_type = '';
					var reminder_nexttime = '';
					var reminder_exact_time = '';
					var reminder_num = '0';
					if (reminder_type == 'NEXT_OCCURRENCE') {
						reminder_nextday_type = $('#reminders_choices_div select[name="reminder_nextday_type"]').val();
						reminder_nexttime = $('#reminders_choices_div input[name="reminder_nexttime"]').val();
					} else if (reminder_type == 'TIME_LAG') {
						reminder_timelag_days = ($('#reminders_choices_div input[name="reminder_timelag_days"]').val() == '') ? '0' : $('#reminders_choices_div input[name="reminder_timelag_days"]').val();
						reminder_timelag_hours = ($('#reminders_choices_div input[name="reminder_timelag_hours"]').val() == '') ? '0' : $('#reminders_choices_div input[name="reminder_timelag_hours"]').val();
						reminder_timelag_minutes = ($('#reminders_choices_div input[name="reminder_timelag_minutes"]').val() == '') ? '0' : $('#reminders_choices_div input[name="reminder_timelag_minutes"]').val();
					} else if (reminder_type == 'EXACT_TIME') {
						reminder_exact_time = $('#reminders_choices_div input[name="reminder_exact_time"]').val();
					}
					var reminder_num = $('#reminders_choices_div select[name="reminder_num"]').val();
					// Set status message
					$(':button:contains("Cancel")').html('Close');
					$(':button:contains("Send Invitation")').unbind().html('<span class="ui-button-text"><img src="'+app_path_images+'progress_circle.gif" style="vertical-align:middle;"> <span style="vertical-align:middle;">Sending...</span></span>');
					// Send email via ajax
					$.post(app_path_webroot+'Surveys/invite_participant_popup.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id+'&instance='+instance, { email: email,
						action: 'email', form: form, record: record, email_account: $('#followupSurvEmailFrom').val(), subject: $('#followupSurvEmailSubject').val(), msg: $('#followupSurvEmailMsg').val(),
						sendTime: $('#inviteFollowupSurvey input[name="emailSendTime"]:checked').val(), sendTimeTS: $('#inviteFollowupSurvey #emailSendTimeTS').val(),
						reminder_type: reminder_type,
						reminder_timelag_days: reminder_timelag_days,
						reminder_timelag_hours: reminder_timelag_hours,
						reminder_timelag_minutes: reminder_timelag_minutes,
						reminder_nextday_type: reminder_nextday_type,
						reminder_nexttime: reminder_nexttime,
						reminder_exact_time: reminder_exact_time,
						reminder_num: reminder_num,
						delivery_type: delivery_type, phone: phone,
						email_sender_display: $('#email_sender_display').val()
					}, function(data){
						if (data == '0') {
							alert(woops);
							return;
						}
						// If sending the invite immediately, then remind the user to leave the form to prevent possibly overwriting data
						if ($('#inviteFollowupSurvey input[name="emailSendTime"]:checked').val() == 'IMMEDIATELY') {
							simpleDialog(lang_data_entry_473,lang_data_entry_370,null,570,null,langStayOnPage,function(){
								window.location.href = $('#record-home-link').attr('href');
							},lang_data_entry_474);
						}
						// Replace popup content and auto-hide after 4s
						$('#inviteFollowupSurvey').html(data);
						$('#inviteFollowupSurveyBtn').hide();
						$(':button:contains("Sending...")').remove();
					});
				} }
		});
		// Show/hide all fields in popup accordingly
		if ($('#inviteFollowupSurvey select[name="delivery_type"]').length) {
			$('#inviteFollowupSurvey select[name="delivery_type"]').trigger('change');
		}
		// Make the email display name drop-down a typeable element
		$("#email_sender_display:not(.hidden)").select2({
			tags: true,
			placeholder: $("#email_sender_display").attr('placeholder')
		})
		.on('select2:select', function(e){
			if (e.params.data.id == '-- clear --') {
				$('#email_sender_display').val('');
				$('#email_sender_display').trigger('change.select2');
			}
		});
		$("#inviteFollowupSurvey .select2-selection").css({'height':'24px'});
		$("#inviteFollowupSurvey #select2-email_sender_display-container").css({'line-height':'24px'});
		initTinyMCEglobal('mceEditor');
	});
}