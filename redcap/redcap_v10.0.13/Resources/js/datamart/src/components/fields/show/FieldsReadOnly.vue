<template>
<section>
    <header>
      <span>Source Fields List</span>
    </header>
    <main>
      <section v-if="sourceFields" class="FieldsReadOnly">
        <FieldNode v-for="(node, key) in sourceFields" :key="key" :node="node" />
      </section>
    </main>
</section>
</template>

<script>
import FieldNode from '@/components/fields/show/FieldNode'

export default {
  name: 'FieldsReadOnly',
  components: {
    FieldNode
  },
  props: {
    list: {
      type: Array,
      default: () => ([]),
    }
  },
  computed: {
    sourceFields() {
      const nodes = this.$store.getters['nodes/getSelected'](this.list)
      if(!nodes) return
      return nodes.data //skip the root element
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style>
.FieldsReadOnly label[for="id"] {
  color: #C00000;
  font-weight: bold;
}
</style>

<style scoped>
  main {
    padding: 5px;
    border: solid 1px #ccc;
  }
  header {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    background-color: #E2EAFA;
    padding: 10px 5px;
    border: solid 1px #A7C3F1;
  }
  header > span {
    font-weight: bold;
    font-size: 1.3em;
  }
</style>
