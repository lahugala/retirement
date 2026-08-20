import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'

const routes = [
  { path: '/login', name: 'Login', component: () => import('../views/Login.vue'), meta: { guest: true } },
  { path: '/', redirect: '/dashboard' },
  {
    path: '/',
    component: () => import('../components/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: 'dashboard', name: 'Dashboard', component: () => import('../views/Dashboard.vue') },
      { path: 'events', name: 'Events', component: () => import('../views/Events.vue') },
      { path: 'events/:id', name: 'EventDetail', component: () => import('../views/EventDetail.vue') },
      { path: 'transactions', name: 'Transactions', component: () => import('../views/Transactions.vue') },
      { path: 'users', name: 'Users', component: () => import('../views/Users.vue') },
      { path: 'members', name: 'Members', component: () => import('../views/Members.vue') },
      { path: 'categories', name: 'Categories', component: () => import('../views/Categories.vue') },
      { path: 'accounts', name: 'Accounts', component: () => import('../views/Accounts.vue') },
      { path: 'gift-stock', name: 'GiftStock', component: () => import('../views/GiftStock.vue') },
      { path: 'reports', name: 'Reports', component: () => import('../views/Reports.vue') },
      { path: 'audit', name: 'AuditLog', component: () => import('../views/AuditLog.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory('/retirement/'),
  routes,
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    next('/login')
  } else if (to.meta.guest && auth.isLoggedIn) {
    next('/dashboard')
  } else {
    next()
  }
})

export default router
