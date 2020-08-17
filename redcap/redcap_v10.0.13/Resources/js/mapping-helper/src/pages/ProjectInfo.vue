<template>
  <div >
    <h3>Project Info</h3>
    <div>
      <h3>info</h3>
      <JsonObject :value="info" />
      <h3>datamart_revision</h3>
      <JsonObject :value="datamart_revision" />
      <h3>cdp_mapping</h3>
      <JsonObject :value="cdp_mapping" />
    </div>
  </div>
</template>

<script>
import Vue from 'vue'
import JsonObject from '@/components/JsonObject'

export default {
  name: 'ProjectInfoPage',
  components: {
    JsonObject,
  },
  computed: {
    info() {
      const {info} = this.$store.state.project
      return info
    },
    datamart_revision() {
      const {datamart_revision} = this.$store.state.project
      return datamart_revision
    },
    cdp_mapping() {
      const {cdp_mapping} = this.$store.state.project
      return cdp_mapping
    }
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>