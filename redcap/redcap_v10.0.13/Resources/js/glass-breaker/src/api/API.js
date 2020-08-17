/**
 * REDCap API interface
 */
import axios from 'axios'
import qs from 'qs'

export default class API {
    route_prefix = 'GlassBreakerController'

    constructor() {
        const base_url = window.app_path_webroot ? `${window.app_path_webroot}` : '/api'
        // 'app_path_webroot' is a global variable instantiated in REDCap
        const query_params = qs.parse(location.search, { ignoreQueryPrefix: true })
        const {pid=''} = query_params

        this.api_client = axios.create({
            baseURL: base_url,
            paramsSerializer: params => {
                if(pid) params.pid = pid
                if(window.redcap_csrf_token) params.redcap_csrf_token = window.redcap_csrf_token // csrf token for post requests
                /**
                 * set a serializer for the params
                 * available formats are indices, brackets, repeat, comma
                 */
                return qs.stringify(params, { arrayFormat: 'comma' }) // pid is always passed
            },
            headers: {common: {'X-Requested-With': 'XMLHttpRequest'}}
        })
        // this.api_client.defaults.params = {pid}
        // Add a request interceptor
        /* this.api_client.interceptors.request.use(config => {
            // add a cancel token to all requests
            const CancelToken = axios.CancelToken
            const source = CancelToken.source()
            // config.params = Object.assign({}, config.params, {cancelToken: source.token})
            console.log(config, arguments)
            return config
        }, error => {
            // Do something with request error
            return Promise.reject(error)
        }) */
    }

    /**
     * get data from the initialize endpoint:
     * 
     * - Reasons: ["Unspecified", "Direct Patient Care", "Scheduling", "Billing", "Record Review", "Investigation"]
     * - LegalMessage: "The patient file you are attempting to access is restricted.  If you have aclinical/business need to access the patient's file, please enter a reason andpassword and you may proceed."
     * - InappropriateMessage: ""
     * - DataRequirementReason: "None"
     * - DataRequirementExplanation: "None"
     * - TimeoutInMinutes: 15
     * - DataRequirementExplanationOverrides: []
     */
    initialize() {
        const route = `${this.route_prefix}:initialize`
        const params = {route}
        
        return this.api_client.get('', {params})
    }

    /**
     * check if a MRN is protected by "break the glass":
     * @param {string} mrn 
     */
    check(mrn) {
        const route = `${this.route_prefix}:check`
        const params = {route}
        const request_params = qs.stringify({mrn})
        
        return this.api_client.post('', request_params, {params})
    }

    /**
     * check if a MRN is protected by "break the glass":
     * @param {object} params {mrn, reason, explanation, department, department_type}
     * @param {object} config 
     */
    accept(params, config={}) {
        const request_params = new URLSearchParams()
        for(let key in params) request_params.append(key, params[key])
        // params
        const route = `${this.route_prefix}:accept`
        config.params = {route} // set the query_params
        return this.api_client.post('', request_params, config)
    }

    cancel(mrn, reason, department, department_type) {
        const route = `${this.route_prefix}:cancel`
        const params = {route}
        const request_params = qs.stringify({mrn, reason, department, department_type})
        
        return this.api_client.post('', request_params, {params})
    }

    getProtectedMrnList() {
        const route = `${this.route_prefix}:getProtectedMrnList`
        const params = {route}
        return this.api_client.get('', {params})
    }

    clearProtectedMrnList() {
        const route = `${this.route_prefix}:clearProtectedMrnList`
        const params = {route}
        return this.api_client.delete('', {params})
    }

    checkREDCapCredentials(password) {
        const route = `${this.route_prefix}:checkREDCapCredentials`
        const params = {route}
        const request_params = qs.stringify({password})
        return this.api_client.post('', request_params, {params})
    }
    
    test({id}) {

        return this.api_client.get('https://jsonplaceholder.typicode.com/posts/', {
            params: {id},
        })
    }
}