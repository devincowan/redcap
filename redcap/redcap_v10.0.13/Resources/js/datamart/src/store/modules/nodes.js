import Vue from 'vue'

import { debounce } from 'lodash'
import { getNodeFields } from '@/libraries/utils'


const module = {
    namespaced: true,
    state: {
        nodes: null,
        visibleNodes: null,
        query: '',
    },
    mutations: {
        /**
         * set the list of fields that are submitted for admin revision
         * @param {object} state 
         * @param {array} payload 
         */
        SET_NODES: function(state, payload) {
            Vue.set(state, 'nodes', payload)
            Vue.set(state, 'visibleNodes', payload)
            // when nodes are updated, also update the visible ones
        },
        SET_VISIBLE_NODES: function(state, payload) {
            Vue.set(state, 'visibleNodes', payload)
        },
        SET_QUERY: function(state, payload) {
            Vue.set(state, 'query', payload)
        },
    },
    actions: {
        /**
         * set the query to filter fields
         * 
         * @param {object} context 
         * @param {object} fields 
         */
        setQuery(context, text) {
            context.commit('SET_QUERY', text)
        },
        /**
         * filter nodes using text
         * 
         * @param {object} context 
         * @param {object} fields 
         */
        filterNodes(context, text) {
            const filter = function({node, text, parent=null}) {
                const { data, name, attributes: {label}, metadata: {total} } = node
                if(total>0) {
                    // loop through the chidlren of the node recursively
                    Object.keys(data).forEach(key=>{
                        const childNode = data[key]
                        return filter({node:childNode, text, parent:node})
                    })
                    // remove the node if has no more children after the recursive loop
                    if(parent && Object.keys(data).length==0) {
                        delete parent.data[name]
                    }
                }
                if(label) {
                    // check if the label of the node matches the query text
                    const regex = new RegExp(`${text}`,'i')
                    const assertion = (
                        !label.match(regex) &&
                        !name.match(regex)
                    )
                    if (assertion) {
                        delete parent.data[name]
                    }
                }
                return node
            }
            const escapeRegExp = function(string) {
                return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
            }
            // escape the text to make it regexp compatible
            const escapedText = escapeRegExp(text) // use if you want to disable regular expression syntax in filter
            try {
                // trim the text
                text = text.trim()
                // duplicate the nodes pulled from remote
                const nodes = JSON.parse(JSON.stringify(context.state.nodes))
                // check recursively every node that mactches the query text
                const visibleNodes = filter({node:nodes, text})
                context.commit('SET_VISIBLE_NODES', visibleNodes)
            } catch (error) {
                console.log(error)
            }
        },
        /**
         * set nodes
         * @param {object} context 
         * @param {object} nodes nodes fetched from REDCap
         */
        setNodes(context, nodes) {
            context.commit('SET_NODES',null) // reset the requests before making the remote call
            // const {data: {data}} = nodesWithRoot // skip the root node
            const { data: rootNode } = nodes
            context.commit('SET_NODES', rootNode)
            return rootNode
        },
    },
    getters: {
        /**
         * get the selected children of a node
         */
        getSelected: (state) => (list=[]) => {
            const filter = function({node, list, parent=null}) {
                const { data, name, attributes: {label}, metadata: {total} } = node

                if(total>0) {
                    // loop through the chidlren of the node recursively
                    Object.keys(data).forEach(key=>{
                        const childNode = data[key]
                        return filter({node:childNode, list, parent:node})
                    })
                    // remove the node if has no more children after the recursive loop
                    if(parent && Object.keys(data).length==0) {
                        delete parent.data[name]
                    }
                }
                if(label && list.indexOf(name)<0) {
                    delete parent.data[name]
                }
                return node
            }
            // duplicate the nodes pulled from remote
            const nodes = JSON.parse(JSON.stringify(state.nodes))
            if(!nodes) return
            return filter({node:nodes, list})
            
        },
        /**
         * return all fields of the node
         */
        getFields: (state) => {
            const nodes = state.nodes
            if(!nodes) return
            return getNodeFields(state.nodes)
        }
    }
}

export default module