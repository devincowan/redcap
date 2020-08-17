<template>
    <div>
        <div v-if="label" class="category-container">
            <div @click.stop="toggle" class="label">
                <span>{{label}}</span>
                <span class="selected-status">{{selected_nodes.length}}/{{total_leaves}} selected</span>
            </div>
            <button @click="toggleSelectAll(all_selected)" v-text="select_all_text"/>
        </div>
        <!-- {{selected_nodes}} -->
        <div class="content" v-show="expanded">
            <div v-if="node.field">
                <label :for="node.field">{{node.field}}</label>
                <input :id="node.field" type="checkbox" v-model="checked" :disabled="locked">
            </div>
            <ul v-else>
                <li v-for="(child, key) in node" :key="key">
                    <FhirNode :intial_checked="checked" :label="(isNaN(key)) ? key : ''" class="node" :node="child" :ref="`node-${key}`"/>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
// import FhirNodeList from './FhirNodeList'

export default {
    name: 'FhirNode', // needed for recursive components
    data() {
        return {
            mounted: false,
            // checked: false,
            expanded: false,
        }
    },
    components: {
        // FhirNodeList,
    },
    mounted() {
        this.mounted = true
        if(this.locked) this.checked = true // check field if locked
        this.expanded = !this.label
    },
    props: {
        /**
         * a node can be an object or an array
         * depending on the type: object is probably a leaf
         */
        node: {
            type: [Object,Array],
        },
        label: {
            type: String,
        }
    },
    computed: {
        checked: {
            get() {
                if(this.name=='') return false
                return this.selected_fields.indexOf(this.name) >= 0
            },
            set(value) {
                if(this.name=='') return
                const fields = [...this.selected_fields]
                const index = fields.indexOf(this.name)
                this.$store.dispatch('revision/toggleField', {name:this.name, value})
                /* if(value==false) {
                    if(index < 0) return
                    fields.splice(index, 1) //remove the element
                    this.$store.dispatch('revision/setFields', fields)
                }else {
                    if(index>=0) return // do not add duplicates
                    fields.push(this.name)
                    this.$store.dispatch('revision/setFields', fields)
                } */
            },
        },
        /**
         * get checked fields from the store
         */
        selected_fields() {
            const {fields=[]} = this.$store.state.revision
            return fields
        },
        children() {
            const {children=[]} = this.node
            return children
        },
        name() {
            const {field:name=''} = this.node
            return name
        },
        child_nodes() {
            if(!this.mounted) return []
            const nodes = []
            this.$children.forEach(child => {
                const tag = this.getComponentTag(child)
                if(tag.match(/FhirNode/i)) {
                    nodes.push(child)
                }
            })
            return nodes
        },
        locked() {
            const locked_fields = ['id']
            return locked_fields.indexOf(this.name) >=0
        },
        /**
         * get total number of leaves recursively
         */
        total_leaves() {
            if(this.is_leaf) return 1
            let total = 0
            this.child_nodes.forEach(node => {
                total += node.total_leaves
            })
            return total
        },
        selected_nodes() {
            let selected = []
            if(this.name && this.checked) selected.push(this.name)
            this.child_nodes.forEach(child => {
                selected = selected.concat(child.selected_nodes)
            })
            return selected
        },
        is_leaf() {
            return this.child_nodes.length==0
        },
        all_selected() {
            const all_selected =  this.selected_nodes.length==this.total_leaves
            return all_selected
        },
        select_all_text() {
            if(this.all_selected) return 'deselect all'
            else return 'select all'
        }
    },
    watch: {
        /**
         * watch the check value 
         * and force an update of all components
         * (use with checked in data)
         */
        /* checked(value) {
            console.log(value)
            this.update()
        } */
    },
    methods: {
         /**
         * force an update of the component
         * and recursively of the parents
         */
        update() {
            this.$forceUpdate()
            if(this.$parent && typeof this.$parent.update == 'function') {
                this.$parent.update()
            }
        },
        /**
         * select all leaves contained in this node
         */
        toggleSelectAll(all_selected) {
            if(this.is_leaf) this.checked = !all_selected
            else {
                this.child_nodes.forEach(node => {
                     node.toggleSelectAll(all_selected)
                })
            }
        },
        /**
         * helper function to get the tag of a vue component
         */
        getComponentTag(component) {
            return component.$options._componentTag
        },
        /**
         * toggle visibility of children
         */
        toggle() {
            this.expanded = !this.expanded
        }
    }
}
</script>

<style scoped>
.category-container {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
}
.label {
    cursor: pointer;
}
.selected-status {
    color: #999;
    font-style: italic;
    display: block;
}
</style>