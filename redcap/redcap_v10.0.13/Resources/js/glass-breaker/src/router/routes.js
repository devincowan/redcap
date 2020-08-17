/**
 * components are lazy loaded as the routes are visited
 */
const routes = [
    // { path: '/', redirect: {name: 'newsletter'} },
    { path: '', component: () => import('@/layouts/MainLayout'),
        children: [
            { path: '', name: 'home', component: () => import('@/pages/Home') },
            { path: 'test', name: 'test', component: () => import('@/pages/Test') },
            { path: 'counter', name: 'counter', component: () => import('@/pages/Counter') },
            { path: "*", name: 'not_found', component: () => import('@/pages/PageNotFound')  }
        ]
    },
]

export default routes