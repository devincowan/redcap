<template>
<main>
  <section class="date-range">
    <span class="date-wrapper" v-if="dateMin">
      <span class="label">From</span><span class="date">{{dateMin}}</span>
    </span>
    <span class="date-wrapper" v-if="dateMax">
      <span class="label"> to</span><span class="date">{{dateMax}}</span>
    </span>
    <span v-if="!dateMin && !dateMax">no date range specified (get all available data)</span>
  </section>
</main>
</template>

<script>
import moment from 'moment';

const user_date_format = 'MM-DD-YYYY'

export default {
  name: 'DateRangeInfo',
  props: {
    min: {
      type: String,
      default: '',
    },
    max: {
      type: String,
      default: '',
    },
  },
  computed: {
    dateMin() {
      return this.getFormattedDate(this.min)
    },
    dateMax() {
      return this.getFormattedDate(this.max)
    },
  },
  methods: {
    getFormattedDate(date) {
      if(date.trim()=='') return date;
      return moment(date).format(user_date_format);
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
  .date {
    font-style: italic;
  }
  .date + i[class^="fa"]{
    margin-left: 3px;
  }
  .label {
    font-weight: bold;
    margin-right: 3px;
  }
</style>
