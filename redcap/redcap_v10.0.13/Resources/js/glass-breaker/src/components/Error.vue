<template>
    <div>
      <p v-html="message"></p>
      <p class="font-italic">(code {{code}})</p>
    </div>
</template>

<script>
export default {
    name: 'Error',
    props: {
        message: {
            type: String,
            default: 'error'
        },
        code: {
            type: Number,
            default: 400,
        }
    }
}
</script>

<style>

</style>