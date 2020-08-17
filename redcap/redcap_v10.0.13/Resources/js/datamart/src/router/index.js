import Vue from 'vue'
import VueRouter from 'vue-router'
import routes from '@/router/routes'

import store from '@/store'

Vue.use(VueRouter)

const router = new VueRouter({
    mode: 'hash', // hash|history
    base: process.env.BASE_URL,
    routes
})

router.beforeEach((to, from, next) => {
    if (to.matched.some((record) => record.meta.requiresAuth)) {
        const user = store.state.user.info
        if(user && user.can_create_revision===true) {
            next()
        }else {   
            next({
                path: '/',
                // query: { redirect: to.fullPath }
            })
        }
    }else {
        next() // make sure to always call next()!
    }
  })

export default router