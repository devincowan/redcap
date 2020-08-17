const module = {
    namespaced: true,
    state: {
        count: 0,
    },
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