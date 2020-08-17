<template>
  <div class="page not-found">
      <h1>Sorry, page not found.</h1>
  </div>
</template>

<script>
export default {
  name: 'PageNotFound',
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.page {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}
.page h1 {
  margin-top: auto;
  flex: 1;
}
</style>