<?php


require dirname(dirname(__FILE__)) . "/Config/init_global.php";

// aliases for repeats
$ae = array();
$ach = array('class'=>'headerrc');
$aci = array('class'=>'item');
$acp = array('class'=>'pa');
$achsub = array('class'=>'h sub');
$br = RCView::br();
$capi = '<code>' . APP_PATH_WEBROOT_FULL . 'api/</code>';
$cp = '<code>POST</code>';
$req = RCView::b($lang['api_docs_063']);
$opt = RCView::b($lang['api_docs_064']);
$pre_ae_capi = RCView::pre(array('style'=>'overflow: hidden;'), $capi);
$pre_ae_cp = RCView::pre(array('style'=>'overflow: hidden;'), $cp);
$div_ach_l50 = RCView::div($ach, $lang['api_docs_050']);
$div_acp_token = RCView::div($acp, RCView::span($aci, 'token') . $br . $lang['api_docs_055']);
$div_ach_l47 = RCView::div($ach, $lang['api_docs_047']);
$div_ach_l54 = RCView::div($ach, $lang['api_docs_054']);
$div_ach_l51 = RCView::div($ach, $lang['api_docs_051']);
$div_ach_l49 = RCView::div($ach, $lang['api_docs_049']);
$b_l75 = RCView::b($lang['api_docs_075']);
$div_ret_fmt = RCView::div($acp, RCView::span($aci, 'returnFormat') . $br . $lang['api_docs_081']);
$div_acp_fmt = RCView::div($acp, RCView::span($aci, 'format') . $br . $lang['api_docs_057']);
$div_acp_fmt_odm = RCView::div($acp, RCView::span($aci, 'format') . $br . $lang['api_docs_250']);
$div_acp_fmt_no_dflt = RCView::div($acp, RCView::span($aci, 'format') . $br . $lang['api_docs_268']);
$div_acp_data = RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_093']);
$div_acp_action = RCView::div($acp, RCView::span($aci, 'action') . $br . 'import');
$div_acp_ret_fmt = RCView::div($acp, RCView::span($aci, 'returnFormat') . $br . $lang['api_docs_140']);
$div_perm = RCView::div($ach, $lang['api_docs_243']);
$div_perm_e = $div_perm . RCView::div($achsub, $lang['api_docs_244']);
$div_perm_d = $div_perm . RCView::div($achsub, $lang['api_docs_260']);
$div_perm_i = $div_perm . RCView::div($achsub, $lang['api_docs_245']);
$div_perm_iuser = $div_perm . RCView::div($achsub, $lang['api_docs_246']);
$div_perm_idesign = $div_perm . RCView::div($achsub, $lang['api_docs_247']);
$div_perm_iproj = $div_perm . RCView::div($achsub, $lang['api_docs_248']);

// what to show
$content = isset($_GET['content']) ? $_GET['content'] : 'default';
$show = '';

// default
if($content == 'default')
{
	$show = implode('', array(
		RCView::h4($ae, $lang['api_docs_001']),
		RCView::p($ae, $lang['api_docs_002']),
		RCView::p($ae, $lang['api_docs_003']),
		RCView::p($ae, $lang['api_docs_004'])
	));
}

// tokens
if($content == 'tokens')
{
	$show = implode('', array(
		RCView::p($ae, $lang['api_docs_005']),
		RCView::p($ae, $lang['api_docs_006']),
		RCView::p($ae, $lang['api_docs_242'])
	));
}

// errors
if($content == 'errors')
{
	$show = implode('', array(
		RCView::div($ach, $lang['api_docs_007']),
		RCView::p($ae, $lang['api_docs_008']),
		RCView::ul($ae, implode('', array(
			RCView::li($ae, RCView::b($lang['api_docs_009']) . $lang['api_docs_010']),
			RCView::li($ae, RCView::b($lang['api_docs_011']) . $lang['api_docs_012']),
			RCView::li($ae, RCView::b($lang['api_docs_013']) . $lang['api_docs_014']),
			RCView::li($ae, RCView::b($lang['api_docs_015']) . $lang['api_docs_016']),
			RCView::li($ae, RCView::b($lang['api_docs_017']) . $lang['api_docs_018']),
			RCView::li($ae, RCView::b($lang['api_docs_019']) . $lang['api_docs_020']),
			RCView::li($ae, RCView::b($lang['api_docs_021']) . $lang['api_docs_022']),
			RCView::li($ae, RCView::b($lang['api_docs_023']) . $lang['api_docs_024'])
		))),
		RCView::div($ach, $lang['api_docs_025']),
		RCView::p($ae, $lang['api_docs_026']),
		RCView::pre($ae, '<code>&lt;?xml version="1.0" encoding="UTF-8" ?&gt;
&lt;hash&gt;
  &lt;error&gt;' . $lang['api_docs_027'] . '&lt;/error&gt;
&lt;/hash&gt;
</code>')
	));
}

// security
if($content == 'security')
{
	$show = implode('', array(
		RCView::p($ae, $lang['api_docs_028']),
		RCView::div($ach, $lang['api_docs_029']),
		RCView::p($ae, $lang['api_docs_033'] . RCView::a(array('href'=>'http://en.wikipedia.org/wiki/Man-in-the-middle_attack', 'target'=>'_blank'), $lang['api_docs_030']) . $lang['api_docs_034']),
		RCView::div($ach, $lang['api_docs_035']),
		RCView::p($ae, $lang['api_docs_036'] . RCView::a(array('href'=>'http://curl.haxx.se/libcurl/', 'target'=>'_blank'), 'cURL') . $lang['api_docs_037'] . RCView::b($lang['api_docs_032']) . $lang['api_docs_038'] . RCView::a(array('href'=>'https://www.google.com/search?q=Java+verify+ssl+certificate', 'target'=>'_blank'), $lang['api_docs_031']) . $lang['api_docs_039']),
		RCView::div($ach, $lang['api_docs_040']),
		RCView::p($ae, $lang['api_docs_041'])
	));
}

// examples
if($content == 'examples')
{
	$show = implode('', array(
		RCView::p($ae, $lang['api_docs_042']),
		RCView::p($ae,
			RCView::button(array('style'=>'', 'onclick'=>"window.location.href='".APP_PATH_WEBROOT."API/get_api_code.php';"),
				RCView::img(array('src'=>'download.png', 'style'=>'vertical-align:middle')) .
				RCView::span(array('style'=>'vertical-align:middle'), $lang['api_docs_045'])
			)
		)
	));
}

// delete records
if($content == 'del_records')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_258']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_259']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_d,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . $lang['api_docs_056']),
			RCView::div($acp, RCView::span($aci, 'action') . $br . $lang['api_docs_261']),
			RCView::div($acp, RCView::span($aci, 'records') . $br . $lang['api_docs_262'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'arm') . $br . $lang['api_docs_264'])
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_263'])
	));
}

// export records
if($content == 'exp_records')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_048']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_052']),
		RCView::p($ae, RCView::b($lang['api_docs_046']) . $lang['api_docs_053']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . $lang['api_docs_056']),
			$div_acp_fmt_odm,
			RCView::div($acp, RCView::span($aci, 'type') . $br . RCView::ul($ae, implode('', array(
				RCView::li($ae, $lang['api_docs_059']),
				RCView::li($ae, $lang['api_docs_060'] . RCView::ul($ae, implode('', array(
					RCView::li($ae, $lang['api_docs_061']),
					RCView::li($ae, $lang['api_docs_062'])
				))))
			))) . $lang['api_docs_058'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'records') . $br . $lang['api_docs_065']),
			RCView::div($acp, RCView::span($aci, 'fields') . $br . $lang['api_docs_066']),
			RCView::div($acp, RCView::span($aci, 'forms') . $br . $lang['api_docs_067']),
			RCView::div($acp, RCView::span($aci, 'events') . $br . $lang['api_docs_068']),
			RCView::div($acp, RCView::span($aci, 'rawOrLabel') . $br . $lang['api_docs_069']),
			RCView::div($acp, RCView::span($aci, 'rawOrLabelHeaders') . $br . $lang['api_docs_070']),
			RCView::div($acp, RCView::span($aci, 'exportCheckboxLabel') . $br . $lang['api_docs_071']),
			RCView::div($acp, RCView::span($aci, 'returnFormat') . $br . $lang['api_docs_072']),
			RCView::div($acp, RCView::span($aci, 'exportSurveyFields') . $br . $lang['api_docs_073']),
			RCView::div($acp, RCView::span($aci, 'exportDataAccessGroups') . $br . $lang['api_docs_074']),
			RCView::div($acp, RCView::span($aci, 'filterLogic') . $br . $lang['api_docs_249']),
			RCView::div($acp, RCView::span($aci, 'dateRangeBegin') . $br . $lang['api_docs_285']),
			RCView::div($acp, RCView::span($aci, 'dateRangeEnd') . $br . $lang['api_docs_286']),
			RCView::div($acp, RCView::span($aci, 'csvDelimiter') . $br . $lang['api_docs_287']),
			RCView::div($acp, RCView::span($aci, 'decimalCharacter') . $br . $lang['api_docs_288'])
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_076']),
		RCView::p($ae, $lang['api_docs_077']),
		RCView::pre($ae, '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;records&gt;
   &lt;item&gt;
      &lt;record&gt;&lt;/record&gt;
      &lt;field_name&gt;&lt;/field_name&gt;
      &lt;value&gt;&lt;/value&gt;
      &lt;redcap_event_name&gt;&lt;/redcap_event_name&gt;
   &lt;/item&gt;
&lt;/records&gt;</code>'),
		RCView::p($ae, $lang['api_docs_078']),
		RCView::pre($ae, "<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;records&gt;
   &lt;item&gt;
      $lang[api_docs_079]
      ...
   &lt;/item&gt;
&lt;/records&gt;</code>")
	));
}

// export reports
if($content == 'exp_reports')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_084']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_085']),
		RCView::p($ae, RCView::b($lang['api_docs_186']) . $lang['api_docs_086']),
		RCView::p($ae, $lang['api_docs_087']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'report'),
			RCView::div($acp, RCView::span($aci, 'report_id') . $br . $lang['api_docs_080']),
			$div_acp_fmt_odm
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
			RCView::div($acp, RCView::span($aci, 'rawOrLabel') . $br . $lang['api_docs_069']),
			RCView::div($acp, RCView::span($aci, 'rawOrLabelHeaders') . $br . $lang['api_docs_082']),
			RCView::div($acp, RCView::span($aci, 'exportCheckboxLabel') . $br . $lang['api_docs_083']),
			RCView::div($acp, RCView::span($aci, 'csvDelimiter') . $br . $lang['api_docs_287']),
			RCView::div($acp, RCView::span($aci, 'decimalCharacter') . $br . $lang['api_docs_288'])
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_088'])
	));
}

// import records
if($content == 'imp_records')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_102']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_103']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_i,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . $lang['api_docs_056']),
			$div_acp_fmt_odm,
			RCView::div($acp, RCView::span($aci, 'type') . $br . RCView::ul($ae, implode('', array(
				RCView::li($ae, $lang['api_docs_059']),
				RCView::li($ae, $lang['api_docs_089'] . RCView::ul($ae, implode('', array(
					RCView::li($ae, $lang['api_docs_061']),
					RCView::li($ae, $lang['api_docs_062'])
				))))
			))) . $lang['api_docs_058'] . $br . $lang['api_docs_092']),
			RCView::div($acp, RCView::span($aci, 'overwriteBehavior') . $br . RCView::ul($ae, implode('', array(
				RCView::li($ae, $lang['api_docs_090']),
				RCView::li($ae, $lang['api_docs_091'])
			)))),
			RCView::div($acp, RCView::span($aci, 'forceAutoNumber') . $br .
				$lang['api_docs_282'] .
				$br . RCView::ul($ae, implode('', array(
				RCView::li($ae, $lang['api_docs_280']),
				RCView::li($ae, $lang['api_docs_281'])
			)))),
			RCView::div(array('class'=>'pa', 'style'=>'margin-top:-15px'), RCView::span($aci, 'data') . $br . $lang['api_docs_093'] . $br . $br . RCView::b($lang['api_docs_094']) . $lang['api_docs_095'] . implode('', array(
				RCView::p(array('style'=>'margin-left:25px'), $lang['api_docs_077']),
				RCView::pre(array('style'=>'padding-left:35px'), '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;records&gt;
   &lt;item&gt;
      &lt;record&gt;&lt;/record&gt;
      &lt;field_name&gt;&lt;/field_name&gt;
      &lt;value&gt;&lt;/value&gt;
      &lt;redcap_event_name&gt;&lt;/redcap_event_name&gt;
   &lt;/item&gt;
&lt;/records&gt;</code>'),
	RCView::p(array('style'=>'margin-left:25px'), $lang['api_docs_078']),
	RCView::pre(array('style'=>'padding-left:35px'), "<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;records&gt;
   &lt;item&gt;
      $lang[api_docs_079]
      ...
   &lt;/item&gt;
&lt;/records&gt;</code>")
			)))
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'dateFormat') . $br . $lang['api_docs_096'] . RCView::b($lang['api_docs_097']) . $lang['api_docs_098'] . RCView::b($lang['api_docs_099']) . $lang['api_docs_100']),
			RCView::div($acp, RCView::span($aci, 'returnContent') . $br . $lang['api_docs_283']),
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_104'] . RCView::b('returnContent'))
	));
}

// export metadata
if($content == 'exp_metadata')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_107']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_108']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'metadata'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'fields') . $br . $lang['api_docs_105']),
			RCView::div($acp, RCView::span($aci, 'forms') . $br . $lang['api_docs_106']),
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_109'])
	));
}

// import metadata
if($content == 'imp_metadata')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_204']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_205'] . $br),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_idesign,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'metadata'),
			$div_acp_fmt,
			$div_acp_data,
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_206'])
	));
}

// export field names
if($content == 'exp_field_names')
{
	$show = implode('', array(
		$div_ach_l47,
		RCView::p($ae, $lang['api_docs_112']),
		$div_ach_l49,
		RCView::p($ae, $lang['api_docs_113']),
		RCView::p($ae, $lang['api_docs_114']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'exportFieldNames'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'field') . $br . $lang['api_docs_110']),
			RCView::div($acp, RCView::span($aci, 'returnFormat') . $br . $lang['api_docs_111'])
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_115'])
	));
}

// export file
if($content == 'exp_file')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_119']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_120']),
		RCview::p($ae, RCView::b($lang['api_docs_121']) . $lang['api_docs_122']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'file'),
			RCView::div($acp, RCView::span($aci, 'action') . $br . 'export'),
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_116']),
			RCView::div($acp, RCView::span($aci, 'field') . $br . $lang['api_docs_117']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_118']),
			RCView::div($acp, RCView::span($aci, 'repeat_instance') . $br . $lang['api_docs_278'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_123'] . $br . $br . RCView::b($lang['api_docs_124']) . $br . $lang['api_docs_125'])
	));
}

// import file
if($content == 'imp_file')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_129']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_130']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_i,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'file'),
			$div_acp_action,
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_116']),
			RCView::div($acp, RCView::span($aci, 'field') . $br . $lang['api_docs_126']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_127']),
			RCView::div($acp, RCView::span($aci, 'repeat_instance') . $br . $lang['api_docs_278']),
			RCView::div($acp, RCView::span($aci, 'file') . $br . $lang['api_docs_128'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt
		)))
	));
}

// delete file
if($content == 'del_file')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_132']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_131']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_i,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'file'),
			RCView::div($acp, RCView::span($aci, 'action') . $br . 'delete'),
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_116']),
			RCView::div($acp, RCView::span($aci, 'field') . $br . $lang['api_docs_117']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_118']),
			RCView::div($acp, RCView::span($aci, 'repeat_instance') . $br . $lang['api_docs_278'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt
		)))
	));
}

// export instruments
if($content == 'exp_instr')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_133']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_134']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'instrument'),
			$div_acp_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_135'])
	));
}

// export repeating forms and events
if($content == 'exp_repeating_forms_events')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['rep_forms_events_02']),
		$div_ach_l49,
		RCview::p($ae, $lang['rep_forms_events_03']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'repeatingFormsEvents'),
			$div_acp_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['rep_forms_events_04'])
	));
}

// import repeating forms and events
if($content == 'imp_repeating_forms_events')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['rep_forms_events_06']),
		$div_ach_l49,
		RCview::p($ae, $lang['rep_forms_events_07']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'repeatingFormsEvents'),
			$div_acp_fmt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_289']),
			
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
		))),
		$b_l75,
		RCView::p($ae, $lang['rep_forms_events_08'])
	));
}

// export instruments PDF
if($content == 'exp_instr_pdf')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_141']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_142']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'pdf')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_136']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_137']),
			RCView::div($acp, RCView::span($aci, 'instrument') . $br . $lang['api_docs_138']),
			RCView::div($acp, RCView::span($aci, 'allRecords') . $br . $lang['api_docs_139']),
			RCView::div($acp, RCView::span($aci, 'compactDisplay') . $br . $lang['api_docs_284']),
			$div_acp_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_143'])
	));
}

// export survey link
if($content == 'exp_surv_link')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_147']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_148']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'surveyLink'),
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_144']),
			RCView::div($acp, RCView::span($aci, 'instrument') . $br . $lang['api_docs_145']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_146']),
			RCView::div($acp, RCView::span($aci, 'repeat_instance') . $br . $lang['api_docs_278'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_acp_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_149'])
	));
}

// export survey queue link
if($content == 'exp_surv_queue_link')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_150']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_151']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'surveyQueueLink'),
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_144'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_acp_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_152'])
	));
}

// export survey return code
if($content == 'exp_surv_ret_code')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_154']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_155']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'surveyReturnCode'),
			RCView::div($acp, RCView::span($aci, $lang['api_docs_056']) . $br . $lang['api_docs_144']),
			RCView::div($acp, RCView::span($aci, 'instrument') . $br . $lang['api_docs_153']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_146']),
			RCView::div($acp, RCView::span($aci, 'repeat_instance') . $br . $lang['api_docs_278'])
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_acp_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_156'])
	));
}

// export survey participants
if($content == 'exp_surv_parts')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_159']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_160']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'participantList'),
			RCView::div($acp, RCView::span($aci, 'instrument') . $br . $lang['api_docs_157']),
			RCView::div($acp, RCView::span($aci, 'event') . $br . $lang['api_docs_158']),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_161'])
	));
}

// export events
if($content == 'exp_events')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_163']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_164'] . $br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'event'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'arms') . $br . $lang['api_docs_162']),
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_166'])
	));
}

// delete events
if($content == 'del_events')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_194']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_195'] . $br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_idesign,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'event'),
			RCView::div($acp, RCView::span($aci, 'action') . $br . 'delete'),
			RCView::div($acp, RCView::span($aci, 'events') . $br . $lang['api_docs_196']),
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_197'])
	));
}

// import events
if($content == 'imp_events')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_198']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_199'] . " " .
				(!$enable_edit_prod_events ? $lang['api_docs_224'] : $lang['api_docs_225']) .
				$br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_idesign,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'event'),
			$div_acp_action,
			RCView::div($acp, RCView::span($aci, 'override') . $br . $lang['api_docs_223'] . " &mdash; " . $lang['api_109']),
			$div_acp_fmt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_238']),
			// JSON example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_232']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>[{"event_name":"Baseline","arm_num":"1","day_offset":"1","offset_min":"0",
"offset_max":"0","unique_event_name":"baseline_arm_1"},
{"event_name":"Visit 1","arm_num":"1","day_offset":"2","offset_min":"0",
"offset_max":"0","unique_event_name":"visit_1_arm_1"},
{"event_name":"Visit 2","arm_num":"1","day_offset":"3","offset_min":"0",
"offset_max":"0","unique_event_name":"visit_2_arm_1"}]</code>'),
			// CSV example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_233']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>event_name,arm_num
"Baseline",1
"Visit 1",1
"Visit 2",1</code>'),
			// XML example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_234']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;events&gt;
   &lt;item&gt;
      &lt;event_name&gt;Baseline&lt;/event_name&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;event_name&gt;Visit 1&lt;/event_name&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;event_name&gt;Visit 2&lt;/event_name&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
   &lt;/item&gt;
&lt;/events&gt;</code>')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_200'])
	));
}

// delete arms
if($content == 'del_arms')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_188']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_187'] . $br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_idesign,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'arm'),
			RCView::div($acp, RCView::span($aci, 'action') . $br . 'delete'),
			RCView::div($acp, RCView::span($aci, 'arms') . $br . $lang['api_docs_190']),
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_189'])
	));
}

// import arms
if($content == 'imp_arms')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_191']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_192'] . " " .
				(!$enable_edit_prod_events ? $lang['api_docs_224'] : $lang['api_docs_225']) .
				$br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_idesign,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'arm'),
			RCView::div($acp, RCView::span($aci, 'override') . $br . $lang['api_docs_223'] . " &mdash; " . $lang['api_108']),
			$div_acp_action,
			$div_acp_fmt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_237']),
			// JSON example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_232']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>[{"arm_num":"1","name":"Drug A"},
{"arm_num":"2","name":"Drug B"},
{"arm_num":"3","name":"Drug C"}]</code>'),
			// CSV example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_233']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>arm_num,name
1,Drug A
2,Drug B
3,Drug C</code>'),
			// XML example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_234']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;arms&gt;
   &lt;item&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
      &lt;name&gt;Drug A&lt;/name&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;arm_num&gt;2&lt;/arm_num&gt;
      &lt;name&gt;Drug B&lt;/name&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;arm_num&gt;3&lt;/arm_num&gt;
      &lt;name&gt;Drug C&lt;/name&gt;
   &lt;/item&gt;
&lt;/arms&gt;</code>')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_193'])
	));
}

// export arms
if($content == 'exp_arms')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_167']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_168'] . $br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'arm'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'arms') . $br . $lang['api_docs_162']),
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_170'])
	));
}

// export instrument event maps
if($content == 'exp_inst_event_maps')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_171']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_172'] . $br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'formEventMapping'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'arms') . $br . $lang['api_docs_162']),
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_173'])
	));
}

// import instrument event mappings
if($content == 'imp_inst_event_maps')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_201']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_202'] . " " .
				(!$enable_edit_prod_events ? $lang['api_docs_224'] : "") .
				$br . $br . RCview::b($lang['api_docs_094']) . $lang['api_docs_165']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_idesign,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'formEventMapping'),
			$div_acp_fmt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_239']),
			// JSON example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_232']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>[{"arm_num":"1","unique_event_name":"baseline_arm_1","form":"demographics"},
{"arm_num":"1","unique_event_name":"visit_1_arm_1","form":"day_3"},
{"arm_num":"1","unique_event_name":"visit_1_arm_1","form":"other"},
{"arm_num":"1","unique_event_name":"visit_2_arm_1","form":"other"}]</code>'),
			// CSV example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_233']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>arm_num,unique_event_name,form
1,baseline_arm_1,demographics
1,visit_1_arm_1,day_3
1,visit_1_arm_1,other
1,visit_2_arm_1,other</code>'),
			// XML example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_234']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;items&gt;
   &lt;item&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
      &lt;unique_event_name&gt;baseline_arm_1&lt;/unique_event_name&gt;
      &lt;form&gt;demographics&lt;/form&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
      &lt;unique_event_name&gt;visit_1_arm_1&lt;/unique_event_name&gt;
      &lt;form&gt;day_3&lt;/form&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
      &lt;unique_event_name&gt;visit_1_arm_1&lt;/unique_event_name&gt;
      &lt;form&gt;other&lt;/form&gt;
   &lt;/item&gt;
   &lt;item&gt;
      &lt;arm_num&gt;1&lt;/arm_num&gt;
      &lt;unique_event_name&gt;visit_2_arm_1&lt;/unique_event_name&gt;
      &lt;form&gt;other&lt;/form&gt;
   &lt;/item&gt;
&lt;/items&gt;</code>')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_203'])
	));
}

// export users
if($content == 'exp_users')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_174']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_175']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'user'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_226'] .
				  $br . RCView::div($achsub, implode(", ", UserRights::getApiUserPrivilegesAttr(true))) .
				  $br . RCView::b($lang['api_docs_227']) . $br . $lang['api_docs_177'] . $br . $lang['api_docs_178'] . $br . $lang['api_docs_229'])
	));
}

// import user rights
if($content == 'imp_users')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_210']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_211']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_iuser,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'user'),
			$div_acp_fmt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_240'] .
				$br . $br . $lang['api_docs_241'] .
				$br . $br . $lang['api_docs_177'] . $br . $lang['api_docs_178'] . $br . $lang['api_docs_229'] .
				RCView::div(array('style'=>'margin:10px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_236']) .
				RCView::div(array('class'=>'pa', 'style'=>'font-size:13px;text-indent:0;margin-left:0;margin-top:0;'),
					'<code>'.implode(", ", UserRights::getApiUserPrivilegesAttr()).'</code>')),
			// JSON example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_232']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>[{"username":"harrispa","expiration":"","data_access_group":"","design":"1","user_rights":"1",
"data_access_groups":"1","data_export":"1","reports":"1","stats_and_charts":"1",
"manage_survey_participants":"1","calendar":"1","data_import_tool":"1","data_comparison_tool":"1",
"logging":"1","file_repository":"1","data_quality_create":"1","data_quality_execute":"1",
"api_export":"1","api_import":"1","mobile_app":"1","mobile_app_download_data":"0","record_create":"1",
"record_rename":"0","record_delete":"0","lock_records_all_forms":"0","lock_records":"0",
"lock_records_customization":"0","forms":{"demographics":"1","day_3":"1","other":"1"}},
{"username":"taylorr4","expiration":"2015-12-07","data_access_group":"","design":"0",
"user_rights":"0","data_access_groups":"0","data_export":"2","reports":"1","stats_and_charts":"1",
"manage_survey_participants":"1","calendar":"1","data_import_tool":"0",
"data_comparison_tool":"0","logging":"0","file_repository":"1","data_quality_create":"0",
"data_quality_execute":"0","api_export":"0","api_import":"0","mobile_app":"0",
"mobile_app_download_data":"0","record_create":"1","record_rename":"0","record_delete":"0",
"lock_records_all_forms":"0","lock_records":"0","lock_records_customization":"0",
"forms":{"demographics":"1","day_3":"2","other":"0"}}]</code>'),
			// CSV example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_233']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>username,design,user_rights,forms
harrispa,1,1,"demographics:1,day_3:1,other:1"
taylorr4,0,0,"demographics:1,day_3:2,other:0"</code>'),
			// XML example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_234']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;users&gt;
   &lt;item&gt;
      &lt;username&gt;harrispa&lt;/username&gt;
      &lt;expiration&gt;2015-12-07&lt;/expiration&gt;
      &lt;user_rights&gt;1&lt;/user_rights&gt;
      &lt;design&gt;0&lt;/design&gt;
      &lt;forms&gt;
         &lt;demographics&gt;1&lt;/demographics&gt;
         &lt;day_3&gt;2&lt;/day_3&gt;
         &lt;other&gt;0&lt;/other&gt;
      &lt;/forms&gt;
   &lt;/item&gt;
&lt;/users&gt;</code>')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt,
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_212'])
	));
}

if($content == 'imp_proj_sett')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_265']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_266']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_i,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'project_settings'),
			$div_acp_fmt_no_dflt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_267']),
		        RCView::p(	$ae, $lang['api_docs_270'] .
					RCView::div($achsub, implode(", ", Project::getAttributesApiImportProjectInfo())))
		))),
        $b_l75,
		RCview::p($ae, $lang['api_docs_269']),
    ));
}


// export project xml
if($content == 'exp_proj_xml')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_253']),
		$div_ach_l49,
		RCview::p($ae, $lang['data_export_tool_202'] . " " . $lang['data_export_tool_203'] . " " . $lang['api_docs_255']),
		RCView::p($ae, RCView::b($lang['api_docs_046']) . $lang['api_docs_257']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'project_xml')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'returnMetadataOnly') . $br . $lang['api_docs_256']),
			RCView::div($acp, RCView::span($aci, 'records') . $br . $lang['api_docs_065']),
			RCView::div($acp, RCView::span($aci, 'fields') . $br . $lang['api_docs_066']),
			RCView::div($acp, RCView::span($aci, 'events') . $br . $lang['api_docs_068']),
			RCView::div($acp, RCView::span($aci, 'returnFormat') . $br . $lang['api_docs_072']),
			RCView::div($acp, RCView::span($aci, 'exportSurveyFields') . $br . $lang['api_docs_073']),
			RCView::div($acp, RCView::span($aci, 'exportDataAccessGroups') . $br . $lang['api_docs_074']),
			RCView::div($acp, RCView::span($aci, 'filterLogic') . $br . $lang['api_docs_249']),
			RCView::div($acp, RCView::span($aci, 'exportFiles') . $br . $lang['api_docs_279'])
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_254'])
	));
}

// generate next record name
if($content == 'exp_next_id')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_272']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_273']),
		RCview::p($ae, $lang['api_docs_274']),
		RCview::p($ae, $lang['api_docs_275']),
		RCview::p($ae, $lang['api_docs_276']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'generateNextRecordName')
		))),
		$b_l75,
		RCView::p(	$ae, $lang['api_docs_277'] )
	));
}

// export project
if($content == 'exp_proj')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_180']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_181']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'project'),
			$div_acp_fmt
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt
		))),
		$b_l75,
		RCView::p(	$ae, $lang['api_docs_182'] .
					RCView::div($achsub, implode(", ", Project::getAttributesApiExportProjectInfo())))
	));
}

// import project info
if($content == 'imp_proj')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_207']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_208']),
		RCview::p($ae, $lang['api_docs_230']),
		RCview::p($ae, $lang['api_docs_231']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_iproj,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			RCView::div($acp, RCView::span($aci, 'token') . $br . $lang['api_docs_222']),
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'project'),
			$div_acp_fmt,
			RCView::div($acp, RCView::span($aci, 'data') . $br . $lang['api_docs_235'] .
				RCView::div(array('style'=>'margin:10px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_236']) .
				RCView::div(array('class'=>'pa', 'style'=>'font-size:13px;text-indent:0;margin-left:0;margin-top:0;'),
					'<code>'.implode(", ", Project::getApiCreateProjectAttr()).'</code>')),
			// JSON example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_232']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>[{"project_title":"My New REDCap Project","purpose":"0"}]</code>'),
			// CSV example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_233']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>project_title,purpose,is_longitudinal
"My New REDCap Project",0,1</code>'),
			// XML example
			RCView::div(array('style'=>'font-size:13px;margin:4px 6px 0px 25px;padding:5px 5px 0;'), $lang['api_docs_234']),
			RCView::pre(array('style'=>'margin:1px 5px 10px 25px;padding:5px 10px;'), '<code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; ?&gt;
&lt;item&gt;
   &lt;project_title&gt;My New REDCap Project&lt;/project_title&gt;
   &lt;purpose&gt;0&lt;/purpose&gt;
   &lt;surveys_enabled&gt;1&lt;/surveys_enabled&gt;
   &lt;record_autonumbering_enabled&gt;0&lt;/record_autonumbering_enabled&gt;
&lt;/item&gt;</code>')
		))),
		RCView::div($achsub, $opt . $br . implode('', array(
			$div_ret_fmt .
			RCView::div($acp, RCView::span($aci, 'odm') . $br . $lang['api_docs_251'])
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_221'])
	));
}

// export redcap version
if($content == 'exp_rc_v')
{
	$show = implode('', array(
		$div_ach_l47,
		RCview::p($ae, $lang['api_docs_183']),
		$div_ach_l49,
		RCview::p($ae, $lang['api_docs_184']),
		$div_ach_l50,
		$pre_ae_capi,
		$div_ach_l51,
		$pre_ae_cp,
		$div_perm_e,
		$div_ach_l54,
		RCView::div($achsub, $req . $br . implode('', array(
			$div_acp_token,
			RCView::div(array('class'=>'pa','style'=>'text-indent:0;'), $lang['api_docs_289']),
			RCView::div($acp, RCView::span($aci, 'content') . $br . 'version'),
			$div_acp_fmt
		))),
		$b_l75,
		RCView::p($ae, $lang['api_docs_185'])
	));
}

$intro = 'Introduction';
if($content == 'default') $intro = RCView::b($intro);

// Page header
$objHtmlPage = new HtmlPage();
$objHtmlPage->setPageTitle(remBr(br2nl($app_title))." | REDCap");
$objHtmlPage->PrintHeader(false);

?>
<style type="text/css">
body { background: #fff; color:#000; }
body, td, th { font-family:'Open Sans', Helvetica, Arial, sans-serif; font-size:15px; }
a:link, a:visited, a:active { color:#008; text-decoration:none; }
a:hover { text-decoration:underline; }
.center { text-align:center; margin:0 0 100px; }
.center table { margin-left:auto; margin-right:auto; text-align:left; }
.center th { text-align:center !important; }
td, th { border: 1px solid #000; }
h4 {font-weight:bold;margin-bottom:10px; }
h5 { font-weight:bold;margin-bottom:10px; }
#faq h2 { color: #800000; }
.p { text-align: left; }
.e { background: #aaa; color: #000; }
.h { background: #eee; color: #000; }
.v { background: #ddd; color: #000; }
.w { background: #fff; color: #000; margin:10px 5px; padding:8px 10px; border:1px solid #ccc; font-size: 15px; font-family: monospace; }
.pa { margin:4px 6px 4px 25px; text-indent:-25px; padding:5px; }
.vr { background:#ccc; text-align:right; color:#000; }
img { border:0; }
hr { width:800px; background:#ccc; border:0; height:1px; color:#000; }
.mm { border-bottom-color:#aaa; border-bottom-style:dotted; border-width:0 0 1px; margin:1px; padding:3px; }
div.sub { margin:5px 0 15px; border:1px solid #bbb; padding:5px; }
pre { margin:10px 5px; font-family:monospace; padding:10px; color:#555; background:#fff; border:1px solid #ccc; font-size:12px;}
div.headerrc { font-weight:bold; font-size:18px; line-height:22px;margin:2px 0; }
span.item { font-size:15px; font-family:monospace; color:#444; font-weight:bold; }
div.pa li { text-indent:0; }
</style>
<?php
renderJsVars();

$div1 = RCView::div(array('class'=>'d-none d-sm-block', 'style'=>'margin:10px 0 15px;'),
			RCView::a(array('href'=>APP_PATH_WEBROOT_PARENT . 'index.php?action=myprojects'),
				RCView::img(array('border'=>0, 'alt'=>'REDCap', 'src'=>'redcap-logo.png'))
			)
		) .
		RCView::div(array('class'=>'d-block d-sm-none', 'style'=>'margin-top:60px;'), '');

$cc_div = SUPER_USER
	? RCView::div(array('class'=>'mm'),
		RCView::a(array(
			'href'  => APP_PATH_WEBROOT . 'ControlCenter/'
		), $lang['global_07']))
	: '';

$div2 = RCView::div(array('class'=>'mm'),
	RCView::a(array(
		'href'  => APP_PATH_WEBROOT_PARENT . 'index.php?action=myprojects'
	), $lang['bottom_03']));

$h2_1 = RCView::h4(array('class'=>'p', 'style'=>'margin-bottom:8px;'), $lang['api_docs_219']);

$div_links_1 = $ae;

$array = array(
	'default'  => $lang['api_docs_213'],
	'tokens'   => $lang['api_docs_214'],
	'errors'   => $lang['api_docs_215'],
	'security' => $lang['api_docs_216'],
	'examples' => $lang['api_docs_217'],
);

foreach($array as $k => $v)
{
	if($content == $k) $v = RCView::b($v);

	$div_links_1[] = RCview::div(array('class'=>'mm'),
		RCView::a(array('href'=>"?content=$k"), $v));
}

$div_links_1 = implode('', $div_links_1);

$h2_2 = RCView::h4(array(
	'class' => 'p',
	'style' => 'margin-bottom:8px;'
), $lang['api_docs_220']);

$div_links_2 = $ae;

foreach(APIPlayground::getAPIsArray() as $group => $apis)
{
	$div_links_2[] = RCView::div(array(
		'class' => 'mm',
		'style' => 'font-size:13px'
	), RCView::b($group));

	foreach($apis as $k => $v)
	{
		if($content == $k) $v = RCView::b($v);

		$div_links_2[] = RCView::div(array(
			'class' => 'mm',
			'style' => 'padding-left:10px'
		), RCView::a(array('href'=>"?content=$k"), $v));
	}
}

$div_links_2 = implode('', $div_links_2);

$td_1 = RCView::div(array(
	'id'	  => 'left-menu',
	'class'	  => 'd-none d-md-block col-md-4 col-lg-3',
	'style'   => 'background-color:#eee;vertical-align:top; width:260px; padding:10px 10px 100px;border-right:1px solid #aaa;'
), $div1 . $div2 . $cc_div . $h2_1 . $div_links_1 . $h2_2 . $div_links_2);

$td_2 = RCView::div(array('class'=>'col-12 col-md-8 col-lg-9', 'style'=>'max-width:900px;'), 
			RCView::div(array('class'=>'h', 'style'=>'font-weight:bold;padding:10px 10px;border:1px solid #000;margin:10px 0;'), $lang['api_docs_218']) . 
			$show
		);

$year = strftime('%Y');

$tr_3 = RCView::div(array(
	'class'   => 'col-12',
	'style'   => 'padding:20px 0; border:0; color:#aaa; text-align:center; font-size:12px;'
), RCView::a(array(
	'href'   => 'https://projectredcap.org',
	'style'  => 'color:#aaa; text-decoration:none; font-weight:normal; font-size:12px;',
	'target' => '_blank'
), "REDCap Software - Version $redcap_version - &copy; $year Vanderbilt University"));

echo RCView::div(array('class'=>'row container-fluid'), $td_1 . $td_2 . $tr_3);

?>
<!-- top navbar -->
<nav class="rcproject-navbar navbar navbar-expand-md navbar-light fixed-top" style="background-color:#f8f8f8;border-bottom:1px solid #e7e7e7;padding:10px;" role="navigation">
	<span class="navbar-brand" style="max-width:78%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-right:0;"><?php print $lang['api_docs_218'] ?></span>
	<button type="button" class="navbar-toggler float-right" onclick="$('#left-menu').toggleClass('d-sm-block').toggleClass('d-none');" aria-expanded="false">
		<span class="navbar-toggler-icon"></span>
	</button>
</nav>

</body>
</html>
