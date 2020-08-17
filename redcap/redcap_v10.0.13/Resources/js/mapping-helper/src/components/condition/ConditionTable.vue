<template>
  <div v-if="rows.length>0">
    <table class="table table-striped table-bordered">
      <thead>
        <th>Description (from EHR, not from REDCap mapping)</th>
        <th>Status</th>
        <th>System</th>
        <th>Code</th>
        <th>Date/time</th>
      </thead>
      <!-- codings are grouped by condition??? -->
      <tbody >
        <tr v-for="(row, index) in rows" :key="index">
          <td>{{row.display}}</td>
          <td>{{row.status}}</td>
          <td>{{row.system}}</td>
          <td>{{row.code}}</td>
          <td>{{formatDate(row.date)}}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import {formatDate} from '@/libraries'

export default {
  name: 'ConditionTable',
  data: () => ({}),
  props: {
    entries: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    rows() {
      const rows = []
      this.entries.forEach(entry => {
        const {date, status, text, codings} = entry
        if(codings.length==0) {
          const row = {
            date,
            status,
            display: text,
          }
          rows.push(row)
        }else {
          codings.forEach(coding => {
            const row = {
              date,
              status,
              display: coding.display || text,
              system: coding.system,
              code: coding.code
            }
            rows.push(row)
          })
        }
      })
      return rows
    },
  },
  methods: {
    /**
     * formatDate from Utils
     */
    formatDate,
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

td.centered {
  vertical-align: middle;
  text-align: center;
}
</style>