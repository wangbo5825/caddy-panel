<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'

const { t } = useI18n()
const message = useMessage()
const data = ref<any>({ mode: 'managed', global: '', content: '' })
const globalText = ref('')
const contentText = ref('')

onMounted(async () => {
  data.value = await request('/caddyfile')
  globalText.value = data.value.global
  contentText.value = data.value.content
})

async function check() {
  const body = data.value.mode === 'custom'
    ? { mode: 'custom', content: contentText.value }
    : { mode: 'managed', global: globalText.value }
  try {
    await request('/caddyfile/check', { method: 'POST', body })
    message.success(t('caddyfile.msgCheckOk'))
  } catch (e: any) {
    message.error(e.message)
  }
}

async function apply() {
  const body = data.value.mode === 'custom'
    ? { mode: 'custom', content: contentText.value }
    : { mode: 'managed', global: globalText.value }
  try {
    await request('/caddyfile/apply', { method: 'POST', body })
    message.success(t('caddyfile.msgApplied'))
    data.value = await request('/caddyfile')
    contentText.value = data.value.content
  } catch (e: any) {
    message.error(e.message)
  }
}
</script>

<template>
  <div class="cp-card">
    <div class="cp-card-title">
      {{ t('caddyfile.title') }}
      <span class="cp-pill" style="margin-left:8px">{{ data.mode === 'managed' ? t('caddyfile.pillManaged') : t('caddyfile.pillCustom') }}</span>
    </div>
    <div class="cp-muted" style="margin-bottom:12px">{{ t('caddyfile.hint') }}</div>

    <template v-if="data.mode === 'managed'">
      <div class="cp-muted">{{ t('caddyfile.globalLabel') }}</div>
      <textarea v-model="globalText" class="cp-editor" style="min-height:150px" placeholder="{ }" />
    </template>

    <div class="cp-muted" style="margin-top:12px">{{ t('caddyfile.mainLabel', { file: data.caddyfile }) }}</div>
    <textarea v-model="contentText" class="cp-editor" :readonly="data.mode === 'managed'" />

    <div class="cp-actions" style="margin-top:12px">
      <n-button @click="check">{{ t('caddyfile.check') }}</n-button>
      <n-button type="primary" @click="apply">{{ t('caddyfile.apply') }}</n-button>
    </div>
  </div>
</template>
