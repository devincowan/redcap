<template>
  <div>
      <p>{{counter}}</p>
      <button @click="onIncrementClicked">+</button>
      <button @click="onDecrementClicked">-</button>
  </div>
</template>

<script>
export default {
    name: 'CounterPage',
    computed: {
        counter() {
            return this.$store.state.counter.count
        }
    },
    methods: {
        onIncrementClicked() {
            this.$store.dispatch('counter/increment')
        },
        onDecrementClicked() {
            this.$store.dispatch('counter/decrement')
        },
    }
}
</script>

<style>

</style>