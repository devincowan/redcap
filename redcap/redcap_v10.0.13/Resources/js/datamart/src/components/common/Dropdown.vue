<template>

    <div class="dropdown-container" @mousedown="onMouseDown" @click="onClick" :class="{expanded:expanded}">
      <button ref="button" class="btn btn-secondary" :class="{'btn-sm': small}" type="button" @blur="onBlur" >
        <i v-if="icon" :class="icon"></i>
        <span v-html="text"/> <i v-if="!hideCaret && hasItems" class="fas fa-caret-down status-indicator"></i>
      </button>
      <section class="menu" :class="{right, bottom}" v-show="expanded">
        <slot name="items"></slot>
      </section>
    </div>

</template>

<script>
export default {
  name: 'Dropdown',
  data: () => ({
    expanded: false,
    showCaret: false,
  }),
  props: {
    /**
     * a fontawesome class for icon (example: fas fa-cog)
     */
    icon: {
      type: String,
      default: ''
    },
    hideCaret: {
      type: Boolean,
      default: false
    },
    text: {
      type: String,
      default: 'select'
    },
    small: {
      type: Boolean,
      default: false
    },
    /**
     * align menu to the right
     */
    right: {
      type: Boolean,
      default: false
    },
    /**
     * align menu to the bottom
     */
    bottom: {
      type: Boolean,
      default: false
    },

  },
  computed: {
    hasItems() {
      if(typeof this.$slots.items==='undefined') return false
      return this.$slots.items.length>0
    }
  },
  methods: {
    /**
     * keep focus on the button if an element inside the component
     * receives a mousedown event.
     * this ensures that blur is only fired when a click is outside of the component
     */
    onMouseDown(event) {
      event.preventDefault()
      this.$refs.button.focus()
    },
    onBlur(event) {
      this.collapse()
    },
    onClick(event) {
      this.toggle()
    },
    toggle() {
      // this.$el.focus()
      this.expanded ? this.collapse() : this.expand()
    },
    collapse() {
      this.expanded = false
      this.$refs.button.blur()
    },
    expand() {
      if(!this.hasItems) return
      this.expanded = true
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.dropdown-container {
  position: relative;
  cursor: pointer;
  /* z-index: 1; */
}
.dropdown-container:focus {
  background-color:  red;
}
/* .dropdown-container > button {

} */

.dropdown-container .menu {
  position: absolute;
  top: auto;
  left: auto;
  right: auto;
  bottom: auto;
  will-change: transform;
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
  z-index: 1;
}
.dropdown-container .menu.right {
  right: 0;
}
.dropdown-container .menu.bottom {
  bottom: 100%;
  margin-bottom: 2px;
}
.dropdown-container .menu > * {
    display: block;
    width: 100%;
    padding: .25rem 1.5rem;
    clear: both;
    font-size: inherit;
    font-weight: 400;
    color: #212529;
    text-align: inherit;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
}
.dropdown-container .menu > *:focus,
.dropdown-container .menu > *:hover {
    color: #16181b;
    text-decoration: none;
    background-color: #f8f9fa;
}
.status-indicator {
  transition-property: transform;
  transition-duration: 150ms;
  transition-timing-function: ease-in-out;
  transform: rotate(0deg);
}
.expanded .status-indicator {
  transform: rotate(-180deg);
}
@media only screen and (max-width: 768px) {
  
  .dropdown-container > button {
    width: 100%;
  }
}
</style>
