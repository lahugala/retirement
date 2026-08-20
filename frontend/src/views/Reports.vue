<template>
  <div>
    <a-page-header title="Reports" sub-title="Financial summaries for board members" />

    <!-- Global Net Balance Bar -->
    <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
      <a-col :span="8">
        <a-statistic title="Current Net Balance" :value="globalNetBalance" prefix="Rs." precision="2" :value-style="{ color: globalNetBalance >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
      </a-col>
      <a-col :span="8">
        <a-statistic title="Total Budgeted (Active Events)" :value="globalBudgeted" prefix="Rs." precision="2" />
      </a-col>
      <a-col :span="8">
        <a-statistic title="Projected Balance" :value="globalProjected" prefix="Rs." precision="2" :value-style="{ color: globalProjected >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
      </a-col>
    </a-row>

    <a-tabs default-active-key="dashboard">
      <!-- Dashboard Tab -->
      <a-tab-pane key="dashboard" tab="Dashboard">
        <a-row :gutter="[16, 16]">
          <a-col :xs="24" :sm="12" :lg="6">
            <a-card>
              <a-statistic title="Total Income" :value="dashboardData.total_income" prefix="Rs. " :value-style="{ color: '#3f8600' }" />
            </a-card>
          </a-col>
          <a-col :xs="24" :sm="12" :lg="6">
            <a-card>
              <a-statistic title="Total Expense" :value="dashboardData.total_expense" prefix="Rs. " :value-style="{ color: '#cf1322' }" />
            </a-card>
          </a-col>
          <a-col :xs="24" :sm="12" :lg="6">
            <a-card>
              <a-statistic title="Net Balance" :value="dashboardData.net_balance" prefix="Rs. " :value-style="{ color: dashboardData.net_balance >= 0 ? '#3f8600' : '#cf1322' }" />
            </a-card>
          </a-col>
          <a-col :xs="24" :sm="12" :lg="6">
            <a-card>
              <a-statistic title="Active Events" :value="dashboardData.active_events" suffix="events" />
            </a-card>
          </a-col>
        </a-row>
      </a-tab-pane>

      <!-- Income Statement -->
      <a-tab-pane key="income" tab="Income Statement">
        <a-card>
          <template #extra>
            <a-space>
              <a-date-picker v-model:value="isDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="isDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-button type="primary" @click="fetchIncomeStatement">Refresh</a-button>
              <a-button @click="exportCSV(incomeData.categories, 'income-statement.csv')">Export CSV</a-button>
            </a-space>
          </template>
          <a-table :dataSource="incomeData.categories || []" rowKey="id" size="small" :pagination="false">
            <a-table-column title="Category" dataIndex="name" />
            <a-table-column title="Type" dataIndex="type" width="80">
              <template #default="{ record }">
                <a-tag :color="record.type === 'income' ? 'green' : 'volcano'">{{ record.type }}</a-tag>
              </template>
            </a-table-column>
            <a-table-column title="Transactions" dataIndex="count" width="120" align="right" />
            <a-table-column title="Total" dataIndex="total" align="right" width="120">
              <template #default="{ record }">Rs. {{ Number(record.total).toFixed(2) }}</template>
            </a-table-column>
          </a-table>
          <a-divider />
          <a-row :gutter="16">
            <a-col :span="8"><strong>Total Income:</strong> Rs. {{ Number(incomeData.total_income || 0).toFixed(2) }}</a-col>
            <a-col :span="8"><strong>Total Expense:</strong> Rs. {{ Number(incomeData.total_expense || 0).toFixed(2) }}</a-col>
            <a-col :span="8"><strong>Net Income:</strong> <span :style="{ color: (incomeData.net_income || 0) >= 0 ? '#3f8600' : '#cf1322' }">Rs. {{ Number(incomeData.net_income || 0).toFixed(2) }}</span></a-col>
          </a-row>
        </a-card>
      </a-tab-pane>

      <!-- Member Contributions -->
      <a-tab-pane key="contributions" tab="Member Contributions">
        <a-card>
          <template #extra>
            <a-space>
              <a-date-picker v-model:value="mcDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="mcDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-button type="primary" @click="fetchContributions">Refresh</a-button>
              <a-button @click="exportCSV(contributionsData.by_retiree, 'contributions-by-retiree.csv')">Export CSV</a-button>
            </a-space>
          </template>

          <a-tabs>
            <a-tab-pane key="retiree" tab="By Retiree">
              <a-table :dataSource="contributionsData.by_retiree || []" rowKey="retiree" size="small" :pagination="false">
                <a-table-column title="Retiree" dataIndex="retiree" />
                <a-table-column title="Contributions" dataIndex="count" align="right" />
                <a-table-column title="Total" dataIndex="total" align="right">
                  <template #default="{ record }">Rs. {{ Number(record.total).toFixed(2) }}</template>
                </a-table-column>
              </a-table>
            </a-tab-pane>
            <a-tab-pane key="member" tab="By Member">
              <a-table :dataSource="contributionsData.by_member || []" rowKey="member_name" size="small" :pagination="false">
                <a-table-column title="Member" dataIndex="member_name" />
                <a-table-column title="Contributions" dataIndex="count" align="right" />
                <a-table-column title="Total" dataIndex="total" align="right">
                  <template #default="{ record }">Rs. {{ Number(record.total).toFixed(2) }}</template>
                </a-table-column>
              </a-table>
            </a-tab-pane>
          </a-tabs>
          <p><strong>Grand Total:</strong> Rs. {{ Number(contributionsData.grand_total || 0).toFixed(2) }}</p>
        </a-card>
      </a-tab-pane>

      <!-- Quarterly Summary -->
      <a-tab-pane key="quarterly" tab="Quarterly">
        <a-card>
          <template #extra>
            <a-space>
              <a-select v-model:value="qYear" style="width: 120px" @change="fetchQuarterly">
                <a-select-option v-for="y in [2024,2025,2026,2027]" :key="y" :value="y">{{ y }}</a-select-option>
              </a-select>
              <a-button @click="exportCSV(qData.quarters, 'quarterly-summary.csv')">Export CSV</a-button>
            </a-space>
          </template>

          <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
            <a-col :span="6"><a-statistic title="Year Budget" :value="qData.year_budget" prefix="Rs." /></a-col>
            <a-col :span="6"><a-statistic title="Income" :value="qData.year_total_income" prefix="Rs." :value-style="{ color: '#3f8600' }" /></a-col>
            <a-col :span="6"><a-statistic title="Expense" :value="qData.year_total_expense" prefix="Rs." :value-style="{ color: '#cf1322' }" /></a-col>
            <a-col :span="6"><a-statistic title="Net" :value="qData.year_net" prefix="Rs." :value-style="{ color: (qData.year_net || 0) >= 0 ? '#3f8600' : '#cf1322' }" /></a-col>
          </a-row>

          <a-table :dataSource="qData.quarters || []" rowKey="id" size="small" :pagination="false">
            <a-table-column title="Quarter" width="160">
              <template #default="{ record }">
                <template v-if="[1,2,3].includes(Number(record.quarter))">P{{ record.quarter }} · {{ ['','Jan-Apr','May-Aug','Sep-Dec'][record.quarter] }}</template>
                <template v-else>{{ record.name }}</template>
              </template>
            </a-table-column>
            <a-table-column title="Status" dataIndex="status" width="100">
              <template #default="{ record }"><a-tag :color="{ planned: 'blue', active: 'green', completed: 'default' }[record.status] || 'default'">{{ record.status }}</a-tag></template>
            </a-table-column>
            <a-table-column title="Budget" dataIndex="budget_allocated" align="right">
              <template #default="{ record }">Rs. {{ Number(record.budget_allocated || 0).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Income" dataIndex="total_income" align="right">
              <template #default="{ record }">Rs. {{ Number(record.total_income || 0).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Expense" dataIndex="total_expense" align="right">
              <template #default="{ record }">Rs. {{ Number(record.total_expense || 0).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Progress" width="200">
              <template #default="{ record }">
                <a-progress
                  :percent="record.budget_allocated > 0 ? Math.min(100, Math.round((record.total_expense / record.budget_allocated) * 100)) : 0"
                  size="small"
                  :status="record.total_expense > record.budget_allocated ? 'exception' : 'active'"
                />
              </template>
            </a-table-column>
            <a-table-column title="Retirees" dataIndex="retiree_name" ellipsis />
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- Retired Members Report -->
      <a-tab-pane key="retired-members" tab="Retired Members">
        <a-card>
          <template #extra>
            <a-space>
              <a-date-picker v-model:value="rmDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="rmDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-button type="primary" @click="fetchRetiredMembers">Search</a-button>
              <a-button @click="exportCSV(retiredMembers, 'retired-members.csv')" :disabled="retiredMembers.length === 0">Export CSV</a-button>
            </a-space>
          </template>

          <a-statistic title="Total Retired" :value="retiredTotal" suffix="members" :value-style="{ color: '#1890ff' }" style="margin-bottom: 16px" />

          <a-table :dataSource="retiredMembers" rowKey="nic" size="small" :pagination="{ pageSize: 20 }">
            <a-table-column title="Name" dataIndex="name" />
            <a-table-column title="NIC" dataIndex="nic" />
            <a-table-column title="Service No" dataIndex="service_no" />
            <a-table-column title="Computer No" dataIndex="computer_no" />
            <a-table-column title="Retirement Date" dataIndex="retirement_date" />
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- Gift History Report -->
      <a-tab-pane key="gift-history" tab="Gift History">
        <a-card>
          <template #extra>
            <a-space>
              <a-date-picker v-model:value="ghDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="ghDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-select v-model:value="ghGiftId" placeholder="All gifts" style="width: 200px" allow-clear :options="giftOptions" />
              <a-button type="primary" @click="fetchGiftHistory">Search</a-button>
              <a-button @click="exportCSV(giftMovements, 'gift-history.csv')" :disabled="giftMovements.length === 0">Export CSV</a-button>
            </a-space>
          </template>

          <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
            <a-col :span="6"><a-statistic title="Total Received" :value="giftSummary.total_received" suffix="units" :value-style="{ color: '#3f8600' }" /></a-col>
            <a-col :span="6"><a-statistic title="Total Issued" :value="giftSummary.total_issued" suffix="units" :value-style="{ color: '#cf1322' }" /></a-col>
            <a-col :span="6"><a-statistic title="Received Value" :value="giftSummary.value_received" prefix="Rs." :value-style="{ color: '#3f8600' }" /></a-col>
            <a-col :span="6"><a-statistic title="Issued Value" :value="giftSummary.value_issued" prefix="Rs." :value-style="{ color: '#cf1322' }" /></a-col>
          </a-row>

          <a-table :dataSource="giftMovements" rowKey="id" size="small" :pagination="{ pageSize: 20 }">
            <a-table-column title="Date" dataIndex="created_at" width="110">
              <template #default="{ record }">{{ dayjs(record.created_at).format('YYYY-MM-DD') }}</template>
            </a-table-column>
            <a-table-column title="Gift" dataIndex="gift_name" ellipsis />
            <a-table-column title="Type" dataIndex="movement_type" width="100">
              <template #default="{ record }">
                <a-tag :color="record.movement_type === 'received' ? 'green' : 'red'">{{ record.movement_type }}</a-tag>
              </template>
            </a-table-column>
            <a-table-column title="Qty" dataIndex="quantity" align="right" width="70" />
            <a-table-column title="Unit Price" dataIndex="unit_price" align="right" width="100">
              <template #default="{ record }">Rs. {{ Number(record.unit_price).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Total" dataIndex="total_value" align="right" width="110">
              <template #default="{ record }">Rs. {{ Number(record.total_value).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Member" dataIndex="member_name" ellipsis>
              <template #default="{ record }">{{ record.member_name || '-' }}</template>
            </a-table-column>
            <a-table-column title="Event" dataIndex="event_name" ellipsis>
              <template #default="{ record }">{{ record.event_name || '-' }}</template>
            </a-table-column>
            <a-table-column title="Recorded By" dataIndex="created_by_name" ellipsis>
              <template #default="{ record }">{{ record.created_by_name || '-' }}</template>
            </a-table-column>
            <a-table-column title="Notes" dataIndex="notes" ellipsis>
              <template #default="{ record }">{{ record.notes || '-' }}</template>
            </a-table-column>
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- Gift Not Issued Report -->
      <a-tab-pane key="gift-not-issued" tab="Gifts Not Issued">
        <a-card>
          <template #extra>
            <a-space>
              <a-select v-model:value="gniEventId" style="width: 300px" @change="fetchGiftNotIssued">
                <a-select-option value="all">View All Events</a-select-option>
                <a-select-option v-for="e in gniEventOptions" :key="e.value" :value="e.value">{{ e.label }}</a-select-option>
              </a-select>
              <a-button type="primary" @click="fetchGiftNotIssued">Search</a-button>
              <a-button @click="exportCSV(gniNotIssuedRetirees, 'gifts-not-issued-event.csv')" :disabled="gniNotIssuedRetirees.length === 0">Export CSV</a-button>
            </a-space>
          </template>

          <h5 style="margin: 0 0 8px 0">Event Retirees Without Gifts</h5>
          <a-statistic v-if="gniEventId && gniEventId !== 'all'" :title="`Not yet issued - ${gniEventName}`" :value="gniNotIssuedCount" suffix="retirees" :value-style="{ color: gniNotIssuedCount > 0 ? '#cf1322' : '#3f8600' }" style="margin-bottom: 16px" />
          <a-statistic v-else title="Not yet issued across all events" :value="gniNotIssuedCount" suffix="retirees" :value-style="{ color: gniNotIssuedCount > 0 ? '#cf1322' : '#3f8600' }" style="margin-bottom: 16px" />

          <a-table :dataSource="gniNotIssuedRetirees" rowKey="member_id" size="small" :pagination="{ pageSize: 20 }">
            <a-table-column v-if="gniEventId === 'all'" title="Event" dataIndex="event_name" ellipsis />
            <a-table-column title="Retiree" dataIndex="name" ellipsis />
            <a-table-column title="NIC" dataIndex="nic">
              <template #default="{ record }">{{ record.nic || '-' }}</template>
            </a-table-column>
            <a-table-column title="Service No" dataIndex="service_no">
              <template #default="{ record }">{{ record.service_no || '-' }}</template>
            </a-table-column>
            <a-table-column title="Computer No" dataIndex="computer_no">
              <template #default="{ record }">{{ record.computer_no || '-' }}</template>
            </a-table-column>
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- Account Balances -->
      <a-tab-pane key="account-balances" tab="Account Balances">
        <a-card>
          <template #extra>
            <a-space>
              <a-date-picker v-model:value="abDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="abDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-button type="primary" @click="fetchAccountBalances">Refresh</a-button>
            </a-space>
          </template>

          <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
            <a-col :span="6"><a-statistic title="Total Assets" :value="abTotals.total_assets" prefix="Rs. " :value-style="{ color: '#1890ff' }" /></a-col>
            <a-col :span="6"><a-statistic title="Total Liabilities" :value="abTotals.total_liabilities" prefix="Rs. " :value-style="{ color: '#faad14' }" /></a-col>
            <a-col :span="6"><a-statistic title="Total Equity" :value="abTotals.total_equity" prefix="Rs. " :value-style="{ color: '#3f8600' }" /></a-col>
            <a-col :span="6"><a-statistic title="Net Income" :value="abTotals.net_income" prefix="Rs. " :value-style="{ color: (abTotals.net_income || 0) >= 0 ? '#3f8600' : '#cf1322' }" /></a-col>
          </a-row>

          <a-table :dataSource="accountBalances" rowKey="id" size="small" :pagination="{ pageSize: 50 }">
            <a-table-column title="Code" dataIndex="code" width="80" />
            <a-table-column title="Account" dataIndex="name" ellipsis />
            <a-table-column title="Type" dataIndex="type" width="100">
              <template #default="{ record }">
                <a-tag :color="{ asset: 'blue', liability: 'orange', equity: 'green', income: 'cyan', expense: 'volcano' }[record.type] || 'default'">{{ record.type }}</a-tag>
              </template>
            </a-table-column>
            <a-table-column title="Debit" dataIndex="total_debit" align="right" width="120">
              <template #default="{ record }">Rs. {{ Number(record.total_debit).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Credit" dataIndex="total_credit" align="right" width="120">
              <template #default="{ record }">Rs. {{ Number(record.total_credit).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Balance" dataIndex="balance" align="right" width="120">
              <template #default="{ record }">
                <span :style="{ color: record.balance >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }">
                  Rs. {{ Number(record.balance).toFixed(2) }}
                </span>
              </template>
            </a-table-column>
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- Event P&L -->
      <a-tab-pane key="event-pnl" tab="Event P&L">
        <a-card>
          <template #extra>
            <a-space>
              <a-select v-model:value="selectedEventId" placeholder="Select event" style="width: 300px" :options="eventOptions" @change="fetchEventPnL" />
              <a-button @click="exportCSV(eventPnL.budget_lines, 'event-pnl.csv')" :disabled="!eventPnL.event">Export CSV</a-button>
            </a-space>
          </template>

          <div v-if="eventPnL.event">
            <!-- Summary Cards -->
            <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
              <a-col :span="6">
                <a-statistic title="Budget" :value="eventPnL.budget_total" prefix="Rs." precision="2" />
              </a-col>
              <a-col :span="6">
                <a-statistic title="Total Income" :value="eventPnL.total_income" prefix="Rs." precision="2" :value-style="{ color: '#3f8600' }" />
              </a-col>
              <a-col :span="6">
                <a-statistic title="Total Expense" :value="eventPnL.total_expense" prefix="Rs." precision="2" :value-style="{ color: eventPnL.total_expense > eventPnL.budget_total ? '#cf1322' : 'inherit' }" />
              </a-col>
              <a-col :span="6">
                <a-statistic title="Net Result" :value="eventPnL.net_result" prefix="Rs." precision="2" :value-style="{ color: (eventPnL.net_result || 0) >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
              </a-col>
            </a-row>

            <!-- Budget Utilization -->
            <a-progress
              v-if="eventPnL.budget_total > 0"
              :percent="Math.min(100, eventPnL.budget_utilization)"
              :status="eventPnL.budget_utilization > 100 ? 'exception' : 'active'"
              style="margin-bottom: 16px"
            />
            <div v-else style="margin-bottom: 16px; color: #999; font-size: 13px">No budget allocated</div>

            <!-- Budget vs Actual Table -->
            <h5 style="margin: 0 0 8px 0">Budget vs Actual</h5>
            <a-table :dataSource="eventPnL.budget_lines || []" rowKey="id" size="small" :pagination="false" style="margin-bottom: 16px">
              <a-table-column title="Category" dataIndex="category_name" />
              <a-table-column title="Planned" dataIndex="planned_amount" align="right">
                <template #default="{ record }">Rs. {{ Number(record.planned_amount).toFixed(2) }}</template>
              </a-table-column>
              <a-table-column title="Actual" dataIndex="actual" align="right">
                <template #default="{ record }">Rs. {{ Number(record.actual).toFixed(2) }}</template>
              </a-table-column>
              <a-table-column title="Variance" align="right">
                <template #default="{ record }">
                  <span :style="{ color: (record.planned_amount - record.actual) >= 0 ? '#3f8600' : '#cf1322' }">
                    Rs. {{ (Number(record.planned_amount) - Number(record.actual)).toFixed(2) }}
                  </span>
                </template>
              </a-table-column>
              <a-table-column title="Utilization" width="160">
                <template #default="{ record }">
                  <a-progress
                    :percent="record.planned_amount > 0 ? Math.min(100, Math.round((record.actual / record.planned_amount) * 100)) : 0"
                    size="small"
                    :status="record.actual > record.planned_amount ? 'exception' : 'active'"
                  />
                </template>
              </a-table-column>
            </a-table>

            <!-- Income Breakdown -->
            <h5 v-if="eventPnL.income_lines && eventPnL.income_lines.length > 0" style="margin: 0 0 8px 0">Income Sources</h5>
            <a-table
              v-if="eventPnL.income_lines && eventPnL.income_lines.length > 0"
              :dataSource="eventPnL.income_lines" rowKey="category_id" size="small" :pagination="false" style="margin-bottom: 16px"
            >
              <a-table-column title="Category" dataIndex="category_name" />
              <a-table-column title="Amount" dataIndex="amount" align="right">
                <template #default="{ record }">Rs. {{ Number(record.amount).toFixed(2) }}</template>
              </a-table-column>
              <a-table-column title="Share" width="200">
                <template #default="{ record }">
                  <a-progress
                    :percent="eventPnL.total_income > 0 ? Math.round((record.amount / eventPnL.total_income) * 100) : 0"
                    size="small" :showInfo="true"
                  />
                </template>
              </a-table-column>
            </a-table>

            <!-- Unbudgeted Expenses -->
            <div v-if="eventPnL.unbudgeted_expense > 0" style="color: #cf1322; font-size: 13px">
              * Includes Rs. {{ Number(eventPnL.unbudgeted_expense).toFixed(2) }} in unbudgeted expenses
            </div>
          </div>
          <div v-else style="padding: 24px; text-align: center; color: #999">Select an event to view P&L</div>
        </a-card>
      </a-tab-pane>
    </a-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { reports as reportsApi, events as eventApi, giftStock as giftApi } from '../api/index.js'
import { message } from 'ant-design-vue'
import dayjs from 'dayjs'

const dashboardData = reactive({ total_income: 0, total_expense: 0, net_balance: 0, active_events: 0 })
const incomeData = reactive({ categories: [], total_income: 0, total_expense: 0, net_income: 0 })
const contributionsData = reactive({ by_retiree: [], by_member: [], grand_total: 0 })
const eventPnL = reactive({ event: null, budget_lines: [], budget_total: 0, total_income: 0, income_lines: [], total_expense: 0, variance: 0, net_result: 0, budget_utilization: 0, unbudgeted_expense: 0 })

const globalNetBalance = ref(0)
const globalBudgeted = ref(0)
const globalProjected = computed(() => globalNetBalance.value - globalBudgeted.value)

const isDateFrom = ref(dayjs().startOf('year').format('YYYY-MM-DD'))
const isDateTo = ref(dayjs().format('YYYY-MM-DD'))
const mcDateFrom = ref(dayjs().startOf('year').format('YYYY-MM-DD'))
const mcDateTo = ref(dayjs().format('YYYY-MM-DD'))
const selectedEventId = ref(null)
const eventOptions = ref([])
const qYear = ref(new Date().getFullYear())
const qData = reactive({ year: 0, quarters: [], year_budget: 0, year_total_income: 0, year_total_expense: 0, year_variance: 0, year_net: 0 })

const rmDateFrom = ref(dayjs().startOf('year').format('YYYY-MM-DD'))
const rmDateTo = ref(dayjs().format('YYYY-MM-DD'))
const retiredMembers = ref([])
const retiredTotal = ref(0)

const abDateFrom = ref(null)
const abDateTo = ref(null)
const accountBalances = ref([])
const abTotals = reactive({ total_assets: 0, total_liabilities: 0, total_equity: 0, total_income: 0, total_expense: 0, net_income: 0 })

const ghDateFrom = ref(dayjs().startOf('year').format('YYYY-MM-DD'))
const ghDateTo = ref(dayjs().format('YYYY-MM-DD'))
const ghGiftId = ref(null)
const giftOptions = ref([])
const giftMovements = ref([])
const giftSummary = reactive({ total_received: 0, total_issued: 0, value_received: 0, value_issued: 0 })

const gniEventId = ref('all')
const gniEventOptions = ref([])
const gniEventName = ref('')
const gniNotIssuedRetirees = ref([])
const gniNotIssuedCount = ref(0)

async function fetchDashboard() {
  try {
    const res = await reportsApi.dashboard()
    Object.assign(dashboardData, res.data)
  } catch {}
}

async function fetchIncomeStatement() {
  try {
    const res = await reportsApi.incomeStatement({
      date_from: isDateFrom.value || dayjs().startOf('year').format('YYYY-MM-DD'),
      date_to: isDateTo.value || dayjs().format('YYYY-MM-DD'),
    })
    Object.assign(incomeData, res.data)
  } catch {}
}

async function fetchContributions() {
  try {
    const res = await reportsApi.memberContributions({
      date_from: mcDateFrom.value || dayjs().startOf('year').format('YYYY-MM-DD'),
      date_to: mcDateTo.value || dayjs().format('YYYY-MM-DD'),
    })
    Object.assign(contributionsData, res.data)
  } catch {}
}

async function fetchEvents() {
  try {
    const res = await eventApi.list({ per_page: 100 })
    eventOptions.value = (res.data?.items || []).map((e) => ({ label: e.name, value: e.id }))
    gniEventOptions.value = eventOptions.value
  } catch {}
}

async function fetchQuarterly() {
  try {
    const res = await reportsApi.quarterlySummary({ year: qYear.value })
    Object.assign(qData, res.data)
  } catch { message.error('Failed to load quarterly summary') }
}

async function fetchAccountBalances() {
  try {
    const params = {}
    if (abDateFrom.value) params.date_from = abDateFrom.value
    if (abDateTo.value) params.date_to = abDateTo.value
    const res = await reportsApi.accountBalances(params)
    accountBalances.value = res.data?.accounts || []
    Object.assign(abTotals, res.data?.totals || {})
  } catch { message.error('Failed to load account balances') }
}

async function fetchRetiredMembers() {
  try {
    const res = await reportsApi.retiredMembers({
      date_from: rmDateFrom.value || dayjs().startOf('year').format('YYYY-MM-DD'),
      date_to: rmDateTo.value || dayjs().format('YYYY-MM-DD'),
    })
    retiredMembers.value = res.data?.members || []
    retiredTotal.value = res.data?.total || 0
  } catch { message.error('Failed to load retired members') }
}

async function fetchGifts() {
  try {
    const res = await giftApi.list()
    giftOptions.value = (res.data || []).map((g) => ({ label: g.name, value: g.id }))
  } catch {}
}

async function fetchGiftHistory() {
  try {
    const params = {}
    if (ghDateFrom.value) params.date_from = ghDateFrom.value
    if (ghDateTo.value) params.date_to = ghDateTo.value
    if (ghGiftId.value) params.gift_id = ghGiftId.value
    const res = await reportsApi.giftHistory(params)
    giftMovements.value = res.data?.movements || []
    Object.assign(giftSummary, res.data?.summary || {})
  } catch { message.error('Failed to load gift history') }
}

async function fetchGiftNotIssued() {
  try {
    const params = {}
    if (gniEventId.value && gniEventId.value !== 'all') params.event_id = gniEventId.value
    const res = await reportsApi.giftNotIssued(params)
    gniEventName.value = res.data?.event?.name || ''
    const retirees = res.data?.event_retirees || []
    gniNotIssuedRetirees.value = retirees.filter((r) => !r.gift_issued)
    gniNotIssuedCount.value = res.data?.not_issued_count || 0
  } catch { message.error('Failed to load gift not issued report') }
}

async function fetchGlobalBalances() {
  try {
    const [dashRes, evtRes] = await Promise.all([
      reportsApi.dashboard({ year: new Date().getFullYear() }),
      eventApi.list({ status: 'active', per_page: 100 }),
    ])
    globalNetBalance.value = dashRes.data?.net_balance || 0
    const activeEvents = evtRes.data?.items || []
    globalBudgeted.value = activeEvents.reduce((sum, e) => sum + Number(e.budget_allocated || 0), 0)
  } catch {}
}

async function fetchEventPnL() {
  if (!selectedEventId.value) return
  try {
    const res = await reportsApi.eventProfitLoss(selectedEventId.value)
    Object.assign(eventPnL, res.data)
  } catch { message.error('Failed to load event P&L') }
}

function exportCSV(data, filename) {
  if (!data || data.length === 0) { message.warning('No data to export'); return }
  const headers = Object.keys(data[0])
  const csvContent = [
    headers.join(','),
    ...data.map((row) => headers.map((h) => `"${row[h] ?? ''}"`).join(',')),
  ].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = filename; a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => {
  fetchDashboard()
  fetchIncomeStatement()
  fetchContributions()
  fetchEvents()
  fetchQuarterly()
  fetchRetiredMembers()
  fetchGlobalBalances()
  fetchAccountBalances()
  fetchGifts()
  fetchGiftHistory()
  fetchGiftNotIssued()
})
</script>
