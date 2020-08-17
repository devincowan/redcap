<template>
  <div class="json" :class="{collapsed: collapsed, activeValue: activeValue}">
      <div v-if="showKey" class="key" :class="{active: activeKey}" @click="onKeyClick(key_name, $event)" :title="path">"{{key_name}}":</div>
      <template v-if="value && typeof value === 'object'">
          <div class="parenthesis">{{type==='array' ? '[' : '{'}}</div>
          <div @click="toggleChildren" class="children-toggler">▶</div>

          <template v-if="!valueIsEmpty">
            <div v-if="collapsed" class="ellipsis" @click="toggleChildren">...</div>
            <div v-else class="node" >
              <div v-for="(item, key, index) in value" :key="key" class="item">
                <JsonObject :key_name="key" :value="item" :depth="depth+1"/>
                <span v-if="(index<(Object.keys(value).length-1))">,</span>
              </div>
            </div>
          </template>

          <div class="parenthesis">{{type==='array' ? ']' : '}'}}</div>
      </template>

      <template v-else>
        <div v-if="value==null" class="value">null</div>
        <div v-else class="value" :class="{active: activeValue}" @click="onValueClick(value, $event)">"{{value}}"</div>
      </template>

    </div>
</template>

<script>
import EventBus from '@/libraries/EventBus'

export default {
  name: 'JsonObject',
  data: () => ({
    collapsed: false, // node status
    activeValue: false, // value selected status
    activeKey: false, // key selected status
  }),
  props: {
    depth: {
      type: Number,
      default: 0,
    },
    value: {
      // type: [Object, Array, String, Number, Boolean],
      default: null,
    },
    key_name: {
      type: [String, Number],
      default: null,
    },
  },
  created() {
    EventBus.$on('keyClicked', (node, event) => {
      const modifier = event.shiftKey
      this.setActiveKey(node, modifier)
    })
    EventBus.$on('valueClicked', (node, event) => {
      const modifier = event.shiftKey
      this.setActiveValue(node, modifier)
    })
  },
  computed: {
    type() {
      if(typeof this.value !== 'object') return typeof this.value
      return Array.isArray(this.value) ? 'array' : 'object'
    },
    /**
     * recursively get the path to this node
     */
    path() {
      if(this.key_name === null) return
      const keys = [this.key_name]
      let parent = this.$parent
      while(parent.$options.name===this.$options.name)
      {
        if(parent.key_name!==null) keys.push(parent.key_name)
        parent = parent.$parent
      }
      return keys.reverse();
    },
    valueIsEmpty()
    {
      const value = this.value
      if(typeof value === 'object') {
        if(Array.isArray(value)) {
          return value.length < 1
        }else {
          return Object.keys(value).length < 1
        }
      }
      return Boolean(value)
    },
    /**
     * check if the value passed as prop is an object (not an array)
     */
    isObject() {
      return typeof this.value === 'object' && !Array.isArray(this.value)
    },
    showKey() {
      return this.$parent.isObject
    }
  },
  methods: {
    setActiveKey(node, modifier=false) {
      if(this._uid === node._uid)
      {
        this.activeKey = !this.activeKey
      }else {
         if(!modifier) this.activeKey = false
      }
    },
    /**
     * set the active value
     * if a sibiling is active, it stays active
     */
    setActiveValue(node, modifier=false) {
      if(this._uid === node._uid)
      {
        // toggle the active element
        this.activeValue = !this.activeValue
      }else {
        if(!modifier) this.activeValue = false
      }
    },
    toggleChildren() {
      this.collapsed = !this.collapsed
    },
    onKeyClick(key, event)
    {
      EventBus.$emit('keyClicked', this, event)
      console.log(key, this.path)
    },
    onValueClick(value, event)
    {
      EventBus.$emit('valueClicked', this, event)
      console.log({value, path:this.path})
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
pre {
  text-align: left;
}
.json {
  text-align: left;
  display: block;
  user-select: none;
}
.item {
  display: inline-block;
}
.node {
  margin-left: 1em;
  display: flex;
  flex-direction: column;
}
.key {
  margin-right: 2px;
}
.item .json,
.parenthesis,
.value,
.key {
  display: inline;
}
/* features */
.value:hover {
  cursor: pointer;
  background-color: rgb(148, 85, 219,.8);
}
.key:hover {
  cursor: pointer;
  background-color: rgba(85, 157, 219, .8);
}
.children-toggler {
  position: relative;
  transition-duration: 150ms;
  transition-property: transform, color;
  transition-timing-function: ease-in-out;
  transform-origin: center;
  transform: rotate(90deg);
  display: inline-block;
  cursor: pointer;
  color:rgba(0, 255, 63, 1);
  font-size: 12px;
}
.collapsed > .children-toggler {
  color:rgba(255, 0, 63, 1);
  transform: rotate(0deg)
}
.ellipsis {
  display: inline-block;
  cursor: pointer;
}
.value.active {
  background-color:rgba(191, 202, 40, 0.8)
}
.key.active {
  background-color:rgba(191, 0, 150, 0.8)
}

</style>
