<template>
    <form class="fhir-form my-2">
      <div class="form-group mb-2">
        <label for="mrn" class="mr-2 font-weight-bold">Medical Record Number</label>
        <input type="text" class="form-control" id="mrn" v-model="mrn" placeholder="MRN">
      </div>
      <div class="extra-fields">
        <slot></slot>
      </div>
    </form>
</template>

<script>
export default {
  name: 'FhirForm',
  components: {  },
  data: () => ({
    loading: false,
  }),
  props: {
    method_name: {
      type: String,
      default: ''
    },
    resource_type: {
      type: String,
      default: ''
    },
  },
  computed: {
    mrn: {
      get() {
        return this.$store.state.endpoint.mrn
      },
      set(value) {
        this.$store.dispatch('endpoint/setMRN', value)
      },
    },
    canSubmit() {
      return Boolean(this.mrn.trim())
    },
  },
  methods: {}
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
form {
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
form > * + * {
  margin-left: 10px;
}
.buttons button + button {
  margin-left: 5px;
}
</style>
