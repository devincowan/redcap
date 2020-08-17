
 @if(!$browser_supported)
<h3>
    <i class="fas fa-exclamation-triangle"></i>
    <span>This feature is not available for your browser.</span>
</h3>
@else

{{-- include javascript and CSS --}}
<script src="{{$app_path_js}}vue.min.js"></script>
<script src="{{$app_path_js}}datamart/dist/datamart_vue.umd.min.js"></script>
<link rel="stylesheet" href="{{$app_path_js}}datamart/dist/datamart_vue.css">
<script>
/**
 * launch the DataMart and return a reference to the app
 */
function launchDataMart(selector) {
    // expose a variable that will contain a reference to the Data Mart App
    var datamart_app = new Vue(datamart_vue).$mount(selector)
    datamart_app.$on('load', function(){
        @if($route==='review')
        this.goToReviewProjectPage()
        @else
        this.goToCreateProjectPage()
        @endif
    })

    return datamart_app
}
</script>
@endif