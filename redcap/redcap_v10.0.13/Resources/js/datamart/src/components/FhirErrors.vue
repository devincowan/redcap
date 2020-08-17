<template>
  <div v-if="has_errors" class="errors">
      <p>Warning - the following errors occurred:</p>
      <ul class="error-list text-left">
          <transition-group name="list">
              <li v-for="(group, mrn) in errors" :key="`error-${mrn}`">
              <details>
                  <summary>{{mrn}}</summary>
                  <ul class="text-left">
                  <li v-for="(error, index) in group" :key="index">
                      <p v-html="error.message"></p>
                      <p v-if="error.detail">{{error.detail.message}} <span>({{error.detail.code}})</span></p>
                  </li>
                  </ul>
              </details>
              </li>
          </transition-group>
      </ul>
  </div>
  <!-- do not show anything if no errors -->
  <div v-else />
</template>

<script>
export default {
  name: 'FhirErrors',
  data: () => ({}),
  props: {
    errors: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    has_errors() {
        return Object.keys(this.errors).length>0
    },
  },
  methods: {}
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.errors {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeeba;
    /* background-color: rgba(255,255,0, 0.3); */
    /* border: solid 1px rgba(0,0,0, 0.2); */
    border-radius: 5px;
    padding: 10px;
}
.errors ul {
    margin: 0;
}
.errors > ul {
    list-style-type: none;
    padding: 0;
}
/* transition */
.list-enter-active, .list-leave-active {
  transition: all 1s;
}
.list-enter, .list-leave-to /* .list-leave-active below version 2.1.8 */ {
  opacity: 0;
  transform: translateX(30px);
}
/* transition */
</style>
