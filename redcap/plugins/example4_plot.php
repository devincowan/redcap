<?php
/**
 * PLUGIN NAME: Name Of The Plugin
 * DESCRIPTION: A brief description of the Plugin.
 * VERSION: The Plugin's Version Number, e.g.: 1.0
 * AUTHOR: Name Of The Plugin Author
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// Display the header
$HtmlPage = new HtmlPage();
$HtmlPage->PrintHeaderExt();

?>
<!-- Display page instructions -->
<h3 style="color:#800000;">
	Plugin Example Page (System-Level)
</h3>
<p>
	You may even utilize the Google Chart Tools JavaScript library used in REDCap for displaying bar charts and scatter plots, 
	such as the one below. This plot has randomly generated data, but one could easily query REDCap's database to extract data values
	for a project and display them in a plot similar to this one. To learn how to construct plots like this one using JavaScript and
	the Google Chart Tools library,	feel free to peruse the documentation at 
	<a target="_blank" href="http://code.google.com/apis/chart/">http://code.google.com/apis/chart/</a>.
</p>
<!-- Div that will contain the scatter plot -->
<div id="mychart"></div>
<!-- Call the charts javascript library -->
<script type="text/javascript" src="<?php echo APP_PATH_WEBROOT ?>Resources/js/charts.js"></script>
<!-- Create your custom javascript code to construct the scatter plot on this page -->
<script type="text/javascript">
// Create and populate the scatter chart
var data = new google.visualization.DataTable();
data.addColumn('number', '');
data.addColumn('number', 'X/Y values');
for (var i = 0; i < 100; ++i) {
  data.addRow([Math.random()*100, Math.random()]);
}
var chart = new google.visualization.ScatterChart(document.getElementById('mychart'));
chart.draw(data, {legend: 'none', title: 'Scatter plot example', width: 600, height: 400});
</script>
<?php

// Display the footer
$HtmlPage->PrintFooterExt();