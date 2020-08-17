
 @if(!$browser_supported)
<h3>
    <i class="fas fa-exclamation-triangle"></i>
    <span>This feature is not available for your browser.</span>
</h3>

@elseif($datamart_enabled)
<div id="datamart"></div>

{{-- include javascript and CSS --}}
<script src="{{$app_path_js}}vue.min.js"></script>
<script src="{{$app_path_js}}datamart/dist/datamart_vue.umd.min.js"></script>
<link rel="stylesheet" href="{{$app_path_js}}datamart/dist/datamart_vue.css">
<script>
(function(window,document, Vue) {
    window.addEventListener('DOMContentLoaded', function(event) {
         new Vue(datamart_vue).$mount('#datamart')
    })
}(window,document, Vue))
</script>


@else
<h3>
    <i class="fas fa-info-circle"></i>
    <span>This is not a Datamart Project!</span>
</h3>
@endif