/**
 * set a global_params variable tha can hold
 * App-wide settings
 */
export default {
    install(Vue, options) {
        // Add or modify global methods or properties.
        Vue.global_params = options
        // Add Vue instance methods by attaching them to Vue.prototype.
        // Vue.prototype.$myProperty = 'This is a Vue instance property.'
    },
}