<template>
  <div class="error-list">
    <transition name="error">
      <ul class="errors" v-if="errors.length">
        <li v-for="(error, index) in errors" :key="index"><i class="fas fa-exclamation-circle"></i> {{error}}</li>
      </ul>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'ErrorList',
  props: {
    errors: {
      type: Array,
      default: () => []
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
div {
  min-height: 1em;
  font-size: 1em;
  line-height: 1em;
}
.errors {
  /* border: solid 1px;
  background-color: #f2dede;
  border-color: #ebccd1;
  padding: 10px; */
  padding: 0;
  color: #a94442;
  border-radius: 3px;
  list-style-type: none;
  width: 100%;
  transition-property: opacity, transform;
  transition-duration: .3s;
  transition-timing-function: ease-in-out;
  margin: 0;
  /* box-shadow: 2px 2px 5px 0px grey; */
}
/* .errors li::before {
  content: '•';
  margin-right: 3px;
} */

/*
 * The following styles are auto-applied to elements with
 * transition="error" when their visibility is toggled
 * by Vue.js.
 *
 */

.error-enter {
  opacity: 0;
}

.error-leave-active {
  opacity: 0;
}
</style>
