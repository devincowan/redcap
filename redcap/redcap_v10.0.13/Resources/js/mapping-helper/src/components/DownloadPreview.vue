<template>
  <div class="download-preview">
    <p>The following data will be exported to a file.<br>
    You can use the exported fields as a refrence for mapping new values in REDCap.</p>
    <ul>
      <li v-for="(line, index) in lines" :key="index">
        {{line}}
      </li>
    </ul>
  </div>
</template>

<script>

export default {
  name: 'download-preview',
  props: {
    lines: {
      type: Array,
      default: () => []
    }
  },
}
</script>

<style scoped>
.download-preview ul {
  text-align: left;
}
</style>
