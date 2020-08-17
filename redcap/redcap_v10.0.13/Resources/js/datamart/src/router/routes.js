/**
 * components are lazy loaded as the routes are visited
 */

const routes = [
    // { path: '/', redirect: {name: 'newsletter'} },
    { path: '/', component: () => import('@/layouts/MainLayout'),
        children: [
            { path: '', component: () => import('@/pages/Home'),
            children: [
                {path: '', name: 'home', component: () => import('@/views/HomeView')},
                {path: 'create-revision', name: 'create-revision', component: () => import('@/pages/CreateRevision')}
            ] },
            { path: '/create-project', name: 'create-project', component: () => import('@/pages/CreateProject'), meta: { requiresAuth: false } },
            { path: '/review-project', name: 'review-project', component: () => import('@/pages/ReviewProject'), meta: { requiresAuth: true } },
            { path: "*", name: 'not_found', component: () => import('@/pages/PageNotFound')  }
        ]
    }
]


export default routes