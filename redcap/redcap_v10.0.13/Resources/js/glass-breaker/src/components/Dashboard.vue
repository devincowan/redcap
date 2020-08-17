<template>
  <div>
        <div v-if="mrns.length && !error">
            <button class="btn btn-sm btn-info" @click="reviewBlockedMrns" :disabled="loading">
                <span v-if="loading">
                    <i class="fas fa-spinner fa-spin"/> Loading
                </span>
                <span v-else>
                    <i class="fas fa-lock"></i> Protected patients detected <span class="badge badge-danger" v-text="mrns.length"></span>
                </span>
            </button>
        </div>
        <!-- <div v-if="error">
          <p>Break the glass</p>
          <pre>{{error}}</pre>
          <button class="btn btn-sm btn-primary" @click="start">retry</button>
        </div> -->
<!--       <div v-else>
          <button class="btn btn-sm btn-info" @click="reviewBlockedMrns">Review blocked MRNs <span class="badge badge-light" v-text="mrns.length"></span></button>
          <button class="btn btn-sm btn-primary" @click="getMrnList" :disabled="loading">check <i :style="{display: loading ? 'inline-block' : 'none'}" class="fas fa-spinner fa-spin"></i></button>
      </div> -->
  </div>
</template>

<script>
import { mapState } from 'vuex'
import AcceptForm from '@/components/AcceptForm'
import {initialState as information_default} from '@/store/modules/information'

function noDelaySetInterval(func, interval=0)
{
    func()
    return setInterval(func, interval)
}

export default {
    data() {
        return {
            loading: false,
            error: false,
            interval_handle: null,
            interval_amount: 60*1000
        }
    },
    computed: {
        /**
         * connect read-only states from the store
         */
        ...mapState({
            reasons: state => state.information.Reasons,
            legal_message: state => state.information.LegalMessage,
            mrns: state => state.mrns.list
        }),
    },
    created() { 
        this.start()
    },
    destroyed() {
        clearInterval(this.interval_handle)
    },
    methods: {
        async start() {
            this.error = false
            // await this.initialize()
            // if(this.error) return // stop here if we have errors initializing
            // fetch the mrn list
            this.pollMrnList()
        },
        pollMrnList() {
            clearInterval(this.interval_handle)
            this.interval_handle = noDelaySetInterval(async () => {
                this.getMrnList()
            }, this.interval_amount) 
        },
        async getMrnList() {
            try {
                this.loading = true
                const response = await this.$API.getProtectedMrnList()
                const {data} = response
                this.$store.dispatch('mrns/setList', data)
            } catch (error) {
                console.log(error)
                this.error = error
            }finally {
                this.loading = false
            }
        },
        /**
         * get values from the initialize endpoint
         */
        async initialize() {
            try {
                this.loading = true
                const response = await this.$store.dispatch('information/initialize')
                return true
            } catch (error) {
                const {response={}} = error
                const {data: {message=error, code=0}={}} = response
                 this.$swal.fire({
                    icon: 'error',
                    allowOutsideClick: true,
                    allowEscapeKey: true,
                    showConfirmButton: true,
                    showCancelButton: false,
                    text: message,
                })
                this.error = `error initializing:\r\n${message}`
                return false
            } finally {
                this.loading = false
            }

        },
        /**
         * show the dialog that allows the user
         * to break the glass for blocked MRNs
         */
        async reviewBlockedMrns() {
            const initialized = await this.initialize()
            if(!initialized) {
                const message = this.error || 'Error initializing the "break the glass" process.'
                this.$swal.fire({
                    icon: 'error',
                    allowOutsideClick: true,
                    allowEscapeKey: true,
                    showConfirmButton: true,
                    showCancelButton: false,
                    html: message,
                })
                return
            }
            const modal = this.$swal.fire({
                // icon: 'info',
                allowOutsideClick: true,
                allowEscapeKey: false,
                showConfirmButton: false,
                showCancelButton: false,
            })
            AcceptForm.store = this.$store
            this.$swal.addVueComponent(AcceptForm)
            const accept_form = this.$swal.getComponent()
            // close when the cancel button is clicked
            accept_form.$on('cancel', this.$swal.close)
            accept_form.$on('done', params => {
                this.$swal.close()
                const results = accept_form.results
                this.onReviewDone(results, params)
            })
            accept_form.reasons = this.reasons
            /**
             * the legal message coming from Epic usually contains
             * typos (newlines stripped)
             * so we use a static text instead
             */
            // accept_form.legal_message = this.legal_message
            accept_form.legal_message = information_default.LegalMessage
            accept_form.mrns = this.mrns
        },
        /**
         * process the results and return
         * a message and a status to be displayed in 
         */
        processResults(results) {
            let text = ``
            let status = 'success'
            const failed = []
            for (let[mrn, data] of Object.entries(results)) {
                const {success=''} = data
                if(!success) failed.push(mrn)
            }
            // update icon and message if unlocked MRNs are found
            if(failed.length>0) {
                status = 'warning'
                const failed_mrns = failed.join(', ')
                text += `The following MRNs could not be unlocked: ${failed_mrns}.`
            }
            return {status,text}
        },
        onReviewDone(results, {message=''}) {
            const {text,status} = this.processResults(results)
            this.$swal.fire({
                icon: status,
                allowOutsideClick: true,
                allowEscapeKey: true,
                showConfirmButton: true,
                showCancelButton: false,
                title: message,
                text: `${text}`,
            })
            // reload the list after the review has been made
            this.getMrnList()
        }
    }
}
</script>

<style>

</style>