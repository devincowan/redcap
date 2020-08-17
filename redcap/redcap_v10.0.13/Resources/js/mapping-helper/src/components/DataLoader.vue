<template>
  <div>

    <div class="error" v-if="error">
      <p>
        <i class="fas fa-exclamation-triangle"></i>
        Error loading required data from one of the endpoints!
      </p>
      <pre>({{error}})</pre>
    </div>
    <div class="loader" v-else>
      <p class="load-text">
         <span>Loading {{label}}</span> <span>{{progress}}/{{total}}</span>
      </p>
      <ProgressBar :total="total" :progress="progress" />
    </div>
  </div>
</template>

<script>
import ProgressBar from '@/components/ProgressBar'


export default {
  /**
   * this companent only purspose is to preload
   * mandatory data
   */
  name: 'DataLoader',
  components: {ProgressBar},
  data: () => ({
    total: 0,
    progress: 0,
    error: null,
  }),
  props: {
    /**
     * data to load for the app
     * the order is important
     */
    promises: {
      type: Array,
      default: () => []
    }
  },
  created() {
    this.loadData()
  },
  methods: {
    async loadData() {
      const promises = this.promises
      this.total = promises.length
      this.progress = 0

      for(let {label, promise} of promises) {
        try {
          this.label = label
          this.progress = this.progress+1
          await promise()
          if(this.progress >= this.total) {
            setTimeout(()=>{
              this.$emit('onLoad')
            }, 500)
          }
        }catch(error) {
          this.error = error
          this.$emit('onError', error)
        }
      }
    },
  }
}
</script>

<style scoped>
.loader {
  width: 400px;
}
.load-text span {
  font-size: 20px;
}
.load-text {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
}
</style>
