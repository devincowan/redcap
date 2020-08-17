import Vue from 'vue'
import {union, get} from 'lodash'

const initialState = {
    info: {},
    datamart_revision: {},
    cdp_mapping: [],
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_INFO: function(state, payload) {
            state.info = payload
        },
        SET_DATAMART_REVISION: function(state, payload) {
            state.datamart_revision = payload
        },
        SET_CDP_MAPPING: function(state, payload) {
            state.cdp_mapping = payload
        },
    },
    actions: {
        async fetchInfo(context) {
            const response = await Vue.$API.getProjectInfo()
            const {data: {info, datamart_revision, cdp_mapping}} = response
            context.commit('SET_INFO',info)
            context.commit('SET_DATAMART_REVISION', datamart_revision)
            context.commit('SET_CDP_MAPPING', cdp_mapping)
            return Promise.resolve(true)
        },
    },
    getters: {
        mappedCodes: (state) => {
            const datamart_fields = get(state, 'datamart_revision.data.fields', [])
            const cdp_mapping = get(state, 'cdp_mapping', [])

            // extract the codes from the CDP mapping
            const cdp_fields = cdp_mapping.map(mapping => mapping.external_source_field_name) || []
            // merge the datamart fields with the cdp mapping (if project is both CPD and datamart)
            const codes = union(datamart_fields, cdp_fields)
            return codes
        }
    }
}

export default module;