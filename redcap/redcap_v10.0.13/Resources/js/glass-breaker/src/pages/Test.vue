<template>
  <div>
      <p>test</p>
      <p>Department type:{{department_type}}</p>
      <p>Department:<input type="text" v-model="department" /></p>
      <input type="text" v-model="explanation" placeholder="explanation">
      <select v-model="reason">
        <option disabled value=''>Select a reason...</option>
        <option v-for="(reason, index) in reasons" :key="index" :value="reason">{{reason}}</option>
      </select>

      <p>{{legal_message}}</p>

      <button @click="onTestClick">test</button>
      <button @click="onInitializeClick">initialize</button>
      <input type="text" v-model="mrn">
      <button @click="onCheckClick">check</button>
      <button @click="onAcceptClick">accept</button>
      <button @click="onCancelClick">cancel</button>
      <div>
        {{response}}
      </div>
      <div>
        {{error}}
      </div>
      <div>
        <pre v-for="(item, index) in items" :key="index">
          {{item}}
        </pre>
      </div>
  </div>
</template>

<script>

const presets = {
  epic_app_orchard: {
    department_id: '10501101',
    mrn: '202500',
  },
  vanderbilt_test: {
    department_id: '101000206',
    mrn: '2000789'
  },
}


export default {
  name: 'TestPage',
  data: () =>({
    items: [],
    reason: '',
    explanation: 'REDCap break the glass',
    department: '101000206',
    department_type: 'internal',
    mrn: '2000789', // 202500 is a valid patient in the Epic App Orchard
    response: {},
    error: {},
  }),
  components: {},
  computed: {
    reasons() {
      return this.$store.state.information.Reasons || []
    },
    legal_message() {
      return this.$store.state.information.LegalMessage || ''
    }
  },
  methods: {
    async onTestClick() {
      try {
        this.reset()
        const response = await this.$API.test({id:1})
        this.response = response.data
        
      } catch (error) {
        console.log(error)
        this.error = error
      }
    },
    async onInitializeClick() {
      try {
        this.reset()
        const response = await this.$API.initialize()
        this.response = response.data
        this.$store.dispatch('information/initialize')
        console.log(response)
      } catch (error) {
        console.log(error)
        this.error = error
      }
    },
    async onCheckClick() {
      try {
        this.reset()
        const response = await this.$API.check(this.mrn)
        console.log(response)
        this.response = response.data
      } catch (error) {
        console.log(error)
        this.error = error
      }
    },
    async onAcceptClick() {
      try {
        this.reset()
        const explanation = this.explanation
        const reason = this.reason;
        const department = this.department
        const department_type = this.department_type
        const response = await this.$API.accept(this.mrn, reason, explanation, department, department_type)
        console.log(response)
        this.response = response.data
      } catch (error) {
        console.log(error)
        this.error = error
      }
    },
    async onCancelClick() {
      try {
        this.reset()
        const reason = 'Cancelled BTG Form' // this is not an error
        const department = this.department
        const department_type = this.department_type
        const response = await this.$API.cancel(this.mrn, reason, department, department_type)
      } catch (error) {
        console.log(error)
        this.error = error
      }
    },
    reset() {
      this.response = {}
      this.error = {}
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>