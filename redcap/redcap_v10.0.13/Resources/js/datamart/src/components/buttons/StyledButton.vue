<template>
  <button type="button" :class="`btn btn-sm ${types[type]}`" @click="$emit('click')" :disabled="disabled"><i v-if="icon" :class="icon"></i>{{text}}</button>
</template>


<script>
export const TYPES = Object.freeze({
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  success: 'btn-success',
  info: 'btn-info',
  danger: 'btn-danger',
  warning: 'btn-warning',
  light: 'btn-light',
  dark: 'btn-dark',
  transparent: '',
})

export default {
  name: 'SubmitButton',
  data: () => ({
    types: TYPES
  }),
  props: {
    type: {
      type: String,
      default: TYPES.primary
    },
    // a fontawesome icon style
    icon: {
      type: String,
      default: null
    },
    text: {
      type: String,
      default: 'submit'
    },
    disabled: {
      type: Boolean,
      default: false
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
i {
  margin-right: 5px;
}
</style>
