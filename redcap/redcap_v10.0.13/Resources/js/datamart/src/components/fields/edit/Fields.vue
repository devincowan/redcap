<template>
  <section v-if="sourceFields" class="sourceFields">
    <header>
      <span>Source Fields List</span>
      <section>
        <span>Filter:</span>
        <FieldsFilter />
      </section>
    </header>
    <main>
      <FieldNodeCheckbox v-for="(node, key) in sourceFields" :key="key" :node="node" />
      <section v-if="noResults">sorry no results</section>
    </main>
  </section>
</template>

<script>
import FieldsFilter from '@/components/fields/edit/FieldsFilter'
import FieldNodeCheckbox from '@/components/fields/edit/FieldNodeCheckbox'

export default {
  name: 'Fields',
  components: {
    FieldsFilter,
    FieldNodeCheckbox
  },
  computed: {
    sourceFields() {
      const nodes = this.$store.state.nodes.visibleNodes
      if(!nodes) return
      return nodes.data //skip the root element
    },
    noResults() {
      const query = this.$store.state.nodes.query
      const nodesIsEmpty = Object.keys(this.sourceFields).length==0
      return query!='' && nodesIsEmpty
      }
  },
}
</script>

<style>
  .sourceFields main label[for="id"] {
    color: #C00000;
    font-weight: bold;
  }
</style>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
  .sourceFields main {
    padding: 5px;
    /* max-height: 300px; */
    overflow-y: auto;
    border: solid 1px #ccc;
    background-color: white;
    max-height: 500px;
  }
  .sourceFields header {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    background-color: #E2EAFA;
    padding: 10px 5px;
    border: solid 1px #A7C3F1;
  }
  .sourceFields header > span {
    font-weight: bold;
    font-size: 1.3em;
  }
</style>
