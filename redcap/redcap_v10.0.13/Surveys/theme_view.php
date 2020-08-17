<?php


// define("NOAUTH", true);
// if (isset($_GET['pid']) && is_numeric($_GET['pid'])) {
require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
// } else {
	// require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
// }

// Init values
if (!isset($_GET['font_family'])) $_GET['font_family'] = '';
if (!isset($_GET['text_size'])) $_GET['text_size'] = '';
if (!isset($_GET['theme'])) $_GET['theme'] = '';
if (!isset($_GET['enhanced_choices'])) $_GET['enhanced_choices'] = '0';

// Check for custom theme elements
if ($_GET['theme_bg_page'] == '') {
	$custom_theme_elements = array();
} else {
	$regex_color = '/^[a-f0-9]{6}$/i';
	$_GET['theme_bg_page'] = (isset($_GET['theme_bg_page']) && preg_match($regex_color, $_GET['theme_bg_page'])) ? $_GET['theme_bg_page'] : '';
	$_GET['theme_text_buttons'] = (isset($_GET['theme_text_buttons']) && preg_match($regex_color, $_GET['theme_text_buttons'])) ? $_GET['theme_text_buttons'] : '';
	$_GET['theme_text_title'] = (isset($_GET['theme_text_title']) && preg_match($regex_color, $_GET['theme_text_title'])) ? $_GET['theme_text_title'] : '';
	$_GET['theme_bg_title'] = (isset($_GET['theme_bg_title']) && preg_match($regex_color, $_GET['theme_bg_title'])) ? $_GET['theme_bg_title'] : '';
	$_GET['theme_text_question'] = (isset($_GET['theme_text_question']) && preg_match($regex_color, $_GET['theme_text_question'])) ? $_GET['theme_text_question'] : '';
	$_GET['theme_bg_question'] = (isset($_GET['theme_bg_question']) && preg_match($regex_color, $_GET['theme_bg_question'])) ? $_GET['theme_bg_question'] : '';
	$_GET['theme_text_sectionheader'] = (isset($_GET['theme_text_sectionheader']) && preg_match($regex_color, $_GET['theme_text_sectionheader'])) ? $_GET['theme_text_sectionheader'] : '';
	$_GET['theme_bg_sectionheader'] = (isset($_GET['theme_bg_sectionheader']) && preg_match($regex_color, $_GET['theme_bg_sectionheader'])) ? $_GET['theme_bg_sectionheader'] : '';
	$custom_theme_elements = array(	'theme_bg_page'=>$_GET['theme_bg_page'], 'theme_text_buttons'=>$_GET['theme_text_buttons'], 'theme_text_title'=>$_GET['theme_text_title'],
									'theme_bg_title'=>$_GET['theme_bg_title'], 'theme_text_question'=>$_GET['theme_text_question'], 'theme_bg_question'=>$_GET['theme_bg_question'],
									'theme_text_sectionheader'=>$_GET['theme_text_sectionheader'], 'theme_bg_sectionheader'=>$_GET['theme_bg_sectionheader']);
}

// Class for html page display system
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("survey.css", 'screen,print');
// Set the font family
$objHtmlPage = Survey::applyFont($_GET['font_family'], $objHtmlPage);
// Set the size of survey text
$objHtmlPage = Survey::setTextSize($_GET['text_size'], $objHtmlPage);
// If survey theme is being used, then apply it here
$objHtmlPage = Survey::applyTheme($_GET['theme'], $objHtmlPage, $custom_theme_elements);
// Page header
$objHtmlPage->PrintHeader();
// Style
?>
<style type='text/css'>
#questiontable_loading { display: none; }
#questiontable { display: table !important;  }
.surveysubmit td { text-align: center;padding:15px !important; }
</style>
<?php
// Set the width if displayed in the iframe
if (isset($_GET['iframe'])) { ?>
	<style type='text/css'>
	body { width: 950px; }
	<?php if ($_GET['theme'] == '' && empty($custom_theme_elements)) { ?>
	body { background-color: #333 !important; }
	<?php } ?>
	</style>
<?php }
// Survey title and instructions
print RCView::div(array('id'=>'surveytitle'), $lang['survey_1021']);
print RCView::div(array('id'=>'surveyinstructions'), $lang['survey_1022']);
// Set up fields to be displayed
$elements = array();
$elements[] = array('rr_type'=>'header', 'shfield'=>'field1', 'css_element_class'=>'header','value'=>$lang['survey_1023']);
$elements[] = array('rr_type'=>'text', 'name'=>'field2', 'label'=>"What is your first name?", 'custom_alignment'=>'RV');
$elements[] = array('rr_type'=>'checkbox', 'name'=>'field4', 'label'=>"What days of the week do you work? (check all that apply)", 'enum'=>"1, Monday \\n 2, Tuesday \\n 3, Wednesday \\n 4, Thursday \\n 5, Friday", 'custom_alignment'=>'LH');
$elements[] = array('rr_type'=>'select', 'name'=>'field5', 'label'=>"What is your ethnicity?", 'enum'=>"1, Hispanic or Latino \\n 2, NOT Hispanic or Latino \\n 3, Unknown / Not Reported", 'custom_alignment'=>'RH');
$elements[] = array('rr_type'=>'radio', 'name'=>'field3', 'label'=>"What is your favorite ice cream?", 'enum'=>"1, Chocolate \\n 2, Vanilla \\n 3, Strawberry", 'custom_alignment'=>'LV');
$elements[] = array('rr_type'=>'descriptive', 'name'=>'field6', 'label'=>$lang['survey_1024']);
$elements[] = array('rr_type'=>'surveysubmit', 'label'=>RCView::table(array('cellspacing'=>'0'), RCView::tr(array(), RCView::button(array('class'=>'jqbutton','style'=>'color:#800000;width:140px;', 'onclick'=>'return false;'), $lang['survey_200']))));
DataEntry::renderForm($elements);
// Page footer
$objHtmlPage->PrintFooter();