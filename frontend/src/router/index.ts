import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
    },
    {
      path: '/entrada',
      name: 'entrada',
      component: () => import('@/views/ProximamenteView.vue'),
      props: { titulo: 'Entradas' },
    },
    {
      path: '/prestamo',
      name: 'prestamo',
      component: () => import('@/views/ProximamenteView.vue'),
      props: { titulo: 'Préstamos' },
    },
    {
      path: '/usuarios',
      name: 'usuarios',
      component: () => import('@/views/UsuariosView.vue'),
    },
    {
      path: '/salas',
      name: 'salas',
      component: () => import('@/views/ProximamenteView.vue'),
      props: { titulo: 'Salas' },
    },
    {
      path: '/reportes',
      name: 'reportes',
      component: () => import('@/views/ProximamenteView.vue'),
      props: { titulo: 'Reportes' },
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.token) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.token) {
    return { name: 'dashboard' }
  }
})

export default router
