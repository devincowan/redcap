/**
 * store the selected medical record numbers
 * for which the data will be fetched
 */

const initialState = {
    list: []
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_LIST: function(state, payload) {
            state.list = payload
        },
    },
    actions: {
        setList(context, list) {
            context.commit('SET_LIST',list)
        },
    }
}

export default module