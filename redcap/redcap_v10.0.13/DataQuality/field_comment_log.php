<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Page is only usable is Field Comment Log is enabled
if ($data_resolution_enabled != '1')
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}

// Header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Page title
renderPageTitle("<i class=\"fas fa-comments\"></i> {$lang['dataqueries_141']}");

// Instructions
print RCView::p(array('style'=>'margin-top:0;'),
		$lang['dataqueries_172']
	  );
// Display field comment log table
$dq = new DataQuality();
print RCView::div(
	array('id'=>'field_comment_parent','style'=>'margin:20px 0 0;'),
	$dq->renderFieldCommentLog(urldecode(label_decode(isset($_GET['record']) ? $_GET['record'] : '')), isset($_GET['event_id']) ? $_GET['event_id'] : '',
	isset($_GET['field']) ? $_GET['field'] : '', isset($_GET['group_id']) ? $_GET['group_id'] : '', isset($_GET['user']) ? $_GET['user'] : '', label_decode(urldecode(isset($_GET['keyword']) ? $_GET['keyword'] : '')))
);

?>
<style type="text/css">
span.keyword_search { color: #C00000; background-color: yellow; }
</style>
<script type="text/javascript">
// Reload the field comment log table
function reloadFieldCommentLog(show_progress) {
	// Set vars
	var record = $('#choose_record').val();
	var event_id = $('#choose_event').val();
	var user = $('#choose_user').val();
	var group_id = ($('#choose_dag').length) ? $('#choose_dag').val() : '';
	var field = ($('#choose_field').length) ? $('#choose_field').val() : '';
	var keyword = trim($('#choose_keyword').val());
	// Keyword cannot be single letter
	if (keyword.length == 1) {
		simpleDialog('<?php echo js_escape($lang['dataqueries_230']) ?>','<?php echo js_escape($lang['global_01']) ?>');
		return;
	}
	// Determine if we should display progress icon
	show_progress = !!show_progress;
	if (show_progress) showProgress(1);
	// Set query string
	var query_string = 'pid='+pid+'&record='+record+'&field='+field+'&event_id='+event_id+'&group_id='+group_id+'&user='+user+'&keyword='+keyword;
	// Make ajax call
	$.get(app_path_webroot+'DataQuality/field_comment_log_ajax.php?'+query_string, { }, function(data) {
		if (data == '0') { alert(woops); return; }
		var json_data = jQuery.parseJSON(data);
		$('#field_comment_parent').html(json_data.html);
		initWidgets();
		if (show_progress) showProgress(0);
		// Modify URL without reloading page
		modifyURL(app_path_webroot+page+'?'+query_string);
	});
}
// Display "search tips" dialog
function openFieldCommentLogSearchTips() {
	simpleDialog('<?php echo js_escape($lang['dataqueries_232'].' "'.implode('", "', $dq->FCL_keywords_ignore).'"'.$lang['period'].'<br><br>'.$lang['dataqueries_251']) ?>','<?php echo js_escape($lang['dataqueries_141'].$lang['colon'].' '.$lang['dataqueries_231']) ?>');
}
</script>
<?php

// Footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';