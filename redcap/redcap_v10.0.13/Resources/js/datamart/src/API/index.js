/**
 * API interface
 */
// import axios from 'axios'
import qs from 'qs'
import {default as axios, CancelToken} from 'axios'

class API {
    route = 'DataMartController'; // route name for the DataMart API
    requests = {} // store ajax requests
    cancel = null //cancel token

    constructor() {
        let baseURL = window.app_path_webroot ? `${window.app_path_webroot}` : '/backend/api'
        this.base_url = baseURL
        // if(location.hostname==='localhost') base_url = '/backend/api'
        const query_params = qs.parse(location.search, { ignoreQueryPrefix: true })
        const {pid='',request_id=''} = query_params

        this.api_client= axios.create({
            baseURL,
            // params,
            paramsSerializer: params => {
                if(pid) params.pid = pid //inject pid
                if(request_id) params.request_id = request_id //inject request_id
                if(window.redcap_csrf_token) params.redcap_csrf_token = window.redcap_csrf_token // csrf token for post requests
                /**
                 * set a serializer for the params
                 * FHIR endpoints like the repeat arrayFormat.
                 * available formats are indices, brackets, repeat, comma
                 */
                return qs.stringify(params, { arrayFormat: 'comma' })
            },
            headers: {common: {'X-Requested-With': 'XMLHttpRequest'}}
        })
    }

    /**
     * export URL used for exporting a revision
     * @param {object} options 
     */
    getExportURL(options) {
        const route = `${this.route}:exportRevision`
        options.route = route
        // get the pid from the URL
        const query_params = qs.parse(location.search, { ignoreQueryPrefix: true })
        const {pid=''} = query_params
        options.pid = pid
        const query = qs.stringify(options)
        const url = `${this.base_url}?${query}`
        return url
    }

    getRoute(action) {
        return `route=${this.route}:${action}`
    }

    /**
     * get the revisions list
     * 
     */
    getUser() {
        var params = {
            route: `${this.route}:getUser`,
        }
        return this.api_client.get('',{params})
        // return this.ajaxRequest(url, 'GET', {});
    }

    /**
     * get the revisions list
     * 
     */
    getSettings() {
        var params = {
            route: `${this.route}:getSettings`,
        }
        return this.api_client.get('',{params})
    }

    /**
     * get the revisions list
     */
    getRevisions() {
        var params = {
            route: `${this.route}:revisions`
        }
        return this.api_client.get('',{params})
        // return this.ajaxRequest(url, 'GET', {});
    }

    /**
     * get sourceFields
     */
    getSourceFields() {
        var params = {
            route: `${this.route}:sourceFields`
        }
        return this.api_client.get('',{
            params,
            responseType: 'text',
        })
        // return this.ajaxRequest(url, 'GET', {dataType:'html'})
    }

    /**
     * add a revision on the database
     */
    addRevision(data={}) {
        var params = {
            route: `${this.route}:addRevision`
        }
        const post_params = qs.stringify(data)
        return this.api_client.post('', post_params, {params})
    }
    /**
     * add a revision on the database
     */
    runRevision(revision_id, mrn) {
        var params = {
            route: `${this.route}:runRevision`
        }
        const post_params = qs.stringify({revision_id, mrn})
        const source = CancelToken.source()
        const request = this.api_client.post('', post_params, {
            params,
            cancelToken: source.token
        })
        // modify the promise adding the cancel token
        request.cancelToken = source
        return request
    }
    /**
     * approve a revision
     */
    approveRevision(data={}) {
        var params = {
            route: `${this.route}:approveRevision`
        }
        const post_params = qs.stringify(data)
        return this.api_client.post('', post_params, {params})
    }
    /**
     * import a revision
     * @param {FormData} data 
     */
    importRevision(data={}) {
        var params = {
            route: `${this.route}:importRevision`,
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }
        // const post_params = qs.stringify(data)
        const post_params = data
        return this.api_client.post('', post_params, {params}/* {processData: false, contentType: false,} */)
    }
    /**
     * delete a revision
     */
    deleteRevision(data={}) {
        var params = {
            route: `${this.route}:deleteRevision`
        }
        return this.api_client.delete('', {data, params})
    }

    /**
     * static method
     * send an ajax request
     * 
     * @param {string} method 
     * @param {object} params 
     */
    ajaxRequest(url, method, data, options)
    {
        // if(location.hostname==='localhost') {
            const redcap_csrf_token = window.redcap_csrf_token || ''
            if(data instanceof FormData) {
                data.append('redcap_csrf_token', redcap_csrf_token)
            }else {
                data = Object.assign({}, data, {redcap_csrf_token}) // add the csrf token
            }
        // }
        /* var base_params = {
            url: url || location.href,
            type: method,
            data: data,
            dataType: 'json',
        } */
        const base_params = {
            url,
            method,
        }
        
        var config = Object.assign(base_params, options)
        return axios(config)
        // return $.ajax(params)
    }

    /**
     * 
     * @param {string} key 
     * @param {XMLHttpRequest} request 
     */
    addRequest(key, request)
    {
        try {
            if(this.requests[key]) this.requests[key].abort()
            this.requests[key] = request
            request.done((response) => {
                delete this.requests[key]
            })
        } catch (error) {
            console.error('error adding the request to the active XMLHttpRequest list' )
        }
    }
}

export default API