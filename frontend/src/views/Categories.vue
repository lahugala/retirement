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
        <a-table-column title="Linked Account" width="160">
          <template #default="{ record }">
            <template v-if="record.account_code">{{ record.account_code }} {{ record.account_name }}</template>
            <a-tag v-else color="red">No account</a-tag>
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
        <a-form-item label="Linked Account" required>
          <a-select v-model:value="form.default_account_id" placeholder="Select account" style="width: 100%" :options="accountOptions" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { categories as catApi, accounts as accApi } from '../api/index.js'
import { message } from 'ant-design-vue'

const categories = ref([])
const accountsList = ref([])
const loading = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const form = reactive({ name: '', type: 'expense', default_account_id: null })

const accountOptions = computed(() =>
  accountsList.value
    .filter((a) => a.type === form.type)
    .map((a) => ({ label: `${a.code} ${a.name}`, value: a.id }))
)

async function fetchData() {
  loading.value = true
  try {
    const res = await catApi.list()
    categories.value = res.data || []
  } catch {} finally { loading.value = false }
}

async function fetchAccounts() {
  try {
    const res = await accApi.list()
    accountsList.value = res.data || []
  } catch {}
}

function showModal() {
  editingId.value = null
  Object.assign(form, { name: '', type: 'expense', default_account_id: null })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, { name: record.name, type: record.type, default_account_id: record.default_account_id || null })
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

onMounted(() => {
  fetchData()
  fetchAccounts()
})
</script>
