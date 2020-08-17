<template>
  <div>
    <label for="observation-type" class="font-weight-bold">Observation type</label>
    <select class="form-control" id="observation-type" v-model="current_category">
      <option disabled value="">Please select a category</option>
      <option v-for="(category, index) in categories" :key="index" :value="category">{{category}}</option>
    </select>
  </div>
</template>

<script>
import {observation_categories} from '@/variables' 
export default {
  name: 'ObservationFields',
  data: () => ({ 
    categories: observation_categories,
    current_category: ''
  }),
  props: {
    category: {
      type: String,
      default: observation_categories[0],
    }
  },
  created() {
    this.current_category = this.category
  },
  methods: {
    getQuery() {
      const category = this.current_category || observation_categories[0]
      return {category}
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>
