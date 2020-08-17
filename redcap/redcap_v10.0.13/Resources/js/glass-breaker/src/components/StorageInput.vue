<template>
<div>
    <div class="input-group">
        <!-- <input type="text" class="form-control" :value="value" @input="onInput" v-bind="{...$props}"> -->
        <input type="text" class="form-control" v-model="input" v-bind="{...$props}">
        <div class="input-group-append" v-if="storage_enabled">
            <button class="btn btn-outline-secondary" type="button"
                    :disabled="!save_enabled" @click="save"
                    title="save this value to localStorage">
                <i class="fas fa-save"/>
            </button>
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                    :disabled="items.length==0" title="select a previously stored value from localStorage"><i class="fas fa-database"/>
            </button>
            <div class="dropdown-menu">
                <template v-for="(item, index) in items">
                    <div class="dropdown-item" :key="index">
                        <a href="#" @click.prevent="onSelect(item)">{{item}}</a>
                        <a class="remove-item" href="#" @click.prevent="remove(item)" title="remove this value from localStorage">
                            <i class="fas fa-trash" />
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
</template>

<script>
function storageAvailable(type) {
    var storage;
    try {
        storage = window[type];
        var x = '__storage_test__';
        storage.setItem(x, x);
        storage.removeItem(x);
        return true;
    }
    catch(e) {
        return e instanceof DOMException && (
            // everything except Firefox
            e.code === 22 ||
            // Firefox
            e.code === 1014 ||
            // test name field too, because code might not be present
            // everything except Firefox
            e.name === 'QuotaExceededError' ||
            // Firefox
            e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
            // acknowledge QuotaExceededError only if there's something already stored
            (storage && storage.length !== 0);
    }
}

/**
 * for v-model to work on parents using this component:
 * - the 'value' prop must be bind to the input field
 * - an input event must be emitted when the input field fires input: this.$emit('input', event.target.value)
 * 
 * the approach described above is only used if the value prop is provided.
 * if value is not provided then we will use internal_value.
 * to choose between value and internal value we use the computed property input as a proxy.
 * 
 */
export default {
    data() {
        return {
            internal_value: '', // used when no value prop is provided
            items: [],
        }
    },
    props: {
        // for v-model to work
        value: {
            type: String,
            default: null,
        },
        id: {
            type: String,
            default: ''
        },
        placeholder: {
            type: String,
            default: ''
        },
        prefix: {
            type: String,
            default: 'glass_breaker'
        },
        storage_key: {
            type: String,
            default: ''
        }
    },
    created() {
        this.items = this.getStoredItems()
    },
    computed: {
        save_enabled() {
            return this.input.length>0 && this.items.indexOf(this.input)<0
        },
        storage_enabled() {
            return storageAvailable('localStorage')
        },
        full_key() {
            return `${this.prefix}_${this.storage_key}`
        },
        input: {
            get() {
                if(this.value!==null) return this.value
                return this.internal_value
            },
            set(value) {
                if(this.value!==null) this.$emit('input', value)
                this.internal_value = value
            },
        }
    },
    methods: {
        /**
         * emit input when an item is selected
         */
        onSelect(item) {
            this.input = item
        },
        /**
         * save an item in the localStorage
         */
        save() {
            if(!this.storage_enabled) return
            this.items.push(this.input)
            this.storeItems()
        },
        /**
         * remove an item from the localStorage
         */
        remove(item) {
            const index = this.items.indexOf(item)
            if(index<0) return
            // remove 1 item at index
            this.items.splice(index,1)
            this.storeItems()
        },
        getStoredItems() {
            if(!this.storage_enabled) return []
            if(!this.storage_key) return []
            const stored_items = localStorage.getItem(this.full_key)
            const items = JSON.parse(stored_items) || []
            if(Array.isArray(items)) return items
            else return [items]
        },
        storeItems() {
            localStorage.setItem(this.full_key, JSON.stringify(this.items))
        }
    },
}
</script>

<style scoped>
.dropdown-item {
    display: flex;
    flex-direction: row;
    align-items: center;
}
.dropdown-item a {
    font-size: 0.8rem;
    color: inherit;
}
.remove-item {
    margin-left: auto;
}
</style>