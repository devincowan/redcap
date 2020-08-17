<template>
  <Dropdown id="revision-selector" :text="buttonText">
    <template v-if="userIsAdmin" v-slot:items>
      <a class="menu-item"  v-for="(revision, index) in revisions" :key="index"
        :class="{selected: isCurrent(revision), approved: revision.isApproved()}"
        href="#" @click="onSelect(revision, $event)">
          <section class="info">
            <span class="badge badge-info">{{revision.index}}</span>
            <span> Revision date: {{ revision.metadata.date}}</span>
          </section>
          <RevisionMetadataIcons class="metadata-icons" :revision="revision" />
      </a>
    </template>
  </Dropdown>
</template>

<script>
import Dropdown from '@/components/common/Dropdown'
import RevisionMetadataIcons from '@/components/RevisionMetadataIcons'

export default {
  name: 'RevisionList',
  components: {
    Dropdown,
    RevisionMetadataIcons
  },
  computed: {
    revisions() {
      return this.$store.state.revisions.list
    },
    selected() {
      return this.$store.getters['revisions/selected']
    },
    isCurrent() {
      return (revision) => {
        if(this.selected==null) return false
        try {
          return this.selected.metadata.id == revision.metadata.id
        } catch (error) {
          return false
        }
        // selected.metadata.id==revision.metadata.id
      };
    },
    buttonText() {
      if(!this.userIsAdmin || this.revisions.length==1) {
        if(this.selected) return `Revision ${this.selected.index}`
        else return 'no revisions'
      }else {
        if(this.selected) return `Revision ${this.selected.index}`
        else return 'Select a revision'
      }
    },
    /**
     * check if current user is a Super user.
     * A super user can approve revisions
     */
    userIsAdmin() {
      const user = this.$store.state.user.info
      if(!user) return false
      const { super_user: super_user=false } = user
      return super_user
    },
  },
  methods: {
    onSelect(revision, event) {
      event.preventDefault()
      const revision_id = revision.metadata.id
      this.$store.dispatch('revisions/setSelected', revision_id)
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
#revision-selector .menu-item {
  display: flex;
  white-space: nowrap;
  padding: 5px 5px;
  color: inherit;
  text-decoration: none;
  font-size: 13px;
}
#revision-selector .menu-item:hover {
  background-color: #f8f9fa;
}
#revision-selector .menu-item.selected {
  font-weight: bold;
}
#revision-selector .menu-item .info {
  margin-right: auto;
}
#revision-selector .menu-item .metadata-icons {
  margin-left: 3px;
  padding-left: 3px;
  border-left: solid 1px #cacaca;
  display: inline-block;
  min-width: 13px;
}
i.status-approved {
  color: #28a745;
}
i.status-not-approved {
  color: #dc3545;
}
i.status-date-valid {
  color: #28a745;
  color: #000000;
}
i.status-date-due {
  color: #ffc107;
  color: #000000;
}
@media only screen and (max-width: 768px) {
  nav.menu .submenu {
    width: 100%;
  }
}
</style>
