<template>
  <div class="detail-container" :class="{open:open}" >
    <div class="summary" @click="onClick">
      <slot name="summary"></slot>
    </div>
    <transition :duration="{ enter: animationEnterDuration, leave: animationLeaveDuration }" enter-active-class="animated fadeIn" leave-active-class="animated fadeOut">
      <div class="detail" v-show="open">
        <slot></slot>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'Details',
  data: () => ({
    open: false,
  }),
  props: {
    animated: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    animationEnterDuration() {
      return this.animated ? 150 : 0
    },
    animationLeaveDuration() {
      return this.animated ? 300 : 0
    },
  },
  methods: {
    onClick() {
      this.open = !this.open
    }
  }

}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
/* .detail-container > .detail {
  display: none;
}
.detail-container.open > .detail {
  display: block;
} */
.summary {
  cursor: pointer;
}
.summary:before {
  font-weight: bold;
  content: '+';
  /* display: inline-block; */
  float: left;
  height: 20px;
  width: 20px;
}
.detail-container.open > .summary:before {
  content: '-';
}

</style>
