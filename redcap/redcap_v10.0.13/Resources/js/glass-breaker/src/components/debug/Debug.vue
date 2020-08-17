<template>
  <div class="mt-2">
    <ResetBasketButton />
    <GetListButton class="ml-2" />
  </div>
</template>

<script>
import ResetBasketButton from '@/components/debug/ResetBasketButton'
import GetListButton from '@/components/debug/GetListButton'

export default {
    components: {
        ResetBasketButton,GetListButton,
    },
    methods: {}
}
</script>

<style>

</style>