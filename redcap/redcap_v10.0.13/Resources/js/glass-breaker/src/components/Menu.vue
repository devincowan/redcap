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
export default {
    name: 'Menu',
    data: () => ({
        links: [
            { label:'Home', to: {name: 'home'}, exact:true },
            { label:'Test', to: {name: 'test'} },
            { label:'Counter', to: {name: 'counter'} },
        ]
    })
}
</script>

<style>

</style>