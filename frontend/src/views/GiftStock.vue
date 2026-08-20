<template>
  <div>
    <a-page-header title="Gift Stock" sub-title="Track gifts received into stock and issued">
      <template #extra>
        <a-button type="primary" @click="showModal" v-if="canManage">
          <PlusOutlined /> Add Gift
        </a-button>
      </template>
    </a-page-header>

    <a-card>
      <a-table :dataSource="items" :loading="loading" rowKey="id" :pagination="false">
        <a-table-column title="S/No" width="60">
          <template #default="{ index }">{{ index + 1 }}</template>
        </a-table-column>
        <a-table-column title="Gift Name" dataIndex="name" />
        <a-table-column title="Unit" dataIndex="unit" width="90" />
        <a-table-column title="Unit Price" dataIndex="unit_price" align="right" width="120">
          <template #default="{ record }">Rs. {{ Number(record.unit_price).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Total Received" dataIndex="total_received" align="right" width="110" />
        <a-table-column title="Issued" dataIndex="total_issued" align="right" width="80" />
        <a-table-column title="In Stock" dataIndex="quantity" align="right" width="90">
          <template #default="{ record }">
            <a-tag :color="record.quantity > 0 ? 'green' : 'red'">{{ record.quantity }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Stock Value" align="right" width="120">
          <template #default="{ record }">Rs. {{ Number(record.stock_value || 0).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Actions" width="280">
          <template #default="{ record }">
            <a-space>
              <a-button size="small" type="primary" ghost @click="showMovement(record, 'received')" v-if="canManage">
                <ImportOutlined /> Receive
              </a-button>
              <a-button size="small" @click="showMovement(record, 'issued')" v-if="canManage">
                <ExportOutlined /> Issue
              </a-button>
              <a-button size="small" type="link" @click="showHistory(record)">History</a-button>
              <a-button size="small" type="link" @click="showEditModal(record)" v-if="canManage">Edit</a-button>
              <a-popconfirm title="Deactivate this gift?" @confirm="handleDelete(record)" v-if="canManage">
                <a-button size="small" danger type="link"><DeleteOutlined /></a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <!-- Add/Edit Gift Modal -->
    <a-modal v-model:visible="modalVisible" :title="editingId ? 'Edit Gift' : 'Add Gift'" @ok="handleSubmit" :confirm-loading="submitting" destroyOnClose>
      <a-form :model="form" layout="vertical">
        <a-form-item label="Gift Name" required>
          <a-input v-model:value="form.name" placeholder="e.g. Gold Coin, Wall Clock" />
        </a-form-item>
        <a-form-item label="Unit">
          <a-select v-model:value="form.unit" style="width: 100%">
            <a-select-option value="piece">Piece</a-select-option>
            <a-select-option value="set">Set</a-select-option>
            <a-select-option value="bottle">Bottle</a-select-option>
            <a-select-option value="box">Box</a-select-option>
            <a-select-option value="voucher">Voucher</a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Unit Price (LKR)" required>
          <a-input-number v-model:value="form.unit_price" :min="0" :step="10" style="width: 100%" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Receive/Issue Modal -->
    <a-modal v-model:visible="movementModalVisible" :title="movementTitle" @ok="confirmMovement" :confirm-loading="movementSubmitting" destroyOnClose>
      <a-descriptions :column="1" size="small" style="margin-bottom: 16px">
        <a-descriptions-item label="Gift"><strong>{{ currentGift?.name }}</strong></a-descriptions-item>
        <a-descriptions-item label="Current Stock">{{ currentGift?.quantity }}</a-descriptions-item>
      </a-descriptions>
      <a-form :model="movementForm" layout="vertical">
        <a-form-item :label="movementType === 'received' ? 'Quantity Received' : 'Quantity Issued'" required>
          <a-input-number v-model:value="movementForm.quantity" :min="1" style="width: 100%" />
        </a-form-item>
        <a-form-item label="Unit Price (LKR)" v-if="movementType === 'received'" required>
          <a-input-number v-model:value="movementForm.unit_price" :min="0" :step="10" style="width: 100%" />
        </a-form-item>
        <a-form-item label="Event" v-if="movementType === 'issued'">
          <a-select v-model:value="movementForm.event_id" placeholder="Optional - link to an event" style="width: 100%" allowClear>
            <a-select-option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Notes">
          <a-textarea v-model:value="movementForm.notes" :rows="2" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- History Drawer -->
    <a-drawer :title="`Movement History - ${currentGift?.name || ''}`" placement="right" width="520" :open="historyVisible" @close="historyVisible = false">
      <a-table :dataSource="historyItems" :loading="historyLoading" rowKey="id" size="small" :pagination="historyPagination" @change="handleHistoryChange">
        <a-table-column title="Date" dataIndex="created_at" width="110" />
        <a-table-column title="Type" dataIndex="movement_type" width="80">
          <template #default="{ record }">
            <a-tag :color="record.movement_type === 'received' ? 'green' : 'blue'">
              {{ record.movement_type === 'received' ? 'Received' : 'Issued' }}
            </a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Qty" dataIndex="quantity" align="right" width="60" />
        <a-table-column title="Unit Price" dataIndex="unit_price" align="right" width="90">
          <template #default="{ record }">Rs. {{ Number(record.unit_price).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Total" align="right" width="90">
          <template #default="{ record }">Rs. {{ Number(record.total_value || 0).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Event" dataIndex="event_name" ellipsis>
          <template #default="{ record }">{{ record.event_name || '-' }}</template>
        </a-table-column>
        <a-table-column title="Member" dataIndex="member_name" ellipsis>
          <template #default="{ record }">{{ record.member_name || '-' }}</template>
        </a-table-column>
        <a-table-column title="Actions" width="130" v-if="canManage">
          <template #default="{ record }">
            <a-space>
              <a-button size="small" type="link" @click="showEditMovement(record)">Edit</a-button>
              <a-popconfirm title="Delete this movement? Stock will be adjusted." @confirm="deleteMovement(record)">
                <a-button size="small" danger type="link"><DeleteOutlined /></a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </a-table>
    </a-drawer>

    <!-- Edit Movement Modal -->
    <a-modal v-model:visible="editMovementModalVisible" title="Edit Movement" @ok="confirmEditMovement" :confirm-loading="editMovementSubmitting" ok-text="Save" destroyOnClose>
      <a-form :model="editMovementForm" layout="vertical">
        <a-form-item label="Quantity" required>
          <a-input-number v-model:value="editMovementForm.quantity" :min="1" style="width: 100%" />
        </a-form-item>
        <a-form-item label="Unit Price" required v-if="editMovementForm.movement_type === 'received'">
          <a-input-number v-model:value="editMovementForm.unit_price" :min="0" :step="10" style="width: 100%" />
        </a-form-item>
        <a-form-item label="Notes">
          <a-textarea v-model:value="editMovementForm.notes" :rows="2" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { giftStock as api, events as eventsApi } from '../api/index.js'
import { useAuthStore } from '../stores/auth.js'
import { message } from 'ant-design-vue'

const auth = useAuthStore()
const items = ref([])
const events = ref([])
const loading = ref(false)
const modalVisible = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const form = reactive({ name: '', unit: 'piece', unit_price: 0 })

const canManage = computed(() => ['admin', 'treasurer'].includes(auth.user?.role))

const movementModalVisible = ref(false)
const movementType = ref('received')
const movementSubmitting = ref(false)
const currentGift = ref(null)
const movementForm = reactive({ quantity: 1, unit_price: 0, event_id: null, notes: '' })

const historyVisible = ref(false)
const historyItems = ref([])
const historyLoading = ref(false)
const historyTotal = ref(0)
const historyPage = ref(1)

const editMovementModalVisible = ref(false)
const editMovementSubmitting = ref(false)
const editMovementForm = reactive({ movement_id: null, quantity: 1, unit_price: 0, notes: '', movement_type: 'issued' })

const movementTitle = computed(() =>
  movementType.value === 'received' ? 'Receive Stock' : 'Issue Stock'
)

const historyPagination = computed(() => ({
  current: historyPage.value, total: historyTotal.value, pageSize: 20, showSizeChanger: false,
}))

async function fetchData() {
  loading.value = true
  try {
    const res = await api.list()
    items.value = res.data || []
  } catch { message.error('Failed to load gift stock') }
  finally { loading.value = false }
}

async function fetchEvents() {
  try {
    const res = await eventsApi.list({ per_page: 100 })
    events.value = res.data?.items || []
  } catch {}
}

function showModal() {
  editingId.value = null
  Object.assign(form, { name: '', unit: 'piece', unit_price: 0 })
  modalVisible.value = true
}

function showEditModal(record) {
  editingId.value = record.id
  Object.assign(form, { name: record.name, unit: record.unit || 'piece', unit_price: Number(record.unit_price) })
  modalVisible.value = true
}

async function handleSubmit() {
  if (!form.name.trim()) { message.error('Gift name is required'); return }
  submitting.value = true
  try {
    if (editingId.value) {
      await api.update(editingId.value, form)
      message.success('Gift updated')
    } else {
      await api.create(form)
      message.success('Gift created')
    }
    modalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { submitting.value = false }
}

async function handleDelete(record) {
  try {
    await api.delete(record.id)
    message.success('Gift deactivated')
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
}

function showMovement(record, type) {
  currentGift.value = record
  movementType.value = type
  Object.assign(movementForm, { quantity: 1, unit_price: type === 'received' ? Number(record.unit_price) : 0, event_id: null, notes: '' })
  movementModalVisible.value = true
}

async function confirmMovement() {
  if (!movementForm.quantity || movementForm.quantity < 1) { message.error('Quantity must be at least 1'); return }
  if (movementType.value === 'received' && (movementForm.unit_price === null || movementForm.unit_price < 0)) {
    message.error('Unit price is required'); return
  }
  movementSubmitting.value = true
  try {
    const payload = {
      quantity: movementForm.quantity,
      unit_price: movementType.value === 'received' ? movementForm.unit_price : currentGift.value.unit_price,
      event_id: movementForm.event_id || null,
      notes: movementForm.notes || null,
    }
    if (movementType.value === 'received') {
      await api.receive(currentGift.value.id, payload)
      message.success('Stock received')
    } else {
      await api.issue(currentGift.value.id, payload)
      message.success('Stock issued')
    }
    movementModalVisible.value = false
    await fetchData()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { movementSubmitting.value = false }
}

async function showHistory(record) {
  currentGift.value = record
  historyVisible.value = true
  historyPage.value = 1
  await fetchHistory()
}

async function fetchHistory() {
  if (!currentGift.value) return
  historyLoading.value = true
  try {
    const res = await api.movements(currentGift.value.id, { page: historyPage.value, per_page: 20 })
    historyItems.value = res.data?.items || []
    historyTotal.value = res.data?.total || 0
  } catch { message.error('Failed to load history') }
  finally { historyLoading.value = false }
}

function handleHistoryChange(pag) {
  historyPage.value = pag.current
  fetchHistory()
}

function showEditMovement(record) {
  Object.assign(editMovementForm, {
    movement_id: record.id,
    quantity: record.quantity,
    unit_price: Number(record.unit_price),
    notes: record.notes || '',
    movement_type: record.movement_type,
  })
  editMovementModalVisible.value = true
}

async function confirmEditMovement() {
  if (!editMovementForm.quantity || editMovementForm.quantity < 1) { message.error('Quantity must be at least 1'); return }
  editMovementSubmitting.value = true
  try {
    const payload = { quantity: editMovementForm.quantity, notes: editMovementForm.notes || null }
    if (editMovementForm.movement_type === 'received') payload.unit_price = editMovementForm.unit_price
    await api.updateMovement(editMovementForm.movement_id, payload)
    message.success('Movement updated')
    editMovementModalVisible.value = false
    await fetchData()
    await fetchHistory()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { editMovementSubmitting.value = false }
}

async function deleteMovement(record) {
  try {
    await api.deleteMovement(record.id)
    message.success('Movement deleted')
    await fetchData()
    await fetchHistory()
  } catch (e) { message.error(e?.message || 'Failed') }
}

onMounted(() => { fetchData(); fetchEvents() })
</script>