<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request, fmtBytes, fmtPct } from '../api'

const { t } = useI18n()
const message = useMessage()
const status = ref<any>(null)
const audit = ref<any[]>([])

function auditAction(code: string): string {
  const key = 'audit.' + code
  return t(key, {}, { missingWarn: false }) !== key ? t(key) : code
}

function auditResult(result: string): string {
  const key = 'result.' + result
  return t(key, {}, { missingWarn: false }) !== key ? t(key) : result
}

onMounted(async () => {
  try {
    status.value = await request('/status')
    audit.value = (await request('/audit')).items
  } catch (e: any) {
    message.error(e.message)
  }
})
</script>

<template>
  <div class="cp-grid">
    <div class="cp-card">
      <div class="cp-stat-label">{{ t('dashboard.statCaddy') }}</div>
      <div class="cp-stat-value" :class="status?.caddy?.ok ? 'cp-green' : 'cp-red'">
        {{ status?.caddy?.ok ? t('dashboard.running') : t('dashboard.offline') }}
      </div>
      <div class="cp-stat-hint">{{ status?.caddy?.ok ? t('dashboard.adminLatency', { ms: status.caddy.latency }) : status?.caddy?.error }}</div>
    </div>
    <div class="cp-card">
      <div class="cp-stat-label">{{ t('dashboard.statSites') }}</div>
      <div class="cp-stat-value">{{ status?.sites?.total ?? '—' }}</div>
      <div class="cp-stat-hint">{{ t('dashboard.enabledCount', { n: status?.sites?.enabled ?? 0 }) }}</div>
    </div>
    <div class="cp-card">
      <div class="cp-stat-label">{{ t('dashboard.statCerts') }}</div>
      <div class="cp-stat-value">{{ status?.certs?.total ?? '—' }}</div>
      <div class="cp-stat-hint" :class="status?.certs?.expiring ? 'cp-amber' : ''">
        {{ status?.certs?.expiring ? t('dashboard.expiringSoon', { n: status.certs.expiring }) : t('dashboard.noExpiring') }}
      </div>
    </div>
    <div class="cp-card">
      <div class="cp-stat-label">{{ t('dashboard.statDisk') }}</div>
      <div class="cp-stat-value">{{ fmtPct(status?.disk?.free ?? 0, status?.disk?.total ?? 0) }}</div>
      <div class="cp-stat-hint">{{ t('dashboard.diskHint', { total: fmtBytes(status?.disk?.total ?? 0), varSize: fmtBytes(status?.disk?.var ?? 0) }) }}</div>
    </div>
  </div>

  <div class="cp-card">
    <div class="cp-card-title">{{ t('dashboard.auditTitle') }}</div>
    <table class="cp-table">
      <thead>
        <tr><th>{{ t('dashboard.colTime') }}</th><th>{{ t('dashboard.colAction') }}</th><th>{{ t('dashboard.colTarget') }}</th><th>{{ t('dashboard.colResult') }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="a in audit" :key="a.id">
          <td>{{ a.ts.replace('T', ' ').slice(5, 19) }}</td>
          <td>{{ auditAction(a.action) }}</td>
          <td>{{ a.target }}</td>
          <td>{{ auditResult(a.result) }}</td>
        </tr>
        <tr v-if="!audit.length"><td colspan="4" class="cp-empty">{{ t('dashboard.empty') }}</td></tr>
      </tbody>
    </table>
  </div>
</template>
