const Rule = class {
    /**
     * 
     * @param {object} params 
     */
    constructor({name, module, mutation, validation, message, ...params }) {
        this.name = name
        this.module = module
        this.mutation = mutation
        this.validation = validation || true
        this.message = message || `${name} is invalid`
        this.params = params
    }
    /**
     * validate the rule
     * @param {bool} checkDirty if true also check if the rule is dirty (has been touched)
     */
    validate(state) {
      let assert = this.validation(state)===true
      return assert
    }
  }

class Validator {
    rules = {}
    errors = {}

    constructor(params) {
        this.params = params
    }

    addRule(settings) {
        const rule = new Rule(settings)
        const key = `${settings.module}/${settings.mutation}`
        if(!this.rules[key]) this.rules[key] = {
          list: [],
          dirty: false
        }
        this.rules[key].list.push(rule)
    }

    validate(key, state) {
        const group = this.rules[key]
        let groupValid = true
        this.errors[key] = []
        group.list.forEach(rule => {
            const ruleValid = rule.validate(state)
            if(!ruleValid) this.errors[key].push(rule.message)
            groupValid = groupValid && ruleValid
        })

        const valid = groupValid || !group.dirty
        /**
         * mark dirty after first validation attempt
         * maybe I should instead make it dirty in watch
         */
        group.dirty = true
        return valid
    }

    getErrors(key) {
        return this.errors[key]
    }

    plugin() {
        return store => {
            this.$store = store //register the store in the validator
            store.$validator = this //register the validator in the store
            store.state.$errors = {} // init errors state
            // called when the store is initialized
            store.subscribe((mutation, state) => {
                const regex = /(?:(\w+)\/)?([\w]+)/
                const matches = mutation.type.match(regex)
                const [full, module, mutationName] = regex.exec(mutation.type)
                // console.log(state)
                if(module && !state[module].$errors) state[module].$errors = {}

                const rules = this.rules[mutation.type]

                /* if(rules) {
                    const valid = this.validate(mutation.type, state)
                    if(!valid) {
                        if(module) state[module].$errors = this.getErrors(mutation.type)
                        else state.$errors = this.getErrors(mutation.type)
                    }
                } */
                if(rules) {
                    state.$errors[mutation.type] = []
                    const valid = this.validate(mutation.type, state)
                    if(!valid) state.$errors[mutation.type] = this.getErrors(mutation.type)
                }
                if(module && state.$errors[mutation.type])state[module].$errors[mutationName] = state.$errors[mutation.type]
                // console.log(mutation, state)
                // console.log(module, state[module].$errors)
                // called after every mutation.
                // The mutation comes in the format of `{ type, payload }`.
            })
        }
    }
}

const createValidator = (params) => {
    

   
}

export default Validator