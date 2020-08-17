<template>
  <div class="string-identifier">
    <p>The SSN of a patient can be used to retrieve patient data.</p>
    <ul>
      <li>THE SSN is represented in resources with dashes removed</li>
      <li>The OID system to use is 2.16.840.1.113883.4.1</li>
    </ul>
    <div class="form-group mb-2">
      <label for="ssn" class="mr-2">SSN</label>
      <input type="text" class="form-control" v-model="ssn" id="ssn" />
    </div>
    <button type="button" class="btn btn-primary" @click="search">Search</button>
   
    <ResourceContainer />
  </div>
</template>

<script>

const search_by_ssn = '2.16.840.1.113883.4.1|555668877'
const url_search_by_ssn = 'https://ic1-dev.service.vumc.org/Interconnect-DEV-FHIR/api/FHIR/DSTU2/Patient/?identifier=2.16.840.1.113883.4.1|555668877'

import ResourceContainer from '@/components/ResourceContainer'

export default {
  name: 'StringIdentifier',
  components: {
    ResourceContainer,
  },
  data: () => ({
    ssn: '',
  }),
  computed: {
    resource() {
      try {
        const { resource={} } = this.$store.state.resource
        const {metadata:{resourceType}} = resource
        // make sure the resource is of type patient
        if(resourceType!=='Patient') return {}
        return resource
      } catch (error) {
        return {}
      }
    },
    data() {
      const {data={}} = this.resource
      return data
    },
  },
  methods: {
    async search() {
      const response = await this.$API.getFhirResource('Patient.search', this.ssn)
      console.log(response)
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>