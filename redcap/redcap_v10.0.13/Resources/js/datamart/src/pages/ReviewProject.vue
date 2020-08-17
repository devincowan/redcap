<template>
  <div class="review-revision-page">
    <main v-if="revision">
      <RevisionDetail :revision="revision">
        <template v-slot:footer></template>
      </RevisionDetail>
    </main>
    <FormSettings />
  </div>
</template>

<script>
import RevisionDetail from '@/components/RevisionDetail'
/**
 * this component will expose in a form the settings of the revision
 */
import FormSettings from '@/components/FormSettings'

export default {
  name: 'ReviewModule',
  components: {
    RevisionDetail,
    FormSettings,
  },
  computed: {
    revision() {
      return this.$store.getters['revisions/selected']
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>
