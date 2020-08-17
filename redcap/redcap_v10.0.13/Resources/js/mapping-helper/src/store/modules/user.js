import Vue from 'vue'

const initialState = {
    info: null,
    tokens: [],
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_INFO: function(state, info) {
            state.info = info
        },
        SET_TOKENS: function(state, list) {
            state.tokens = list
        },
    },
    actions: {
        async fetchInfo(context) {
            // set the list to an empty array before fetching remote data
            context.commit('SET_INFO',initialState.info)
            context.commit('SET_TOKENS',initialState.tokens)
            const response = await Vue.$API.getUserInfo()
            const {info,tokens=[]} = response.data
            context.commit('SET_INFO',info)
            context.commit('SET_TOKENS',tokens)
            return {info, tokens}
        },
        
    },
}

export default module;