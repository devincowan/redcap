<template>
  <div class="node">
     <!-- single internal node -->
    <template v-if="node.data.length===0">
        <label :for="node.name" :title="node.attributes.description">{{node.attributes.field}} ({{node.attributes.label}})</label>
    </template>
     <!-- external node (container) -->
    <template v-else>
      <Details class="node-list">
        <template v-slot:summary>
          <div class="node-name">{{node.name}} (<span>{{total}} {{total==1 ? 'field' : 'fields'}}</span>)</div>
        </template>
        <section class="content">
          <SourceFieldNode ref="childnode" v-for="(childnode) in node.data" :key="childnode.name" :parents="parents.concat(node.name)" :node="childnode" />
        </section>
      </Details>
    </template>
  </div>
  
</template>

<script>
import Details from '@/components/common/Details'
import {getTotalNodeFields} from '@/libraries/utils'

export default {
  name: 'SourceFieldNode',
  components: {
    Details,
  },
  data: function() {
      return {
          open: false,
      }
  },
  props: {
    node: {
      type: Object,
      default: () => {} 
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
    total() {
      return getTotalNodeFields(this.node)
    }
  },
  methods: {
    onClick(e) {
      e.preventDefault()
      this.open ? this.collapse() : this.expand()
    },
    expand() { if(!this.open) this.open = true },
    collapse() { if(this.open) this.open = false },
  }
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

.node {
    padding: 5px 0;
    white-space: nowrap;
}
.node label {
    margin: 0 0 0 3px;
    white-space: normal;
    vertical-align: top;
}
.node .node-name {
  font-weight: bold;
  white-space: normal;
  position: relative;
}
.node > .node {
    margin-left: 20px;
}

:not(section.content) .node > label {
  font-weight: bold;
  color: red;
}

.node-list .content {
  margin-left: 20px;
}

</style>
