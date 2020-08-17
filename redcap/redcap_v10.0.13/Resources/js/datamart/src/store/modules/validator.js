
import Vue from 'vue'
import {isEmpty, difference} from 'lodash'
import {mandatoryFields} from '@/store/modules/revision'

/**
 * simple rule class to validate asserts
 */
class Rule {
    rule = () => {}
    message = ''
    invalid = false
    
    constructor(rule, message) {
        this.rule = rule
        this.message = message
    }

    /**
     * apply the validation rule
     * 
     * @param {mixed} params params are applied to the validation rule
     */
    validate(context) {
        if(typeof this.rule !== 'function') {
            console.error('rule must be a function')
            return
        }
        this.invalid = this.rule(context)===false
    }
}

export const rules = {
    mrns: [
        /* new Rule(
            (context) => context.rootState.revision.mrns.length>0,
            'You have not entered any Medical Record Numbers into the text box. Please enter one or more MRNs with one MRN per line.'
        ) */
    ],
    date: [
        new Rule(
            (context) => {
                const {dateMin: min, dateMax: max} = context.rootState.revision
                return (isEmpty(max) || min <= max)
            },
            'Invalid date range. Please insert a valid date range.'
        )
    ],
    fields: [
        new Rule(
            (context) => {
                const fields = context.rootState.revision.fields
                return difference(fields, mandatoryFields).length > 0
            },
            'No fields have been selected. You must choose at least one field other than the MRN field in the Source Fields List.'
        ),
        new Rule(
            (context) => {
                const fields = context.rootState.revision.fields
                return difference(mandatoryFields, fields).length === 0
            },
            'All mandatory fields must be selected.'
        )
    ]
}

const module = {
    namespaced: true,
    state: {
        errors: {},
    },
    mutations: {
        /**
         * 
         * @param {object} state 
         * @param {object} payload {key, errors: [string]} key of the rule and array of errors
         */
        SET_ERRORS: (state, {key, errors}) => {
            if(isEmpty(errors)) Vue.delete(state.errors, key)
            else Vue.set(state.errors, key, errors)
        }
    },
    actions: {
        /**
         * set the revision
         * 
         * @param {object} context 
         * @param {object} params {key, params}
         */
        validate(context, key) {
            const errors = []
            const rulesGroup = rules[key]
            if(!rulesGroup) {
                console.warn(`no validation group for the key ${key}`)
            }
            rulesGroup.forEach(rule => {
                rule.validate(context)
                if(rule.invalid===true) {
                    errors.push(rule.message)
                }
            })
            context.commit('SET_ERRORS',{key,errors})
        }
    },
}

export default module;