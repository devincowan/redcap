/**
 * store for mapping values or keys
 */

const initialState = {
    values: [],
    keys: [],
}

const module = {
    namespaced: true,
    state: {...initialState},
    mutations: {
        SET_VALUES: function(state, {values}) {
            state.values = values
        },
        SET_KEYS: function(state, {keys}) {
            state.key = keys
        },
    },
    actions: {
        setValues(context, {values}) {
            context.commit('SET_VALUES',{values})
        },
        setKey(context, {key}) {
            context.commit('SET_KEYS',{key})
        }
    },
}

export default module;