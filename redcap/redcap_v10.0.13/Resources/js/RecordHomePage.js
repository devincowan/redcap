$(function(){
	// Center the record ID name with the table
	var eg = $('#event_grid_table');
	if (eg.width() < 800) $('#record_display_name').width( eg.width() );
	$('#record_display_name').css('visibility','visible');
	// Open upcoming calendar events popup
	$('.btn.rhp_calevents').click(function(){
		$.post(app_path_webroot+'index.php?route=DataEntryController:renderUpcomingCalEvents&pid='+pid,{ days: 7, record: getParameterByName('id') },function(data){
			simpleDialog(data,'<img src="'+app_path_images+'date.png"> '+langRH01,'rhp_calevents_popup',570);
			fitDialog($('#rhp_calevents_popup'));
		});
	});
	// Open upcoming scheduled invitations popup
	$('.btn.rhp_schedinvites').click(function(){
		$.post(app_path_webroot+'index.php?route=SurveyController:renderUpcomingScheduledInvites&pid='+pid,{ days: 7, record: getParameterByName('id') },function(data){
			simpleDialog(data,'<img src="'+app_path_images+'clock_fill.png"> '+langRH02,'rhp_schedinvites_popup',570);
			fitDialog($('#rhp_schedinvites_popup'));
		});
	});
	// Enable repeating forms buttons
	$('.btnAddRptEv').on({
		mouseenter: function(){
			$(this).removeClass('btn-defaultrc').addClass('btn-success');
			var opacity = $(this).attr('opacity');
			if (typeof opacity !== typeof undefined && opacity !== false) {
				$(this).removeClass('opacity'+opacity);
			}
		},
		mouseleave: function () {
			$(this).removeClass('btn-success').addClass('btn-defaultrc');
			var opacity = $(this).attr('opacity');
			if (typeof opacity !== typeof undefined && opacity !== false) {
				$(this).addClass('opacity'+opacity);
			}
		}
	});
	// Enable fixed table headers for event grid
	enableFixedTableHdrs('event_grid_table');
	setTimeout(function(){
		// Clickable buttons to collapse tables
		initBtnsCollapseTables();
		// Move collapse icon position for main table
		$('#event_grid_table .btn-table-collapse').css('position','absolute').show().position({ my: 'left top', at: 'left+3 top+4', of: $('#event_grid_table') });
		setTimeout(function(){
			$('#event_grid_table .btn-table-collapse').position({ my: 'left top', at: 'left+3 top+4', of: $('#event_grid_table') });
		},500);
	},500);
});

// Reload the calendar events popup
function reloadCalendarEventsPopup() {
	if ($('#rhp_calevents_popup').length) {
		$('#rhp_calevents_popup').remove();
		$('.btn.rhp_calevents').trigger('click');
	}
}

// Clickable buttons to collapse tables
function initBtnsCollapseTables() {
	$('.btn-table-collapse, .btn-event-collapse').not('.btn-collapse-activated').on({
		mouseenter: function(){
			if ($(this).attr('collapsed') == '1') return;
			$(this).removeClass('opacity50').addClass($(this).hasClass('btn-table-collapse') ? 'btn-primaryrc' : 'btn-warning');
		},
		mouseleave: function () {
			if ($(this).attr('collapsed') == '1') return;
			$(this).addClass('opacity50').removeClass($(this).hasClass('btn-table-collapse') ? 'btn-primaryrc' : 'btn-warning');
		}
	});
	$('#recordhome-uncollapse-all').not('.btn-collapse-activated').on({
		mouseenter: function(){
			$(this).removeClass('opacity65');
		},
		mouseleave: function () {
			$(this).addClass('opacity65');
		}
	});
	// Uncollapse all tables/events on the Record Home Page
	$('#recordhome-uncollapse-all').not('.btn-collapse-activated').click(function(){		
		var targetids = new Array();
		var i = 0;
		$('.btn-table-collapse.btn-primaryrc, .btn-event-collapse.btn-warning').each(function(){
			if ($(this).attr('targetid') == null) return;
			// Add .collapse-no-save class to prevent individual AJAX request from firing from each
			$(this).addClass('collapse-no-save').trigger('click');
			// Get targetid
			targetids[i++] = $(this).attr('targetid');
		});
		if (!targetids.length) return;
		// Save all at once via AJAX
		$.post(app_path_webroot+'DataEntry/record_home_collapse_table.php?pid='+pid,{ collapse: 0, object: 'record_home', targetid: targetids.join(',') });
		// Reset footer position
		displayUncollapseAllLink();
		setProjectFooterPosition();
	});
	// Collapse or uncollapse tables or event columns
	$('.btn-table-collapse, .btn-event-collapse').not('.btn-collapse-activated').click(function(){
		var targetid = $(this).attr('targetid');
		var collapsed = ($(this).attr('collapsed') == '1') ? '1' : '0';
		var collapse = Math.abs(collapsed-1);
		$(this).attr('collapsed', collapse);
		// If the table has floating headers or column, then ensure that all instances of it get the "collapsed" attr set
		if (targetid == 'event_grid_table') {
			$('button[targetid="event_grid_table"]').attr('collapsed', collapse);
		}
		if ($(this).hasClass('btn-table-collapse')) {
			// Collapse table
			if (collapsed == '1') {
				$(this).removeClass('btn-primaryrc');
				$('#'+targetid+' tr:not(:first)').removeClass('hidden').show();
			} else {
				$(this).addClass('btn-primaryrc');
				$('#'+targetid+' tr:not(:first)').hide();
			}
		} else {
			// Collapse event columns
			if (collapsed == '1') {
				$(this).removeClass('btn-warning');
				$('.eventCol-'+targetid).removeClass('hidden').show();
				$('span:first', this).removeClass('fas fa-forward').addClass('fas fa-backward');
			} else {
				$(this).addClass('btn-warning');
				$('.eventCol-'+targetid).hide();
				$('span:first', this).removeClass('fas fa-backward').addClass('fas fa-forward');
			}
			targetid = 'repeat_event-'+targetid;
		}
		// If button contains .collapse-no-save class, then skip the AJAX save
		if (!$(this).hasClass('collapse-no-save')) {
			$.post(app_path_webroot+'DataEntry/record_home_collapse_table.php?pid='+pid,{ collapse: collapse, object: 'record_home', targetid: targetid });
		}
		// Now remove the .collapse-no-save class since we are done with this item
		$(this).removeClass('collapse-no-save');
		// Enable fixed table headers for event grid
		enableFixedTableHdrs('event_grid_table');
		// Clickable buttons to collapse tables
		initBtnsCollapseTables();
		// Reset footer position
		displayUncollapseAllLink();
		setProjectFooterPosition();
	});
	// If some tables or event columns are collapsed, then display the "Uncollapse all" link
	displayUncollapseAllLink();
	// Add class to all buttons to note that they are activated
	$('#recordhome-uncollapse-all, .btn-table-collapse, .btn-event-collapse').addClass('btn-collapse-activated');
}

// If some tables or event columns are collapsed, then display the "Uncollapse all" link
function displayUncollapseAllLink() {
	if ($('.btn-table-collapse.btn-primaryrc, .btn-event-collapse.btn-warning').length) {
		$('#recordhome-uncollapse-all').show();
	} else {
		$('#recordhome-uncollapse-all').hide();
	}
}

// Action when click button to repeat an event - adds new column to table
function gridAddRepeatingEvent(ob) {	
	try { 
		// Only if fixed column is enabled, then destory dataTable and rebuild
		if ($('.dataTable.DTFC_Cloned').length || $('.dataTable.fixedHeader-floating').length) {
			rcDataTable.destroy();
			$('#event_grid_table thead').insertBefore('#event_grid_table tbody');
			$('#event_grid_table').addClass('dataTable');
		}
	} catch(e) { }
	
	var index = $(ob).parentsUntil('th').parent().index();
	var cellTag = 'th';
	var cell, clone;
	// Get current instance
	var newInstance = $(ob).attr('instance')*1 + 1;
	// Get event_id
	var event_id = $(ob).attr('event_id');	
	// Remove button just pressed and the arrow button
	$('table#event_grid_table tr th:eq('+(index)+') .divBtnAddRptEv, table#event_grid_table tr th:eq('+(index)+') .btn-event-collapse').css('display','none');
	// Loop through table rows
	$('#event_grid_table tr').each(function(){	
		// Find cell
		cell = $(cellTag+':eq('+index+')', this);
		// Clone the cell and append it
		clone = cell.clone();
		cell.after(clone);
		clone.hide().fadeIn(500);
		// Modify new column
		if (cellTag == 'th') {
			// Header
			clone.find('.instanceNum').html(newInstance);
			clone.find('.evTitle').remove();
			clone.find('.custom_event_label').remove();
			clone.css({'background-color':'#C1FFC1'});
			// Add delete icon
			clone.prepend('<div style="text-align:center;margin-bottom:10px;"><a href="javascript:;" onclick="gridDeleteRepeatingEvent(this)"><img src="'+app_path_images+'cross.png"></a></div>');
			// Set for all remaining loops
			cellTag = 'td';
		} else {	
			// Normal row
			clone.css({'background-color':'#C1FFC1'});
			clone.find('.gridLockEsign').remove();
			clone.find('.fa-times').remove();
			// Set all status icons as gray icon
			clone.find('img').prop('src',app_path_images+'circle_gray.png');
			// Change all icon URLs to point to new instance
			clone.find('a').each(function(){
				var href = $(this).attr('href').replace('&instance=','&oldinstance=');
				$(this).prop('href', href+'&instance='+newInstance);
			});
		}
	});
	// Make sure all instance numbers are displayed (if this is the first repeating event)
	$('.evGridHdrInstance-'+event_id).show();	
	// Re-enable fixed table headers for event grid
	// setTimeout(function(){
	// 	enableFixedTableHdrs('event_grid_table');
	// },1550);
}

// Action when click delete icon to remove tentative repeating event
function gridDeleteRepeatingEvent(ob) {	
	var index = $(ob).parentsUntil('th').parent().index();
	var cellTag = 'th';
	var cell, this_event_id;
	// Loop through table rows
	$('#event_grid_table tr').each(function(){
		// Remove cell
		cell = $(cellTag+':eq('+index+')', this);
		if (cellTag == 'th') {	
			cell.fadeOut('slow',function(){ 
				$(this).remove(); 
				// Restore "repeat" button and arrow button for event
				$('#event_grid_table tr th:eq('+(index-1)+') .divBtnAddRptEv, #event_grid_table tr th:eq('+(index-1)+') .btn-event-collapse').show();
			});
			// Set for all remaining loops
			cellTag = 'td';
		} else {
			cell.fadeOut(500,function(){ $(this).remove(); });
		}
	});	
	// Re-enable fixed table headers for event grid
	setTimeout(function(){
		enableFixedTableHdrs('event_grid_table');
	},550);
}

function openDeleteRecordLogDialog() {
	if (!$('#allow_delete_record_from_log').prop('checked')) return;
	simpleDialog(null,null,'allow_delete_record_from_log_confirm',450,function(){
		$('#allow_delete_record_from_log').prop('checked',false);
	},'Cancel',function(){ 
		var val = $('#allow_delete_record_from_log_delete').val().trim().toLowerCase();
		if (val == 'delete') {
			$('#allow_delete_record_from_log').prop('checked',true);
		} else {
			simpleDialog('Invalid value entered! Try again.',null,null,400,"setTimeout('openDeleteRecordLogDialog()',250);",'Close');
		}
		$('#allow_delete_record_from_log_delete').val('');
	},'Confirm');
}