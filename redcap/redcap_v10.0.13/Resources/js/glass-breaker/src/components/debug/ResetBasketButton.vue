<template>
    <button class="btn btn-sm btn-danger" @click="resetBasket" :disabled="loading">
        <i v-if="loading" class="fas fa-spinner fa-spin"/>
        <i v-else class="fas fa-trash"/>
        <span> empty basket</span>
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
        async resetBasket() {
            try {
                this.loading = true
                await this.$API.clearProtectedMrnList()
                this.$store.dispatch('mrns/setList', [])
            } catch (error) {
                console.error(error)
            }finally {
                this.loading = false
            }
        },
    }
}
</script>

<style>

</style>