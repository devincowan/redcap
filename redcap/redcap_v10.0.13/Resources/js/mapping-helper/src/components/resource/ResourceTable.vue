<template>

      <table v-if="Array.isArray(data)" class="table table-bordered thead-light table-striped">
        <thead >
          <tr>
            <td v-for="(key, index) in keys" :key="index">{{key}}</td>
            <!-- <td v-if="source">raw data</td> -->
          </tr>
        </thead>
        <tbody>
          <ResourceTable v-for="(item, index) in data" :key="index" :data="item" />
        </tbody>
      </table>

      <tr v-else-if="typeof data === 'object'" class="object">
        <template v-for="(item, index) in data" >
            <td v-if="typeof item==='object'" :key="index">
              <ResourceTable v-if="Array.isArray(item)" :data="item" />

              <tr v-else v-for="(value, key) in item" :key="key">
                <th>{{key}}</th>
                <td><ResourceTable :data="value" /></td>
              </tr>
            </td>

            <td v-else :key="index" >
              <ResourceTable :data="item" />
            </td>
        </template>
      </tr>

      <section v-else class="item" v-html="data"></section>
</template>

<script>


import { keysIn, reduce, merge } from 'lodash'
import JsonObject from '@/components/JsonObject'

export default {
  name: 'ResourceTable',
  components: {JsonObject},
  props: {
    source: {
      type: Object,
      default: null
    },
    data: {
      default: null
    },
  },
  methods: {
    prettify(object) {
      const str = JSON.stringify(object, null, 2) // spacing level = 2
      return str

    },
  },
  computed: {
    // check if the current object is the root table (not nested)
    isRoot() {
      return this.$parent.$options.name!==this.$options.name
    },
    keys() {
      const data = this.data || []
      if(typeof data !== 'object') return
      if(Array.isArray(data)) {
          // get a list of arrays with keys for each object
          const keys_list = data.map(item => {
              if(typeof item !== 'object') return
              return keysIn(item)
          })
          // get a single list of keys containing all the available keys
          const keys = reduce(keys_list, (all_keys, keys) => {
            return merge(keys, all_keys)
              // return merge(all_keys, keys)
          }, [])
          return keys
        }else {
          return keysIn(data)
        }

    }
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
pre {
  text-align: left;
}
.item {
  /* background-color: rgba(200,0,0,.1); */
  text-align: left;
}
.object {
  /* background-color: rgba(0,200,0,.1); */
}
</style>
