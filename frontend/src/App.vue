<template>
  <a-config-provider :locale="locale">
    <router-view />
  </a-config-provider>
</template>

<script setup>
import { ref } from 'vue'
import dayjs from 'dayjs'

// Basic locale for Ant Design (English)
const locale = ref(null)

// Set dayjs locale
import 'dayjs/locale/en'
dayjs.locale('en')
</script>

<style>
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: #f0f2f5;
}
</style>
