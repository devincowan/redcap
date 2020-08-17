import {Resource, Observation, Condition, MedicationOrder, AllergyIntolerance} from './index'

class Bundle extends  Resource {

    constructor(source={}) {
        super(source)
    }

    get entries() {
        const {entry:entries=[]} = this.source
        const results = []
        if(!entries) return results
        
        entries.forEach(entry => {
            const {resource, resource:{resourceType}} = entry
            switch (resourceType) {
                case 'Observation': {
                    const observation = new Observation(resource)
                    results.push(observation)
                    break;
                }
                case 'MedicationOrder': {
                    const medication_order = new MedicationOrder(resource)
                    results.push(medication_order)
                    break;
                }
                case 'Condition': {
                    const condition = new Condition(resource)
                    results.push(condition)
                    break;
                }
                case 'AllergyIntolerance': {
                    const allergy_intolerance = new AllergyIntolerance(resource)
                    results.push(allergy_intolerance)
                    break;
                }
                default: {
                    const generic = new Resource(resource)
                    results.push(generic)
                    break;
                }
            }
            
        })
        return results
    }

    /**
     * return a list of entries filtered by class
     * 
     * @param {class} type_class 
     */
    getEntriesOfType(class_name) {
        const entries = this.entries.filter( entry => {
            return entry.constructor.name == class_name
        })
        return entries
    }

}

export default Bundle