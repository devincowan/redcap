$(function(){
	// If table is not disabled, then add dynamic elements for making edits to instruments (reordering, popup tooltips)
	if (!disable_instrument_table) {
		var i = 1;
		$("#table-forms_surveys tr").each(function() {
			$(this.cells[0]).addClass('dragHandle');
			$(this).prop("id","row_"+i);
			i++;
		});
		// Modify form order: Enable drag-n-drop on table
		$('#table-forms_surveys').tableDnD({
			onDrop: function(table, row) {
				// Remove "add form" button rows, if displayed
				$('.addNewInstrRow').remove();
				// Loop through table
				var i = 1;
				var forms = "";
				var this_form = trim($(row.cells[0]).text());
				$("#table-forms_surveys tr").each(function() {
					// Restripe table
					$(this).removeClass('erow');
					if (i%2 == 0) $(this).addClass('erow');
					i++;
					// Gather form_names
					forms += trim($(this.cells[0]).text()) + ",";
				});
				// Show success
				$('#savedMove-'+this_form).show();
				setTimeout(function(){
					$('#savedMove-'+this_form).hide();
				},2500);
				// Save form order
				$.post(app_path_webroot+'Design/update_form_order.php?pid='+pid, { forms: forms }, function(data){
					if (data != '1' && data != '2') {
						alert(woops);
					}
					// Give conformation and reload page to update the left-hand menu
					else if (status < 1 && !longitudinal) {
						setTimeout(function(){
							simpleDialogAlt(form_moved_msg, 2.5, 400, 'window.location.reload();');
						},500);
					}
				});
			},
			dragHandle: "dragHandle"
		});
		// Create mouseover image for drag-n-drop action and enable button fading on row hover
		$("#table-forms_surveys tr").mouseenter(function() {
			$(this.cells[0]).css('background','#ffffff url("'+app_path_images+'updown.gif") no-repeat center');
			$(this.cells[0]).css('cursor','move');
		}).mouseleave(function() {
			$(this.cells[0]).css('background','');
			$(this.cells[0]).css('cursor','');
		});
		// Set up drag-n-drop pop-up tooltip
		$("#forms_surveys .hDiv .hDivBox tr").find("th:first").each(function() {
			$(this).prop('title',langDrag);
			$(this).tooltip2({ tipClass: 'tooltip4sm', position: 'top center', offset: [25,0], predelay: 100, delay: 0, effect: 'fade' });
		});
		$('.dragHandle').mouseenter(function() {
			$("#forms_surveys .hDiv .hDivBox tr").find("th:first").trigger('mouseover');
		}).mouseleave(function() {
			$("#forms_surveys .hDiv .hDivBox tr").find("th:first").trigger('mouseout');
		});
		// Set up formname mouseover pop-up tooltip
		$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(1)").each(function() {
			$(this).prop('title','<b>'+langClickRowMod+'</b><br>'+langAddNewFlds);
			$(this).tooltip2({ tipClass: 'tooltip4', position: 'top center', offset: [25,0], predelay: 100, delay: 0, effect: 'fade' });
		});
		$('.formLink').mouseenter(function() {
			$(this).find(".instrEdtIcon").show();
			$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(1)").trigger('mouseover');
		}).mouseleave(function() {
			$(this).find(".instrEdtIcon").hide();
			$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(1)").trigger('mouseout');
		});
	}

	// Set up "modify survey settings" pop-up tooltip
	if (surveys_enabled > 0) {
		$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(4)").each(function() {
			$(this).prop('title',langModSurvey);
			$(this).tooltip2({ tipClass: 'tooltip4sm', position: 'top center', offset: [12,0], predelay: 100, delay: 0, effect: 'fade' });
		});
		$('.modsurvstg').mouseenter(function() {
			$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(4)").trigger('mouseover');
			$(this).parent().css({'background-image':'url("'+app_path_images+'pencil_small2.png")','background-repeat':'no-repeat',
				'background-position':'50px center'});
		}).mouseleave(function() {
			$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(4)").trigger('mouseout');
			$(this).parent().css({'background-image':''});
		});
	}

	// Set up "download PDF" pop-up tooltip
	$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(3)").each(function() {
		$(this).prop('title',langDownloadPdf);
		$(this).tooltip2({ tipClass: 'tooltip4sm', position: 'top center', offset: [12,0], predelay: 100, delay: 0, effect: 'fade' });
	});
	$('.pdficon').mouseenter(function() {
		$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(3)").trigger('mouseover');
	}).mouseleave(function() {
		$("#forms_surveys .hDiv .hDivBox tr").find("th:eq(3)").trigger('mouseout');
	});

	// If "autoInviteClick" exists in query string, then click that button for Automated Invitations (longitudinal only)
	if (longitudinal && getParameterByName('autoInviteClick') != '') {
		$('#autoInviteBtn-'+getParameterByName('autoInviteClick')).click();
	}

	// Initialize button drop-down(s) for Instrument Actions
	$('#formActionDropdown').menu();
	$('#formActionDropdownDiv ul li a').click(function(){
		$('#formActionDropdownDiv').hide();
	});
});

// Get form name for given row in Online Designer
function saveFormODrow(form) {
	$('#ActionCurrentForm').val(form);
}

// Displays "add here" button to add new forms in Online Form Editor
function showAddForm() {
	if ($('.addNewInstrRow').length) {
		$('.addNewInstrRow').remove();
	} else {
		// Check to make sure at least one form exists
		var colCount = $("#table-forms_surveys tr:first td").length;
		var rowCount = $("#table-forms_surveys tr").length;
		if (rowCount > 0) {
			$("#table-forms_surveys tr").each(function() {
				var form_name = trim($(this.cells[0]).text());
				$(this).after("<tr class='addNewInstrRow' style='display:none;'><td id='new-"+form_name+"' class='darkgreen' colspan='"+colCount+"' style='font-size:12px;border:0;border-bottom:1px solid #A5CC7A;border-top:1px solid #A5CC7A;padding:10px;'>"
							+ "<button onclick=\"addNewFormReveal('"+form_name+"')\" class=\"jqbuttonmed addInstrBtn\" style=''><img src='"+app_path_images+"plus_small2.png'> <span style='vertical-align:middle;'>"+langAddInstHere+"</span></button>"
							+ "</td></td></tr>");
			});
			$('.addNewInstrRow').show('fade');
			initWidgets();
		} else {
			$("#table-forms_surveys").html("<tr class='addNewInstrRow'><td id='new-' style='border:0;border-bottom:1px solid #ccc;border-top:1px solid #ccc;padding:5px;background-color:#E8ECF0;width:720px;'></td></tr>");
			addNewFormReveal('');
		}
	}
}

// Navigate user to Design page when adding new data entry form via Online Form Builder
function addNewFormReveal(form_name) {
	$('#new-'+form_name).html('<span style="margin:0 5px 0 25px;font-weight:bold;">'+langNewInstName+'</span>&nbsp; '
		+ '<input type="text" class="x-form-text x-form-field" style="font-size:13px;" id="new_form-'+form_name+'"> '
		+ '<input type="button" value="'+langCreate+'" class="jqbuttonmed" onclick=\'addNewForm("'+form_name+'")\' style="font-size:12px;padding: 2px 8px;">'
		+ '<span style="padding-left:10px;"><a href="javascript:;" style="font-size:12px;text-decoration:underline;" onclick="showAddForm()">'+langCancel+'</a></span>');
	setCaretToEnd(document.getElementById('new_form-'+form_name));
	initWidgets();
}
function addNewForm(form_name) {
	var newForm = $('#new_form-'+form_name).val();
	// if (checkIsTwoByte(newForm)) {
		// simpleDialog(langRemove2Bchar);
		// return;
	// }
	// Remove unwanted characters
	$('#new_form-'+form_name).val(newForm.replace(/^\s+|\s+$/g,''));
	if (newForm.length < 1) {
		simpleDialog(langProvideInstName);
		return;
	}
	// Save form via ajax
	$.post(app_path_webroot+'Design/create_form.php?pid='+pid, { form_name: newForm, after_form: form_name },function(data){
		if (data != '1') {
			alert(woops);
		} else {
			showProgress(1);window.location.reload();
		}
	});
}

//For editing the menu description of a form name
function setFormMenuDescription(form_name,askChangeSurveyTitle) {
	if (askChangeSurveyTitle == null) askChangeSurveyTitle = 0;
	if ($('#form_menu_description_input-'+form_name).val().length < 1) {
		alert('Please enter a value for the form name');
		return false;
	}
	// else if (checkIsTwoByte($('#form_menu_description_input-'+form_name).val())) {
		// alert('Please remove two-byte characters');
		// return false;
	// }
	document.getElementById('progress-'+form_name).style.visibility = 'visible';
	var this_value = document.getElementById('form_menu_description_input-'+form_name).value;
	document.getElementById('form_menu_description_input-'+form_name).disabled = true;
	document.getElementById('form_menu_save_btn-'+form_name).disabled = true;
	$.post(app_path_webroot+'Design/set_form_name.php?pid='+pid, { page: form_name, action: 'set_menu_name', menu_description: this_value },
		function(data) {
			var formArray = data.split("\n");
			var new_form = formArray[0];
			var new_form_menu = formArray[1];
			var survey_title = formArray[2];
			document.getElementById('formlabel-'+form_name).innerHTML = new_form_menu;
			document.getElementById('formlabel-'+form_name).style.display = '';
			document.getElementById('form_menu_description_input_span-'+form_name).style.display = 'none';
			document.getElementById('progress-'+form_name).style.visibility = 'hidden';
			document.getElementById('form_menu_description_input-'+form_name).disabled = false;
			document.getElementById('form_menu_save_btn-'+form_name).disabled = false;
			// If instrument is a survey, prompt to also change survey title to this value
			if (askChangeSurveyTitle && survey_title != new_form_menu) {
				simpleDialog(langSetSurveyTitleAsForm1+' "<b>'+new_form_menu+'</b>"'+langQuestionMark+" "
					+langSetSurveyTitleAsForm2+' "'+survey_title+'"'+langPeriod, langSetSurveyTitleAsForm3,null,null,"window.location.reload();",langNo,"setSurveyTitleAsFormLabel('"+new_form+"');",langSetSurveyTitleAsForm4);
				return;
			}
			// Change menu label and reload page (if in development only)
			if (status == 0) {
				if (document.getElementById('form['+form_name+']') != null) {
					document.getElementById('form['+form_name+']').innerHTML = new_form_menu;
				}
				window.location.reload();
			}
		}
	);
}

// Set the survey title to be the same as the form label value
function setSurveyTitleAsFormLabel(form) {
	$.post(app_path_webroot+'Design/set_survey_title_as_form_name.php?pid='+pid,{ form: form },function(data){
		if (data == '0') {
			alert(woops);
		} else {
			simpleDialog(langSetSurveyTitleAsForm5+' "<b>'+data+'</b>"'+langPeriod,langSetSurveyTitleAsForm6,null,null,"window.location.reload();");
		}
	});
}

// Open dialog to set up conditional invitation settings for this survey/event
function setUpConditionalInvites(survey_id, event_id, form) {
	// Remove any enabled rich text editors
	tinymce.remove('.mceEditor');
	// Set URL for ajax request
	var url = app_path_webroot+'Surveys/automated_invitations_setup.php?pid='+pid+'&survey_id='+survey_id+'&event_id='+event_id;
	// If longitudinal and event_id=0, then prompt user to select events first
	if (event_id == 0) {
		automatedInvitesSelectEvent(survey_id, event_id, form);
		return;
	}
	// Ajax request
	$.post(url, { action: 'view' },function(data){
		if (data == "0") { alert(woops); return; }
		// Set dialog title/content
		var json_data = jQuery.parseJSON(data);
		if (json_data.response == "0") { alert(woops); return; }
		var dialogId = 'popupSetUpCondInvites';
		initDialog(dialogId);
		var dialogOb = $('#'+dialogId);
		dialogOb.prop("title",json_data.popupTitle).html(json_data.popupContent);
		initWidgets();
		// Open dialog
		dialogOb.dialog({ bgiframe: true, modal: true, width: 1150, open:function(){fitDialog(this);}, buttons: [
			{ text: langASI.import_button1, click: function () { $(this).dialog('destroy'); } },
			{ text: langASI.save_and_clone_button, click: function () {
				// Set survey_id-event_id pair
				var se_id = survey_id+'-'+event_id;
				$.when(
					validate_auto_invite_logic($('#sscondlogic-'+se_id)),
					saveCondInviteSetup(survey_id,event_id,form)
				).done(function(response1, response2){
					// check if the AutomatedSurveyInvitationTool is present
					if(AutomatedSurveyInvitationTool)
						AutomatedSurveyInvitationTool.showCloneDialog(survey_id, event_id);
				}).fail(function(response1, response2){
					console.log(response1, response2);
				});
			} },
			{ text: langASI.save_button, click: function () {
				// Set survey_id-event_id pair
				var se_id = survey_id+'-'+event_id;
				// Check values and save via ajax
				if ($('#sscondoption-logic-'+se_id).prop('checked') && $('#sscondlogic-'+se_id).val() != '') {
					var logicNotValid = checkLogicErrors($('#sscondlogic-'+se_id).val(),1);
					if (logicNotValid){
						// Syntax error in logic
						return;
					} else {
						// Validation via ajax for deeper look. Save on success.
						validate_auto_invite_logic($('#sscondlogic-'+se_id),"saveCondInviteSetupWrapper("+survey_id+","+event_id+",'"+form+"');");
					}
				} else {
					// Save it
                    saveCondInviteSetupWrapper(survey_id,event_id,form);
				}
			} }
		],
            open: function() {
                $buttonPane = $(this).next();
                $buttonPane.find('button:last').addClass('ui-priority-primary');
            }
		});
		fitDialog($('#'+dialogId));
		// Enable sendtime datetime picker
		$('#popupSetUpCondInvites .ssdt').datetimepicker({
			onClose: function(dateText, inst){ $('#'+$(inst).attr('id')).blur(); },
			buttonText: 'Click to select a date', yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
			hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
			showOn: 'button', buttonImage: app_path_images+'datetime.png', buttonImageOnly: true, timeFormat: 'hh:mm', constrainInput: false
		});
		// Survey Reminder related setup
		initSurveyReminderSettings();
		// Run the function to show/hide notes about Subject/Message not being applicable when Part Pref has been selected as Delivery type
		setInviteDeliveryMethod($('#popupSetUpCondInvites select[name="delivery_type"]'));
		// Set rich text editor
		var email_message = $('#'+dialogId+' #ssemail-'+survey_id+'-'+event_id).val();
		if (!REDCap.isTinyMCESupported()) {
			// Can't using TinyMCE with old browsers - add line breaks back to message
			$('#'+dialogId+' #ssemail-'+survey_id+'-'+event_id).val( email_message.replace(/<br\s*[\/]?>\s?/gi,"\n") );
		} else {
			$('#'+dialogId+' #ssemail-'+survey_id+'-'+event_id).val(email_message);
			initTinyMCEglobal('mceEditor');
		}
		$('#'+dialogId+' [data-toggle="popover"]').popover();
	});
}

// Wrapper function for saveCondInviteSetup
function saveCondInviteSetupWrapper(survey_id,event_id,form) {
    saveCondInviteSetup(survey_id, event_id, form).done(function (json_data) {
        // Hide dialog (if displayed)
        if ($('#popupSetUpCondInvites').hasClass('ui-dialog-content')) $('#popupSetUpCondInvites').dialog('destroy');
        // Display popup (if specified)
        if (json_data.popupContent.length > 0) {
            // Set the onclose javascript to reload the event list for longitudinal projects
            var oncloseJS = (longitudinal) ? "window.location.href=app_path_webroot+page+'?pid='+pid+'&autoInviteClick=" + form + "';" : "window.location.reload();";
            // Simple dialog to display confirmation
            simpleDialog(json_data.popupContent, json_data.popupTitle, null, 600, oncloseJS);
        }
    });
}

// Auto survey invites: save settings via ajax
function saveCondInviteSetup(survey_id,event_id,form) {
	// Set survey_id-event_id pair
	var se_id = survey_id+'-'+event_id;
	// Set initial values
	var delivery_type = $('select[name="delivery_type"]').val();
	$('#sscondlogic-'+se_id).val( trim($('#sscondlogic-'+se_id).val()) );
	$('#sssubj-'+se_id).val( trim($('#sssubj-'+se_id).val()) );
	$('#ssemail-' + se_id).val($('#ssemail-' + se_id).val().trim());
	if (REDCap.isTinyMCESupported()) {
		// Remove all line breaks (in case pre-rich text value) because rich text editor does not have any
		$('#ssemail-' + se_id).val($('#ssemail-' + se_id).val().replace(/(\r\n|\n|\r)/gm, ""));
	}
	var condition_send_time_option = $('input[name="sscondwhen-'+se_id+'"]:checked').val();
	var condition_send_time_exact = '';
	var condition_surveycomplete_survey_id = '';
	var condition_surveycomplete_event_id = '';
	var condition_andor = '';
	var condition_logic = '';
	var condition_send_next_day_type = '';
	var condition_send_next_time = '';
	var condition_send_time_lag_days = '';
	var condition_send_time_lag_hours = '';
	var condition_send_time_lag_minutes = '';
	var condition_send_time_lag_field = '';
	var condition_send_time_lag_field_after = 'after';
	var condition_andor = $('#sscondoption-andor-'+se_id).val();
	var reminder_type = $('#reminders_choices_div input[name="reminder_type"]:checked').val();
	if (reminder_type == null || !$('#enable_reminders_chk').prop('checked')) reminder_type = '';
	var reminder_timelag_days = '';
	var reminder_timelag_hours = '';
	var reminder_timelag_minutes = '';
	var reminder_nextday_type = '';
	var reminder_nexttime = '';
	var reminder_exact_time = '';
	var reminder_num = '0';
	var reeval_before_send = ($('#sscondlogic-'+se_id).val() != '' && $('#sscondoption-reeval_before_send-'+se_id).prop('checked')) ? '1' : '0';
	
	var dfd = $.Deferred();
	
	// Error checking to make sure all elements in row have been set
	if ($('input[name="ssactive-'+se_id+'"]:checked').val() == '1') {
		if ($('#sscondoption-surveycomplete-'+se_id).prop('checked') && $('#sscondoption-surveycompleteids-'+se_id).val() == '') {
			simpleDialog(langAutoInvite5);
			return dfd.reject(langAutoInvite5);
		} else if (!$('#sscondoption-surveycomplete-'+se_id).prop('checked') && !$('#sscondoption-logic-'+se_id).prop('checked')) {
			simpleDialog(langAutoInvite6);
			return dfd.reject(langAutoInvite6);
		} else if ($('#sscondoption-logic-'+se_id).prop('checked') && $('#sscondlogic-'+se_id).val() == '') {
			simpleDialog(langAutoInvite7);
			return dfd.reject(langAutoInvite7);
		}
		if (condition_send_time_option == null) {
			simpleDialog(langAutoInvite8);
			return dfd.reject(langAutoInvite8);
		} else if (condition_send_time_option == 'NEXT_OCCURRENCE' &&
			($('#sscond-nextdaytype-'+se_id).val() == '' || $('#sscond-nexttime-'+se_id).val() == '')) {
			simpleDialog(langAutoInvite9);
			return dfd.reject(langAutoInvite9);
		} else if (condition_send_time_option == 'TIME_LAG' && $('#sscond-timelagfield-'+se_id).val() == '' &&
			$('#sscond-timelagdays-'+se_id).val() == '' && $('#sscond-timelaghours-'+se_id).val() == '' && $('#sscond-timelagminutes-'+se_id).val() == '') {
			simpleDialog(langAutoInvite10);
			return dfd.reject(langAutoInvite10);
		} else if (condition_send_time_option == 'EXACT_TIME' && $('#ssdt-'+se_id).val() == '') {
			simpleDialog(langAutoInvite11);
			return dfd.reject(langAutoInvite11);
		}
	} else if ($('input[name="ssactive-'+se_id+'"]:checked').val() == null) {
		simpleDialog(langAutoInvite12);
		return dfd.reject(langAutoInvite12);
	}
	// Check reminder options
	if (!validateSurveyRemindersOptions()) return dfd.reject(false);

	// Collect values needed for ajax save
	if ($('#sscondoption-surveycomplete-'+se_id).prop('checked')) {
		var condSurvEvtIds = $('#sscondoption-surveycompleteids-'+se_id).val().split('-');
		condition_surveycomplete_survey_id = condSurvEvtIds[0];
		condition_surveycomplete_event_id = condSurvEvtIds[1];
	}
	if ($('#sscondoption-logic-'+se_id).prop('checked')) {
		condition_logic = $('#sscondlogic-'+se_id).val();
	}
	if (condition_send_time_option == 'NEXT_OCCURRENCE') {
		condition_send_next_day_type = $('#sscond-nextdaytype-'+se_id).val();
		condition_send_next_time = $('#sscond-nexttime-'+se_id).val();
	} else if (condition_send_time_option == 'TIME_LAG') {
		condition_send_time_lag_days = ($('#sscond-timelagdays-'+se_id).val() == '') ? '0' : $('#sscond-timelagdays-'+se_id).val();
		condition_send_time_lag_hours = ($('#sscond-timelaghours-'+se_id).val() == '') ? '0' : $('#sscond-timelaghours-'+se_id).val();
		condition_send_time_lag_minutes = ($('#sscond-timelagminutes-'+se_id).val() == '') ? '0' : $('#sscond-timelagminutes-'+se_id).val();
		condition_send_time_lag_field = $('#sscond-timelagfield-'+se_id).length ? $('#sscond-timelagfield-'+se_id).val() : '';
		condition_send_time_lag_field_after = $('#sscond-timelagfieldafter-'+se_id).length ? $('#sscond-timelagfieldafter-'+se_id).val() : 'after';
	} else if (condition_send_time_option == 'EXACT_TIME') {
		condition_send_time_exact = ($('#ssdt-'+se_id).val() == '') ? '' : $('#ssdt-'+se_id).val();
	}
	var active = ($('input[name="ssactive-'+se_id+'"]:checked').val() == '0') ? '0' : '1';
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

	showProgress(1);
	// Save via ajax
	$.post(app_path_webroot+'Surveys/automated_invitations_setup.php?pid='+pid+'&event_id='+event_id+'&survey_id='+survey_id, {
		action: 'save', email_subject: $('#sssubj-'+se_id).val(), email_content: $('#ssemail-'+se_id).val(),
		email_sender: $('#email_sender').val(), email_sender_display: $('#email_sender_display').val(), active: active,
		condition_send_time_exact: condition_send_time_exact, condition_surveycomplete_survey_id: condition_surveycomplete_survey_id,
		condition_surveycomplete_event_id: condition_surveycomplete_event_id, condition_logic: condition_logic,
		condition_send_time_option: condition_send_time_option, condition_send_next_day_type: condition_send_next_day_type,
		condition_send_next_time: condition_send_next_time, condition_send_time_lag_days: condition_send_time_lag_days,
		condition_send_time_lag_hours: condition_send_time_lag_hours, condition_send_time_lag_minutes: condition_send_time_lag_minutes,
		condition_send_time_lag_field: condition_send_time_lag_field,
		condition_send_time_lag_field_after: condition_send_time_lag_field_after,
		condition_andor: condition_andor,
		reminder_type: reminder_type,
		reminder_timelag_days: reminder_timelag_days,
		reminder_timelag_hours: reminder_timelag_hours,
		reminder_timelag_minutes: reminder_timelag_minutes,
		reminder_nextday_type: reminder_nextday_type,
		reminder_nexttime: reminder_nexttime,
		reminder_exact_time: reminder_exact_time,
		reminder_num: reminder_num,
		delivery_type: delivery_type, reeval_before_send: reeval_before_send
		}, function(data){
		var json_data = jQuery.parseJSON(data);
		showProgress(0);
		if (json_data.response == '1') {
            $('#popupSetUpCondInvites').dialog('destroy');
			dfd.resolve(json_data);
		} else {
			// Error
			dfd.reject(data);
			alert(woops);
		}
	});
	return dfd;
}

// When click Automated Invite button for longitudinal projects, open pop-up box to list events to choose from
function automatedInvitesSelectEvent(survey_id,event_id,form) {
	// Set popup object
	var popup = $('#choose_event_div');
	// Redisplay "loading" text and remove any exist events listed from previous opening
	$('#choose_event_div_loading').show();
	$('#choose_event_div_list').html('').hide();
	// Make user pop-up appear
	popup.hide();
	// Determine where to put the box and then display it
	var cell = $('#'+form+'-btns').parent().parent();
	var cellpos = cell.offset();
	popup.css({ 'left': cellpos.left - $('#west').width() - 100,
				'top': cellpos.top + cell.outerHeight(true) - 6 });
	popup.show();
	setProjectFooterPosition();
	// Get pop-up content via ajax before displaying
	$.post(app_path_webroot+'Design/get_events_auto_invites_for_form.php?pid='+pid+'&page='+form+'&survey_id='+survey_id,{ },function(data){
		// Add response data to div
		$('#choose_event_div_loading').hide();
		$('#choose_event_div_list').html(data);
		initWidgets();
		$('#choose_event_div_list').show();
	});
}

// Rename selected data entry form on Design page
function setupRenameForm(form) {
	$('#formlabel-'+form+', #formlabeladapt-'+form).hide();
	$('#form_menu_description_input_span-'+form).show();
	setCaretToEnd(document.getElementById('form_menu_description_input-'+form));
}

// Delete selected data entry form on Design page
function deleteForm(form_to_delete) {
	// Don't allow user to delete only form
	if (numForms <= 1) {
		simpleDialog(langCannotDeleteForm, langCannotDeleteForm2);
		return;
	}
	//Set form name to appear in dialog
	var formLabel = trim($('#formlabel-'+form_to_delete).text());
	//Open dialog
	$('#del_dialog_form_name').html(formLabel);
	$('#delete_form_dialog').dialog({ bgiframe: true, modal: true, width: 450, buttons: [
		{ text: langCancel, click: function () { $(this).dialog('close'); } },
		{ text: langYesDelete, click: function () {
			$('#delete_form_dialog').dialog('close');
			$.get(app_path_webroot+'Design/delete_form.php', { pid: pid, form_name: form_to_delete },
				function(data) {
					if (data=='1' || data=='2') {
						// Decrement numForms variable
						numForms--;
						//Delete form row from table
						$("#table-forms_surveys tr").each(function() {
							if (form_to_delete == trim($(this.cells[0]).text())) {
								$(this).remove();
							}
						});
						//Remove form from form menu on left (if in Development only)
						if (status == 0 && document.getElementById('form['+form_to_delete+']') != null) {
							document.getElementById('form['+form_to_delete+']').parentNode.style.display = 'none';
						}
						//simpleDialog(langDeleteFormSuccess,langDeleted,'deleteFormSuccessDlg');
						simpleDialogAlt('<div style="font-size:14px;color:#800000;"><b>'+langDeleted+'</b><br><br>'+langDeleteFormSuccess+'</div>', 2.5, 350);
						if (data == '2') update_pk_msg(true,'form');
						// setTimeout(function(){
							// $('#deleteFormSuccessDlg').dialog('close');
						// },2500);
					} else if (data == '0') {
						alert(woops);
					} else if (data == '3') {
						simpleDialog(langNotDeletedRand);
					}
				}
			);
		}}
	] });
}

// Return boolean if survey-event provided has a dependent survey-event (prevent infinite looping via automated invites)
function hasDependentSurveyEvent(ob) {
	if ($('#dependent-survey-event').length == 0) return false;
	// If not in array, then give error message and reset drop-down value
	if (in_array($(ob).val(), $('#dependent-survey-event').val().split(','))) {
		simpleDialog(langAutoInvite1+" <b>"+$('#'+$(ob).attr('id')+' option:selected').text()
			+"</b> "+langAutoInvite2,
			langAutoInvite3,null,null,"$('#"+$(ob).attr('id')+"').val('');");
	}
}

// Display the pop-up for Triggers & Notifications
function displayTrigNotifyPopup(survey_id) {
	if (survey_id == null) survey_id = '';
	$.post(app_path_webroot+'Surveys/triggers_notifications.php?pid='+pid+'&survey_id='+survey_id,{},function(data){
		if (data=='[]') alert(woops);
		else {
			var json_data = jQuery.parseJSON(data);
			try { $('#surveyNotifySetupDialog').dialog('close'); }catch(e){ }
			$('#surveyNotifySetupDialog').remove();
			simpleDialog(json_data.content,json_data.title,'surveyNotifySetupDialog',750);
			fitDialog($('#surveyNotifySetupDialog'));
		}
	});
}

// Store Triggers & Notifications for end-survey emails
function endSurvTrigSave(user,saveValue,survey_id) {
	$.post(app_path_webroot+'Surveys/triggers_notifications.php?pid='+pid+'&survey_id='+survey_id,{username: user, action: 'endsurvey_email', value: saveValue},function(data){
		if (data=='0') alert(woops);
		else {
			var json_data = jQuery.parseJSON(data);
			// Set icon and save status text
			var saveStatus = $('#triggerEndSurv-svd-'+survey_id+'-'+user);
			var iconEnabled = $('#triggerEnabled_'+survey_id+'-'+user);
			var iconDisabled = $('#triggerDisabled_'+survey_id+'-'+user);
			iconEnabled.hide();
			iconDisabled.hide();
			saveStatus.show();
			setTimeout(function(){
				saveStatus.hide();
				if (saveValue > 0) {
					iconEnabled.show();
				} else {
					iconDisabled.show();
				}
			},1500);
			// Show/hide the check icon in the Survey Notifications button on Online Designer form table
			if (json_data.survey_notifications_enabled == '1') {
				$('#survey_notifications_active').show();
			} else {
				$('#survey_notifications_active').hide();
			}
		}
	});
}

// Display the pop-up for setting up of Survey Queue
function displaySurveyQueueSetupPopup() {
	showProgress(1,0);
	$.post(app_path_webroot+'Surveys/survey_queue_setup.php?pid='+pid,{action: 'view'},function(data){
		showProgress(0,0);
		if (data=='[]') alert(woops);
		else {
			var json_data = jQuery.parseJSON(data);
			// Open dialog
			initDialog('surveyQueueSetupDialog');
			$('#surveyQueueSetupDialog').html(json_data.content);
			$('#surveyQueueSetupDialog').dialog({ title: json_data.title, bgiframe: true, modal: true, width: 850, open:function(){fitDialog(this);}, buttons: [
				{ text: langCancel, click: function () { $(this).dialog('destroy'); } },
				{ text: langSave, click: function () {
					// Loop through each row to find errors before submitting
					var errmsg = '';
					$('form#survey_queue_form table.form_border tr').each(function(){
						var row = $(this);
						if (row.attr('id') != null) {
							var trpc = row.attr('id').split('-');
							var sid = trpc[1];
							var eid = trpc[2];
							if ($('#sqactive-'+sid+'-'+eid).prop('checked') && $('#sqcondoption-surveycompleteids-'+sid+'-'+eid).val() == ''
								&& $('#sqcondlogic-'+sid+'-'+eid).val() == '') {
								errmsg += '<div style="font-weight:bold;margin:2px 0;"> &bull; '+row.find('td:eq(1)').text()+'</div>';
							}
						}
					});
					// Display errors and stop (if there are errors)
					if (errmsg != '') {
						simpleDialog('<b>'+langErrorColon+'</b> '+langSurveyQueue1+'<br><br>'+errmsg);
						return false;
					}
					// Disable dialog buttons
					$('#surveyQueueSetupDialog').parent().find('div.ui-dialog-buttonpane button').button('disable');
					// Save the values
					saveSurveyQueueSetupPopup();
				}
			}] });
			initWidgets();
			// Hide Save button if no surveys are displays as applicable in the queue
			if (!$('form#survey_queue_form').length) {
				$('#surveyQueueSetupDialog').parent().find('div.ui-dialog-buttonpane').hide();
			} else {
				// Add bold to Save button
				$('#surveyQueueSetupDialog').parent().find('div.ui-dialog-buttonpane button:eq(1)').css({'font-weight':'bold','color':'#222'});
			}
		}
	});
}

// Save the values in the pop-up when setting up of Survey Queue
function saveSurveyQueueSetupPopup() {
	// Remove disabled flag from all input elements so that their values get saved
	$('form#survey_queue_form input, form#survey_queue_form select').prop('disabled', false);
	// Get all form values
	var json_ob = $('form#survey_queue_form').serializeObject();
	json_ob.action = 'save';
	// Save via ajax
	$.post(app_path_webroot+'Surveys/survey_queue_setup.php?pid='+pid, json_ob,function(data){
		if (data=='[]') alert(woops);
		else {
			var json_data = jQuery.parseJSON(data);
			if ($('#surveyQueueSetupDialog').hasClass('ui-dialog-content')) $('#surveyQueueSetupDialog').dialog('destroy');
			simpleDialog(json_data.content,json_data.title);
			// Show/hide the check icon in the survey queue button on Online Designer form table
			if (json_data.survey_queue_enabled == '1') {
				$('#survey_queue_active').show();
			} else {
				$('#survey_queue_active').hide();
			}
		}
	});
}

// Survey Queue setup: Adjust bgcolor of cells and inputs when activating/deactivating a survey
function surveyQueueSetupActivate(activate, survey_id, event_id) {
	if (activate) {
		// Activate this survey
		$('#sqtr-'+survey_id+'-'+event_id+' td').removeClass('opacity35').addClass('darkgreen');
		// Enable all inputs
		$('#sqtr-'+survey_id+'-'+event_id+' textarea, #sqtr-'+survey_id+'-'+event_id+' input, #sqtr-'+survey_id+'-'+event_id+' select').prop('disabled', false);
		$('#sqactive-'+survey_id+'-'+event_id).prop('checked', true);
		// Show/hide activation icons/text
		$('#div_sq_icon_enabled-'+survey_id+'-'+event_id).show();
		$('#div_sq_icon_disabled-'+survey_id+'-'+event_id).hide();
	} else {
		// Deactivate this survey
		// Remove bgcolors
		$('#sqtr-'+survey_id+'-'+event_id+' td').removeClass('darkgreen');
		$('#sqtr-'+survey_id+'-'+event_id+' td:eq(2), #sqtr-'+survey_id+'-'+event_id+' td:eq(3)').addClass('opacity35');
		// Disable all inputs and remove their values
		$('#sqcondoption-surveycompleteids-'+survey_id+'-'+event_id+', #sqcondlogic-'+survey_id+'-'+event_id).val('');
		$('#sqcondoption-andor-'+survey_id+'-'+event_id).val('AND');
		$('#sqtr-'+survey_id+'-'+event_id+' input[type="checkbox"]').prop('checked', false);
		$('#sqtr-'+survey_id+'-'+event_id+' textarea, #sqtr-'+survey_id+'-'+event_id+' input, #sqtr-'+survey_id+'-'+event_id+' select').prop('disabled', true);
		$('#sqactive-'+survey_id+'-'+event_id).prop('checked', false);
		// Show/hide activation icons/text
		$('#div_sq_icon_enabled-'+survey_id+'-'+event_id).hide();
		$('#div_sq_icon_disabled-'+survey_id+'-'+event_id).show();
	}
}

// Validate Survey Login setup form
function validationSurveyLoginSetupForm() {
	// Make sure all visible fields have a value
	var fe = 0;
	$('.survey-login-field:visible').each(function(){
		if ($(this).val() == '') {
			fe++;
		}
	});
	if (fe > 0) {
		simpleDialog(langSurveyLogin1);
		return true;
	}
	// Make sure they've entered custom error msg
	if (trim($('textarea[name="survey_auth_custom_message"]').val()) == '') {
		simpleDialog(langSurveyLogin2);
		return true;
	}
	// If only 1 of 2 failed login fields were entered
	var failedLoginFieldsEntered = ($('input[name="survey_auth_fail_limit"]').val() == '' ? 0 : 1) + ($('input[name="survey_auth_fail_window"]').val() == '' ? 0 : 1);
	if (failedLoginFieldsEntered == 1) {
		simpleDialog(langSurveyLogin3);
		return true;
	}
	return false;
}

// Display the Survey Login setup dialog
function showSurveyLoginSetupDialog() {
	// Call ajax to load dialog content
	var url = app_path_webroot+'Design/survey_login_setup.php?pid='+pid;
	$.post(url,{ action: 'view' },function(data){
		if (data == '0') {
			alert(woops);
		} else {
			var json_data = jQuery.parseJSON(data);
			// Display dialog
			initDialog('survey_login_setup_dialog');
			$('#survey_login_setup_dialog').html(json_data.content).dialog({ title: json_data.title, bgiframe: true, modal: true,
				width: 850, open:function(){fitDialog(this);}, buttons: [
				{ text: json_data.cancel_btn, click: function () { $(this).dialog('destroy'); } },
				{ text: json_data.save_btn, click: function () {
					// Validate form
					if (validationSurveyLoginSetupForm()) return false;
					// Save form via ajax
					$.post(url, $('#survey_login_setup_form').serializeObject(), function(data){
						if (data == '0') {
							alert(woops);
						} else {
							// Successfully saved
							$('#survey_login_setup_dialog').dialog('destroy');
							var json_data2 = jQuery.parseJSON(data);
							simpleDialog(json_data2.content,json_data2.title);
							// If login is enabled, then make sure we show the small tick icon
							if (json_data2.login_enabled == '1') {
								$('#survey_login_active').show();
							} else {
								$('#survey_login_active').hide();
							}
						}
					});
				}}]
			});
		}
	});
}

// Add another Survey Login field in the setup dialog
function addSurveyLoginFieldInDialog() {
	$('.survey-login-field').not(':visible').eq(0).parents('tr:first').show('fade');
	// If all are visible, then hide all the Add links
	$('.survey-login-field-add').hide();
	if ($('.survey-login-field').not(':visible').length > 0) {
		$('.survey-login-field-add:last').show();
	}
	showHideSurveyLoginFieldDeleteIcon();
}

// Remove Survey Login field in the setup dialog
function removeSurveyLoginFieldInDialog(ob) {
	$(ob).parents('tr:first').hide().find('select:first').val('');
	$('.survey-login-field-add').hide();
	if ($('.survey-login-field:visible').length == 1) {
		$('.survey-login-field-add:first').show();
	} else {
		$('.survey-login-field-add:last').show();
	}
	showHideSurveyLoginFieldDeleteIcon();
}

// Make sure that only the last visible X icon next to the Survey Login field in the setup dialog is displayed
function showHideSurveyLoginFieldDeleteIcon() {
	$('.survey_auth_field_delete').hide();
	if ($('.survey-login-field:visible').length == 3) {
		$('.survey_auth_field_delete:last').show();
	} else {
		$('.survey_auth_field_delete:first').show();
	}
}

// Change color of "survey login enabled" row in dialog to enable Survey Login
function enableSurveyLoginRowColor() {
	var ob = $('#survey_login_setup_dialog select[name="survey_auth_enabled"]');
	var enable = ob.val();
	if (enable == '1') {
		ob.parents('tr:first').children().removeClass('red').addClass('darkgreen');
	} else {
		ob.parents('tr:first').children().removeClass('darkgreen').addClass('red');
	}
}

// Open pop-up for explaining instrument ZIP files
function openZipInstrumentExplainPopup() {
	// Display the popup
	var mydlg = $('#instrument_zip_explain_dialog').dialog({ bgiframe: true, modal: true, width: 650,
		buttons: [
			{ text: langClose, click: function () { $(this).dialog('close'); } }
		]
	});
	// Load the library list via the public website. If fails, then provide link.
	if ($('#external_instrument_list').attr('loaded_list') != '1') {
		// Ajax call to get content
		var thisAjax = $.ajax({
			type: 'GET',
			url: shared_lib_path+'external_library_list.php?json=1',
			crossDomain: true,
			success: function(data) {
				try {
					var json_data = $.parseJSON(data);
					var libs = '';
					// Loop through JSON elements to add them to DIV
					$.each(json_data, function(index, value) {
						libs += '<div style="margin-bottom:10px;padding-bottom:15px;border-bottom:1px solid #ddd;">'
							+ '<div style="float:left;font-weight:bold;font-size:13px;padding-top:6px;">' + value.name	+ '</div>'
							+ '<div style="float:right;padding-bottom:5px;"><button class="jqbuttonmed" onclick="window.open(\'' + value.url + '\', \'_blank\');"><img src="'+app_path_images+'arrow_right_curve.png" style="vertical-align:middle;"> <span style="vertical-align:middle;">' + langUploadInstZip3 + '</span></button></div>'
							+ '<div style="clear:both;font-size:12px;color:#333;">' + value.description	+ '</div>'
						+ '</div>';
					});
					$('#external_instrument_list').html(libs);
					// Set flag so this is not call via ajax again if re-opened
					$('#external_instrument_list').attr('loaded_list', '1');
					// Enable buttons
					initButtonWidgets();
					// Resize the dialog popup, if needed
					fitDialog($('#instrument_zip_explain_dialog'));
					mydlg.dialog('option', 'position', { my: "center", at: "center", of: window});
				} catch(e) {
					displayLinkToExtLibPublicPage();
				}
			},
			error: function(e) {
				displayLinkToExtLibPublicPage();
			}
		});
		// If Ajax call does not return after 5 seconds, then stop and just display link to page
		var maxAjaxTime = 5; // seconds
		setTimeout(function(){
			if (thisAjax.readyState == 1) {
				thisAjax.abort();
				displayLinkToExtLibPublicPage();
			}
		},maxAjaxTime*1000);
	}
}

// Display link to External Instrument Libraries page on public website
function displayLinkToExtLibPublicPage() {
	if ($('#external_instrument_list').attr('loaded_list') != '1') {
		// Set flag so this is not call via ajax again if re-opened
		$('#external_instrument_list').attr('loaded_list', '1');
		// Add content
		var langNoLoadList = '<div style="margin-bottom:10px;">' + langUploadInstZip4	+ '</div>'
			+ '<div style=""><button class="jqbuttonmed" onclick="window.open(\'' + shared_lib_path+'external_library_list.php\', \'_blank\');"><img src="'+app_path_images+'arrow_right_curve.png" style="vertical-align:middle;"> <span style="vertical-align:middle;">' + langUploadInstZip5 + '</span></button></div>';
		$('#external_instrument_list').html(langNoLoadList).effect('highlight',{ },3000);
		initButtonWidgets();
	}
}


// Open pop-up for uploading instrument ZIP files
function openZipInstrumentPopup() {
	$('#div_zip_instrument_in_progress').hide();
	$('#div_zip_instrument_success').hide();
	$('#div_zip_instrument_fail').hide();
	$("#zipInstrumentUploadForm").show();
	$('#myfile').val('');
	$('#zip-instrument-popup').dialog({ bgiframe: true, modal: true, width: 500,
		buttons: [
			{ text: langClose, click: function () { $(this).dialog('close'); } },
			{ text: langUploadInstZip1, click: function () {
				// Make sure a file is selected
				if ($('#myfile').val().length < 1) {
					simpleDialog(langOD32,null,null,300);
					return false;
				}
				// Make sure it's a zip file
				var file_ext = getfileextension(trim($('#myfile').val().toLowerCase()));
				if (file_ext != 'zip') {
					simpleDialog(langUploadInstZip2,null,null,350);
					return false;
				}
				// Upload it
				$(":button:contains('"+langUploadInstZip1+"')").css('display','none');
				$('#div_zip_instrument_in_progress').show();
				$('#zipInstrumentUploadForm').hide();
				$("#zipInstrumentUploadForm").submit();
			} }
		]
	});
}

// Add listener to reload OD page when instrument Zip popup is closed
function reloadPageOnCloseZipPopup(renamed_fields_text) {
	$('#div_zip_instrument_success_dups').html(renamed_fields_text);
	$('#zip-instrument-popup').dialog({ close: function(){ showProgress(1); window.location.reload(); } });
}

// Download instrument zip file for a given form
function downloadInstrumentZip(form, draft_mode) {
	window.location.href = app_path_webroot+"Design/zip_instrument_download.php?pid="+pid+"&page="+form+(draft_mode ? "&draft=1" : "");
}

// Remove any illegal characters from affix name when copying instrument
function filterFieldAffix(temp) {
	temp = trim(temp);
	temp = temp.toLowerCase().replace(/[^a-z0-9]/ig,"_").replace(/[_]+/g,"_");
	while (temp.length > 0 && temp.charAt(temp.length-1) == "_") {
		temp = temp.substr(0,temp.length-1);
	}
	return temp;
}

// Open pop-up for copying instrument
function copyForm(form) {
	// Set form name to appear in dialog
	var form_label = trim($('#formlabel-'+form).text());
	$('#copy_instrument_label').html(form_label);
	$('#copy_instrument_new_name').val(form_label+' 2');
	$('#copy_instrument_affix').val('_v2');
	// Open popup
	$('#copy-instrument-popup').dialog({ bgiframe: true, modal: true, width: 500,
		buttons: [
			{ text: langClose, click: function () { $(this).dialog('close'); } },
			{ text: langCopyInstr, click: function () {
				// Make sure instrument title and affix is given
				$('#copy_instrument_new_name').val( trim($('#copy_instrument_new_name').val()) );
				if ($('#copy_instrument_new_name').val() == '' || $('#copy_instrument_affix').val() == '') {
					simpleDialog(langCopyInstr2);
					return false;
				}
				// Ajax request to copy instrument
				$.post(app_path_webroot+'Design/copy_instrument.php?pid='+pid,{ page:form, form_label: $('#copy_instrument_new_name').val(), affix: $('#copy_instrument_affix').val() }, function(data) {
					if (data == "0") { alert(woops); return; }
					// Set dialog title/content
					try {
						var json_data = jQuery.parseJSON(data);
						$('#copy-instrument-popup').dialog('close');
						//simpleDialog("<div style='color:green;font-size:13px;'><img src='"+app_path_images+"tick.png'> "+langCopyInstr3+"</div>"+json_data.renamed_fields_text, langCopyInstr4, 'copyInstrumentSuccessDlg', null, "window.location.href = app_path_webroot+'Design/online_designer.php?pid='+pid");
						simpleDialogAlt("<div style='color:green;font-size:13px;'><img src='"+app_path_images+"tick.png'> "+langCopyInstr3+"</div>"+json_data.renamed_fields_text, 300, 400);
						setTimeout(function(){
							if (json_data.renamed_fields_text.length == 0) {
								showProgress(1);
								window.location.href = app_path_webroot+'Design/online_designer.php?pid='+pid;
							}
						},3000);
						initWidgets();
					} catch(e) {
						alert(woops);
					}
				});
			} }
		]
	});
}

// Open the dialog for info about Shared Library
function openLibInfoPopup(action_text) {
	$.post(app_path_webroot+'SharedLibrary/info.php?pid='+pid, { action_text: action_text }, function(data){
		// Add dialog content
		if (!$('#sharedLibInfo').length) $('body').append('<div id="sharedLibInfo"></div>');
		$('#sharedLibInfo').html(data);
		$('#sharedLibInfo').dialog({ bgiframe: true, modal: true, width: 650, open: function(){fitDialog(this)},
			buttons: { Close: function() { $(this).dialog('close'); } }, title: 'The REDCap Shared Library'
		});
	});
}