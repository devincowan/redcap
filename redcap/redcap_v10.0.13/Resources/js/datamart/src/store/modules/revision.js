import Vue from 'vue'
import EventBus from '@/libraries/EventBus'
import { compare } from '@/libraries/utils'
import {isEmpty, union, intersection} from 'lodash'


/**
 * this fields are mandatory
 * are preselected and cannot be unchecked
 */
export const mandatoryFields = ['id']

const initialState = Object.freeze({
    user_id: null,
    request_id: null,
    dateMin: '',
    dateMax: '',
    mrns: [],
    fields: mandatoryFields.slice(0),
    errors: {},
    /**
     * properties to use for submission and comparison
     */
    allowedSettings: {
        dates: true,
        dateMin: true,
        dateMax: true,
        fields: true,
        mrns: true,
    },
    // revision reference to check for dirty fields
    reference: null,
})

export default {
    namespaced: true,
    state: {...initialState},
    mutations: {
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_USER_ID: (state, payload) => state.user_id = payload,
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_REQUEST_ID: (state, payload) => state.request_id = payload,
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_DATE_MIN: (state, payload) => state.dateMin = payload,
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_DATE_MAX: (state, payload) => state.dateMax = payload,
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_MRNS: (state, payload) => state.mrns = payload,
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_FIELDS: (state, payload) => state.fields = payload,
        /**
         * 
         * @param {object} state 
         * @param {string} payload 
         */
        SET_REFERENCE: (state, payload) => state.reference = payload,
        SET_ALLOWED_SETTINGS: (state, payload) => state.allowedSettings = payload
    },
    actions: {
        /**
         * 
         * @param {object} context
         * @param {string} value 
         */
        setUserID: (context, value) => context.commit('SET_USER_ID', value),
        /**
         * 
         * @param {object} context
         * @param {string} value 
         */
        setRequestID: (context, value) => context.commit('SET_REQUEST_ID', value),
        /**
         * 
         * @param {object} context
         * @param {string} value 
         */
        setDateMin: (context, value) => {
            context.commit('SET_DATE_MIN', value)
            // validation
            context.dispatch('validator/validate', 'date', {root: true})
        },
        /**
         * 
         * @param {object} context
         * @param {string} value 
         */
        setDateMax: (context, value) => {
            context.commit('SET_DATE_MAX', value)
            // validation
            context.dispatch('validator/validate', 'date', {root: true})
        },
        /**
         * 
         * @param {object} context
         * @param {string} value 
         */
        setMRNs(context, value) {
            if(!Array.isArray(value)) {
                const regExp = new RegExp(/^(?:\s*)(.+?)(?:\s*)$/, 'gim')
                const matches = value.match(regExp) || []
                // make sure there are no whitespaces
                const list = matches.map(match=>match.trim())
                value = list
            }
            context.commit('SET_MRNS', value)
            // validation
            context.dispatch('validator/validate', 'mrns', {root: true})
        },
        /**
         * 
         * @param {object} context
         * @param {array} fields 
         */
        setFields(context, fields=[]) {
            // ensure that fields is an array
            if(!Array.isArray(fields)) return
            fields = union(fields, mandatoryFields) //always include mandatory fields

            /**
             * only allow fields available in the nodes store
             * @param {array} fields 
             */
            const filterFields = (fields) => {
                const allowedFields = context.rootGetters['nodes/getFields']
                return intersection(allowedFields, fields)
            }
            fields = filterFields(fields)
            context.commit('SET_FIELDS', fields)
            // validation
            context.dispatch('validator/validate', 'fields', {root: true})
        },
        /**
         * update the list of selected fields
         * 
         * @param {object} context 
         * @param {object} params {checked, name}
         */
        updateFields(context, {name, checked}) {
            if(mandatoryFields.indexOf(name)>=0 && !checked) return Promise.reject('this field is mandatory')
            const list = context.state.fields.slice(0) //duplicate array SLICE! not splice
            // change the list for NOT mandatory fields
            const index = list.indexOf(name)
            if(index>=0 && checked==false) list.splice(index, 1)
            else if(index<0 && checked==true) list.push(name)
            context.dispatch('setFields', list)
        },
        /**
         * 
         * @param {object} context 
         * @param {FormData} formData
         */
        async import(context, formData) {
            // helper function to normalize the data before importing
            const normalizeData = (data) => {
                const revision = Object.assign({}, data)
                if(!Array.isArray(revision.fields)) revision.fields = []
                if(!Array.isArray(revision.mrns)) revision.mrns = []
                return revision
            }
            const { data } = await Vue.$API.importRevision(formData)
            const revision = normalizeData(data)
            context.dispatch('set', revision)
            return data
        },
        /**
         * set the revision
         * 
         * @param {object} context 
         * @param {object} params {user_id, request_id, dateMin, dateMax, mrns, fields}
         */
        set(context, {user_id, request_id, dateMin='', dateMax='', mrns=[], fields=[]}) {
            if(typeof user_id !== 'undefined') context.dispatch('setUserID', user_id)
            if(typeof request_id !== 'undefined') context.dispatch('setRequestID', request_id)
            if(typeof dateMin !== 'undefined') context.dispatch('setDateMin', dateMin)
            if(typeof dateMax !== 'undefined') context.dispatch('setDateMax', dateMax)
            if(typeof mrns !== 'undefined') context.dispatch('setMRNs', mrns)
            if(typeof fields !== 'undefined') context.dispatch('setFields', fields)
            EventBus.$emit('REVISION_SET')
        },
        setReference(context, revision) {
            const reference = revision.clone()
            context.commit('SET_REFERENCE', reference)
        },
        async submit(context) {

            let params = {
                mrns: context.state.mrns,
                fields: context.state.fields,
                date_min: context.state.dateMin,
                date_max: context.state.dateMax,
            }
            /**
             * filter params and send only the ones allowed
             * @param {object} params 
             * @param {object} allowedSettings {dates,fields,mrns} 
             */
            const filterParams = (params, {dates,fields,mrns}) => {
                if(!dates && params.hasOwnProperty('date_min')) delete params['date_min']
                if(!dates && params.hasOwnProperty('date_min')) delete params['date_min']
                if(!fields && params.hasOwnProperty('fields')) delete params['fields']
                if(!mrns && params.hasOwnProperty('mrns')) delete params['mrns']
                return params
            }
            const { state: { allowedSettings } } = context
            params = filterParams(params, allowedSettings)
            const {data:revision} = await Vue.$API.addRevision(params)
            const { metadata: {id: revision_id} } = revision // get the revision_id
            const response = await Vue.$API.getRevisions()
            const {data: revisions} = response
            await context.dispatch('revisions/setList', revisions, {root: true})
            //select the revision that has just been added
            await context.dispatch('revisions/setSelected', revision_id, {root: true})
        },
        validate(context) {
            const keys = ['mrns','date','fields']
            keys.forEach(key => {
                context.dispatch('validator/validate', key, {root: true})
            })
            const {errors} = context.rootState.validator
            return isEmpty(errors)
        },
        /**
         * reset the revision using data from the selected (active) revision
         * 
         * @param {object} context 
         */
        reset(context) {
            const revision = context.state.reference
            if(!revision) return
            const { metadata: {creator: {id: user_id}}, dateMin, dateMax, mrns, fields } = revision
            const settings = {user_id, dateMin, dateMax, mrns, fields}
            context.dispatch('set', settings)
        },
        /**
         * reset the revision using data from the selected (active) revision
         * 
         * @param {object} context 
         */
        setAllowedSettings(context, settings) {
            const {state : {allowedSettings}} = context
            Object.keys(settings).forEach((key) => {
                const value = settings[key]
                if(allowedSettings.hasOwnProperty(key))
                    allowedSettings[key] = value
            })
            context.commit('SET_ALLOWED_SETTINGS', allowedSettings)
        },
    },
    getters: {
        /**
         * check if the current revision settings have been modified.
         * A dirty revision can be submitted for approval
         * 
         * @param {object} state
         * @param {object} getters
         * @param {object} rootState
         * @param {object} rootGetters
         */
        isDirty(state) {
            const revision = state.reference
            if(revision == null) return false
            let clean = true
            const {allowedSettings: {dates, mrns, fields}} = state
            if(dates) {
                clean = clean 
                && compare(revision.dateMin, state.dateMin, 'date')
                && compare(revision.dateMax, state.dateMax, 'date')
            }
            if(fields) {
                clean = clean 
                && compare(revision.fields.sort(), state.fields.sort())
            }
            if(mrns) {
                clean = clean 
                && compare(revision.mrns, state.mrns)
            }
            return !clean
            /* return !(
                compare(revision.dateMin, state.dateMin, 'date')
                && compare(revision.dateMax, state.dateMax, 'date')
                // attention to performances with sort here
                && compare(revision.fields.sort(), state.fields.sort())
                // ignore MRNs when testing for dirtyness
                && compare(revision.mrns, state.mrns)
            ) */
        },
    }
}