<template>
  <div>
    <!-- <JsonObject :object="object" /> -->
    <details>
      <summary>source</summary>
      <JsonObject :value="raw_data" />
      <button class="btn btn-primary" @click="copyData">copy</button>
    </details>
  </div>
</template>

<script>
import JsonObject from '@/components/JsonObject'
import EventBus from '@/libraries/EventBus'

export default {
  name: 'ResourceRawData',
  components: {
    JsonObject,
  },
  props: {
    raw_data: {
      type: Object,
      default: () => ({})
    },
  },
  methods: {
    onKeyClick({key, path}) {
      console.log(arguments)
      this.$store.dispatch('mappings/setKey', {key, path})
    },
    onValueClick({value, path}) {
      console.log(arguments)
      this.$store.dispatch('mappings/setValues', {value, path})
    },
    /**
     * print a pretty version of a JSON object
     */
    prettify(object) {
      const str = JSON.stringify(object, null, 2) // spacing level = 2
      return str
    },
    copyToClipboard(str) {
      const el = document.createElement('textarea')  // Create a <textarea> element
      el.value = str                                 // Set its value to the string that you want copied
      el.setAttribute('readonly', '')                // Make it readonly to be tamper-proof
      el.style.position = 'absolute'                 
      el.style.left = '-9999px'                      // Move outside the screen to make it invisible
      document.body.appendChild(el)                  // Append the <textarea> element to the HTML document
      const selected =            
        document.getSelection().rangeCount > 0        // Check if there is any content selected previously
          ? document.getSelection().getRangeAt(0)     // Store selection if found
          : false                                    // Mark as false to know no selection existed before
      el.select()                                    // Select the <textarea> content
      document.execCommand('copy')                   // Copy - only works as a result of a user action (e.g. click events)
      document.body.removeChild(el)                  // Remove the <textarea> element
      if (selected) {                                 // If a selection existed before copying
        document.getSelection().removeAllRanges()    // Unselect everything on the HTML document
        document.getSelection().addRange(selected)   // Restore the original selection
      }
    },
    copyData()
    {
      const data = this.prettify(this.raw_data)
      this.copyToClipboard(data)
      alert("copied!")
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
details {
  background-color: beige;
  padding: 10px;
}
</style>
