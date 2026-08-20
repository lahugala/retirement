<template>
  <div>
    <a-page-header title="Event Planning" sub-title="Plan and manage events throughout the year">
      <template #extra>
        <a-space>
          <a-select v-model:value="selectedYear" style="width: 120px" @change="fetchData">
            <a-select-option v-for="y in availableYears" :key="y" :value="y">{{ y }}</a-select-option>
          </a-select>
          <a-button v-if="missingQuarters.length > 0" @click="showCreateMissing" type="primary">
            <PlusOutlined /> Create {{ missingQuarters.length }} Missing
          </a-button>
          <a-button @click="showGenerateModal" type="primary" v-if="canCreate">
            <CalendarOutlined /> Custom Event Creator
          </a-button>
        </a-space>
      </template>
    </a-page-header>

    <!-- Financial Summary Bar -->
    <a-row :gutter="[16, 16]" style="margin-bottom: 20px">
      <a-col :span="6">
        <a-statistic title="Current Net Balance" :value="netBalance" prefix="Rs." precision="2" :value-style="{ color: netBalance >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
      </a-col>
      <a-col :span="6">
        <a-statistic :title="'Total Budgeted (' + selectedYear + ')'" :value="totalBudgeted" prefix="Rs." precision="2" />
      </a-col>
      <a-col :span="6">
        <a-statistic :title="'Total Expense (' + selectedYear + ')'" :value="totalExpensed" prefix="Rs." precision="2" :value-style="{ color: totalExpensed > totalBudgeted ? '#cf1322' : 'inherit' }" />
      </a-col>
      <a-col :span="6">
        <a-statistic title="Available for Planning" :value="availableForPlanning" prefix="Rs." precision="2" :value-style="{ color: availableForPlanning >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
      </a-col>
    </a-row>

    <!-- Default Periods: P1 Jan-Apr, P2 May-Aug, P3 Sep-Dec -->
    <h4 style="margin: 0 0 12px 0; color: #666; font-weight: 500">Standard Periods</h4>
    <a-row :gutter="[16, 16]">
      <a-col :xs="24" :sm="12" :lg="8" v-for="q in defaultPeriods" :key="q.num">
        <a-card
          :title="q.label"
          :loading="loading"
          hoverable
          :body-style="{ padding: '16px' }"
          @click="q.event ? $router.push(`/events/${q.event.id}`) : null"
          :style="q.event ? 'cursor: pointer' : ''"
        >
          <template #extra>
            <a-tag :color="statusColor(q.event?.status)" v-if="q.event">{{ q.event.status }}</a-tag>
            <a-tag v-else color="default">Not created</a-tag>
          </template>

          <div v-if="q.event">
            <a-descriptions :column="1" size="small">
              <a-descriptions-item label="Budget">
                Rs. {{ Number(q.event.budget_allocated || 0).toFixed(2) }}
              </a-descriptions-item>
              <a-descriptions-item label="Expenses">
                <span :style="{ color: q.totalExpense > (q.event.budget_allocated || 0) ? '#cf1322' : '#3f8600' }">
                  Rs. {{ Number(q.totalExpense || 0).toFixed(2) }}
                </span>
              </a-descriptions-item>
              <a-descriptions-item label="Remaining">
                Rs. {{ Number((q.event.budget_allocated || 0) - (q.totalExpense || 0)).toFixed(2) }}
              </a-descriptions-item>
              <a-descriptions-item label="Retirees">
                {{ q.event.retiree_name || '-' }}
              </a-descriptions-item>
            </a-descriptions>

            <a-progress
              :percent="q.event.budget_allocated > 0 ? Math.min(100, Math.round(((q.totalExpense || 0) / q.event.budget_allocated) * 100)) : 0"
              :status="q.totalExpense > q.event.budget_allocated ? 'exception' : 'active'"
              size="small"
              style="margin-top: 12px"
            />

            <div style="margin-top: 12px; display: flex; gap: 8px; justify-content: flex-end">
              <a-button size="small" @click.stop="$router.push(`/events/${q.event.id}`)">Manage</a-button>
              <a-button size="small" @click.stop="addRetiree(q.event)" v-if="canCreate">+ Retiree</a-button>
            </div>
          </div>

          <div v-else style="text-align: center; padding: 8px">
            <p style="color: #999">No event planned</p>
            <a-button size="small" @click.stop="createQuarter(q.num)" v-if="canCreate">
              <PlusOutlined /> Create P{{ q.num }}
            </a-button>
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- Custom Events (created via Custom Event Creator) -->
    <div v-if="customEvents.length > 0" style="margin-top: 24px">
      <h4 style="margin: 0 0 12px 0; color: #666; font-weight: 500">Custom Events</h4>
      <a-row :gutter="[16, 16]">
        <a-col :xs="24" :sm="12" :lg="8" v-for="evt in customEvents" :key="evt.id">
          <a-card
            :title="evt.name"
            :loading="loading"
            hoverable
            :body-style="{ padding: '16px' }"
            @click="$router.push(`/events/${evt.id}`)"
            style="cursor: pointer"
          >
            <template #extra>
              <a-tag :color="statusColor(evt.status)">{{ evt.status }}</a-tag>
            </template>
            <a-descriptions :column="1" size="small">
              <a-descriptions-item label="Quarter">P{{ evt.quarter }}</a-descriptions-item>
              <a-descriptions-item label="Budget">Rs. {{ Number(evt.budget_allocated || 0).toFixed(2) }}</a-descriptions-item>
              <a-descriptions-item label="Expenses">
                <span :style="{ color: Number(evt.total_expense || 0) > Number(evt.budget_allocated || 0) ? '#cf1322' : '#3f8600' }">
                  Rs. {{ Number(evt.total_expense || 0).toFixed(2) }}
                </span>
              </a-descriptions-item>
              <a-descriptions-item label="Retirees">{{ evt.retiree_name || '-' }}</a-descriptions-item>
            </a-descriptions>
            <div style="margin-top: 12px; display: flex; gap: 8px; justify-content: flex-end">
              <a-button size="small" @click.stop="$router.push(`/events/${evt.id}`)">Manage</a-button>
            </div>
          </a-card>
        </a-col>
      </a-row>
    </div>

    <!-- Retiree Name Modal -->
    <a-modal v-model:visible="retireeModalVisible" title="Add Retiree(s)" @ok="confirmRetiree" :confirm-loading="retireeSaving" destroyOnClose>
      <a-form layout="vertical">
        <a-form-item label="Select Members" required>
          <a-select v-model:value="selectedRetirees" mode="multiple" placeholder="Search and select members" style="width: 100%" :options="memberOptions" :filter-option="(input, option) => option.label.toLowerCase().includes(input.toLowerCase())" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Custom Event Creator Modal -->
    <a-modal v-model:visible="generateModalVisible" title="Custom Event Creator" @ok="confirmGenerate" :confirm-loading="generating" ok-text="Create Events" :width="440" destroyOnClose>
      <p style="margin-bottom: 12px">Define periods for <strong>{{ selectedYear }}</strong>:</p>
      <div v-for="(p, i) in customPeriods" :key="i" style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px">
        <a-input v-model:value="p.label" placeholder="Label" size="small" style="flex: 1" />
        <a-input-number v-model:value="p.quarter" :min="1" :max="12" size="small" style="width: 60px" placeholder="#"/>
        <a-button danger type="link" size="small" @click="removePeriod(i)" v-if="customPeriods.length > 1">
          <DeleteOutlined />
        </a-button>
      </div>
      <a-button type="dashed" block size="small" @click="addPeriod" style="margin-bottom: 8px">
        <PlusOutlined /> Add Period
      </a-button>
      <a-input v-model:value="namePattern" placeholder="Name pattern" size="small" style="margin-bottom: 4px" />
      <p style="color: #999; font-size: 11px">Use {quarter}, {label}, {year} &middot; Existing periods skipped.</p>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useEventStore } from '../stores/events.js'
import { useAuthStore } from '../stores/auth.js'
import { events as eventsApi, reports as reportsApi, members as membersApi } from '../api/index.js'
import { message } from 'ant-design-vue'

const router = useRouter()
const store = useEventStore()
const auth = useAuthStore()
const loading = ref(true)
const selectedYear = ref(new Date().getFullYear())
const allEvents = ref([])

const retireeModalVisible = ref(false)
const retireeTarget = ref(null)
const selectedRetirees = ref([])
const memberOptions = ref([])
const retireeSaving = ref(false)

const generateModalVisible = ref(false)
const generating = ref(false)
const customPeriods = ref([
  { quarter: 1, label: 'Jan-Apr' },
  { quarter: 2, label: 'May-Aug' },
  { quarter: 3, label: 'Sep-Dec' },
])
const namePattern = ref('P{quarter} {label} {year}')

function addPeriod() {
  const next = customPeriods.value.length + 1
  customPeriods.value.push({ quarter: next, label: '' })
}
function removePeriod(index) {
  customPeriods.value.splice(index, 1)
}

const availableYears = computed(() => {
  const years = new Set([new Date().getFullYear()])
  allEvents.value.forEach((e) => { if (e.year) years.add(Number(e.year)) })
  const sorted = [...years].sort((a, b) => b - a)
  // Ensure at least current year + next 2 years
  for (let i = 0; i < 3; i++) sorted.push(new Date().getFullYear() + i)
  return [...new Set(sorted)].sort((a, b) => b - a)
})

const canCreate = computed(() => ['admin', 'treasurer', 'organizer'].includes(auth.user?.role))

const quarterDefs = [
  { num: 1, label: 'P1 · Jan-Apr' },
  { num: 2, label: 'P2 · May-Aug' },
  { num: 3, label: 'P3 · Sep-Dec' },
]

const defaultPeriods = computed(() => {
  return quarterDefs.map((qd) => {
    const event = allEvents.value.find((e) => Number(e.quarter) === qd.num && Number(e.year) === selectedYear.value)
    return { ...qd, event, totalExpense: event ? Number(event.total_expense || 0) : 0 }
  })
})

const customEvents = computed(() => {
  const standardQuarters = [1, 2, 3]
  return allEvents.value.filter((e) => Number(e.year) === selectedYear.value && !standardQuarters.includes(Number(e.quarter)))
})

const missingQuarters = computed(() => {
  return defaultPeriods.value.filter((q) => !q.event).map((q) => q.num)
})

const netBalance = ref(0)
const totalBudgeted = computed(() => {
  return allEvents.value
    .filter((e) => Number(e.year) === selectedYear.value)
    .reduce((sum, e) => sum + Number(e.budget_allocated || 0), 0)
})
const totalExpensed = computed(() => {
  return allEvents.value
    .filter((e) => Number(e.year) === selectedYear.value)
    .reduce((sum, e) => sum + Number(e.total_expense || 0), 0)
})
const availableForPlanning = computed(() => {
  return netBalance.value - totalBudgeted.value
})

function statusColor(s) {
  return { planned: 'blue', active: 'green', completed: 'default', cancelled: 'red' }[s] || 'default'
}

async function fetchData() {
  loading.value = true
  try {
    const [evtRes, dashRes] = await Promise.all([
      eventsApi.list({ per_page: 50, year: selectedYear.value }),
      reportsApi.dashboard(),
    ])
    allEvents.value = evtRes.data.items || []
    netBalance.value = dashRes.data?.net_balance || 0
  } catch (e) { message.error('Failed to load events') }
  finally { loading.value = false }
}

async function createQuarter(qNum) {
  const qLabels = { 1: 'Jan-Apr', 2: 'May-Aug', 3: 'Sep-Dec' }
  try {
    const res = await store.create({
      name: `P${qNum} ${qLabels[qNum]} ${selectedYear.value}`,
      quarter: qNum,
      year: selectedYear.value,
    })
    message.success(`P${qNum} created`)
    await fetchData()
    if (res?.data?.id) router.push(`/events/${res.data.id}`)
  } catch (e) { message.error(e?.message || 'Failed') }
}

async function addRetiree(event) {
  retireeTarget.value = event
  selectedRetirees.value = []
  try {
    const res = await membersApi.list({ per_page: 1000 })
    memberOptions.value = (res.data?.items || []).map((m) => ({ label: `${m.name} (${m.nic})`, value: m.name }))
  } catch { memberOptions.value = [] }
  retireeModalVisible.value = true
}

async function confirmRetiree() {
  if (selectedRetirees.value.length === 0) { message.error('Select at least one member'); return }
  retireeSaving.value = true
  try {
    const existing = retireeTarget.value?.retiree_name ? retireeTarget.value.retiree_name.split(', ').filter(Boolean) : []
    const all = [...new Set([...existing, ...selectedRetirees.value])]
    await store.update(retireeTarget.value.id, { retiree_name: all.join(', ') })
    message.success('Retiree(s) added')
    retireeModalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { retireeSaving.value = false }
}

function showCreateMissing() {
  missingQuarters.value.forEach((qNum) => createQuarter(qNum))
}

function showGenerateModal() {
  customPeriods.value = [
    { quarter: 1, label: 'Jan-Apr' },
    { quarter: 2, label: 'May-Aug' },
    { quarter: 3, label: 'Sep-Dec' },
  ]
  namePattern.value = 'P{quarter} {label} {year}'
  generateModalVisible.value = true
}

function buildEventName(period) {
  return namePattern.value
    .replace('{quarter}', period.quarter)
    .replace('{label}', period.label)
    .replace('{year}', selectedYear.value)
}

async function confirmGenerate() {
  const invalid = customPeriods.value.find((p) => !p.label.trim())
  if (invalid) { message.error('All periods must have a label'); return }
  generating.value = true
  try {
    const periods = customPeriods.value.map((p) => ({
      quarter: p.quarter,
      label: p.label.trim(),
      name: buildEventName(p),
    }))
    const res = await eventsApi.generateQuarters({
      year: selectedYear.value,
      periods: periods,
      name_pattern: namePattern.value,
    })
    message.success('Events created')
    generateModalVisible.value = false
    await fetchData()
    if (res?.data?.length > 0) router.push(`/events/${res.data[0].id}`)
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { generating.value = false }
}

onMounted(fetchData)
</script>

<style scoped>
.ant-card-hoverable:hover {
  border-color: #1890ff;
}
</style>
