import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

const routes = [
  { path: '/', name: 'dashboard', component: () => import('./views/DashboardView.vue'), meta: { titleKey: 'nav.dashboard' } },
  { path: '/sites', name: 'sites', component: () => import('./views/SitesView.vue'), meta: { titleKey: 'nav.sites' } },
  { path: '/sites/:id', name: 'site-editor', component: () => import('./views/SiteEditorView.vue'), meta: { titleKey: 'nav.sites' } },
  { path: '/access', name: 'access', component: () => import('./views/PlaceholderView.vue'), meta: { titleKey: 'nav.access', p1: true } },
  { path: '/caddyfile', name: 'caddyfile', component: () => import('./views/CaddyfileView.vue'), meta: { titleKey: 'nav.caddyfile' } },
  { path: '/certificates', name: 'certificates', component: () => import('./views/CertsView.vue'), meta: { titleKey: 'nav.certs' } },
  { path: '/logs', name: 'logs', component: () => import('./views/LogsView.vue'), meta: { titleKey: 'nav.logs' } },
  { path: '/security', name: 'security', component: () => import('./views/PlaceholderView.vue'), meta: { titleKey: 'nav.security', p1: true } },
  { path: '/storage', name: 'storage', component: () => import('./views/PlaceholderView.vue'), meta: { titleKey: 'nav.storage', p1: true } },
  { path: '/backups', name: 'backups', component: () => import('./views/PlaceholderView.vue'), meta: { titleKey: 'nav.backups', p1: true } },
  { path: '/settings', name: 'settings', component: () => import('./views/SettingsView.vue'), meta: { titleKey: 'nav.settings' } },
  { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { titleKey: 'app.brand' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) {
    await auth.init()
  }
  if (to.name !== 'login' && !auth.user) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.user) {
    return { name: 'dashboard' }
  }
  return true
})

export default router
