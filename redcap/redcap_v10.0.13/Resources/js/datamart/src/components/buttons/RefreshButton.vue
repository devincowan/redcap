<template>
  <button type="button" class="btn btn-sm btn-secondary" @click="onClick"><i class="fas fa-sync-alt" :class="{'fa-spin':loading}"></i></button>
</template>

<script>

export default {
  name: 'ResetButton',
  data() {
    return {loading:false}
  },
  computed: {},
  methods: {
    async onClick() {
      try {
        this.loading = true
        const response = await this.$API.getRevisions()
        const {data: revisions} = response
        this.$store.dispatch('revisions/setList', revisions)
      } catch (error) {
        alert(error)
      }finally {
        this.loading = false
      }
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
