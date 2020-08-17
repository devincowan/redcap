import {resource_types, interactions} from '@/variables'

const initialState = {
    resource_type: '', // resource type
    interaction: '', // method name of the interaction
    mrn: '',
    params: {},
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_RESOURCE_TYPE: function(state, resource_type) {
            state.resource_type = resource_type
        },
        SET_INTERACTION: function(state, interaction) {
            state.interaction = interaction
        },
        SET_MRN: function(state, payload) {
            state.mrn = payload
        },
        SET_PARAMS: function(state, payload) {
            state.params = payload
        },
    },
    actions: {
        /* setEndpoint(context, endpoint) {
            context.commit('SET_ENDPOINT',endpoint)
        }, */
        reset(context) {
            context.commit('SET_PARAMS', initialState.params)
        },
        setResourceType(context, resource_type) {
            context.commit('SET_RESOURCE_TYPE',resource_type)
        },
        setInteraction(context, interaction) {
            context.commit('SET_INTERACTION',interaction)
        },
        setMRN(context, mrn) {
            context.commit('SET_MRN',mrn)
        },
        setParams(context, params) {
            context.commit('SET_PARAMS',params)
        },
    }
}

export default module;