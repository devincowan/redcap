<template>
  <section class="MRNs-wrapper">
      <div class="textarea-container">

        <textarea v-model="MRN_text" />

        <aside>
          <span><b>Total MRNs:</b> {{list.length}}</span>
        </aside>
      </div>
  </section>
</template>

<script>
import { debounce } from 'lodash'
import EventBus from '@/libraries/EventBus'

export default {
  name: 'MRNListEditor',
  data() {
    return {
      text: ''
    };
  },
  created() {
    this.getTextFromList()
    EventBus.$on('REVISION_SET', this.getTextFromList)
  },
  destroyed() {
    EventBus.$off('REVISION_SET', this.getTextFromList)
  },
  computed: {
    MRN_text: {
      get() {
        return this.text
      },
      set(value) {
        this.text = value
        this.updateMRNs(value)
      }
    },
    list() {
      return this.$store.state.revision.mrns
    },
  },
  methods: {
    getTextFromList() {
      this.text = this.list.join("\n")
    },
    updateMRNs: debounce(function(text) {
        this.$store.dispatch('revision/setMRNs', text)
    }, 250),
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
textarea {
  width: 100%;
  height: 100px;
  border: 1px solid rgba(0,0,0,.125);
}
.textarea-container {
  display: flex;
  flex-direction: column;
  position: relative;
}
.textarea-container aside {
  margin-top: 3px;
  text-align: right;
  color: rgb(180, 180, 180);
}
#content {
  border: 1px solid rgba(0,0,0,.125);
  min-width: 300px;
  min-height: 300px;
  white-space: pre-wrap;
}
</style>
