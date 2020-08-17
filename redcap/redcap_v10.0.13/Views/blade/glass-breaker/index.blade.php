{{-- <link rel="stylesheet" href="{{$dist_url}}/css/app.css"> --}}
<noscript>
  <strong>We're sorry but this feature doesn't work properly without JavaScript enabled. Please enable it to continue.</strong>
</noscript>

@if(!$browser_supported)
<h3>
  <i class="fas fa-exclamation-triangle"></i>
  <span>This feature is not available for your browser.</span>
</h3>

@else
<script src="{{$app_path_js}}vue.min.js"></script>
<script src="{{$app_path_js}}glass-breaker/dist/glass_breaker_vue.umd.min.js"></script>
<link rel="stylesheet" href="{{$app_path_js}}glass-breaker/dist/glass_breaker_vue.css">
<script>
  (function(window,document,Vue) {
    /**
     * create a instance of the glass breaker
     * and append it to an element using a selector
     * @param {string} target_selector
     */
    function appendGlassBreaker(target_selector) {
      var target = document.querySelector(target_selector)
      // create an element that will be turned into a vue element
      var container = document.createElement('div')
      target.appendChild(container)
      var app = new Vue(glass_breaker_vue).$mount(container)
      // add class after mounting the vue app
      app.$el.classList.add('glass-breaker-container')
      return app
    }

    window.addEventListener('DOMContentLoaded', (event) => {
      window.glass_breaker = appendGlassBreaker('{!!$target!!}')
    })
  }(window,document,Vue))
</script>
<style>
/* .glass-breaker-container {
  position: fixed;
  top: 5px;
  right: 5px;
  z-index: 10;
} */
</style>
@endif
