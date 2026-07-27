<template>
  <div>
    <a-page-header title="Audit Log" sub-title="Immutable record of all system changes" />

    <a-card>
      <a-row :gutter="[12, 12]" style="margin-bottom: 16px">
        <a-col :xs="12" :sm="6">
          <a-select v-model:value="filters.entity_type" placeholder="Entity" allow-clear style="width: 100%" @change="fetchData">
            <a-select-option value="">All Entities</a-select-option>
            <a-select-option value="transactions">Transactions</a-select-option>
            <a-select-option value="events">Events</a-select-option>
            <a-select-option value="budgets">Budgets</a-select-option>
            <a-select-option value="members">Members</a-select-option>
            <a-select-option value="categories">Categories</a-select-option>
          </a-select>
        </a-col>
        <a-col :xs="12" :sm="6">
          <a-select v-model:value="filters.action" placeholder="Action" allow-clear style="width: 100%" @change="fetchData">
            <a-select-option value="">All Actions</a-select-option>
            <a-select-option value="create">Create</a-select-option>
            <a-select-option value="update">Update</a-select-option>
            <a-select-option value="approve">Approve</a-select-option>
            <a-select-option value="reject">Reject</a-select-option>
            <a-select-option value="delete">Delete</a-select-option>
            <a-select-option value="soft_delete">Soft Delete</a-select-option>
          </a-select>
        </a-col>
        <a-col :xs="12" :sm="6">
          <a-input v-model:value="filters.entity_id" placeholder="Entity ID" allow-clear @change="fetchData" />
        </a-col>
        <a-col :xs="12" :sm="6">
          <a-button @click="resetFilters">Reset</a-button>
        </a-col>
      </a-row>

      <a-table :dataSource="items" :loading="loading" :pagination="pagination" rowKey="id" @change="handleChange" :scroll="{ x: 1000 }">
        <a-table-column title="Timestamp" dataIndex="timestamp" width="160" fixed="left" />
        <a-table-column title="Entity" dataIndex="entity_type" width="100" />
        <a-table-column title="Action" dataIndex="action" width="100">
          <template #default="{ record }">
            <a-tag :color="actionColor(record.action)">{{ record.action }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Changed By" dataIndex="changed_by_name" width="120" />
        <a-table-column title="Old Values" ellipsis min-width="200">
          <template #default="{ record }">
            <a-typography-paragraph :copyable="!!record.old_values" :ellipsis="{ rows: 2, expandable: true }" style="margin: 0">
              {{ record.old_values || '-' }}
            </a-typography-paragraph>
          </template>
        </a-table-column>
        <a-table-column title="New Values" ellipsis min-width="200">
          <template #default="{ record }">
            <a-typography-paragraph :copyable="!!record.new_values" :ellipsis="{ rows: 2, expandable: true }" style="margin: 0">
              {{ record.new_values || '-' }}
            </a-typography-paragraph>
          </template>
        </a-table-column>
      </a-table>
    </a-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { auditLogs as auditApi } from '../api/index.js'

const items = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const filters = reactive({ entity_type: '', action: '', entity_id: '' })

const pagination = computed(() => ({
  current: page.value, total: total.value, pageSize: 50, showSizeChanger: false,
}))

function actionColor(action) {
  const colors = { create: 'green', update: 'blue', approve: 'cyan', reject: 'red', delete: 'red', soft_delete: 'orange' }
  return colors[action] || 'default'
}

function handleChange(pag) {
  page.value = pag.current
  fetchData()
}

function resetFilters() {
  Object.assign(filters, { entity_type: '', action: '', entity_id: '' })
  page.value = 1
  fetchData()
}

async function fetchData() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: 50 }
    if (filters.entity_type) params.entity_type = filters.entity_type
    if (filters.action) params.action = filters.action
    if (filters.entity_id) params.entity_id = filters.entity_id
    const res = await auditApi.list(params)
    items.value = res.data.items
    total.value = res.data.total
    page.value = res.data.page
  } catch {}
  finally { loading.value = false }
}

onMounted(fetchData)
</script>
