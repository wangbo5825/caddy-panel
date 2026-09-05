<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NInput, NSelect, useMessage } from 'naive-ui'
import { useI18n } from 'vue-i18n'
import { request } from '../api'
import { currentLocale, setLocale } from '../i18n'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const router = useRouter()
const message = useMessage()
const auth = useAuthStore()

const hasUser = ref(true)
const username = ref('admin')
const password = ref('')
const token = ref('')
const busy = ref(false)
const locale = ref(currentLocale())

function changeLocale(v: string) {
  locale.value = v
  setLocale(v === 'zh' ? 'zh' : 'en')
  location.reload()
}

onMounted(async () => {
  const me = await request('/auth/me')
  hasUser.value = !!me.has_user
})

async function submit() {
  busy.value = true
  try {
    if (hasUser.value) {
      await auth.login(username.value, password.value)
      message.success(t('login.msgIn'))
    } else {
      await auth.initAccount(token.value, username.value, password.value)
      message.success(t('login.msgInit'))
    }
    router.push('/')
  } catch (e: any) {
    message.error(e.message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div style="min-height:100vh;display:grid;place-items:center;padding:20px">
    <div class="cp-card" style="width:400px;max-width:100%">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div class="cp-brand" style="border:0;padding:0 0 6px"><span class="cp-logo">⌂</span> {{ t('app.brand') }}</div>
        <n-select
          :value="locale"
          :options="[{ label: 'English', value: 'en' }, { label: '中文', value: 'zh' }]"
          size="small"
          style="width:100px"
          @update:value="changeLocale"
        />
      </div>
      <div class="cp-card-title">{{ hasUser ? t('login.loginTitle') : t('login.initTitle') }}</div>
      <div v-if="!hasUser" class="cp-muted" style="margin-bottom:8px">
        {{ t('login.initHint') }}<span class="cp-code">{{ t('login.initHintCli') }}</span>{{ t('login.initHintOr') }}
      </div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <div class="cp-muted">{{ t('login.account') }}</div>
          <n-input v-model:value="username" style="margin-top:4px" />
        </div>
        <div v-if="!hasUser">
          <div class="cp-muted">{{ t('login.token') }}</div>
          <n-input v-model:value="token" :placeholder="t('login.tokenPh')" style="margin-top:4px" />
        </div>
        <div>
          <div class="cp-muted">{{ t('login.password') }}</div>
          <n-input v-model:value="password" type="password" show-password-on="click" style="margin-top:4px" @keyup.enter="submit" />
        </div>
        <n-button type="primary" :loading="busy" @click="submit">{{ hasUser ? t('login.submit') : t('login.initSubmit') }}</n-button>
        <div class="cp-muted">{{ t('login.rateHint') }}</div>
      </div>
    </div>
  </div>
</template>
