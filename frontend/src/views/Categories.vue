<template>
  <div>
    <a-page-header title="Categories" sub-title="Manage income and expense categories">
      <template #extra>
        <a-button type="primary" @click="showModal">
          <PlusOutlined /> Add Category
        </a-button>
      </template>
    </a-page-header>

    <a-card>
      <a-table :dataSource="categories" :loading="loading" rowKey="id" :pagination="false">
        <a-table-column title="Name" dataIndex="name" />
        <a-table-column title="Type" dataIndex="type" width="100">
          <template #default="{ record }">
            <a-tag :color="record.type === 'income' ? 'green' : 'volcano'">{{ record.type }}</a-tag>
          </template>
        </a-table-column>
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

    <a-modal v-model:visible="modalVisible" :title="editingId ? 'Edit Category' : 'Add Category'" @ok="handleSubmit" :confirm-loading="submitting" destroyOnClose>
      <a-form :model="form" layout="vertical">
        <a-form-item label="Name" required>
          <a-input v-model:value="form.name" />
        </a-form-item>
        <a-form-item label="Type" required>
          <a-radio-group v-model:value="form.type" button-style="solid">
            <a-radio-button value="income">Income</a-radio-button>
            <a-radio-button value="expense">Expense</a-radio-button>
          </a-radio-group>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { categories as catApi } from '../api/index.js'
import { message } from 'ant-design-vue'

const categories = ref([])
const loading = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const form = reactive({ name: '', type: 'expense' })

async function fetchData() {
  loading.value = true
  try {
    const res = await catApi.list()
    categories.value = res.data || []
  } catch {} finally { loading.value = false }
}

function showModal() {
  editingId.value = null
  Object.assign(form, { name: '', type: 'expense' })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, { name: record.name, type: record.type })
  modalVisible.value = true
}

async function handleSubmit() {
  if (!form.name) { message.error('Name is required'); return }
  submitting.value = true
  try {
    if (editingId.value) {
      await catApi.update(editingId.value, form)
      message.success('Category updated')
    } else {
      await catApi.create(form)
      message.success('Category created')
    }
    modalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { submitting.value = false }
}

async function handleDelete(record) {
  try {
    await catApi.delete(record.id)
    message.success('Category deactivated')
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
}

onMounted(fetchData)
</script>
