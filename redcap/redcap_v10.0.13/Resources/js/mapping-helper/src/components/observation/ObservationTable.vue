<template>
  <div v-if="filtered_codings.length">

    <section>
      <p v-html="lang['mapping_helper_04']" />
    </section>

    <table class="table table-striped table-bordered">
      <thead>
        <!-- <th><button type="button" class="btn" @click="toggleGroups">Group</button></th> -->
        <th>Description (from EHR, not from REDCap mapping)</th>
        <th>Code
          <!-- <button class="btn btn-sm"
            @click="hide_blocklisted=!hide_blocklisted"
            :title="(hide_blocklisted ? 'show' : 'hide') + ' hidden codes'">
            <span class="mr-2">Code</span>
            <i v-if="hide_blocklisted" class="fa fa-eye-slash"></i>
            <i v-else class="fas fa-eye"></i>
          </button> -->
        </th>
        <th>System</th>
        <th>Value</th>
        <th>Date/time of service</th>
        <th>
          <span>Actions</span>
          <div v-show="exportable.length>0">
            <div class="btn-group" role="group">
              <button
                type="button" 
                class="btn btn-sm"
                :class="{
                'btn-success': codes_to_export.length>=1,
                'btn-info': codes_to_export.length<1
                }"
                @click="toggleExportableSelection"
              ><span>{{(exportable.length===codes_to_export.length) ? `deselect all` : `select all`}} <i class="fas fa-check-square"></i></span></button>
              <button
                type="button"
                :disabled="codes_to_export.length<1"
                class="btn btn-sm btn-primary"
                @click="showPreview">
                <span>Export <i class="fas fa-download"></i></span>
              </button>
            </div>
          </div>
        </th>
      </thead>
      <!-- codings are grouped by observation -->
      <tbody >
        <tr v-for="(coding, coding_index) in filtered_codings" :key="coding_index">
          <!-- <td :style="getGroupStyle(index)">{{index}}</td> -->
          <td>{{coding.display}}</td>
          <td>
            <div><span>{{coding.code}}</span></div>
          </td>
          <td>{{coding.system}}</td>
          <td>{{coding.value}}</td>
          <td>{{formatDate(coding.date)}}</td>
          <td>
            <section v-if="isBlocklisted(coding.code)">
              <div>
                <small><em>(this code is not used in REDCap: {{isBlocklisted(coding.code)}})</em></small>
              </div>
            </section>
            <section v-if="!isAvailableInREDCap(coding.code)">
              <button class="btn btn-sm btn-outline-warning" @click="displayNewCodeInfo(coding)">
                info <i class="fas fa-info-circle"></i>
              </button>
              <div>
                <small :style="{color:'red'}">(not available in REDCap)</small>
              </div>
            </section>
            <section v-if="isExportable(coding.code)">
              <button class="btn btn-sm" type="button"
              :class="{
                'btn-success': isCodeSelected(coding.code),
                'btn-info': !isCodeSelected(coding.code)
                }"
              @click="toggleSelect(coding.code)">{{isCodeSelected(coding.code) ? 'deselect' : 'select'}}</button>
              <div>
                <small><em>(not mapped in your project)</em></small>
              </div>
            </section>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
// blocklisted codes
import Vue from 'vue'
import { saveAs } from 'file-saver'
import {formatDate} from '@/libraries'
import DownloadPreview from '@/components/DownloadPreview'

export default {
  name: 'ObservationTable',
  data: () => ({
    hide_blocklisted: false,
    show_groups: false,
    codes_to_export: [],
    internal_codes: null, // internal state for the mapped codes
  }),
  props: {
    entries: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    lang() {
      const {lang} = this.$store.state.settings.settings
      return lang
    },
    codes_blocklist() {
      const {blocklisted_codes} = this.$store.state.settings.settings
      return blocklisted_codes
    },
    /**
     * get a list of the codings based on the selected codes
     */
    values_to_export() {
      const codings = []
      // keep track of the codes pushed in codings to avoid duplicates
      const track_codes = []
      this.filtered_codings.forEach(coding => {
        if(this.codes_to_export.indexOf(coding.code)>=0) {
          // check for codes duplicates
          if(track_codes.indexOf(coding.code)<0) {
            track_codes.push(coding.code)
            codings.push(coding)
          }
        }
      })
      return codings
    },
    mapped_codes() {
      const codes = this.$store.getters['project/mappedCodes']
      return codes
    },
    /**
     * list of the mapped codes in REDCap
     */
    fhir_metadata_codes() {
      const {codes} = this.$store.state.fhir_metadata
      return codes
    },
    /**
     * extract codings from entries
     */
    codings() {
      try {
        const entries = [...this.entries]
        if(!Array.isArray(entries)) return
        const codings = entries.reduce((all, entry) => {
          return [...all, ...entry.codings]
        }, [])
        return codings
      } catch (error) {
        return []
      }
    },
    /**
     * apply filters to codings (blocklist...)
     */
    filtered_codings() {
      const codings = this.codings
      if(this.hide_blocklisted) {
        return codings.filter(coding => !this.isBlocklisted(coding.code) )
      }
      return codings
    },
    /**
     * get a list of codes that could be exported
     */
    exportable() {
      const codings = this.filtered_codings
      const exportable = []
      codings.forEach(coding => {
        const {code=''} = coding
        if(!this.isExportable(code)) return
        if(exportable.indexOf(code) >=0) return
        exportable.push(code)
      })
      return exportable
    }
  },
  methods: {
    toggleSelect(code) {
      const index = this.codes_to_export.indexOf(code)
      if(index<0) this.codes_to_export.push(code)
      else this.codes_to_export.splice(index, 1)
    },
    isCodeSelected(code) {
      return this.codes_to_export.indexOf(code)>=0
    },
    toggleExportableSelection() {
      if(this.exportable.length===this.codes_to_export.length) {
        this.codes_to_export = []
      }else {
        this.codes_to_export = [...this.exportable]
      }
    },
    isAvailableInREDCap(code='') {
      if(code.trim()=='') return true
      if(this.isBlocklisted(code)) return true
      const mapped_codes = this.fhir_metadata_codes
      const mapped = mapped_codes.some(mapped_code => mapped_code===code)
      return mapped
    },
    /**
     * check if a code is blocklisted
     */
    isBlocklisted(code='') {
      const codes = this.codes_blocklist.map(element => element.code)

      const index = codes.indexOf(code)
      if(index>=0) return this.codes_blocklist[index].reason
      else return false
    },
    isMappedInProject(code='') {
      if(code.trim()=='') return true
      if(this.isBlocklisted(code)) return true
      const mapped_codes = this.mapped_codes
      return mapped_codes.indexOf(code) >=0
    },
    isExportable(code) {
      const mapped_in_redcap = this.isAvailableInREDCap(code)
      const mapped_in_project =this.isMappedInProject(code)
      return mapped_in_redcap && !mapped_in_project
    },
    displayNewCodeInfo(coding) {
      const {code} = coding

      this.$swal.fire({
        title: `Code '${code}' not available`,
        icon: 'info',
        text: `${this.lang['mapping_helper_03']}`,
      })
    },
    async sendNotification(coding) {
      const {code} = coding
      const {resource_type, interaction, mrn} = this.$store.state.endpoint
      try {
        await this.$API.sendNotification({code, resource_type, interaction, mrn})
        this.$swal.fire({
          title: 'Success',
          icon: 'success',
          text: 'Your request has been sent to an admin.',
        })
      } catch (error) {
        this.$swal.fire({
          title: 'Error',
          icon: 'error',
          text: 'There was an error sending your request.',
        })
      }
    },
    /**
     * create the lisnes that will be exported
     */
    getLinesToExport() {
      const lines = []
      this.values_to_export.forEach(coding => {
        const line = `${coding.code}\t${coding.display}`
        lines.push(line)
      })
      return lines
    },
    /**
     * show a preview of the text file that will be exported
     */
    async showPreview() {
      const lines = this.getLinesToExport()

      // Create a “subclass” of the base Vue constructor
      const download_preview_component = Vue.extend(DownloadPreview)
      const properties = {
        propsData: {lines},
        store: this.$store, //pass along the store if you want
        created() {
          console.log(this.$store)
        }
      }

      const response = await this.$swal.fire({
        icon: 'info',
        title: 'Export fields',
        confirmButtonText: 'Download',
        component: download_preview_component,
        component_args: properties
      })

      const {value:response_value=false} = response
      if(response_value) this.exportData(lines)
    },
    /**
     * export data to file:
     * join array of lines using newline
     */
    async exportData(lines) {
      const text = lines.join(`\n`)
      var blob = new Blob([text], {type: "text/plain;charset=utf-8"})
      saveAs(blob, "fields.txt")
    },
    /**
     * formatDate from Utils
     */
    formatDate,
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
button {
  white-space: nowrap;
}
td {
  white-space: nowrap;
}
td.centered {
  vertical-align: middle;
  text-align: center;
}
</style>