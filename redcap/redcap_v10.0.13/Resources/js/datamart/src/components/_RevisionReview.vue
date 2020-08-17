<template>
  <section class="card detail" v-if="revision">
    <div class="card-header">
      <header>
        <RevisionMetadata :revision="revision" />
        <slot name="header"></slot>
      </header>
    </div>
    <div class="card-body">
      <main>
        <section>
          <h5 class="card-title"><i class="fas fa-calendar-week"></i> Range of time from which to pull data</h5>
          <DateRangeInfo :min="revision.data.dateMin" :max="revision.data.dateMax"/>
        </section>
        <section>
          <h5 class="card-title"><i class="fas fa-tasks"></i> Fields in EHR for which to pull data</h5>
          <FieldsReadOnly :list="revision.data.fields"/>
        </section>
        <section v-if="revision.data.mrns>0">
          <h5 class="card-title"><i class="fas fa-clipboard-list"></i> Medical record numbers of patients to import from EHR</h5>
          <MRNList :list="revision.data.mrns"/>
        </section>
      </main>

      <footer>
        <slot name="footer"></slot>
      </footer>
    </div>
  </section>
</template>

<script>
import {formatDate, humanReadableDate} from '@/libraries/utils'
import RevisionMetadata from '@/components/RevisionMetadata'
import MRNList from '@/components/MRNList'
import DateRangeInfo from '@/components/DateRangeInfo'
import FieldsReadOnly from '@/components/fields/show/FieldsReadOnly'

export default {
  name: 'RevisionReview',
  components: {
    MRNList,
    RevisionMetadata,
    DateRangeInfo,
    FieldsReadOnly,
  },
  props: {
    revision: {
      type: Object,
      default: () => {return null}
    }
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
header {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: flex-start;
}
header a {
  font-size: inherit;
}
header .subtitle {
  font-style: italic;
  font-size: .8rem;
}
main section + section {
  margin-top: 30px;
}
main h5 {
  color: #030399;
}
@media only screen and (max-width: 768px) {
  header {
    flex-direction: column;
  }
  header > * + * {
    margin-top: 15px;
  }
  .revision-import-export {
    align-self: flex-start;
  }
}
</style>
