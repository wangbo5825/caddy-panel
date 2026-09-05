<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NConfigProvider,
  NMessageProvider,
  NSelect,
  darkTheme,
  dateEnUS,
  dateZhCN,
  enUS,
  zhCN,
} from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { currentLocale, setLocale } from './i18n'
import { setLang } from './api'
import { useAuthStore } from './stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const locale = ref(currentLocale())
function changeLocale(v: string) {
  locale.value = v
  setLocale(v === 'zh' ? 'zh' : 'en')
  setLang(v)
}

const naiveLocale = computed(() => (locale.value === 'zh' ? zhCN : enUS))
const naiveDateLocale = computed(() => (locale.value === 'zh' ? dateZhCN : dateEnUS))
const isDark = typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches

const nav = [
  { key: 'dashboard', to: '/' },
  { key: 'sites', to: '/sites' },
  { key: 'access', to: '/access', p1: true },
  { key: 'caddyfile', to: '/caddyfile' },
  { key: 'certs', to: '/certificates' },
  { key: 'logs', to: '/logs' },
  { key: 'security', to: '/security', p1: true },
  { key: 'storage', to: '/storage', p1: true },
  { key: 'backups', to: '/backups', p1: true },
  { key: 'settings', to: '/settings' },
]

const isLogin = computed(() => route.name === 'login')
const title = computed(() => t((route.meta.titleKey as string) || 'app.brand'))

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <n-config-provider :theme="isDark ? darkTheme : null" :locale="naiveLocale" :date-locale="naiveDateLocale">
    <n-message-provider>
      <div v-if="!isLogin" class="cp-layout">
        <aside class="cp-sidebar">
          <div class="cp-brand"><span class="cp-logo">⌂</span> {{ t('app.brand') }}</div>
          <nav class="cp-nav">
            <RouterLink v-for="item in nav" :key="item.to" :to="item.to" class="cp-nav-item">
              <span>{{ t('nav.' + item.key) }}</span>
              <span v-if="item.p1" class="cp-muted">{{ t('nav.p1') }}</span>
            </RouterLink>
          </nav>
          <div class="cp-sidebar-foot">
            <div>{{ auth.user?.local ? t('app.localAdmin') : t('app.adminOf', { name: auth.user?.username || '' }) }}</div>
            <n-button size="tiny" quaternary type="error" style="margin-top:6px" @click="logout">{{ t('app.logout') }}</n-button>
          </div>
        </aside>
        <div class="cp-main">
          <header class="cp-topbar">
            <h1>{{ title }}</h1>
            <div class="cp-topbar-right">
              <n-select
                :value="locale"
                :options="[{ label: 'English', value: 'en' }, { label: '中文', value: 'zh' }]"
                size="small"
                style="width:110px"
                @update:value="changeLocale"
              />
              <RouterLink to="/settings"><n-button size="small" quaternary>{{ t('app.settings') }}</n-button></RouterLink>
            </div>
          </header>
          <main class="cp-content"><RouterView /></main>
        </div>
      </div>
      <RouterView v-else />
    </n-message-provider>
  </n-config-provider>
</template>
