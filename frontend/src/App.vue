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
</style>
