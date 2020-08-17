<template>
  <div class="medicationorder-params">
    <label class=" font-weight-bold" for="status">Medication status</label>
    <fieldset>
      <div class="form-check form-check-inline" v-for="(status, index) in status_list" :key="index" >
        <input class="form-check-input" type="checkbox" name="status" :id="`status-${index}`" :value="status" v-model="checked">
        <label class="form-check-label" :for="`status-${index}`">{{status}}</label>
      </div>
    </fieldset>
  </div>
</template>

<script>
import {medication_status_list} from '@/variables'
import qs from 'qs'

export default {
  name: 'MedicationOrderFields',
  data: () => ({ 
    status_list: medication_status_list,
    checked: [],
  }),
  props: {
    status: {
      type: [Array, String],
      default: () => []
    }
  },
  created() {
    if(Array.isArray(this.status)) this.checked = [...this.status]
    else this.status = this.status.split(',') //comma separated values
  },
  methods: {
    getQuery() {
      const status = this.checked || []
      return {status}
    }
  }
  
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>
