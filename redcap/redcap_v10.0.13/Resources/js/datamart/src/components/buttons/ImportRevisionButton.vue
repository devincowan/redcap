<template>
    <StyledButton type="light" text="Import Revision" icon="fas fa-file-import" @click="importRevision" />
</template>


<script>
import StyledButton from '@/components/buttons/StyledButton'

export default {
  name: 'ImportRevisionButton',
  components: { StyledButton },
  computed: {
    userCanImportRevision() {
      const user = this.$store.state.user.info
      if(!user) return false
      const {super_user: userIsAdmin, can_create_revision} = user
      if(userIsAdmin) {
          return true
      }else {
          return can_create_revision
      }
    },
  },
  methods: {
    /**
     * show a file dialog box
     */
    importRevision()
    {
      const self = this
      // create a file inout element and append it to the DOM
      const fileUpload = document.createElement('input')
      fileUpload.setAttribute('type', 'file')
      // fileUpload.setAttribute('multiple', true)
      fileUpload.style.display = 'none'
      document.body.appendChild(fileUpload)

      fileUpload.addEventListener('change', async (e) => {

        const formData = new FormData()
        Array.from(fileUpload.files).forEach(file => {
          formData.append('files[]', file)
        })

        const data = await self.$store.dispatch('revision/import', formData)
        self.$emit('import', data)
        if(!data) {
          self.$swal.fire({
            type: 'error',
            title: 'import error',
            text: 'The file you are trying to import could not be processed.',
          })
        }

 
        fileUpload.remove()
      })
      fileUpload.click()
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>