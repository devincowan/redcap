<template>
  <button type="button" class="btn btn-sm btn-info" @click="onClick"><i class="fas fa-code-branch"></i> Use the Mapping Helper</button>
</template>

<script>

export default {
  name: 'mapping-helper-button',
  data: () => ({

  }),
  computed: {
    mapping_helper_url() {
      const {settings:{mapping_helper_url}} = this.$store.state.settings
      return mapping_helper_url
    }
  },
  methods: {
    async onClick() {
      // location.href = this.mapping_helper_url
      const dismissalPromise = this.$swal.fire({
        icon: 'info',
        title: 'Mapping Helper',
        text: 'You will be redirected to the Mapping Helper. Continue?',
        allowOutsideClick: true,
        allowEscapeKey: true,
        showConfirmButton: true,
        showCancelButton: true,
      })

      const { dismiss, value } = await dismissalPromise
      if(value===true) {
        window.open(this.mapping_helper_url,'_self')
      }else if(dismiss && dismiss==='cancel') {
          console.log('operation canceled by user')
      }
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
