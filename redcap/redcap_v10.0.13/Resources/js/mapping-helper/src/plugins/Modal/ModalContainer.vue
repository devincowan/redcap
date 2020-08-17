<template>
  <div data-bmodal>
    <Modal
      v-bind="modal"
      @close="onClose"
      @hidden="onHidden"
      @cancel="onCancelClicked"
      @confirm="onConfirmClicked"
      >
      <template v-slot:header>
        <span v-if="modal.header!=null">{{modal.header}}</span>
      </template>
      <template v-slot:body>
          <template  v-if="modal.component">
            <component :is="modal.component" v-bind="modal.component_properties" />
          </template>
          <span>{{modal.body}}</span>
      </template>
      <template v-slot:footer>
        <div v-if="modal.footer!=null">{{modal.footer}}</div>
      </template>
    </Modal>

    <!-- <button @click="showModal">shoModal</button> -->
    <!-- <button @click="setComponent">component</button> -->
  </div>
</template>

<script>
import Modal, {types} from './Modal'

const modal_default = {
    show: false,
    prevent_closing: false,
    header: null,
    body: null,
    component: null,
    component_properties: {},
    footer: null,
    onConfirm: null,
    onCancel: null,
    cancel_text: 'Cancel',
    confirm_text: 'Ok',
    show_cancel_button: true,
    show_ok_button: true,
    type: types.default,
}

export default {
  name: 'ModalContainer',
  components: {Modal},
  data: () => ({
    modal: {...modal_default}
  }),
  methods: {
    reset() {
      for(let [key, value] of Object.entries(modal_default)) {
        this.modal[key] = value
      } 
      this.$emit('reset')
    },
    /**
     * set the component properties and show the dialog
     */
    fire(config) {
        for(let [key, value] of Object.entries(config)) {
          if(!modal_default.hasOwnProperty(key)) continue
          this.modal[key] = value
        }
        this.show()
    },
    error(config) {
        const default_config = {
            type: types.error,
            footer: '',
        }
        const merged_config = {...config, ...default_config}
        this.fire(merged_config)
    },
    show() {
      this.modal.show = true
    },
    hide() {
      this.modal.hide = false
    },
    onClose() {
      this.modal.show = false
    },
    onHidden() {
      // reset the modal after it is hidden
      this.reset()
    },
    onCancelClicked() {
      if(typeof this.modal.onCancel == 'function') this.modal.onCancel()
    },
    onConfirmClicked() {
      if(typeof this.modal.onConfirm == 'function') this.modal.onConfirm()
    },
  }
}
</script>

<style scoped>
</style>
