import Vue from 'vue'
import Bundle from '@/libraries/FhirResource/Bundle'
import Patient from '@/libraries/FhirResource/Patient'


const initialState = {
    resource: null, // resource as fetched from endpoint
    entries: [],
    patient: {}, // patient entry
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_RESOURCE: function(state, resource) {
            state.resource = resource
        },
        SET_ENTRIES: function(state, entries) {
            state.entries = entries
        },
        SET_PATIENT: function(state, entry) {
            state.patient = entry
        },
    },
    actions: {
        reset(context) {
            context.commit('SET_RESOURCE', initialState.resource)
            context.commit('SET_ENTRIES', initialState.entries)
            context.commit('SET_PATIENT', initialState.patient)
        },
        async fetchResource(context, {resource_type, interaction, mrn, params}) {
            // reset before fecthing
            context.dispatch('reset')
            const response = await Vue.$API.getFhirResourceByMrn(resource_type, interaction, mrn, params)
            const resource = response.data || {}
            context.commit('SET_RESOURCE', resource)
            context.dispatch('processResource', resource)
            return resource
        },
        processResource(context, resource) {
            const {source={}} = resource.metadata || {}
            const {resourceType} = source
            switch (resourceType) {
                case 'Bundle': {
                    const bundle = new Bundle(source)
                    const {entries=[]} = bundle
                    context.commit('SET_ENTRIES', entries)
                    break;
                }
                case 'Patient': {
                    const patient = new Patient(source)
                    context.commit('SET_PATIENT', patient)
                    break;
                }

                default:
                    break;
            }
        }
    },
}

export default module;