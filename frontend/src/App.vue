<template>
  <a-config-provider
    :locale="locale"
    :theme="{ algorithm: isDark ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm }"
  >
    <router-view />
  </a-config-provider>
</template>

<script setup>
import { ref } from 'vue'
import dayjs from 'dayjs'
import { theme as antdTheme } from 'ant-design-vue'
import { useTheme } from './composables/useTheme.js'

// Basic locale for Ant Design (English)
const locale = ref(null)
const { isDark } = useTheme()

// Set dayjs locale
import 'dayjs/locale/en'
dayjs.locale('en')
</script>

<style>
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: #f0f2f5;
  transition: background-color 0.2s, color 0.2s;
}

html[data-theme='dark'],
html[data-theme='dark'] body {
  background: #141414;
  color: rgba(255, 255, 255, 0.85);
}

html[data-theme='dark'] .content-wrapper {
  color: rgba(255, 255, 255, 0.85);
}

/* Footer */
html[data-theme='dark'] .app-footer {
  background: #1f1f1f !important;
  border-top-color: #303030 !important;
  color: rgba(255, 255, 255, 0.45) !important;
}

/* Section headers (h4, h5) */
html[data-theme='dark'] h4,
html[data-theme='dark'] h5 {
  color: rgba(255, 255, 255, 0.65) !important;
}

/* Profile card detail rows */
html[data-theme='dark'] .profile-detail-row {
  background: #1f1f1f !important;
  border-color: #303030 !important;
}
html[data-theme='dark'] .profile-detail-value {
  color: rgba(255, 255, 255, 0.85) !important;
}
html[data-theme='dark'] .profile-detail-label {
  color: rgba(255, 255, 255, 0.45) !important;
}

/* Empty state / placeholder text */
html[data-theme='dark'] .ant-empty-description {
  color: rgba(255, 255, 255, 0.45) !important;
}
</style>
