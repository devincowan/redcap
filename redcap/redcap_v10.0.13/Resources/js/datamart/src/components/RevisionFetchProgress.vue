<template>
    <div>
        <!-- processing -->
        <div v-if="is_processing">
            <h2>Fetching data</h2>
            <div>
                <p class="time">Elapsed time: <span class="stopwatch">{{fixed_time}} ({{readable_time}})</span></p>
                <ProgressBar class="mb-2" ref="progress_bar" :value="progress_value" :total="progress_total"/>
                <p>
                    <span>Processing <strong>{{mrn}}</strong> - {{progress_value+1}}/{{progress_total}} </span>
                    <i class="fas fa-spinner fa-spin"></i>
                </p>
            </div>
        </div>
        <div v-else>
            <!-- stats and execution time -->
            <p class="time">The request took {{readable_time}} ({{fixed_time}} seconds)</p>
            <div>
                <p v-if="overall_stats_count==0" class="text-center">Data across all patients was already up to date.</p>
                <p v-else class="text-center">Here is a summary of the data pulled across all patients:</p>
            </div>
        </div>
        <!-- errors -->
        <transition name="fade">
            <Stats :stats="fhir_stats" />
        </transition>
        <transition name="fade">
            <FhirErrors class="errors-container" :errors="fhir_errors" />
        </transition>
        <div v-if="!is_processing && has_errors">
            <button class="btn btn-secondary mt-2" @click="selectMrns">Select the MRN identifiers with fetch errors</button>
        </div>
    </div>
</template>



<script>
import EventBus from '@/libraries/EventBus'
import moment from 'moment'
import anime from 'animejs/lib/anime.es.js'
import Stopwatch from '@/libraries/Stopwatch'
import ProgressBar from '@/components/ProgressBar'
import Stats from '@/components/Stats'
import FhirErrors from '@/components/FhirErrors'

export default {
    name: '',
        components: {
        ProgressBar,
        Stats,
        FhirErrors,
    },
    data: () => ({
        stopwatch: new Stopwatch(false), // create a stopwatch and start it
        time: 0.0,
        stopwatch_interval: null,
        fhir_errors: {},
        fhir_stats: {},
        overall_stats_count: 0, // overall count of the stats
    }),
    destroyed() {
        // clear interval if set
        this.clearInterval()
    },
    props: {
        progress_value: {
            type: Number,
            default: 0
        },
        progress_total: {
            type: Number,
            default: 0
        },
        /**
         * list of processed MRNs
         */
        mrn_list: {
            type: Array,
            default: () =>[]
        },
        /**
         * the currently processed MRN
         */
        mrn: {
            type: String,
            default: ''
        },
    },
    computed: {
        is_processing() {
            return this.progress_value<this.progress_total
        },
        fixed_time() {
            const seconds = this.time/1000
            return seconds.toFixed(2);
        },
        readable_time() {
            const seconds = this.time/1000
            return moment.duration(this.time,'milliseconds').humanize()
        },
        has_errors() {
            return Object.keys(this.fhir_errors).length>0
        }
    },
    watch: {
        /**
         * stop the watch when the process is complete
         */
        progress_value(value) {
            if(value>=this.progress_total) this.stopWatch()
        }
    },
    methods: {
        startWatch() {
            this.stopwatch.start()
            this.stopwatch_interval = setInterval(() => {
                this.time = this.stopwatch.total
            },10)
        },
        stopWatch() {
            this.stopwatch.stop()
            clearInterval()
        },
        /**
         * select MRN numbers in MrnSelect
         */
        selectMrns() {
            const list = Object.keys(this.fhir_errors)
            EventBus.$emit('SET_SELECTED_MRNS', list)
        },
        clearInterval() {
            if(this.stopwatch_interval) clearInterval(this.stopwatch_interval)
        },
        addResults(mrn, data) {
            var battery = {
                charged: '0%',
                cycles: 120
            }


            anime({
                targets: battery,
                charged: '100%',
                cycles: 130,
                round: 1,
                easing: 'linear',
                update: function() {
                    const stringified = JSON.stringify(battery)
                    // console.log(stringified)
                }
            })

            const {errors=[], metadata: {stats=[]}} = data
            // manage stats
            for(let [resource_type, count] of Object.entries(stats)) {
                this.overall_stats_count += count // increment the overall count
                // make sure the property we are going to increase is a number
                let total = this.fhir_stats[resource_type] || 0 
                this.$set(this.fhir_stats, resource_type, count+total)
            }
            // manage errors
            if(errors.length>0) this.$set(this.fhir_errors, mrn, errors)
        }
    }
}
</script>

<style scoped>
.stopwatch {
    font-variant-numeric: tabular-nums; /* fixed width digits */
}
.time {
    text-align: center;
    font-weight: 300;
}
.errors-container {
    max-height: 200px;
    overflow-y: scroll;
}
/* transition */
.fade-enter,
.fade-leave-active {
  opacity: 0;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity .3s ease-out;
}
/* transition */
</style>