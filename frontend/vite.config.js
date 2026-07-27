import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/retirement/',
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost/retirement/backend',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://localhost/retirement/backend',
        changeOrigin: true,
      },
    },
  },
})
