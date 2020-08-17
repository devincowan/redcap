import Vuex from 'vuex';
import Vue from 'vue';
Vue.use(Vuex);

import settingsModule from '@/store/modules/settings'
import warningsModule from '@/store/modules/warnings'
import revisionsModule from '@/store/modules/revisions'
import revisionModule from '@/store/modules/revision'
import userModule from '@/store/modules/user'
import nodesModule from '@/store/modules/nodes'
import validatorModule from '@/store/modules/validator'
import mrnsModule from '@/store/modules/mrns'

/**
 * state management
 */


var initialState = {}

const store = new Vuex.Store({
    state: Object.assign({}, initialState),
    modules: {
        settings: settingsModule,
        revisions: revisionsModule,
        revision: revisionModule,
        user: userModule,
        nodes: nodesModule,
        validator: validatorModule,
        warnings: warningsModule,
        mrns: mrnsModule,
    },
    mutations: {},
    actions: {
        /**
         * select a revision adn update all settings with it's data 
         * 
         * @param {object} context 
         * @param {object} revision
         */
        selectRevision(context, revision) {
            const creator = revision.getCreator()
            const settings = {
                user_id: creator.id,
                request_id: revision.getRequestID(),
                dateMin: revision.dateMin,
                dateMax: revision.dateMax,
                fields: revision.fields,
                mrns: revision.mrns,
            }
            context.dispatch('revision/set', settings)
            context.dispatch('revision/setReference', revision)
            // set the list of MRNs
            const {fetchable_mrns} = revision.metadata
            context.dispatch('mrns/setList', fetchable_mrns)
        },
    },
    getters: {
        settings(state) {
            const { revision: { user_id, request_id,  dateMin, dateMax, mrns, fields } } = state
            return { user_id, request_id, dateMin, dateMax, mrns, fields }
        },
    },
})

export default store