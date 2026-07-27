import { defineStore } from 'pinia'
import { auth as authApi } from '../api/index.js'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    token: localStorage.getItem('token') || null,
    loading: false,
  }),
  getters: {
    isLoggedIn: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin',
    isTreasurer: (state) => state.user?.role === 'treasurer',
    isBoard: (state) => state.user?.role === 'board',
    isOrganizer: (state) => state.user?.role === 'organizer',
    canApprove: (state) => ['admin', 'treasurer', 'board'].includes(state.user?.role),
    roleLabel: (state) => {
      const labels = { admin: 'Admin', treasurer: 'Treasurer', organizer: 'Event Organizer', board: 'Board Member', member: 'Member' }
      return labels[state.user?.role] || state.user?.role
    },
  },
  actions: {
    async login(email, password) {
      this.loading = true
      try {
        const res = await authApi.login({ email, password })
        this.token = res.data.token
        this.user = res.data.user
        localStorage.setItem('token', res.data.token)
        localStorage.setItem('user', JSON.stringify(res.data.user))
        return res
      } finally {
        this.loading = false
      }
    },
    async register(name, email, password) {
      this.loading = true
      try {
        const res = await authApi.register({ name, email, password })
        this.token = res.data.token
        this.user = res.data.user
        localStorage.setItem('token', res.data.token)
        localStorage.setItem('user', JSON.stringify(res.data.user))
        return res
      } finally {
        this.loading = false
      }
    },
    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    },
    async fetchMe() {
      try {
        const res = await authApi.me()
        this.user = res.data
        localStorage.setItem('user', JSON.stringify(res.data))
      } catch {
        this.logout()
      }
    },
  },
})
