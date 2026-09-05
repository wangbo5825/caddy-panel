<script setup lang="ts">
import { h, onMounted, ref } from 'vue'
import { NButton, NInput, NRadioButton, NRadioGroup, NSelect, NSwitch, useDialog, useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const message = useMessage()
const dialog = useDialog()
const auth = useAuthStore()

const s = ref<any>({})
const apiInput = ref('')
const connType = ref<'tcp' | 'unix'>('tcp')
const testing = ref(false)
const testResult = ref('')
const token = ref('')
const pwd = ref({ old: '', next: '' })

onMounted(async () => {
  s.value = await request('/settings')
  apiInput.value = s.value.admin_api
  connType.value = apiInput.value.startsWith('unix://') ? 'unix' : 'tcp'
})

function changeConnType(v: 'tcp' | 'unix') {
  connType.value = v
  apiInput.value = v === 'unix' ? 'unix:///run/caddy-admin.sock' : 'http://127.0.0.1:2019'
}

async function saveSettings() {
  try {
    await request('/settings', {
      method: 'PUT',
      body: {
        local_auto_login: !!s.value.local_auto_login,
        admin_api: apiInput.value,
        data_dir: s.value.data_dir,
        log_file: s.value.log_file,
        panel_name: s.value.panel_name,
        managed_mode: !!s.value.managed_mode,
        allow_php: !!s.value.allow_php,
        caddyfile_style: s.value.caddyfile_style || 'import',
      },
    })
    message.success(t('settings.msgSaved'))
  } catch (e: any) {
    message.error(e.message)
  }
}

async function testConn() {
  testing.value = true
  testResult.value = t('settings.testing')
  try {
    const r = await request('/settings/test', { method: 'POST', body: { admin_api: apiInput.value } })
    testResult.value = r.ok ? t('settings.testOk', { ms: r.latency }) : t('settings.testFail', { err: r.error })
  } catch (e: any) {
    testResult.value = t('settings.testFail', { err: e.message })
  } finally {
    testing.value = false
  }
}

function changePwd() {
  pwd.value = { old: '', next: '' }
  dialog.info({
    title: t('settings.pwdTitle'),
    content: () =>
      h('div', { style: 'display:flex;flex-direction:column;gap:10px' }, [
        h('div', { style: 'font-size:12px;color:var(--cp-muted)' }, t('settings.oldPwd')),
        h(NInput, { type: 'password', placeholder: t('settings.oldPwd'), value: pwd.value.old, onUpdateValue: (v: string) => (pwd.value.old = v) }),
        h('div', { style: 'font-size:12px;color:var(--cp-muted)' }, t('settings.newPwd')),
        h(NInput, { type: 'password', placeholder: t('settings.newPwd'), value: pwd.value.next, onUpdateValue: (v: string) => (pwd.value.next = v) }),
      ]),
    positiveText: t('settings.ok'),
    negativeText: t('settings.cancel'),
    onPositiveClick: async () => {
      try {
        await auth.changePassword(pwd.value.old, pwd.value.next)
        message.success(t('settings.msgPwdChanged'))
      } catch (e: any) {
        message.error(e.message)
      }
    },
  })
}

async function genToken() {
  try {
    const r = await request('/tokens', { method: 'POST' })
    token.value = r.token
    message.success(t('settings.tokenGenerated'))
  } catch (e: any) {
    message.error(e.message)
  }
}
</script>

<template>
  <div class="cp-card">
    <div class="cp-card-title">{{ t('settings.authTitle') }}</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-width:640px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <span>{{ t('settings.localAuto') }}</span>
        <n-switch v-model:value="s.local_auto_login" />
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <span>{{ t('settings.account', { name: auth.user?.username }) }}</span>
        <n-button size="small" @click="changePwd">{{ t('settings.changePwd') }}</n-button>
      </div>
      <div class="cp-muted">{{ t('settings.forgot') }}<span class="cp-code">{{ t('settings.forgotCli') }}</span>{{ t('settings.forgotCfg') }}</div>
    </div>
  </div>

  <div class="cp-card">
    <div class="cp-card-title">{{ t('settings.connTitle') }}</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-width:640px">
      <div>
        <div class="cp-muted">{{ t('settings.connType') }}</div>
        <n-radio-group :value="connType" style="margin-top:4px" @update:value="changeConnType">
          <n-radio-button value="tcp">{{ t('settings.connTcp') }}</n-radio-button>
          <n-radio-button value="unix">{{ t('settings.connUnix') }}</n-radio-button>
        </n-radio-group>
      </div>
      <div>
        <div class="cp-muted">{{ t('settings.adminApi') }}</div>
        <n-input v-model:value="apiInput" :placeholder="connType === 'unix' ? t('settings.unixHint') : t('settings.tcpHint')" style="margin-top:4px" />
      </div>
      <div class="cp-actions">
        <n-button size="small" :loading="testing" @click="testConn">{{ t('settings.test') }}</n-button>
        <span class="cp-muted" style="align-self:center">{{ testResult }}</span>
      </div>
      <div class="cp-muted">{{ t('settings.paths') }}</div>
      <div class="cp-code">{{ t('settings.mainCaddyfile', { path: s.caddyfile }) }}</div>
      <div class="cp-code">{{ t('settings.fragmentsDir', { path: s.fragments_dir }) }}</div>
      <div class="cp-muted" style="margin-top:6px">{{ t('settings.dataDir') }}</div>
      <n-input v-model:value="s.data_dir" :placeholder="t('settings.dataDirHint')" style="margin-top:4px" />
      <div class="cp-muted">{{ t('settings.logFile') }}</div>
      <n-input v-model:value="s.log_file" :placeholder="t('settings.logFileHint')" style="margin-top:4px" />
    </div>
  </div>

  <div class="cp-card">
    <div class="cp-card-title">{{ t('settings.modeTitle') }}</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-width:640px">
      <n-radio-group v-model:value="s.managed_mode">
        <n-radio-button :value="true">{{ t('settings.modeManaged') }}</n-radio-button>
        <n-radio-button :value="false">{{ t('settings.modeCustom') }}</n-radio-button>
      </n-radio-group>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <span>{{ t('settings.allowPhp') }}</span>
        <n-switch v-model:value="s.allow_php" />
      </div>
      <div class="cp-muted">{{ t('settings.modeNote') }}</div>
      <div>
        <div class="cp-muted">{{ t('settings.styleTitle') }}</div>
        <n-select
          v-model:value="s.caddyfile_style"
          :options="[
            { label: t('settings.styleImport'), value: 'import' },
            { label: t('settings.styleConcat'), value: 'concat' },
          ]"
          style="margin-top:4px"
        />
      </div>
      <div class="cp-muted">{{ t('settings.styleNote') }}</div>
    </div>
  </div>

  <div class="cp-card">
    <div class="cp-card-title">{{ t('settings.panelTitle') }}</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-width:640px">
      <div>
        <div class="cp-muted">{{ t('settings.panelName') }}</div>
        <n-input v-model:value="s.panel_name" style="margin-top:4px" />
      </div>
    </div>
  </div>

  <div class="cp-card">
    <div class="cp-card-title">{{ t('settings.tokenTitle') }}</div>
    <div class="cp-muted">{{ t('settings.tokenDesc') }}</div>
    <div class="cp-actions" style="margin-top:10px">
      <n-button size="small" @click="genToken">{{ t('settings.tokenGenerate') }}</n-button>
    </div>
    <div v-if="token" class="cp-code" style="margin-top:10px;word-break:break-all">{{ token }}</div>
  </div>

  <div class="cp-card">
    <div class="cp-card-title">{{ t('settings.deployTitle') }}</div>
    <div class="cp-muted">{{ s.installed ? t('settings.installed') : t('settings.notInstalled') }}</div>
    <div class="cp-actions" style="margin-top:12px">
      <n-button type="primary" @click="saveSettings">{{ t('settings.save') }}</n-button>
    </div>
  </div>
</template>
