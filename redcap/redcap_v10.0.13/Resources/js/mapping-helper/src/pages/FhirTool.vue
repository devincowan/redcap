<template>
  <div class="container">

    <!-- resource type -->
    <select class="form-control" v-model="selected_resource_type">
      <option value="" disabled>Select a resource type</option>
      <option v-for="(resource_type_object, key) in resource_types" :key="key" :value="key">{{resource_type_object.label}}</option>
    </select>
    
    <select class="form-control" v-model="selected_interaction">
      <option value="" disabled>Select an interaction method</option>
      <option v-for="(_interaction, index) in interactions" :key="index" :value="_interaction">{{_interaction}}</option>
    </select>

    <ParameterContainer />
  </div>

</template>

<script>
import ParameterContainer from '@/components/search_parameters/ParameterContainer'


export default {
  name: 'FhirTool',
  components: {ParameterContainer},
  data: () => ({
    loading: false,
    resource_types: {
      Patient: {
        label: 'Demographics',
      },
      MedicationOrder: {
        label: 'Medications',
      },
      Observation: {
        label: 'Labs and vitals',
      },
      Condition: {
        label: 'Problem list',
      },
    },
    interactions: [
      'search',
      'read',
    ]
  }),
  computed: {
    selected_resource_type: {
      get() {
        let {resource_type} = this.$store.state.endpoint
        const default_resource_type = Object.keys(this.resource_types)[0]
        if(!resource_type) resource_type = default_resource_type
        return resource_type
      },
      set(value) {
        this.$store.dispatch('endpoint/setResourceType', value)
      },
    },
    selected_interaction: {
      get() {
        let {interaction} = this.$store.state.endpoint
        const default_interaction = this.interactions[0]
        if(!interaction) interaction = default_interaction
        return interaction
      },
      set(value) {
        this.$store.dispatch('endpoint/setInteraction', value)
      },
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.container {
  display: flex;
  flex-direction: row;
  align-items: flex-start;

  margin-bottom: 10px;
  display: flex;
  flex-direction: row;
  border-radius: 3px;
  border-color: #cacaca;
  border-style: solid;
  border-width: 1px;
  background-color: #fafafa;
  padding: 15px;
}
.container > * + * {
  margin-left: 3px;
}
</style>