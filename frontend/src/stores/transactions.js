import { defineStore } from 'pinia'
import { transactions as api } from '../api/index.js'

export const useTransactionStore = defineStore('transactions', {
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
        this.page = res.data.page
      } finally {
        this.loading = false
      }
    },
    async create(data) {
      const res = await api.create(data)
      return res
    },
    async update(id, data) {
      return await api.update(id, data)
    },
    async approve(id) {
      return await api.approve(id)
    },
    async reject(id, reason) {
      return await api.reject(id, { reject_reason: reason })
    },
    async submit(id) {
      return await api.submit(id)
    },
    async delete(id, reason) {
      return await api.delete(id, { delete_reason: reason })
    },
  },
})
