<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) System::redirectHome();
$defaults = array(
	'api_call'           => 'exp_proj',
	'api_fmt'            => 'json',
	'api_return'         => 'json',
	'api_type'           => 'flat',
	'api_overwrite'      => 'normal',
	'api_forceAutoNumber'=> 'false',
	'api_return_content' => 'count',
	'api_name_label'     => 'raw',
	'api_header_label'   => 'raw',
	'api_checkbox_label' => 'false',
	'api_survey_field'   => 'false',
	'api_dag'            => 'false',
	'api_return_content' => 'count',
	'api_all_records'    => 'false',
	'code_tab'           => 'php',
	'api_returnMetadataOnly' => 'false',
	'api_exportFiles' => 'false'
);

foreach($defaults as $k => $v)
{
	$_SESSION[$k] = isset($_SESSION[$k]) ? $_SESSION[$k] : $v;
}

// prevent dangerous APIs when switching projects
if($Proj->project['status'] > 0 && in_array($_SESSION['api_call'], APIPlayground::dangerousAPIs()))
{
	$_SESSION['api_call'] = $defaults['api_call'];
}

unset($_SESSION['api_exp_file_path']);

$gets = array(
	'api_fmt',
	'api_call',
	'api_return',
	'api_event',
	'api_inst',
	'api_record',
	'api_field_name',
	'api_type',
	'api_name_label',
	'api_header_label',
	'api_checkbox_label',
	'api_survey_field',
	'api_dag',
	'api_report_id',
	'api_overwrite',
	'api_forceAutoNumber',
	'api_date_format',
	'api_return_content',
	'api_all_records',
	'code_tab',
	'api_filter_logic',
	'api_date_range_begin',
	'api_date_range_end',
	'api_returnMetadataOnly',
	'api_exportFiles',
	'api_csvdelimiter'
);

foreach($gets as $k)
{
	if(isset($_GET[$k]))
	{
		$_SESSION[$k] = rawurldecode(urldecode($_GET[$k]));
		redirect(APP_PATH_WEBROOT . "API/playground.php?pid=$project_id");
	}
}

$jq_gets = array(
	'arm_nums',
	'api_events',
	'api_insts',
	'api_records',
	'api_field_names',
	'api_csvdelimiter'
);

foreach($jq_gets as $k)
{
	if(isset($_GET[$k]))
	{
		// handle jquery multi-select data
		if($_GET[$k] == 'null')
		{
			$a = array();
		}
		elseif(strpos( $_GET[$k], ',') !== false)
		{
			$a = explode(',', $_GET[$k]);
		}
		else
		{
			$a = array($_GET[$k]);
		}

		$_SESSION[$k] = $a;
		header("Location: playground.php?pid=$project_id");
		exit;
	}
}

if(isset($_POST['api_data']))
{
	if($_SESSION['api_fmt'] == 'csv')
	{
		$_SESSION['api_data'] = str_replace(array("\n", "\r", "\r\n", "\t"), array('&#10;', '&#13;', '&#13;&#10;', '&#09;'), $_POST['api_data']);
	}
	else
	{
		$_SESSION['api_data'] = str_replace(array("\n", "\r", "\r\n", "\t", '  '), ' ', $_POST['api_data']);
	}

	header("Location: playground.php?pid=$project_id");
	exit;
}

if(isset($_FILES['api_file']))
{
	unset($_SESSION['api_file']);

	if($_FILES['api_file']['error'] === UPLOAD_ERR_OK)
	{
		$path = sys_get_temp_dir() . DS . $_FILES['api_file']['name'];
		if(!move_uploaded_file($_FILES['api_file']['tmp_name'], $path))
		{
			echo 'Cannot move uploaded file';
			exit;
		}

		$_SESSION['api_file_path'] = $path;
		$_SESSION['api_file_mime'] = $_FILES['api_file']['type'];
		$_SESSION['api_file_name'] = $_FILES['api_file']['name'];

		header("Location: playground.php?pid=$project_id");
		exit;
	}

	echo $pg->getUploadErrorMessage($_FILES['api_file']['error']);
	exit;
}

$db = new RedCapDB();
$token = $db->getAPIToken($userid, $project_id);
$pg = new APIPlayground($token, $lang);

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

renderPageTitle('<i class="fas fa-laptop-code"></i> ' . $lang['setup_143']);

$instr = RCView::p(array('style' => 'margin-top:20px;max-width:800px;'),
				$lang['system_config_528'] . ' ' .
				RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
								$lang['edit_project_142']) .
				$lang['period'] . ' ');
echo $instr;

if (empty($token))
{
	// need token
	$userInfo = $db->getUserInfoByUsername($userid);
	$requestAuto = ($api_token_request_type == 'auto_approve_all' || ($api_token_request_type == 'auto_approve_selected' && $userInfo->api_token_auto_request == '1')) ? 1 : 0;
	$req = RCView::div(array('class' => 'chklisthdr'), $lang['api_139'] . ' "' . RCView::escape($app_title) . '"');
	$req .= RCView::div(array('style' => 'margin:5px 0 0;'), ($requestAuto ? $lang['edit_project_183'] : $lang['edit_project_88']));
	$ui_id = $userInfo->ui_id;
	$todo_type = 'token access';
	if(ToDoList::checkIfRequestExist($project_id, $ui_id, $todo_type) > 0){
		$reqAPIBtn = RCView::button(array('class' => 'api-req-pending'), $lang['api_03']);
		$reqP = RCView::p(array('class' => 'api-req-pending-text'), $lang['edit_project_179']);
	}else{
		$reqAPIBtn = RCView::button(array('class' => 'jqbuttonmed', 'onclick' => "requestToken($requestAuto);"), ($requestAuto ? $lang['api_138'] : $lang['api_03']));
		$reqP = '';
	}
	$req .= RCView::div(array('class' => 'chklistbtn'), $reqAPIBtn.$reqP);
	if ($super_user && !defined("AUTOMATE_ALL")) {
		$req .= RCView::br();
		$approveLink = APP_PATH_WEBROOT . 'ControlCenter/user_api_tokens.php?action=createToken&api_username=' . $userid .
			'&api_pid=' . $project_id . '&goto_proj=1';
		$req .= RCView::button(array('onclick' =>"window.location.href='$approveLink';", 'class' => 'jqbuttonmed'), RCView::escape($lang['api_08'])) .
			RCView::SP . RCView::span(array('style' => 'color: red;'), $lang['edit_project_77']);
	}
	$req = RCView::div(array('id' => 'apiReqBoxId', 'class' => 'redcapAppCtrl'), $req);
	echo RCView::div(array(), $req);
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}

// api call opts
$api_prod_warn = '';
if($Proj->project['status'] > 0)
{
	$api_prod_warn = RCView::span(array('style'=>'font-size:10px;color:green;font-weight:bold;'), RCView::br() . $lang['api_72']);
}

$api_calls = $pg->getAPICalls($Proj);
foreach($api_calls as $group => $calls)
{
	foreach($calls as $k => $call)
	{
		// only used in docs
		if($k == 'imp_proj')
		{
			unset($api_calls[$group][$k]);
		}
	}
}

$api_call = isset( $_SESSION['api_call'] ) ? $_SESSION['api_call'] : '';
$api_call_opts = RCView::select(array('id'=>'api_call'), $api_calls, $api_call);
$td_call_label = RCView::td(array('style'=>'text-align:right;font-weight:bold;padding-top:2px;', 'valign'=>'top'), $lang['api_12']);
$td_call_select = RCView::td(array('style'=>'', 'valign'=>'top'), $api_call_opts . $api_prod_warn);
$tr_call = RCView::tr(array(), $td_call_label . $td_call_select);

// data format opts
$afs = array();
foreach( $pg->getAPIFormats() as $k)
{
	$afs[$k] = strtoupper($k);
}
$api_fmt_opts = RCView::select(array('id'=>'api_fmt'), $afs, $_SESSION['api_fmt']);
$td_fmt_label = RCView::td(array('style'=>'text-align:right'), $lang['api_13']);
$td_fmt_select = RCView::td(array(), $api_fmt_opts);
$tr_fmt = RCView::tr(array(), $td_fmt_label . $td_fmt_select);

$api_csvdelimiter_opts = RCView::select(array('id'=>'api_csvdelimiter'), DataExport::getCsvDelimiters(), $_SESSION['api_csvdelimiter']);
$td_csvdelimiter_label = RCView::td(array('style'=>'text-align:right'), $lang['data_export_tool_233'].$lang['colon']);
$td_csvdelimiter_select = RCView::td(array(), $api_csvdelimiter_opts);
$tr_csvdelimiter = RCView::tr(array(), $td_csvdelimiter_label . $td_csvdelimiter_select);

// multiple arms
$arms = $pg->getArms($project_id);
$arms_size = count($arms) <= 5 ? count($arms) : 5;
$select_opts = array('id'=>'arm_nums', 'multiple'=>'multiple', 'size'=>$arms_size);
if(!$longitudinal)
{
	$select_opts['disabled'] = 'disabled';
}
$arm_opts = RCView::select($select_opts, $arms, $_SESSION['arm_nums']);
$td_arms_label = RCView::td(array('style'=>'text-align:right'), $lang['api_22']);
$td_arms_select = RCView::td(array(), $arm_opts);
$tr_arms = RCView::tr(array(), $td_arms_label . $td_arms_select);

// error return
$api_return_opts = RCView::select(array('id'=>'api_return'), $afs, $_SESSION['api_return']);
$td_return_fmt_label = RCView::td(array('style'=>'text-align:right'), $lang['api_23']);
$td_return_fmt_select = RCView::td(array(), $api_return_opts);
$tr_return_fmt = RCView::tr(array(), $td_return_fmt_label . $td_return_fmt_select);

// single instruments
$insts = array('' => '--');
foreach($pg->getInstruments($project_id) as $i)
{
	$insts[$i] = $i;
}
$inst_opt = RCView::select(array('id'=>'api_inst'), $insts, $_SESSION['api_inst']);
$td_inst_label = RCView::td(array('style'=>'text-align:right'), $lang['api_24']);
$td_inst_select = RCView::td(array(), $inst_opt);
$tr_inst = RCView::tr(array(), $td_inst_label . $td_inst_select);

// multiple instruments
$insts = array();
foreach($pg->getInstruments($project_id) as $i)
{
	$insts[$i] = $i;
}
$insts_size = count($insts) <= 5 ? count($insts) : 5;
$inst_opts = RCView::select(array('id'=>'api_insts', 'multiple'=>'multiple', 'size'=>$insts_size, 'style'=>'padding: 1px 3px;'), $insts, $_SESSION['api_insts']);
$td_insts_label = RCView::td(array('style'=>'text-align:right'), $lang['api_29']);
$td_insts_select = RCView::td(array(), $inst_opts);
$tr_insts = RCView::tr(array(), $td_insts_label . $td_insts_select);

$event_names = $Proj->getUniqueEventNames();

// single event
$events = array('' => '--');
foreach($event_names as $e)
{
	$events[$e] = $e;
}
$select_opts = array('id'=>'api_event');
if(!$longitudinal)
{
	$select_opts['disabled'] = 'disabled';
}
$event_opts = RCView::select($select_opts, $events, $_SESSION['api_event']);
$td_event_label = RCView::td(array('style'=>'text-align:right'), $lang['api_25']);
$td_event_select = RCView::td(array(), $event_opts);
$tr_event = RCView::tr(array(), $td_event_label . $td_event_select);

// multiple events
$events = array();
foreach($event_names as $e)
{
	$events[$e] = $e;
}
$events_size = count($events) <= 5 ? count($events) : 5;
$select_opts = array('id'=>'api_events', 'multiple'=>'multiple', 'size'=>$events_size, 'style'=>'padding: 1px 3px;');
if(!$longitudinal)
{
	$select_opts['disabled'] = 'disabled';
}
$event_opts = RCView::select($select_opts, $events, $_SESSION['api_events']);
$td_events_label = RCView::td(array('style'=>'text-align:right'), $lang['api_32']);
$td_events_select = RCView::td(array(), $event_opts);
$tr_events = RCView::tr(array(), $td_events_label . $td_events_select);

$record_list = Records::getRecordList($project_id, $user_rights['group_id'], true);

// single record
$records = array('' => '--');
foreach($record_list as $r)
{
	$records[$r] = $r;
}
$record_opts = RCView::select(array('id'=>'api_record'), $records, $_SESSION['api_record']);
$td_record_label = RCView::td(array('style'=>'text-align:right'), $lang['api_26']);
$td_record_select = RCView::td(array(), $record_opts);
$tr_record = RCView::tr(array(), $td_record_label . $td_record_select);

// multiple records
$records = array();
foreach($record_list as $r)
{
	$records[$r] = $r;
}
$records_size = count($records) <= 5 ? count($records) : 5;
$record_opts = RCView::select(array('id'=>'api_records', 'multiple'=>'multiple', 'size'=>$records_size, 'style'=>'padding: 1px 3px;'),
				$records, $_SESSION['api_records']);
$td_records_label = RCView::td(array('style'=>'text-align:right'), $lang['api_31']);
$td_records_select = RCView::td(array(), $record_opts);
$tr_records = RCView::tr(array(), $td_records_label . $td_records_select);

// single field name
$field_names = array(''=>'--');
foreach($pg->getFieldNames($project_id) as $n)
{
	$field_names[$n] = $n;
}
$field_name_opts = RCView::select(array('id'=>'api_field_name'), $field_names, $_SESSION['api_field_name']);
$td_field_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_27']);
$td_field_name_select = RCView::td(array(), $field_name_opts);
$tr_field_name = RCView::tr(array(), $td_field_name_label . $td_field_name_select);

// multiple field names
$field_names = array();
foreach($pg->getFieldNames($project_id) as $n)
{
	$field_names[$n] = $n;
}
$field_names_size = count($field_names) <= 5 ? count($field_names) : 5;
$field_name_opts = RCView::select(array('id'=>'api_field_names', 'multiple'=>'multiple', 'size'=>$field_names_size, 'style'=>'padding: 1px 3px;'),
								$field_names, $_SESSION['api_field_names']);
$td_field_names_label = RCView::td(array('style'=>'text-align:right'), $lang['api_28']);
$td_field_names_select = RCView::td(array(), $field_name_opts);
$tr_field_names = RCView::tr(array(), $td_field_names_label . $td_field_names_select);

// type opts
$type_names = array();
foreach($pg->getTypeNames() as $n)
{
	$type_names[$n] = $n;
}
$type_name_opts = RCView::select(array('id'=>'api_type'), $type_names, $_SESSION['api_type']);
$td_type_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_30']);
$td_type_name_select = RCView::td(array(), $type_name_opts);
$tr_type_name = RCView::tr(array(), $td_type_name_label . $td_type_name_select);

$raw_label_types = $pg->getRawLabelTypes();

// raw or with labels
$label_names = array();
foreach($raw_label_types as $n)
{
	$label_names[$n] = $n;
}
$label_name_opts = RCView::select(array('id'=>'api_name_label'), $label_names, $_SESSION['api_name_label']);
$td_label_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_33']);
$td_label_name_select = RCView::td(array(), $label_name_opts);
$tr_label_name = RCView::tr(array(), $td_label_name_label . $td_label_name_select);

// date logic (for export records)
$td_date_begin_label = RCView::td(array('style'=>'text-align:right'), $lang['api_147']);
$td_date_begin_select = RCView::td(array(), RCView::text(array('id'=>'api_date_range_begin', 'value'=>$_SESSION['api_date_range_begin'])));
$tr_date_begin_name = RCView::tr(array(), $td_date_begin_label . $td_date_begin_select);
$td_date_end_label = RCView::td(array('style'=>'text-align:right'), $lang['api_148']);
$td_date_end_select = RCView::td(array(), RCView::text(array('id'=>'api_date_range_end', 'value'=>$_SESSION['api_date_range_end'])));
$tr_date_end_name = RCView::tr(array(), $td_date_end_label . $td_date_end_select);

// filter logic (for export records)
$td_filter_logic_label = RCView::td(array('style'=>'text-align:right'), $lang['api_127']);
$td_filter_logic_select = RCView::td(array(), RCView::text(array('id'=>'api_filter_logic', 'value'=>$_SESSION['api_filter_logic'])));
$tr_filter_logic_name = RCView::tr(array(), $td_filter_logic_label . $td_filter_logic_select);

// raw or with labels header
$header_names = array();
foreach($raw_label_types as $n)
{
	$header_names[$n] = $n;
}
$header_name_opts = RCView::select(array('id'=>'api_header_label'), $header_names, $_SESSION['api_header_label']);
$td_header_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_34']);
$td_header_name_select = RCView::td(array(), $header_name_opts);
$tr_header_name = RCView::tr(array(), $td_header_name_label . $td_header_name_select);

$bool_types = $pg->getBooleanTypes();

// export checkbox labels
$checkbox_names = array();
foreach($bool_types as $n)
{
	$checkbox_names[$n] = $n;
}
$checkbox_opts = RCView::select(array('id'=>'api_checkbox_label'), $checkbox_names, $_SESSION['api_checkbox_label']);
$td_checkbox_label = RCView::td(array('style'=>'text-align:right'), $lang['api_35']);
$td_checkbox_select = RCView::td(array(), $checkbox_opts);
$tr_checkbox_label = RCView::tr(array(), $td_checkbox_label . $td_checkbox_select);

// export survey fields
$survey_field_names = array();
foreach($bool_types as $n)
{
	$survey_field_names[$n] = $n;
}
$survey_field_opts = RCView::select(array('id'=>'api_survey_field'), $survey_field_names, $_SESSION['api_survey_field']);
$td_survey_field_label = RCView::td(array('style'=>'text-align:right'), $lang['api_36']);
$td_survey_field_select = RCView::td(array(), $survey_field_opts);
$tr_survey_field_label = RCView::tr(array(), $td_survey_field_label . $td_survey_field_select);

// returnMetadataOnly
$returnMetadataOnly_field_names = array();
foreach($bool_types as $n)
{
	$returnMetadataOnly_field_names[$n] = $n;
}
$returnMetadataOnly_field_opts = RCView::select(array('id'=>'api_returnMetadataOnly'), $returnMetadataOnly_field_names, $_SESSION['api_returnMetadataOnly']);
$td_returnMetadataOnly_field_label = RCView::td(array('style'=>'text-align:right'), $lang['api_129']);
$td_returnMetadataOnly_field_select = RCView::td(array(), $returnMetadataOnly_field_opts);
$tr_returnMetadataOnly_field_label = RCView::tr(array(), $td_returnMetadataOnly_field_label . $td_returnMetadataOnly_field_select);

// api_exportFiles
$returnXmlExportFiles = array();
foreach($bool_types as $n)
{
	$returnXmlExportFiles[$n] = $n;
}
$returnXmlExportFiles_opts = RCView::select(array('id'=>'api_exportFiles'), $returnXmlExportFiles, $_SESSION['api_exportFiles']);
$returnXmlExportFiles_field_label = RCView::td(array('style'=>'text-align:right'), $lang['api_149']);
$returnXmlExportFiles_field_select = RCView::td(array(), $returnXmlExportFiles_opts);
$tr_returnXmlExportFiles_field_label = RCView::tr(array(), $returnXmlExportFiles_field_label . $returnXmlExportFiles_field_select);


// export dags
$dag_names = array();
foreach($bool_types as $n)
{
	$dag_names[$n] = $n;
}
$dag_opts = RCView::select(array('id'=>'api_dag'), $dag_names, $_SESSION['api_dag']);
$td_dag_label = RCView::td(array('style'=>'text-align:right'), $lang['api_37']);
$td_dag_select = RCView::td(array(), $dag_opts);
$tr_dag = RCView::tr(array(), $td_dag_label . $td_dag_select);

// export report id
$report_names = array(''=>'--');
foreach(DataExport::getReportNames(null, !$user_rights['reports']) as $r)
{
	$report_names[$r['report_id']] = $r['title'];
}
$report_opts = RCView::select(array('id'=>'api_report_id'), $report_names, $_SESSION['api_report_id']);
$td_report_label = RCView::td(array('style'=>'text-align:right'), $lang['api_38']);
$td_report_select = RCView::td(array(), $report_opts);
$tr_report = RCView::tr(array(), $td_report_label . $td_report_select);

// overwrite opts
$overwrite_names = array();
foreach($pg->getOverwriteOptions() as $o)
{
	$overwrite_names[$o] = $o;
}
$overwrite_opts = RCView::select(array('id'=>'api_overwrite'), $overwrite_names, $_SESSION['api_overwrite']);
$td_overwrite_label = RCView::td(array('style'=>'text-align:right'), $lang['api_39']);
$td_overwrite_select = RCView::td(array(), $overwrite_opts);
$tr_overwrite = RCView::tr(array(), $td_overwrite_label . $td_overwrite_select);

// forceAutoNumber
$tr_forceAutoNumber = '';
if ($Proj->project['auto_inc_set']) {
	$forceAutoNumber_names = array();
	foreach($pg->getBooleanTypes() as $o)
	{
		$forceAutoNumber_names[$o] = $o;
	}
	$forceAutoNumber_opts = RCView::select(array('id'=>'api_forceAutoNumber'), $forceAutoNumber_names, $_SESSION['api_forceAutoNumber']);
	$td_forceAutoNumber_label = RCView::td(array('style'=>'text-align:right'), $lang['api_146']);
	$td_forceAutoNumber_select = RCView::td(array(), $forceAutoNumber_opts);
	$tr_forceAutoNumber = RCView::tr(array(), $td_forceAutoNumber_label . $td_forceAutoNumber_select);
}

// data field textarea
$data_textarea = "<textarea id='api_data' name='api_data' class='x-form-field notesbox' style='width:500px;'>".$_SESSION['api_data']."</textarea>";
$td_data_label = RCView::td(array('valign'=>'top', 'style'=>'padding-top:5px;text-align:right'), $lang['api_40']);
$data_form = RCView::form(array('id'=>'data_form', 'method'=>'post', 'action'=>"playground.php?pid=$project_id"), $data_textarea);
$td_data_textarea = RCView::td(array(), $data_form);
$tr_data = RCView::tr(array(), $td_data_label . $td_data_textarea);

// date format
$date_format_opts = RCView::select(array('id'=>'api_date_format'), $pg->getDateFormatOptions(), $_SESSION['api_date_format']);
$td_date_format_label = RCView::td(array('style'=>'text-align:right'), $lang['api_42']);
$td_date_format_select = RCView::td(array(), $date_format_opts);
$tr_date_format = RCView::tr(array(), $td_date_format_label . $td_date_format_select);

// file field
$file_name = '';
if(isset($_SESSION['api_file_path']))
{
	$file_name = basename($_SESSION['api_file_path']) . '<br />';
}
$file_input = RCView::input(array('id'=>'api_file', 'name'=>'api_file', 'type'=>'file'), $_SESSION['api_file']);
$td_file_label = RCView::td(array('style'=>'text-align:right'), $lang['api_45']);
$update_href = RCView::a(array('id'=>'update_file', 'href'=>'javascript:;'), '<small>' . $lang['api_41'] . '</small>');
$file_form = RCView::form(array('id'=>'file_form', 'method'=>'post', 'action'=>"playground.php?pid=$project_id", 'enctype'=>'multipart/form-data'),
				$file_input);
$td_file_input = RCView::td(array(), $file_name . $file_form . $update_href);
$tr_file = RCView::tr(array(), $td_file_label . $td_file_input);

// export all records
$all_records_names = array();
foreach($bool_types as $n)
{
	$all_records_names[$n] = $n;
}
$all_records_opts = RCView::select(array('id'=>'api_all_records'), $all_records_names, $_SESSION['api_all_records']);
$td_all_records_label = RCView::td(array('style'=>'text-align:right'), $lang['api_47']);
$td_all_records_select = RCView::td(array(), $all_records_opts);
$tr_all_records = RCView::tr(array(), $td_all_records_label . $td_all_records_select);

// return content
$return_content_opts = RCView::select(array('id'=>'api_return_content'), $pg->getReturnContentOptions(), $_SESSION['api_return_content']);
$td_return_content_label = RCView::td(array('style'=>'text-align:right'), $lang['api_43']);
$td_return_content_select = RCView::td(array(), $return_content_opts);
$tr_return_content = RCView::tr(array(), $td_return_content_label . $td_return_content_select);

$tbody_content = $tr_call . $tr_fmt;

switch($_SESSION['api_call'])
{
case 'exp_events':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'imp_events':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'del_events':
	$tbody_content .= $tr_events . $tr_return_fmt;
	break;
case 'exp_users':
	$tbody_content .= $tr_return_fmt;
	break;
case 'imp_users':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_inst_event_maps':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'imp_inst_event_maps':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_arms':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'imp_arms':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'del_arms':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'exp_surv_parts':
	$tbody_content .= $tr_inst . $tr_event . $tr_return_fmt;
	break;
case 'exp_surv_ret_code':
	$tbody_content .= $tr_inst . $tr_event . $tr_record . $tr_return_fmt;
	break;
case 'exp_surv_queue_link':
	$tbody_content .= $tr_record . $tr_return_fmt;
	break;
case 'exp_surv_link':
	$tbody_content .= $tr_inst . $tr_event . $tr_record . $tr_return_fmt;
	break;
case 'exp_instr':
	$tbody_content .= $tr_return_fmt;
	break;
case 'exp_repeating_forms_events':
	$tbody_content .= $tr_return_fmt;
	break;
case 'imp_repeating_forms_events':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_metadata':
	$tbody_content .= $tr_field_names . $tr_insts . $tr_return_fmt;
	break;
case 'imp_metadata':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_field_names':
	$tbody_content .= $tr_field_name . $tr_return_fmt;
	break;
case 'exp_next_id':
	$tbody_content .= $tr_return_fmt;
	break;
case 'exp_proj':
	$tbody_content .= $tr_return_fmt;
	break;
case 'exp_proj_xml':
	$tbody_content = $tr_call . $tr_returnMetadataOnly_field_label . $tr_records . $tr_field_names . $tr_events .
		$tr_survey_field_label .
		$tr_dag . $tr_filter_logic_name . $tr_returnXmlExportFiles_field_label . $tr_return_fmt;
	break;
case 'imp_proj_sett':
	$tbody_content .= $tr_data;
	break;
case 'imp_proj':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_records':
	$tbody_content .= $tr_type_name . $tr_records . $tr_field_names . $tr_insts . $tr_events .
		$tr_label_name . $tr_header_name . $tr_checkbox_label . $tr_survey_field_label .
		$tr_dag . $tr_filter_logic_name . $tr_date_begin_name . $tr_date_end_name . $tr_csvdelimiter . $tr_return_fmt;
	break;
case 'exp_reports':
	$tbody_content .= $tr_report . $tr_label_name . $tr_header_name . $tr_checkbox_label . $tr_csvdelimiter . $tr_return_fmt;
	break;
case 'imp_records':
	$tbody_content .= $tr_type_name . $tr_overwrite . $tr_forceAutoNumber . $tr_data . $tr_date_format . $tr_return_content . $tr_return_fmt;
        break;
case 'del_records':
	$tbody_content .= $tr_records . $tr_arms;
	break;
case 'imp_file':
	$tbody_content .= $tr_record . $tr_field_name . $tr_event . $tr_file . $tr_return_fmt;
	break;
case 'del_file':
	$tbody_content .= $tr_record . $tr_field_name . $tr_event . $tr_return_fmt;
	break;
case 'exp_file':
	$tbody_content .= $tr_record . $tr_field_name . $tr_event . $tr_return_fmt;
	break;
case 'exp_instr_pdf':
	$tbody_content .= $tr_record . $tr_event . $tr_inst . $tr_all_records . $tr_return_fmt;
	break;
}

$tbody = RCView::tbody(array(), $tbody_content);
$table = RCView::div(array('style'=>'margin:0 0 10px;'), $lang['api_77']) . RCView::table(array('id'=>'api_playground_params'), $tbody);
echo RCView::table(array('style'=>'width:750px;margin-top:20px;'), RCView::tbody(array(), RCView::tr(array(), RCView::td(array('class'=>'blue'), $table))));

// request
$raw_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_14']);
$raw_img = RCView::img(array('src'=>'arrow_up.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$raw_a = RCView::a(array('id'=>'raw', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px', 'name'=>'data'), $raw_img . $raw_span);
$raw_li = RCView::li(array('class'=>'active'), $raw_a);

$data_ul = RCView::ul(array(), $raw_li);
$data_div = RCView::div(array('id'=>'sub-nav', 'class'=>'d-print-none', 'style'=>'margin:30px 0 15px 0;'), $data_ul);
echo $data_div;
echo RCView::div(array('style'=>'margin-bottom:5px;'), $lang['api_75']);

echo RCView::textarea(array('style'=>'font-size:1.1em; font-family:monospace', 'rows'=>7, 'cols'=>96, 'readonly'=>'readonly'), $pg->getRawData());

// response
$resp_span = RCView::span(array('style'=>'vertical-align:middle'), $lang['api_16']);
$resp_img = RCView::img(array('src'=>'arrow_down.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$resp_a = RCView::a(array('id'=>'resp', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $resp_img . $resp_span);
$resp_li = RCView::li(array('class'=>'active'), $resp_a);

$data_ul = RCView::ul(array(), $resp_li);
$data_div = RCView::div(array('id'=>'sub-nav', 'class'=>'d-print-none', 'style'=>'margin:25px 0 15px 0;'), $data_ul);
echo $data_div;

echo RCView::div(array('style'=>'margin-bottom:10px;'), $lang['api_76']);
echo RCView::div(array('style'=>'width: 155px; float:left; margin:-15px 0 0 0'), RCView::br() . RCView::button(array('id'=>'exec_req', 'class'=>'jqbuttonmed'), RCView::span(array('class'=>'ui-button-text'), RCView::img(array('src'=>'arrow_up.png', 'style'=>'vertical-align:middle; position:relative; top:-1px', 'height'=>14, 'width'=>14)) . RCView::span(array('style'=>'vertical-align:middle'), $lang['api_73'])))) . RCView::div(array(), RCView::img(array('id'=>'wait', 'src'=>'progress_circle.gif', 'style'=>'display:none; margin:3px 0 0 0; width:16px; height:16px')) . RCView::img(array('src'=>'pixel.gif', 'style'=>'margin:3px 0 0 0; width:16px; height:16px'))) . RCView::br();

echo RCView::div(array('id'=>'exec_resp', 'style'=>'display:none;'), '&nbsp;');

// code views
$php_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_17']);
$php_img = RCView::img(array('src'=>'php.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$php_a = RCView::a(array('id'=>'php', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px', 'name'=>'langs'), $php_img . $php_span);
$php_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'php' ? 'active' : ''), $php_a);

$perl_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_18']);
$perl_img = RCView::img(array('src'=>'perl.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$perl_a = RCView::a(array('id'=>'perl', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $perl_img . $perl_span);
$perl_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'perl' ? 'active' : ''), $perl_a);

$python_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_19']);
$python_img = RCView::img(array('src'=>'python.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$python_a = RCView::a(array('id'=>'python', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $python_img . $python_span);
$python_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'python' ? 'active' : ''), $python_a);

$ruby_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_20']);
$ruby_img = RCView::img(array('src'=>'ruby.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$ruby_a = RCView::a(array('id'=>'ruby', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $ruby_img . $ruby_span);
$ruby_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'ruby' ? 'active' : ''), $ruby_a);

$java_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_21']);
$java_img = RCView::img(array('src'=>'java.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$java_a = RCView::a(array('id'=>'java', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $java_img . $java_span);
$java_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'java' ? 'active' : ''), $java_a);

$r_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_70']);
$r_img = RCView::img(array('src'=>'r.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$r_a = RCView::a(array('id'=>'r', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $r_img . $r_span);
$r_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'r' ? 'active' : ''), $r_a);

$curl_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_71']);
$curl_img = RCView::img(array('src'=>'curl.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$curl_a = RCView::a(array('id'=>'curl', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $curl_img . $curl_span);
$curl_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'curl' ? 'active' : ''), $curl_a);

$code_ul = RCView::ul(array(), $php_li . $perl_li . $python_li . $ruby_li . $java_li . $r_li . $curl_li);
$code_div = RCView::div(array('id'=>'sub-nav', 'class'=>'d-print-none', 'style'=>'margin:20px 0 15px 0;'), $code_ul);
echo $code_div;
echo RCView::div(array('class'=>'clear'), '&nbsp;');
echo RCView::div(array('style'=>'margin-bottom:5px;'), $lang['api_74']);
echo RCView::textarea(array('style'=>'font-size:1.1em; font-family:monospace', 'rows'=>12, 'cols'=>96, 'readonly'=>'readonly'), $pg->getCode());
echo RCView::br() . RCView::br() . RCView::a(array('href'=>"get_api_code.php?lang=$_SESSION[code_tab]"), RCView::img(array('src'=>'download.png', 'style'=>'vertical-align:middle'))) . ' ' . RCView::a(array('href'=>"get_api_code.php?lang=$_SESSION[code_tab]"), $lang['api_68'] . $pg->getLangName($_SESSION['code_tab']) . $lang['api_69']);

$langs_js = '';
foreach(APIPlayground::getLangs() as $l)
{
	$langs_js .= "\$('a#$l').click(function(){window.location.href='?pid=$project_id&code_tab=$l#langs';});\n";
}

$single_js = '';
$selects = array(
	'api_call',
	'api_fmt',
	'api_return',
	'api_event',
	'api_record',
	'api_inst',
	'api_field_name',
	'api_type',
	'api_name_label',
	'api_header_label',
	'api_checkbox_label',
	'api_survey_field',
	'api_dag',
	'api_report_id',
	'api_overwrite',
	'api_forceAutoNumber',
	'api_date_format',
	'api_return_content',
	'api_all_records',
	'api_returnMetadataOnly',
	'api_exportFiles'
);
foreach($selects as $s)
{
	// $single_js .= "\$('select#$s').change(function(){debugger; console.log('?pid=$project_id&$s='+\$('select#$s :selected').val());window.location.href='?pid=$project_id&$s='+\$('select#$s :selected').val();});";
	$single_js .= "\$('select#$s').change(function(){window.location.href='?pid=$project_id&$s='+\$('select#$s :selected').val();});";
}

$multi_js = '';
$selects = array(
	'arm_nums',
	'api_insts',
	'api_records',
	'api_events',
	'api_field_names',
	'api_csvdelimiter'
);
foreach($selects as $s)
{
	$multi_js .= "\$('select#$s').change(function(){window.location.href='?pid=$project_id&$s='+\$('select#$s').val();});";
}

$text_js = '';
$textboxes = array(
	'api_filter_logic',
	'api_date_range_begin',
	'api_date_range_end'
);
foreach($textboxes as $s)
{
	$text_js .= "\$('input[type=text]#$s').change(function(){window.location.href='?pid=$project_id&$s='+\$('input[type=text]#$s').val();});";
}

// js
echo <<<EOF
<script type="text/javascript">
$langs_js
$single_js
$multi_js
$text_js

/* data textarea */
$('#api_data').blur(function(){
	showProgress(1);
	$('form#data_form').submit();
});

/* file upload */
$('a#update_file').click(function(){
	showProgress(1);
	$('form#file_form').submit();
});

/* execute api request */
$('button#exec_req').click(function(){
  $('img#wait').show();
  $.ajax({
      url: 'playground_api_call.php?pid=$project_id',
      success: function(data) { eval(data); }
  });
});

$('textarea').resizable();

$('select[multiple]').keydown(function( event ) {
	if (event.which == 38 || event.which == 40) {
		event.preventDefault();
	}
});

</script>
EOF;

echo RCView::br() . RCView::br() . RCView::br();
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
