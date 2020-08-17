<canvas id="{{$name}}_total_chart" width="400" height="400"></canvas>
<canvas id="{{$name}}_date_range_chart" width="400" height="400"></canvas>

<script>
(function(window, document) {

    /*
     * get a color from a list of predefined colors
     */
    var getColor = function(index=0) {
        if(isNaN(index)) throw new Error('A numeric index must be provided')
        var colors = [
            'rgba(223, 58, 87, 1)',
            'rgba(172, 243, 157, 1)',
            'rgba(255, 253, 152, 1)',
            'rgba(104, 195, 212, 1)',
            'rgba(247, 146, 86, 1)',
            'rgba(86, 142, 163, 1)',
        ]
        return colors[index]
    }
    /*
     * get a random color
     */
    var getRandomColor = function(value) {
        //value from 0 to 1
        value += 1
        var max = 200
        var red = Math.round( (value  * Math.random() * 200 ) )% max
        var green = Math.round( (value * Math.random() * 200 ) )% max
        var blue = Math.round( (value * Math.random() * 200 ) )% max
        var colors = [red, green, blue, 0.2].join(', ')
        return 'rgba('+colors+')'
        // hlsa version
        // var hue=((value+200)*20).toString(10)
        // return ["hsla(",hue,",100%,50%, .2)"].join("")
    }

    /* var rand = function(min, max, seed=123) {
        seed = seed*Math.random()
        min = min === undefined ? 0 : min;
        max = max === undefined ? 1 : max;
        seed = (seed * 9301 + 49297) % 233280;
        return min + (seed / 233280) * (max - min);
    } */

    /**
     * create the config options for entries count
     */
    var getTotalChartConfig = function(total) {
        var labels = Object.keys(total)
        var values = []
        var colors = []
        var border_colors = []
        for(let i=0; i<labels.length; i++) {
            var total_key = labels[i]
            values.push(total[total_key])
            var color = getColor(i)
            colors.push(color)
            border_colors.push(color.replace(/(0?\.\d\))$/, '1)'))
        }
        var config = {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{$lang['fhir_stats_chart_01']}}',
                    data: values,
                    backgroundColor: colors,
                    borderColor: border_colors,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        }
        return config
    }

    /**
     * group dayly count by resource
     */
    function groupDataByResource(counts_per_day) {
        var days = Object.keys(counts_per_day)
        var datasets = {}
        for(var i=0; i<days.length;i++) {
            var day = days[i]
            var counts =  counts_per_day[day]
            for(var resource_type in counts) {
                var resource_count = counts[resource_type]
                if(!datasets[resource_type]) datasets[resource_type] = []
                datasets[resource_type].push(resource_count)
            }
        }
        return datasets
    }

    /**
     * create the config options for the daily graph
     */
    var getDateRangeChartConfig = function(counts_per_day) {
        var data_by_resource = groupDataByResource(counts_per_day)
        var labels = Object.keys(counts_per_day)
        var datasets = []
        var index = 0 // use this index to pick a color
        for(var resource_type in data_by_resource) {
            var color = getColor(index++)
            var data = data_by_resource[resource_type]
            var dataset = {
                label: resource_type,
                backgroundColor: color,
                borderColor: color,
                fill: false,
                data: data, //set the data
            }
            datasets.push(dataset)
        }

		var config = {
			type: 'line',
			data: {
				labels: labels,
				datasets: datasets
			},
			options: {
				responsive: true,
				title: {
					display: true,
					text: '{{$lang['fhir_stats_chart_02']}}',// Entries per day
				},
				tooltips: {
					mode: 'index',
					intersect: false,
				},
				hover: {
					mode: 'nearest',
					intersect: true
				},
				scales: {
					xAxes: [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: '{{$lang['fhir_stats_chart_03']}}',// Day
						}
					}],
					yAxes: [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: '{{$lang['fhir_stats_chart_04']}}',// Entries
						}
					}]
				}
			}
		}
        return config
    }

    /**
     * render graphs (total and daily)
     * @param {Object} data data for graphs
     */
    var renderGraphs = function(data) {
        // total entries
        var total = data.total
        var total_chart_config = getTotalChartConfig(total)
        var ctx_total = document.getElementById('{{$name}}_total_chart').getContext('2d')
        var total_chart = new Chart(ctx_total, total_chart_config);
        
        // daily entries
        var counts_per_day = data.daily
        var date_range_chart_config = getDateRangeChartConfig(counts_per_day)
        var ctx_date_range_chart = document.getElementById('{{$name}}_date_range_chart').getContext('2d')
        var date_range_chart = new Chart(ctx_date_range_chart, date_range_chart_config);
    }

    /**
     * 
     */
    document.addEventListener("DOMContentLoaded",function(){

        var data = {!!json_encode($data, JSON_PRETTY_PRINT)!!}
        
        renderGraphs(data)

    })
}(window, document))
</script>