<template>
  <div>
      <p>total selected = {{total}}</p>
      <!-- {{selected}} -->
      <FhirNode ref="root" :node="fhir_fields" />
  </div>
</template>

<script>
import FhirNode from './FhirNode'
import node from './nodes'

export default {
    components: {
        FhirNode,
    },
    data: () => ({
        node: node,
        mounted: false,
    }),
    mounted() {
        this.mounted = true
    },
    computed: {
        fhir_fields() {
            const {fhir_fields=[]} = this.$store.state.settings.settings
            return fhir_fields
        },
        selected() {
            if(!this.mounted) return []
            const {selected_nodes=[]} = this.$refs.root
            return selected_nodes
        },
        total() {
            return this.selected.length
        }
    },
    watch: {
        
    }
}
</script>

<style>

</style>