import Resource from './Resource'
import Coding from './Coding'
import {intersection} from 'lodash'

class MedicationOrder extends  Resource {

    static status_list = ['active', 'completed', 'on-hold', 'stopped']

    constructor(source={}) {
        super(source)
    }

    get date() {
        const {dateWritten} = this.source
        if(!dateWritten) return ''
        const date = new Date(dateWritten)
        return date
    }

    get dosage_instructions() {
       const {dosageInstruction:list=[]} = this.source
       const dosage_instructions = []
       list.forEach(source => {
            const dosage_instruction = new DosageInstruction(source)
            dosage_instructions.push(dosage_instruction)
       })
       return dosage_instructions
    }

    get medication_reference(){
        const {medicationReference} = this.source
        if(!medicationReference) return ''
        const {display=''} = medicationReference
        return display
    }

    get status()
    {
        return this.source.status
    }

    toString() {
        return this.medication_reference
    }
}

class DosageInstruction extends Resource{

    constructor(source) {
        super(source)
    }

    get asNeeded() {
        return this.source.asNeededBoolean
    }

    get route() {
        const source = this.source && this.source.route
        if(!source) return ''
        return new Route(source)
    }

    get text() {
        return this.source.text || ''
    }

    /* get route() {
        const route_resource = new Resource(this.source.route)
        if(route_resource) return route_resource.codings
    } */

    toString() {
        return this.text
    }

    
}

class Route extends Resource {
    constructor(source) {
        super(source)
    }

    get codings() {
        const codings = this.source.codings || []
        const list = codings.map(source => {
            return new Coding(source)
        })
        return list
    }

    get text() {
        return this.source.text || ''
    }

    toString() {
        return this.text
    }

}

export default MedicationOrder