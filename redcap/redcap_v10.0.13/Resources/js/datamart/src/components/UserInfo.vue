<template>
<div>
  <h1>User Info</h1>
  <button @click="SweetAlertTest">go</button>
</div>
</template>

<script>
import Vue from 'vue'
import LoadingIndicator from '@/components/common/LoadingIndicator'
export default {
  name: 'UserInfo',
  components: {
    LoadingIndicator,
  },
  data: () => ({
    html:'',
    component: null
  }),
  mounted() {
    this.html = this.$el.innerHTML
    /* this.$el.innerHTML = ''
    setTimeout(() => {
      this.SweetAlertTest()
    }, 3000)
    setTimeout(() => {
      this.SweetAlertTest()
    }, 10000) */
  },
  methods: {
    async SweetAlertTest() {
      // https://stackoverflow.com/a/48684144/1875109

      // extend the component you want to display in the alert
      const alertComponent = Vue.extend(LoadingIndicator)

      // create an alert and save a reference to the promise
      const tst = this.$swal.fire({
        icon: 'info',
        title: 'approving',
        // html: '', // the html is created later with $mount
        allowOutsideClick: true,
        allowEscapeKey: true,
        showConfirmButton: true,
      })

      // create an instance of the component to show in the alert
      const instance = new alertComponent({
        propsData: {value: 50, total:100},
        store: this.$store, //pass along the store if you want
        created() {
          console.log(this.$store)
        }
      })

      // create a mount point in the alert content node
      const container = document.createElement('div')
      this.$swal.getContent().appendChild(container)

      // mount the instance in the alert content element
      instance.$mount(container)

      // await for the promise to resolve
      try {
        const res = await tst
        console.log(res)
      } catch (error) {
        console.log(error)
      }
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
