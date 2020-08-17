import Vue from 'vue'

const initialState = {
    fields: {},
    codes: [],
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_FIELDS: function(state, payload) {
            state.fields = payload
        },
        SET_CODES: function(state, payload) {
            state.codes = payload
        },
    },
    actions: {
        async fetchMetadata(context) {
            const response = await Vue.$API.getFhirMetadata()
            const {data: {fields, codes}} = response
            context.commit('SET_FIELDS',fields)
            context.commit('SET_CODES',codes)
            return Promise.resolve(true)
        },
    }
}

export default module;