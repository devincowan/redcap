<template>
    <form class="accept-form" @submit="checkForm">
        <p>{{legal_message}}</p>

        <div class="form-group" :class="{error: validate($v.checked_mrns)}">
            <div class="d-flex mb-1">
                <label>
                    <span>Medical Record Numbers </span>
                    <i class="fas fa-info-circle" title="select the MRNs in order to get access"></i>
                </label>
                <button class="ml-auto btn btn-sm" :class="all_selected_class" type="button" @click="toggleSelectMrnList">
                    <span v-if="all_mrns_selected">
                        <i class="far fa-square"></i> Deselect all
                    </span>
                    <span v-else>
                        <i class="far fa-check-square"></i> Select all
                    </span>
                </button>
            </div>
            <div class="mrn-list border p-2">
                <div class="form-check" v-for="(mrn, index) in mrns" :key="index">
                    <input class="form-check-input" type="checkbox" v-model="checked_mrns" :id="`mrn-${index}`" :value="mrn">
                    <label class="form-check-label" :for="`mrn-${index}`">{{mrn}}</label>
                </div>
            </div>
        </div>

        <div class="form-group" :class="{error: validate($v.reason)}">
            <label for="reason">
                <span>Reason </span>
                <i class="fas fa-info-circle" title="Select a reason from the drop-down menu to explain why you need to access this patient's record. You may enter any additional explanation below"></i>
            </label>
            <select class="form-control" id="reason" v-model="reason">
                <option value='' disabled>Select a reason</option>
                <option v-for="(reason, index) in reasons" :key="index" :value="reason">{{reason}}</option>
            </select>
        </div>

        <div class="form-group" :class="{error: validate($v.explanation)}">
            <label for="explanation-textarea">
                <span>Explanation </span>
                <i class="fas fa-info-circle" title="The explanation for breaking the glass"></i>
            </label>
            <textarea class="form-control" id="explanation-textarea" rows="3" v-model="explanation"></textarea>
        </div>

        <div class="form-group" :class="{error: validate($v.department)}">
            <label for="department">
                <span>Department </span>
                <i class="fas fa-info-circle" title="The department where you will break the glass"></i>
            </label>
            <!-- <input type="text" class="form-control" id="department" v-model="department" placeholder="example: 101000206"/> -->
            <StorageInput storage_key="department" v-model="department" id="department" placeholder="example: 101000206"/>
        </div>

        <div class="form-group">
            <label for="department-type">
                <span>Department type </span>
                <i class="fas fa-info-circle" title="The type of the provided department ID"></i>
            </label>
            <select class="form-control" id="department-type" v-model="department_type">
                <option v-for="(department_type, index) in department_types" :key="index" :value="department_type">{{department_type}}</option>
            </select>
        </div>

        <div class="form-group" :class="{error: validate($v.password)}">
            <label for="password">
                <span>REDCap password </span>
                <i class="fas fa-info-circle" title="Provide your password to proceed"></i>
            </label>
            <input type="password" class="form-control" id="password" v-model="password" />
            <span class="error" v-if="authentication_error">Error: {{authentication_error}}</span>
        </div>

        <div class="form-group buttons">
            <button class="btn btn-sm btn-outline-success" type="submit" :disabled="processing || $v.$invalid">
                <span><i class="fas fa-lock-open"></i> Break the glass</span>
                <section v-if="processing" id="button_loader" :style="{width: `${processed_percentage}%`}"></section>
                </button>
            <button class="btn btn-sm btn-outline-secondary ml-2" type="button" @click="onCancel"><i class="far fa-times-circle"></i> Cancel</button>
            <div v-if="processing" class="processing-info ml-2">
                <i class="fas fa-spinner fa-spin"></i>
                <span> Processing {{results_count}}/{{checked_mrns.length}}</span>
            </div>
        </div>
    </form>
</template>

<script>
import { mapState } from 'vuex'
import { required, minLength } from 'vuelidate/lib/validators'
import {CancelToken, isCancel} from 'axios'
import StorageInput from '@/components/StorageInput'

export default {
    data() {
        const department_types = ['Internal', 'External', 'ExternalKey', 'Name', 'CID', 'IIT']
        return {
            checked_mrns: [],
            reason: null,
            explanation: '',
            department: '',
            department_types,
            department_type: department_types[0],
            password: '', // REDCap password must be provided
            authentication_error: null,
            // processing variables
            cancelRequest: null, // used to stop the current ajax request
            processing: false,
            processed_mrn: null,
            results: {},
        }
    },
    components: {StorageInput},
    props: {
        reasons: {
            type: Array,
            default: () => []
        },
        legal_message: {
            type: String,
            default: ''
        },
        mrns: {
            type: Array,
            default: () => []
        },
    },
    computed: {
        /**
         * class for the mrn select toggle button
         */
        all_selected_class() {
            return this. all_mrns_selected ? 'btn-success' : 'btn-outline-secondary'
        },
        all_mrns_selected() {
            const all_selected = this.checked_mrns.length === this.mrns.length
            return all_selected
        },
        results_count() {
            return Object.keys(this.results).length
        },
        // get the progress percentage
        processed_percentage() {
            const selected_count = this.checked_mrns.length
            if(this.results_count<=0 || selected_count<=0) return 0
            return this.results_count/selected_count * 100
        }
    },
    methods: {
        /**
         * check if the form is valid before submitting
         */
        async checkForm(e) {
            e.preventDefault();
            if(this.$v.$invalid) return false
            // try to authenticate, then perform break the glass
            this.breakTheGlass()
        },
        /**
         * validate a model checking if is dirty and invalid.
         * used to determine the "error" class in the form elements
         */
        validate(validation_model) {
            return validation_model.$dirty && validation_model.$invalid
        },
        /**
         * toggle MRN list selection
         */
        toggleSelectMrnList() {
            if(this.checked_mrns.length < this.mrns.length) {
                this.checked_mrns = [...this.mrns]
            }else {
                this.checked_mrns = []
            }
        },
        /**
         * dispatch a break the glass API call
         */
        async breakTheGlass() {
            try {
                // collect checked MRNs
                const mrns = [...this.checked_mrns]
                // common params for all requests
                const common_params = {
                    reason: this.reason,
                    explanation: this.explanation,
                    department: this.department ,
                    department_type: this.department_type,
                    password: this.password
                }
                // reset the processing params
                this.results = {}
                this.processing = true
                for(let mrn of mrns) {
                    // exit if the process is stopped (using the cancel button)
                    if(!this.processing) break
                    this.processed_mrn = mrn
                    // create a cancel token to stop the request
                    const source = CancelToken.source()
                    this.cancelRequest = source.cancel
                    const request_params = Object.assign(common_params, {mrn})
                    const response = await this.$API.accept(request_params, {cancelToken: source.token})
                    this.authentication_error = null
                    const {data} = response
                    this.$set(this.results, mrn, data)
                }
                this.$emit('done', {message: 'Process complete'})
            } catch (error) {
                const {response:{data={}}={}} = error
                const {message='error', code=403} = data
                // detect if we have a REDCap authentication error (wrong password)
                if(code==403) {
                    this.authentication_error = message
                }else {
                    this.$emit('done', {message: error, icon: 'error'})
                }
                /* if(typeof error == 'object' && error.constructor.name == 'Cancel') {
                    // detect if the user canceled the ajax process
                    this.$emit('cancel', {message: error})
                }else {
                    this.$emit('done', {message: error})
                } */
                // this.$emit('done', {message: error, icon: 'error'})
            }finally {
                this.processing = false
                this.processed_mrn = null
                this.cancel_source = null
            }
        },
        onCancel() {
            this.processing = false
            if( typeof this.cancelRequest == 'function' ) {
                // cancel the ajax request if any
                this.cancelRequest('Operation canceled by the user')
            }
            // standard cancel with no ongoing process
            this.$emit('cancel')
        }
    },
    validations: {
        checked_mrns: {
            required,
            minLength: minLength(1)
        },
        explanation: {
            required,
        },
        reason: {
            required,
        },
        department: {
            required,
        },
        password: {
            required,
        },
    }
}
</script>

<style scoped>
.accept-form {
    max-width: 500px;
    margin: auto;
    text-align: left;
    font-size: 14px;
}
label {
    font-weight: bold;
}
label i {
    cursor: help;
}
.mrn-list {
    max-height: 100px;
    overflow-y: auto;
}
.error {
    color: red;
    font-style: italic;
    font-size: 0.8rem;
}
/* processing styles */
.buttons {
    display: flex;
    flex-direction: row;
    align-items: center;
}
.processing-info {
    display: inline-block;
}
button[type="submit"] {
    position: relative;
}
#button_loader {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background-color: rgba(50,255,0, .5);
    width: 0;
    transition-timing-function: ease-in-out;
    transition-duration: 300ms;
    transition-property: all;
}
</style>