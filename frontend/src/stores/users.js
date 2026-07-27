import { defineStore } from 'pinia'
import { users as api } from '../api/index.js'

export const useUserStore = defineStore('users', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    loading: false,
  }),
  actions: {
    async fetch(params = {}) {
      this.loading = true
      try {
        const res = await api.list({ page: this.page, ...params })
        this.items = res.data.items
        this.total = res.data.total
      } finally {
        this.loading = false
      }
    },
    async create(data) { return await api.create(data) },
    async update(id, data) { return await api.update(id, data) },
    async delete(id) { return await api.delete(id) },
  },
})
