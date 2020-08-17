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

// Obtain array of DAGs
$dags = $Proj->getGroups();

// SET CHART DIVS
$chart_divs = "";
// Loop through each chart
foreach ($dq->drw_metrics_charts as $chart_name=>$attr)
{
	if ($chart_name == '') continue;
	// If user is in DAG or DAGs don't exist, then skip DAG-related charts
	if ($attr['dag_related'] && ($user_rights['group_id'] != '' || empty($dags))) {
		continue;
	}
	// Add chart
	$chart_divs .= 	RCView::div(array('class'=>'chart_title'),
						// Chart title
						RCView::div(array('style'=>'margin-right:50px;'),
							$attr['label']
						) .
						// Drop-down to select chart type
						RCView::div(array('class'=>'hidden', 'id'=>"drw-charttypedd-$chart_name", 'style'=>'text-align:right;'),
							RCView::select(array('style'=>'font-size:11px;margin-left:30px;',
								'onchange'=>"renderDrwBarChart('$chart_name','$chart_name',$(this).val());"),
								array('BarChart'=>$lang['graphical_view_49'], 'PieChart'=>$lang['graphical_view_50']), 'BarChart')
						)
					) .
					// Pre-fill with progress circle until it loads
					RCView::div(array('id'=>$chart_name),
						RCView::div(array('class'=>'progress_container'), '')
					);
}

// Get a count of unresolved issues
$queryStatuses = $dq->countDataResIssues();

// Header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Call the Charts javascript file
print "<script type='text/javascript' src='" . APP_PATH_JS . "StatsAndCharts.js'></script>";

?>
<style type="text/css">
#general_stats_container { font-size:13px; border-top:1px solid #ddd;padding-top:20px;margin-top:20px;max-width:650px; }
.chart_title { font-weight:bold; font-size:14px; text-align: center; border-top:1px solid #ddd;padding-top:20px;margin-top:10px; }
#chart_container { max-width:650px; margin:30px 0 50px; }
.progress_container { width:600px;height:200px;text-align:center; background: transparent url('<?php echo APP_PATH_IMAGES ?>progress.gif') no-repeat center center; }
</style>
<script type="text/javascript">
// Display bar chart
function renderDrwBarChart(div_id,chart_name,plottype) {
	// Obtain JSON chart info via ajax
	$.post(app_path_webroot+'DataQuality/metrics_ajax.php?pid='+pid,{ chart_name: chart_name },function(chartdata){
		// Parse JSON
		var json_data = jQuery.parseJSON(chartdata);
		var raw_data = json_data.data;
		var minValue = json_data.min;
		var maxValue = json_data.max;
		// If no data is returned, hide chart
		if (raw_data.length == 0) {
			// Display text about why chart is not displayed
			$('#'+div_id).html('<p style="padding-top:40px;height:100px;text-align:center;color:#777;"><?php echo js_escape($lang['dataqueries_250']) ?></p>');
			return;
		}
		// Instantiate data object
		var data = new google.visualization.DataTable();
		// Add data columns
		data.addColumn('string', '');
		data.addColumn('number', 'Count');
		// Add data rows
		data.addRows(raw_data);
		// Also display the charttype drop-down
		$('#drw-charttypedd-'+chart_name).show();
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

// Load charts when page first loads
$(function(){
	// Pace each chart with a delay
	var delay = 150;
	var num_loop = 0;
	// Loop through all chart divs inside the chart_container div and load their chart via ajax
	$('div#chart_container div').each(function(){
		var id = $(this).attr('id');
		if (id != null && id.indexOf('drw-charttypedd-') == -1)	{
			setTimeout(function(){
				renderDrwBarChart(id,id,'BarChart');
			},delay*num_loop);
			num_loop++;
		}
	});
});
</script>
<?php

// Page title
renderPageTitle("<i class=\"fas fa-clipboard-check\"></i> {$lang['app_20']}");

// Display tabs
print $dq->renderTabs();

// Instructions
print RCView::p(array('style'=>'margin-top:0;'),
		$lang['dataqueries_170'] .
		// Note about displaying DAG-related charts (if DAGs exist and user does NOT belong to a DAG)
		(($user_rights['group_id'] != '' || empty($dags)) ? "" : " ".$lang['dataqueries_256']) .
		// Note about NOT displaying DAG-related charts (if DAGs exist and user DOES belong to a DAG)
		(($user_rights['group_id'] != '' && !empty($dags)) ? " ".$lang['dataqueries_257'] : "")
	  );

// General stats
print 	RCView::div(array('id'=>'general_stats_container'),
			RCView::div(array('style'=>'font-weight:bold;font-size:14px;margin-bottom:5px;'), $lang['dataqueries_242']) .
			// Number of open queries
			RCView::div(array('style'=>''),
				$lang['dataqueries_244'] . " " . RCView::b($queryStatuses['OPEN']) .
				RCView::span(array('style'=>'color:#888;'),
					"&nbsp; ({$lang['dataqueries_246']} " . RCView::b($queryStatuses['OPEN_UNRESPONDED']) . ",
					{$lang['dataqueries_245']} " . RCView::b($queryStatuses['OPEN_RESPONDED']) . ")"
				)
			) .
			// Number of closed queries
			RCView::div(array('style'=>''),
				$lang['dataqueries_247'] . " " . RCView::b($queryStatuses['CLOSED'])
			) .
			// Average time queries are/were open (includes both open and closed queries)
			RCView::div(array('style'=>''),
				$lang['dataqueries_279'] . " " . RCView::b($dq->calculateAvgTimeQueryOpen() . " " . $lang['scheduling_25']) .
				"&nbsp; " . RCView::span(array('style'=>'color:#888;'), $lang['dataqueries_280'])
			) .
			// Average time for query response
			RCView::div(array('style'=>''),
				$lang['dataqueries_255'] . " " . RCView::b($dq->calculateAvgTimeForQueryResponse() . " " . $lang['scheduling_25']) .
				"&nbsp; " . RCView::span(array('style'=>'color:#888;'), $lang['dataqueries_282'])
			) .
			// Average time to query resolution
			RCView::div(array('style'=>''),
				$lang['dataqueries_243'] . " " . RCView::b($dq->calculateAvgTimeToQueryResolution() . " " . $lang['scheduling_25']) .
				"&nbsp; " . RCView::span(array('style'=>'color:#888;'), $lang['dataqueries_281'])
			)
		);

// Chart divs
print RCView::div(array('id'=>'chart_container'), $chart_divs);

// Footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';