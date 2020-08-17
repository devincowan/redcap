<template>
  <div class="fields-filter">
    <input ref="input" type=text v-model="query" placeholder="search source fields by keywords"/>
    <button @click="clear" v-if="query"><i class="far fa-times-circle"></i></button>
  </div>
</template>

<script>
import { debounce } from 'lodash'

export default {
  name: 'FieldsFilter',
  computed: {
    query: {
      get() { return this.$store.state.nodes.query },
      set: debounce(
        function (value) {
          this.setQuery(value)
      }, 300),
    }
  },
  methods: {
    clear(e) {
      e.preventDefault()
      this.setQuery('')
    },
    setQuery(text) {
      this.$store.dispatch('nodes/setQuery', text)
      this.$store.dispatch('nodes/filterNodes', text)
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
  .fields-filter {
    position: relative;
    display: inline-block;
  }
  .fields-filter input {
    min-width: 250px;
    padding: 3px;
  }
  .fields-filter button {
    position: absolute;
    right: 2px;
    top: 2px;
    bottom: 2px;
    border: none;
    background-color: transparent;
    cursor: pointer;
  }
</style>
