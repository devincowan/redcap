<template>
  <div class="backToTop">
    <transition
    name="backtotop-transition"
    enter-active-class="animated fadeIn"
    leave-active-class="animated fadeOut">
    <div v-if="showButton">
      <button type="button" class="btn btn-sm btn-primary"  @click="goToTop">Back to Top</button>
    </div>
    </transition>
  </div>
</template>

<script>

import { debounce } from 'lodash'

const SCROLL_POSITION_LIMIT = 100

export default {
  name: 'BackToTop',
  data: () => ({
    scroll_position: 0
  }),
  props: {
    msg: String
  },
  computed: {
    showButton() {
      return this.scroll_position >= SCROLL_POSITION_LIMIT
    }
  },
  mounted() {
    window.addEventListener('scroll', debounce(() => {
      this.scroll_position = window.scrollY
    }, 300))
  },
  methods: {
    goToTop() {
      window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'smooth'
      });
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.backToTop {
  position: relative;
}
.backToTop > div {
  position: fixed;
  bottom: 20px;
  left: 20px;
}
@media only screen and (max-width: 768px){
  .backToTop > div {
    /* top: 100px; */
    right: 20px;
    left: auto;
    /* bottom: auto; */
    bottom: 50px;
  }
}


</style>
