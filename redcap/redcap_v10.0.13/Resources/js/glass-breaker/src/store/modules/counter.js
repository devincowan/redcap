const initialState = {
    count: 0
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        INCREMENT: function(state, payload) {
            state.count += 1
        },
        DECREMENT: function(state, payload) {
            state.count -= 1
        },
    },
    actions: {
        increment(context) {
            context.commit('INCREMENT',null)
        },
        decrement(context) {
            context.commit('DECREMENT',null)
        },
    }
}

export default module;