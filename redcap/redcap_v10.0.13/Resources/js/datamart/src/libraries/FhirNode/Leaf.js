import Node from './Node'
import ObserverMixin from './ObserverMixin'

export default class Leaf {
    parent = null
    _active = false
    _hidden = false

    constructor({field='',temporal=false,label='',description='',category='',subcategory='',identifier=false}, parent=null) {
        this.parent = parent
        this._active = false
         
        Object.assign(this, {field,temporal,label,description,category,subcategory,identifier})
        
        // add the observer mixin
        Object.assign(this, ObserverMixin())
        if(parent!=null) this.addObserver(parent) //register the parent as observer
    }

    get hidden() {
        return this._hidden
    }

    set hidden(value) {
        this._hidden = value
        this.notifyObservers(value)
    }

    get active () {
        // console.log('getting')
        return this._active
    }

    set active(value) {
        // console.log('setting')
        // Vue.set(self, '_active', value )
        console.log(this.observers)
        this._active = value
        this.notifyObservers(value)
    }

    get parent() {
        return this._parent
    }

    set parent(value) {
        if(!(value instanceof Node)) throw new Error('Parent must be instance of Node')
        this._parent = value
    }
}