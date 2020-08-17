// Replace all record "fetch" buttons with spinning progress icon
var recordProgressIcon = '<img src="'+app_path_images+'progress_circle.gif" class="imgfix2">';

$(function(){
	// Enable fixed table headers for event grid
	enableFixedTableHdrs('record_status_table');
	// Enable repeating forms buttons
	$('table td .btnAddRptEv').on({
		mouseenter: function(){
			$(this).removeClass('opacity50').removeClass('btn-defaultrc').addClass('btn-success');
		},
		mouseleave: function () {
			$(this).removeClass('btn-success').addClass('btn-defaultrc').addClass('opacity50');
		}
	});
});

function changeLinkStatus(ob) {
	$(ob).parents('div:first').find('a').removeClass('statuslink_selected').addClass('statuslink_unselected');
	$(ob).removeClass('statuslink_unselected').addClass('statuslink_selected');
}

function openDashboardSetup(rd_id) {
	if ($('#dashboard-config').css('display') != 'none') {
		$('#dashboard-config').hide('fast');
		return;
	}
	$('#rd_id').val(rd_id);
	$('select[name="sort_field_name"] option[value=""]').remove(); // Remove sort field blank option
	if (rd_id == '') {
		$('#dashboard-config input, #dashboard-config textarea').val('');
		$('#dashboard-config select>option:first-child, #dashboard-config select>optgroup:first-child>option:first-child').prop('selected', true);
		$('#btn_delete').hide();
	} else {
		$('#dashboard-config')[0].reset();
		$('#btn_delete').show();
	}
	$('#dashboard-config').hide().show('fast');
}

var rd_id_new = null;
function saveDashboard() {
	if (trim($('#title').val()) == '') {
		alert("Please enter a dashboard title");
		$('#title').focus();
		return;
	}
	$('#selected_forms_events').prop('disabled', false);
	showProgress(1);
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=RecordDashboardController:save', $('#dashboard-config').serializeObject(), function(data){
		$('#selected_forms_events').prop('disabled', true);
		showProgress(0,0);
		if (data == '0') {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		rd_id_new = json_data.rd_id;
		simpleDialog(json_data.content, json_data.title, null, 520, function(){
			showProgress(1);
			window.location.href = app_path_webroot+'DataEntry/record_status_dashboard.php?pid='+pid+'&rd_id='+rd_id_new;
		}, json_data.button);
	});
}

function openExcludeFormsEvents() {
	if ($('#choose_select_forms_events_div').css('display') == 'none') {
		// Parse the existing value to check all the correct checkboxes
		var selected_forms_events = trim($('#selected_forms_events').val());
		if (selected_forms_events == '') {
			// Select all
			selectAllFormsEvents(true);
		} else {
			// Deselect all first
			selectAllFormsEvents(false);
			// Select those from the input val
			var event_forms = selected_forms_events.split(',');
			for (var i = 0; i < event_forms.length; i++) {
				// Trim the excess whitespace.
				var event_form = event_forms[i].trim().split('][');
				// Add additional code here, such as:
				if (typeof event_form[1] == "undefined") {
				   var event_name = '';
				   var form = event_form[0].replace(']','').replace('[','');
				} else {
				   var event_name = event_form[0].replace(']','').replace('[','');
				   var form = event_form[1].replace(']','').replace('[','');
				}
				$('#choose_select_forms_events_div_sub input#ef-'+event_name+'-'+form).prop('checked',true);
			}
			
		}
	}
	$('#choose_select_forms_events_div').toggle();
}

function excludeEventsUpdate(update) {
	if (update == '1') {
		var selected_forms_events = new Array();
		var i=0;
		$('#choose_select_forms_events_div_sub input[type="checkbox"].efchk:checked').each(function(){
			var ef = $(this).prop('id').split('-');
			var event_name = longitudinal ? '['+ef[1]+']' : '';
			var form = '['+ef[2]+']';
			selected_forms_events[i++] = event_name+form;
		});
		$('#selected_forms_events').val(selected_forms_events.join(','));
	}
	$('#choose_select_forms_events_div').hide();
}

function selectAllFormsEvents(select_all) {
	$('#choose_select_forms_events_div_sub input[type="checkbox"]').prop('checked',select_all);
}

function selectAllInEvent(event_name,ob) {
	$('#choose_select_forms_events_div_sub input[id^="ef-'+event_name+'-"]').prop('checked',$(ob).prop('checked'));
}

function deleteDashboardConfirm(content,title,btnclose,btndelete) {
	simpleDialog(content, title, null, 520, null, btnclose, function(){
		deleteDashboard();
	}, btndelete);	
}

function deleteDashboard() {
	showProgress(1);
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=RecordDashboardController:delete', $('#dashboard-config').serializeObject(), function(data){
		showProgress(0,0);
		if (data == '0') {
			alert(woops);
			return;
		}
		var json_data = jQuery.parseJSON(data);
		rd_id_new = json_data.rd_id;
		simpleDialog(json_data.content, json_data.title, null, 520, function(){
			showProgress(1);
			window.location.href = app_path_webroot+'DataEntry/record_status_dashboard.php?pid='+pid+'&rd_id=';
		}, json_data.button);
		setTimeout(function(){
			showProgress(1);
			window.location.href = app_path_webroot+'DataEntry/record_status_dashboard.php?pid='+pid+'&rd_id=';
		},3000);
	});
}