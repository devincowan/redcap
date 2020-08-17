<template>
  <section class="date-range">
      <section>
          <label for="date-min">from</label>
          <div class="date-picker">
            <DatePicker v-model="min" :max_date="max"/>
          </div>
          <i class="fas fa-calendar-day"></i>
      </section>
      <section>
          <label for="date-min">to</label>
          <div class="date-picker">
            <DatePicker v-model="max" :min_date="min" />
          </div>
          <i class="fas fa-calendar-day"></i>
      </section>
  </section>
</template>

<script>
import DatePicker from '@/components/DatePicker'

export default {
  name: 'DateRange',
  components: {DatePicker},
  data: () => ({
    datepicker_config: {
      altInputClass: 'date-picker',
      altInput: true,
      altFormat: "m-d-Y",
      dateFormat: "Y-m-d",
    }
  }),
  mounted() {
    var component = this;
    var dateFields = this.$el.querySelectorAll('input[data-key]');
  },
  computed: {
    min: {
      get() {
        const date = this.$store.state.revision.dateMin
        return date;
      },
      set(value) {
        this.$store.dispatch('revision/setDateMin', value)
      },
    },
    max: {
      get() {
        const date = this.$store.state.revision.dateMax
        return date
      },
      set(value) {
        this.$store.dispatch('revision/setDateMax', value)
      },
    },
  },
  methods: {
    /**
     * reset date value if delete or canc are pressed
     */
    reset(e) {
      const element = e.target
      const deleteKeys = [46, 8]
      if(deleteKeys.indexOf(event.keyCode)>=0) {
        const key = element.getAttribute('data-key')
        this[key] = ''
      }
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

.date-range {
  display: flex;
}
.date-range section {
  display: flex;
  align-items: center;
}
.date-range section label {
  margin-right: 3px;

}
.date-range section i {
  margin-left: 3px;
}
.date-range section + section {
  margin-left: 5px;
}
.date-picker {
  display: inline;
}
</style>
