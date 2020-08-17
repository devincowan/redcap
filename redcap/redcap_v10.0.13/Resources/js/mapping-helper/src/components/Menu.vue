
<template>
    <ul class="nav nav-tabs">
        <router-link v-for="(link, index) in links" :key="index"
          v-slot="{ href, isActive, isExactActive }"
          :to="link.to">
          <li  class="nav-item">
            <a :href="href" class="nav-link" :class="{active: link.exact ? isExactActive : isActive}">
              {{link.label}}
            </a>
          </li>
        </router-link>
    </ul>
</template>

<script>
import {menu_links} from '@/variables'

export default {
  name: 'MenuLayout',
  data: () => ({
    links: menu_links
  }),
}
</script>

<style scoped>

@media only screen and (max-width: 768px) {

}
</style>