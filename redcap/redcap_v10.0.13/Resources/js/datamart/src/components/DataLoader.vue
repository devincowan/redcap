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
        <span>{{label}}</span> <span>{{progress_value}}/{{progress_total}}</span>
      </p>
      <ProgressBar :total="progress_total" :value="progress_value" />
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
    progress_total: 0,
    progress_value: 0,
    error: null,
    label: 'loading...',
  }),
  props: {},
  computed: {
    api_calls() {
      // promise with error for debugging purposes
      const methods = [
        {
          label: 'user info loaded',
          method: () => this.$API.getUser(),
        },
        {
          label: 'source fields loaded',
          method: () => this.$API.getSourceFields(),
        },
        {
          label: 'settings loaded',
          method: () => this.$API.getSettings(),
        },
        /* {
          label: 'error test',
          method: () => this.error_test(),
        }, */
        {
          label: 'revisions loaded',
          method: () => this.$API.getRevisions(),
        },
      ]
      return methods
    },
  },
  created() {
    this.loadData()
  },
  methods: {
    async loadData() {
      const methods = this.api_calls
      this.progress_total = methods.length
      const promises = [] // keep track of all promises
      // run all methods and update progress and label
      for(let {label, method} of methods) {
        // increase the progress every time a promise is fulfilled
        let promise = method().then((response) => {
          this.label = label
          this.progress_value++
          return response
        })
        promises.push(promise) // collect each promise
      }
      // emit onLoad when all promises are resolved
      Promise.all(promises)
        .then((data) => {
          // wait a little bit for a smooth trasnition
          this.setStoreData(data)
          setTimeout(()=>{
              this.$emit('onLoad', data)
            }, 500)
        })
        .catch(error => {
          console.log('error', error)
          this.error = error
          this.$emit('onError', error)
        })
    },
    async setStoreData(data) {
      // collect the data in the expected order
      const [user, nodes, settings, revisions ] = data
      // the order is important: revisions must be set after the nodes
      const actions = [
        {
          message: 'setting user info',
          action: () => this.$store.dispatch('settings/set', settings.data),
        },
        {
          message: 'setting source fields',
          action: () => this.$store.dispatch('user/setInfo', user.data),
        },
        {
          message: 'setting translations and magic',
          action: () => this.$store.dispatch('nodes/setNodes', nodes.data),
        },
        {
          message: 'setting revisions',
          action: () => {
            this.$store.dispatch('revisions/setList', revisions.data)
            this.$store.dispatch('revisions/selectMostRecentRevision')
          },
      },
      ]
      for(let {message, action} of actions) {
        this.label = message
        await action()
      }
    },
    error_test() {
        const promise = new Promise((resolve, reject) => { 
          setTimeout(() => {
            const error = new Error('reject test')
            reject(error)
          }, 2000)
        })
        return promise
      }
  }
}
</script>

<style scoped>
.loader {
  width: 400px;
  max-width: 100%;
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
