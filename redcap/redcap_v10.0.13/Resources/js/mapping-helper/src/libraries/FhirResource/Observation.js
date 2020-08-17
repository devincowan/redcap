import Resource from './Resource'
import Coding from './Coding'
import {intersection} from 'lodash'

class Observation extends  Resource {

    static value_keys = [
        'valueQuantity',
        'valueCodeableConcept',
        'valueString',
        'valueRange',
        'valueRatio',
        'valueSampledData',
        'valueAttachment',
        'valueTime',
        'valueDateTime',
        'valuePeriod',
    ]

    constructor(source={}) {
        super(source)
    }

    get date() {
        const {effectiveDateTime='', issued=''} = this.source
        const date_string = effectiveDateTime || issued // date in social history could come from "issued"
        const date = new Date(date_string)
        return date
    }

    get display() {
        const codings = this.codings
        return codings
    }

    /**
     * return a list of ObservationValue
     */
    get values() {
        const {component:components} = this.source
        // check if the source has components
        if(components) {
            const values = components.map(component => this.getValue(component))
            return values
        }
        const value = this.getValue(this.source)
        return [value] // return as array even if single
    }

    /**
     * get the codings from the values ObservationValue[]
     */
    get codings() {
        const results = []
        const date = this.date
        this.values.forEach(value => {
            const {codings} = value
            if(codings.length==0) {
                const row = {
                    date,
                    // code:'',
                    display: value.display, // display from coding or from code.text
                    // system:'',
                    value: value.toString(),
                }
                // collect the row
                results.push(row)
            }else {
            // loop through all codings of the value
                codings.forEach(coding => {
                    // create a row
                    const {code, display, system} = coding
                    const row = {
                    date,
                    code,
                    display, // display from coding or from code.text
                    system,
                    value: value.toString(),
                    }
                    // collect the row
                    results.push(row)
                })
            }
        })
        return results
    }

    /**
     * get an instance of ObservationValue
     * @param {object} component 
     */
    getValue(component) {
        const keys = Object.keys(component)
        const found = intersection(keys, Observation.value_keys)
        const key = found[0] || ''
        const value = new ObservationValue(key, component)
        return value
    }
}

class ObservationValue extends Resource{

    constructor(type, source) {
        super(source)
        this.type = type
    }

    get value() {
        if(!this.type) return ''
        return this.source[this.type]
    }
    

    toString() {
        return this.text_value
    }

    /**
     * get the display.
     * could come from the coding or from text
     */
    get display() {
        const { code: {text=''} } = this.source
        const texts = this.codings.map(coding => {
            const {display=''} = coding
            return display
        })
        if(texts && texts.length>0) return texts[0]
        return text
    }

    get text_value()
    {
        const value_key = this.type
        if(!value_key) return ''

        var text = ''
        const value_data = this.value
        switch (value_key) {
            case 'valueQuantity':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#quantity
                 * 
                 * "value" : <decimal>, // Numerical value (with implicit precision)
                 * "comparator" : "<code>", // < | <= | >= | > - how to understand the value
                 * "unit" : "<string>", // Unit representation
                 * "system" : "<uri>", // C? System that defines coded unit form
                 * "code" : "<code>" // Coded form of the unit
                 */
                text = value_data.value
                break
            case 'valueCodeableConcept':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#codeableconcept
                 * 
                 * "coding" : [{ Coding }], // Code defined by a terminology system
                 * "text" : "<string>" // Plain text representation of the concept
                 */
                text = value_data.text
                break
            case 'valueRange': {

                /**
                 * https://www.hl7.org/fhir/datatypes.html#range
                 * 
                 * "low" : { Quantity(SimpleQuantity) }, // Low limit
                 * "high" : { Quantity(SimpleQuantity) } // High limit
                 */
                const range = []
                const {low='', high=''} = value_data
                if(low) range.push(low)
                if(high) range.push(high)
                text = range.join(', ')
                break
            }
            case 'valueRatio':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#ratio
                 * "numerator" : { Quantity }, // Numerator value
                 *  "denominator" : { Quantity } // Denominator value
                 */
                try {
                    // watch out with divide by zero!
                    text = (value_data.numerator)/(value_data.denominator)
                } catch (error) {
                    text = error
                }
                break
            case 'valueSampledData':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#sampleddata
                 * 
                 * "origin" : { Quantity(SimpleQuantity) }, // R!  Zero value and units
                 * "period" : <decimal>, // R!  Number of milliseconds between samples
                 * "factor" : <decimal>, // Multiply data by this before adding to origin
                 * "lowerLimit" : <decimal>, // Lower limit of detection
                 * "upperLimit" : <decimal>, // Upper limit of detection
                 * "dimensions" : "<positiveInt>", // R!  Number of sample points at each time point\
                 * "data" : "<string>" // Decimal values with spaces, or "E" | "U" | "L"
                */
                text = value_data.data
                break
            case 'valueAttachment':
                /**
                 * https://www.hl7.org/fhir/datatypes.html#attachment
                 * 
                 * // from Element: extension
                 * "contentType" : "<code>", // Mime type of the content, with charset etc.
                 * "language" : "<code>", // Human language of the content (BCP-47)
                 * "data" : "<base64Binary>", // Data inline, base64ed
                 * "url" : "<url>", // Uri where the data can be found
                 * "size" : "<unsignedInt>", // Number of bytes of content (if url provided)
                 * "hash" : "<base64Binary>", // Hash of the data (sha-1, base64ed)
                 * "title" : "<string>", // Label to display in place of the data
                 * "creation" : "<dateTime>" // Date attachment was first created
                 */
                text = value_data.title
                break
            case 'valuePeriod': {

                /**
                 * https://www.hl7.org/fhir/datatypes.html#period
                 * 
                 * "start" : "<dateTime>", // C? Starting time with inclusive boundary
                 * "end" : "<dateTime>" // C? End time with inclusive boundary, if not ongoing
                 */
                const {start, end} = value_data
                text = `from ${start} to ${end}`
                break
            }
            /**
             * https://www.hl7.org/fhir/datatypes.html#time
             * https://www.hl7.org/fhir/datatypes.html#dateTime
             * https://www.hl7.org/fhir/datatypes.html#string
             */
            case 'valueTime':
            case 'valueDateTime':
            case 'valueString': {
                text = value_data
                break
            }
            default: {

                // get all data and store it as text
                const properties = []
                for(let key in value_data) {
                    properties.push(`${key}: ${value_data[key]}`)
                }
                text = properties.join(', ')
                break
            }
        }
        return text
    }

    get codings() {
        const { coding:codings=[]} = this.source && this.source.code || {}
        const list = codings.map(coding => new Coding(coding))
        return list
    }
}

export default Observation