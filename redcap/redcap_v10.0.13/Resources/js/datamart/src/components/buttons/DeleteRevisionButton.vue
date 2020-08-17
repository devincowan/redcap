<template>
  <StyledButton type="danger" text="Delete Revision" icon="fas fa-trash-alt" @click="onClick" />
</template>

<script>
import StyledButton from '@/components/buttons/StyledButton'

export default {
  name: 'DeleteButton',
  components: {StyledButton},
  methods: {
    async onClick() {
      // check revision before submitting
      const dismissal = await this.$swal.fire({
        icon: 'warning',
        title: 'Are you sure?',
        text: 'you won\'t be able to revert this!',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!'
      })
      if(dismissal.value===true) {
        const revision = this.$store.getters['revisions/selected']
        const {metadata: {id}} = revision
        try {
          const result = await this.$store.dispatch('revisions/delete', id)
          this.$swal.fire({
              toast: true,
              position: 'top-end',// 'center',
              showConfirmButton: false,
              timer: 3000,
              icon: 'success',
              title: 'revision deleted',
              text: ''
          })
        } catch (error) {
          this.$swal.fire({
            icon: 'error',
            // toast: true,
            // position: 'center',// 'top-end',
            showConfirmButton: true,
            timer: 3000,
            title: 'error deleting the revision',
            text: ''
          })
        }
      }
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
