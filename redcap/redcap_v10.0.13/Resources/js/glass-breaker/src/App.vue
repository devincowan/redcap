<template>
  <div id="glass-breaker-app">
    <Dashboard />
    <!-- the debug component will only show in development -->
    <Debug v-if="debug"/>
  </div>
</template>

<script>
import Vue from 'vue'
import store from '@/store'

// form validation
import Vuelidate from 'vuelidate'
Vue.use(Vuelidate)

// import alert system
import SwalVue from 'swal-vue'
Vue.use(SwalVue)

// API
import {API_plugin} from '@/api'
Vue.use(API_plugin)

import Dashboard from '@/components/Dashboard'
import Debug from '@/components/debug/Debug'

export default {
  components: {
    Dashboard,
    Debug,
  },
  data() {
    return {
      debug: false,
    }
  },
  created() {
    this.debug = this.env=='development'
  },
  computed: {
    env: () => process.env.NODE_ENV
  },
  methods: {
    toggleDebug() {this.debug = !this.debug}
  },
  store,
}
</script>

<style>
#glass-breaker-app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}
</style>
