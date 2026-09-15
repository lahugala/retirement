<template>
  <div>
    <a-page-header title="Settings" sub-title="Configure system-wide workflow and permissions" />

    <a-spin :spinning="loading">
      <a-row :gutter="[24, 24]">
        <!-- Transaction Workflow -->
        <a-col :xs="24" :lg="12">
          <a-card title="Transaction Status Workflow" :bordered="false">
            <a-table :dataSource="workflowSettings" rowKey="key" size="small" :pagination="false">
              <a-table-column title="Role" dataIndex="role" width="120">
                <template #default="{ record }">
                  <a-tag :color="roleColor(record.role)">{{ record.role }}</a-tag>
                </template>
              </a-table-column>
              <a-table-column title="Default Status on Create" dataIndex="key">
                <template #default="{ record }">
                  <a-select v-model:value="form[record.key]" style="width: 140px" size="small">
                    <a-select-option value="draft">Draft</a-select-option>
                    <a-select-option value="approved">Approved</a-select-option>
                  </a-select>
                </template>
              </a-table-column>
            </a-table>
          </a-card>
        </a-col>

        <!-- Role Permissions -->
        <a-col :xs="24" :lg="12">
          <a-card title="Role Permissions" :bordered="false">
            <a-table :dataSource="permissionSettings" rowKey="key" size="small" :pagination="false">
              <a-table-column title="Permission" dataIndex="description" />
              <a-table-column title="Roles" key="roles" width="260">
                <template #default="{ record }">
                  <a-select
                    v-model:value="form[record.key]"
                    mode="multiple"
                    style="width: 100%"
                    size="small"
                    :options="allRoleOptions"
                  />
                </template>
              </a-table-column>
            </a-table>
          </a-card>
        </a-col>

        <!-- Approval Settings -->
        <a-col :xs="24" :lg="12">
          <a-card title="Approval Settings" :bordered="false">
            <a-form layout="vertical">
              <a-form-item label="Auto-approve threshold (Rs.)">
                <a-input-number
                  v-model:value="form.txn_auto_approve_threshold"
                  :min="0"
                  :step="10000"
                  style="width: 100%"
                />
                <div style="color: #999; font-size: 12px">Expenses below this amount are auto-approved for organizer</div>
              </a-form-item>
              <a-form-item label="Require approval for expenses above threshold">
                <a-switch v-model:checked="form.txn_require_approval" />
              </a-form-item>
            </a-form>
          </a-card>
        </a-col>

        <!-- Preview -->
        <a-col :xs="24" :lg="12">
          <a-card title="Workflow Preview" :bordered="false">
            <div v-for="role in roles" :key="role" style="margin-bottom: 12px">
              <a-tag :color="roleColor(role)" style="margin-bottom: 4px">{{ role }}</a-tag>
              <div style="padding-left: 8px; font-size: 13px">
                <div>Create: default status → <a-tag size="small" :color="statusColor(form[`txn_${role}_default_status`])">{{ form[`txn_${role}_default_status`] }}</a-tag></div>
                <div>Edit: {{ canPerRole(role, 'txn_edit_roles') ? 'Yes' : 'No' }}</div>
                <div>Submit: {{ canPerRole(role, 'txn_submit_roles') ? 'Yes' : 'No' }}</div>
                <div>Approve: {{ canPerRole(role, 'txn_approve_roles') ? 'Yes' : 'No' }}</div>
                <div>Delete: {{ canPerRole(role, 'txn_delete_roles') ? 'Yes' : 'No' }}</div>
              </div>
            </div>
          </a-card>
        </a-col>
      </a-row>

      <div style="margin-top: 24px; text-align: right">
        <a-button type="primary" @click="saveSettings" :loading="saving">
          <SaveOutlined /> Save Settings
        </a-button>
      </div>
    </a-spin>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { settings as settingsApi } from '../api/index.js'
import { message } from 'ant-design-vue'

const loading = ref(true)
const saving = ref(false)

const roles = ['admin', 'treasurer', 'organizer', 'board', 'member']
const allRoleOptions = roles.map((r) => ({ label: r, value: r }))

const form = reactive({
  txn_create_roles: [],
  txn_edit_roles: [],
  txn_submit_roles: [],
  txn_approve_roles: [],
  txn_delete_roles: [],
  txn_organizer_default_status: 'draft',
  txn_treasurer_default_status: 'approved',
  txn_admin_default_status: 'approved',
  txn_auto_approve_threshold: 50000,
  txn_require_approval: true,
})

const workflowSettings = [
  { key: 'txn_admin_default_status', role: 'admin' },
  { key: 'txn_treasurer_default_status', role: 'treasurer' },
  { key: 'txn_organizer_default_status', role: 'organizer' },
]

const permissionSettings = [
  { key: 'txn_create_roles', description: 'Can create transactions' },
  { key: 'txn_edit_roles', description: 'Can edit transactions' },
  { key: 'txn_submit_roles', description: 'Can submit for approval' },
  { key: 'txn_approve_roles', description: 'Can approve / reject' },
  { key: 'txn_delete_roles', description: 'Can delete transactions' },
]

function roleColor(role) {
  return { admin: 'red', treasurer: 'gold', organizer: 'blue', board: 'purple', member: 'green' }[role] || 'default'
}

function statusColor(s) {
  return { draft: 'default', pending_approval: 'orange', approved: 'green', rejected: 'red' }[s] || 'default'
}

function canPerRole(role, key) {
  return (form[key] || []).includes(role)
}

async function fetchSettings() {
  loading.value = true
  try {
    const res = await settingsApi.list()
    const data = res.data?.data || {}
    for (const [key, val] of Object.entries(data)) {
      if (key in form) {
        form[key] = val.value
      }
    }
  } catch (e) { message.error('Failed to load settings') }
  finally { loading.value = false }
}

async function saveSettings() {
  saving.value = true
  try {
    const payload = {}
    for (const [key, val] of Object.entries(form)) {
      payload[key] = val
    }
    await settingsApi.update({ settings: payload })
    message.success('Settings saved')
  } catch (e) { message.error(e?.message || 'Failed to save') }
  finally { saving.value = false }
}

onMounted(fetchSettings)
</script>
