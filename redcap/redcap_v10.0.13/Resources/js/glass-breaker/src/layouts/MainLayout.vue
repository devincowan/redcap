
<template>
    <section class="layout">
        <header>
            <Menu />
        </header>

        <main class="page-wrapper">
            <router-view />
        </main>

        <footer>
            footer goes here
        </footer>
    </section>
</template>

<script>
import Menu from '@/components/Menu'

export default {
  name: 'MainLayout',
  components: {Menu},
}
</script>

<style scoped>
.layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: stretch;
}
main {
    flex-grow: 1;
}
header, main, footer {
    flex-shrink: 0;
}
</style>