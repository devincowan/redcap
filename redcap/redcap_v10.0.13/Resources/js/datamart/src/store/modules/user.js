import Vue from 'vue'
import User from '@/libraries/User'

const module = {
    namespaced: true,
    state: {
        info: null,
    },
    mutations: {
        SET_INFO: function(state, payload) {
            state.info = payload
        },
    },
    actions: {
        setInfo(context, user) {
            if(typeof user !== 'object') {
                context.commit('SET_INFO', null)
                return
            }
            const info = new User(user)
            context.commit('SET_INFO',info)
            return info
        },
    }
}

export default module;