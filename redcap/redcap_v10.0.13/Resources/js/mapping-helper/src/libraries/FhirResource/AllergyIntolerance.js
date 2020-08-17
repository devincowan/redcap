import Resource from './Resource'
import Coding from './Coding'

class AllergyIntolerance extends Resource {

    constructor(source={}) {
        super(source)
    }

    get date() {
        const {recordedDate} =  this.source
        if(!recordedDate) return ''
        const date = new Date(recordedDate)
        return date
    }

    get status() {
        const {status=''} =  this.source
        return status
    }

    get substance() {
        const {substance:source} =  this.source
        const substance = new Substance(source)
        return substance
    }

    get category() {
        const {category:source} = this.source
        const category = new Category(source)
        return category
    }
    
    get reactions() {
        const reactions = []
        const {reaction=[]} = this.source
        reaction.forEach(source => reactions.push(new Reaction(source)))
        return reactions
    }

    toString() {
        return this.substance
    }
}

class Category extends Resource {
    constructor(source={}) {
        super(source)
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
        const {coding=[]} = this.source || {}
        coding.forEach(params => {
            const _coding = new Coding(params)
            list.push(_coding)
        })
        return list
    }

    toString() {
        return this.text
    }

}

class Substance extends Resource {

    constructor(source={}) {
        super(source)
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
        const {coding=[]} = this.source || {}
        coding.forEach(params => {
            const _coding = new Coding(params)
            list.push(_coding)
        })
        return list
    }

    toString() {
        return this.text
    }
}



class Reaction extends Resource {

    constructor(source={}) {
        super(source)
    }

    get certainty() {
        const {certainty=''} =  this.source
        return certainty
    }

    get date() {
        const {onset:date_source} =  this.source
        if(!date_source) return ''
        const date = new Date(date_source)
        return date
    }

    get manifestations() {
        const manifestations = []
        const {manifestation:list=[]} =  this.source
        list.forEach(item => {
            const {text=''} = item
            manifestations.push(text)
        })
        return manifestations
    }

    get note() {
        const {note} =  this.source
        if(!note) return ''
        const {text} = note
        return text
    }

    toString() {
        return this.manifestations.join(', ')
    }

}


export default AllergyIntolerance