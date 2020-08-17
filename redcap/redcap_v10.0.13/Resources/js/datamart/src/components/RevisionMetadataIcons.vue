<template>
  <div>
    <i v-if="revision.isApproved()" class="info status-approved far fa-check-circle" title="approved"></i>
    <i v-else class="info status-not-approved fas fa-ban" title="not approved"></i>
    <i v-if="revision.isExpired()" class="info status-date-due far fa-calendar" title="date range is due"></i>
    <i v-else class="info status-date-valid far fa-calendar-check" title="date range is valid"></i>
    <i class="info fas fa-hashtag" :title="`ID ${revision.getID()}`"></i>
  </div>
</template>

<script>

export default {
  name: 'RevisionMetadataIcons',
  props: {
    revision: {
      type: Object,
      default: null,
    },
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.info {
  cursor: help;
}
i.status-approved {
  color: #28a745;
}
i.status-not-approved {
  color: #dc3545;
}
i.status-date-valid {
  /* color: #28a745; */
  color: #000000;
}
i.status-date-due {
  /* color: #ffc107; */
  color: #000000;
}
i+i {
  margin-left: 3px;
}
</style>
