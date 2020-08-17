// Set global variables for adjudication dialog and fetch ajax request
var adjud_dialog, rtws_fetch_ajax;
// Exclude a source value for a given record during the Adjudication process
function excludeValue(md_id, exclude, ob) {
	// exclude = 1 means "exclude" else means "remove exclusion"
	if (exclude == null) exclude = 1;
	// Call web service via ajax
	$.post(app_path_webroot+'DynamicDataPull/exclude.php?pid='+pid, { md_id: md_id, exclude: exclude }, function(data){
		if (data == '0') {
			alert(woops);
		} else {
			// Change the text and onclick attribute for the link
			ob.html(data)
			  .attr('onclick',"excludeValue("+md_id+","+(exclude==1?'0':'1')+",$(this));");
			// Get table row object
			var thisRow = ob.parents('tr:first');
			if (exclude) {
				ob.addClass('darkRedClr');
				// Also deselect the radio button for this field and make radio invisible
				thisRow.find('a.reset:first').css('visibility','hidden').click();
				thisRow.find('.rtws_adjud_radio').css('visibility','hidden');
			} else {
				ob.removeClass('darkRedClr');
				// Display the radio button again
				thisRow.find('a.reset:first').css('visibility','visible');
				thisRow.find('.rtws_adjud_radio').css('visibility','visible');
			}
		}
	});
}
// Show all hidden rows in adjudication table
function showHiddenAdjudRows(hidethem) {
	if (hidethem == null) hidethem = true;
	$('#rtws_adjud_form .hidden').addClass('hidden-orig');
	if (!hidethem) {
		// Hide the link just clicked and re-hide all rows and event headers
		$('#btn_existing_items_show').show();
		$('#btn_existing_items_hide').hide();
		$('#rtws_adjud_form tr.adjud_evt_hdr, #rtws_adjud_form tr.adjud_rptinst_hdr').hide();
		// Re-hide the hidden fields that were displayed
		$('#rtws_adjud_form .hidden-orig').addClass('hidden');
	} else {
		// Hide the link just clicked and show all rows and event headers
		$('#btn_existing_items_show').hide();
		$('#btn_existing_items_hide').show();
		$('#rtws_adjud_form tr.adjud_evt_hdr, #rtws_adjud_form tr.adjud_rptinst_hdr').show();
		// Show hidden fields
		if ($('#rtws_adjud_popup_content input[name="rtws-otherformfields-radio"]:checked').val() == 'allforms') {
			$('#rtws_adjud_form .hidden-orig').removeClass('hidden');
		} else {
			$('#rtws_adjud_form .hidden-orig').not('.rtws-otherformHide').removeClass('hidden');
		}
		// Set position of dialog
		$('#rtws_adjud_popup').dialog('option', 'position', { my: 'center', at: 'center', of: window });
		fitDialog(adjud_dialog);
		// Highlight the once-hidden rows
		$('#rtws_adjud_form tr.hidden-orig').each(function(){
			highlightTableRowOb($(this),1000);
		});
		// Now send ajax call to log that the user is viewing the previously hidden values (only do once per dialog-load)
		if ($('#btn_existing_items_show').attr('log_view') == '1') {
			// Log the data viewed
			logSourceValuesInPopup(true);
			// Set flag on button so we won't have duplicate data view logged
			$('#btn_existing_items_show').attr('log_view', '0');
		}
	}
}
// Confirm with user if they should refetch data and lose all selections made
function confirmRefetchSourceData() {
	// Check if any radios have been selected
	var num_radios_selected = $('#rtws_adjud_popup .rtws_adjud_radio_clicked:checked').length;
	// If any are selected, then have them confirm loss
	if (num_radios_selected > 0 && !confirm("ABANDON UNSAVED SELECTIONS?\n\nIf you fetch the source data again, you will lose all selections made below, which have not been saved. Are you sure you wish to abandon your current selections?")) {
		return false;
	}
	return true;
}
// Trigger serial processing of ALL records on Record Status Dashboard (but don't output html into the dialog)
function triggerAllRecordsRTWS() {
	// Get list of records delimited with line break
	var records = new Array();
	var i = 0;
	$('table#record_status_table [id^=rtws_new_items]').each(function(){
		if ($(this).find('button').length) {
			records[i++] = this.id.substring('rtws_new_items-'.length);
		}
	});
	var recordList = records.join("\n");
	$('table#record_status_table [id^=rtws_new_items] button').parent().addClass('darkgreen').removeClass('data').removeClass('statusdashred').html(recordProgressIcon);
	// Call the function to fetch data for each record one at a time
	triggerRTWSmappedField(recordList, false, 0);
}
// Trigger the field mapped to external id field
// If record_name is provided as a list of records delimited with line breaks, then run each record one at a time.
// If record_name is just a single record name, then only run it for that record.
function triggerRTWSmappedField(record_name, autoOpenDialog, outputHtml, showExclusions, forceDataFetch) {
	// Set flag
	if (outputHtml == null) outputHtml = 1;
	if (showExclusions == null) showExclusions = false;
	if (forceDataFetch == null) forceDataFetch = false;

	// If user needs to re-connect with EHR, show standalone launch dialog
	if ($('#fhir_launch_modal').length && !getCookie('fhir-launch-stop-asking')) {
		showFhirLaunchModal();
		return;
	}

	// Check if we are doing a batch of records or just a single record
	var next_record_names = '';
	if (record_name.indexOf("\n") > -1) {
		// Split into array
		var recordList = record_name.split("\n");
		// Get this record name
		record_name = recordList[0];
		// Remove record from array and set record list back
		recordList[0] = '';
		next_record_names = trim(recordList.join("\n"));
	}

	// Open popup (jQuery dialog will reference a global variable so we can modify it in other scopes)
	adjud_dialog = $('#rtws_adjud_popup').dialog({ bgiframe: true, modal: true, autoOpen: autoOpenDialog, width: 1100, close: function(){ $(this).dialog('destroy'); } });

	if (outputHtml) {
		// Add record name inside span inside dialog
		$('#rtws_adjud_popup_recordname').html(record_name);
		// Make sure progress is showing and content div is hidden
		$('#rtws_adjud_popup_content').html('');
		$('#rtws_adjud_popup_progress').show();
	}

	// If not on a data entry form, use slightly different values
	if (page == 'DataEntry/index.php') {
		// Data entry form
		var params = $('form#form').serializeObject();
		var record_exists2 = $('form#form :input[name="hidden_edit_flag"]').val();
		var form = getParameterByName('page');
		// If this record has not been saved, then force a data fetch and cache
		if (record_exists2 == '0') forceDataFetch = true;
	} else {
		// Any other page
		event_id = '';
		instance = 1;
		var form = '';
		var params = { record: record_name };
		var record_exists2 = 1;
	}

	// Get day offset value
	var day_offset = $('#rtws_adjud_popup_day_offset').val();
	// Set span value as day offset value in dialog to reflect the change if user changed day offset
	$('#rtws_adjud_popup_day_offset_span').html(day_offset);

	// Get day offset plus/minus operator value
	var day_offset_plusminus = $('#rtws_adjud_popup_day_offset_plusminus').val();
	// Set span value as day offset plus/minue value in dialog to reflect the change if user changed day offset
	$('#rtws_adjud_popup_day_offset_plusminus_span').html((day_offset_plusminus == '+-' ? '&plusmn;' : day_offset_plusminus));

	// Call web service via ajax
	$.post(app_path_webroot+'DynamicDataPull/fetch.php?pid='+pid+'&event_id='+event_id+'&instance='+instance+'&page='+form+'&day_offset='+day_offset
		   +'&day_offset_plusminus='+encodeURIComponent(day_offset_plusminus)+'&output_html='+outputHtml+'&record_exists='+record_exists2
		   +'&show_excluded='+(showExclusions?'1':'0')+'&forceDataFetch='+(forceDataFetch?'1':'0'), params)
	.done(function(json_data)
	{
		// Get count of items to adjudicate
		var itemsToAdjudicate = json_data.item_count;
		// Record Home Page
		var isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
		if (isEHRpage || page == 'DataEntry/record_home.php') {
			$('#RTWS_sourceDataCheck .badgerc:first').html(itemsToAdjudicate);
		// Record Status Dashboard only: replace all record "fetch" buttons with spinning progress icon
		} else if (page == 'DataEntry/record_status_dashboard.php') {
			// Get cell selector
			var dashboardRTWScell = $('table#record_status_table #rtws_new_items-'+record_name);
			// Set html to place in status dashboard cell
			if (itemsToAdjudicate < 0) {
				// Set all buttons to "Error" text
				$('table#record_status_table [id^="rtws_new_items-"]').removeClass('darkgreen').addClass('data').addClass('statusdashred')
					.html('<span style="color:#800000;font-size:11px;">ERROR!</span>');
			} else if (itemsToAdjudicate > 0) {
				// New items to adjudicate
				var newItemsHtml = '<div style="float:left;font-size:15px;width:50px;text-align:center;font-weight:bold;color:red;">'+itemsToAdjudicate+'</div>'
								 + '<div style="float:right;"><a href="javascript:;" onclick="triggerRTWSmappedField(\''+record_name+'\',true);" style="font-size:10px;text-decoration:underline;">view</a></div>'
								 + '<div style="clear:both:height:0;"></div>';
				// Change bg color of cell
				dashboardRTWScell.removeClass('darkgreen').addClass('data').addClass('statusdashred');
			} else {
				// Zero items to adjudicate
				var newItemsHtml = '<div style="float:left;width:50px;color:#999;font-size:10px;">None</div><div style="clear:both:height:0;"></div>'
								 + '<div style="float:right;"><a href="javascript:;" onclick="triggerRTWSmappedField(\''+record_name+'\',true);" style="font-size:10px;text-decoration:underline;">view</a></div>'
								 + '<div style="clear:both:height:0;"></div>';
			}
			// Add content to cell in status dashboard
			dashboardRTWScell.html(newItemsHtml);
			// If user clicked individual "fetch data", then set "view" link's onclick to merely open
			if (itemsToAdjudicate > 0 && !autoOpenDialog && outputHtml) {
				setTimeout(function(){
					openAdjudicationDialog(record_name);
				},50);
			}
		}

		// If running a batch of records, then run this function again recursively for the next record until we're done
		if (next_record_names != '') {
			triggerRTWSmappedField(next_record_names, autoOpenDialog, outputHtml);
		}

		// DIALOG OPERATIONS
		if (outputHtml) {

			// Hide progress div
			$('#rtws_adjud_popup_progress').hide();

			// Add payload to popup
			$('#rtws_adjud_popup_content').html(json_data.html);

			// If any temporal fields are being displayed, then display the day offset label
			if ($('#rtws_adjud_popup #rtws_temporal_fields_displayed').val() == 1) {
				$('#rtws_adjud_popup .rtws_pullingdates_label').css('visibility', 'visible');
			} else {
				$('#rtws_adjud_popup .rtws_pullingdates_label').css('visibility', 'hidden');
			}

			// Hide all rows that already have a value that's the same as one returned from the web service
			if ($('#rtws_adjud_popup #adjud_tr_classes').length) {
				var adjud_tr_classes_csv = $('#rtws_adjud_popup #adjud_tr_classes').html();
				if (adjud_tr_classes_csv.length > 0) {
					var adjud_tr_classes_arr = adjud_tr_classes_csv.split(',');
					for (var i=0; i<adjud_tr_classes_arr.length; i++) {
						// Hide each class in array
						$('#rtws_adjud_popup .'+adjud_tr_classes_arr[i]).addClass('hidden');
					}
				}
			}

			// Hide all event name headers if all fields inside those sections are hidden
			if (longitudinal && $('#rtws_adjud_popup .adjud_evt_hdr').length) {
				// Loop through each event name section header
				$('#rtws_adjud_popup .adjud_evt_hdr').each(function(){
					// Get its event_id
					var this_evt_id = $(this).attr('evtid');
					// Count how many rows are being displayed for that event
					var num_evt_rows = $('#rtws_adjud_popup .evtfld-'+this_evt_id+':not(.hidden)').length;
					if (num_evt_rows == 0) {
						// All fields are hidden so now hide the event name header
						$(this).hide();
					}
				});
			}

			// Hide all repeat instance headers if all fields inside those sections are hidden
			if ($('#rtws_adjud_popup .adjud_rptinst_hdr').length) {
				// Loop through each event name section header
				$('#rtws_adjud_popup .adjud_rptinst_hdr').each(function(){
					// Get its repeat instrument-instance
					var rptinst = $(this).attr('rptinst');
					// Count how many rows are being displayed for that repeat instrument-instance
					var num_rptinst_rows = $('#rtws_adjud_popup .rptinst-'+rptinst+':not(.hidden)').length;
					if (num_rptinst_rows == 0) {
						// All fields are hidden so now hide the repeat instrument-instance header
						$(this).hide();
					}
				});
			}
			
			// Log the source values displayed in the adjudication popup when it initially opens
			logSourceValuesInPopup();

			// Buttonize buttons
			initWidgets();
			if (autoOpenDialog) {
				// Re-center dialog
				$('#rtws_adjud_popup').dialog('option', 'position', { my: "center", at: "center", of: window });
			}
				
			// Add save/cancel buttons
			if ($('#rtws_adjud_popup_content #adjud_hide_buttons').length && $('#rtws_adjud_popup_content #adjud_hide_buttons').text() == '1') {
				// Do NOT display the Save/Cancel buttons b/c something went wrong
				$('#rtws_adjud_popup').dialog('option', 'buttons', [
					{ text: 'Close', click: function() { $(this).dialog('close'); } }
				]);
			} else {
				// Display Save/Cancel buttons as normal
				$('#rtws_adjud_popup').dialog('option', 'buttons', [
					{ text: 'Cancel', click: function() { $(this).dialog('close'); } },
					{ text: 'Save', click: function() {
						var noFieldsSelected = ($('form#rtws_adjud_form input[type="radio"]').serialize().length == 0);
						if (noFieldsSelected) {
							if (!confirm("No fields were selected! Are you sure you have reviewed the values and wish to mark them "
										+"as such so that they are not shown again?")) {
								return;
							}
						}
						// Close dialog
						$(this).dialog('close');
						showProgress(1);
						// Remove all hidden items that we're not processing
						$('form#rtws_adjud_form table#adjud_table tr.hidden').remove();
						// Save data via ajax
						var rtws_adjud_form_vars = {};
						$('form#rtws_adjud_form input').each(function(){
							if ($(this).attr('name') != null) {
								if ($(this).attr('type') == 'radio') {
									if (noFieldsSelected) {
										rtws_adjud_form_vars[$(this).attr('name')] = '';
									} else if ($(this).prop('checked')) {
										rtws_adjud_form_vars[$(this).attr('name')] = $(this).val();
									}
								} else {
									rtws_adjud_form_vars[$(this).attr('name')] = $(this).val();
								}
							}
						});
						$.post(app_path_webroot+'DynamicDataPull/save.php?pid='+pid+'&record='+record_name+'&event_id='+event_id+'&instance='+(getParameterByName('instance')=='' ? 1 : getParameterByName('instance')), rtws_adjud_form_vars, function(data){
							showProgress(0,0);
							// Display success message
							simpleDialog(data,"Saved successfully!");
							// If javascript is in div to set form values, then eval it
							if ($('#adjud_js_set_form_values').length) {
								eval($('#adjud_js_set_form_values').text());
							}
						});
					} }
				]);
			}
			
			// Fit dialog to window
			fitDialog(adjud_dialog);
			
			// Enable triggers to radio buttons inside popup
			$('#rtws_adjud_popup .rtws_adjud_radio').click(function(){
				var this_radio = $(this);
				// Add class that denotes that the user clicked it (as opposed to it being preselected)
				this_radio.addClass('rtws_adjud_radio_clicked');
				// Reset bgcolor for all radios in this group and hide "reset" link
				resetAdjudRadioBgLink(this_radio.attr('name'));
				// Uncheck all other radios in this row
				var this_row_classes = this_radio.parents('tr:first').attr('class').split(' ');
				var this_row_class = null;
				for (var i=0; i<this_row_classes.length; i++) {
					if (this_row_classes[i].indexOf('adjud_tr-') > -1) {
						this_row_class = this_row_classes[i];
					}
				}
				if (this_row_class != null && !$('#adjud_table tr.'+this_row_class).hasClass('chkbx')) {
					$('#adjud_table tr.'+this_row_class).find('.rtws_adjud_radio').prop('checked', false);
					$('#adjud_table tr.'+this_row_class+' td.radiogreen').each(function(){
						$(this).removeClass('radiogreen').children('a.reset').css('visibility', 'hidden');
					});
				}
				// Set bgcolor to green for this radio and show "reset" link
				this_radio.parents('td:first').addClass('radiogreen').children('a.reset').css('visibility', 'visible');
				this_radio.prop('checked', true);
				// If selected a value for a checkbox field, make sure all get selected				
				if (this_row_class != null && $('#adjud_table tr.'+this_row_class).hasClass('chkbx')) {
					$('#adjud_table tr.'+this_row_class).find('.rtws_adjud_radio').each(function(){
						$(this).prop('checked', true);
						$(this).parentsUntil('td').parent().addClass('radiogreen').children('a.reset').css('visibility', 'visible');
					});
				}
			});
		}

		// If on a data entry form AND checking for source data in the background, then show how many items were returned
		if (!autoOpenDialog && page == 'DataEntry/index.php') {
			// Replace progress icon
			if (itemsToAdjudicate < 0) {
				// If items returned is "-1", then user no longer has access to source data (via user access web service call), so display error msg
				var newItemsText = json_data.html;
			} else if (itemsToAdjudicate == 0) {
				// No new items
				var newItemsText = '<img src="'+app_path_images+'information_frame.png"> <span style="color:#000066;">No new items from source system</span>';
			} else {
				// There are 1 or more new items
				var newItemsText = '<div id="RTWS_sourceDataCheck_msgBox" class="red" style="display:none;color:#C00000;text-align:center;font-weight:bold;">'
								 + '<span class="badgerc" style="font-size:12px;">'+itemsToAdjudicate+'</span><span style="">new items from source system</span>'
								 + '<button class="jqbuttonmed" style="margin-left:10px;" onclick="openAdjudicationDialog(\''+record_name+'\'); return false;">View</button></div>';
			}
			if ($('#RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox').length) {
				// If badge number is already displayed, then only update its number
				if (itemsToAdjudicate != $('#RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox .badgerc').text()*1) {
					$('#RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox .badgerc').html(itemsToAdjudicate);
					setTimeout(function(){
						$('#RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox').effect('highlight',{},2000);
					},100);
				}
			} else {
				// Replace progress text with new message if found or didn't find new items
				$('#RTWS_sourceDataCheck').html(newItemsText);
				// Buttonize the button
				$('#RTWS_sourceDataCheck button').button();
				// Show text
				$('#RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox').show('blind');
			}
			// If badge div is not viewable, then clone it and display to user as fixed in browser
			if ($('#RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox').length && !elementInViewport( document.getElementById('RTWS_sourceDataCheck_msgBox') )) {
				doFloatingNewItemsCountDiv();
			}
		}
	}).catch(function(response){
		var status = response.status || 'unknown';
		var responseText = response.responseText || 'error';
		if(response.responseJSON && response.responseJSON.error) {
			responseText = response.responseJSON.error;
		}
		var message = 'Error ' + status + ". " + responseText;
		// If error, then display error
		simpleDialog(message,'ERROR');
	});
	return false;
}

// Determine if an element is viewable within the current viewport of the web browser
function elementInViewport(el) {
	var top = el.offsetTop;
	var left = el.offsetLeft;
	var width = el.offsetWidth;
	var height = el.offsetHeight;
	while(el.offsetParent) {
		el = el.offsetParent;
		top += el.offsetTop;
		left += el.offsetLeft;
	}
	return (
		top >= window.pageYOffset &&
		left >= window.pageXOffset &&
		(top + height) <= (window.pageYOffset + window.innerHeight) &&
		(left + width) <= (window.pageXOffset + window.innerWidth)
	);
}

// If badge div is not viewable, then clone it and display to user as fixed in browser
function doFloatingNewItemsCountDiv() {
	// Set original div as jQuery object
	var ob = $('#RTWS_sourceDataCheck');
	// Clone the existing one
	var thisdiv = ob.html();
	// Get position of original
	var thisdiv_position = ob.position();
	// Add clone to page with different IDs
	var clonediv = '<div id="RTWS_sourceDataCheck_clone" style="padding-top:20px;">'+thisdiv.replace('RTWS_sourceDataCheck_msgBox','RTWS_sourceDataCheck_clone_msgBox')
				 + '</div>';
	$('body').append(clonediv);
	// Position clone as fixed at top of page
	$('#RTWS_sourceDataCheck_clone_msgBox')
		.width($('#RTWS_sourceDataCheck_msgBox').width())
		.css({ 'display':'none', 'position':'fixed', 'top':10, 'left':thisdiv_position.left })
		.click(function(){
			$(this).remove();
		});
	// Fade it in
	//$('#RTWS_sourceDataCheck_clone_msgBox').show('fade',{},700);
	$('#RTWS_sourceDataCheck_clone_msgBox').slideDown(500,'swing');
	// After showing for several seconds, fade it out
	setTimeout(function(){
		if ($('#RTWS_sourceDataCheck_clone_msgBox').length) {
			//$('#RTWS_sourceDataCheck_clone_msgBox').hide('fade',{},700);
			$('#RTWS_sourceDataCheck_clone_msgBox').slideUp(500,'swing');
		}
	},8000);
}

// Open the adjudication dialog popup, if close
function openAdjudicationDialog(record_name) {
	if ($('#rtws_adjud_popup').hasClass('ui-dialog-content') && $('#rtws_adjud_popup').dialog('isOpen') == false) {
		// Display the hidden dialog on the page
		$('#rtws_adjud_popup').dialog('open');
		// Fit dialog to window
		fitDialog(adjud_dialog);
		// Re-center dialog
		$('#rtws_adjud_popup').dialog('option', 'position', { my: "center", at: "center", of: window });
		// Log the source values displayed in the adjudication popup when it is opened
		logSourceValuesInPopup();
	} else {
		// Fetch source data all over again and reopen dialog
		triggerRTWSmappedField(record_name, true);
	}
}
// Log the source values displayed in the adjudication popup when it initially opens
function logSourceValuesInPopup(getHiddenMdIds) {
	// If popup is not visible, then return
	if (!$('#rtws_adjud_popup').dialog('isOpen')) return;
	// Set var if null
	if (getHiddenMdIds == null || getHiddenMdIds !== true) getHiddenMdIds = false;
	// Get all values/md_ids that are visible
	var md_ids_viewed = '';
	$('#rtws_adjud_popup_content #adjud_table tr').each(function(){
		var row = $(this);
		if (row.hasClass('hidden') == getHiddenMdIds && row.attr('md_id') != null) {
			md_ids_viewed += row.attr('md_id')+',';
		}
	});
	if (md_ids_viewed.length > 0) {
		md_ids_viewed = md_ids_viewed.substring(0,md_ids_viewed.length-1);
		// Log the values displayed to the user
		$.post(app_path_webroot+'DynamicDataPull/data_view_logging.php?pid='+pid, { source_id_value: $('#rtws_adjud_popup #hidden_source_id_value').val(), md_ids: md_ids_viewed }, function(data){
			// Do nothing
		});
	}
}
// Unset radio value for group of radios in adjudication table
function radioResetValAdjud(field,form) {
	$('form[name="'+form+'"] input[name="'+field+'"]').prop('checked',false);
	resetAdjudRadioBgLink(field);
}
// Reset bgcolor for all radios in this group and hide "reset" link
function resetAdjudRadioBgLink(field) {
	// Radio input
	var this_radio = $('#rtws_adjud_popup input[name="'+field+'"]');
	// Uncheck radio
	this_radio.each(function(){
		var thisCell2 = $(this).parents('td:first');
		thisCell2.removeClass('radiogreen');
		thisCell2.children('a.reset').css('visibility', 'hidden');
	});	
	// Get row class
	var this_row_classes = this_radio.parents('tr:first').attr('class').split(' ');
	var this_row_class = null;
	for (var i=0; i<this_row_classes.length; i++) {
		if (this_row_classes[i].indexOf('adjud_tr-') > -1) {
			this_row_class = this_row_classes[i];
		}
	}
	// If selected a value for a checkbox field, make sure all get selected
	var trs = $('#adjud_table tr.chkbx.'+this_row_class);
	if (trs.length && this_row_class != null) {
		trs.find('.rtws_adjud_radio').each(function(){
			$(this).prop('checked', false);
			var thisCell2 = $(this).parents('td:first');
			thisCell2.removeClass('radiogreen');
			thisCell2.children('a.reset').css('visibility', 'hidden');
		});
	}
}
// Perform auto-check of new items from source system and display count at top of data entry form
function autoCheckNewItemsFromSource(record, forceDataFetch) {
	// Set var
	if (forceDataFetch == null) forceDataFetch = false;
	// Set html for fetching status message
	var fetchingDataText = '<img src="'+app_path_images+'progress_circle.gif"> Checking data in source system...';
	setRTWSContextMsgPlaceholder(fetchingDataText);
	// Now check for data in the source system
	triggerRTWSmappedField(record, false, 1, false, forceDataFetch);
}

// Set the div in place in the form's blue/green context msg div, in which we'll put the RTWS fetching data status msg
function setRTWSContextMsgPlaceholder(text) {
	// Record Home Page
	var isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
	if (isEHRpage || page == 'DataEntry/record_home.php') {
		$('#record_display_name>div:last').append('<div id="RTWS_sourceDataCheck" style="margin-top:2px;color:#666;width:320px;text-align:right;">'+text+'</div>');
	} else {
		// Get class of context msg div
		var contextMsgClass = (record_exists ? 'blue' : 'darkgreen');
		// Add the "checking source system..." text at top of form
		if (!$('div#contextMsg #RTWS_sourceDataCheck').length) {
			// Get contents of context msg div
			var contextMsgDiv = $('div#contextMsg .'+contextMsgClass);
			var contextMsgContents = contextMsgDiv.html();
			// Check if #RTWS_sourceDataCheck is already on page. If not, then add.
			contextMsgDiv.html('<table cellspacing="0" style="width:100%;table-layout:fixed;"><tr><td id="RTWS_contextMsg_original">'+contextMsgContents+'</td>'
							 + '<td id="RTWS_sourceDataCheck" style="color:#666;width:320px;text-align:right;">'+text+'</td></tr></table>');
		} else if (!$('div#contextMsg #RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox').length) {
			$('div#contextMsg #RTWS_sourceDataCheck').html(text);
		}
	}
}

// Add db icon as td cell background
function addDDPicon(field) {
	$('#questiontable #'+field+'-tr').children('td:last').css('background','#F3F3F3 url("'+app_path_images+'databases_arrow.png") no-repeat 97% 8px');
}

// In adjudication popup, hide/show fields that exist on a different form/event that the current one
function hideOtherFormFields(hide) {
	if (hide) {
		// Hide them
		$('#adjud_table .rtws-otherform').addClass('rtws-otherformHide');
	} else {
		// Redisplay them
		$('#adjud_table .rtws-otherformHide').removeClass('rtws-otherformHide').children('td').effect('highlight',{},2000);
	}
}