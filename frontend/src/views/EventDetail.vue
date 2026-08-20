<template>
  <div>
    <a-page-header :title="event?.name || 'Event Detail'" sub-title="View and manage event details" @back="$router.back()">
      <template #extra>
        <a-space>
          <a-button @click="showActivateModal" v-if="event?.status === 'planned'" type="primary">Activate</a-button>
          <a-button @click="showCompleteModal" v-if="event?.status === 'active'" type="primary">Complete</a-button>
          <a-button @click="showCancelModal" v-if="['planned','active'].includes(event?.status)" danger>Cancel Event</a-button>
        </a-space>
      </template>
    </a-page-header>

    <a-row :gutter="[16, 16]">
      <!-- Event Info -->
      <a-col :xs="24" :lg="8">
        <a-card title="Event Details" :loading="loading">
          <a-descriptions :column="1" size="small">
            <a-descriptions-item label="Quarter">
              <strong v-if="event?.quarter">
                P{{ event.quarter }} · {{ ['','Jan-Apr','May-Aug','Sep-Dec'][event.quarter] }} {{ event.year }}
              </strong>
              <span v-else>-</span>
            </a-descriptions-item>
            <a-descriptions-item label="Retiree(s)">
              <div v-if="event?.retiree_name">
                <template v-for="r in retireeDetails" :key="r.name">
                  <a-popover trigger="click" placement="right">
                    <template #content>
                      <div style="font-size: 13px">
                        <div><strong>NIC:</strong> {{ r.nic || '-' }}</div>
                        <div><strong>Service No:</strong> {{ r.service_no || '-' }}</div>
                        <div><strong>Computer No:</strong> {{ r.computer_no || '-' }}</div>
                        <div><strong>Retirement:</strong> {{ r.retirement_date || '-' }}</div>
                      </div>
                    </template>
                    <a-tag color="blue" style="margin: 2px; cursor: pointer">{{ r.name }}</a-tag>
                  </a-popover>
                </template>
                <a-button type="link" size="small" @click="showRetireeModal" v-if="canEdit">+ Add</a-button>
              </div>
              <span v-else>
                <span style="color: #999">None</span>
                <a-button type="link" size="small" @click="showRetireeModal" v-if="canEdit">+ Add</a-button>
              </span>
            </a-descriptions-item>
            <a-descriptions-item label="Date">
              <template v-if="editingDate && canEdit">
                <a-date-picker
                  v-model:value="dateFormValue"
                  size="small"
                  style="width: 140px"
                  value-format="YYYY-MM-DD"
                  autofocus
                />
                <a-button type="link" size="small" @click="saveDate" :loading="savingDate">
                  <CheckOutlined />
                </a-button>
                <a-button type="link" size="small" @click="cancelEditDate">
                  <CloseOutlined />
                </a-button>
              </template>
              <template v-else>
                {{ event?.event_date || '-' }}
                <a-button type="link" size="small" @click="startEditDate" v-if="canEdit">
                  <EditOutlined />
                </a-button>
              </template>
            </a-descriptions-item>
            <a-descriptions-item label="Status">
              <a-tag :color="statusColor(event?.status)">{{ event?.status }}</a-tag>
            </a-descriptions-item>
            <a-descriptions-item label="Organizer">{{ event?.organizer_name }}</a-descriptions-item>
            <a-descriptions-item label="Total Budget">Rs. {{ Number(event?.budget_allocated || 0).toFixed(2) }}</a-descriptions-item>
            <a-descriptions-item label="Notes">
              <template v-if="editingNotes && canEdit">
                <a-textarea
                  v-model:value="notesFormValue"
                  :rows="3"
                  style="min-width: 200px"
                  autofocus
                />
                <div style="margin-top: 4px">
                  <a-button type="primary" size="small" @click="saveNotes" :loading="savingNotes">
                    <CheckOutlined /> Save
                  </a-button>
                  <a-button size="small" @click="cancelEditNotes" style="margin-left: 4px">
                    Cancel
                  </a-button>
                </div>
              </template>
              <template v-else>
                <span style="white-space: pre-wrap">{{ event?.notes || '-' }}</span>
                <a-button type="link" size="small" @click="startEditNotes" v-if="canEdit">
                  <EditOutlined />
                </a-button>
              </template>
            </a-descriptions-item>
          </a-descriptions>
        </a-card>
      </a-col>

      <!-- Budget vs Actual -->
      <a-col :xs="24" :lg="16">
        <a-card title="Budget vs Actual" :loading="loading">
          <template #extra>
            <a-button type="primary" size="small" @click="showAddBudget" v-if="canEdit">
              <PlusOutlined /> Add Line
            </a-button>
          </template>
          <a-table :dataSource="event?.budgets || []" rowKey="id" size="small" :pagination="false">
            <a-table-column title="Category" dataIndex="category_name" />
            <a-table-column title="Planned" dataIndex="planned_amount" align="right">
              <template #default="{ record }">Rs. {{ Number(record.planned_amount).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Actual" dataIndex="actual_spent" align="right">
              <template #default="{ record }">Rs. {{ Number(record.actual_spent).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Remaining" align="right">
              <template #default="{ record }">
                <span :style="{ color: (record.planned_amount - record.actual_spent) < 0 ? '#cf1322' : '#3f8600' }">
                  Rs. {{ (Number(record.planned_amount) - Number(record.actual_spent)).toFixed(2) }}
                </span>
              </template>
            </a-table-column>
            <a-table-column title="% Used" align="right">
              <template #default="{ record }">
                <a-progress :percent="record.planned_amount > 0 ? Math.min(100, Math.round((record.actual_spent / record.planned_amount) * 100)) : 0" size="small" :status="record.planned_amount > 0 && record.actual_spent > record.planned_amount ? 'exception' : 'active'" />
              </template>
            </a-table-column>
            <a-table-column title="Actions" v-if="canEdit">
              <template #default="{ record }">
                <a-popconfirm title="Delete this budget line?" @confirm="deleteBudget(record.id)">
                  <a-button size="small" danger type="link"><DeleteOutlined /></a-button>
                </a-popconfirm>
              </template>
            </a-table-column>
          </a-table>
        </a-card>
      </a-col>
    </a-row>

    <!-- Gift Issuance -->
    <a-card title="Gift Issuance" style="margin-top: 16px" :loading="loading">
      <template #extra>
        <a-button type="primary" size="small" @click="showIssueGiftModal" v-if="canEdit" :disabled="giftIssuanceRetirees.length === 0">
          <GiftOutlined /> Issue Gift
        </a-button>
      </template>
      <a-alert v-if="giftIssuanceRetirees.length === 0" type="info" show-icon message="No retirees added to this event yet" style="margin-bottom: 12px" />
      <a-table :dataSource="giftIssuanceRetirees" rowKey="name" size="small" :pagination="false">
        <a-table-column title="Retiree" dataIndex="name" />
        <a-table-column title="Gift Status" width="140">
          <template #default="{ record }">
            <a-tag :color="record.gift_issued ? 'green' : 'orange'">
              {{ record.gift_issued ? 'Issued' : 'Not Issued' }}
            </a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Gift(s) Issued" ellipsis>
          <template #default="{ record }">
            <template v-if="record.gifts && record.gifts.length > 0">
              <a-tag v-for="g in record.gifts" :key="g.id" color="blue" style="margin: 2px">
                {{ g.gift_name }} ({{ g.quantity }})
              </a-tag>
            </template>
            <span v-else style="color: #999">-</span>
          </template>
        </a-table-column>
        <a-table-column title="Actions" width="100" v-if="canEdit">
          <template #default="{ record }">
            <a-button size="small" type="link" @click="showManageGifts(record)" :disabled="!record.gifts || record.gifts.length === 0">
              Manage
            </a-button>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <!-- Transactions for this event -->
    <a-card title="Event Transactions" style="margin-top: 16px" :loading="loading">
      <a-table :dataSource="event?.transactions || []" rowKey="id" size="small" :pagination="{ pageSize: 10 }">
        <a-table-column title="Date" dataIndex="transaction_date" width="100" />
        <a-table-column title="Type" dataIndex="type" width="80">
          <template #default="{ record }">
            <a-tag :color="record.type === 'income' ? 'green' : 'red'">{{ record.type }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="Category" dataIndex="category_name" />
        <a-table-column title="Description" dataIndex="description" ellipsis />
        <a-table-column title="Amount" dataIndex="amount" align="right" width="100">
          <template #default="{ record }">Rs. {{ Number(record.amount).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Status" dataIndex="status" width="120">
          <template #default="{ record }">
            <a-tag :color="txnStatusColor(record.status)">{{ record.status }}</a-tag>
          </template>
        </a-table-column>
      </a-table>
    </a-card>

    <!-- Add Budget Modal -->
    <a-modal v-model:visible="budgetModalVisible" title="Add Budget Line" @ok="handleAddBudget" :confirm-loading="budgetSubmitting" destroyOnClose>
      <a-form layout="vertical">
        <a-form-item label="Category" required>
          <a-select v-model:value="budgetForm.category_id" placeholder="Select category" style="width: 100%">
            <a-select-option v-for="cat in expenseCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Planned Amount (LKR)" required>
          <a-input-number v-model:value="budgetForm.planned_amount" :min="0" :step="10" style="width: 100%" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Activate Event Modal -->
    <a-modal v-model:visible="activateModalVisible" title="Activate Event" @ok="confirmActivate" :confirm-loading="activating" ok-text="Yes, Activate Event" destroyOnClose>
      <p>Are you sure you want to activate <strong>{{ event?.name }}</strong>?</p>
      <a-divider />
      <a-descriptions :column="1" size="small" bordered>
        <a-descriptions-item label="Event Budget">
          <strong>Rs. {{ Number(event?.budget_allocated || 0).toFixed(2) }}</strong>
        </a-descriptions-item>
        <a-descriptions-item label="Current Net Balance">
          <span :style="{ color: activateNetBalance >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }">
            Rs. {{ Number(activateNetBalance).toFixed(2) }}
          </span>
        </a-descriptions-item>
        <a-descriptions-item label="Projected Balance After Event">
          <span :style="{ color: (activateNetBalance - (event?.budget_allocated || 0)) >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }">
            Rs. {{ (activateNetBalance - Number(event?.budget_allocated || 0)).toFixed(2) }}
          </span>
        </a-descriptions-item>
      </a-descriptions>
      <p style="color: #999; margin-top: 12px">Once activated, expenses and budgets can be tracked against this event.</p>
    </a-modal>

    <!-- Complete Event Modal -->
    <a-modal v-model:visible="completeModalVisible" title="Complete Event" @ok="confirmComplete" :confirm-loading="completing" ok-text="Yes, Complete Event" destroyOnClose>
      <p>Are you sure you want to mark <strong>{{ event?.name }}</strong> as completed?</p>
      <p style="color: #999">This will lock the event and prevent further transactions. A final P&L report will be generated.</p>
      <a-form layout="vertical">
        <a-form-item label="Final notes (optional)">
          <a-textarea v-model:value="completeNotes" :rows="3" placeholder="Any closing remarks..." />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Cancel Event Modal -->
    <a-modal v-model:visible="cancelModalVisible" title="Cancel Event" @ok="confirmCancel" :confirm-loading="cancelling" ok-text="Yes, Cancel Event" ok-button-props="{ danger: true }" destroyOnClose>
      <p>Are you sure you want to cancel <strong>{{ event?.name }}</strong>?</p>
      <p style="color: #999">This will lock the event and prevent further transactions.</p>
      <a-form layout="vertical">
        <a-form-item label="Reason for cancellation" required>
          <a-textarea v-model:value="cancelReason" :rows="3" placeholder="Explain why this event is being cancelled..." />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Add Retiree Modal -->
    <a-modal v-model:visible="retireeModalVisible" title="Add Retiree(s)" @ok="confirmRetiree" :confirm-loading="retireeSaving" destroyOnClose>
      <a-form layout="vertical">
        <a-form-item label="Select Members" required>
          <a-select v-model:value="selectedRetirees" mode="multiple" placeholder="Search and select members" style="width: 100%" :options="memberOptions" :filter-option="(input, option) => option.label.toLowerCase().includes(input.toLowerCase())" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Issue Gift Modal -->
    <a-modal v-model:visible="issueGiftModalVisible" title="Issue Gift to Retiree" @ok="confirmIssueGift" :confirm-loading="issueGiftSubmitting" ok-text="Issue Gift" destroyOnClose>
      <a-form layout="vertical">
        <a-form-item label="Retiree" required>
          <a-select v-model:value="issueGiftForm.member_id" placeholder="Select retiree" style="width: 100%">
            <a-select-option v-for="r in giftIssuanceRetirees" :key="r.member_id" :value="r.member_id">
              {{ r.name }} {{ r.gift_issued ? '(has gifts)' : '' }}
            </a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Gift" required>
          <a-select v-model:value="issueGiftForm.gift_id" placeholder="Select gift" style="width: 100%">
            <a-select-option v-for="g in giftStockItems" :key="g.id" :value="g.id" :disabled="g.quantity <= 0">
              {{ g.name }} ({{ g.quantity }} in stock)
            </a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Quantity" required>
          <a-input-number v-model:value="issueGiftForm.quantity" :min="1" style="width: 100%" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- Manage Gifts Modal -->
    <a-modal v-model:visible="manageGiftsVisible" :title="`Manage Gifts - ${manageGiftsRetiree?.name || ''}`" :footer="null" destroyOnClose width="860">
      <a-table :dataSource="manageGiftsList" rowKey="id" size="small" :pagination="false" :loading="manageGiftsLoading">
        <a-table-column title="Gift" dataIndex="gift_name" ellipsis />
        <a-table-column title="Qty" dataIndex="quantity" align="right" width="60" />
        <a-table-column title="Unit Price" dataIndex="unit_price" align="right" width="100">
          <template #default="{ record }">Rs. {{ Number(record.unit_price || 0).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Total" align="right" width="110">
          <template #default="{ record }">Rs. {{ (Number(record.quantity || 0) * Number(record.unit_price || 0)).toFixed(2) }}</template>
        </a-table-column>
        <a-table-column title="Issued On" dataIndex="created_at" width="110">
          <template #default="{ record }">{{ record.created_at ? dayjs(record.created_at).format('YYYY-MM-DD') : '-' }}</template>
        </a-table-column>
        <a-table-column title="Actions" width="150">
          <template #default="{ record }">
            <a-space>
              <a-button size="small" type="link" @click="startEditMovement(record)">Edit</a-button>
              <a-popconfirm title="Remove this gift issue? Stock will be returned." @confirm="removeMovement(record)">
                <a-button size="small" danger type="link"><DeleteOutlined /></a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </a-table>
      <div style="margin-top: 12px; text-align: right">
        <a-button type="primary" size="small" @click="closeManageGifts">Done</a-button>
      </div>
    </a-modal>

    <!-- Edit Movement Modal -->
    <a-modal v-model:visible="editMovementVisible" title="Edit Issued Gift" @ok="confirmEditMovement" :confirm-loading="editMovementSubmitting" ok-text="Save" destroyOnClose>
      <a-form layout="vertical">
        <a-form-item label="Gift">
          <a-select v-model:value="editMovementForm.gift_id" style="width: 100%">
            <a-select-option v-for="g in giftStockItems" :key="g.id" :value="g.id">{{ g.name }}</a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item label="Quantity" required>
          <a-input-number v-model:value="editMovementForm.quantity" :min="1" style="width: 100%" />
        </a-form-item>
        <a-form-item label="Notes">
          <a-textarea v-model:value="editMovementForm.notes" :rows="2" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useEventStore } from '../stores/events.js'
import { useAuthStore } from '../stores/auth.js'
import { categories as catApi, members as membersApi, reports as reportsApi, giftStock as giftStockApi } from '../api/index.js'
import { message } from 'ant-design-vue'
import dayjs from 'dayjs'

const route = useRoute()
const store = useEventStore()
const auth = useAuthStore()
const loading = ref(true)
const event = ref(null)
const expenseCategories = ref([])
const membersList = ref([])
const budgetModalVisible = ref(false)
const budgetSubmitting = ref(false)
const budgetForm = ref({ category_id: null, planned_amount: 0 })

const canEdit = computed(() => {
  if (!['admin', 'treasurer', 'organizer'].includes(auth.user?.role)) return false
  if (!event.value) return true
  return !['completed', 'cancelled'].includes(event.value.status)
})

const retireeDetails = ref([])

function refreshRetireeDetails() {
  const names = (event.value?.retiree_name || '').split(',').map((s) => s.trim()).filter(Boolean)
  if (names.length === 0) { retireeDetails.value = []; return }
  const map = {}
  membersList.value.forEach((m) => { map[m.name.toLowerCase()] = m })
  retireeDetails.value = names.map((n) => map[n.toLowerCase()] || { name: n })
}

const activateModalVisible = ref(false)
const activating = ref(false)
const activateNetBalance = ref(0)
const completeModalVisible = ref(false)
const completing = ref(false)
const completeNotes = ref('')

// Date editing
const editingDate = ref(false)
const dateFormValue = ref(null)
const savingDate = ref(false)

function startEditDate() {
  dateFormValue.value = event.value?.event_date || null
  editingDate.value = true
}

function cancelEditDate() {
  editingDate.value = false
}

async function saveDate() {
  savingDate.value = true
  try {
    await store.update(route.params.id, { event_date: dateFormValue.value || null })
    message.success('Event date updated')
    editingDate.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { savingDate.value = false }
}

// Notes editing
const editingNotes = ref(false)
const notesFormValue = ref('')
const savingNotes = ref(false)

function startEditNotes() {
  notesFormValue.value = event.value?.notes || ''
  editingNotes.value = true
}

function cancelEditNotes() {
  editingNotes.value = false
}

async function saveNotes() {
  savingNotes.value = true
  try {
    await store.update(route.params.id, { notes: notesFormValue.value || '' })
    message.success('Notes updated')
    editingNotes.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { savingNotes.value = false }
}
const cancelModalVisible = ref(false)
const cancelling = ref(false)
const cancelReason = ref('')
const retireeModalVisible = ref(false)
const selectedRetirees = ref([])
const memberOptions = ref([])
const retireeSaving = ref(false)

const giftIssuanceRetirees = ref([])
const giftStockItems = ref([])
const issueGiftModalVisible = ref(false)
const issueGiftSubmitting = ref(false)
const issueGiftForm = ref({ member_id: null, gift_id: null, quantity: 1 })

const manageGiftsVisible = ref(false)
const manageGiftsRetiree = ref(null)
const manageGiftsList = ref([])
const manageGiftsLoading = ref(false)
const editMovementVisible = ref(false)
const editMovementSubmitting = ref(false)
const editMovementForm = ref({ movement_id: null, gift_id: null, quantity: 1, notes: '' })

function statusColor(s) {
  return { planned: 'blue', active: 'green', completed: 'default', cancelled: 'red' }[s] || 'default'
}
function txnStatusColor(s) {
  return { draft: 'default', pending_approval: 'orange', approved: 'green', rejected: 'red' }[s] || 'default'
}

async function fetchEvent() {
  loading.value = true
  try {
    const [evtRes, memRes, giRes, giftRes] = await Promise.all([
      store.get(route.params.id),
      membersApi.list({ per_page: 1000 }),
      store.giftIssuance ? store.giftIssuance(route.params.id) : Promise.resolve(null),
      giftStockApi.list(),
    ])
    event.value = evtRes
    membersList.value = memRes.data?.items || []
    giftIssuanceRetirees.value = giRes?.data?.retirees || []
    giftStockItems.value = giftRes.data || []
    refreshRetireeDetails()
  } catch (e) { message.error('Failed to load event') }
  finally { loading.value = false }
}

async function showActivateModal() {
  try {
    const res = await reportsApi.dashboard({ year: new Date().getFullYear() })
    activateNetBalance.value = res.data?.net_balance || 0
  } catch { activateNetBalance.value = 0 }
  activateModalVisible.value = true
}

function showCompleteModal() {
  completeNotes.value = ''
  completeModalVisible.value = true
}

function showCancelModal() {
  cancelReason.value = ''
  cancelModalVisible.value = true
}

async function confirmActivate() {
  activating.value = true
  try {
    await store.updateStatus(route.params.id, 'active')
    message.success('Event activated')
    activateModalVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { activating.value = false }
}

async function confirmComplete() {
  completing.value = true
  try {
    await store.updateStatus(route.params.id, 'completed')
    if (completeNotes.value.trim()) {
      await store.update(route.params.id, { notes: completeNotes.value.trim() })
    }
    message.success('Event completed')
    completeModalVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { completing.value = false }
}

async function confirmCancel() {
  if (!cancelReason.value.trim()) {
    message.error('Please provide a reason for cancellation')
    return
  }
  cancelling.value = true
  try {
    await store.updateStatus(route.params.id, 'cancelled')
    message.success('Event cancelled')
    cancelModalVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { cancelling.value = false }
}

async function showRetireeModal() {
  selectedRetirees.value = []
  try {
    const res = await membersApi.list({ per_page: 1000 })
    memberOptions.value = (res.data?.items || []).map((m) => ({ label: `${m.name} (${m.nic})`, value: m.name }))
  } catch { memberOptions.value = [] }
  retireeModalVisible.value = true
}

async function confirmRetiree() {
  if (selectedRetirees.value.length === 0) { message.error('Select at least one member'); return }
  retireeSaving.value = true
  try {
    const existing = event.value.retiree_name ? event.value.retiree_name.split(', ').filter(Boolean) : []
    const all = [...new Set([...existing, ...selectedRetirees.value])]
    await store.update(route.params.id, { retiree_name: all.join(', ') })
    message.success('Retiree(s) added')
    retireeModalVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { retireeSaving.value = false }
}

function showIssueGiftModal() {
  issueGiftForm.value = { member_id: null, gift_id: null, quantity: 1 }
  issueGiftModalVisible.value = true
}

async function confirmIssueGift() {
  if (!issueGiftForm.value.member_id) { message.error('Select a retiree'); return }
  if (!issueGiftForm.value.gift_id) { message.error('Select a gift'); return }
  if (!issueGiftForm.value.quantity || issueGiftForm.value.quantity < 1) { message.error('Quantity must be at least 1'); return }
  issueGiftSubmitting.value = true
  try {
    await giftStockApi.issue(issueGiftForm.value.gift_id, {
      quantity: issueGiftForm.value.quantity,
      event_id: route.params.id,
      member_id: issueGiftForm.value.member_id,
    })
    message.success('Gift issued')
    issueGiftModalVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { issueGiftSubmitting.value = false }
}

function showManageGifts(record) {
  manageGiftsRetiree.value = record
  manageGiftsList.value = record.gifts || []
  manageGiftsVisible.value = true
}

function closeManageGifts() {
  manageGiftsVisible.value = false
}

function startEditMovement(record) {
  editMovementForm.value = {
    movement_id: record.id,
    gift_id: record.gift_id,
    quantity: record.quantity,
    notes: record.notes || '',
  }
  editMovementVisible.value = true
}

async function confirmEditMovement() {
  if (!editMovementForm.value.gift_id) { message.error('Select a gift'); return }
  if (!editMovementForm.value.quantity || editMovementForm.value.quantity < 1) { message.error('Quantity must be at least 1'); return }
  editMovementSubmitting.value = true
  try {
    await giftStockApi.updateMovement(editMovementForm.value.movement_id, {
      quantity: editMovementForm.value.quantity,
      notes: editMovementForm.value.notes || null,
    })
    message.success('Gift issue updated')
    editMovementVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { editMovementSubmitting.value = false }
}

async function removeMovement(record) {
  try {
    await giftStockApi.deleteMovement(record.id)
    message.success('Gift issue removed')
    await fetchEvent()
    showManageGifts(manageGiftsRetiree.value)
  } catch (e) { message.error(e?.message || 'Failed') }
}

async function showAddBudget() {
  try {
    const res = await catApi.list({ type: 'expense' })
    expenseCategories.value = res.data || []
  } catch {}
  budgetForm.value = { category_id: null, planned_amount: 0 }
  budgetModalVisible.value = true
}

async function handleAddBudget() {
  if (!budgetForm.value.category_id || !budgetForm.value.planned_amount) {
    message.error('Please fill all fields')
    return
  }
  budgetSubmitting.value = true
  try {
    await store.createBudget(route.params.id, budgetForm.value)
    message.success('Budget line added')
    budgetModalVisible.value = false
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
  finally { budgetSubmitting.value = false }
}

async function deleteBudget(id) {
  try {
    await store.deleteBudget(id)
    message.success('Budget line deleted')
    await fetchEvent()
  } catch (e) { message.error(e?.message || 'Failed') }
}

onMounted(fetchEvent)
</script>
