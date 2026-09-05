<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'

const { t } = useI18n()
const message = useMessage()
const data = ref<any>({ configured: false, data_dir: '', certs: [], expiring: 0 })

onMounted(async () => {
  try {
    data.value = await request('/certs')
  } catch (e: any) {
    message.error(e.message)
  }
})

function statusLabel(c: any): { text: string; cls: string } {
  const now = Date.now() / 1000
  if (c.expires > now + 14 * 86400) return { text: t('certs.ok'), cls: 'cp-pill-green' }
  if (c.expires > now) {
    const d = Math.ceil((c.expires - now) / 86400)
    return { text: t('certs.expiringIn', { n: d }), cls: 'cp-pill-amber' }
  }
  return { text: t('certs.expired'), cls: 'cp-pill-red' }
}
</script>

<template>
  <div class="cp-card">
    <div class="cp-card-title">{{ t('certs.title', { n: data.certs.length }) }}</div>
    <div v-if="!data.configured" class="cp-muted" style="margin-bottom:12px">
      {{ t('certs.notConfigured') }}
      <RouterLink to="/settings">{{ t('certs.settingsLink') }}</RouterLink>
    </div>
    <div v-else class="cp-muted" style="margin-bottom:12px">{{ t('certs.dataDir', { dir: data.data_dir, n: data.expiring }) }}</div>
    <table class="cp-table">
      <thead>
        <tr><th>{{ t('certs.colDomain') }}</th><th>{{ t('certs.colIssuer') }}</th><th>{{ t('certs.colExpiry') }}</th><th>{{ t('certs.colStatus') }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="c in data.certs" :key="c.file">
          <td><strong>{{ c.domain }}</strong></td>
          <td>{{ c.issuer }}</td>
          <td>{{ new Date(c.not_after * 1000).toLocaleDateString() }}</td>
          <td><span class="cp-pill" :class="statusLabel(c).cls">{{ statusLabel(c).text }}</span></td>
        </tr>
        <tr v-if="!data.certs.length"><td colspan="4" class="cp-empty">{{ t('certs.empty') }}</td></tr>
      </tbody>
    </table>
  </div>
</template>
