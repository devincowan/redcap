import Warning from '@/libraries/Warning'
import Revision from '@/libraries/Revision'

const module = {
    namespaced: true,
    state: {
        list: {}
    },
    mutations: {
        ADD_WARNING: function(state, {key, warning}) {
            state.list[key] = warning
        },
        REMOVE_WARNING: function(state, {key}) {
            if(state.list.hasOwnProperty(key))
                delete state.list[key]
        }
    },
    actions: {
        checkAll({dispatch}) {
            const actions = ['checkToken',
                            'checkNoRevisions',
                            'checkMrns',
                            'checkRevisionIsActive',
                            'checkRevisionApproved',
                            // 'checkUserCanRepeatRevisions'
            ]
            actions.forEach(action => dispatch(action))
        },
        checkToken(context) {
            const user = context.rootState.user.info
            const key = 'validToken'
            if(user.hasValidToken()) return context.commit('REMOVE_WARNING', {key})
            let description = ''
            const {settings: {lang, standalone_launch_url, standalone_launch_enabled, ehr_system_name}} = context.rootState.settings

            if(standalone_launch_enabled) {
                description = `<p>${lang.ehr_launch_modal_02}</p>`
                description += `<p class="font-italic">${lang.ehr_launch_modal_03}</p>`
                description += `<div class="mt-2"><a class="btn btn-sm btn-primary" href="${standalone_launch_url}">Log in to ${ehr_system_name}</a></div>`
            }else {
                description = `An active FHIR access token was not found.
                    You will need to launch REDCap from inside your EHR system first in order
                    to restore communication with the EHR.`
            }
            return context.commit('ADD_WARNING', {
                key,
                warning: new Warning({
                    summary: 'You do not have a valid access token',
                    description,
                    type: 'warning'
                })
            })
        },
        checkUserCanRepeatRevisions(context) {
            const user = context.rootState.user.info
            const key = 'canRepeatRevisions'
            if(user.canRepeatRevision()) return context.commit('REMOVE_WARNING', {key})
            else {
                // user cannot repeat revisions. check if the revision has already been run
                const revision = context.rootGetters['revisions/selected']
                if(revision instanceof Revision) {

                    if(revision.canBeRunByUser(user) || !revision.hasBeenExecuted()) {
                        return context.commit('REMOVE_WARNING', {key})
                    }
                }
            }
            return context.commit('ADD_WARNING', {
                key,
                warning: new Warning({
                    summary: 'You can not run this revision',
                    description: `This revision has already been run.
                    You do not have the privileges to run a revision
                    multiple times.`,
                    type: 'warning'
                })
            })
        },
        checkNoRevisions(context) {
            const selectedRevision = context.rootGetters['revisions/selected']
            const key = 'noRevisions'
            if(selectedRevision) return context.commit('REMOVE_WARNING', {key})
            return context.commit('ADD_WARNING', {
                key,
                warning:new Warning({
                    summary: 'No revisions',
                    description: `There are no revisions available.`,
                    type: 'warning'
                })
            })
        },        
        checkMrns(context) {
            const key = 'totalMrns'
            const selectedRevision = context.rootGetters['revisions/selected']
            if(!selectedRevision || selectedRevision.getTotalFetchableMrns()>=1) return context.commit('REMOVE_WARNING', {key})
            return context.commit('ADD_WARNING', {
                key,
                warning: new Warning({
                    summary: 'No fetchable records in the project',
                    description: `You can only run a revision if you have at least 1 fetchable record in your project.`,
                    type: 'warning'
                })
            })
        },
        checkRevisionIsActive(context) {
            const key = 'isActive'
            const selectedRevision = context.rootGetters['revisions/selected']
            const isActive = context.rootGetters['revisions/isActive'](selectedRevision)
            if(!selectedRevision || isActive) return context.commit('REMOVE_WARNING', {key})
            // if(!selectedRevision.isActive()) {
            return context.commit('ADD_WARNING', {
                key,
                warning: new Warning({
                    summary: 'This is not the active revision',
                    description: `Only the most recent revision can be run.`,
                    type: 'warning'
                })
            })
        },
        checkRevisionApproved(context) {
            const key = 'approved'
            const selectedRevision = context.rootGetters['revisions/selected']
            if(!selectedRevision || selectedRevision.isApproved()) return context.commit('REMOVE_WARNING', {key})
            return context.commit('ADD_WARNING', {
                key,
                warning: new Warning({
                    summary: 'Revision not approved',
                    description: `This revision must be approved by an admin before it can be run.`,
                    type: 'warning'
                })
            })
        },
    },
    getters: {}
}

export default module