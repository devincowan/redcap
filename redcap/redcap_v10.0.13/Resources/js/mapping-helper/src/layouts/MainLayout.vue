
<template>
    <section class="layout">
      <!-- <img alt="REDCap logo" class="redcap-logo" src="@/assets/redcap_logo.png"> -->
      <Menu />
      <div class="page-wrapper">
          <router-view />
      </div>
      <!-- <footer>REDCap - © 2020 Vanderbilt University</footer> -->
    </section>
</template>

<script>

import Menu from '@/components/Menu'

export default {
  name: 'MainLayout',
  components: {Menu,},
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
.page-wrapper {
  padding: 25px 0;
  flex: 1;
}
nav a + a {
  margin-left: 5px;
}
@media only screen and (max-width: 768px) {

}
</style>