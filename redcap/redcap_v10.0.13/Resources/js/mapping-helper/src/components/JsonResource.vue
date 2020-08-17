<script>
import JsonObject from '@/components/JsonObject'
import EventBus from '@/libraries/EventBus'

export default {
  name: 'JsonResource',
  extends: JsonObject,
  components: {JsonObject},
  created() {
    EventBus.$on('keyClicked', node => {
      this.setActiveKey(node)
    })
    EventBus.$on('valueClicked', node => {
      this.setActiveValue(node)
    })
  },
  methods: {
    setActiveKey(node) {
      console.log(node)
      this.activeKey = this._uid === node._uid
    },
    /**
     * set the active value
     * if a sibiling is active, it stays active
     */
    setActiveValue(node) {
      console.log(this , node, this._uid , node._uid)
      if(this._uid === node._uid)
      {
        // toggle the active element
        this.activeValue = !this.activeValue
      }else {
        // disable all elements that are not sibilings of the currently active element
        if(this.$parent._uid !== node.$parent._uid) {
          this.activeValue = false
        }
      }
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.value.active {
  background-color:rgba(191, 202, 40, 0.8)
}
.key.active {
  background-color:rgba(191, 0, 150, 0.8)
}

</style>
