$(function(){
	// Enable editing of participant list email/identifier
	enableEditParticipant();
	// Reset participant list editing after sorting it by clicking header
	$('div#participant_table table th').click(function(){
		setTimeout(function(){ enableEditParticipant() },100);
	});
	// Survey Reminder related setup
	initSurveyReminderSettings();
	// Enable sendtime datetime picker
	$('#emailSendTimeTS').datetimepicker({
		onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
		buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
		showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
	});
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
	$("#emailPart .select2-selection").css({'height':'24px'});
	$("#emailPart #select2-email_sender_display-container").css({'line-height':'24px'});
});

// Select/deselect all invites in the Invitation Log
function selectAllDeleteInvite(checked) {
	$('#email_log_table table td input[type="checkbox"]').prop('checked',checked);
}
function selectOrDeselectAllDeleteInvite() {
	var allAreChecked = ($('#email_log_table table td input[type="checkbox"]:checked').length == $('#email_log_table table td input[type="checkbox"]').length);
	selectAllDeleteInvite(!allAreChecked);
}

// Delete multiple invitations
function deleteMultipleInvites() {
	var invitesChecked = $('#email_log_table table td input[type="checkbox"]:checked').length;
	if (invitesChecked == 0) {
		simpleDialog(langInvLog03);
	} else {
		simpleDialog(langInvLog02,langInvLog01,null,500,null,'Cancel','deleteMultipleInvitesDo()','Delete '+invitesChecked+' invitations');
	}
}
function deleteMultipleInvitesDo() {
	var ssq_ids = new Array();
	var i=0;
	$('#email_log_table table td input[type="checkbox"]:checked').each(function(){
		ssq_ids[i++] = $(this).prop('id').replace('delssq_','');
	});
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ ssq_ids: ssq_ids.join(','), action: 'delete_multiple' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,'showProgress(1);window.location.reload()');
	});
}

// In email survey inviations pop-up, pre-select checkboxes based on action selected
function emailPartPreselect(val) {
	if (val.length < 1) return;
	if (val == 'check_all') {
		// Check all
		$('#participant_table_email input.chk_part').prop('checked',true);
	} else {
		// Uncheck all first
		$('#participant_table_email input.chk_part').prop('checked',false);
		// Now check specifically
		if (val == 'check_sent') {
			$('#participant_table_email input.part_sent').prop('checked',true);
		} else if (val == 'check_unsent') {
			$('#participant_table_email input.part_unsent').prop('checked',true);
		} else if (val == 'check_sched') {
			$('#participant_table_email input.sched').prop('checked',true);
		} else if (val == 'check_unsched') {
			$('#participant_table_email input.unsched').prop('checked',true);
		} else if (val == 'check_unsent_unsched') {
			$('#participant_table_email input.unsched.part_unsent').prop('checked',true);
		} else if (val == 'check_resp_partial') {
			$('#participant_table_email input.part_resp_partial').prop('checked',true);
		} else if (val == 'check_resp_full') {
			$('#participant_table_email input.part_resp_full').prop('checked',true);
		} else if (val == 'check_not_resp') {
			$('#participant_table_email input.part_not_resp').prop('checked',true);
		} else if (val == 'check_resp') {
			$('#participant_table_email input.part_resp_full, #participant_table_email input.part_resp_partial').prop('checked',true);
		}
	}
}

// Load/reload the participant list via ajax
function loadPartList(survey_id,event_id,pagenum,callback_msg,callback_title) {
	if (pagenum == null) pagenum = 1;
	// Add pagenum parameter to the URL (in case page is refreshed)
	modifyURL(window.location.href+'&pagenum='+pagenum);
	// AJAX call
	showProgress(1);
	$.get(app_path_webroot+'Surveys/participant_list.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id+'&pagenum='+pagenum, function(data){
		$('#partlist_outerdiv').html(data);
		showProgress(0,0);
		// Only make table editable if on initial survey
		//if (survey_id == firstFormSurveyId && event_id == firstEventId)
		enableEditParticipant();
		// Initialize all buttons in participant list
		initWidgets();
		if (callback_msg != null) {
			var rndm = Math.random()+"";
			var dlgloadpartid = 'dlgloadpartid_'+rndm.replace('.','');
			simpleDialog(callback_msg,callback_title,dlgloadpartid);
			setTimeout(function(){
				if ($('#'+dlgloadpartid).hasClass('ui-dialog-content')) $('#'+dlgloadpartid).dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
				// Destroy the dialog so that fade effect doesn't persist if reopened
				setTimeout(function(){
					if ($('#'+dlgloadpartid).hasClass('ui-dialog-content')) $('#'+dlgloadpartid).dialog('destroy').remove();
				},500);
			},2000);
		}
	});
}

// Retrieve short url and display for user
function getShortUrl(hash,survey_id) {
	if ($('#shorturl').val().length < 1) {
		$('#shorturl_div').hide();
		$('#shorturl_loading_div').show();
		$.get(app_path_webroot+'Surveys/shorturl.php', { pid: pid, hash: hash, survey_id: survey_id }, function(data) {
			if (data != '0') {
				$('#shorturl_loading_div').hide();
				$('#shorturl').val(data);
				$('#shorturl_div').show('fade','fast');
				$('#shorturl_div').effect('highlight', 'slow');
			}
		});
	} else {
		$('#shorturl_div').effect('highlight', 'slow');
	}
}

function closeCustom(){
	$('.ui-dialog').fadeOut();
}

function confirmCustomUrl(hash,survey_id,custom_url,arm_id){
	custom_url = trim(custom_url);
	if(custom_url != ''){
		showProgress(1);
		$.get(app_path_webroot+'Surveys/shorturl_custom.php', { pid: pid, hash: hash, survey_id: survey_id, custom_url: custom_url, arm_id: arm_id }, function(data) {
			showProgress(0,0);
			if (data == '0' || data == '') {
				simpleDialog(woops,null,null,350,"customizeShortUrl('"+hash+"','"+survey_id+"','"+arm_id+"')",'Close');
			} else if (data == '1') {
				simpleDialog('The text you entered does not make a valid URL. Please try again using only letters, numbers, and underscores.',null,null,350,"customizeShortUrl('"+hash+"','"+survey_id+"','"+arm_id+"')",'Close');
			} else if (data == '2') {
				simpleDialog('Unfortunately, the URL you entered has already been taken. Please try again.',null,null,350,"customizeShortUrl('"+hash+"','"+survey_id+"','"+arm_id+"')",'Close');
			} else {
				var title = (data.indexOf('ERROR:') > -1) ? "ERROR!" : "SUCCESS!";
				simpleDialog(data,title,null,500,'window.location.reload();','Close');
			}
		});
	}else{
		simpleDialog('Please enter a valid url.',null,null,350,"customizeShortUrl('"+hash+"','"+survey_id+"','"+arm_id+"')",'Close');
	}
}

function customizeShortUrl(hash,survey_id,arm_id){
	simpleDialog(null,null,'custom_url_dialog',550,null,'Cancel',function(){
		confirmCustomUrl(hash,survey_id,$('input.customurl-input').val(),arm_id)
	},'Submit');
}

//delete custom url global function
function confirmDeleteCustomUrl(armNumber){
	$.get(app_path_webroot+'Surveys/shorturl_custom.php?pid='+pid, {action:'delete-customurl', arm_number:armNumber},function(data){
		if (data === '1'){
			$('.customurl-container *').remove();
			$('.custom-survey-link-btn').button('enable');
		} else alert(woops);
	});
}


// Click the enable/disable Participant Identifiers button to open dialog
function enablePartIdent(survey_id,event_id) {
	// First, fire JS to reorder table by Email (since the button to trigger this function ordered it by Identifier)
	setTimeout(function(){ SortTable('table-participant_table',0,'string'); },5);
	// Ajax request
	$.post(app_path_webroot+'Surveys/participant_list_enable.php?pid='+pid, { action: 'view' },function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		// Set dialog title/content
		var json_data = jQuery.parseJSON(data);
		$('#popupEnablePartIdent').prop("title",json_data.title);
		$('#popupEnablePartIdent').html(json_data.payload);
		var saveBtn = json_data.saveBtn;
		var successDialogContent = json_data.successDialogContent;
		// Open dialog
		$('#popupEnablePartIdent').dialog({ bgiframe: true, modal: true, width: 550, buttons: [{
				text: "Cancel",
				click: function () {
					$(this).dialog('close');
				}
			},{
				text: json_data.saveBtn,
				click: function () {
					// Save value via AJAX
					$.post(app_path_webroot+'Surveys/participant_list_enable.php?pid='+pid, { action: 'save' },function(data){
						if (data == "0") {
							alert(woops);
						} else {
							// Success!
							if ($('#popupEnablePartIdent').hasClass('ui-dialog-content')) $('#popupEnablePartIdent').dialog('destroy');
							var pageNum = $('#pageNumSelect').val();
							if (!isNumeric(pageNum)) pageNum = 1;
							simpleDialog(successDialogContent,null,'',500,"loadPartList("+survey_id+","+event_id+","+pageNum+");");
						}
					});
				}
			}]
		});
	});
};

// Disable Participant Identifiers column in the List (prevent adding/editing)
function disablepartIdentColumn() {
	if ($('#enable_participant_identifiers').val() == '0') {
		// DISABLED
		// Set gray background for all cells in column
		$('.partIdentColDisabled').parent().parent().css('background-color','#E8E8E8');
		// Hide text on page relating to identifiers
		$('.partIdentInstrText').hide();
		// Pop-up tooltip: Give warning message to user if tries to edit identifier IF identifiers are DISABLED (not allowed)
		$('.partIdentColDisabled').tooltip2({
			tip: '#tooltipIdentDisabled',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
	} else {
		// ENABLED
		// Show text on page relating to identifiers
		$('.partIdentInstrText').show();
	}
}

// Copy the public survey URL to the user's clipboard
function copyUrlToClipboard(ob) {
	// Create progress element that says "Copied!" when clicked
	var rndm = Math.random()+"";
	var copyid = 'clip'+rndm.replace('.','');
	var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
	$(ob).after(clipSaveHtml);
	$('#'+copyid).toggle('fade','fast');
	setTimeout(function(){
		$('#'+copyid).toggle('fade','fast',function(){
			$('#'+copyid).remove();
		});
	},2000);
}

// Pop-up tooltip: Give warning message to user if tries to click partial/complete icon to view response IF identifier is not defined
function noViewResponseTooltip() {
	$('.noviewresponse').tooltip2({
		tip: '#tooltipViewResp',
		position: 'center left',
		offset: [30, -10],
		delay: 100,
		events: { def: "click,mouseout" }
	});
}

// Set up in-line editing for email address and identifier
function enableEditParticipant() {
	// First, check if we should disabled the Identifier column in the table(if not enabled yet)
	disablepartIdentColumn();
	// Pop-up tooltip: Give warning message to user if tries to edit email/identifier IF response is partial/complete (not allowed)
	$('.noeditidentifier').tooltip2({
		tip: '#tooltipEdit',
		position: 'center right',
		offset: [10, -60],
		delay: 100,
		events: { def: "click,mouseout" }
	});
	// Pop-up tooltip: Give warning message to user if tries to click partial/complete icon to view response IF identifier is not defined
	noViewResponseTooltip();
	// Pop-up tooltip: Denote that user can click partial/complete icon to view response
	$('.viewresponse, .partLink').tooltip2({
		position: 'center right',
		offset: [0, 10],
		delay: 100
	});
	// If user clicks on Participant Email to add it for response from Public Survey, give tooltip explaining why this is not possible
	$('.noeditemailpublic').tooltip2({
		tip: '#tooltipNoEditEmailPublic',
		position: 'center right',
		offset: [10, -60],
		delay: 100,
		events: { def: "click,mouseout" }
	});
	$('.noeditphonepublic').tooltip2({
		tip: '#tooltipNoPhoneEmailPublic',
		position: 'center right',
		offset: [10, -60],
		delay: 100,
		events: { def: "click,mouseout" }
	});
	// Hide tooltips if they are clicked on
	$('#tooltipEdit, #tooltipViewResp, #tooltipIdentDisabled, #tooltipNoEditEmailPublic, #tooltipNoPhoneEmailPublic').click(function(){
		$(this).hide('fade');
	});

	// For editing Twilio invitation preference
	if ($('.editinvpref').length) enableEditInvPref( $('.editinvpref') );

	if (!isFollowUpSurvey) {
		// INITIAL SURVEY
		// For editing email
		$('.editemail').mouseenter(function(){
			// If already clicked
			if ($(this).html().indexOf('<input') > -1) {
				$(this).unbind('click');
				return;
			}
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to edit email');
		}).mouseleave(function() {
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
		});
		$('.editemail').click(function(){
			// If already clicked
			if ($(this).html().indexOf('<input') > -1) {
				$(this).unbind('click');
				return;
			}
			// Undo css
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
			$(this).unbind('click');
			var thisEmail = $(this).text();
			if (thisEmail.indexOf(')') > 0) {
				var aaa = thisEmail.split(')');
				if (aaa[1].indexOf('@') > 0) {
					thisEmail = trim(aaa[1]);
				}
			}
			if (thisEmail.indexOf('(') > 0) {
				var aaa = thisEmail.split('(');
				thisEmail = trim(aaa[0]);
			}
			var thisPartId = $(this).attr('part');
			$(this).html( '<input id="partNewEmail_'+thisPartId+'" onblur=\'redcap_validate(this,"","","soft_typed","email")\' type="text" class="x-form-text x-form-field" style="vertical-align:middle;width:70%;" value="'+thisEmail+'"> &nbsp;'
						+ '<button style="vertical-align:middle;" class="jqbuttonsm" onclick="editPartEmail('+thisPartId+');">'+langSave+'</button>');
		});
		// For editing identifier
		$('.editidentifier').mouseenter(function(){
			// If already clicked
			if ($(this).html().indexOf('<input') > -1) {
				$(this).unbind('click');
				return;
			}
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to edit identifier');
		}).mouseleave(function() {
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
		});
		$('.editidentifier').click(function(){
			// If already clicked
			if ($(this).html().indexOf('<input') > -1) {
				$(this).unbind('click');
				return;
			}
			// Undo css
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
			$(this).unbind('click');
			var thisIdentifier = trim($(this).text().replace(/"/ig,'&quot;'));
			var thisPartId = $(this).attr('part');
			$(this).html( '<input id="partNewIdentifier_'+thisPartId+'" type="text" class="x-form-text x-form-field" style="vertical-align:middle;width:73%;" value="'+thisIdentifier+'"> &nbsp;'
						+ '<button style="vertical-align:middle;" class="jqbuttonsm" onclick="editPartIdentifier('+thisPartId+');">'+langSave+'</button>');
		});
		// For editing phone
		$('.editphone').mouseenter(function(){
			// If already clicked
			if ($(this).html().indexOf('<input') > -1) {
				$(this).unbind('click');
				return;
			}
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to edit email');
		}).mouseleave(function() {
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
		});
		$('.editphone').click(function(){
			// If already clicked
			if ($(this).html().indexOf('<input') > -1) {
				$(this).unbind('click');
				return;
			}
			// Undo css
			$(this).css('cursor','');
			$(this).removeClass('edit_active');
			$(this).removeAttr('title');
			$(this).unbind('click');
			var thisPhone = $(this).text();
			// Remove all but numbers
			thisPhone = thisPhone.replace(/[^0-9\.]+/g, '');
			var thisPartId = $(this).attr('part');
			$(this).html( '<input id="partNewPhone_'+thisPartId+'" onblur=\'this.value = this.value.replace(/\\D/g,"");redcap_validate(this,"","","soft_typed","integer",1)\' type="text" class="x-form-text x-form-field" style="font-size:11px;vertical-align:middle;width:60%;" value="'+thisPhone+'"> &nbsp;'
						+ '<button style="vertical-align:middle;" class="jqbuttonsm" onclick="editPartPhone('+thisPartId+');">'+langSave+'</button>');
		});
	} else {
		// FOLLOW-UP SURVEY
		// If user clicks on Participant Identifier to add it for follow-up survey, give tooltip explaining why this is not possible
		$('.editidentifier').tooltip2({
			tip: '#tooltipNoEditIdentFollowup',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
		// If user clicks on Participant Email to add it for follow-up survey, give tooltip explaining why this is not possible
		$('.editemail').tooltip2({
			tip: '#tooltipNoEditEmailFollowup',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
		// If user clicks on Participant Phone to add it for follow-up survey, give tooltip explaining why this is not possible
		$('.editphone').tooltip2({
			tip: '#tooltipNoEditPhoneFollowup',
			position: 'center right',
			offset: [10, -60],
			delay: 100,
			events: { def: "click,mouseout" }
		});
		// Hide tooltips if they are clicked on
		$('#tooltipNoEditEmailFollowup, #tooltipNoEditIdentFollowup').click(function(){
			$(this).hide('fade');
		});
	}
}

// Open the "view email" dialog
function viewEmail(email_recip_id, ssq_id) {
	$.post(app_path_webroot+'Surveys/view_sent_email.php?pid='+pid,{ email_recip_id: email_recip_id, ssq_id: ssq_id }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,600);
	});
}

// Reload the Survey Invitation Log for another "page" when paging the log
function loadInvitationLog(pagenum) {
	showProgress(1);
	window.location.href = app_path_webroot+page+'?pid='+pid+'&email_log=1&pagenum='+pagenum+
		'&filterBeginTime='+$('#filterBeginTime').val()+'&filterEndTime='+$('#filterEndTime').val()+
		'&filterInviteType='+$('#filterInviteType').val()+'&filterResponseType='+$('#filterResponseType').val()+
		'&filterSurveyEvent='+$('#filterSurveyEvent').val()+
		'&filterRecord='+$('#filterRecord').val()+
		'&filterReminders='+($('#filterReminders').prop('checked') ? '1' : '0');
}

// Delete a scheduled survey invitation from invitation log
function deleteSurveyInvite(email_recip_id, reminder_num) {
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'view_delete' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,null,'Cancel','deleteSurveyInviteDo('+email_recip_id+','+reminder_num+')','Delete invitation');
	});
}
function deleteSurveyInviteDo(email_recip_id, reminder_num) {
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'delete' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,'showProgress(1);window.location.reload()');
	});
}

// Modify the send time for a scheduled survey invitation in the invitation log
function editSurveyInviteTime(email_recip_id, reminder_num) {
	$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'view_edit_time' }, function(data){
		if (data == "0") {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		// Display dialog
		simpleDialog(json_data.content,json_data.title,null,500,null,'Cancel','editSurveyInviteTimeDo('+email_recip_id+','+reminder_num+')','Change invitation time');
		initWidgets();
		$('#newInviteTime').datetimepicker({
			buttonText: 'Click to select a date', yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
			hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
			showOn: 'both', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
		});
	});
}
function editSurveyInviteTimeDo(email_recip_id, reminder_num) {
	if ($('#newInviteTime').val() == '') {
		simpleDialog("Please enter a date/time");
	} else {
		$.post(app_path_webroot+'Surveys/survey_invitation_ajax.php?pid='+pid,{ email_recip_id: email_recip_id, reminder_num: reminder_num, action: 'edit_time', newInviteTime: $('#newInviteTime').val() }, function(data){
			if (data == "0") {
				alert(woops);
				return;
			}
			var json_data = jQuery.parseJSON(data);
			// Display dialog
			simpleDialog(json_data.content,json_data.title,null,500,'showProgress(1);window.location.reload()');
		});
	}
}

// Open dialog for initiating Voice/SMS for surveys
function initCallSMS(hash,phone,format) {
	// Id of dialog
	var dlgid = 'VoiceSMSdialog';
	// Display dialog
	if (phone == null) {
		// Get content via ajax
		$.post(app_path_webroot+'Surveys/twilio_initiate_call_sms.php?pid='+pid+'&action=view&s='+hash,{  }, function(data){
			if (data == "0" || data == "") {
				alert(woops);
				return;
			}
			// Decode JSON
			var json_data = jQuery.parseJSON(data);
			// Add html
			initDialog(dlgid);
			$('#'+dlgid).html(json_data.content);
			// Display dialog
			$('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: 550, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); } });
			// Init buttons
			initButtonWidgets();
		});
	} else {
		// Make sure numbers were entered
		phone = trim(phone);
		$('#'+dlgid+' #call_sms_to_number').val(phone);
		if (phone == '') {
			simpleDialog('You did not enter any phone numbers.',null,null,null,'$("#'+dlgid+' #call_sms_to_number").focus();');
			return;
		}
		showProgress(1);
		// Send SMS/voice call
		$.post(app_path_webroot+'Surveys/twilio_initiate_call_sms.php?pid='+pid+'&action=init&s='+hash+'&delivery_type='+format,{ phone: phone, sms_message: $('#'+dlgid+' #sms_message').val() }, function(data){
			showProgress(0,0);
			if (data == "0" || data == "") {
				alert(woops);
				$('#'+dlgid).dialog('close');
				initCallSMS(hash);
				return;
			}
			// Decode JSON
			var json_data = jQuery.parseJSON(data);
			// Add html
			initDialog(dlgid);
			$('#'+dlgid).html(json_data.content);
			// Display dialog
			$('#'+dlgid).dialog({ title: json_data.title, bgiframe: true, modal: true, width: 500, open:function(){ fitDialog(this); }, close:function(){ $(this).dialog('destroy'); } });
			// Init buttons
			initButtonWidgets();
		});
	}
}

// Show/hide the custom SMS message text box for Public Survey invitations
function showSmsCustomMessage() {
	var isVoiceCall = ($('#VoiceSMSdialog #delivery_type').val() == 'VOICE_INITIATE');
	if (isVoiceCall) {
		$('#VoiceSMSdialog #sms_message_div').hide();
	} else {
		$('#VoiceSMSdialog #sms_message_div').show();
	}
}

// Edit the participant's email address and identifier via ajax
function editPartEmail(thisPartId) {
	var email = trim($('#partNewEmail_'+thisPartId).val());
	if (email.length<1) {
		alert('Enter an email address');
		return;
	}
	$.post(app_path_webroot+'Surveys/edit_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { email: email, participant_id: thisPartId }, function(data){
		var json_data = jQuery.parseJSON(data);
		var item = json_data.item;
		if (item.length < 1) item = '&nbsp;';
		// Loop through all instances of the same participant
		var partArray = json_data.participant_id;
		for (var i = 0; i < partArray.length; i++) {
			$('#editemail_'+partArray[i]).addClass('edit_saved').html(item);
		}
		setTimeout(function(){
			$('.editemail.edit_saved').removeClass('edit_saved');
		},1500);
		enableEditParticipant();
	});
}
function editPartIdentifier(thisPartId) {
	var identifier = trim($('#partNewIdentifier_'+thisPartId).val());
	$.post(app_path_webroot+'Surveys/edit_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { identifier: identifier, participant_id: thisPartId }, function(data){
		var json_data = jQuery.parseJSON(data);
		var item = json_data.item;
		if (item.length < 1) item = '&nbsp;';
		// Loop through all instances of the same participant
		var partArray = json_data.participant_id;
		for (var i = 0; i < partArray.length; i++) {
			$('#editidentifier_'+partArray[i]).addClass('edit_saved').html(item);
		}
		setTimeout(function(){
			$('.editidentifier.edit_saved').removeClass('edit_saved');
		},1500);
		enableEditParticipant();
	});
}
function editPartPhone(thisPartId) {
	var phone = trim($('#partNewPhone_'+thisPartId).val());
	$.post(app_path_webroot+'Surveys/edit_participant.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id, { phone: phone, participant_id: thisPartId }, function(data){
		var json_data = jQuery.parseJSON(data);
		var item = json_data.item;
		if (item.length < 1) item = '&nbsp;';
		// Loop through all instances of the same participant
		var partArray = json_data.participant_id;
		for (var i = 0; i < partArray.length; i++) {
			$('#editphone_'+partArray[i]).addClass('edit_saved').html(item);
		}
		setTimeout(function(){
			$('.editphone.edit_saved').removeClass('edit_saved');
		},1500);
		enableEditParticipant();
	});
}

// Submit the email form
function sendEmailsSubmit(survey_id,event_id) {
	var dlg_id = 'invites_sent_confirm_dialog';
	showProgress(1);
	$.post(app_path_webroot+"Surveys/email_participants.php?pid="+pid+"&survey_id="+survey_id+"&event_id="+event_id, $('#emailPartForm').serializeObject(), function(data) {
		// Hide email form dialog
		if ($('#emailPart').hasClass('ui-dialog-content')) $('#emailPart').dialog('close');
		if ($('#reschedule-reminder-dialog').hasClass('ui-dialog-content')) $('#reschedule-reminder-dialog').dialog('close');
		showProgress(0,0);
		// Start reloading the participant list underneath dialog
		loadPartList(survey_id, event_id, $('#pageNumSelect').val());
		// Display confirmation dialog
		initDialog(dlg_id);
		$('#'+dlg_id).html(data);
		// Take title from inside of dialog and remove it from dialog content
		var dlg_title = $('#'+dlg_id+' h3:first').html();
		$('#'+dlg_id+' h3:first').remove();
		simpleDialog(null,dlg_title,dlg_id,550);
		// Remove the hidden input field that was added to the form right before submission
		$('#emailPartForm input[name="participants"]').remove();
	});
}

// Enable editing of Invitation Preference
function enableEditInvPref(ob) {
	ob.mouseenter(function(){
		$(this).css('cursor','pointer');
		$(this).addClass('edit_active');
		$(this).prop('title','Click to edit preference');
	}).mouseleave(function() {
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
	});
	ob.click(function(){
		// Undo css
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
		$(this).unbind('click');
		// Show/hide things and set values of hidden elements
		$('#partInvPrefSaved').hide();
		$('#partInvPrefPartId').val($(this).attr('part'));
		$('#partInvPrefRecord').val($(this).attr('rec'));
		$('#partInvPref').val($(this).attr('pref')).prop('disabled', false);
		$('#invPrefPopup button').button('enable');
		$('#invPrefPopup a').removeClass('opacity35');
		// Determine where to put the box and then display it
		var cell = $(this).parent().parent();
		var cellpos = cell.offset();
		var invPrefPopup = $('#invPrefPopup');
		invPrefPopup.css({ 'left': cellpos.left - $('#west').outerWidth(true) - (invPrefPopup.outerWidth(true) - cell.outerWidth(true))/2, 'top': cellpos.top + cell.outerHeight(true) });
		invPrefPopup.fadeIn('slow');
	});
}

// Change participant's delivery preference for Twilio voice/SMS
function changeInvPref(survey_id, event_id) {
	var participant_id = $('#partInvPrefPartId').val();
	var delivery_preference = $('#partInvPref').val();
	$.post(app_path_webroot+"Surveys/change_delivery_preference.php?pid="+pid,{ delivery_preference: delivery_preference,
		record: $('#partInvPrefRecord').val(), participant_id: participant_id, survey_id: survey_id, event_id: event_id },function(data) {
		if (data == '' || data == '0') {
			alert(woops);
		} else {
			var this_image = $('#editinvpref_'+participant_id);
			this_image.html(data).attr('pref', delivery_preference);
			enableEditInvPref(this_image);
			$('#partInvPrefSaved').show();
			setTimeout(function(){
				$('#invPrefPopup').fadeOut('slow');
			},1000);
			$('#partInvPref').prop('disabled', true);
			$('#invPrefPopup button').button('disable');
			$('#invPrefPopup a').addClass('opacity35');
		}
	});
}

// Open dialog to change Link Expiration time (time limit)
function changeLinkExpiration(participant_id) {
	$.post(app_path_webroot+"index.php?pid="+pid+"&route=SurveyController:changeLinkExpiration",{ participant_id: participant_id, action: 'view' },function(data) {
		if (data == '' || data == '0') {
			alert(woops);
		} else {
			// Display dialog
			simpleDialog(data,langLinkExpire1,'linkExpirationDialog',650,null,langCancel,function(){
				showProgress(1);
				changeLinkExpirationSave(participant_id);
			},langSave);
			// Copy the email address into the div below the input for context
			var partContent = $('#editemail_'+participant_id).html();
			if ($('#editidentifier_'+participant_id).length && !$('#editidentifier_'+participant_id).hasClass('partIdentColDisabled')) {
				var partIdent = trim($('#editidentifier_'+participant_id).html());
				if (partIdent != '' && partIdent != '&nbsp;') {
					partContent += ' ('+$('#editidentifier_'+participant_id).html()+')'; 
				}
			}
			$('#changeLinkExpirationEmailDup').html(partContent);
			// Enable link expiration datetime picker
			$('#time_limit_expiration').datetimepicker({
				onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
				buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
				hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
				showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
			});
		}
	});
}

// Change Link Expiration time (time limit)
function changeLinkExpirationSave(participant_id) {
	$.post(app_path_webroot+"index.php?pid="+pid+"&route=SurveyController:changeLinkExpiration",{ time_limit_expiration: $('#time_limit_expiration').val(), participant_id: participant_id, action: 'save' },function(data) {
		if (data == '' || data == '0') {
			alert(woops);
		} else {
			var pageNum = $('#pageNumSelect').length ? $('#pageNumSelect').val() : 1;
			loadPartList(survey_id,event_id,pageNum);
			showProgress(0,0);
			simpleDialog(data,langLinkExpire1,'linkExpirationSuccess');
			setTimeout(function(){
				$('#linkExpirationSuccess').dialog('option', 'hide', 'fade');
				$('#linkExpirationSuccess').dialog('close');
			},3000);
		}
	});
}

// Save reCaptcha setting
function enableCaptcha(enable) {
    $.post(app_path_webroot+"index.php?pid="+pid+"&route=SurveyController:enableCaptcha",{ enable: (enable ? '1' : '0') },function(data) {
        if (data != '1') {
            alert(woops);
        } else {
        	$('#captchaSavedMsg').show();
            setTimeout(function(){
                $('#captchaSavedMsg').hide();
            },3000);
        }
    });
}

// Send email to oneself with survey link
function sendSelfEmail(survey_id,url) {
	$.get(app_path_webroot+'Surveys/email_self.php', { pid: pid, survey_id: survey_id, url: url }, function(data) {
		if (data != '0') {
			simpleDialog('The survey link was successfully emailed to '+data,'Email sent!');
		} else {
			alert(woops);
		}
	});
}