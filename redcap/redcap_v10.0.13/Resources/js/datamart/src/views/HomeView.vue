<template>
  <div class="page">

    <div class="projhdr mb-2">
      <i class="fas fa-shopping-cart"></i> Clinical Data Mart
    </div>
    <p class="mb-2">
      Listed below is the current Data Mart configuration for this project. If you have appropriate privileges,
      you may run the "Fetch clinical data" button to retrieve data from the EHR. When fetching, any existing patients
      will have new data added to their record in the project. If you have permission to create new revisions of the current
      Data Mart configuration, you will see a "Request a configuration change" button at the bottom of the page. When submitted,
      all configuration revisions must be approved by a REDCap administrator before taking effect in the project.<br/>
      If a revision contains a list of Medical Record Numbers, those not yet in the project will be created as new records as soon as the revision is approved.
    </p>

    <div class="top-container card mb-4" v-if="revision">
      <div class="card-header">
        <span class="title">Revision</span>
      </div>
      <div class="card-body py-2 px-3">
        <section class="buttons">
          <RevisionSelect />
          <RunRevisionSplitButton />
          
        </section>
        
        <InfoPanel v-for="(warning, index) in warnings" :key="index" :summary="warning.summary" :description="warning.description" :type="warning.type" />
        
        <!-- <transition-group
          name="warnings"
          tag="div"
          :duration="{ enter: 300, leave: 600 }"
          enter-active-class="animated fadeIn"
          leave-active-class="animated fadeOut">
          <InfoPanel v-for="(warning, index) in warnings" :key="index" :summary="warning.summary" :description="warning.description" :type="warning.type" />
        </transition-group> -->

      </div>
    </div>

    <div class="details-container" v-if="revision && user">
      <RevisionDetail :revision="revision">
        <template v-slot:header></template>
        <template v-slot:footer>
          <section class='buttons'>
            <DeleteRevisionButton v-if="user.isAdmin() && totalRevisions > 1" />
            <CreateRevisionButton v-if="revision.canBeUsedByUserForNewRevision(user)"/>
            <ApproveRevisionButton v-if="user.canApproveRevision(revision)"/>
          </section>
        </template>
      </RevisionDetail>
    </div>

  </div>

  <!-- <section v-else>
    <span>no revision</span>
  </section> -->
</template>

<script>
import InfoPanel from '@/components/InfoPanel'
import RevisionSelect from '@/components/RevisionSelect'
import RevisionDetail from '@/components/RevisionDetail'

import RunRevisionSplitButton from '@/components/RunRevisionSplitButton'
import SplitButton from '@/components/buttons/SplitButton'
import ApproveRevisionButton from '@/components/buttons/ApproveRevisionButton'
import CreateRevisionButton from '@/components/buttons/CreateRevisionButton'
import DeleteRevisionButton from '@/components/buttons/DeleteRevisionButton'

/* return 'no records in the project'
return 'you do not have a valid access token'
return 'thi is not the active revision' */
import moment from 'moment'

export default {
  name: 'HomeView',
  data() {
    return {
      test: ''
    }
  },
  components: {
    RevisionSelect,
    RevisionDetail,
    RunRevisionSplitButton,
    ApproveRevisionButton,
    CreateRevisionButton,
    DeleteRevisionButton,
    InfoPanel,
  },
  computed: {
    revision() {
      return this.$store.getters['revisions/selected']
    },
    user() {
      return this.$store.state.user.info
    },
    totalRevisions() {
      return this.$store.getters['revisions/total']
    },
    warnings() {
      this.$store.dispatch('warnings/checkAll')
      return this.$store.state.warnings.list
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.top-container .card-header .title {
  font-size: 1.2em;
  font-weight: bold;
}
.top-container .buttons {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
}
.top-container .buttons + * {
    margin-top: 20px;
}
.top-container .buttons * + * {
  margin-left: 20px;
}
.details-container .buttons {
  display: flex;
  flex-direction: row;
  justify-content: flex-end;
  margin-top: 10px;
}
.details-container .buttons * + * {
  margin-left: 10px;
}
.card section:not(:first-child) {
  margin-top: 20px;
}
.my_alert {
  position: relative;
  padding: .375rem .75rem;
  border: 1px solid transparent;
  border-radius: .25rem;
  border: solid 1px transparent;
  font-size: .875rem;
  line-height: 1.5;
  font-weight: 400;
}

@media only screen and (max-width: 768px) {
  .top-container .buttons {
    flex-direction: column;
    justify-content: center;
    margin-top: 10px;
  }
  .top-container .buttons * + * {
    margin-left: 0;
    margin-top: 10px;
  }
  .details-container .buttons {
    flex-direction: column;
    justify-content: center;
    margin-top: 10px;
  }
  .details-container .buttons * + * {
    margin-left: 0;
    margin-top: 10px;
  }
}
</style>
