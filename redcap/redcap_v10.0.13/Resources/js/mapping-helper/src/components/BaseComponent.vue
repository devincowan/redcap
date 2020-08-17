<template>
  <div id="base">
    <span>{{msg}}</span>
    {{test}}
    <!-- <async-webpack-example /> -->
    <!-- <test :level="3">asdfdsafdsaf</test> -->
  </div>
</template>

<script>
import Vue from 'vue'

/* Vue.component(
  'async-webpack-example',
  // The `import` function returns a Promise.
  () => import('@/components/BaseComponent')
) */
/* Vue.component('test', {

    // The `import` function returns a Promise.
  render: (createElement) => {
    return createElement(
      'h' + this.level,   // tag name
      this.$slots.default // array of children
    )
  },
  props: {
    level: {
      type: Number,
      required: true
    }
  }
}) */

export default {
  name: 'base-component',
  props: {
    msg: {
      type: String,
      default: 'hello'
    }
  },
  computed: {
    test() {
      return this.$store.state.counter.count
    }
  }
}
</script>

<style scoped>
</style>
