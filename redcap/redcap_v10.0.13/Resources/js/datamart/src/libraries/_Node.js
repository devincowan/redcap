export default class Node {
    parent = '' //name of the parent
    name = '' // name of the node
    data = {} // children
    attributes = {
        field: "", // field name
        temporal: false, // is a field with dates
        label: '', // label
        description: '', // desciption
        category: '', // applies to leaves
        subcategory: '', // applies to leaves
        identifier: false // is an identifier type field
    } //atttributes
    metadata = {total: 0, subtotal: 0, fieldsTotal: 0, fieldsSubtotal: 0}
    isContainer = false

    constructor({
                parent='',
                name='',
                data={},
                attributes={},
                metadata={total:0, subtotal:0, fieldsTotal:0, fieldsSubtotal:0},
                isContainer=false}) {
        this.parent = parent
        this.name = name
        this.data = data
        this.attributes = attributes
        this.metadata = metadata
        this.isContainer = isContainer
    }

    filterByText({text, parent=null}) {
        const { data, name, attributes: {label}, isContainer } = this
        if(isContainer) {
            // loop through the chidlren of the node recursively
            for(let [key, child_data] of Object.entries(data)) {
                const child = new Node(child_data)
                return child.filterByText({text, parent:this})
            }
            // remove the node if has no more children after the recursive loop
            if(parent && Object.keys(data).length==0) {
                delete parent.data[name]
            }
        }
        if(label) {
            // check if the label of the node matches the query text
            const regex = new RegExp(`${text}`,'i')
            if ( !label.match(regex) &&!name.match(regex) ) {
                // remove the element from the parent
                delete parent.data[name]
            }
        }
        return this
    }

    filterByList({list, parent=null}) {
        const { data, name, attributes: {label}, isContainer } = this

        if(isContainer) {
            // loop through the chidlren of the node recursively
            for(let [key, child_data] of Object.entries(data)) {
                const child = new Node(child_data)
                return child.filterByList({list, parent:this})
            }
            // remove the node if has no more children after the recursive loop
            if(parent && Object.keys(data).length==0) {
                delete parent.data[name]
            }
        }
        if(label && list.indexOf(name)<0) {
            delete parent.data[name]
        }
        return this
    }

     /**
     * recursively get the fields of a node
     * @param {object} node 
     */
    getLeaves() {
        let fields = []
        if(this.isContainer) {
            for(let [key, child_data] of Object.entries(this.data)) {
                const child = new Node(child_data)
                const childFields = child.getLeaves()
                fields = fields.concat(childFields)
            }
        }
        else fields.push(this.name)
        return fields
    }
}