import Vue from 'vue'
import {first} from 'lodash'
import Revision from '@/libraries/Revision'
import EventBus from '@/libraries/EventBus'

const module = {
    namespaced: true,
    state: {
        list: [],
        selected_id: null,
    },
    mutations: {
        SET_LIST: function(state, payload) {
            state.list = payload
        },
        SET_SELECTED: function(state, payload) {
            state.selected_id = payload
        },
    },
    actions: {
        setList(context, remote_list) {
            const list = []
            remote_list.forEach((params, index) => {
                let revision = new Revision(params)
                // set the revision index (visible, for example, in the RevisionSelect DropDown)
                revision.setIndex(index+1)
                list.push(revision)
            })
            list.reverse()
            context.commit('SET_LIST', list)
            return list
        },
        setSelected(context, revision_id) {
            context.commit('SET_SELECTED', revision_id)
            const revision = context.getters['selected']
            context.dispatch('selectRevision', revision, { root: true })
            EventBus.$emit('REVISION_SELECTED')
            return revision
        },
        selectMostRecentRevision(context) {
            const revisions = context.state.list
            if(revisions.length>0) {
                const revision = revisions[0]
                const revision_id = revision.getID()
                context.dispatch('setSelected', revision_id)
            }
        },
        run(context, {revision_id, mrn}) {
            return Vue.$API.runRevision(revision_id, mrn)
        },
        async approve(context, revision_id) {
            const params = { revision_id }
            const approve_response =  await Vue.$API.approveRevision(params)
            const {data} = approve_response
            const { data: revision } = data
            const {metadata: {id}} = revision // get the revision_id
            // reload the list
            const revisions_response = await Vue.$API.getRevisions()
            const {data: revisions} = revisions_response
            const list = await context.dispatch('setList', revisions)
            //select the revision that has just been approved
            context.dispatch('setSelected', id)
            return approve_response
        },
        async delete(context, revision_id) {
            const params = { revision_id }
            const result =  await Vue.$API.deleteRevision(params)
            
            // reload the list
            const revisions_response = await Vue.$API.getRevisions()
            const {data: revisions} = revisions_response
            const list = await context.dispatch('setList', revisions)
            if(list.length>0) {
                const lastRevision = list[0]
                const {metadata: {id}} = lastRevision
                //select the revision that has just been approved
                context.dispatch('setSelected', id)
            }
            return result
        },
    },
    getters: {
        selected(state) {
            const revisions = state.list
            const revision_id = state.selected_id
            if(!revision_id) return

            const revision = revisions.filter(revision => revision.getID() === revision_id).shift()
            if(revision===null || typeof revision !== 'object') return

            return revision
        },
        /**
         * get the total number of revisions available
         * @param {object} state 
         */
        total(state) {
            const revisions = state.list
            let total = revisions.length
            if(isNaN(total) || total<1) total = 0
            return total
        },
        /* current: (state) => {
            if(isNaN(state.selected)) return null
            const revision = state.list.filter( revision => revision.id==state.selected)
            return revision
        }, */
        isActive: (state) => (revision) => {
            try {
                const { metadata: { id} } = revision
                const revisions = state.list
                const active_revision = first(revisions)
                const { metadata: { id:active_revision_ID } } = active_revision
                return (id===active_revision_ID)
            } catch (error) {
                return false
            }
        },
    }
}

export default module