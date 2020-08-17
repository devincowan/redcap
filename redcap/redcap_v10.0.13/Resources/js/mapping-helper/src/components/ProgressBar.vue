<template>
    <div class="progress">
        <div class="progress-bar" role="progressbar" :style="{width: `${width}%`}" :aria-valuenow="width" :aria-valuemin="0" :aria-valuemax="total"></div>
    </div>
</template>

<script>
export default {
    name: 'ProgressBar',
    props: {
        total: {
            type: Number,
            default: 0
        },
        progress: {
            type: Number,
            default: 0
        },
        color: {
            type: String,
            default: 'green'
        },
    },
    computed: {
        width() {
            if(this.total<=0) return 0
            return 100/this.total*this.progress
        }
    }
}
</script>