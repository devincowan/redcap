<template>
  <div class="wrapper">
    <Dropdown right hideCaret text="" icon="fas fa-cog" class="import-export">
      <template v-slot:items>
        <ExportRevisionButton class="export"/>
        <ImportRevisionButton class="import" v-on:import="onImport"/>
      </template>
    </Dropdown>
  </div>
</template>

<script>
import Dropdown from '@/components/common/Dropdown'
import ImportRevisionButton from '@/components/buttons/ImportRevisionButton'
import ExportRevisionButton from '@/components/buttons/ExportRevisionButton'

/**
 * helper function to get the fields to export
 */
const getFields = ({mrns,fields,dates}) => {
  const keys = []
  if(mrns) keys.push('mrns')
  if(fields) keys.push('fields')
  if(dates) keys.push('dateMin','dateMax')
  return keys
}

export default {
  name: 'RevisionImportExport',
  components: {
      Dropdown,
      ImportRevisionButton,
      ExportRevisionButton,
  },
  methods: {
    onImport(data) {
      if(data) {
        this.$router.push({ name: 'create-revision'})
      }
    }
  },
}
</script>

<style>
.import-export.dropdown-container > button {
    background-color: transparent;
    border: none;
    color: black;
  }
.import-export.dropdown-container > button > i {
  transition-property: transform;
  transition-duration: 300ms;
  transition-timing-function: ease-in-out;
  transform: rotate(0deg);
}
.import-export.dropdown-container > button:focus > i {
  transform: rotate(45deg);
}
/* style for buttons inside menu */
.export > button,
.import > button {
  font-size: 1rem;
  padding: 0;
  background-color: transparent;
  border-color: transparent;
}
.export > button:hover,
.import > button:hover {
  /* color: #212529; */
  background-color: transparent;
  border-color: transparent;
}
</style>
<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
  
</style>
