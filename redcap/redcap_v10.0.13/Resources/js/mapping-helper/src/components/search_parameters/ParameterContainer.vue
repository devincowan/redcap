<template>
  <div>
    <div class="param-creator">
        <div class="form-group text-container mr-1">
            <input type="text" class="form-control" v-model="new_param_name" placeholder="parameter name" @keyup.enter="addParameter">
        </div>
        <button type="button" class="btn btn-primary" @click="addParameter" :disabled="!canAdd"><i class="fas fa-plus"></i></button>
    </div>
    <div class="parameters" v-for="(parameter, index) in parameters" :key="index">
        <div class="parameter-row">
            <Parameter :name="parameter.name" class="parameter mr-1"/>
            <button type="button" class="btn btn-danger" @click="removeParameter(index)"><i class="fas fa-minus-circle"></i></button>
        </div>
    </div>
  </div>
</template>

<script>
import Parameter from './Parameter'

export default {
    name: "FhirParameterContainer",
    components: {Parameter},
    data: () =>({
        new_param_name: '',
        parameters: []
    }),
    computed: {
        canAdd() {
            if(!this.new_param_name.trim()) return false
            return !this.parameters.some(item => item.name===this.new_param_name)
        },
    },
    methods: {
        addParameter() {
            const name = this.new_param_name
            if(!this.canAdd) return
            this.parameters.push({name})
            this.new_param_name = ''
        },
        removeParameter(index) {
            const parameters = this.parameters
            parameters.splice(index, 1)
            this.parameters = parameters
        }
    }
}
</script>

<style scoped>
.param-creator {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    width: 300px;
    justify-content: space-between;
}
.parameters {
    display: flex;
    flex-direction: column;
    width: 300px;

}
.parameter-row {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    margin-top: 3px;
    align-items: flex-start;
}
.text-container {
    flex: 1;
}
.parameter {
    flex: 1;
}
</style>
