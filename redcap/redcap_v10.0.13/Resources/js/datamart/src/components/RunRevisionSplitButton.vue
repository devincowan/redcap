<template>
    <SplitButton btn_class="btn btn-success" :dropdown_disabled="mrn_list.length<1">
        <template v-slot:button>
            <RunRevisionButton />
        </template>
        <template v-slot:dropdown-content>
            <MrnSelect :mrn_list="mrn_list" />
        </template>
    </SplitButton>
  
</template>

<script>
import SplitButton from '@/components/buttons/SplitButton'
import RunRevisionButton from '@/components/buttons/RunRevisionButton'
import MrnSelect from '@/components/MrnSelect'

export default {
    name: 'RunRevisionSplitButton',
    components: {
        SplitButton,
        RunRevisionButton,
        MrnSelect,
    },
    computed: {
        mrn_list() {
            const revision = this.$store.getters['revisions/selected']
            if(!revision) return []
            const { metadata: { fetchable_mrns=[] } } = revision
            return fetchable_mrns
        }
    }
}
</script>

<style>

</style>