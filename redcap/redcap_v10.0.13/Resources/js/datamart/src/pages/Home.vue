
<template>
  <div class="page-wrapper">
    <div v-if="!userCanUseDatamart" class="text-center">
      <h3>You are not allowed to use the Data Mart.</h3>
      <h5>Please contact your administrator.</h5>
    </div>
    <transition v-else name="fade">
      <router-view/>
    </transition>
  </div>
</template>


<script>

export default {
  name: 'HomePage',
  components: {},
  methods: {},
  computed: {
    userCanUseDatamart() {
      const {info:user} = this.$store.state.user
      if(!user) return false
      const { can_use_datamart=false } = user
      return can_use_datamart
    },
  },
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 200ms;
}
.fade-enter-active {
  /** delay same amount of time of the transition duration */
  transition-delay: 200ms;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}
</style>