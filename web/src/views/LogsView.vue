<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { NButton, NInput, NSelect, NSwitch, useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'

const { t } = useI18n()
const message = useMessage()
const lines = ref<any[]>([])
const configured = ref(true)
const file = ref('')
const q = ref('')
const status = ref('')
const autorefresh = ref(true)
let timer: number | undefined

async function load() {
  try {
    const params = new URLSearchParams({ lines: '200' })
    if (q.value) params.set('q', q.value)
    if (status.value) params.set('status', status.value)
    const d = await request('/logs?' + params.toString())
    configured.value = d.configured
    file.value = d.file
    lines.value = d.lines
  } catch (e: any) {
    message.error(e.message)
  }
}

watch([q, status], () => load())

onMounted(() => {
  load()
  timer = window.setInterval(() => {
    if (autorefresh.value) load()
  }, 5000)
})

onUnmounted(() => {
  if (timer) window.clearInterval(timer)
})

function statusPill(s: number): string {
  if (s >= 500) return 'cp-pill-red'
  if (s >= 400) return 'cp-pill-amber'
  return 'cp-pill-green'
}
</script>

<template>
  <div class="cp-card">
    <div class="cp-card-title">{{ t('logs.title') }}</div>
    <div v-if="!configured" class="cp-muted" style="margin-bottom:12px">{{ t('logs.unconfigured') }}</div>
    <div v-else class="cp-muted" style="margin-bottom:12px">{{ file }}</div>

    <div class="cp-actions" style="margin-bottom:12px">
      <n-input v-model:value="q" :placeholder="t('logs.filterPh')" clearable style="width:240px" />
      <n-select
        v-model:value="status"
        :options="[
          { label: t('logs.allStatus'), value: '' },
          { label: '2xx', value: '200' },
          { label: '3xx', value: '301' },
          { label: '404', value: '404' },
          { label: '5xx', value: '500' },
        ]"
        style="width:150px"
      />
      <label class="cp-muted" style="display:flex;gap:6px;align-items:center">{{ t('logs.autoRefresh') }} <n-switch v-model:value="autorefresh" size="small" /></label>
      <n-button size="small" @click="load">{{ t('logs.refresh') }}</n-button>
    </div>

    <table class="cp-table">
      <thead>
        <tr><th>{{ t('logs.colTime') }}</th><th>{{ t('logs.colIp') }}</th><th>{{ t('logs.colMethod') }}</th><th>{{ t('logs.colHost') }}</th><th>{{ t('logs.colUri') }}</th><th>{{ t('logs.colStatus') }}</th><th>{{ t('logs.colDuration') }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="(l, i) in lines" :key="i">
          <td>{{ l.time }}</td>
          <td>{{ l.ip }}</td>
          <td>{{ l.method }}</td>
          <td>{{ l.host }}</td>
          <td style="word-break:break-all">{{ l.uri }}</td>
          <td><span class="cp-pill" :class="statusPill(l.status)">{{ l.status }}</span></td>
          <td>{{ l.duration.toFixed(3) }}s</td>
        </tr>
        <tr v-if="!lines.length"><td colspan="7" class="cp-empty">{{ t('logs.empty') }}</td></tr>
      </tbody>
    </table>
  </div>
</template>
