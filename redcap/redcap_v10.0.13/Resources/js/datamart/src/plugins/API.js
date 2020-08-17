/**
 * set a global $API reference
 */
import API from '@/API'
export default {
    install(Vue, options) {
        // set a global $API reference with params
        Vue.$API = Vue.prototype.$API = new API()
        // Add Vue instance methods by attaching them to Vue.prototype.
        // Vue.prototype.$myProperty = 'This is a Vue instance property.'
    },
}