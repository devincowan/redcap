<template>
  <div v-if="settings">
    <section class="metadata">
      <input type="hidden" name="datamart[request_id]" :value="settings.request_id" />
      <input type="hidden" name="datamart[user_id]" :value="settings.user_id" />
    </section>
    <section class="daterange">
      <input type="hidden" name="datamart[daterange][min]" :value="settings.dateMin" />
      <input type="hidden" name="datamart[daterange][max]" :value="settings.dateMax" />
    </section>
    <section class="mrns">
      <input v-for="(field, key) in settings.mrns" :key="key" type="hidden" name="datamart[mrns][]" :value="field">
    </section>
    <section class="fields">
      <input v-for="(field, key) in settings.fields" :key="key" type="hidden" name="datamart[fields][]" :value="field">
    </section>
  </div>
</template>

<script>
/**
 * these hidden input fields are used by the
 * createModule and the reviewModule in
 * the ToDoList page
 */
export default {
  name: 'FormSettings',
  computed: {
    settings() {
        const settings = this.$store.getters['settings']
        return settings
      }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
