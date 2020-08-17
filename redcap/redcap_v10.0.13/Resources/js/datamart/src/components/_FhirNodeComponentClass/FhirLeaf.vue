<template>
    <div>
        <span >
            <input type="checkbox" :id="node.field" :value="node.field"  @click="onChecked($event)" :checked="node.active">
            <label class="ml-2" :for="node.field">{{node.field}} ({{node.label}})</label>
        </span>
    </div>
</template>

<script>
import {Leaf} from '@/libraries/'



export default {
    name: 'FhirLeaf',
    props: {
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
            if(this.$parent.$options._componentTag.match(/FhirNode/i)) {
                this.$parent.update()
            }
        },
    }
}
</script>

<style scoped>

</style>