<template>
  <div>
    <a-page-header title="Transactions" sub-title="Record and manage all income and expenses">
      <template #extra>
        <a-button type="primary" @click="showCreateModal" v-if="canCreate">
          <PlusOutlined /> New Transaction
        </a-button>
      </template>
    </a-page-header>

    <a-card>
      <!-- Filters -->
      <a-row :gutter="[8, 8]" style="margin-bottom: 16px">
        <a-col :xs="12" :sm="6" :md="4">
          <a-select v-model:value="filters.type" placeholder="Type" allow-clear style="width: 100%" @change="fetchData">
            <a-select-option value="">All Types</a-select-option>
            <a-select-option value="income">Income</a-select-option>
            <a-select-option value="expense">Expense</a-select-option>
          </a-select>
        </a-col>
        <a-col :xs="12" :sm="6" :md="4">
          <a-select v-model:value="filters.status" placeholder="Status" allow-clear style="width: 100%" @change="fetchData">
            <a-select-option value="">All Status</a-select-option>
            <a-select-option value="draft">Draft</a-select-option>
            <a-select-option value="pending_approval">Pending</a-select-option>
            <a-select-option value="approved">Approved</a-select-option>
            <a-select-option value="rejected">Rejected</a-select-option>
          </a-select>
        </a-col>
        <a-col :xs="12" :sm="6" :md="4">
          <a-date-picker v-model:value="filters.date_from" placeholder="From" style="width: 100%" @change="fetchData" value-format="YYYY-MM-DD" />
        </a-col>
        <a-col :xs="12" :sm="6" :md="4">
          <a-date-picker v-model:value="filters.date_to" placeholder="To" style="width: 100%" @change="fetchData" value-format="YYYY-MM-DD" />
        </a-col>
        <a-col :xs="24" :sm="12" :md="4">
          <a-input v-model:value="filters.designated_retiree" placeholder="Retiree name" allow-clear @change="fetchData" />
        </a-col>
        <a-col :xs="24" :sm="12" :md="4">
          <a-button @click="resetFilters">Reset</a-button>
        </a-col>
      </a-row>

      <a-table :dataSource="store.items" :loading="store.loading" :pagination="pagination" rowKey="id" @change="handleTableChange" :scroll="{ x: 1000 }">
        <a-table-column title="Date" dataIndex="transaction_date" width="100" fixed="left" />
        <a-table-column title="Type" dataIndex="type" width="80">
          <template #default="{ record }">
            <a-tag :color="record.type === 'income' ? 'green' : 'volcano'">{{ record.type }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Category" dataIndex="category_name" width="120" />
        <a-table-column title="Description" dataIndex="description" ellipsis min-width="150" />
        <a-table-column title="Payee" dataIndex="payee" width="120" />
        <a-table-column title="Amount" dataIndex="amount" width="100" align="right">
          <template #default="{ record }">Rs. {{ Number(record.amount).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Status" dataIndex="status" width="120">
          <template #default="{ record }">
            <a-tag :color="statusColor(record.status)">{{ record.status }}</a-tag>
            <a-button v-if="record.receipt_path" type="link" size="small" @click="viewReceipt(record)">
              <PaperClipOutlined />
            </a-button>
          </template>
        </a-table-column>
        <a-table-column title="Event" dataIndex="event_name" ellipsis width="120" />
        <a-table-column title="Created By" dataIndex="created_by_name" width="120" />
        <a-table-column title="Actions" width="180" fixed="right">
          <template #default="{ record }">
            <a-space>
              <a-button size="small" @click="showEditModal(record)" v-if="canEdit(record)">Edit</a-button>
              <a-button size="small" type="primary" v-if="canApprove && record.status === 'pending_approval'" @click="handleApprove(record)">Approve</a-button>
              <a-button size="small" danger v-if="canApprove && record.status === 'pending_approval'" @click="handleReject(record)">Reject</a-button>
            </a-space>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <!-- Create/Edit Modal -->
    <a-modal v-model:visible="modalVisible" :title="editingId ? 'Edit Transaction' : 'New Transaction'" :width="640" @ok="handleSubmit" :confirm-loading="submitting" destroyOnClose>
      <a-form :model="form" layout="vertical">
        <a-form-item label="Type" required>
          <a-radio-group v-model:value="form.type" button-style="solid">
            <a-radio-button value="income">Income</a-radio-button>
            <a-radio-button value="expense">Expense</a-radio-button>
          </a-radio-group>
        </a-form-item>
        <a-row :gutter="12">
          <a-col :span="12">
            <a-form-item label="Category" required>
              <a-select v-model:value="form.category_id" placeholder="Select category" style="width: 100%" :options="categoryOptions" />
            </a-form-item>
          </a-col>
          <a-col :span="12">
            <a-form-item label="Amount (LKR)" required>
              <a-input-number v-model:value="form.amount" :min="0.01" :step="1" style="width: 100%" />
            </a-form-item>
          </a-col>
        </a-row>
        <a-form-item label="Transaction Date" required>
          <a-date-picker v-model:value="form.transaction_date" style="width: 100%" value-format="YYYY-MM-DD" />
        </a-form-item>
        <a-form-item label="Payee / Payer">
          <a-input v-model:value="form.payee" :placeholder="form.type === 'income' ? 'Who paid?' : 'Vendor name'" />
        </a-form-item>
        <a-form-item label="Description">
          <a-textarea v-model:value="form.description" :rows="2" />
        </a-form-item>
        <a-row :gutter="12">
          <a-col :span="8">
            <a-form-item label="Payment Method">
              <a-select v-model:value="form.payment_method" style="width: 100%">
                <a-select-option value="cash">Cash</a-select-option>
                <a-select-option value="bank_transfer">Bank Transfer</a-select-option>
                <a-select-option value="credit_card">Credit Card</a-select-option>
                <a-select-option value="cheque">Cheque</a-select-option>
                <a-select-option value="upi">UPI</a-select-option>
              </a-select>
            </a-form-item>
          </a-col>
          <a-col :span="8">
            <a-form-item label="Event (optional)">
              <a-select v-model:value="form.event_id" placeholder="Link to event" allow-clear style="width: 100%" :options="eventOptions" :disabled="form.type === 'income'" />
            </a-form-item>
          </a-col>
        </a-row>
        <a-form-item label="Receipt">
          <a-upload :before-upload="beforeUpload" :file-list="fileList" @remove="fileList = []">
            <a-button><UploadOutlined /> Upload Receipt</a-button>
          </a-upload>
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Reject Modal -->
    <a-modal v-model:visible="rejectModalVisible" title="Reject Transaction" @ok="confirmReject" :confirm-loading="rejecting">
      <a-form layout="vertical">
        <a-form-item label="Reason for rejection" required>
          <a-textarea v-model:value="rejectReason" :rows="3" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useTransactionStore } from '../stores/transactions.js'
import { useAuthStore } from '../stores/auth.js'
import { categories as catApi, events as eventApi, uploads as uploadApi } from '../api/index.js'
import { message, Modal } from 'ant-design-vue'

const store = useTransactionStore()
const auth = useAuthStore()
const route = useRoute()
const submitting = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const rejecting = ref(false)
const rejectModalVisible = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')
const fileList = ref([])
const categoriesList = ref([])
const eventsList = ref([])
const filters = reactive({
  type: route.query.status ? '' : (route.query.type || ''),
  status: route.query.status || '',
  date_from: '',
  date_to: '',
  designated_retiree: '',
})

const canCreate = computed(() => ['admin', 'treasurer', 'organizer'].includes(auth.user?.role))
const canApprove = computed(() => auth.canApprove)
const canEdit = computed(() => (record) => {
  if (!['admin', 'treasurer'].includes(auth.user?.role) && record.created_by !== auth.user?.id) return false
  if (record.event_status && ['completed', 'cancelled'].includes(record.event_status)) return false
  return true
})

const form = reactive({
  type: 'expense',
  category_id: null,
  amount: null,
  transaction_date: null,
  payee: '',
  description: '',
  payment_method: 'cash',
  event_id: null,
  designated_retiree: '',
  receipt_path: null,
})

const categoryOptions = computed(() =>
  categoriesList.value
    .filter((c) => c.type === form.type)
    .map((c) => ({ label: c.name, value: c.id }))
)
const eventOptions = computed(() => {
  const active = eventsList.value.filter((e) => e.status === 'active').map((e) => ({ label: e.name, value: e.id }))
  // When editing, include the currently linked event even if not active
  if (editingId.value && form.event_id) {
    const current = eventsList.value.find((e) => e.id === form.event_id)
    if (current && current.status !== 'active') {
      active.push({ label: current.name + ' (inactive)', value: current.id })
    }
  }
  return active
})
const pagination = computed(() => ({
  current: store.page,
  total: store.total,
  pageSize: 20,
  showSizeChanger: false,
}))

function statusColor(s) {
  return { draft: 'default', pending_approval: 'orange', approved: 'green', rejected: 'red' }[s] || 'default'
}

function handleTableChange(pag) {
  store.page = pag.current
  fetchData()
}

async function fetchData() {
  const params = {}
  if (filters.type) params.type = filters.type
  if (filters.status) params.status = filters.status
  if (filters.date_from) params.date_from = filters.date_from
  if (filters.date_to) params.date_to = filters.date_to
  if (filters.designated_retiree) params.designated_retiree = filters.designated_retiree
  await store.fetch(params)
}

function resetFilters() {
  Object.assign(filters, { type: '', status: '', date_from: '', date_to: '', designated_retiree: '' })
  store.page = 1
  fetchData()
}

async function showCreateModal() {
  editingId.value = null
  Object.assign(form, {
    type: 'expense', category_id: null, amount: null, transaction_date: null,
    payee: '', description: '', payment_method: 'cash', event_id: null,
    designated_retiree: '', receipt_path: null,
  })
  fileList.value = []
  await loadFormData()
  modalVisible.value = true
}

async function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, {
    type: record.type, category_id: record.category_id, amount: record.amount,
    transaction_date: record.transaction_date, payee: record.payee || '',
    description: record.description || '', payment_method: record.payment_method,
    event_id: record.event_id, designated_retiree: record.designated_retiree || '',
    receipt_path: record.receipt_path,
  })
  fileList.value = record.receipt_path ? [{ name: 'Receipt', url: record.receipt_path }] : []
  await loadFormData()
  modalVisible.value = true
}

async function loadFormData() {
  try {
    const [catRes, evtRes] = await Promise.all([
      catApi.list(),
      eventApi.list({ per_page: 100 }),
    ])
    categoriesList.value = catRes.data || []
    eventsList.value = evtRes.data?.items || []
  } catch {}
}

function beforeUpload(file) {
  const isAllowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'].includes(file.type)
  if (!isAllowed) { message.error('Only JPG, PNG, GIF, PDF allowed'); return false }
  const isLt5M = file.size / 1024 / 1024 < 5
  if (!isLt5M) { message.error('File must be under 5MB'); return false }
  // Upload immediately
  uploadApi.upload(file).then((res) => {
    form.receipt_path = res.data.url
    fileList.value = [{ name: res.data.original_name, url: res.data.url, status: 'done' }]
    message.success('Receipt uploaded')
  }).catch((e) => {
    message.error('Upload failed')
  })
  return false // Prevent default upload
}

async function handleSubmit() {
  if (!form.category_id || !form.amount || !form.transaction_date) {
    message.error('Please fill required fields')
    return
  }
  submitting.value = true
  try {
    if (editingId.value) {
      await store.update(editingId.value, form)
      message.success('Transaction updated')
    } else {
      await store.create(form)
      message.success('Transaction created')
    }
    modalVisible.value = false
    await fetchData()
  } catch (e) {
    if (e?.data?.duplicate) {
      Modal.confirm({
        title: 'Duplicate Detected',
        content: `This looks similar to transaction #${e.data.duplicate.id} on ${e.data.duplicate.transaction_date}. Add anyway?`,
        onOk: async () => {
          try {
            await store.create({ ...form, confirm_duplicate: true })
            message.success('Transaction created')
            modalVisible.value = false
            await fetchData()
          } catch (err) { message.error(err?.message || 'Failed') }
        },
      })
    } else {
      message.error(e?.message || 'Failed')
    }
  }
  finally { submitting.value = false }
}

function handleApprove(record) {
  Modal.confirm({
    title: 'Approve Transaction',
    content: `Approve Rs. ${Number(record.amount).toFixed(2)} ${record.type} from ${record.payee || record.created_by_name}?`,
    onOk: async () => {
      try {
        await store.approve(record.id)
        message.success('Transaction approved')
        await fetchData()
      } catch (e) { message.error(e?.message || 'Failed') }
    },
  })
}

function handleReject(record) {
  rejectTarget.value = record
  rejectReason.value = ''
  rejectModalVisible.value = true
}

async function confirmReject() {
  if (!rejectReason.value) { message.error('Please provide a reason'); return }
  rejecting.value = true
  try {
    await store.reject(rejectTarget.value.id, rejectReason.value)
    message.success('Transaction rejected')
    rejectModalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { rejecting.value = false }
}

function viewReceipt(record) {
  if (record.receipt_path) window.open(record.receipt_path, '_blank')
}

onMounted(fetchData)
</script>
