<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate checked_fields, if is not empty
$checked_fields = explode(",", $_POST['checked_fields']);
foreach ($checked_fields as $key=>$this_field) {
	if (!isset($Proj->metadata[$this_field])) {
		unset($checked_fields[$key]);
	}
}
// Set fields as keys in array
$checked_fields = array_flip(array_unique($checked_fields));

// Loop through all fields and build HTML table (exclude Descriptive fields)
$t = "";
foreach ($Proj->metadata as $this_field=>$attr) {
	// Form name if first field in form
	if ($attr['form_menu_description'] != '') {
		$t .= 	RCView::tr(array(),
					RCView::td(array('class'=>'header', 'style'=>'width:30px;'),
						''
					) .
					RCView::td(array('class'=>'header', 'valign'=>'bottom', 'style'=>'color:#800000;font-size:14px;'),
						RCView::br() .
						// Form name
						RCView::escape(strip_tags(label_decode($attr['form_menu_description']))) .
						// Select all
						RCView::span(array('style'=>'color:#777;margin-left:50px;font-weight:normal;font-size:12px;'),
							"(" .
							RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;margin:0 3px;text-decoration:underline;font-weight:normal;', 'onclick'=>"reportQuickAddForm('{$attr['form_name']}',true);"), $lang['data_export_tool_52']) .
							"/" .
							// Deselect all
							RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;margin:0 3px;text-decoration:underline;font-weight:normal;', 'onclick'=>"reportQuickAddForm('{$attr['form_name']}',false);"), $lang['data_export_tool_53']) .
							")"
						)
					)
				);
	}
	// Skip descriptive fields
	if ($attr['element_type'] == 'descriptive') continue;
	// Add the "checked" attribute if field already exists in report
	$checked = (isset($checked_fields[$this_field])) ? "checked" : "";
	// Add field row
	$t .= 	RCView::tr(array(),
				RCView::td(array('class'=>'data nowrap', 'style'=>'width:30px;text-align:center;padding-top:4px;'),
					RCView::checkbox(array('class'=>"frm-".$attr['form_name'], 'name'=>$this_field, 'onclick'=>"qa($(this))", $checked=>$checked))
				) .
				RCView::td(array('class'=>'data', 'style'=>'padding:4px 0 2px 5px;'),
					$this_field .
					RCView::span(array(), '"' . RCView::escape(strip_tags(label_decode($attr['element_label']))) . '"')
				)
			);
}

// Response
$dialog_title = 	RCView::span(array('style'=>'color:green;vertical-align:middle'), '<i class="fas fa-plus"></i> '.$lang['report_builder_136']);
$dialog_content = 	RCView::div(array('style'=>'font-size:13px;margin-bottom:15px;'),
						$lang['report_builder_137']
					) .
					RCView::div(array('style'=>''),
						// Table
						RCView::table(array('cellspacing'=>'0', 'class'=>'form_border', 'style'=>'table-layout:fixed;width:100%;'),
							$t
						)
					);
// Output JSON response
print json_encode_rc(array('title'=>$dialog_title, 'content'=>$dialog_content));