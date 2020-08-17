<template>
  <transition name="modal-fade" @after-leave="afterLeave">
    <div class="modal-backdrop" v-show="show" @click.self="onBackdropClicked">
      <div class="vue-modal">
        <header class="vue-modal-header">
          <div class="header-wrapper">
            <slot name="header"/>
          </div>
          <button v-if="!prevent_closing" type="button" class="btn-close" @click.self="onHeaderClose" >×</button>
        </header>

        <!-- add icon for modal type -->
        <div class="icons">
          <div v-if="type==types.success">
            <i class="far fa-check-circle" style="color:#a5dc86"></i>
          </div>
          <div v-if="type==types.error" style="color:#f27474">
            <i class="far fa-times-circle"></i>
          </div>
          <div v-if="type==types.warning" style="color:#f8bb86">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div v-if="type==types.info" style="color:#3fc3ee">
            <i class="fas fa-info-circle"></i>
          </div>
          <div v-if="type==types.question" style="color:#87adbd">
            <i class="fas fa-question-circle"></i>
            <!-- <div class="icon">
              <span>?</span>
            </div> -->
          </div>
        </div>
          <!-- end icons -->
        <section class="vue-modal-body">
          <slot name="body"></slot>
        </section>
        <footer class="vue-modal-footer">
            <slot name="footer">
              <button v-if="show_cancel_button" type="button" class="btn btn-secondary mr-2" @click.self="onCancel">{{cancel_text}}</button>
              <button v-if="show_ok_button" type="button" class="btn btn-primary" @click.self="onConfirm">{{confirm_text}}</button>
            </slot>
        </footer>
      </div>
    </div>
  </transition>
</template>

<script>
const types = Object.freeze({
  default: 'default',
  success: 'success',
  error: 'error',
  warning: 'warning',
  info: 'info',
  question: 'question',
})
export {types}

export default {
  name: 'Modal',
  data: () => ({
    types,
  }),
  props: {
    show: {
      type: Boolean,
      default: false
    },
    showFooter: {
      type: Boolean,
      default: true
    },
    prevent_closing: {
      type: Boolean,
      default: false
    },
    confirm_text: {
      type: String,
      default: 'Ok'
    },
    cancel_text: {
      type: String,
      default: 'Cancel'
    },
    show_cancel_button: {
      type: Boolean,
      default: true
    },
    show_ok_button: {
      type: Boolean,
      default: true
    },
    type: {
      type: String,
      default: types.default
    }
  },
  methods: {
    onBackdropClicked() {
      if(this.prevent_closing) return
      this.$emit('backdrop-clicked')
      this.close()
    },
    onHeaderClose() {
      this.$emit('header-close-clicked')
      this.close()
    },
    onCancel() {
      this.$emit('cancel')
      this.close()
    },
    onConfirm() {
      this.$emit('confirm')
      this.close()
    },
    close() {
      this.$emit('close')
    },
    afterLeave() {
      this.$emit('hidden')
    }
  },
}
</script>

<style scoped>
/* transitions */
/* overall opacity */
.modal-fade-enter,
.modal-fade-leave-active {
  opacity: 0;
}
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity .3s ease-out;
}
/* modal animation */
.modal-fade-enter-to .vue-modal {
  opacity: 1;
  animation-duration: .3s;
  animation-name: show;
}
.modal-fade-leave-to .vue-modal {
  animation-duration: .3s;
  animation-name: hide;
  /* animation-direction: reverse; */
}
@keyframes show {
  0% {
      transform: scale(.7);
  }
  45% {
      transform: scale(1.05);
  }
  80% {
      transform: scale(.95);
  }
  100% {
      transform: scale(1);
  }
}
@keyframes hide {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  100% {
    transform: scale(0.5);
    opacity: 0;
  }
}
/* component style */
.modal-backdrop {
  overflow: scroll;
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: rgba(0, 0, 0, 0.3);
  display: flex;
  justify-content: center;
  align-items: flex-start;
}

.vue-modal {
  background: #FFFFFF;
  box-shadow: 2px 2px 20px 1px rgba(0, 0, 0, 0.3);
  overflow-x: auto;
  display: flex;
  flex-direction: column;
  margin: 1.75rem auto;
  max-width: 80%;
  min-width: 30%;
  max-width: 500px;
  border-radius: 5px;
  color: #595959;
}

.vue-modal-header,
.vue-modal-footer {
  padding: 15px;
  display: flex;
}

.vue-modal-header {
  border-bottom: 1px solid #eeeeee;
  /* justify-content: space-between; */
  vertical-align: center;
  align-items: center;
  font-weight: 700;
}

.vue-modal-footer {
  border-top: 1px solid #eeeeee;
  justify-content: flex-end;
}

.vue-modal-body {
  position: relative;
  padding: 20px 10px;
  text-align: center;
}
.header-wrapper {
  flex: 1;
}
.btn-close {
  align-self: flex-end;
  border: none;
  font-size: 20px;
  margin-left: 20px;
  cursor: pointer;
  font-weight: 700;
  color: #595959;
  background: transparent;
}
.icons {
  margin: 0 auto;
  padding: 5px;
}
.icons > div {
  animation-delay: .3s;
  animation-duration: .5s;
  animation-name: show-icon;
  font-size: 60px;
}
/* .icons .icon {
  border: solid 3px #595959;
  border-radius: 50px;
  width: 50px;
  height: 50px;
  vertical-align: middle;
  text-align: middle;
  display: flex;
  justify-content: center;
  align-items: center;
}
.icons .icon span{
  font-size: 25px;
  line-height: 25px;
  display: inline-block;
} */
@keyframes show-icon {
  0% {
      transform: scale(1.0) rotate(-15deg);
  }
  45% {
      transform: scale(1.20) rotate(10deg);
  }
  80% {
      transform: scale(.90) rotate(-5deg);
  }
  100% {
      transform: scale(1) rotate(0deg);
  }
}
.btn-green {
  color: white;
  background: #4AAE9B;
  border: 1px solid #4AAE9B;
  border-radius: 2px;
}
</style>
