<template>
  <div class="fhir-resource">
    <!-- form -->
    <router-view />

    <FhirForm class="fhir-form">
      <!-- placeholder for extra fields -->
      <router-view name="extra_fields" ref="extra_fields" v-bind="$route.query"></router-view>
    </FhirForm>

    <!-- submit button -->
    <div class="my-2">
      <button class="btn btn-primary" type="button" :disabled="!canSubmit || loading" @click="submit">
        <span class="mr-2">Fetch</span>
        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
        <i v-else class="fas fa-cloud-download-alt"></i>
      </button>
    </div>


    <!-- placeholder for table -->
    <router-view name="table" :entries="entries"></router-view>
    <!-- clear button -->
    <div class="my-2" v-if="resource">
      <button class="btn btn-info" type="button" @click="onCleanClick">
        <span class="mr-2">clean results</span>
        <i class="fas fa-redo"></i>
      </button>
    </div>

    <!-- json results -->
    <ResourceContainer class="my-2"/>
  </div>
</template>

<script>
import FhirForm from '@/components/FhirForm'
import ResourceContainer from '@/components/ResourceContainer'

export default {
  name: 'FhirResource',
  components: {
    FhirForm,
    ResourceContainer,
  },
  data: () => ({
    loading: false,
    methods: [
      'search',
      'read',
    ],
  }),
  /**
   * check resource type and method on creation
   */
  created() {
    const {meta: {resource_type}, params: {method}} = this.$route
    this.query = this.$route.query
    this.$store.dispatch('endpoint/setResourceType', resource_type)
    this.$store.dispatch('endpoint/setInteraction', method)
  },
  computed: {
    resource() {
      const { resource={} } = this.$store.state.resource
      return resource
    },
    resource_type() {
      const {resource_type} = this.$store.state.endpoint
      return resource_type
    },
    interaction() {
      const {interaction} = this.$store.state.endpoint
      return interaction
    },
    mrn() {
        return this.$store.state.endpoint.mrn
    },
    user_info() {
      return this.$store.state.user.info
    },
    /**
     * get the entries
     */
    entries() {
      try {
        const {entries} = this.$store.state.resource
        return entries
      } catch (error) {
        console.log(error)
        return []  
      }
    },
    canSubmit() {
      try {
        const {has_valid_access_token=false} = this.user_info
        return Boolean(has_valid_access_token && this.resource_type && this.interaction && this.mrn.trim())
      } catch (error) {
        console.log(error)
        return false
      }
    },
  },
  watch: {
    /**
     * resource type and method on creation whenever the route is changed
     */
    $route(to) {
      // react to route changes amd set resource type and interaction...
      const {meta: {resource_type}, params: {method}} = to
      this.$store.dispatch('endpoint/setResourceType', resource_type)
      this.$store.dispatch('endpoint/setInteraction', method)
    }
  },
  methods: {
    /**
     * get parameters from the extra fields if available
     */
    getExtraFieldsQuery() {
      if(!this.$refs.extra_fields) return {}
      if(typeof this.$refs.extra_fields.getQuery !== 'function') return {}
      return this.$refs.extra_fields.getQuery()
    },
    /**
     * update the $route query if is different from the
     * current one
     */
    updateRouteQuery(query) {
      const current_query = JSON.stringify(this.$route.query)
      const new_query = JSON.stringify(query)
      if(current_query!==new_query)
      try {
        this.$router.replace({query})
        return true 
      } catch (error) {
        console.log(error)
        return false
      }
    },
    async submit() {
      if(!this.canSubmit) return
      /* const all_params = this.$store.state.endpoint.params // global params object. contains params for every endpoint
      const params = all_params[endpoint] || [] // get extra params for the current endpoint */
      const resource_type = this.resource_type
      const interaction = this.interaction
      const mrn = this.mrn
        // store previuos query to restore it in case of error
      const previous_query = this.$route.query
      const query = this.getExtraFieldsQuery()

      // set the params in [key, value] array structure
      const params = Object.entries(query)

      try {
        this.loading = true
        this.updateRouteQuery(query)
        await this.$store.dispatch('resource/fetchResource', {resource_type, interaction, mrn, params})
        // update the route if the query ha changed
      } catch (error) {
        // restore the URL with the previous query
        this.updateRouteQuery(previous_query)
        console.error(error)
        const {error:text, response={}} = error
        const {data={}} = response
        const {message=text} = data

        this.$swal.fire({
          title: 'Error',
          icon: 'error',
          text: message || text,
          // confirm_text: 'OK',
          // show_cancel_button: false,
          // show_ok_button: true,
        })
      }finally {
        this.loading = false
      }
    },
    onCleanClick()
    {
      this.$store.dispatch('resource/reset')
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>