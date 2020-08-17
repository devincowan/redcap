import Vue from 'vue'
import Vuex from 'vuex'

import counter from '@/store/modules/counter'
import endpoint from '@/store/modules/endpoint'
import settings from '@/store/modules/settings'
import resource from '@/store/modules/resource'
import mappings from '@/store/modules/mappings'
import fhir_metadata from '@/store/modules/fhir_metadata'
import project from '@/store/modules/project'
import user from '@/store/modules/user'

Vue.use(Vuex)

var initialState = {}

const store = new Vuex.Store({
    state: Object.assign({}, initialState),
    modules: {
        counter: counter,
        endpoint,
        resource,
        mappings,
        fhir_metadata,
        user,
        project,
        settings,
    }
})

export default store