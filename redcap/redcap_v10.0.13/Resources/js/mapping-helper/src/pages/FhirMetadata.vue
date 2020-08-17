<template>
  <div >
    <FhirMetadataNode :metadata="fhir_metadata" />
  </div>
</template>

<script>
import Vue from 'vue'
import FhirMetadataNode from '@/components/FhirMetadataNode'

export default {
  name: 'FhirMetadataPage',
  components: {
    FhirMetadataNode,
  },
  created() {
    // this.$store.dispatch('fhir_metadata/fetchFields')
  },
  computed: {
    fhir_metadata() {
      const {fields} = this.$store.state.fhir_metadata
      return fields
    }
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.fhir-form {
  margin-bottom: 10px;
}
</style>