<template>
  <div>
    <a-page-header title="Members" sub-title="Society members directory">
      <template #extra>
        <a-space>
          <a-button @click="handleSyncAll" :loading="syncingAll" v-if="isAdmin">
            <SyncOutlined /> Sync All
          </a-button>
          <a-button @click="handleExportExcel" :loading="exporting">
            <DownloadOutlined /> Export Excel
          </a-button>
          <a-button type="primary" @click="showModal" v-if="canEdit">
            <PlusOutlined /> Add Member
          </a-button>
        </a-space>
      </template>
    </a-page-header>

    <a-card>
      <div style="margin-bottom: 16px; display: flex; gap: 12px; flex-wrap: wrap">
        <a-input-search v-model:value="search" placeholder="Search by name, NIC, service no, computer no" style="max-width: 400px" @search="handleSearch" allowClear />
        <a-select v-model:value="statusFilter" placeholder="Filter by status" style="width: 180px" allowClear @change="handleStatusFilter">
          <a-select-option value="active">Active</a-select-option>
          <a-select-option value="retired">Retired</a-select-option>
          <a-select-option value="deceased">Deceased</a-select-option>
          <a-select-option value="resigned">Resigned</a-select-option>
          <a-select-option value="inactive">Inactive</a-select-option>
          <a-select-option value="dismissed">Dismissed</a-select-option>
        </a-select>
      </div>
      <a-table :dataSource="items" :loading="loading" :pagination="pagination" rowKey="id" @change="handleChange">
                <a-table-column title="S/No" width="60">
          <template #default="{ index }">{{ (page - 1) * 20 + index + 1 }}</template>
        </a-table-column>
        <a-table-column title="Name" dataIndex="name" />
        <a-table-column title="NIC" dataIndex="nic" />
        <a-table-column title="Service No" dataIndex="service_no" />
        <a-table-column title="Designation" dataIndex="designation" ellipsis />
        <a-table-column title="Computer No" dataIndex="computer_no" />
        <a-table-column title="Retirement Date" dataIndex="retirement_date" />
        <a-table-column title="Status" dataIndex="status">
          <template #default="{ record }">
            <a-tag :color="{ active: 'green', retired: 'orange', deceased: 'red', resigned: 'purple', inactive: 'default', dismissed: 'red' }[record.status] || 'default'">{{ record.status }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Actions" width="120">
          <template #default="{ record }">
            <a-space>
              <a-tooltip title="View Profile">
                <a-button size="small" @click="showProfile(record)">
                  <template #icon><EyeOutlined /></template>
                </a-button>
              </a-tooltip>
              <a-tooltip v-if="canEdit" title="Edit">
                <a-button size="small" @click="showEditModal(record)">
                  <template #icon><EditOutlined /></template>
                </a-button>
              </a-tooltip>
              <a-tooltip v-if="isAdmin" title="Sync">
                <a-button size="small" type="primary" ghost :loading="syncingId === record.id" @click="handleSync(record)" :disabled="!record.computer_no">
                  <template #icon><SyncOutlined /></template>
                </a-button>
              </a-tooltip>
            </a-space>
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
        <a-form-item label="Designation">
          <a-input v-model:value="form.designation" />
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
            <a-select-option value="resigned">Resigned</a-select-option>
            <a-select-option value="inactive">Inactive</a-select-option>
            <a-select-option value="dismissed">Dismissed</a-select-option>
          </a-select>
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Member Profile Card -->
    <a-modal v-model:visible="profileVisible" :title="null" :footer="null" width="420px" destroyOnClose :body-style="{ padding: 0 }" :mask-closable="true">
      <div v-if="profileMember">
        <!-- Header with avatar -->
        <div style="background: linear-gradient(135deg, #1890ff 0%, #096dd9 50%, #0050b3 100%); padding: 40px 24px 28px; position: relative; text-align: center; overflow: hidden">
          <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.06); border-radius: 50%"></div>
          <div style="position: absolute; bottom: -20px; left: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.04); border-radius: 50%"></div>
          <div style="position: relative; zIndex: 1; display: inline-block; cursor: pointer" @click="showFullImage">
            <a-avatar :size="140" :src="profileImage" shape="circle" :style="{ backgroundColor: '#e6f7ff', border: '4px solid rgba(255,255,255,0.8)', boxShadow: '0 6px 20px rgba(0,0,0,0.2)' }">
              <template #icon><UserOutlined style="font-size: 56px; color: #91d5ff" /></template>
            </a-avatar>
            <div v-if="profileImage" style="position: absolute; bottom: 4px; right: 4px; width: 32px; height: 32px; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center">
              <ZoomInOutlined style="color: #fff; font-size: 14px" />
            </div>
          </div>
          <h2 style="color: #fff; margin: 14px 0 4px; font-size: 18px; font-weight: 600; position: relative; zIndex: 1; text-shadow: 0 1px 3px rgba(0,0,0,0.15)">{{ profileMember.name }}</h2>
          <div style="display: flex; justify-content: center; gap: 8px; position: relative; zIndex: 1">
            <a-tag :color="{ active: 'green', retired: 'orange', deceased: 'red', resigned: 'purple', inactive: 'default', dismissed: 'red' }[profileMember.status] || 'default'" style="margin: 4px 0 0; font-size: 12px; padding: 1px 14px; border-radius: 10px; text-transform: capitalize">
              {{ profileMember.status }}
            </a-tag>
            <a-tag v-if="profileMember.computer_no" color="blue" style="margin: 4px 0 0; font-size: 12px; padding: 1px 14px; border-radius: 10px">
              #{{ profileMember.computer_no }}
            </a-tag>
          </div>
        </div>

        <!-- Details -->
        <div style="padding: 20px 24px">
          <div style="display: grid; gap: 12px">
            <div style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fafafa; border-radius: 8px; border: 1px solid #f0f0f0">
              <div style="width: 36px; height: 36px; border-radius: 8px; background: #e6f7ff; display: flex; align-items: center; justify-content: center; flex-shrink: 0">
                <IdcardOutlined style="color: #1890ff; font-size: 16px" />
              </div>
              <div style="min-width: 0">
                <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px">NIC Number</div>
                <div style="font-size: 14px; color: #262626; font-weight: 500">{{ profileMember.nic || '-' }}</div>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fafafa; border-radius: 8px; border: 1px solid #f0f0f0">
              <div style="width: 36px; height: 36px; border-radius: 8px; background: #f6ffed; display: flex; align-items: center; justify-content: center; flex-shrink: 0">
                <NumberOutlined style="color: #52c41a; font-size: 16px" />
              </div>
              <div style="min-width: 0">
                <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px">Service Number</div>
                <div style="font-size: 14px; color: #262626; font-weight: 500">{{ profileMember.service_no || '-' }}</div>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fafafa; border-radius: 8px; border: 1px solid #f0f0f0">
              <div style="width: 36px; height: 36px; border-radius: 8px; background: #fff7e6; display: flex; align-items: center; justify-content: center; flex-shrink: 0">
                <ProfileOutlined style="color: #fa8c16; font-size: 16px" />
              </div>
              <div style="min-width: 0">
                <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px">Designation</div>
                <div style="font-size: 14px; color: #262626; font-weight: 500">{{ profileMember.designation || '-' }}</div>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fafafa; border-radius: 8px; border: 1px solid #f0f0f0">
              <div style="width: 36px; height: 36px; border-radius: 8px; background: #f9f0ff; display: flex; align-items: center; justify-content: center; flex-shrink: 0">
                <CalendarOutlined style="color: #722ed1; font-size: 16px" />
              </div>
              <div style="min-width: 0">
                <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px">Retirement Date</div>
                <div style="font-size: 14px; color: #262626; font-weight: 500">{{ profileMember.retirement_date || '-' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </a-modal>

    <!-- Full Image Preview -->
    <a-modal v-model:visible="imagePreviewVisible" :title="profileMember?.name" :footer="null" :width="'50vw'" :body-style="{ padding: 0, textAlign: 'center', background: '#000' }" :mask-closable="true" centered destroyOnClose>
      <img :src="profileImage" :style="{ maxWidth: '100%', maxHeight: '70vh', objectFit: 'contain' }" />
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { members as api } from '../api/index.js'
import { useAuthStore } from '../stores/auth.js'
import { message, Modal } from 'ant-design-vue'
import dayjs from 'dayjs'
import * as XLSX from 'xlsx'

const auth = useAuthStore()
const items = ref([])
const total = ref(0)
const page = ref(1)
const search = ref('')
const statusFilter = ref(undefined)
const loading = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const syncingId = ref(null)
const syncingAll = ref(false)
const exporting = ref(false)

const profileVisible = ref(false)
const profileMember = ref(null)
const profileImage = computed(() => {
  if (!profileMember.value?.computer_no) return ''
  return `/retirement/backend/uploads/member-images/${profileMember.value.computer_no}.jpg`
})

const imagePreviewVisible = ref(false)

function showFullImage() {
  if (profileImage.value) {
    imagePreviewVisible.value = true
  }
}

const form = reactive({
  name: '', nic: '', service_no: '', designation: '', computer_no: '',
  retirement_date: null, status: 'active',
})

const canEdit = computed(() => ['admin', 'treasurer'].includes(auth.user?.role))
const isAdmin = computed(() => auth.user?.role === 'admin')

const pagination = computed(() => ({
  current: page.value, total: total.value, pageSize: 20, showSizeChanger: false,
}))

function handleChange(pag) {
  page.value = pag.current
  fetchData()
}

function handleSearch() {
  page.value = 1
  fetchData()
}

function handleStatusFilter() {
  page.value = 1
  fetchData()
}

function showProfile(record) {
  profileMember.value = record
  profileVisible.value = true
}

function showModal() {
  editingId.value = null
  Object.assign(form, { name: '', nic: '', service_no: '', designation: '', computer_no: '', retirement_date: null, status: 'active' })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, {
    name: record.name,
    nic: record.nic || '',
    service_no: record.service_no || '',
    designation: record.designation || '',
    computer_no: record.computer_no || '',
    retirement_date: record.retirement_date ? dayjs(record.retirement_date) : null,
    status: record.status,
  })
  modalVisible.value = true
}

async function handleSync(record) {
  syncingId.value = record.id
  try {
    const res = await api.syncRetirement(record.id)
    message.success(res?.message || 'Member data synced')
    await fetchData()
  } catch (e) { message.error(e?.message || 'Sync failed') }
  finally { syncingId.value = null }
}

async function handleSyncAll() {
  Modal.confirm({
    title: 'Sync All Members',
    content: 'This will fetch retirement dates from the external system for every member with a computer number. This may take a while. Continue?',
    okText: 'Sync All',
    cancelText: 'Cancel',
    onOk: async () => {
      syncingAll.value = true
      try {
        const res = await api.syncAll()
        const d = res.data || {}
        message.success(`Sync complete: ${d.synced} updated, ${d.failed} failed, ${d.no_data} no data`)
        if (d.errors && d.errors.length > 0) {
          Modal.warning({
            title: 'Some members could not be synced',
            content: d.errors.map((e) => `${e.name} (${e.computer_no}): ${e.error}`).join('\n'),
            width: 500,
          })
        }
        await fetchData()
      } catch (e) { message.error(e?.message || 'Bulk sync failed') }
      finally { syncingAll.value = false }
    },
  })
}

async function handleExportExcel() {
  exporting.value = true
  try {
    const res = await api.list({ page: 1, per_page: 1000 })
    const rows = res.data?.items || []
    if (rows.length === 0) { message.warning('No members to export'); return }
    const data = rows.map((m) => ({
      Name: m.name,
      'NIC Number': m.nic || '',
      'Service Number': m.service_no || '',
      'Designation': m.designation || '',
      'Computer Number': m.computer_no || '',
      'Retirement Date': m.retirement_date || '',
      Status: m.status || 'active',
    }))
    const ws = XLSX.utils.json_to_sheet(data)
    ws['!cols'] = [{ wch: 45 }, { wch: 16 }, { wch: 16 }, { wch: 20 }, { wch: 16 }, { wch: 18 }, { wch: 10 }]
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, 'Members')
    XLSX.writeFile(wb, `members-${dayjs().format('YYYY-MM-DD')}.xlsx`)
    message.success(`Exported ${rows.length} members`)
  } catch { message.error('Export failed') }
  finally { exporting.value = false }
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
      designation: form.designation || null,
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
    const res = await api.list({ page: page.value, search: search.value || undefined, status: statusFilter.value || undefined })
    items.value = res.data?.items || []
    total.value = res.data?.total || 0
  } catch { message.error('Failed to load members') }
  finally { loading.value = false }
}

onMounted(fetchData)
</script>
