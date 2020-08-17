import Vue from 'vue'

const initialState = {
    settings: {
        project_id: null,
        lang: null,
        standalone_authentication_flow: null,
        standalone_launch_enabled: null,
        standalone_launch_url: null,
        ehr_system_name: null,
    }
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
        async fetch(context) {
            context.commit('SET_SETTINGS',null) // reset the requests before making the remote call
            const {data:settings} = await Vue.$API.getSettings()
            context.commit('SET_SETTINGS',settings)
            return settings
        },
    }
}

export default module;