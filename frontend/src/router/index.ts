import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUsuarioAuthStore } from '@/stores/usuarioAuth'

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
      path: '/login/v2',
      name: 'login-v2',
      component: () => import('@/views/LoginV2View.vue'),
      meta: { public: true },
    },
    {
      path: '/portal/login',
      name: 'portal-login',
      component: () => import('@/views/portal/PortalLoginView.vue'),
      meta: { public: true, portal: true },
    },
    {
      path: '/portal',
      name: 'portal-home',
      component: () => import('@/views/portal/PortalHomeView.vue'),
      meta: { portal: true },
    },
    {
      path: '/portal/entrada',
      name: 'portal-entrada',
      component: () => import('@/views/portal/PortalEntradaView.vue'),
      meta: { portal: true },
    },
    {
      path: '/portal/catalogo',
      name: 'portal-catalogo',
      component: () => import('@/views/portal/PortalCatalogoView.vue'),
      meta: { portal: true },
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
      component: () => import('@/views/EntradaView.vue'),
    },
    {
      path: '/prestamo',
      name: 'prestamo',
      component: () => import('@/views/PrestamoView.vue'),
    },
    {
      path: '/usuarios',
      name: 'usuarios',
      component: () => import('@/views/UsuariosView.vue'),
    },
    {
      path: '/salas',
      name: 'salas',
      component: () => import('@/views/SalasView.vue'),
    },
    {
      path: '/reportes',
      name: 'reportes',
      component: () => import('@/views/ReportesView.vue'),
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.portal) {
    const usuarioAuth = useUsuarioAuthStore()
    if (!to.meta.public && !usuarioAuth.token) {
      return { name: 'portal-login' }
    }
    if (to.name === 'portal-login' && usuarioAuth.token) {
      return { name: 'portal-home' }
    }
    return
  }

  const auth = useAuthStore()
  if (!to.meta.public && !auth.token) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.token) {
    return { name: 'dashboard' }
  }
})

export default router
