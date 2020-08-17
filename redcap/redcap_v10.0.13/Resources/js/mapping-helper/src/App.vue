<template>
  <section id="mapping-helper-app">
    <!-- <button @click="showModal()">default</button>
      <button @click="showModal('success')">success</button>
      <button @click="showModal('error')">error</button>
      <button @click="showModal('warning')">warning</button>
      <button @click="showModal('info')">info</button>
      <button @click="showModal('question')">question</button> -->
    <transition name="loader-fade" >
      <div class="loader-container" v-if="!data_loaded">
        <DataLoader @onLoad="onLoad" @onError="onError" :promises="getBackendDataFunctions()"/>
      </div>
      <AccessTokenProxy v-else>
        <router-view />
      </AccessTokenProxy>
    </transition>
  </section>
</template>

<script>
import Vue from 'vue'
import store from '@/store'
import router from '@/router'

import API from '@/API/plugin'
Vue.use(API)

// import SwalVue from 'swal-vue'
import SwalVue from 'swal-vue'
Vue.use(SwalVue)

/* import Modal from '@/plugins/Modal'
Vue.use(Modal, {store,router}) */

import DataLoader from '@/components/DataLoader'
import AccessTokenProxy from '@/components/AccessTokenProxy'

export default {
  name: 'app',
  data: () => ({
    data_loaded: false,
  }),
  components: {DataLoader,AccessTokenProxy},
  methods: {
    showModal(icon) {
      console.log(icon)
      const dummy_text = 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Perferendis fuga aspernatur debitis omnis, deleniti sed pariatur quibusdam cupiditate ullam in? Nisi cum aperiam, similique autem sed recusandae possimus adipisci iusto?'
      this.$swal.fire({
        icon,
        text: dummy_text.repeat(10),
        confirmButtonText: 'OK',
        showCancelButton: false,
        showConfirmButton: true,
      })
    },
    /**
     * list of async functions used to preload data from the backend
     */
    getBackendDataFunctions() {
      const promises = [
        { label: 'user info', promise: () => this.$store.dispatch('fhir_metadata/fetchMetadata') },
        { label: 'settings', promise: () => this.$store.dispatch('settings/fetch') },
        { label: 'metadata', promise: () => this.$store.dispatch('project/fetchInfo') },
        { label: 'project info', promise: () => this.$store.dispatch('user/fetchInfo') },
      ]
      return promises
    },
    onLoad() {
      this.data_loaded = true
    },
    onError() {},
  },
  store,
  router,
}
</script>

<style>
.router-link-exact-active {
  color: black;
  /* font-weight: bold; */
}
#mapping-helper-app .table thead th {
  font-weight: bold;
}
</style>
<style scoped>
#mapping-helper-app {
  /* font-family: 'Avenir', Helvetica, Arial, sans-serif; */
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  min-width: 300px;
  max-width: 786px;
  /* text-align: center; */
  color: #2c3e50;
  /* width: 80%; */
  padding: 0;
  position: relative;
}
.loader-container {
  position: absolute;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  margin-top: 20%;
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

</style>
