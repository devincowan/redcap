<template>
  <button class="btn btn-sm btn-success"  @click="onClick" :disabled="disabled" :title="title"><i class="fas fa-cloud-download-alt"></i> Fetch clinical data <span class="badge badge-light">{{totalMrns}}</span></button>
</template>

<script>
import Vue from 'vue'

import RevisionFetchProgress from '@/components/RevisionFetchProgress'
import JsonError from '@/components/common/JsonError'
import {delay} from '@/libraries/utils'

const initial_data = {
  promise: null,
  abort: false,
}

export default {
  name: 'RunRevisionButton',
  data: () => ({
    ...initial_data,
    }),
  computed: {
    title() {
      const totalMrns = this.totalMrns
      const cardinality = (totalMrns===1) ? '' : 's'
      if(totalMrns<1) return 'Data can not be fetched'
      return `Fetch data for ${totalMrns} record${cardinality}`
    },
    revision() {
      return this.$store.getters['revisions/selected']
    },
    totalMrns() {
      const mrns = this.$store.state.mrns.list || []
      return mrns.length
    },
    disabled() {
      try {
        if(!this.revision) return true
        const active = this.$store.getters['revisions/isActive'](this.revision)
        const totalMrns = this.totalMrns
        const user = this.$store.state.user.info
        return !active || !user.hasValidToken() || this.totalMrns<1
      } catch (error) {
        return true
      }
    }
  },
  methods: {
    resetData() {
      for(let [key, value] of Object.entries(initial_data)) {
        this[key] = value
      }
    },
    /**
     * helper function used for debug
     */
    wait(milliseconds) {
      return new Promise(resolve => setTimeout(resolve, milliseconds));
    },
    async onClick() {
      try {
        const { metadata: { id: revision_id } } = this.revision
        const mrn_list = this.$store.state.mrns.list || []
        // show modal
        const modal = this.$swal.fire({
          // icon: 'info',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          showCancelButton: true,
        })

        // add the revision fetch progress component to the modal
        this.$swal.addVueComponent(RevisionFetchProgress)
        const revision_fetch_progress = this.$swal.getComponent() // get a reference to the component in the modal
        revision_fetch_progress.startWatch()
        // check if cancel is pressed
        modal.then((result) => {
          const { dismiss } = result
            if(dismiss && dismiss==='cancel') {
              this.abort = true // set the flag that stops the loop
              // the promise returned from runRevision is altered to include a cancel token
              this.promise.cancelToken.cancel('operation canceled by user')
            }
        })

        const mrns_count = mrn_list.length
        revision_fetch_progress.progress_total = mrns_count // set the total in the progress bar component
        revision_fetch_progress.mrn_list = mrns_count // set the total in the progress bar component

        const results = {} // collect results for each MRN
        for(let mrn of mrn_list) {
          if(this.abort) break; //break the loop on abort
          // const promise = this.$store.dispatch('revisions/run', {revision_id, mrn})
          // use the API directly to have an altered Promise that contains the cancel token
          // promises returned by vuex dispatch actions cannot be altered
          revision_fetch_progress.mrn = mrn

          this.promise = Vue.$API.runRevision(revision_id, mrn)
          const response = await this.promise
          revision_fetch_progress.progress_value++ // update progress after the response
          const {data} = response
          results[mrn] = data
          revision_fetch_progress.addResults(mrn, data)
        }
        // await delay(1000) // wait 1 seconds for the progress animation to end
        // update the modal when the process is complete
        const icon = revision_fetch_progress.has_errors ? 'warning' : 'success'
        this.$swal.update({
          icon: icon,
          allowOutsideClick: true,
          allowEscapeKey: true,
          showCancelButton: false,
          showConfirmButton: true,
        })

      } catch (error) {
        this.showErrorModal(error)
      }finally {
        this.reloadRevisions()
        this.resetData()
      }
    },
    showErrorModal(response) {
      const { responseText, message='Error fetching data' } = response // get the error message from REDCap
      const title = responseText || message
      this.$swal.fire({
        icon: 'error',
        title: title,
        // html: 'error running the revision',
      })

      if(responseText) {
        const responseJson = JSON.parse(responseText)
        const { errors } = responseJson
  
        this.$swal.addVueComponent(JsonError, {propsData: {errors}})
      }

    },
    /**
     * reload the revisions
     */
    async reloadRevisions() {
      try {
        const response = await this.$API.getRevisions()
        const {data: revisions} = response
        this.$store.dispatch('revisions/setList', revisions)
        this.$store.dispatch('revisions/selectMostRecentRevision')
      } catch (error) {
        this.$swal.fire({
          icon: 'error',
          title: 'error reloading the revisions',
          html: error,
          //timer: 1500
        })
      }
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
  /* button:disabled {
    cursor: help;
  } */
  button {
    transition-property: opacity;
    transition-duration: 150ms;
    transition-timing-function: ease-in-out;
    opacity: 1.0;
  }
</style>
