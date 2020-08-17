<template>
  <table v-if="Object.keys(data).length" class="table table-striped table-bordered">
    <thead>
      <th>key</th>
      <th>value</th>
    </thead>
    <tbody>
      <tr  v-for="(value, key) in data" :key="key">
          <td>{{key}}</td>
          <td>{{value}}</td>
      </tr>
    </tbody>
  </table>
</template>

<script>
const table_headers = {
    'first_name': 'first name',
    'last_name': 'last name',
    'gender': 'gender',
    'gender_code': 'gender code',
    'ethnicity': 'ethnicity',
    'ethnicity_code': 'ethnicity code',
    'race': 'race',
    'race_code': 'race code',
    'birthdate': 'birthdate',
    'address_city': 'address city',
    'address_country': 'address country',
    'address_postal_code': 'address postal code',
    'address_state': 'address state',
    'address_line': 'address line',
    'phone_home': 'phone home',
    'phone_mobile': 'phone mobile',
    'email': 'email',
    'is_deceased': 'is deceased',
}

export default {
  name: 'PatientTable',
  data: () => ({
      table_headers,
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
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.label {
  font-weight: bold;
}
</style>