<template>
<!-- tabindex is used to enable @blur in the div -->
<div class="split-button" ref="container" @mousedown="onMouseDown" @blur="onBlur" tabindex="0">
  <div class="btn-group">
      <slot name="button">
        <button type="button" :class="btn_class">
          <span>Split dropright</span>
        </button>
      </slot>
    <button type="button" :class="btn_class" @click="toggle" :disabled="dropdown_disabled">
      <i v-if="collapsed" data-v-18f96775="" class="fas fa-caret-down status-indicator"></i>
      <i v-else data-v-18f96775="" class="fas fa-caret-up status-indicator"></i>
    </button>
  </div>
  <div class="dropdown-content" :class="{collapsed}">
    <slot name="dropdown-content"></slot>
  </div>
</div>
</template>

<script>
import Vue from 'vue'

const initial_data = {
  promise: null,
  abort: false,
}

export default {
  name: 'SplitButton',
  data: () => ({
    collapsed: true
  }),
  props: {
    btn_class: {
      type: String,
      default: 'btn btn-primary'
    },
    dropdown_disabled: {
      type: Boolean,
      default: false
    }
  },
  methods: {
    onMouseDown(event) {
      this.keepFocus()
    },
    /**
     * check if the clicked element is contained
     * by the main container; if not then collapse
     */
    onBlur(event) {
      const {relatedTarget} = event
      if(this.$refs.container.contains(relatedTarget)) {
        this.keepFocus()
      }else {
        this.collapse()
      }
    },
    toggle() {
      this.collapsed = !this.collapsed
    },
    keepFocus() {
      this.$refs.container.focus()
    },
    collapse() {
      this.collapsed = true;
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.split-button {
  position: relative;
  outline: none;
}
.dropdown-content.collapsed {
  display: none;
}
.dropdown-content {
  background-color: green;
}
.dropdown-content {
  right: auto;
  margin-top: 0;
  margin-left: .125rem;
  position: absolute;
  top: 100%;
  left: 0;
  z-index: 1000;
  display: block;
  float: left;
  min-width: 10rem;
  padding: .5rem 0;
  margin: .125rem 0 0;
  font-size: 1rem;
  color: #212529;
  text-align: left;
  list-style: none;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid rgba(0,0,0,.15);
  border-radius: .25rem;
}
</style>
