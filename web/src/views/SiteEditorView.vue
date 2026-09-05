<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NInput, NSelect, NSwitch, useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const message = useMessage()
const id = route.params.id as string
const isNew = id === 'new'

const site = ref<any>({
  name: '',
  mode: 'static',
  enabled: true,
  advanced: false,
  raw: '',
  form: { domains: [], root: '', upstreams: [], lb_policy: '' },
})
const settings = ref<any>({ allow_php: true })
const domainsText = ref('')
const upstreamsText = ref('')

onMounted(async () => {
  settings.value = await request('/settings')
  if (!isNew) {
    site.value = await request('/sites/' + id)
    domainsText.value = (site.value.form?.domains || []).join('\n')
    upstreamsText.value = (site.value.form?.upstreams || []).join('\n')
    if (site.value.external_modified) {
      message.warning(t('editor.warnExternal'))
    }
  }
})

const modes = computed(() => {
  const m = [
    { label: t('mode.static'), value: 'static' },
    { label: t('mode.proxy'), value: 'proxy' },
  ]
  if (settings.value.allow_php) {
    m.push({ label: t('mode.php'), value: 'php' })
  }
  return m
})

async function save() {
  const form: any = {
    ...(site.value.form || {}),
    domains: domainsText.value.split(/\r?\n/).map((s) => s.trim()).filter(Boolean),
  }
  if (site.value.mode === 'proxy') {
    form.upstreams = upstreamsText.value.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
  }
  const body = {
    name: site.value.name,
    mode: site.value.mode,
    enabled: !!site.value.enabled,
    advanced: !!site.value.advanced,
    raw: site.value.raw || '',
    form,
  }
  try {
    if (isNew) {
      await request('/sites', { method: 'POST', body })
    } else {
      await request('/sites/' + id, { method: 'PUT', body })
    }
    message.success(t('editor.msgSaved'))
    router.push('/sites')
  } catch (e: any) {
    message.error(e.message)
  }
}
</script>

<template>
  <div class="cp-card">
    <div class="cp-card-title">{{ isNew ? t('editor.newTitle') : t('editor.editTitle', { name: site.name }) }}</div>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
      <div>
        <div class="cp-muted">{{ t('editor.fldName') }}</div>
        <n-input v-model:value="site.name" :disabled="!isNew" :placeholder="t('editor.fldNamePh')" style="margin-top:4px" />
      </div>
      <div>
        <div class="cp-muted">{{ t('editor.fldMode') }}</div>
        <n-select v-model:value="site.mode" :options="modes" style="margin-top:4px" />
      </div>
    </div>
    <div style="display:flex;gap:18px;margin-top:12px;align-items:center">
      <label class="cp-muted" style="display:flex;gap:6px;align-items:center">{{ t('editor.fldEnabled') }} <n-switch v-model:value="site.enabled" size="small" /></label>
      <label class="cp-muted" style="display:flex;gap:6px;align-items:center">{{ t('editor.fldAdvanced') }} <n-switch v-model:value="site.advanced" size="small" /></label>
    </div>

    <div v-if="!site.advanced" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px">
      <div>
        <div class="cp-muted">{{ t('editor.fldDomains') }}</div>
        <n-input v-model:value="domainsText" type="textarea" :rows="4" :placeholder="t('editor.fldDomainsPh')" style="margin-top:4px" />
      </div>
      <div v-if="site.mode === 'proxy'">
        <div class="cp-muted">{{ t('editor.fldUpstreams') }}</div>
        <n-input v-model:value="upstreamsText" type="textarea" :rows="4" :placeholder="t('editor.fldUpstreamsPh')" style="margin-top:4px" />
        <div class="cp-muted" style="margin-top:8px">{{ t('editor.fldLb') }}</div>
        <n-select
          v-model:value="site.form.lb_policy"
          :options="[
            { label: t('editor.lbRoundRobin'), value: 'round_robin' },
            { label: t('editor.lbLeastConn'), value: 'least_conn' },
            { label: t('editor.lbRandom'), value: 'random' },
            { label: t('editor.lbIpHash'), value: 'ip_hash' },
          ]"
          clearable
          style="margin-top:4px"
        />
      </div>
      <div v-else>
        <div class="cp-muted">{{ t('editor.fldRoot') }}</div>
        <n-input v-model:value="site.form.root" :placeholder="t('editor.fldRootPh')" style="margin-top:4px" />
      </div>
    </div>

    <div v-else style="margin-top:14px">
      <div class="cp-muted">{{ t('editor.fldRaw') }}</div>
      <n-input
        v-model:value="site.raw"
        type="textarea"
        :rows="12"
        :placeholder="t('editor.rawPh')"
        style="margin-top:6px;font-family:ui-monospace,Consolas,monospace;font-size:12.5px"
      />
    </div>

    <div class="cp-actions" style="margin-top:16px">
      <n-button type="primary" @click="save">{{ isNew ? t('editor.createApply') : t('editor.saveApply') }}</n-button>
      <n-button @click="router.push('/sites')">{{ t('editor.back') }}</n-button>
    </div>
  </div>
</template>
