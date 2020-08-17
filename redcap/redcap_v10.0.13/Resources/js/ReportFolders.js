$(function(){
	// Report searching
	enableReportSearching();
});

function openReportFolders() {
	showProgress(1);
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFoldersDialog',{  },function(data){
		showProgress(0, 0);
		if (data == '') { alert(woops);return; }
		$('#report_folders_popup').html(data).dialog({ bgiframe: true, modal: true, width: 894, buttons: { 
			'Close': function() { 
				$(this).dialog('destroy');
			}
		}});
		setDragDropReportFolderTable();
	});
}
function setDragDropReportFolderTable() {			
	$('table#report_folders_list tbody').sortable({
	  containment:'parent',
	  tolerance: 'pointer',
	  update: function( event, ui ) {
			var data = $('table#report_folders_list tbody').sortable('serialize');
			$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderResort',{ data: data },function(data){
				updateReportPanel();
				updateReportFolderDropdown();					
			});
		}
	});
}

function checkReportFolderNameSubmit(event)
{
	if(event && event.keyCode == 13)
	{
		newReportFolder();
	}
}

function newReportFolder()
{
	$('#folderName').val( trim($('#folderName').val()) );
	if ($('#folderName').val() == '') {
		simpleDialog(langProjFolder05,null,'',330,"$('#folderName').focus();");
		return;
	}
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderCreate',{ folder_name: $('#folderName').val() },function(data){
		if (data != '1') { alert(woops);return; }
		$('#folderName').val('').focus();
		updateReportPanel();
		updateReportFolderTable();
		updateReportFolderDropdown();
	});
}

function editFolder(folder_id)
{
	$("[id^=rft_]").removeClass('hidden');
	$("[id^=rfi_]").addClass('hidden');	
	$('#rft_'+folder_id).addClass('hidden');
	$('#rfi_'+folder_id).removeClass('hidden');
	$('#rfiv_'+folder_id).focus();
}

function editFolderSave(folder_id)
{
	$('#rfiv_'+folder_id).val( trim($('#rfiv_'+folder_id).val()) );
	if ($('#rfiv_'+folder_id).val() == '') {
		simpleDialog(langProjFolder05,null,'',330,"$('#rfiv_"+folder_id+"').focus();");
		return;
	}
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderEdit',{ folder_id: folder_id, folder_name: $('#rfiv_'+folder_id).val() },function(data){
		$('#rft_'+folder_id).removeClass('hidden');
		$('#rfi_'+folder_id).addClass('hidden');	
		if (data != '1') { alert(woops);return; }
		updateReportPanel();
		updateReportFolderTable();
		updateReportFolderDropdown();
	});
}

function deleteFolder(folder_id)
{
	simpleDialog(langDelFolder,langDelete,'',330,null,langCancel,"deleteFolderSave("+folder_id+");",langDelete);
}

function deleteFolderSave(folder_id)
{
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderDelete',{ folder_id: folder_id },function(data){
		if (data != '1') { alert(woops);return; }
		var ddselected = $('#folder_id').val();
		updateReportPanel();
		updateReportFolderTable();
		updateReportFolderDropdown();
		if (ddselected == folder_id) {
			$('#report_folders_assign').html('');
		}
	});
}

function updateReportFolderTable(folder_id)
{
	if (typeof folder_id == "undefined") folder_id = '';	
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderDisplayTable',{ folder_id: folder_id },function(data){
		$('#folders').html(data);
		setDragDropReportFolderTable();
	});
}

function updateReportFolderDropdown()
{
	var ddselected = $('#folder_id').val();
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderDisplayDropdown',{  },function(data){
		$('#select_folders').html(data)
		$('#folder_id').val(ddselected);
	});	
}

function updateReportFolderTableAssign(folder_id)
{
	var hide_assigned = $('#hide_assigned_rf').prop('checked') ? 1 : 0;
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderDisplayTableAssign',{ folder_id: folder_id, hide_assigned: hide_assigned },function(data){
		$('#report_folders_assign').html(data);
	});	
}

function hideAssignedReportFolders()
{
	var folder_id = $('#folder_id').val();
	var hide_assigned = $('#hide_assigned_rf').prop('checked') ? 1 : 0;
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderDisplayTableAssign',{ folder_id: folder_id, hide_assigned: hide_assigned },function(data){
		$('#report_folders_assign').html(data);
	});	
}

function rfAssignSingle(folder_id, report_id, checked) 
{	
	checked = checked ? '1' : '0';
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderAssign',{ folder_id: folder_id, report_id: report_id, checked: checked },function(data){
		if (data != '1') { 
			$('input#rid_'+report_id).prop('checked', false);
			alert(woops);
			return; 
		}
		updateReportPanel();
		highlightSavedReport(report_id);
	});
}

function checkAllReportFolders(folder_id, ids)
{
	var checkAll = $('input#checkAll').is(':checked');
	$.each(ids.split(','), function( k, v ) {
		$('input#rid_' + v).prop('checked', checkAll);
	});
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportFolderAssign',{ folder_id: folder_id, ids: ids, checkAll: checkAll },function(data){
		updateReportPanel();
		highlightSavedReportAll();
	});
}

function highlightSavedReport(report_id)
{
	$('#report_tr_' + report_id + ' td').effect('highlight', 1500);
	$('#report_saved_' + report_id).show().fadeOut(1500);
}

function highlightSavedReportAll()
{
	$('#report_folders_assign tr[id^=report_tr_] td').effect('highlight', 1500);
	$('#report_folders_assign [id^=report_saved_]').show().fadeOut(1500);
}

function openSearchReports()
{
	$("#menuLnkEditReports, #menuLnkProjectFolders, #menuLnkSearchReports").hide();
	$("#searchReportsDiv").show();
	$("#searchReports").focus();
}

function closeSearchReports()
{
	$("#menuLnkEditReports, #menuLnkProjectFolders, #menuLnkSearchReports").show();
	$("#searchReportsDiv").hide();
	$("#searchReports").val('');
}

function enableReportSearching()
{
	if (!$('#searchReports').length) return;
	$('#searchReports').autocomplete({
		source: app_path_webroot+'index.php?pid='+pid+'&route=ReportController:reportSearch',
		minLength: 2,
		delay: 150,
		focus: function( event, ui ) {
			return false;
		},
		select: function( event, ui ) {
			window.location.href = app_path_webroot+'DataExport/index.php?pid='+pid+'&report_id='+ui.item.value;
			return false;
		}
	})
	.data('ui-autocomplete')._renderItem = function( ul, item ) {
		return $("<li class='fs11'></li>")
			.data("item", item)
			.append("<a class='fs11'>"+item.label+"</a>")
			.appendTo(ul);
	};
}

// Update left-hand menu panel of Reports
function updateReportPanel(folder_id, collapsed) {
	if (typeof folder_id == "undefined") folder_id = '';
	if (typeof collapsed == "undefined") collapsed = '0';
	var collapse = (collapsed == '1') ? '0' : '1';
	$.post(app_path_webroot+'DataExport/render_report_panel_ajax.php?pid='+pid, { folder_id: folder_id, collapse: collapse }, function(data){
		$('#report_panel').remove();
		if (data != '') {
			// Update the left-hand menu
			if ($('#external_modules_panel').length) {
				$('#external_modules_panel').before(data);
			} else {
				$('#help_panel').before(data);
			}
			// Add fade mouseover for "Edit instruments" and "Edit reports" links on project menu
			$("#menuLnkEditReports").mouseenter(function() {
				$(this).removeClass('opacity50');
				if (isIE) $(this).find("img").removeClass('opacity50');
			}).mouseleave(function() {
				$(this).addClass('opacity50');
				if (isIE) $(this).find("img").addClass('opacity50');
			});
			projectMenuToggle('#projMenuReports');
			enableReportSearching();
		}
	});
}