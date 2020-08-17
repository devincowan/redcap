import Vue from 'vue'
import Vuex from 'vuex'

import counter from '@/store/modules/counter'
import information from '@/store/modules/information'
import mrns from '@/store/modules/mrns'

Vue.use(Vuex)

var initialState = {}
const store = new Vuex.Store({
    state: {...initialState},
    modules: {
        counter,
        information,
        mrns,
    }
})

export default store