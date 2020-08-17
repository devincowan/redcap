<template>
  <div class="json-error" v-if="errors.length>0">
    <div v-if="error">
      <p class="error-title" v-html="error.title" />
      <p class="error-detail" v-html="error.detail" />
    </div>
    <ul v-else>
      <li v-for="(error, index) in errors" :key="index">
        <p class="error-title" v-html="error.title" />
        <p class="error-detail" v-html="error.detail" />
      </li>
    </ul>
  </div>
</template>

<script>

export default {
  name: 'JsonError',
  props: {
    /**
     * a list of errors with these properties:
     * {title, detail, status, source: {pointer} }
     */
    errors: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    /**
     * in case of single error
     * with multiple error use a list
     */
    error() {
      if(this.errors.length===1) return this.errors[0]
      return false
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.json-error,
.json-error .error-title,
.json-error .error-detail {
  font-size: 18px;
  font-weight: 300;
  text-align: center;
}
</style>
