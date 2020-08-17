import Leaf from './Leaf'
import ObserverMixin from './ObserverMixin'

export default class Node {
    children = []
    name = ''
    parent = null
    _filtered = []
    
    constructor(params, parent=null, name='') {
        // add the observer mixin
        Object.assign(this, ObserverMixin())

        this.name = name
        this.parent = parent
        for(let key in params) {
            const child = params[key]
            if(typeof child!=='object') continue
            if(child.field) this.children.push(new Leaf(child, this))
            else this.children.push(new Node(child, this, key))
        }
        if(parent!=null) this.addObserver(parent) //register the parent as observer
    }

    /**
     * do something when notified
     * @param {Mixed} params 
     */
    update(params=null) {
        this.notifyObservers(params)
    }

    filter(text) {
        const reg_exp = new RegExp(text, 'i')
        for(let child of this.children) {
            if(child instanceof Node) {
                child.filter(text)
                continue
            }
            if(text.trim()=='') {
                child.hidden = false
                continue
            }
            const searchable_keys = [
                'field',
                'label',
                'description',
                // 'category',
                // 'subcategory',
            ]
            const found = searchable_keys.some(key => {
                return child[key].match(reg_exp)
            })
            child.hidden = !found
        }
        this.notifyObservers(text)
    }


    get filtered_children() {
        this._filtered = []
        this.children.forEach(child => {
            if(
                (child instanceof Leaf && !child.hidden) ||
                (child instanceof Node && child.total_visible_leaves>0)
            ) this._filtered.push(child)
        })
        return this._filtered
    }

    get is_root() {
        return this.parent===null
    }

    get leaves() {
        let leaves = []
        for(let child of this.children) {
            if(child instanceof Leaf) leaves.push(child)
            else if(child instanceof Node) leaves = leaves.concat(child.leaves)
        }
        return leaves
    }

    get total_leaves() {
        return this.leaves.length
    }

    get active_leaves() {
        const leaves = this.leaves.filter(leaf=>leaf.active===true)
        return leaves
    }

    get visible_leaves() {
        const leaves = this.leaves.filter(leaf=>leaf.hidden===false)
        return leaves
    }

    get total_visible_leaves() {
        return this.visible_leaves.length
    }

    get total_active_leaves() {
        return this.active_leaves.length
    }
    
    setAllActive() {
        this.visible_leaves.forEach(leaf=>leaf.active=true)
    }

    setAllInactive() {
        this.visible_leaves.forEach(leaf=>leaf.active=false)
    }
}