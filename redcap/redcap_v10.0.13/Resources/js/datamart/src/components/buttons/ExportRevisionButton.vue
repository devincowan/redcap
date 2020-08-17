<template>
    <StyledButton type="light" text="Export Revision" icon="fas fa-file-export" @click="showRevisionExportModal" />
</template>


<script>
import Vue from 'vue'
import RevisionExportSettings from '@/components/RevisionExportSettings'
import StyledButton from '@/components/buttons/StyledButton'

/**
 * helper function to get the fields to export
 */
const getFields = ({mrns,fields,dates}) => {
  const keys = []
  if(mrns) keys.push('mrns')
  if(fields) keys.push('fields')
  if(dates) keys.push('dateMin','dateMax')
  return keys
}

export default {
  name: 'ExportRevisionButton',
  components: { StyledButton },
  computed: {
    revision() {
      return this.$store.getters['revisions/selected']
    }
  },
  methods: {
    /**
     * export the active revision as a JSON file
     */
    async showRevisionExportModal() {
      

      const modal = this.$swal.fire({
        title: 'Select your export settings',
        allowOutsideClick: true,
        allowEscapeKey: true,
        showConfirmButton: true,
      })
      // initially disable confirm button
      const confirmButton = this.$swal.getConfirmButton()
      confirmButton.disabled = true

      const propsData = {revision:this.revision}
      this.$swal.addVueComponent(RevisionExportSettings, {propsData})
      const revisionExportSettings = this.$swal.getComponent()
      revisionExportSettings.$on('update', (settings) => {
        confirmButton.disabled = !revisionExportSettings.validate()
      })

      const dismissal = await modal
      if(dismissal.value===true) {
        const settings = revisionExportSettings.settings
        this.exportRevision(settings)
      }
    },
    /**
     * create a temporary link to download a revision (server side export)
     */
    exportRevision(settings) {
      const {format, csv_delimiter=','} = settings
      const revision_id = this.revision.getID()
      const fields = getFields(settings)

      const exportURL = Vue.$API.getExportURL({revision_id, fields, format, csv_delimiter})
      const anchor = document.createElement('a')
      const fileName = `datamart_revision_${revision_id}.${format}`
      anchor.setAttribute("download", fileName)
      anchor.setAttribute("target", '_SELF')
      anchor.setAttribute("href", exportURL)
      anchor.innerText = 'download'
      // temporarily add the anchor to the DOM, click and remove it
      document.body.appendChild(anchor) // required for firefox
      anchor.click()
      anchor.remove()
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>
