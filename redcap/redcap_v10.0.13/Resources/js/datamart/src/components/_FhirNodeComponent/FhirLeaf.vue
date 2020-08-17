<template>
    <div>
        <span >
            <input type="checkbox" :id="field" :value="field"  @click="onChecked($event)" :checked="active">
            <label class="ml-2" :for="field">{{field}} ({{label}})</label>
        </span>
    </div>
</template>

<script>
import {Leaf} from '@/libraries/'



export default {
    name: 'FhirLeaf',
    data: () =>({
        active:false,
    }),
    props: {
        field: {
            type: String,
            default: ''
        },
        temporal:{
            type: [Boolean,Number],
            default: false,
        },
        label: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        category: {
            type: String,
            default: ''
        },
        subcategory: {
            type: String,
            default: ''
        },
        identifier:{
            type: [Boolean, Number],
            default: false,
        },
        node: {
            type: [Leaf],
            default: null
        },
    },
    created() {
        // console.log('created', this.$options.name, this.node)
        this.node.addObserver(this)
    },
    destroyed() {
        // console.log('destroyed', this.$options.name, this.node)
        this.node.removeObserver(this)
    },
    computed: {},
    methods: {
        onChecked(event) {
            this.node.active = event.target.checked
            this.update()
        },
        /**
         * fired when a change in a node is notified (see ObserverMixin)
         */
        update() {
            this.$forceUpdate()
            this.$emit('update')
        },
    }
}
</script>

<style scoped>

</style>