<template>
  <div v-if="node" class="fhir-node" :class="{root: is_root, node: is_node, leaf: is_leaf}">
      {{_uid}}
    <div v-if="is_node" class="header">
        <span @click.stop="onClick($event)" class="label" :class="{expanded: expanded}">{{node.name}}</span>
        <span class="selected-leaves" >{{node.total_active_leaves}}/{{node.total_leaves}} | {{node.total_visible_leaves}}selected</span>
        <button class="btn btn-success" :class="{'all-selected': (node.total_active_leaves===node.total_leaves)}" @click="onToggleSelectAll">{{node.total_active_leaves===node.total_leaves ? 'deselect' : 'select'}} all</button>
    </div>

    <div class="content" v-if="expanded" >
        <span v-if="is_leaf">
            <FhirLeaf :node="node" />
        </span>

        <ul v-else>
            <li v-for="(child_node, index) in node.filtered_children" :key="index" :class="{collapsable: isNode(child_node)}">
                <FhirNode :node="child_node" />
            </li>
        </ul>
    </div>
  </div>
</template>

<script>
import {Node, Leaf} from '@/libraries/'
import FhirNode from './FhirNode'
import FhirLeaf from './FhirLeaf'

export default {
    name: 'FhirNode',
    components: {
        FhirNode,
        FhirLeaf,
    },
    data: () => ({
        expanded: false
    }),
    props: {
        filter: {
            type: String,
            default: null
        },
        node: {
            type: [Node, Leaf],
            default: null
        },
    },
    created() {
        // console.log('created', this.$options.name, this.node)
        this.expanded = this.is_leaf || (this.is_node && this.is_root)
        this.node.addObserver(this)
    },
    destroyed() {
        // console.log('destroyed', this.$options.name, this.node)
        this.node.removeObserver(this)
    },
    computed: {
        is_node() {
            return this.node instanceof Node
        },
        is_leaf() {
            return this.node instanceof Leaf
        },
        is_root() {
            return this.node.is_root
        },
    },
    watch: {
        filter() {
            if(this.is_node) this.node.filter(this.filter)
        }
    },
    methods: {
        onChecked(event) {
            this.node.active = event.target.checked
        },
        onToggleSelectAll() {
            if(this.node.total_leaves > this.node.total_active_leaves) this.node.setAllActive()
            else this.node.setAllInactive()
        },
        /**
         * fired when a change in a node is notified (see ObserverMixin)
         */
        update() {
            this.$forceUpdate()
            if(this.$parent.$options._componentTag.match(/FhirNode/i)) {
                this.$parent.update()
            }
        },
        isNode(node) {
            return node instanceof Node
        },
        isLeaf(node) {
            return node instanceof Leaf
        },
        expand() {
            this.expanded = true
        },
        collapse() {
            this.expanded = false
        },
        onClick() {
            this.expanded = !this.expanded
        }
    }
}
</script>

<style scoped>
.root > .header {
    background-color: red;
    display: none;
}
.root > .content > ul {
    padding: 0;
}
ul {
    list-style-type: none;
}
.label {
    cursor: pointer;
}
.collapsable .label:before {
    display: inline-block;
    margin-right: 5px;
    width: 5px;
    content: '+'
}
.collapsable .label.expanded:before {
    content: '-'
}

.fhir-node.node {
    border-bottom: solid 1px #cacaca;
    padding: 10px 0;
}
.header {
    position: relative;
}
.header .label {
    font-weight: bold;
}
.header .selected-leaves {
    color: #cacaca;
    font-style: italic;
    display: block;
}

.header button {
    position: absolute;
    right: 0;
    top: 0;
    transition-property: background-color, color, border-color;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
    background-color: rgb(255,255,255); 
    color: rgb(100,100,100);
    border-color: rgb(100,100,100);
}
.header button.all-selected {
    background-color: #28a745;
    border-color: #28a745;
    color: rgb(255,255,255);
}

.content .hidden {
    display: none;
}
</style>