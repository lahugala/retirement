<template>
  <a-layout style="min-height: 100vh">
    <!-- Sider -->
    <a-layout-sider v-model:collapsed="collapsed" :trigger="null" collapsible breakpoint="lg" class="sider">
      <div class="logo">
        <GiftOutlined style="font-size: 22px; margin-right: 8px" v-if="!collapsed" />
        <GiftOutlined style="font-size: 20px" v-else />
        <span v-if="!collapsed" class="logo-text">Retirement Society</span>
      </div>
      <a-menu theme="dark" mode="inline" :selectedKeys="[selectedKey]" @click="navigate">
        <a-menu-item key="dashboard">
          <DashboardOutlined />
          <span>Dashboard</span>
        </a-menu-item>
        <a-menu-item key="events" v-if="canAccess('events')">
          <CalendarOutlined />
          <span>Quarterly Planning</span>
        </a-menu-item>
        <a-menu-item key="transactions" v-if="canAccess('transactions')">
          <DollarOutlined />
          <span>Transactions</span>
        </a-menu-item>
        <a-menu-item key="reports" v-if="canAccess('reports')">
          <BarChartOutlined />
          <span>Reports</span>
        </a-menu-item>
        <a-menu-divider style="background: rgba(255,255,255,0.1); margin: 8px 16px" />
        <a-menu-item key="members" v-if="canAccess('members')">
          <SolutionOutlined />
          <span>Members</span>
        </a-menu-item>
        <a-menu-item key="users" v-if="canAccess('users')">
          <TeamOutlined />
          <span>Users</span>
        </a-menu-item>
        <a-menu-item key="categories" v-if="canAccess('categories')">
          <TagsOutlined />
          <span>Categories</span>
        </a-menu-item>
        <a-menu-item key="audit" v-if="canAccess('audit')">
          <SafetyOutlined />
          <span>Audit Log</span>
        </a-menu-item>
      </a-menu>
    </a-layout-sider>

    <!-- Layout -->
    <a-layout class="site-layout" :style="{ marginLeft: collapsed ? '80px' : '200px' }">
      <!-- Header -->
      <a-layout-header class="top-header">
        <div class="header-left">
          <MenuOutlined class="trigger" @click="collapsed = !collapsed" />

          <!-- Breadcrumb-style page title -->
          <span class="page-title">{{ pageTitle }}</span>
        </div>

        <div class="header-right">
          <!-- Quick actions -->
          <a-dropdown v-if="canCreate">
            <a-button type="primary" class="quick-add-btn">
              <PlusOutlined /> Quick Add
              <DownOutlined />
            </a-button>
            <template #overlay>
              <a-menu @click="handleQuickAction">
                <a-menu-item key="transaction"><DollarOutlined /> Transaction</a-menu-item>
                <a-menu-item key="event"><CalendarOutlined /> Event</a-menu-item>
              </a-menu>
            </template>
          </a-dropdown>

          <!-- Pending approvals -->
          <a-badge :count="pendingCount" :overflow-count="99" class="pending-badge" v-if="canApprove">
            <a-tooltip title="Pending approvals">
              <a-button shape="circle" :class="['pending-btn', { 'has-pending': pendingCount > 0 }]" @click="goToPending">
                <ClockCircleOutlined />
              </a-button>
            </a-tooltip>
          </a-badge>

          <!-- Notification bell (placeholder for future) -->
          <a-tooltip title="Notifications (coming soon)">
            <a-button shape="circle" class="header-icon-btn">
              <BellOutlined />
            </a-button>
          </a-tooltip>

          <!-- User dropdown -->
          <a-dropdown placement="bottomRight">
            <div class="user-dropdown-trigger">
              <a-avatar :style="{ backgroundColor: roleColor, verticalAlign: 'middle' }" size="small">
                {{ userInitials }}
              </a-avatar>
              <span class="user-name">{{ auth.user?.name }}</span>
              <DownOutlined style="font-size: 10px; color: #999" />
            </div>
            <template #overlay>
              <a-menu>
                <div class="user-dropdown-header">
                  <a-avatar :style="{ backgroundColor: roleColor }" size="large">{{ userInitials }}</a-avatar>
                  <div style="margin-left: 12px">
                    <div style="font-weight: 600">{{ auth.user?.name }}</div>
                    <a-tag :color="roleColor" style="margin: 0">{{ roleLabel }}</a-tag>
                    <div style="font-size: 12px; color: #999; margin-top: 2px">{{ auth.user?.email }}</div>
                  </div>
                </div>
                <a-menu-divider />
                <a-menu-item @click="$router.push('/dashboard')"><DashboardOutlined /> Dashboard</a-menu-item>
                <a-menu-divider />
                <a-menu-item @click="logout" style="color: #ff4d4f"><LogoutOutlined /> Sign Out</a-menu-item>
              </a-menu>
            </template>
          </a-dropdown>
        </div>
      </a-layout-header>

      <!-- Content -->
      <div class="content-wrapper">
        <router-view />
      </div>

      <!-- Footer -->
      <a-layout-footer class="app-footer">
        Retirement Celebration Society &copy; {{ new Date().getFullYear() }}
        <span class="footer-sep">|</span>
        <span style="color: #999">v1.0</span>
      </a-layout-footer>
    </a-layout>
  </a-layout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { canAccess as checkAccess } from '../utils/permissions.js'
import { transactions as txnApi } from '../api/index.js'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const collapsed = ref(false)
const pendingCount = ref(0)
let pollInterval = null

const pageTitle = computed(() => {
  const titles = {
    dashboard: 'Dashboard',
    events: 'Quarterly Event Planning',
    eventdetail: 'Event Detail',
    transactions: 'Transactions',
    reports: 'Reports & Analytics',
    members: 'Members',
    users: 'Users',
    categories: 'Income & Expense Categories',
    auditlog: 'Audit Trail',
  }
  return titles[route.name?.toLowerCase()] || 'Retirement Society'
})

const selectedKey = computed(() => {
  const name = route.name?.toLowerCase() || 'dashboard'
  if (name === 'eventdetail') return 'events'
  return name
})

const userInitials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(s => s[0]).join('').toUpperCase().slice(0, 2)
})

const roleLabel = computed(() => auth.roleLabel)
const roleColor = computed(() => {
  const colors = { admin: '#f5222d', treasurer: '#faad14', organizer: '#1890ff', board: '#722ed1', member: '#52c41a' }
  return colors[auth.user?.role] || '#1890ff'
})
const canApprove = computed(() => auth.canApprove)
const canCreate = computed(() => ['admin', 'treasurer', 'organizer'].includes(auth.user?.role))

function canAccess(menu) {
  return checkAccess(menu, auth.user?.role)
}

function navigate({ key }) {
  const routeMap = {
    dashboard: 'Dashboard',
    events: 'Events',
    transactions: 'Transactions',
    reports: 'Reports',
    members: 'Members',
    users: 'Users',
    categories: 'Categories',
    audit: 'AuditLog',
  }
  const name = routeMap[key]
  if (name) router.push({ name })
}

function goToPending() {
  router.push({ name: 'Transactions', query: { status: 'pending_approval' } })
}

function handleQuickAction({ key }) {
  if (key === 'transaction') {
    router.push({ name: 'Transactions', query: { quick_add: '1' } })
  } else if (key === 'event') {
    router.push({ name: 'Events' })
  }
}

function logout() {
  auth.logout()
  router.push('/login')
}

async function fetchPendingCount() {
  if (!canApprove.value) return
  try {
    const res = await txnApi.list({ status: 'pending_approval', per_page: 1 })
    pendingCount.value = res.data.total || 0
  } catch {}
}

onMounted(() => {
  fetchPendingCount()
  pollInterval = setInterval(fetchPendingCount, 30000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<style scoped>
.sider {
  background: #001529;
  box-shadow: 2px 0 8px rgba(0,0,0,0.05);
  z-index: 10;
  position: fixed;
  height: 100vh;
  top: 0;
  left: 0;
  overflow-y: auto;
}

.site-layout {
  margin-left: 200px;
  transition: margin-left 0.2s;
  min-height: 100vh;
}

.logo {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 16px;
  font-weight: 600;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  letter-spacing: 0.5px;
}
.logo-text {
  white-space: nowrap;
}

.top-header {
  background: #fff !important;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  position: sticky;
  top: 0;
  z-index: 9;
  height: 56px;
  line-height: 56px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.trigger {
  font-size: 18px;
  cursor: pointer;
  transition: color 0.3s;
  color: #666;
  padding: 4px;
  border-radius: 4px;
}
.trigger:hover {
  color: #1890ff;
  background: #f0f5ff;
}

.page-title {
  font-size: 16px;
  font-weight: 600;
  color: #262626;
  white-space: nowrap;
}

.quick-add-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  border-radius: 6px;
}

.pending-badge {
  margin-right: 4px;
}

.pending-btn {
  border: 1px solid #d9d9d9;
  color: #8c8c8c;
  transition: all 0.3s;
}
.pending-btn:hover {
  color: #faad14;
  border-color: #faad14;
}
.pending-btn.has-pending {
  color: #faad14;
  border-color: #faad14;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(250, 173, 20, 0.3); }
  50% { box-shadow: 0 0 0 6px rgba(250, 173, 20, 0); }
}

.header-icon-btn {
  border: 1px solid #d9d9d9;
  color: #8c8c8c;
  transition: all 0.3s;
}
.header-icon-btn:hover {
  color: #1890ff;
  border-color: #1890ff;
}

.user-dropdown-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.3s;
  margin-left: 4px;
}
.user-dropdown-trigger:hover {
  background: #f5f5f5;
}

.user-name {
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14px;
  color: #262626;
}

:deep(.user-dropdown-header) {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  min-width: 220px;
}

.content-wrapper {
  margin: 20px 24px;
  min-height: calc(100vh - 56px - 53px - 40px);
}

.app-footer {
  text-align: center;
  padding: 12px 50px;
  color: #bfbfbf;
  font-size: 13px;
  background: #fafafa;
  border-top: 1px solid #f0f0f0;
}

.footer-sep {
  margin: 0 12px;
  color: #e8e8e8;
}
</style>
