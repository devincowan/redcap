<template>
  <div class="create-project-page">
    <div class="buttons">
      <ImportRevisionButton />
    </div>
    <RevisionManagerView :hidden_groups="revision_hidden_groups"/>
    <FormSettings />
  </div>
</template>

<script>
/**
 * this page is used when a DataMart project is created
 * and the first revision must be inserted
 */

/**
 * this component will expose in a form the settings of the revision
 */
import FormSettings from '@/components/FormSettings'
import RevisionManagerView, {settings_groups} from '@/views/RevisionManagerView'
import ImportRevisionButton from '@/components/buttons/ImportRevisionButton'

export default {
  name: 'CreateProjectPage',
  data: () => ({
    revision_hidden_groups:[] // nothing hidden!
  }),
  components: {
    RevisionManagerView,
    ImportRevisionButton,
    FormSettings
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.buttons {
  text-align: right;
  margin-bottom: 10px;
}
</style>