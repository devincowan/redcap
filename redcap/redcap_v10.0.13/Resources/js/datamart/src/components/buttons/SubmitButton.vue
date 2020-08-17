<template>
  <button class="btn btn-sm btn-success" @click="onClick" :disabled="!isValid || !isDirty"><i class="fas fa-file-export"></i> Submit</button>
</template>

<script>
export default {
  name: 'SubmitButton',
  computed: {
    isDirty() {
      return this.$store.getters['revision/isDirty']
    },
    isValid() {
      const errors = this.$store.state.validator.errors
      return Object.keys(errors).length==0
    }
  },
  methods: {
    async onClick() {
      // use this check instead of :disabled to improve performances
      /* if(!this.isDirty) {
        alert('change something before submitting')
        return 
      } */
      try {
        // check revision before submitting
        const isValid = await this.$store.dispatch('revision/validate')
        if(!isValid) return

        // close the modal
        // this.$store.dispatch('modal/setOpen', false)

        this.$swal.fire({
          icon: 'info',
          title: 'sending data',
          html: `<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Please wait.</p>`,
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
        })

        const revision = await this.$store.dispatch('revision/submit')

        const dismissal = await this.$swal.fire({
          icon: 'success',
          title: 'Success',
          text: 'Your revision has been submitted!',
        })
        this.$emit('dismissed', dismissal)
      } catch (error) {
        console.log(error)
        this.$swal.fire({
          icon: 'error',
          title: 'Error submitting your revision',
          text: '',
          //timer: 1500
        })
      }
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
