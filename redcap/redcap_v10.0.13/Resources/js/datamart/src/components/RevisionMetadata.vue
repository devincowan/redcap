<template>
  <main>

    <section class="title">
      <span class="revision-title">Revision</span>
      <span> created by <span><a :href="`mailto:${user.user_email}`">{{`${user.user_firstname} ${user.user_lastname}`}}</a></span></span>
      <span class="revision-creation-date info" :title="date(revision.metadata.date)"> {{created_at_readable}}</span>
    </section>

    <section class="details">
      <RevisionMetadataIcons :revision="revision"/>
      <span class="last-execution" v-if="revision.metadata.executed_at">| Last execution time: <span class="info" :title="date(revision.metadata.executed_at)">{{executed_at_readable}}</span></span>
      <span class="last-execution" v-else>| never executed</span>
    </section>

  </main>
</template>

<script>
import RevisionMetadataIcons from '@/components/RevisionMetadataIcons'

import {formatDate, humanReadableDate} from '@/libraries/utils'

// refresh interval for the human readable dates
const refreshInterval = 1000
// store the clear setInterval ID
let clearIntervalID = false

export default {
  name: 'RevisionMetadata',
  data: () => ({
    created_at_readable: '',
    executed_at_readable: '',

  }),
  components: {RevisionMetadataIcons},
  props: {
    revision: {
      type: Object,
      default: null,
    }
  },
  created() {
    this.setHumanReadableDates()

    /**
     * update human readable dates once every minute
     */
    clearIntervalID = setInterval(() => {
      this.setHumanReadableDates()
    }, refreshInterval)
  },
  beforeDestroy() {
    clearInterval(clearIntervalID)
  },
  computed: {
    user() {
      return this.revision.metadata.creator
    },
  },
  watch: {
    revision() {
      // reset dates when the revision is changed
      this.setHumanReadableDates()
    }
  },
  methods: {
    setHumanReadableDates() {
      const revision = this.revision
      this.created_at_readable = humanReadableDate(revision.metadata.date)
      this.executed_at_readable = humanReadableDate(revision.metadata.executed_at)
    },
    date(date) {
      return formatDate(date, 'MM-DD-YYYY hh:mm:ss')
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
main {

  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-start;
}
main .title {
  font-size: 1.2em;
  font-weight: bold;
}
main .details {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
}
main .details > * + * {
  margin-left: 3px;
}
main .last-execution {
  font-style: italic;
}
main .title a {
  font-size: inherit;
}
</style>
