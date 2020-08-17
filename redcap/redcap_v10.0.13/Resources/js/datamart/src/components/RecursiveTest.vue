<template>
  <div>
    <span v-if="label" @click.stop="onClick($event)" class="label" :class="{expanded: expanded}">{{label}}</span>
    <div v-if="is_node">
        {{node.name}}
    </div>
    <div v-if="is_leaf">
        is leaf
    </div>
    <div class="content" v-show="expanded">
        <span v-if="field && field.field">
            {{field.field}} ({{field.label}})
        </span>

        <ul v-else>
            <li v-for="(child_field, label) in field" :key="label" :class="{collapsable: isNaN(label)}">
                <RecursiveTest :field="child_field" :label="(isNaN(label)) ? label : ''" />
            </li>
        </ul>
    </div>
  </div>
</template>

<script>
import {Node, Leaf} from '@/libraries/'
import RecursiveTest from './RecursiveTest'
import {Map, List, fromJS} from 'immutable'


export default {
    name: 'RecursiveTest',
    components: {RecursiveTest},
    data: () => ({
        // map_field: new Map(),
        expanded: false
    }),
    props: {
        field: {
            type: [Object, Array, Map, List],
            default: () => ({})
        },
        label: {
            type: [String, Number],
            default: ''
        },
        node: {
            type: [Node, Leaf],
            default: null
        }
    },
    created() {
        
        this.expanded = !this.label
    },
    computed: {
        is_node() {
            return this.node instanceof Node
        },
        is_leaf() {
            return this.node instanceof Leaf
        },
    },
    methods: {
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

<style>
ul {
    list-style-type: none;
}
.content  ul:first-of-type {
    /* padding: 0; */
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
</style>