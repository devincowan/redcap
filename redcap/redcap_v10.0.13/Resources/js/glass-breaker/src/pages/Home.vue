<template>
  <div>
    <div v-if="loading" class="m-5">
      <p>
        <i class="fas fa-spinner fa-spin"></i>
        Initializing...
      </p>
    </div>
    <div v-if="error">
      <div class="alert alert-danger" role="alert">
        <p>Error initializing the Break the Glass information</p>
        <Error :message="error_message" :code="error_code" />
        <button type="button" class="btn btn-sm btn-primary" @click="initialize">Retry</button>
      </div>
    </div>
    <AcceptForm v-if="!loading && !error"/>
  </div>
</template>

<script>
/**
 * here is where the component is initialized
 */
import AcceptForm from '@/components/AcceptForm'
import Error from '@/components/Error'

export default {
  name: 'HomePage',
  components: {AcceptForm, Error},
  data: () => ({
    loading: false,
    error: false,
    error_message: null,
    error_code: null,
  }),
  created() {
    // trigger inizialization on component creation
    this.initialize()
  },
  computed: {
    reasons() {
      return this.$store.information.Reasons
    }
  },
  methods: {
    /**
     * get initialize information
     */
    async initialize() {
      try {
        this.loading = true
        this.error = false
        const response = await this.$store.dispatch('information/initialize')
        console.log(response)
      } catch (error) {
        const {response} = error
        const {data: {message, code}} = response
        this.error = true
        this.error_message = message
        this.error_code = code
      } finally {
        this.loading = false
      }

    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>