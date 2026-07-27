<template>
  <div>
    <!-- Header row with year selector -->
    <a-row :gutter="[16, 16]" style="margin-bottom: 8px">
      <a-col :flex="'auto'">
        <span style="font-size: 20px; font-weight: 600; color: #262626">Dashboard</span>
      </a-col>
      <a-col :flex="'160px'">
        <a-select v-model:value="selectedYear" style="width: 100%" @change="refreshAll">
          <a-select-option v-for="y in availableYears" :key="y" :value="y">{{ y }}</a-select-option>
        </a-select>
      </a-col>
    </a-row>

    <!-- Stat cards -->
    <a-row :gutter="[16, 16]">
      <a-col :xs="24" :sm="12" :lg="6">
        <a-card :loading="loading" class="stat-card income-card" :body-style="{ padding: '20px 24px' }">
          <div class="stat-inner">
            <div class="stat-icon" style="background: #f6ffed; color: #52c41a">
              <ArrowUpOutlined />
            </div>
            <div class="stat-body">
              <a-statistic title="Total Income" :value="data.total_income" prefix="Rs. " :value-style="{ color: '#3f8600', fontSize: '22px' }" />
            </div>
          </div>
        </a-card>
      </a-col>
      <a-col :xs="24" :sm="12" :lg="6">
        <a-card :loading="loading" class="stat-card expense-card" :body-style="{ padding: '20px 24px' }">
          <div class="stat-inner">
            <div class="stat-icon" style="background: #fff2f0; color: #ff4d4f">
              <ArrowDownOutlined />
            </div>
            <div class="stat-body">
              <a-statistic title="Total Expense" :value="data.total_expense" prefix="Rs. " :value-style="{ color: '#cf1322', fontSize: '22px' }" />
            </div>
          </div>
        </a-card>
      </a-col>
      <a-col :xs="24" :sm="12" :lg="6">
        <a-card :loading="loading" class="stat-card" :body-style="{ padding: '20px 24px' }">
          <div class="stat-inner">
            <div class="stat-icon" :style="{ background: data.net_balance >= 0 ? '#f0f5ff' : '#fff2f0', color: data.net_balance >= 0 ? '#1890ff' : '#ff4d4f' }">
              <WalletOutlined />
            </div>
            <div class="stat-body">
              <a-statistic title="Net Balance" :value="data.net_balance" prefix="Rs. " :value-style="{ color: data.net_balance >= 0 ? '#3f8600' : '#cf1322', fontSize: '22px' }" />
            </div>
          </div>
        </a-card>
      </a-col>
      <a-col :xs="24" :sm="12" :lg="6">
        <a-card :loading="loading" class="stat-card events-card" :body-style="{ padding: '20px 24px' }">
          <div class="stat-inner">
            <div class="stat-icon" style="background: #fff7e6; color: #fa8c16">
              <CalendarOutlined />
            </div>
            <div class="stat-body">
              <a-statistic title="Active Events" :value="data.active_events" suffix="events" :value-style="{ fontSize: '22px' }" />
            </div>
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- Row 2: Quarter overview -->
    <a-row :gutter="[16, 16]" style="margin-top: 16px">
      <a-col :span="24">
        <a-card title="Quarterly Planning" :loading="loading">
          <div v-if="defaultPeriods.length > 0 || customEventList.length > 0">
            <h5 style="margin: 0 0 8px 0; color: #666; font-weight: 500">Standard Periods</h5>
            <div class="quarter-list" style="margin-bottom: 16px">
              <div v-for="q in defaultPeriods" :key="q.num" class="quarter-item" @click="goToEvent(q.event)" :style="{ cursor: q.event ? 'pointer' : 'default' }">
                <div class="quarter-header">
                  <span class="quarter-label">{{ q.label }}</span>
                  <a-tag v-if="q.event" :color="statusColor(q.event.status)" size="small">{{ q.event.status }}</a-tag>
                  <a-tag v-else color="default">Not planned</a-tag>
                </div>
                <div v-if="q.event">
                  <div class="quarter-amounts">
                    <span>Budget: <strong>Rs. {{ Number(q.event.budget_allocated || 0).toFixed(0) }}</strong></span>
                    <span :style="{ color: q.totalExpense > (q.event.budget_allocated || 0) ? '#cf1322' : '#3f8600' }">
                      Spent: <strong>Rs. {{ Number(q.totalExpense || 0).toFixed(0) }}</strong>
                    </span>
                  </div>
                  <a-progress
                    :percent="q.event.budget_allocated > 0 ? Math.min(100, Math.round(((q.totalExpense || 0) / q.event.budget_allocated) * 100)) : 0"
                    :status="q.totalExpense > q.event.budget_allocated ? 'exception' : 'active'"
                    size="small"
                  />
                </div>
                <div v-else class="quarter-empty">
                  <a-button size="small" type="dashed" @click.stop="createQuarter(q.num)" v-if="canCreate">
                    <PlusOutlined /> Plan P{{ q.num }}
                  </a-button>
                </div>
              </div>
            </div>
            <div v-if="customEventList.length > 0">
              <h5 style="margin: 0 0 8px 0; color: #666; font-weight: 500">Custom Events</h5>
              <div class="quarter-list">
                <div v-for="evt in customEventList" :key="evt.id" class="quarter-item" @click="goToEvent(evt)" style="cursor: pointer">
                  <div class="quarter-header">
                    <span class="quarter-label">{{ evt.name }}</span>
                    <a-tag :color="statusColor(evt.status)" size="small">{{ evt.status }}</a-tag>
                  </div>
                  <div class="quarter-amounts">
                    <span>Budget: <strong>Rs. {{ Number(evt.budget_allocated || 0).toFixed(0) }}</strong></span>
                    <span :style="{ color: Number(evt.total_expense || 0) > Number(evt.budget_allocated || 0) ? '#cf1322' : '#3f8600' }">
                      Spent: <strong>Rs. {{ Number(evt.total_expense || 0).toFixed(0) }}</strong>
                    </span>
                  </div>
                  <a-progress
                    :percent="Number(evt.budget_allocated || 0) > 0 ? Math.min(100, Math.round(((Number(evt.total_expense || 0) / Number(evt.budget_allocated || 0)) * 100))) : 0"
                    :status="Number(evt.total_expense || 0) > Number(evt.budget_allocated || 0) ? 'exception' : 'active'"
                    size="small"
                  />
                </div>
              </div>
            </div>
          </div>
          <div v-else-if="!loading" style="text-align: center; padding: 32px; color: #999">
            <InboxOutlined style="font-size: 40px" />
            <p style="margin-top: 8px">No events for {{ selectedYear }}</p>
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- Row 3: Recent transactions + Pending + Categories -->
    <a-row :gutter="[16, 16]" style="margin-top: 16px">
      <a-col :xs="24" :lg="12">
        <a-card title="Recent Transactions" :loading="loading">
          <template #extra>
            <a-button type="link" size="small" @click="$router.push({ name: 'Transactions' })">View All</a-button>
          </template>
          <a-list v-if="recentTransactions.length > 0" item-layout="horizontal" :data-source="recentTransactions" size="small">
            <template #renderItem="{ item }">
              <a-list-item>
                <a-list-item-meta>
                  <template #title>
                    <span>{{ item.description || item.category_name || 'Transaction' }}</span>
                    <a-tag :color="item.type === 'income' ? 'green' : 'red'" style="margin-left: 8px">{{ item.type }}</a-tag>
                  </template>
                  <template #description>
                    {{ item.transaction_date }}
                    <span v-if="item.payee"> &middot; {{ item.payee }}</span>
                    <a-tag v-if="item.event_name" style="margin-left: 4px">{{ item.event_name }}</a-tag>
                  </template>
                </a-list-item-meta>
                <div style="text-align: right">
                  <div :style="{ color: item.type === 'income' ? '#3f8600' : '#cf1322', fontWeight: 600 }">
                    {{ item.type === 'income' ? '+' : '-' }} Rs. {{ Number(item.amount).toFixed(2) }}
                  </div>
                  <a-tag :color="txnStatusColor(item.status)" size="small" style="font-size: 10px">{{ item.status }}</a-tag>
                </div>
              </a-list-item>
            </template>
          </a-list>
          <div v-else style="text-align: center; padding: 32px; color: #999">
            <InboxOutlined style="font-size: 40px" />
            <p style="margin-top: 8px">No transactions yet</p>
          </div>
        </a-card>
      </a-col>
      <a-col :xs="24" :lg="12">
        <a-card title="Top Categories" :loading="loading">
          <div ref="categoryChartRef" style="height: 260px"></div>
        </a-card>
      </a-col>
    </a-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { reports as reportsApi, events as eventsApi, transactions as txnApi } from '../api/index.js'
import { message } from 'ant-design-vue'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(true)
const selectedYear = ref(new Date().getFullYear())

const data = reactive({
  total_income: 0, total_expense: 0, net_balance: 0,
  active_events: 0, pending_approvals: 0,
  monthly_trend: [], top_categories: [],
})

const defaultPeriods = ref([])
const customEventList = ref([])
const recentTransactions = ref([])
const categoryChartRef = ref(null)
let categoryChartInstance = null

const canCreate = computed(() => ['admin', 'treasurer', 'organizer'].includes(auth.user?.role))

const availableYears = computed(() => {
  const years = [new Date().getFullYear()]
  for (let i = 1; i <= 3; i++) years.push(new Date().getFullYear() + i)
  return years
})

const quarterDefs = [
  { num: 1, label: 'P1 · Jan-Apr' },
  { num: 2, label: 'P2 · May-Aug' },
  { num: 3, label: 'P3 · Sep-Dec' },
]

function statusColor(s) {
  return { planned: 'blue', active: 'green', completed: 'default', cancelled: 'red' }[s] || 'default'
}
function txnStatusColor(s) {
  return { draft: 'default', pending_approval: 'orange', approved: 'green', rejected: 'red' }[s] || 'default'
}

function goToEvent(event) {
  if (event) router.push(`/events/${event.id}`)
}

async function createQuarter(qNum) {
  const qLabels = { 1: 'Jan-Apr', 2: 'May-Aug', 3: 'Sep-Dec' }
  try {
    await eventsApi.create({
      name: `P${qNum} ${qLabels[qNum]} ${selectedYear.value}`,
      quarter: qNum,
      year: selectedYear.value,
    })
    message.success(`P${qNum} planned`)
    await fetchQuarters()
  } catch (e) { message.error(e?.message || 'Failed') }
}

async function refreshAll() {
  loading.value = true
  await Promise.all([fetchDashboard(), fetchQuarters(), fetchRecentTransactions()])
  loading.value = false
}

async function fetchDashboard() {
  try {
    const res = await reportsApi.dashboard({ year: selectedYear.value })
    Object.assign(data, res.data)
  } catch {}
}

async function fetchQuarters() {
  try {
    const res = await eventsApi.list({ year: selectedYear.value, per_page: 20 })
    const items = res.data?.items || []
    defaultPeriods.value = quarterDefs.map((qd) => {
      const event = items.find((e) => Number(e.quarter) === qd.num)
      return { ...qd, event, totalExpense: event ? Number(event.total_expense || 0) : 0 }
    })
    customEventList.value = items.filter((e) => ![1, 2, 3].includes(Number(e.quarter)))
  } catch { defaultPeriods.value = []; customEventList.value = [] }
}

async function fetchRecentTransactions() {
  try {
    const res = await txnApi.list({ per_page: 5 })
    recentTransactions.value = res.data?.items || []
  } catch { recentTransactions.value = [] }
}

function destroyCharts() {
  if (categoryChartInstance) { categoryChartInstance.destroy(); categoryChartInstance = null }
}

async function renderCharts() {
  destroyCharts()
  try {
    const { Pie } = await import('@antv/g2plot')

    const catData = (data.top_categories || []).map((d) => ({
      type: d.name,
      value: parseFloat(d.total || 0),
    }))

    if (catData.length > 0) {
      categoryChartInstance = new Pie(categoryChartRef.value, {
        data: catData,
        angleField: 'value',
        colorField: 'type',
        radius: 0.7,
        innerRadius: 0.3,
        label: {
          type: 'outer',
          formatter: (v) => `${v.type}: Rs. ${Number(v.value).toFixed(0)}`,
          autoRotate: true,
          style: { fontSize: 10 },
        },
        statistic: {
          title: false,
          content: { style: { fontSize: 14, fontWeight: 600 } },
        },
        legend: { position: 'bottom' },
        tooltip: {
          formatter: (datum) => ({
            name: datum.type,
            value: `Rs. ${Number(datum.value).toFixed(2)}`,
          }),
        },
      })
      categoryChartInstance.render()
    }
  } catch (e) {
    console.warn('Chart rendering skipped:', e.message)
  }
}

onMounted(async () => {
  loading.value = true
  await Promise.all([fetchDashboard(), fetchQuarters(), fetchRecentTransactions()])
  await nextTick()
  renderCharts()
  loading.value = false
})

onUnmounted(() => {
  destroyCharts()
})
</script>

<style scoped>
.stat-card {
  border-radius: 8px;
  transition: box-shadow 0.3s;
}
.stat-card:hover {
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
.stat-inner {
  display: flex;
  align-items: center;
  gap: 16px;
}
.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}
.stat-body {
  flex: 1;
  min-width: 0;
}
.quarter-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.quarter-item {
  border: 1px solid #f0f0f0;
  border-radius: 8px;
  padding: 12px;
  transition: all 0.3s;
}
.quarter-item:hover {
  border-color: #1890ff;
  background: #f0f5ff;
}
.quarter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.quarter-label {
  font-weight: 600;
  font-size: 13px;
}
.quarter-amounts {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #666;
  margin-bottom: 6px;
}
.quarter-empty {
  text-align: center;
  padding: 4px 0;
}
</style>
