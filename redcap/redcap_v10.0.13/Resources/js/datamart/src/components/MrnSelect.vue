<template>
    <div class="wrapper">
        <div class="select-all-toggle">
            <input class="mr-2" type="checkbox" id="select-all" :checked="is_all_selected" @click="toggleSelectAll">
            <label for="select-all">select all</label>
        </div>
        <div class="divider"></div>
        <ul class="list">
            <li v-for="(mrn, index) in mrn_list" :key="index">
                <input class="mr-2" type="checkbox" :id="`mrn-${mrn}`" v-model="selected_mrns" :value="mrn">
                <label :for="`mrn-${mrn}`">{{mrn}}</label>
            </li>
        </ul>
    </div>
</template>

<script>
import EventBus from '@/libraries/EventBus'
import RunRevisionButton from '@/components/buttons/RunRevisionButton'

export default {
    name: 'MrnSelect',
    data: () => ({
        selected_mrns: []
    }),
    props: {
        mrn_list: {
            type: Array,
            default: () => []
        }
    },
    created() {
        this.syncSelectedMrns()
        EventBus.$on('REVISION_SELECTED', this.syncSelectedMrns)
        EventBus.$on('SET_SELECTED_MRNS', this.setSelectedMrns)
    },
    destroyed() {
        EventBus.$off('REVISION_SELECTED', this.syncSelectedMrns)
        EventBus.$off('SET_SELECTED_MRNS', this.setSelectedMrns)
    },
    watch: {
        selected_mrns() {
            this.$store.dispatch('mrns/setList', this.selected_mrns)
        }
    },
    computed: {
        is_all_selected() {
            const total_mrns = this.mrn_list.length
            const total_selected_mrns = this.selected_mrns.length
            return (total_mrns > 0) && (total_mrns === total_selected_mrns)
        },
        modal_message() {
            const total = this.selected_mrns.length
            const singular = total==1
            const message = `The following ${total} MRN number${singular ? ' has' : 's have'} been selected:`
            return message
        }
    },
    methods: {
        toggleSelectAll() {
            if(this.is_all_selected) this.selected_mrns = []
            else this.selected_mrns = this.mrn_list
        },
        /**
         * sync selected mrns with provided mrns
         */
        syncSelectedMrns() {
            this.selected_mrns = this.mrn_list
        },
        setSelectedMrns(list=[]) {
            // make sure to only select available MRNs
            const existing_mrns = list.filter(mrn => this.mrn_list.includes(mrn))
            if(existing_mrns.length==0) return

            this.selected_mrns = existing_mrns // select the provided MRN numbers
            // display a modal with the list
            const list_items = this.selected_mrns.map(mrn => `<li>${mrn}</li>`).join('')
            const total = this.selected_mrns.length
            const singular = total==1
            const message = `The following ${total} MRN number${singular ? ' has' : 's have'} been selected:`
            const html = `<div class="text-left" style="max-height:300px;overflow-y:scroll;"><span>${message}</span><ul class="mt-2">${list_items}</ul></div>`
            this.$swal.fire({
                icon: 'success',
                toast: false,
                html,
                showConfirmButton: true,
            })
        }
    }
}
</script>

<style scoped>
.wrapper {
    padding: 0 10px;
}
.divider {
    border-bottom: solid 1px #cacaca;
    /* balance padding of the container with a negative margin*/
    margin: 0 -10px; 
}
ul.list {
    margin: 0;
    padding: 0;
    list-style-type: none;
    max-height: 300px;
    overflow-y: scroll;
}
.select-all-toggle,
.list li {
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    margin-bottom: 5px;
}
.select-all-toggle label,
.list label {
    flex: 1;
    margin: 0;
}
.select-all-toggle label {
    font-weight: bold;
    user-select: none;
}
label:hover,
input:hover {
    cursor: pointer;
}
</style>