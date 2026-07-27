<template>
  <div>
    <a-page-header title="Members" sub-title="Society members directory">
      <template #extra>
        <a-button type="primary" @click="showModal" v-if="canEdit">
          <PlusOutlined /> Add Member
        </a-button>
      </template>
    </a-page-header>

    <a-card>
      <a-table :dataSource="items" :loading="loading" :pagination="pagination" rowKey="id" @change="handleChange">
        <a-table-column title="Name" dataIndex="name" />
        <a-table-column title="NIC" dataIndex="nic" />
        <a-table-column title="Service No" dataIndex="service_no" />
        <a-table-column title="Computer No" dataIndex="computer_no" />
        <a-table-column title="Retirement Date" dataIndex="retirement_date" />
        <a-table-column title="Status" dataIndex="status">
          <template #default="{ record }">
            <a-tag :color="{ active: 'green', retired: 'orange', deceased: 'red' }[record.status] || 'default'">{{ record.status }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Actions" width="120" v-if="canEdit">
          <template #default="{ record }">
            <a-button size="small" @click="showEditModal(record)">Edit</a-button>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <a-modal v-model:visible="modalVisible" :title="editingId ? 'Edit Member' : 'Add Member'" @ok="handleSubmit" :confirm-loading="submitting" destroyOnClose>
      <a-form :model="form" layout="vertical">
        <a-form-item label="Name" required>
          <a-input v-model:value="form.name" />
        </a-form-item>
        <a-form-item label="NIC" required>
          <a-input v-model:value="form.nic" />
        </a-form-item>
        <a-form-item label="Service Number">
          <a-input v-model:value="form.service_no" />
        </a-form-item>
        <a-form-item label="Computer Number" required>
          <a-input v-model:value="form.computer_no" />
        </a-form-item>
        <a-form-item label="Retirement Date">
          <a-date-picker v-model:value="form.retirement_date" style="width: 100%" />
        </a-form-item>
        <a-form-item label="Status">
          <a-select v-model:value="form.status" style="width: 100%">
            <a-select-option value="active">Active</a-select-option>
            <a-select-option value="retired">Retired</a-select-option>
            <a-select-option value="deceased">Deceased</a-select-option>
          </a-select>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { members as api } from '../api/index.js'
import { useAuthStore } from '../stores/auth.js'
import { message } from 'ant-design-vue'
import dayjs from 'dayjs'

const auth = useAuthStore()
const items = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)

const form = reactive({
  name: '', nic: '', service_no: '', computer_no: '',
  retirement_date: null, status: 'active',
})

const canEdit = computed(() => ['admin', 'treasurer'].includes(auth.user?.role))

const pagination = computed(() => ({
  current: page.value, total: total.value, pageSize: 20, showSizeChanger: false,
}))

function handleChange(pag) {
  page.value = pag.current
  fetchData()
}

function showModal() {
  editingId.value = null
  Object.assign(form, { name: '', nic: '', service_no: '', computer_no: '', retirement_date: null, status: 'active' })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, {
    name: record.name,
    nic: record.nic || '',
    service_no: record.service_no || '',
    computer_no: record.computer_no || '',
    retirement_date: record.retirement_date ? dayjs(record.retirement_date) : null,
    status: record.status,
  })
  modalVisible.value = true
}

async function handleSubmit() {
  if (!form.name.trim()) { message.error('Name required'); return }
  if (!form.nic.trim()) { message.error('NIC required'); return }
  if (!form.computer_no.trim()) { message.error('Computer number required'); return }
  submitting.value = true
  try {
    const payload = {
      name: form.name.trim(),
      nic: form.nic || null,
      service_no: form.service_no || null,
      computer_no: form.computer_no || null,
      retirement_date: form.retirement_date ? dayjs(form.retirement_date).format('YYYY-MM-DD') : null,
      status: form.status,
    }
    if (editingId.value) {
      await api.update(editingId.value, payload)
      message.success('Member updated')
    } else {
      await api.create(payload)
      message.success('Member created')
    }
    modalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { submitting.value = false }
}

async function fetchData() {
  loading.value = true
  try {
    const res = await api.list({ page: page.value })
    items.value = res.data?.items || []
    total.value = res.data?.total || 0
  } catch { message.error('Failed to load members') }
  finally { loading.value = false }
}

onMounted(fetchData)
</script>
