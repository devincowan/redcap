/**
 * API interface
 */
import axios from 'axios'
import qs from 'qs'

export default class API {
    route_prefix = 'FhirMappingHelperController'
    requests = {} // store ajax requests 

    constructor() {
        const base_url = window.app_path_webroot ? `${window.app_path_webroot}index.php` : '/backend/api'
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
                 * FHIR endpoints like the repeat arrayFormat.
                 * available formats are indices, brackets, repeat, comma
                 */
                return qs.stringify(params, { arrayFormat: 'comma' }) // pid is always passed
            }
        })
        this.api_client.defaults.params = {pid}
    }


    /**
     * get data from a FHIR endpoint
     * @param {Interaction} interaction 
     * @param {string} mrn 
     * @param {object} params additional params to use in the request
     */
    getFhirResourceByMrn(resource_type, interaction, mrn, params=[])
    {
        const url = ''
        // get parameters from the current URL

        params = JSON.stringify(params)
        const request_params = {
            // route,
            interaction,
            resource_type,
            mrn,
            params,
        }

        // route is only usued in production
        const route = 'fetchFhirResourceByMrn'
        request_params.route = `${this.route_prefix}:${route}`


        return this.api_client.get(url, {
            params: request_params,
        })
    }

    /**
     * get data from a FHIR endpoint
     * @param {string} endpoint 
     * @param {string} mrn 
     * @param {object} params additional params to use in the request
     */
    getFhirResource(endpoint, patient_id, params=[])
    {
        // https://redcap.test/API_DEV/?pid=104&route=FhirDataToolController:fhirTest&userid=delacqf"
        // const test = qs.parse('status=completed&status=stopped&status=on-hold')
        // const extra_params = qs.stringify(params)
        const route = `${this.route_prefix}:${route}`
        // get parameters from the current URL

        const request_params = {
            route,
            endpoint,
            patient_id,
            params,
        }

        return this.api_client.get('', {
            params: request_params,
        })
    }

    /**
     * get the active tokens of a user
     * @param {username} param0 
     */
    getTokens({user})
    {
        const url = ''
        const route = 'getTokens'
        // get parameters from the current URL
        const request_params = {
            user,
        }
        request_params.route = `${this.route_prefix}:${route}`

        return this.api_client.get(url, {
            params: request_params,
        })
    }

    /**
     * get the active tokens of a user
     */
    getUserInfo()
    {
        const url = ''
        const route = 'getUserInfo'
        // get parameters from the current URL

        const request_params = {}
        request_params.route = `${this.route_prefix}:${route}`

        return this.api_client.get(url, {
            params: request_params,
        })
    }

    /**
     * get the active tokens of a user
     */
    getSettings()
    {
        const url = ''
        const route = 'getSettings'
        // get parameters from the current URL

        const request_params = {}
        request_params.route = `${this.route_prefix}:${route}`

        return this.api_client.get(url, {
            params: request_params,
        })
    }

    /**
     * get info about a project
     * 
     */
    getProjectInfo() {
        const url = ''
        // route is only usued in production
        const route = 'getProjectInfo'
        const request_params = {}
        
        request_params.route = `${this.route_prefix}:${route}`

        return this.api_client.get(url, {
            params: request_params,
        })
    }

    /**
     * get the mapping data for the FHIR resources
     */
    getFhirMetadata() {
        const url = ''
        const request_params = {}

        // route is only usued in production
        const route = 'getFhirMetadata'
        request_params.route = `${this.route_prefix}:${route}`


        return this.api_client.get(url, {
            params: request_params,
        })
    }

    exportCodes(parameters) {
        // const {params} = this.api_client.defaults //extract the default parameters
        const url = ''
        const route = 'exportCodes'
        const params = {
            route: `${this.route_prefix}:${route}`,
            responseType: 'blob',
        }
        const request_params = qs.stringify(parameters)
        // return this.api_client.get(url, {params}) // inject the default parameters in the request
        return this.api_client.post(url, request_params, {params}) // inject the default parameters in the request
    }

    sendNotification(parameters) {
        // const {params} = this.api_client.defaults //extract the default parameters
        const request_params = qs.stringify(parameters)
        const url = ''
        const route = 'notifyAdmin'
        const params = {
            route: `${this.route_prefix}:${route}`
        }
        return this.api_client.post(url, request_params, {params}) // inject the default parameters in the request
    }
}