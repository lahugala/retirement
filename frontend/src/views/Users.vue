<template>
  <div>
    <a-page-header title="Users" sub-title="Manage users and their roles">
      <template #extra>
        <a-button type="primary" @click="showModal" v-if="isAdmin">
          <PlusOutlined /> Add User
        </a-button>
      </template>
    </a-page-header>

    <a-card>
      <a-table :dataSource="store.items" :loading="store.loading" :pagination="pagination" rowKey="id" @change="handleChange">
        <a-table-column title="Name" dataIndex="name" />
        <a-table-column title="Email" dataIndex="email" />
        <a-table-column title="Role" dataIndex="role">
          <template #default="{ record }">
            <a-tag :color="roleColors[record.role] || 'default'">{{ roleLabels[record.role] || record.role }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Joined" dataIndex="join_date" />
        <a-table-column title="Active" dataIndex="is_active">
          <template #default="{ record }">
            <a-switch :checked="!!record.is_active" disabled />
          </template>
        </a-table-column>
        <a-table-column title="Actions" width="160" v-if="isAdmin">
          <template #default="{ record }">
            <a-space>
              <a-button size="small" @click="showEditModal(record)">Edit</a-button>
              <a-popconfirm title="Deactivate this user?" @confirm="handleDelete(record)">
                <a-button size="small" danger :disabled="record.id === auth.user?.id">Remove</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <a-modal v-model:visible="modalVisible" :title="editingId ? 'Edit User' : 'Add User'" @ok="handleSubmit" :confirm-loading="submitting" destroyOnClose>
      <a-form :model="form" layout="vertical">
        <a-form-item label="Name" required>
          <a-input v-model:value="form.name" />
        </a-form-item>
        <a-form-item label="Email" required>
          <a-input v-model:value="form.email" />
        </a-form-item>
        <a-form-item label="Password" :required="!editingId">
          <a-input-password v-model:value="form.password" :placeholder="editingId ? 'Leave blank to keep current' : 'Password'" />
        </a-form-item>
        <a-form-item label="Role" required>
          <a-select v-model:value="form.role" style="width: 100%">
            <a-select-option v-for="(label, key) in roleLabels" :key="key" :value="key">{{ label }}</a-select-option>
          </a-select>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useUserStore } from '../stores/users.js'
import { useAuthStore } from '../stores/auth.js'
import { message } from 'ant-design-vue'

const store = useUserStore()
const auth = useAuthStore()
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const form = reactive({ name: '', email: '', password: '', role: 'member' })

const isAdmin = computed(() => auth.isAdmin)
const roleLabels = { admin: 'Admin', treasurer: 'Treasurer', organizer: 'Event Organizer', board: 'Board Member', member: 'Member' }
const roleColors = { admin: 'red', treasurer: 'gold', organizer: 'blue', board: 'purple', member: 'green' }

const pagination = computed(() => ({
  current: store.page, total: store.total, pageSize: 20, showSizeChanger: false,
}))

function handleChange(pag) {
  store.page = pag.current
  store.fetch()
}

function showModal() {
  editingId.value = null
  Object.assign(form, { name: '', email: '', password: '', role: 'member' })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, { name: record.name, email: record.email, password: '', role: record.role })
  modalVisible.value = true
}

async function handleSubmit() {
  if (!form.name || !form.email) { message.error('Name and email required'); return }
  if (!editingId && !form.password) { message.error('Password required'); return }
  submitting.value = true
  try {
    if (editingId.value) {
      const payload = { name: form.name, email: form.email, role: form.role }
      if (form.password) payload.password = form.password
      await store.update(editingId.value, payload)
      message.success('User updated')
    } else {
      await store.create({ ...form })
      message.success('User created')
    }
    modalVisible.value = false
    await store.fetch()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { submitting.value = false }
}

async function handleDelete(record) {
  try {
    await store.delete(record.id)
    message.success('User deactivated')
    await store.fetch()
  } catch (e) { message.error(e?.message || 'Failed') }
}

onMounted(() => store.fetch())
</script>
