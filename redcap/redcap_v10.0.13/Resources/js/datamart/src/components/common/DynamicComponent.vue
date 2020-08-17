
<script>

export default {
  name: 'DynamicComponent',
  render: function (createElement) {
    return createElement(
      // 'h' + 1,   // tag name
      'section',   // tag name
      {
        is: this.component,
        ...this.attributes
      },
      this.$slots.default // array of children
    )
  },
  props: {
    component: {
      type: Object,
      default: null
    },
    attributes:{
      type: Object,
      default: {}
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
