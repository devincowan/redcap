<template>
    <input type="text" class="form-control" ref="date_picker" @keyup.delete="onDelete" @input="onInput" readonly>
</template>

<script>

import $ from 'jquery'
import 'jquery-ui/ui/widgets/datepicker'
import 'jquery-ui/themes/base/core.css'
import 'jquery-ui/themes/base/datepicker.css'
import 'jquery-ui/themes/base/theme.css'
import moment from 'moment'

const display_date_format = 'MM-DD-YYYY'
const store_date_format = 'YYYY-MM-DD'

export default {
    name: 'DatePicker',
    props: {
        value: {
            type: [String,Date],
            default: ''
        },
        min_date: {
            type: String
        },
        max_date: {
            type: String
        },
    },
    mounted() {
        const component = this.$refs.date_picker
        const config = {
            changeMonth: true,
            changeYear: true,
            dateFormat: 'mm-dd-yy',
            constrainInput: false,
            onSelect: (date_text, instance) => {
                // trigger an onInput when date is selected
                this.onInput()
            }
        }
        $(component).datepicker(config)

        if(this.value) this.set_value()
        if(this.min_date) this.set_min_date()
        if(this.max_date) this.set_max_date()

    },
    watch: {
        // update the datepicker on value changes
        value() {
            this.set_value()
        },
        // update the datepicker on value changes
        min_date(value) {
            this.set_min_date(value)
        },
        // update the datepicker on value changes
        max_date(value) {
            this.set_max_date(value)
        },
    },
    methods: {
        // set the date
        onDelete() {
            this.$emit('input', '')
        },
        set_value() {
            if(!this.value) {
                $(this.$refs.date_picker).datepicker("setDate", null)
            }else {
                const formatted_date = moment(this.value).format(display_date_format)
                $(this.$refs.date_picker).datepicker("setDate", formatted_date)
            }
        },
        set_min_date(value) {
            const date = moment(this.min_date, store_date_format).toDate()
            $(this.$refs.date_picker).datepicker("option", "minDate", date)
        },
        set_max_date(value) {
            const date = moment(this.max_date, store_date_format).toDate()
            $(this.$refs.date_picker).datepicker("option", "maxDate", date)
        },
        onInput(event) {
            const date = this.getDate()
            const formatted_date = moment(date).format(store_date_format)
            this.$emit('input', formatted_date)
        },
        getDate() {
            const component = this.$refs.date_picker
            return $(component).datepicker( "getDate" )
        }
    }
}
</script>

<style scoped>
 input.form-control[readonly] {
     background-color: #fff;
 }
</style>