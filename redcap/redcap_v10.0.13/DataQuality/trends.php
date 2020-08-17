<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Do user rights check (normally this is done by init_project.php, but we actually have multiple rights
// levels here for a single page (so it's not applicable).
if ($data_resolution_enabled != '2' || $user_rights['data_quality_resolution'] == '0')
{
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}

// Instantiate DataQuality object
$dq = new DataQuality();

// Header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Call the Charts javascript file
print "<script type='text/javascript' src='" . APP_PATH_JS . "StatsAndCharts.js'></script>";

// Page title
renderPageTitle("<i class=\"fas fa-clipboard-check\"></i> {$lang['app_20']}");

// Display tabs
print $dq->renderTabs();

// Instructions
print RCView::p(array('style'=>'margin-top:0;'),
		$lang['dataqueries_170']
	  );

// Chart divs
print 	RCView::div(array('id'=>'chart_container'),
			RCView::div(array('class'=>'chart_title'), 'Top 5 queried fields') .
			RCView::div(array('id'=>'top_fields'), '') .
			RCView::div(array('class'=>'chart_title'), 'Top 5 queried Data Quality rules') .
			RCView::div(array('id'=>'top_rules'), '') .
			RCView::div(array('class'=>'chart_title'), 'Top 5 queried records') .
			RCView::div(array('id'=>'top_records'), '')
		);

?>
<style type="text/css">
.chart_title { font-weight:bold; font-size:14px; text-align: center; border-top:1px solid #ddd;padding-top:20px;margin-top:10px; }
#chart_container { max-width:600px; margin:30px 0 50px; }
</style>
<script type="text/javascript">
// Display bar chart
function renderDrwBarChart(div_id,chart_name,plottype) {
	// Obtain JSON chart info via ajax
	$.post(app_path_webroot+'DataQuality/.php?pid='+pid+'',{  },function(chartdata){
		// var chartdata = '{"min":0,"max":5,"data":[["Incomplete",5],["Unverified",2],["Complete",1]]}';

		// Parse JSON
		var json_data = jQuery.parseJSON(chartdata);
		var raw_data = json_data.data;
		var minValue = json_data.min;
		var maxValue = json_data.max;
		// Instantiate data object
		var data = new google.visualization.DataTable();
		// Add data columns
		data.addColumn('string', '');
		data.addColumn('number', 'Count');
		// Add data rows
		data.addRows(raw_data);
		// Display bar chart or pie chart
		if (plottype == 'PieChart') {
			var chart = new google.visualization.PieChart(document.getElementById(div_id));
			var chartHeight = 300;
			chart.draw(data, {chartArea: {top: 10, height: (chartHeight-50)}, width: 600, height: chartHeight, legend: 'none', hAxis: {minValue: minValue, maxValue: maxValue} });
		} else if (plottype == 'BarChart') {
			var chart = new google.visualization.BarChart(document.getElementById(div_id));
			var chartHeight = 80+(raw_data.length*60);
			chart.draw(data, {colors:['#3366CC','#FF9900'], isStacked: true, chartArea: {top: 10, height: (chartHeight-50)}, width: 600, height: chartHeight, legend: 'none', hAxis: {minValue: minValue, maxValue: maxValue} });
		}
	});
}

$(function(){
	// Loop through all chart divs inside the chart_container div and load their chart via ajax
	$('div#chart_container div').each(function(){
		var id = $(this).attr('id');
		if (id != null)	renderDrwBarChart(id,id,'BarChart');
	});
});
</script>
<?php

// Footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';