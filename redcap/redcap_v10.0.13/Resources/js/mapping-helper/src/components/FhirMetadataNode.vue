<template>
    <div class="fhir-metadata-node">
        <span v-if="label" @click.stop="onClick($event)" class="label">{{label}}</span>
        <div class="content" v-show="expanded">

            <span v-if="metadata.field">
                {{metadata.field}} ({{metadata.label}})
            </span>

            <ul v-else>
                <li v-for="(sub_metadata, key) in metadata" :key="key" :class="{collapsable: isNaN(key)}">
                    <FhirMetadataNode :metadata="sub_metadata" :label="(isNaN(key)) ? key : ''" :key="key" @click.stop="onClickNode($event)" :depth="depth+1" />
                </li>
            </ul>
        </div>
    </div>
</template>


<script>
import FhirMetadataNode from '@/components/FhirMetadataNode'

export default {
  name: 'FhirMetadataNode',
  components: {
    FhirMetadataNode,
  },
  data: () => ({
      expanded: true
  }),
  created() {
      // depth 0 always expanded
      this.expanded = !this.label
  },
  props: {
      metadata: {
          type: [Object,Array],
          default: () => ({})
      },
      label: {
          type: String,
          default: ''
      },
      depth: {
          type: Number,
          default: 0
      },
  },
  computed: {},
  methods: {
      onClick(e) {
          this.expanded = !this.expanded
      },
  },
}
</script>

<style>
.fhir-metadata-node ul,
.fhir-metadata-node li {
}
.fhir-metadata-node .label {
 font-weight: bold;
 cursor: pointer;
}
</style>