<template>
<section>
    <transition name="modal">
      <div class="modal-mask" v-if="showModal" @click="onMaskClick">
        <div class="modal-wrapper">
          <div class="modal-container">

            <div class="modal-header">
              <slot name="header">Modal</slot>
              <span class="close-button" @click="close">×</span>
            </div>

            <div class="modal-body">
              <slot name="body">

              </slot>
            </div>

            <div class="modal-footer">
              <slot name="footer">

                <!-- <button class="modal-default-button" @click="close">Close</button> -->
              </slot>
            </div>
          </div>
        </div>
      </div>
    </transition>
</section>

</template>

<script>
/**
 * for dynamic components see
 * https://markus.oberlehner.net/blog/building-a-modal-dialog-with-vue-and-vuex/
 * 
 * if the body or footer components are not used and a body or footer props are provided,
 * then render the components (body and footer must be Vue components)
 */

export default {
  name: 'Modal',
  data() {
    return {
      showModal: false,
    }
  },
  props: {
    backdrop: {
      type: Boolean,
      default: false,
    },
  },
  watch: {
    /**
     * apply a class on the body when the modal is open
     */
    showModal(value) {
      const modalOpenClassName = 'modal-open'
      if(value) document.body.classList.add(modalOpenClassName)
      else document.body.classList.remove(modalOpenClassName)
    },
  },
  methods: {
    open() {
      if(!this.showModal) {
        this.showModal = true
        this.$emit('open')
      }
    },
    close() {
      if(this.showModal) {
        this.showModal = false
        this.$emit('close')
      }
    },
    toggle() {
      this.showModal ? this.close() : this.open()
    },
    /**
     * make something to close if the user clicks on the outside mask
     */
    onMaskClick(e) {
      // e.preventDefault()
      const target = e.target
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style>
body.modal-open {
  overflow: hidden;
}
</style>
<style scoped>
.modal-mask {
  position: fixed;
  z-index: 100;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, .5);
  display: table;
  transition: opacity .3s ease;
}

.modal-wrapper {
  max-width: 80vw;
  margin: 50px auto;
}

.modal-container {
  display: flex;
  flex-direction: column;
  box-sizing: content-box;
  max-height: 80vh;
  width: auto;
  max-width: 80vw;
  margin: auto;
  padding: 20px 30px;
  background-color: #fff;
  border-radius: 2px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .33);
  transition: all .3s ease;
  font-family: Helvetica, Arial, sans-serif;
}

.modal-header h3 {
  margin-top: 0;
  color: #42b983;
}
.modal-header .close-button {
  font-size: 1.3em;
  font-weight: bold;
  cursor: pointer;
}

.modal-body {
  margin: 20px 0;
  flex: 1;
  overflow: auto;
}

.modal-default-button {
  float: right;
}

/*
 * The following styles are auto-applied to elements with
 * transition="modal" when their visibility is toggled
 * by Vue.js.
 *
 * You can easily play with the modal transition by editing
 * these styles.
 */

.modal-enter {
  opacity: 0;
}

.modal-leave-active {
  opacity: 0;
}

.modal-enter .modal-container,
.modal-leave-active .modal-container {
  -webkit-transform: scale(1.1);
  transform: scale(1.1);
}
</style>
