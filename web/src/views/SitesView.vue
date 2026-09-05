<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NSwitch, useDialog, useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'

const { t } = useI18n()
const router = useRouter()
const message = useMessage()
const dialog = useDialog()
const sites = ref<any[]>([])

async function load() {
  sites.value = (await request('/sites')).items
}

onMounted(load)

async function toggle(s: any) {
  try {
    await request('/sites/' + s.id + '/toggle', { method: 'POST' })
    message.success(s.enabled ? t('sites.msgDisabled') : t('sites.msgEnabled'))
    await load()
  } catch (e: any) {
    message.error(e.message)
    await load()
  }
}

function del(s: any) {
  dialog.warning({
    title: t('sites.delTitle'),
    content: t('sites.delContent', { name: s.name }),
    positiveText: t('sites.delOk'),
    negativeText: t('sites.delCancel'),
    onPositiveClick: async () => {
      try {
        await request('/sites/' + s.id, { method: 'DELETE' })
        message.success(t('sites.msgDeleted'))
        await load()
      } catch (e: any) {
        message.error(e.message)
      }
    },
  })
}

function modeLabel(mode: string): string {
  return t('mode.' + mode, {}, { missingWarn: false }) !== 'mode.' + mode ? t('mode.' + mode) : mode
}
</script>

<template>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <div class="cp-card-title" style="margin:0">{{ t('sites.title', { n: sites.length }) }}</div>
      <div class="cp-muted">{{ t('sites.subtitle') }}</div>
    </div>
    <n-button type="primary" @click="router.push('/sites/new')">{{ t('sites.new') }}</n-button>
  </div>

  <div class="cp-card">
    <table class="cp-table">
      <thead>
        <tr><th>{{ t('sites.colName') }}</th><th>{{ t('sites.colDomains') }}</th><th>{{ t('sites.colMode') }}</th><th>{{ t('sites.colStatus') }}</th><th>{{ t('sites.colActions') }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="s in sites" :key="s.id">
          <td><strong>{{ s.name }}</strong></td>
          <td>{{ (s.form?.domains || []).join(', ') || '—' }}</td>
          <td>
            <span class="cp-pill">{{ modeLabel(s.mode) }}</span>
            <span v-if="s.advanced" class="cp-pill cp-pill-amber" style="margin-left:6px">{{ t('sites.badgeAdvanced') }}</span>
          </td>
          <td>
            <n-switch :value="!!s.enabled" size="small" @update:value="toggle(s)" />
          </td>
          <td>
            <div class="cp-actions">
              <n-button size="small" quaternary @click="router.push('/sites/' + s.id)">{{ t('sites.actEdit') }}</n-button>
              <n-button size="small" quaternary type="error" @click="del(s)">{{ t('sites.actDelete') }}</n-button>
            </div>
          </td>
        </tr>
        <tr v-if="!sites.length"><td colspan="5" class="cp-empty">{{ t('sites.empty') }}</td></tr>
      </tbody>
    </table>
  </div>
</template>
