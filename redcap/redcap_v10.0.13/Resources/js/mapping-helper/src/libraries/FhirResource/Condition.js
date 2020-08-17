import Resource from './Resource'
import Coding from './Coding'
import {intersection} from 'lodash'

class Condition extends Resource {

    constructor(source={}) {
        super(source)
    }

    get date() {
        const {onsetDateTime} =  this.source
        if(!onsetDateTime) return ''
        const date = new Date(onsetDateTime)
        return date
    }

    get status() {
        const {clinicalStatus=''} =  this.source
        return clinicalStatus
    }

    get text() {
        const {text=''} =  this.source
        return text
    }

    /**
     * get the codings from the values ObservationValue[]
     */
    get codings() {
        const list = []
        const {coding=[]} = this.source.code || {}
        coding.forEach(params => {
            const _coding = new Coding(params)
            list.push(_coding)
        })
        return list
    }

    get grouped_codings() {
        const codings = this.codings
        const date = this.date
        const status = this.status
        const text = this.text

        const list = []
        codings.forEach((coding, index) => {
            const row = {
                date,
                status,
                display: coding.display || text,
                system: coding.system,
                code: coding.code,
            }
            list.push(row)
        })
        return list
    }

    toString() {
        return this.text
    }
}



export default Condition