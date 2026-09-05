<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const route = useRoute()
const { t } = useI18n()
</script>

<template>
  <div class="cp-card">
    <div class="cp-card-title">
      {{ t((route.meta.titleKey as string) || 'nav.dashboard') }}
      <span class="cp-pill cp-pill-amber" style="margin-left:8px">{{ t('placeholder.p1') }}</span>
    </div>
    <div class="cp-empty">
      <div style="font-size:15px;margin-bottom:6px">{{ t('placeholder.body') }}</div>
      <div class="cp-muted">{{ t('placeholder.sub') }}</div>
    </div>
  </div>
</template>
