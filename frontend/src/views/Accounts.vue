<template>
  <div>
    <a-page-header title="Accounts" sub-title="Chart of Accounts — manage debit/credit accounts">
      <template #extra>
        <a-button type="primary" @click="showModal">
          <PlusOutlined /> Add Account
        </a-button>
      </template>
    </a-page-header>

    <a-card>
      <a-table :dataSource="accounts" :loading="loading" rowKey="id" :pagination="false">
        <a-table-column title="Code" dataIndex="code" width="90" />
        <a-table-column title="Name" dataIndex="name" ellipsis />
        <a-table-column title="Type" dataIndex="type" width="110">
          <template #default="{ record }">
            <a-tag :color="{ asset: 'blue', liability: 'orange', equity: 'green', income: 'cyan', expense: 'volcano' }[record.type] || 'default'">{{ record.type }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Description" dataIndex="description" ellipsis />
        <a-table-column title="Active" dataIndex="is_active" width="80">
          <template #default="{ record }">
            <a-switch :checked="!!record.is_active" disabled />
          </template>
        </a-table-column>
        <a-table-column title="Actions" width="160">
          <template #default="{ record }">
            <a-space>
              <a-button size="small" @click="showEditModal(record)">Edit</a-button>
              <a-popconfirm title="Deactivate?" @confirm="handleDelete(record)">
                <a-button size="small" danger>Remove</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <a-modal v-model:visible="modalVisible" :title="editingId ? 'Edit Account' : 'Add Account'" @ok="handleSubmit" :confirm-loading="submitting" destroyOnClose>
      <a-form :model="form" layout="vertical">
        <a-row :gutter="12">
          <a-col :span="10">
            <a-form-item label="Code" required>
              <a-input v-model:value="form.code" placeholder="e.g. 1003" />
            </a-form-item>
          </a-col>
          <a-col :span="14">
            <a-form-item label="Name" required>
              <a-input v-model:value="form.name" />
            </a-form-item>
          </a-col>
        </a-row>
        <a-form-item label="Type" required>
          <a-select v-model:value="form.type" style="width: 100%">
            <a-select-option value="asset">Asset</a-select-option>
            <a-select-option value="liability">Liability</a-select-option>
            <a-select-option value="equity">Equity</a-select-option>
            <a-select-option value="income">Income</a-select-option>
            <a-select-option value="expense">Expense</a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Description">
          <a-textarea v-model:value="form.description" :rows="2" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { accounts as accApi } from '../api/index.js'
import { message } from 'ant-design-vue'

const accounts = ref([])
const loading = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const form = reactive({ code: '', name: '', type: 'asset', description: '' })

async function fetchData() {
  loading.value = true
  try {
    const res = await accApi.list()
    accounts.value = res.data || []
  } catch {} finally { loading.value = false }
}

function showModal() {
  editingId.value = null
  Object.assign(form, { code: '', name: '', type: 'asset', description: '' })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, { code: record.code, name: record.name, type: record.type, description: record.description || '' })
  modalVisible.value = true
}

async function handleSubmit() {
  if (!form.code || !form.name) { message.error('Code and Name are required'); return }
  submitting.value = true
  try {
    if (editingId.value) {
      await accApi.update(editingId.value, form)
      message.success('Account updated')
    } else {
      await accApi.create(form)
      message.success('Account created')
    }
    modalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { submitting.value = false }
}

async function handleDelete(record) {
  try {
    await accApi.delete(record.id)
    message.success('Account deactivated')
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
}

onMounted(fetchData)
</script>
