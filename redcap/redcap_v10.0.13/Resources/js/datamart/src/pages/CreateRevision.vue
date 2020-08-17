<template>
  <div class="create-revision-page">
    <section class="main">
      <RevisionManagerView :hidden_groups="revision_hidden_groups">
        <template v-slot:header>
          <MappingHelperButton class="ml-auto"/>
        </template>
      </RevisionManagerView>
    </section>
    <section class="footer">
      <button type="button" class="btn btn-secondary" @click="goBack">Back</button>
      <ResetButton />
      <SubmitButton @dismissed="onDismissed" />
    </section>
  </div>
</template>

<script>
import RevisionManagerView, {settings_groups} from '@/views/RevisionManagerView'
import ResetButton from '@/components/buttons/ResetButton'
import SubmitButton from '@/components/buttons/SubmitButton'
import MappingHelperButton from '@/components/buttons/MappingHelperButton'

export default {
  name: 'CreateRevisionView',
  data: () => ({
    revision_hidden_groups:[settings_groups.MRNS] // nothing hidden!
  }),
  components: {
    RevisionManagerView,
    ResetButton,
    SubmitButton,
    MappingHelperButton,
  },
  methods: {
    goBack() {
      this.$router.push({ name: 'home' })
    },
    onDismissed(dismissal) {
      this.goBack()
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.footer {
  margin-top: 20px;
  display: flex;
  flex-direction: row;
  justify-content: flex-end;
}
.footer * + * {
  margin-left: 10px;
}

@media only screen and (max-width: 768px) {
  .footer {
    flex-direction: column;
    justify-content: center;
  }
  .footer * + * {
    margin-left: 0;
    margin-top: 10px;
  }
}
</style>