<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) exit;

$db = new RedCapDB();
$token = $db->getAPIToken($userid, $project_id);
$pg = new APIPlayground($token, $lang);

$response = $pg->getResponse();

$html = '';

// $pg->getResponse() may populate $_SESSION[api_exp_file_path]
if(($_SESSION['api_call'] == 'exp_file' || $_SESSION['api_call'] == 'exp_instr_pdf')
	&& isset($_SESSION['api_exp_file_path'])
	&& $_SESSION['api_exp_file_path'] != '')
{
	$img_a = RCView::a(array('href'=>"get_exp_file.php?pid=$project_id"),
				RCView::img(array('src'=>'download.png', 'style'=>'vertical-align:middle', 'title'=>$lang['api_46'])));
	$txt_a = RCView::a(array('href'=>"get_exp_file.php?pid=$project_id"), basename($_SESSION['api_exp_file_path']));
	$html = RCView::p(array(), $img_a . '&nbsp;' . $txt_a);
}
else
{
	$html = RCView::textarea(array('style'=>'font-size:1.1em; font-family:monospace', 'rows'=>7, 'cols'=>96, 'readonly'=>'readonly'), $response);
}

$html .= $lang['api_44'] . $pg->getStatus() . '<br /><br />';

$html = js_escape($html);

echo <<<EOF
$('div#exec_resp').html('$html');
$('div#exec_resp').show();
$('img#wait').hide();
$('textarea').resizable();
EOF;

if($_SESSION['api_call'] == 'del_events')
{
	foreach($_SESSION['api_events'] as $e)
	{
		echo "$('select#api_events option[value=\"$e\"]').remove();";
	}
	?>
	var len = $('select#api_events option').length;
	if(len < 5)
	{
		$('select#api_events').attr('size', len);
	}
	<?php
}

if($_SESSION['api_call'] == 'del_arms')
{
	foreach($_SESSION['arm_nums'] as $a)
	{
		echo "$('select#arm_nums option[value=\"$a\"]').remove();";
	}
	?>
	var len = $('select#arm_nums option').length;
	if(len < 5)
	{
		$('select#arm_nums').attr('size', len);
	}
	<?php
}
