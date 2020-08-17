<template>
  <div class="revision-manager-view">

    <div class="setting-group card" v-if="!hidden_groups.includes(settings_groups.DATE)">
      <div class="card-header">
        <span class="setting-title">If pulling time-based data, select the range of time from which to pull data (optional)</span>
      </div>
      <div class="card-body">
        <DateRange />
        <ErrorList :errors="errors.date" />
      </div>
    </div>
    
    <div class="setting-group card mt-4" v-if="!hidden_groups.includes(settings_groups.FIELDS)">
      <div class="card-header">
        <span class="setting-title">Choose fields in EHR for which to pull data</span>
        <slot name="header"></slot>
      </div>
      <div class="card-body">
        <Fields />
        <ErrorList :errors="errors.fields" />
      </div>
    </div>

    <div class="setting-group card mt-4" v-if="!hidden_groups.includes(settings_groups.MRNS)">
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


/**
 * groups used to hide unnecessary settings groups.
 * When creating a new revision the MRNs field editor should be hidden
 */
export const settings_groups = Object.freeze({
  DATE: 'date',
  FIELDS: 'fields',
  MRNS: 'mrns',
})

export default {
  name: 'RevisionManagerView',
  data: () => ({
    settings_groups,
  }),
  components: {
    MRNListEditor,
    Fields,
    DateRange,
    ErrorList,
  },
  props: {
    hidden_groups: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    isDirty() {
      return this.$store.getters['revision/isDirty']
    },
    errors() {
      return this.$store.state.validator.errors
    }
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.card-header {
  display: flex;
}
.error-list {
  margin-top: 10px;
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
