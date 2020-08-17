<template>
    <div>
        <input type="text" v-model="query">
        {{query}}
        {{fhir_node.filtered_leaves}}
        <div>Total fields selected: {{fhir_node.total_active_leaves}}</div>

        <FhirNode v-if="fhir_node" :node="fhir_node" :filter="my_query" @update="onUpdate"/>

    </div>
</template>

<script>
import {Node, Leaf} from '@/libraries/'
import FhirNode from './FhirNode'
import { debounce } from 'lodash'

export default {
    name: 'FhirNodeContainer',
    components: {
        FhirNode,
    },
    data: () => ({
        my_query: '',
        node: null,
    }),
    computed: {
        query: {
            get() { return this.my_query},
            set: debounce( function (value) {
                this.my_query = value
            }, 300),
        },
        fhir_node() {
            const {settings: {fhir_fields={}}} = this.$store.state.settings
            const node = new Node(this.fhir_fields)
            node.addObserver(this)
            return node
        },
        fhir_fields() {
            try {
                const {settings: {fhir_fields}} = this.$store.state.settings
                return fhir_fields
            } catch (error) {
                return null
            }
        }
    },
    methods: {
        onUpdate() {
            this.update()
        },
        update() {
            this.$forceUpdate()
        }
    }
}
</script>

<style scoped>

</style>