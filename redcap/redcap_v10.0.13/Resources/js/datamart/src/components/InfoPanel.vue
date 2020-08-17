<template>
  <div class="alert" :class="_type" role="warning">
      <!-- <details> -->
        <!-- <summary><span v-html="summary"></span></summary> -->
        <span class="title" v-html="summary"></span>
        <div v-html="description"></div>
      <!-- </details> -->
  </div>
</template>


<script>
const TYPES = Object.freeze({
  primary: 'alert-primary',
  secondary: 'alert-secondary',
  success: 'alert-success',
  danger: 'alert-danger',
  warning: 'alert-warning',
  info: 'alert-info',
  light: 'alert-light',
  dark: 'alert-dark',
})

export default {
  name: 'InfoPanel',
  data: () => ({
    types: {...TYPES}
  }),
  props: {
    summary: {
      type: String,
      default: ''
    },
    description: {
      type: String,
      default: ''
    },
    type: {
      type: String,
      default: ''
    },
  },
  computed: {
    _type() {
      const type = this.types[this.type]
      if(type) return type
      return this.types.info // default to info
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.title {
  display: block;
  font-weight: bold;
}
.alert {
  border: 1px solid transparent !important;
}
.alert-warning[role="warning"] {
  color: #856404;
  background-color: #fff3cd;
  border-color: #ffeeba !important;
  padding: 10px;
}

</style>
