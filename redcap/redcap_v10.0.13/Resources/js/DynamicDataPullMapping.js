// Keep track if anyone has potentially modifed the mapping to remind them to save before leaving page
var modifiedMapping = false;

/*
 * delayKeyup
 * http://code.azerti.net/javascript/jquery/delaykeyup.htm
 * Inspired by CMS in this post : http://stackoverflow.com/questions/1909441/jquery-keyup-delay
 * Written by Gaten
 * Exemple : $("#input").delayKeyup(function(){ alert("5 secondes passed from the last event keyup."); }, 5000);
 */
(function ($) {
    $.fn.delayKeyup = function(callback, ms){
        var timer = 0;
        $(this).keyup(function(){
            clearTimeout (timer);
            timer = setTimeout(callback, ms);
        });
        return $(this);
    };
})(jQuery);

$(function(){

	if ($('#ext_field_tree').length) {

		// Click cat/subcat name or "select all" link
		$('#ext_field_tree .rc_ext_cat_name, #ext_field_tree .rc_ext_subcat_name').click(function(event){
			if ($(event.target).attr('class') == 'deselectalllink') {
				// If click "deselect all" link, thende select all checkboxes in cat/subcat
				$(this).next().find('input[type="checkbox"]:visible').each(function(){
					var this_chk = $(this);
					if (this_chk.prop('checked')) {
						// For some reason, we have to check the checkbox before and after we click it to get the counter to work correctly
						this_chk.prop('checked',false).trigger('click').prop('checked',false);
					}
				});
			} else if ($(event.target).attr('class') == 'selectalllink') {
				// If click "select all" link, then select all checkboxes in cat/subcat
				$(this).next().find('input[type="checkbox"]:visible').each(function(){
					var this_chk = $(this);
					if (!this_chk.prop('checked')) {
						// For some reason, we have to check the checkbox before and after we click it to get the counter to work correctly
						this_chk.prop('checked',true).trigger('click').prop('checked',true);
					}
				});
			} else {
				// Show/hide the tree beneath
				var nextdiv = $(this).next('div');
				if (nextdiv.css('display') == 'none') {
					$(this).children('img').attr('src', app_path_images+'collapse.png');
					nextdiv.show('fast');
					// Show the "select all" link
					$(this).find('.selectalllink, .deselectalllink, .selectalllink_sep').show();
				} else {
					$(this).children('img').attr('src', app_path_images+'expand.png');
					nextdiv.hide('fast');
					// Hide the "select all" link
					$(this).find('.selectalllink, .deselectalllink, .selectalllink_sep').hide();
				}
			}
		});

		// Check a checkbox and set number of fields selected
		$('#ext_field_tree input[type="checkbox"]').click(function(){
			var thisChkBox = $(this);
			var chkBoxName = thisChkBox.attr('name');
			var chkBoxSelected = thisChkBox.prop('checked');
			// Also select any other checkbox fields that have the same name (if there are duplicates)
			$('#ext_field_tree input[type="checkbox"][name="'+chkBoxName+'"]').prop('checked', chkBoxSelected);
			// If the external id field was just unchecked, then recheck it and give warning msg
			if (chkBoxName == external_id_field && !chkBoxSelected) {
				setTimeout(function(){
					thisChkBox.prop('checked', true);
				},100);
				simpleDialog("Sorry, but you cannot uncheck the source identifier field.");
			}
			// Set number of fields selected
			$('#rc_ext_num_selected, #rc_ext_num_selected2').html( $('#ext_field_tree_fields input[type="checkbox"][oid="1"]:checked').length );
		});

		// Source field search by keyword (allow for delay when typing)
		$('#source_field_search').delayKeyup(function(){
			var term = trim($('#source_field_search').val());
			// If no term entered, then show all categories (collapsed)
			if (term.length == 0) {
				$('#ext_field_tree_fields div').show();
				$('#ext_field_tree_fields .rc_ext_cat, #ext_field_tree_fields .rc_ext_subcat').hide();
				$('#ext_field_tree_fields .rc_ext_cat_name img').attr('src', app_path_images+'expand.png');
				return;
			}
			// Don't start actually searching until we have 2 characters first
			if (term.length < 2) {
				return;
			}
			// First hide all divs and remove "st" class
			$('#ext_field_tree_fields .st').removeClass('st');
			$('#ext_field_tree_fields div').hide();
			$('#ext_field_tree_fields div.rc_ext_cat_name').show();
			// Loop through each term
			var terms = term.split(" ");
			for (var i=0; i<terms.length; i++) {
				var term = trim(terms[i]);
				if (term.length > 0) {
					// Set as lowercase
					term = term.toLowerCase();
					// Search for term in divs
					$('#ext_field_tree_fields div.extsrcfld').each(function(){
						var thisDivContent = $(this).html().replace(/<\/?[^>]+>/gi, '').replace(/&nbsp;/gi, '').toLowerCase();
						// If field contains the string, then
						if (thisDivContent.indexOf(term) > -1) {
							var parent = $(this).parent();
							parent.addClass('st');
							if (parent.hasClass('rc_ext_subcat')) {
								parent.prev('div').addClass('st').parent().addClass('st');
							}
							$(this).addClass('st');
						}
					});
				}
			}
			// Make all appear that were matched
			$('#ext_field_tree_fields .st').show();
		}, 300);

	} else {
		// Mapping table is displayed

		// Note if any drop-downs have changed value or links clicked
		$('#rtws_mapping_table select').change(function(){
			modifiedMapping = true;
		});
		$('#rtws_mapping_table a, #rtws_mapping_table img').click(function(){
			modifiedMapping = true;
		});
	}

	// COMPOSITE MAPPING: Hide the "add" link for all composite mappings EXCEPT the last instance
	hideCompositeMappingAddLinks(true);
});

// If user made any potential mapping changes on page, then remind them to save them before leaving page
function confirmLeaveMappingChanges(modifiedMapping2) {
	var treePageUrl = app_path_webroot+page+'?pid='+pid+'&add_fields=1';
	if (modifiedMapping2) {
		simpleDialog('Did you make any changes below? If so, please do not forget to save your changes by clicking the Save button at the bottom of the page before you leave. '+
			'If you have not made any changes or if you wish to abandon your changes, then click the Continue button to go and find more source fields to map.',
			'Leave page without saving changes?',null,null,null,'Cancel, stay on mapping page',function(){
			window.location.href = treePageUrl;
		}, 'Continue to leave page');
	} else {
		window.location.href = treePageUrl;
	}
}

// COMPOSITE MAPPING: Hide the "add" link for all composite mappings EXCEPT the last instance
function hideCompositeMappingAddLinks(pageload) {
	if (pageload == null) pageload == false;
	if ($('.manytoone_showddlink').length < 1) return;
	// Put all table row classes in array to loop through later
	var tr_eq_hide_link = new Array();
	var i=0;
	// Get total rows in table
	var max_eq = $('table#src_fld_map_table tr').length-1;
	// Hide all manytoone drop-downs
	$('table#src_fld_map_table .manytoone_dd').hide();
	// Show all manytoone links
	$('table#src_fld_map_table .manytoone_showddlink').show();
	// Hide for ALL fields in table (i.e. for page load)
	$('table#src_fld_map_table tr').filter(function() {
        return $(this).find('.manytoone_showddlink').length;
    }).each(function(){
		// Get the current row
		var this_row = $(this);
		// Get current row's eq in table
		var this_eq = this_row.prevAll('tr').length;
		// If a parent, hide this row's link if the next row has no temfld drop-down
		if (!this_row.next('tr').find('.temfld').length && !this_row.next('tr').find('td.cat_hdr').length && this_eq < max_eq) {
			// Get current row's eq in table and add to array
			tr_eq_hide_link[i++] = this_eq;
		}
	});
	// Loop through and hide all but last instance of the link
	for (k=0; k<i; k++) {
		hideCompositeMappingAddLinksSingle( tr_eq_hide_link[k] );
	}
	// Now loop through the composite mappings again, then remove them and append them to the end of the table
	if (pageload && i > 0) {
		// Add special section header
		$('table#src_fld_map_table').append('<tr><td valign="top" style="font-weight:bold;color:#800000;border:1px solid #aaa;background-color:#ccc;padding:5px 10px;font-size:13px;" colspan="'+(longitudinal ? '5' : '4')+'">[COMPOSITE SOURCE FIELD MAPPINGS]</td></tr>');
		// Remove and append to end
		$('table#src_fld_map_table tr').filter(function() {
			return ($(this).find('.manytoone_showddlink').css('display') == 'none' || $(this).find('.manytoone_or').css('display') == 'block');
		}).each(function(){
			// Get the current row
			var this_row = $(this);
			this_row.remove();
			$('table#src_fld_map_table').append(this_row);
		});
	}
}

// Hide the "add" link for a composite mapping row based on their eq in the table
function hideCompositeMappingAddLinksSingle(eq) {
	if (eq == null || eq == '') return;
	$('table#src_fld_map_table tr').eq(eq).find('.manytoone_showddlink, .copySrcRowTemporal').hide();
	// Hide the copy icon (will always be one row after the eq above)
	$('table#src_fld_map_table tr').eq(eq+1).find('.copySrcRowTemporal').hide();
}

// Display the drop-down list of temporal fields when mapping a many-to-once relationship
function mappingShowManyToOneDD(ob) {
	// Hide the link itself
	var linkparent = ob.parents('div:first');
	linkparent.hide();
	// Show the drop-down
	linkparent.next('.manytoone_dd').show();
}

// When user changes "preselect" drop-down value OR adds a new REDCap field to a source, make sure all REDCap fields used have same value in other source mappings
function preselectConform(ob) {
	// ob may be "this" for RC field or preselection drop-down
	var ob = $(ob);
	var thisrow = ob.parents('tr:first');
	// Get current row's eq in table
	var this_row_eq = thisrow.prevAll('tr').length;
	// Get the preselect value. If doesn't have one, then return (nothing to do).
	if (!thisrow.find('.presel').length) return;
	var preselectDD = thisrow.find('.presel');
	var preselectVal = preselectDD.val();
	// Also get the "preval" attribute value (which is the value prior to being changed), and then set its value as the new selected value
	var preval = preselectDD.attr('preval');
	preselectDD.attr('preval', preselectVal);
	// Create array for putting REDCap fields and number of other instances
	var rcfldPreselSetKey = new Array();
	var rcfldPreselSetVal = new Array();
	var i = 0;
	// Get the fields used for this source field
	thisrow.find('.mapfld').each(function(){
		// Loop through each REDCap field mapped to this source field
		var rc_field = $(this).val();
		//alert(rc_field);
		// Find all other instances of the field
		var num_other_instances = 0;
		$('#src_fld_map_table .mapfld option[value="'+rc_field+'"]:selected').each(function(){
			// Make sure the instance is for a temporal field
			if ($(this).parents('tr:first').find('.presel option[value="'+preselectVal+'"]:not(:selected)').length) {
				num_other_instances++;
			}
		});
		if (num_other_instances > 0) {
			rcfldPreselSetKey[i] = rc_field;
			rcfldPreselSetVal[i] = num_other_instances;
			i++;
		}
	});
	// If we have any other instances
	var arraylen = rcfldPreselSetKey.length;
	if (arraylen > 0) {
		var confirm_msg_fields = '';
		for (var k=0;k<arraylen;k++) {
			confirm_msg_fields += ' - '+rcfldPreselSetKey[k]+'<br>';
		}
		// Give confirmation popup to reset all others
		simpleDialog('One or more of the REDCap fields mapped to this source field are mapped to one or more other source fields that have a different value for the "Preselect a value" option. '+
			'A mapped REDCap field must have the same "preselect" designation for all instances or else it will lead to a conflict/error, so you must either undo the "preselect" option you just selected or you '+
			'can instead choose to have it automatically change all instances of this REDCap field to have the same "preselect" designation. '+
			'<div style="color:#777;margin:10px 0;">NOTE: Clicking "change all instances" may cause a cascading effect if several REDCap fields here are also mapped to source fields that have other REDCap fields mapped to them, which may result in displaying this very same popup multiple times. This is not an issue but just a notice.</div>'+
			'<div style="color:#C00000;margin:15px 0 0;font-size:13px;"><u>REDCap fields whose "preselect" option will be set to "<b>'+preselectDD.find('option:selected').text()+'</b>":</u><br>'+confirm_msg_fields+'</div>',
			'Change all other "preselect" instances?',
			null,600,
			"var thisPreselDD = $('#src_fld_map_table tr').eq("+this_row_eq+").find('.presel'); thisPreselDD.val('"+preval+"').attr('preval','"+preval+"').effect('highlight',{},2500).focus(); setTimeout(function(){ thisPreselDD.trigger('change') },100);",
			'Undo "preselect" selection',
			function(){
				changePreselectDropdown(rcfldPreselSetKey,preselectVal);
			}, 'Change all instances');

	}
}

// Change all "preselect" instances of a REDCap field to a desired value
function changePreselectDropdown(rc_fields,val) {
	// Loop through each REDCap field, then find all source fields that it is mapped to, and set their "preselect value"
	var arraylen = rc_fields.length;
	for (var k=0;k<arraylen;k++) {
		var thisfield = rc_fields[k];
		// Loop through all rows having this REDCap field mapped
		$('table#src_fld_map_table tr').filter(function() {
			return $(this).find('.mapfld option[value="'+thisfield+'"]:selected').length;
		}).each(function(){
			// Change preselect drop-down's value and trigger it with "change" in case it triggers others
			var thisPreselDD = $(this).find('.presel');
			thisPreselDD.val(val).attr('preval',val).effect('highlight',{},2500);
			// Set slight delay on trigger so it doesn't run over itself
			setTimeout(function(){ thisPreselDD.trigger('change') },100);
		});
	}
}

// After choosing new source field when mapping a many-to-once relationship, clone current row with same mappings
function copyRowManyToOneDD(ob,doHighlight) {
	// Make sure value isn't blank
	if (ob.val() == '') return;
	// Set doHighlight
	if (doHighlight == null) doHighlight = true;
	// Copy the row
	var thisrow = ob.parents('tr:first');
	var newrow = thisrow.clone().insertAfter(thisrow);
	// Now reset and hide the many-to-one field drop-down and show the link again
	thisrow.find('.manytoone_showddlink').show();
	var dd_ob = thisrow.find('.manytoone_dd');
	var new_source_var = dd_ob.val();
	var new_source_label = dd_ob.children("option").filter(":selected").text();
	dd_ob.val('').hide();
	// Now hide elements in new row that don't need to be seen
	newrow.find('.source_var_label').html(new_source_label);
	newrow.find('.td_container_div').remove();
	newrow.find('.manytoone_or, .manytoone_showddlink').show();
	newrow.find('.manytoone_dd, .copySrcRowTemporal').hide();
	// Remove top borders on all row cells (except last)
	for (var i=0; i<newrow.children().length-1; i++) {
		newrow.children().eq(i).css('border-top','0');
	}
	// Add special attribute to this row so we know it's a many-to-one child
	newrow.attr('manytoone', new_source_var);
	// Hide all but the last "add" composite field link
	hideCompositeMappingAddLinks();
	// Highlight the new row
	if (doHighlight) highlightTableRowOb(newrow, 2500);
}

// When saving mappings, find any rows mapped as many-to-one and copy their drop-downs into the children rows and modify input names justly
function copyItemsManyToOneChildren() {
	// Set last_parent
	var last_parent = null;
	// Loop through all temporal parent and child fields
	$('table#src_fld_map_table tr').filter(function() {
        return $(this).find('.manytoone_showddlink').length;
    }).each(function(){
		// Get the current row
		var this_row = $(this);
		// Get current row's eq in table
		var this_eq = this_row.prevAll('tr').length;
		// Determine if this is a parent
		if (this_row.find('.temfld').length) {
			// Parent, so set row object
			last_parent = $('table#src_fld_map_table tr').eq(this_eq);
		} else {
			// Child
			thisrowvar = this_row.attr('manytoone');
			// Copy all parent input values into the child
			thisrow2ndcell = this_row.children('td').eq(1);
			thisrow2ndcell.html('');
			if (last_parent.find('.evtfld').length) {
				thisrow2ndcell.append('<input type="hidden" name="dde-'+thisrowvar+'[]" value="'+last_parent.find('.evtfld').val()+'">');
			}
			last_parent.find('.mapfld').each(function(){
				thisrow2ndcell.append('<input type="hidden" name="ddf-'+thisrowvar+'[]" value="'+$(this).val()+'">');
			});
			thisrow2ndcell.append('<input type="hidden" name="ddt-'+thisrowvar+'[]" value="'+last_parent.find('.temfld').val()+'">');
			thisrow2ndcell.append('<input type="hidden" name="ddp-'+thisrowvar+'[]" value="'+last_parent.find('.presel').val()+'">');
		}
	});
}

// For a many-to-one mapped child, find its parent. Return jquery object.
function findManyToOneParent(ob) {
	// Get current row's eq in table
	var this_eq = ob.prevAll('tr').length;
	var this_class = ob.attr('class');
	// Loop backwards to find row with the same class that does *not* have the "manytoone" attribute
	for (var i=(this_eq-1); i>0; i--) {
		var current_row = $('table#src_fld_map_table tr').eq(i);
		if (current_row.hasClass(this_class) && current_row.attr('manytoone') == null) {
			return current_row;
		}
	}
	return null;
}

// Checks if all drop-downs have been mapped/selected
function hasMappedAllSelections() {
	var returnVal = true;
	$('#src_fld_map_table select').each(function(){
		var thisob = $(this);
		if (thisob.val() == '' && !thisob.hasClass('manytoone_dd')) {
			returnVal = false;
		}
	});
	return returnVal;
}
// Checks to make sure no external source field has been mapped to the same field/event twice
function hasDuplicateFieldMapping() {
	var msg = new Array();
	// Get list of all src fields
	var src_fields = new Array();
	var src_fields_dupl = new Array();
	var i=0, j=0;
	$('#src_fld_map_table select.mapfld').each(function(){
		var this_field = $(this).attr('name').substring(4, $(this).attr('name').length-2);
		var fieldsJoined = this_field+'|'+$(this).val();
		if (longitudinal) {
			var this_evt_dd = $(this).parents('tr:first').find('.evtfld');
			var this_event_id = this_evt_dd.val();
			fieldsJoined += '|'+this_event_id;
		}
		if (in_array(fieldsJoined, src_fields)) {
			msg[j] = ' &bull; Source field: <b>'+this_field+'</b>, REDCap field: <b>'+$(this).val()+'</b>';
			if (longitudinal) msg[j] += ' (<b>'+this_evt_dd.children('option:selected').text()+'</b>)';
			j++;
		} else {
			src_fields[i++] = fieldsJoined;
		}
	});
	if (msg.length > 0) {
		// Error: do not submit the form
		simpleDialog("The fields listed below have been matched more than once, which is superfluous. "
			+ "Please remove the duplicate row for the fields listed below.<br><br><b>DUPLICATES:</b><br>"
			+ msg.join("<br>"));
		// Enable submit button
		enableMappingSubmitBtn();
		return true;
	} else {
		// Submit the form!
		addExtraEventDropdowns();
		return false;
	}
}
// Enable the mapping table submit button
function enableMappingSubmitBtn() {
	$('#map_fields_btn').button('enable');
	$('#rtws_mapping_cancel').css('visibility','visible');
}
// Add more dde- and ddt- hidden fields to DOM prior to submitting the form to match the number of ddf- fields
// so that they all have the same number (makes Post processing easy)
function addExtraEventDropdowns() {
	// Loop through all table rows
	$('#src_fld_map_table tr').each(function(){
		var rc_fields_in_row = $(this).find('select.mapfld').length;
		// Skip header row and rows with just one field mapped
		if (rc_fields_in_row > 1) {
			// If is a temporal field, copy temporal field rc_fields_in_row-1 times
			var rc_temp_field = $(this).find('select.temfld');
			var rc_presel_field = $(this).find('select.presel');
			if (rc_temp_field.length) {
				// Create new input element for temporal field and preselect field
				var temfld_new = '<input type="hidden" name="'+rc_temp_field.attr('name')+'" value="'+rc_temp_field.val()+'">';
				var preselfld_new = '<input type="hidden" name="'+rc_presel_field.attr('name')+'" value="'+rc_presel_field.val()+'">';
				for (var i=0; i<(rc_fields_in_row-1); i++) {
					// Add clone(s)
					rc_temp_field.after(temfld_new);
					rc_presel_field.after(preselfld_new);
				}
			}
			// If longitudinal, then copy event field rc_fields_in_row-1 times
			if (longitudinal) {
				var rc_evt_field = $(this).find('select.evtfld');
				// Create new input element for temporal field
				var evtfld_new = '<input type="hidden" name="'+rc_evt_field.attr('name')+'" value="'+rc_evt_field.val()+'">';
				for (var i=0; i<(rc_fields_in_row-1); i++) {
					// Add clone(s)
					rc_evt_field.after(evtfld_new);
				}
			}
		}
	});
}
// Adds new field drop-down in existing row
function mapOtherRedcapField(ob,src_field) {
	// Get preceding drop-down of RC fields
	var originalDDdiv = $(ob).parents('tr:first').find('select.mapfld:last').parents('div:first');
	// Clone the div
	var cloneDDdiv = originalDDdiv.clone();
	originalDDdiv.after( cloneDDdiv );
	// Set value to blank
	$(ob).parents('tr:first').find('select.mapfld:last').val('');
}
// Copy the source field's row (temporal fields only) so that user can map it again
function copySrcRowTemporal(ob,src_field) {
	// Clone the row
	var originalRow = $(ob).parents('tr:first');
	// Clone the div
	var cloneRow = originalRow.clone();
	originalRow.after( cloneRow );
	// Reset all the drop-down values
	cloneRow.find('.temfld, .presel, .mapfld').val('');
	// Highlight row
	highlightTableRowOb(cloneRow,2500);
}
// Adds new row in mapping table by copying existing row
function copyMappingOtherEvent(ob) {
	// Get row object
	var thisRow = $(ob).parents('tr:first');
	// Determine if row has many-to-one children rows attached. If so, copy all rows.
	// Get current row's eq in table
	var this_eq = thisRow.prevAll('tr').length;
	var max_eq = $('table#src_fld_map_table tr').length-1;
	//
	var eq_newrows = new Array();
	var eqi = 0;
	var total_new_rows = 0;
	// Go ahead and add first rows values/html
	var rows = thisRow[0].outerHTML;
	total_new_rows++;
	eq_newrows[eqi++] = this_eq;
	// Loop to find last row in composite mapping
	if (thisRow.next('tr').find('.temfld').length || this_eq == max_eq) {
		// Just one row
		thisRow.after(rows);
	} else {
		// Composite
		// Loop through all following rows until we hit another parent (or the end)
		for (var i=(this_eq+1); i<max_eq; i++) {
			// Get the current row
			var this_loop_row = $('table#src_fld_map_table tr').eq(i);
			// If a parent, then stop here
			if (this_loop_row.find('.temfld').length) break;
			// If a header, then stop here
			if (this_loop_row.find('.cat_hdr').length) break;
			// Add this row's html
			rows += this_loop_row[0].outerHTML;
			total_new_rows++;
			eq_newrows[eqi++] = i;
		}
		// Append new row(s) html
		$('table#src_fld_map_table tr').eq(this_eq+total_new_rows-1).after(rows);
	}
	// Loop through drop-downs in original row and copy their value (since selected value doesn't get copied)
	var mapFldEq = 0;
	thisRow.find('.mapfld').each(function(){
		$('table#src_fld_map_table tr').eq(this_eq+total_new_rows).find('.mapfld').eq(mapFldEq++).val( $(this).val() );
	});
	$('table#src_fld_map_table tr').eq(this_eq+total_new_rows).find('.temfld').val( thisRow.find('.temfld').val() );
	$('table#src_fld_map_table tr').eq(this_eq+total_new_rows).find('.presel').val( thisRow.find('.presel').val() );
	// Reset event drop-down value of new parent row
	$('table#src_fld_map_table tr').eq(this_eq+total_new_rows).find('select.evtfld').val('');
	// Hightlight rows
	for (var i=0; i<total_new_rows; i++) {
		highlightTableRowOb($('table#src_fld_map_table tr').eq(eq_newrows[i]+total_new_rows),2500);
	}
}

// Deletes existing row in mapping table
function deleteMapRow(ob) {
	// Get this row object
	var row = $(ob).parents('tr:first');
	// Get total rows in table
	var max_eq = $('table#src_fld_map_table tr').length-1;
	// Get current row's eq in table
	var this_eq = row.prevAll('tr').length;
	// If this is the parent row of a composite mapping, then do not allow it to be deleted
	if (row.find('.temfld').length && !row.next('tr').find('.temfld').length && this_eq < max_eq) {
		// If this row has a mapfld drop-down, then it is the parent. If so, stop here.
		simpleDialog('Because this is a composite mapping, you are not allowed to remove the first row. However, you may remove other rows in this composite mapping. Once you have removed all other rows for this composite mapping, you will then be able to remove this first row.',
					 'Cannot remove first row of composite mapping');
		return;
	}
	// Hightlight row for a moment before removing it
	highlightTableRowOb(row,1200);
	// Remove row after slight delay
	setTimeout(function(){
		row.remove();
		// Hide all but the last "add" composite field link (if applicable)
		hideCompositeMappingAddLinks();
	},300);
}
// Deletes existing field drop-down in mapping table row
function deleteMapField(ob) {
	// If only one field drop-down exists, don't allow user to remove it
	var prevDD = $(ob).prev('.mapfld');
	var ddName = prevDD.attr('name');
	if ($('select[name="'+ddName+'"]').length > 1) {
		prevDD.parents('div:first').remove();
	} else {
		simpleDialog("Sorry, but you are not allowed to remove all the drop-downs for this source field.");
	}
}

// Clone the Preview field drop-down (can have up to X drop-downs) on mapping
function addPreviewField() {
	// Set max instances
	var max_preview_fields = 5;
	// If hit max instances, then stop and warn
	if ($('.rtws_preview_field').length == max_preview_fields) {
		simpleDialog("Sorry, but you are not allowed to utilize more than "+max_preview_fields+" preview fields.");
		return;
	}
	// Clone last Preview field and insert copy after itself
	var last_preview_field = $('.rtws_preview_field:last');
	var new_preview_field = last_preview_field.clone().insertAfter(last_preview_field);
	// Reset value of new preview field
	new_preview_field.find('select').val('');
}

// Remove the associated Preview field drop-down
function deletePreviewField(ob) {
	// If only one drop-down exists, don't allow user to remove it
	if ($('.rtws_preview_field').length == 1) {
		simpleDialog("Sorry, but you are not allowed to remove all the Preview field drop-downs. One must be displayed minimally.");
		return;
	}
	// Remove it
	$(ob).parent().remove();
}