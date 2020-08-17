<template>
  <section id="data-mart-app">
    <!-- <button type="button" @click="goToReviewProjectPage">goToReviewProjectPage</button> -->
    <transition name="loader-fade">
      <div class="loader-container" v-if="!data_loaded">
        <DataLoader class="data-loader" @onLoad="onLoad" @onError="onError" />
      </div>
      <div v-else>
        <router-view />
      </div>
    </transition>
  </section>
</template>

<script>
import Vue from 'vue'

import store from '@/store'
import router from '@/router'

// style animations
import 'animate.css'

// import alert system
import SwalVue from 'swal-vue'
// import SwalVue from '@/assets/swal-vue/src/index.js'
Vue.use(SwalVue)

// set the global API object
import API_Plugin from '@/plugins/API'
Vue.use(API_Plugin)

import DataLoader from '@/components/DataLoader'

import {Node} from '@/libraries/'

export default {
  name: 'app',
  components: {
    DataLoader,
  },
  store: store,
  router: router,
  data: () => ({
    data_loaded: false,
  }),
  created() {
    // this.loadData()
  },
  methods: {
    /**
     * dispatch actions once data is loaded by the loader
     */
    async onLoad(data) {
      this.data_loaded = true
      // emit load event so REDCap can change route if needed (see blade views)
       this.$emit('load')
    },
    onError(error) {
      this.message = error
    },
    /**
     * validate the settings before submitting a revision
     */
    async validate() {
      const valid = await this.$store.dispatch('revision/validate')
      return Boolean(valid)
    },
    /**
     * exposed method for routing
     */
    goToCreateProjectPage() {
      const {name:route_name} = this.$route
      if(route_name=='create-project') return
      this.$router.push({name:'create-project'})
    },
    /**
     * exposed method for routing
     */
    goToReviewProjectPage() {
      const {name:route_name} = this.$route
      if(route_name=='review-project') return
      this.$router.push({name:'review-project'})
    },
  }
}
</script>

<style scoped>
#data-mart-app {
  /* font-family: 'Avenir', Helvetica, Arial, sans-serif; */
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  /* color: #2c3e50; */
  /* height: 100%; */
  min-height: 300px;
  min-width: 300px;
  max-width: 786px;
  position: relative;
  /* display: contents; */
  /* margin: 20px auto; */
}
/* transition */
.loader-fade-enter,
.loader-fade-leave-active {
  opacity: 0;
}
.loader-fade-enter-active,
.loader-fade-leave-active {
  transition: opacity .3s ease-out;
}
.loader-container {
  position: absolute;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  flex-direction: column;
  text-align: center;
}
.data-loader {
  margin: 0 auto;
}
/* .fade-enter-active, .fade-leave-active {
  transition: opacity 500ms;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
} */

</style>

<style>
/* global styles */
#data-mart-app summary {
  display: block;
}
/* make sure the .swal2-container will be behind
the bootstrap modal with the autologout message */
.swal2-container {
  z-index: 1;
}
/* .swal2-container.swal2-center.swal2-backdrop-show { */
</style>
