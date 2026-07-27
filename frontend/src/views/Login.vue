<template>
  <div class="login-container">
    <a-card class="login-card" :bordered="false">
      <template #title>
        <h2 style="text-align: center; margin: 0">
          <GiftOutlined /> Retirement Society
        </h2>
      </template>

      <a-tabs v-model:activeKey="activeTab" centered>
        <a-tab-pane key="login" tab="Sign In">
          <a-form :model="loginForm" layout="vertical" @finish="handleLogin">
            <a-form-item label="Email" name="email" :rules="[{ required: true, type: 'email' }]">
              <a-input v-model:value="loginForm.email" placeholder="your@email.com">
                <template #prefix><MailOutlined /></template>
              </a-input>
            </a-form-item>
            <a-form-item label="Password" name="password" :rules="[{ required: true }]">
              <a-input-password v-model:value="loginForm.password" placeholder="Password">
                <template #prefix><LockOutlined /></template>
              </a-input-password>
            </a-form-item>
            <a-form-item>
              <a-button type="primary" html-type="submit" :loading="auth.loading" block>
                Sign In
              </a-button>
            </a-form-item>
          </a-form>
        </a-tab-pane>

        <a-tab-pane key="register" tab="Register">
          <a-form :model="registerForm" layout="vertical" @finish="handleRegister">
            <a-form-item label="Full Name" name="name" :rules="[{ required: true }]">
              <a-input v-model:value="registerForm.name" placeholder="Your name">
                <template #prefix><UserOutlined /></template>
              </a-input>
            </a-form-item>
            <a-form-item label="Email" name="email" :rules="[{ required: true, type: 'email' }]">
              <a-input v-model:value="registerForm.email" placeholder="your@email.com">
                <template #prefix><MailOutlined /></template>
              </a-input>
            </a-form-item>
            <a-form-item label="Password" name="password" :rules="[{ required: true, min: 6 }]">
              <a-input-password v-model:value="registerForm.password" placeholder="Min 6 characters">
                <template #prefix><LockOutlined /></template>
              </a-input-password>
            </a-form-item>
            <a-form-item>
              <a-button type="primary" html-type="submit" :loading="auth.loading" block>
                Register
              </a-button>
            </a-form-item>
          </a-form>
        </a-tab-pane>
      </a-tabs>
    </a-card>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { message } from 'ant-design-vue'

const auth = useAuthStore()
const router = useRouter()
const activeTab = ref('login')
const loginForm = reactive({ email: '', password: '' })
const registerForm = reactive({ name: '', email: '', password: '' })

async function handleLogin() {
  try {
    await auth.login(loginForm.email, loginForm.password)
    message.success('Welcome back!')
    router.push('/dashboard')
  } catch (e) {
    message.error(e?.message || 'Login failed')
  }
}

async function handleRegister() {
  try {
    await auth.register(registerForm.name, registerForm.email, registerForm.password)
    message.success('Registration successful!')
    router.push('/dashboard')
  } catch (e) {
    message.error(e?.message || 'Registration failed')
  }
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 24px;
}
.login-card {
  width: 100%;
  max-width: 420px;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}
</style>
