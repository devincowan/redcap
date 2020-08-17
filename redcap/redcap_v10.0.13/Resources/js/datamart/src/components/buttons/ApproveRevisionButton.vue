<template>
  <button class="btn btn-sm btn-success" @click="onClick"><i class="far fa-check-circle"></i> Approve</button>
</template>

<script>
import JsonError from '@/components/common/JsonError'

export default {
  name: 'ApproveButton',
  methods: {
    async onClick() {
      try {
        const { metadata: { id: revision_id } } = this.$store.getters['revisions/selected']
        this.$swal.fire({
          icon: 'info',
          title: 'approving',
          html: `<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Please wait.</p>`,
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
        })
        const response = await this.$store.dispatch('revisions/approve', revision_id)
        this.$swal.fire({
          toast: true,
          showConfirmButton: false,
          timer: 3000,
          position: 'top-end',
          icon: 'success',
          title: 'Success',
          text: 'The revision has been approved!',
        })
      } catch (response) {
        const { responseText } = response // get the error message from REDCap
        const responseJson = JSON.parse(responseText)
        const { errors } = responseJson
        this.$swal.fire({
          icon: 'error',
          title: 'error approving the revision',
          // html: error,
        })
        this.$swal.addVueComponent(JsonError, {propsData: {errors}})
      }

    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
