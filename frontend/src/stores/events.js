import { defineStore } from 'pinia'
import { events as api, budgets as budgetApi } from '../api/index.js'

export const useEventStore = defineStore('events', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    currentEvent: null,
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
    async get(id) {
      this.loading = true
      try {
        const res = await api.get(id)
        this.currentEvent = res.data
        return res.data
      } finally {
        this.loading = false
      }
    },
    async create(data) { return await api.create(data) },
    async update(id, data) { return await api.update(id, data) },
    async updateStatus(id, status) { return await api.updateStatus(id, { status }) },
    async delete(id) { return await api.delete(id) },

    // Budgets
    async fetchBudgets(eventId) {
      const res = await budgetApi.list(eventId)
      return res.data
    },
    async createBudget(eventId, data) { return await budgetApi.create(eventId, data) },
    async updateBudget(id, data) { return await budgetApi.update(id, data) },
    async deleteBudget(id) { return await budgetApi.delete(id) },
  },
})
