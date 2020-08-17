<template>
  <div v-if="Array.isArray(node)">
      <Resource v-for="(object, index) in node" :key="index" :node="object" />
  </div>
  <div v-else class="node">
    <div v-for="(value, key) in node.metadata.source" :key="key">
      <div v-if="typeof value ==='object'">
        {{value}}
        <Resource :node="value" />
      </div>
      <div v-else>
        {{key}}: {{value}}
      </div>
    </div>
  </div>
</template>

<script>

export default {
  name: 'Resource',
  props: {
    node: {
      type: Object,
      default: {}
    ,}
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.node {
  border: dotted 2px aquamarine;
}
</style>
