<template>
  <div class="revision-manager">

    <div class="setting-group card" v-if="showDates">
      <div class="card-header">
        <span class="setting-title">If pulling time-based data, select the range of time from which to pull data (optional)</span>
      </div>
      <div class="card-body">
        <DateRange />
        <ErrorList :errors="errors.date" />
      </div>
    </div>
    
    <div class="setting-group card" v-if="showFields">
      <div class="card-header">
        <span class="setting-title">Choose fields in EHR for which to pull data</span>
      </div>
      <div class="card-body">
        <Fields />
        <ErrorList :errors="errors.fields" />
      </div>
    </div>      

    <div class="setting-group card" v-if="showMrns">
      <div class="card-header">
        <span class="setting-title">Enter medical record numbers of patients to import from the EHR (one per line, optional)</span>
      </div>
      <div class="card-body">
        <MRNListEditor />
        <ErrorList :errors="errors.mrns" />
      </div>
    </div>
    
    <!-- <ul>
    <li v-for="(group, index) in validationErrors.collect()" :key="index">
      <ul>
        <li v-for="(error, index) in group" :key="index">{{ error }}</li>
      </ul>
    </li>
    </ul> -->

  </div>
</template>

<script>

import MRNListEditor from '@/components/MRNListEditor'
import Fields from '@/components/fields/edit/Fields'
import DateRange from '@/components/DateRange'
import ErrorList from '@/components/ErrorList'

export default {
  name: 'RevisionManager',
  components: {
    MRNListEditor,
    Fields,
    DateRange,
    ErrorList,
  },
  computed: {
    showDates() {
      return Boolean(this.$store.state.revision.allowedSettings.dates)
    },
    showFields() {
      return Boolean(this.$store.state.revision.allowedSettings.fields)
    },
    showMrns() {
      return Boolean(this.$store.state.revision.allowedSettings.mrns)
    },
    isDirty() {
      return this.$store.getters['revision/isDirty']
    },
    errors() {
      return this.$store.state.validator.errors
    }
  },
  mounted() {
    window.addEventListener("keydown", this.toggleMrnsOnKeyPress, false)
  },
  beforeDestroy() {
    window.removeEventListener("keydown", this.toggleMrnsOnKeyPress, false)
  },
  methods: {
    toggleMrnsOnKeyPress(event) {
      const {key, code, altKey, shiftKey, ctrlKey} = event
      if(altKey && shiftKey && code == 'KeyM') {
        // this.showMrns = !this.showMrns
        const value = !this.showMrns
        this.$store.dispatch('revision/setAllowedSettings',{mrns:value})
      }
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.error-list {
  margin-top: 10px;
}
.revision-manager > * + * {
  margin-top: 20px;
}
div.setting-group {
  position: relative;
}
.setting-title {
  font-weight: bold;
  font-size: 1.3em;
}
setting-group.setting-group > * + * {
  margin-top: 10px;
}
</style>
