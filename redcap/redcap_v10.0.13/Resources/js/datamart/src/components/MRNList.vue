<template>
<main>

  <span><b>Total MRNs:</b> {{list.length}}</span>
  <ul v-if="list.length<20">
      <li v-for="(mrn, index) in list" :key="index">{{mrn}}</li>
  </ul>
  <Details v-else>
    <template v-slot:summary>Show MRN list...</template>
    <ul>
      <li v-for="(mrn, index) in list" :key="index">{{mrn}}</li>
    </ul>
  </Details>
</main>
</template>

<script>
import Details from '@/components/common/Details'

export default {
  name: 'MRNList',
  components: {
    Details,
  },
  props: {
    list: {
      type: Array,
      default: () => []
    }
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
ul {
  list-style: none;
  max-height: 100px;
  overflow: auto;
  border: 1px solid rgba(0,0,0,.125);
  border-radius: 3px;
  padding: 5px;
  margin: 0;
}
</style>
