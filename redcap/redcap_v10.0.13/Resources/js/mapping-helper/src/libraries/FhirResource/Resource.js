import {search} from 'jmespath'

class Resource {
    constructor(source={}) {
        this.source = source
    }

    /**
     * expose the search method from jmesPath
     * @param  {...any} args 
     */
    search(...args) {
        return search(...args)
    }
}

export default Resource