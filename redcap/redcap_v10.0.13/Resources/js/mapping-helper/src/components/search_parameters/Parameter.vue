<template>
  <div class="parameter">
    <label for=""><b>key:</b> {{name}}</label>
    <label for=""><b>values:</b></label>
    <input type="text" :id="`values-${name}`" ref="choices" v-model="values" @addItem="onAddItem" @removeItem="onRemoveItem"/>
    <label for=""><b>repeat logic:</b></label>
    <select class="form-control" :disabled="values.length<2">
        <option value="">or</option>
        <option value="">and</option>
    </select>
  </div>
</template>

<script>
import Choices from 'choices.js'
import 'choices.js/public/assets/styles/choices.min.css'

export default {
    name: "FhirParameter",
    data: () => ({
        values:[],
        choices: null,
    }),
    props: {
        name: {
            type: String,
            default: ''
        }
    },
    methods: {
        onAddItem() {
            /* console.log(event.detail.id)
            console.log(event.detail.value)
            console.log(event.detail.label)
            console.log(event.detail.customProperties)
            console.log(event.detail.groupValue) */
            this.values = this.choices.getValue(true)
            console.log(this.values)
        },
        onRemoveItem() {
             this.values = this.choices.getValue(true)
        },
    },
    mounted() {
        const ref_element = this.$refs.choices
        this.choices = new Choices(ref_element,{
            items: [1,2,3,4,5],
            // choices: [],
            addItems: true,
            removeItems: true,
            removeItemButton: true,
            duplicateItemsAllowed: false,
            renderSelectedChoices: 'auto',
            addItemText: (value) => {
                return `Press Enter to add <b>"${value}"</b>`;
            },
            classNames: {
                 containerOuter: 'choices',
                containerInner: 'choices__inner',
                input: 'choices__input',
                inputCloned: 'choices__input--cloned',
                list: 'choices__list',
                listItems: 'choices__list--multiple',
                listSingle: 'choices__list--single',
                listDropdown: 'choices__list--dropdown',
                item: 'choices__item',
                itemSelectable: 'choices__item--selectable',
                itemDisabled: 'choices__item--disabled',
                itemChoice: 'choices__item--choice',
                placeholder: 'choices__placeholder',
                group: 'choices__group',
                groupHeading: 'choices__heading',
                button: 'choices__button',
                activeState: 'is-active',
                focusState: 'is-focused',
                openState: 'is-open',
                disabledState: 'is-disabled',
                highlightedState: 'is-highlighted',
                selectedState: 'is-selected',
                flippedState: 'is-flipped',
                loadingState: 'is-loading',
                noResults: 'has-no-results',
                noChoices: 'has-no-choices',
            }
            /* _choices: [{
                value: 'Option 1',
                label: 'Option 1',
                selected: true,
                disabled: false,
            },
            {
                value: 'Option 2',
                label: 'Option 2',
                selected: false,
                disabled: true,
                customProperties: {
                    description: 'Custom description about Option 2',
                    random: 'Another random custom property'
                },
            }] */
        })
    },
    destroyed() {
        if(!this.choices) return
        this.choices.destroy()
    }
}
</script>

<style>
.parameter {
    display: flex;
    flex-direction: column;
    background-color: #fcfcfc;
    padding: 10px;
    border: solid 1px #cacaca;
    border-radius: 3px;
}

.choices__list--multiple .choices__item {
    border-radius: 3px;
    background-color: #cacaca;
    border-color: #aaa;
}
.choices[data-type*=select-multiple] .choices__button, .choices[data-type*=text] .choices__button {
    border-left: 1px solid #aaa;
}
</style>