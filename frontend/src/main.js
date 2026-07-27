import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Antd from 'ant-design-vue'
import 'ant-design-vue/dist/reset.css'
import * as Icons from '@ant-design/icons-vue'
import App from './App.vue'
import router from './router/index.js'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(Antd)

// Register all Ant Design icons globally
Object.keys(Icons).forEach((key) => {
  app.component(key, Icons[key])
})

app.mount('#app')
