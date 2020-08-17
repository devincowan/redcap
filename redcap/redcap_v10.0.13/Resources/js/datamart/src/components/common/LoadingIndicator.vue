<template>
  <div class="loading-indicator text-center">
    <h3>Loading <span>{{value}}/{{total}}</span></h3>

    <section class="progress-container">
      <div class="progress">
          <div class="progress-bar" role="progressbar" :style="{width: `${percentage}%`}" :aria-valuenow="value" aria-valuemin="0" :aria-valuemax="total"></div>
      </div>
    </section>

    <h3>please wait</h3>
  </div>
</template>

<script>
// import {getColor} from '@/libraries/utils'

const RADIUS = 60

export default {
  name: 'LoadingIndicator',
  props: {
    value: {
      type: Number,
      default: 0
    },
    total: {
      type: Number,
      default: 100
    },
    radius: {
      type: Number,
      default: RADIUS
    }
  },
  computed: {
    style() {
      const progress = this.value / this.total
      const dashoffset = this.circumference * (1 - progress)
      return {
        strokeDasharray: this.circumference,
        strokeDashoffset: dashoffset,
        // stroke: getColor(1-this.value/this.total),
      }
    },
    circumference() {
      return 2 * Math.PI * this.radius
    },
    size() {
      return this.radius*3
    },
    /**
     * get the percentage to use in the progressbar
     */
    percentage() {
        if(this.value===0) return 0
        return this.value/this.total*100
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
div.loading-indicator {
  position: relative;
  width: 100%;
}
.progress {
    width: 50%;
    margin: auto;
}
</style>
