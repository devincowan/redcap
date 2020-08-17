import API from '@/API'

export default {
    install(Vue, options) {
        // set a global $API reference with params
        Vue.$API = new API()
        // Add Vue instance methods by attaching them to Vue.prototype.
        Vue.prototype.$API = Vue.$API
    },
}