
<template>
    <section class="layout">
      <!-- <img alt="REDCap logo" class="redcap-logo" src="@/assets/redcap_logo.png"> -->
      <div class="page-wrapper">
        <transition name="fade" appear>
          <router-view />
        </transition>
      </div>
    </section>
</template>

<script>

export default {
  name: 'MainLayout',
  components: {},
}
</script>

<style scoped>
img.redcap-logo {
  object-fit: contain;
}
.layout {
  display: flex;
  flex-direction: column;
}
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
nav a + a {
  margin-left: 5px;
}
@media only screen and (max-width: 768px) {

}
</style>