import Vue from 'vue'

const initialState = {
    list: [],
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
            // only store unique values
            const unique_items = list.filter( (value, index, self) => self.indexOf(value)===index )
            context.commit('SET_LIST',unique_items)
        },
    }
}

export default module;