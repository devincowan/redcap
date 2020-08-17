import Vue from 'vue'

const initialState = {
    settings: {}
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_SETTINGS: function(state, payload) {
            state.settings = {...payload}
        },
    },
    actions: {
        set(context, settings) {
            context.commit('SET_SETTINGS',settings)
        },
    }
}

export default module