<template>
  <Modal ref="modal" @close="onClose">
      <template v-slot:header>
        <h3 v-html="title"></h3>
      </template>
      <template v-slot:body>
        <!-- can be a Vue component -->
        <template v-if="bodyComponents.length>0">
          <section v-for="(component, index) in bodyComponents" :key="`body-${index}`" :is="component" />
        </template>
        <!-- or a simple HTML element -->
        <section v-else v-html="body"/>
      </template>
      <template v-slot:footer>
        <!-- can be a Vue component -->
        <template v-if="footerComponents.length>0">
          <section v-for="(component, index) in footerComponents" :key="`footer-${index}`" :is="component" />
        </template>
        <!-- or a simple HTML element -->
        <section v-else v-html="footer"/>
      </template>
  </Modal>
</template>

<script>
import Modal from '@/components/common//Modal'

export default {
  name: 'ManagedModal',
  components: {Modal},
  computed: {
    // this is watched to trigger open/close
    open() { return this.$store.state.modal.open},
    title() { return this.$store.state.modal.title },
    body() { return this.$store.state.modal.body },
    footer() { return this.$store.state.modal.footer },
    bodyComponents() { return this.$store.state.modal.bodyComponents },
    footerComponents() { return this.$store.state.modal.footerComponents },
    callback() { return this.$store.state.modal.callback },
  },
  watch: {
    /**
     * watch open changes to open/close the modal
     */
    open(value) {
      const {modal} = this.$refs
      value ? modal.open() : modal.close()
    }
  },
  methods: {
    /**
     * reset the status whenever the modal is closed
     */
    onClose() {
      this.$store.dispatch('modal/reset')
      // if(typeof this.callback == 'function') this.callback()
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
