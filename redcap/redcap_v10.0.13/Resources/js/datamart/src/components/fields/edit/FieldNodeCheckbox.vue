<template>
  <div class="node" :class="classes">
     <!-- single internal node -->
    <template v-if="node.data.length===0">
      <div class="checkbox-container" >
        <input ref="checkbox"  :checked="isChecked" :id="node.name" type="checkbox" :value="node.name" @change="onChange"/>
        <label :for="node.name" :title="node.attributes.description">{{node.attributes.field}} ({{node.attributes.label}})</label>
      </div>
    </template>
     <!-- external node (container) -->
    <template v-else>
        <section class="node-row">
          <div class="node-container" @click="toggleExpand" :class="{active: expanded}">
            <span class="node-name" >{{node.name}}</span>
            <div class="node-info">
                <div v-if="query.length>0">
                  <span>showing {{filteredFieldsSubtotal}} of {{fieldsSubtotal}} {{ (fieldsSubtotal==1) ? 'field' : 'fields'}}</span>
                </div>
                <div v-if="filteredFieldsSubtotal">
                  <span>{{totalSelected}}/{{filteredFieldsSubtotal}} selected</span>
                </div>
            </div>
          </div>
          <aside>
              <section class="buttons" v-if="filteredFieldsSubtotal">
                <button class="btn btn-sm btn-select-all" @click="onSelectAll" :class="{
                  'btn-outline-secondary': totalSelected<filteredFieldsSubtotal,
                  'btn-success': totalSelected===filteredFieldsSubtotal,
                }">
                  <span v-if="totalSelected<filteredFieldsSubtotal">select all</span>
                  <span v-else>deselect all</span>
                </button>
              </section>
          </aside>
        </section>
        <section class="children" v-if="expanded">
          <FieldNodeCheckbox ref="childnode" v-for="(childnode) in data" :key="childnode.name" :parents="parents.concat(node.name)" :node="childnode" />
        </section>

        <ScrollSpy v-if="totalVisibleNodes>limit" v-show="expanded" class="my-2 text-center" @click="incrementLimit" @intersect="onScollspyIntersect"/>
    </template>
  </div>
  
</template>

<script>
import { difference, union, filter, reduce } from 'lodash'
import { getTotalNodeFields, getNodeFields, getTotalNodeChildren } from '@/libraries/utils'
import ScrollSpy from '@/components/common/ScrollSpy'

const increment = 200 //amount of nodes loaded incrementally

export default {
  name: 'FieldNodeCheckbox',
  components: {ScrollSpy},
  data: () => ({
    expanded: false,
    start: 0,
    limit: increment,
  }),
  props: {
    node: {
      type: Object,
      default: () => ({})
    },
    parent_id: {
      type: String,
      default: '',
    },
    parents: {
      type: Array,
      default: () => []
    },
  },
  computed: {
    data() {
      let count = 0
      const data = {}
      for(let name in this.node.data)
      {
        if(count++>this.limit) break
        data[name] = this.node.data[name]
      }
      return data
    },
    /**
     * check the revision to see if the checkbox should be checked
     */
    isChecked() {
      const list = this.$store.state.revision.fields
      return list.indexOf(this.node.name)>=0
    },
    /**
     * check if a value has changed compared to the selected revision
     */
    isChanged() {
      const selectedRevision = this.$store.getters['revisions/selected']
      if(!selectedRevision) return false
      const { fields } = selectedRevision
      return (this.isChecked && fields.indexOf(this.node.name)<0) || (!this.isChecked && fields.indexOf(this.node.name)>=0) 
    },
    classes() {
      return {
        collapsible: Object.keys(this.node.data).length>0,
        active: this.isActive,
        changed: this.isChanged,
      }
    },
    query() {
      const query = this.$store.state.nodes.query
      const trimmedQuery = query.trim()
      // if(trimmedQuery.length==0) this.isActive = false
      return trimmedQuery
    },
    /**
     * get list of available fields for this node
     */
    fields() {
      return getNodeFields(this.node)
    },
    // the total NOT FILTERED fields of children and subchildren of the node
    fieldsSubtotal() {
      return this.node.metadata.fieldsSubtotal
    },
    // the total FILTERED fields of children and subchildren of the node
    filteredFieldsSubtotal() {
      return getTotalNodeFields(this.node)
    },
    /**
     * the total selected chidlren and subchildren of the node
     */
    totalSelected() {
      const selectedFields = this.$store.state.revision.fields
      const unselected = difference(this.fields, selectedFields)
      return this.filteredFieldsSubtotal-unselected.length
    },
    /**
     * visible nodes for incremental laoding
     */
    totalVisibleNodes() {
      const {metadata: {total}} = this.node
      const filteredTotal = Object.keys(this.node.data).length
      const totalHiddenNodes = total-filteredTotal
      /* if(this.query.length>0 && totalHiddenNodes>=0) {
        this.isActive = true
      } */
      return total-totalHiddenNodes
    },
  },
  watch: {
    query(value) {
      this.expanded = value.length>0
    },
  },
  methods: {
    async onChange(event) {
      const { currentTarget, currentTarget: { checked } } = event
      const { name } = this.node
      try {
        const result = await this.$store.dispatch('revision/updateFields',{name, checked})
      } catch (error) {
        // field is mandatory: force to true

        // undo last action if error
        currentTarget.checked = !currentTarget.checked
        // show a notification error
        this.$swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            icon: 'error',
            title: error,
            text: ''
        })
      }
    },
    onSelectAll(event) {
      event.preventDefault()
      const select = this.totalSelected<this.filteredFieldsSubtotal
      const fields = this.$store.state.revision.fields

      const updatedFields = select ? union(fields, this.fields) : difference(fields, this.fields)
      this.$store.dispatch('revision/setFields', updatedFields)
    },
    toggleExpand(event) {
      this.expanded = !this.expanded
      if(!this.expanded) this.limit = increment
    },
    /**
     * manage incremental loading
     */
    incrementLimit() {
      let limit = this.limit+increment
      if(limit>this.filteredFieldsSubtotal) limit = this.filteredFieldsSubtotal
      this.limit = limit
    },
    onScollspyIntersect() {
      if(this.limit<this.fieldsSubtotal) this.incrementLimit()
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
/*  .node.collapsible > .node {
   display: none;
 }
 .node.collapsible.active {
   display: block;
 }
 */
.node.collapsible:not(:last-child)::after {
  content: '';
  display: block;
  border-top: solid 1px #cacaca;
  width: calc(100%-20px);
  margin: 10px auto;
}
.node.changed {
  /* background-color: rgba(255,255,0,0.2); */
}
.node.changed .checkbox-container::before {
  content: '•';
  position: absolute;
  left: -10px;
}

.node {
    padding: 5px 0;
    white-space: wrap;
    position: relative;
}
.node .node-row {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
}
.node .node-row .node-info {
  color: #999;
  font-style: italic;
  margin-left: 20px;
}
.node .node-row .node-info > div {
  display: inline;
}
.node .node-row .node-info > div + div::before {
  content: "|";
  display: inline-block;
  margin: 0 2px;
}
.node .node-row aside {
  white-space: nowrap;
  flex: 1;
  margin-left: auto;
  text-align: right;
  margin-left: 3px;
}
.node .node-row aside .buttons {
  display: inline-block;
}

.node label {
    margin: 0 0 0 3px;
    white-space: normal;
    vertical-align: top;
}
.node .checkbox-container {
  white-space: nowrap;
}
.node .node-name {
  white-space: normal;
  font-weight: bold;
  white-space: normal;
  position: relative;
  line-height: 1.1em;
}
.node > .children {
    margin-left: 20px;
}
.node.collapsible .node-container::before {
  position: relative;
  display: inline-block;
  text-align: center;
  width: 20px;
  height: 20px;
  content: '+';
  left: 0;
  font-weight: bold;
}
.node.collapsible .node-container.active::before {
    content: '-';
}
/* .node.collapsible > .children {
    display: none;
}
.node.collapsible.active > .children {
    display: block;
}
.node.collapsible.active > .children > .node.hidden {
    display: none;
} */
.checkbox-container > *,
.node.collapsible .node-container {
  cursor: pointer;
}
.node.collapsible .node-name {
  width: auto;
}
.btn-select-all {
  transition-duration: 300ms;
  transition-property: background-color, color;
  transition-timing-function: ease-in;
}
</style>