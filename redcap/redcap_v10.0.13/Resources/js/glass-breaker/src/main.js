import Vue from 'vue'
import App from './App.vue'

Vue.config.productionTip = false
// console.log('env', JSON.stringify(process.env))

// automatically render in development
if(process.env.NODE_ENV!=='production') {
  new Vue({
    render: h => h(App),
  }).$mount('#app')
}

// expose the constuctor
/**
 * @param element can be a css selector or an HTML element
 */
window.GlassBreakerVue = function(element) {
  return new Vue({
    render: h => h(App),
  }).$mount(element)
}