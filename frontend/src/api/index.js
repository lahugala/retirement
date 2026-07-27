import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_BASE || '/api';

const api = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res.data,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/retirement/login'
    }
    return Promise.reject(err.response?.data || err)
  },
)

export default api

// ---- Auth ----
export const auth = {
  login: (data) => api.post('/auth/login', data),
  register: (data) => api.post('/auth/register', data),
  me: () => api.get('/auth/me'),
}

// ---- Users ----
export const users = {
  list: (params) => api.get('/users', { params }),
  get: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id, data) => api.delete(`/users/${id}`, { data }),
}

// ---- Members (retiree directory) ----
export const members = {
  list: (params) => api.get('/members', { params }),
  get: (id) => api.get(`/members/${id}`),
  create: (data) => api.post('/members', data),
  update: (id, data) => api.put(`/members/${id}`, data),
  delete: (id) => api.delete(`/members/${id}`),
}

// ---- Categories ----
export const categories = {
  list: (params) => api.get('/categories', { params }),
  get: (id) => api.get(`/categories/${id}`),
  create: (data) => api.post('/categories', data),
  update: (id, data) => api.put(`/categories/${id}`, data),
  delete: (id) => api.delete(`/categories/${id}`),
}

// ---- Events ----
export const events = {
  list: (params) => api.get('/events', { params }),
  get: (id) => api.get(`/events/${id}`),
  create: (data) => api.post('/events', data),
  update: (id, data) => api.put(`/events/${id}`, data),
  delete: (id) => api.delete(`/events/${id}`),
  updateStatus: (id, data) => api.put(`/events/${id}/status`, data),
  generateQuarters: (data) => api.post('/events/generate-quarters', data),
}

// ---- Budgets ----
export const budgets = {
  list: (eventId) => api.get(`/events/${eventId}/budgets`),
  create: (eventId, data) => api.post(`/events/${eventId}/budgets`, data),
  update: (id, data) => api.put(`/budgets/${id}`, data),
  delete: (id) => api.delete(`/budgets/${id}`),
}

// ---- Transactions ----
export const transactions = {
  list: (params) => api.get('/transactions', { params }),
  get: (id) => api.get(`/transactions/${id}`),
  create: (data) => api.post('/transactions', data),
  update: (id, data) => api.put(`/transactions/${id}`, data),
  delete: (id, data) => api.delete(`/transactions/${id}`, { data }),
  approve: (id) => api.put(`/transactions/${id}/approve`),
  reject: (id, data) => api.put(`/transactions/${id}/reject`, data),
}

// ---- Upload ----
export const uploads = {
  upload: (file) => {
    const fd = new FormData()
    fd.append('file', file)
    return api.post('/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
}

// ---- Reports ----
export const reports = {
  dashboard: (params) => api.get('/reports/dashboard', { params }),
  incomeStatement: (params) => api.get('/reports/income-statement', { params }),
  eventProfitLoss: (eventId) => api.get(`/reports/event-profit-loss/${eventId}`),
  memberContributions: (params) => api.get('/reports/member-contributions', { params }),
  quarterlySummary: (params) => api.get('/reports/quarterly-summary', { params }),
  retiredMembers: (params) => api.get('/reports/retired-members', { params }),
}

// ---- Audit Logs ----
export const auditLogs = {
  list: (params) => api.get('/audit-logs', { params }),
}
