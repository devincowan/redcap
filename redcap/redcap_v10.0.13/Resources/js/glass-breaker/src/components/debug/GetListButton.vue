<template>
    <button class="btn btn-sm btn-info" @click="getMrnList" :disabled="loading">
        <i v-if="loading" class="fas fa-spinner fa-spin"/>
        <i v-else class="fas fa-download"/>
        <span> get list</span>
    </button>
</template>

<script>
export default {
    data() {
        return {
            loading: false,
        }
    },
    methods: {
        async getMrnList() {
            try {
                this.loading = true
                const response = await this.$API.getProtectedMrnList()
                const {data} = response
                this.$store.dispatch('mrns/setList', data)
            } catch (error) {
                console.error(error)
            }finally {
                this.loading = false
            }
        }
    }
}
</script>

<style>

</style>