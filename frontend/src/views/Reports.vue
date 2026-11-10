<template>
  <div>
    <a-page-header title="Reports" sub-title="Financial summaries for board members" />

    <!-- Global Summary Cards -->
    <a-row :gutter="[16, 16]" style="margin-bottom: 20px">
      <a-col :span="8">
        <a-card size="small" :bordered="true" :body-style="{ padding: '16px 20px' }" :style="{ borderTop: '3px solid ' + (globalNetBalance >= 0 ? '#52c41a' : '#ff4d4f') }">
          <div style="display: flex; align-items: center; gap: 12px">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center" :style="{ background: globalNetBalance >= 0 ? '#f6ffed' : '#fff2f0' }">
              <AccountBookOutlined :style="{ fontSize: '18px', color: globalNetBalance >= 0 ? '#52c41a' : '#ff4d4f' }" />
            </div>
            <a-statistic title="Current Net Balance" :value="globalNetBalance" prefix="Rs." :precision="2" :value-style="{ color: globalNetBalance >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600, fontSize: '18px' }" />
          </div>
        </a-card>
      </a-col>
      <a-col :span="8">
        <a-card size="small" :bordered="true" :body-style="{ padding: '16px 20px' }" :style="{ borderTop: '3px solid #1890ff' }">
          <div style="display: flex; align-items: center; gap: 12px">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: #e6f7ff; display: flex; align-items: center; justify-content: center">
              <FundOutlined style="font-size: 18px; color: #1890ff" />
            </div>
            <a-statistic title="Total Budgeted (Active)" :value="globalBudgeted" prefix="Rs." :precision="2" :value-style="{ fontWeight: 600, fontSize: '18px' }" />
          </div>
        </a-card>
      </a-col>
      <a-col :span="8">
        <a-card size="small" :bordered="true" :body-style="{ padding: '16px 20px' }" :style="{ borderTop: '3px solid ' + (globalProjected >= 0 ? '#52c41a' : '#ff4d4f') }">
          <div style="display: flex; align-items: center; gap: 12px">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center" :style="{ background: globalProjected >= 0 ? '#f6ffed' : '#fff2f0' }">
              <RiseOutlined :style="{ fontSize: '18px', color: globalProjected >= 0 ? '#52c41a' : '#ff4d4f' }" />
            </div>
            <a-statistic title="Projected Balance" :value="globalProjected" prefix="Rs." :precision="2" :value-style="{ color: globalProjected >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600, fontSize: '18px' }" />
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- Two-column layout: Sidebar + Content -->
    <a-row :gutter="[16, 0]">
      <!-- Sidebar Navigation -->
      <a-col :span="5">
        <a-card :body-style="{ padding: '8px' }" size="small">
          <div v-for="group in navGroups" :key="group.label" style="margin-bottom: 4px">
            <div style="padding: 8px 12px 4px; font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600">{{ group.label }}</div>
            <div
              v-for="item in group.items"
              :key="item.key"
              @click="activeKey = item.key"
              :style="{
                display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer', fontSize: '13px', transition: 'all 0.2s',
                background: activeKey === item.key ? '#e6f7ff' : 'transparent',
                color: activeKey === item.key ? '#1890ff' : '#595959',
                fontWeight: activeKey === item.key ? 600 : 400,
              }"
            >
              <component :is="item.icon" :style="{ fontSize: '15px' }" />
              <span>{{ item.label }}</span>
            </div>
          </div>
        </a-card>
      </a-col>

      <!-- Content Area -->
      <a-col :span="19">
      <!-- Dashboard Tab -->
      <div v-show="activeKey === 'dashboard'">
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
      </div>

      <!-- Income Statement -->
      <div v-show="activeKey === 'income'">
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
      </div>

      <!-- Member Contributions -->
      <div v-show="activeKey === 'contributions'">
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
      </div>

      <!-- Quarterly Summary -->
      <div v-show="activeKey === 'quarterly'">
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
      </div>

      <!-- Retired Members Report -->
      <div v-show="activeKey === 'retired-members'">
        <a-card>
          <template #extra>
            <a-space wrap>
              <a-input
                v-model:value="rmSearch"
                placeholder="Search name / NIC / phone"
                allow-clear
                style="width: 240px"
                @pressEnter="fetchRetiredMembers"
              >
                <template #prefix><SearchOutlined style="color: #bfbfbf" /></template>
              </a-input>
              <a-date-picker v-model:value="rmDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="rmDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-select v-model:value="rmStatus" style="width: 150px" :options="rmStatusOptions" />
              <a-select
                v-model:value="rmDesignation"
                placeholder="All designations"
                style="width: 190px"
                allow-clear
                show-search
                option-filter-prop="label"
                :options="rmDesignationOptions"
              />
              <a-button type="primary" :loading="rmLoading" @click="fetchRetiredMembers">Search</a-button>
              <a-button @click="resetRetiredFilters">Reset</a-button>
              <a-button type="primary" ghost :loading="rmExporting" :disabled="retiredMembers.length === 0" @click="exportRetiredMembers(false)">
                <template #icon><DownloadOutlined /></template>
                Export Filtered
              </a-button>
              <a-button :loading="rmExporting" @click="exportRetiredMembers(true)">
                <template #icon><DownloadOutlined /></template>
                Export All Dates
              </a-button>
            </a-space>
          </template>

          <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
            <a-col :span="6">
              <a-statistic title="Retired Members" :value="retiredTotal" suffix="members" :value-style="{ color: '#1890ff', fontSize: '20px' }" />
            </a-col>
            <a-col :span="6">
              <a-statistic title="With Phone" :value="retiredSummary.with_phone" :value-style="{ color: '#13c2c2', fontSize: '20px' }" />
            </a-col>
            <a-col :span="6">
              <a-statistic title="With Address" :value="retiredSummary.with_address" :value-style="{ color: '#722ed1', fontSize: '20px' }" />
            </a-col>
            <a-col :span="6">
              <a-statistic title="Gift Issued" :value="retiredSummary.with_gift" :suffix="`/ ${retiredTotal}`" :value-style="{ color: '#52c41a', fontSize: '20px' }" />
            </a-col>
          </a-row>

          <div v-if="retiredDesignationBreakdown.length" style="margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
            <span style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.6px; margin-right: 4px">By designation</span>
            <a-tag v-for="d in retiredDesignationBreakdown.slice(0, 12)" :key="d.designation" color="blue" style="margin: 0">
              {{ d.designation }} <strong>{{ d.count }}</strong>
            </a-tag>
            <a-tooltip v-if="retiredDesignationBreakdown.length > 12" :title="retiredDesignationBreakdown.slice(12).map(d => d.designation + ' (' + d.count + ')').join(', ')">
              <a-tag style="margin: 0; cursor: help">+{{ retiredDesignationBreakdown.length - 12 }} more</a-tag>
            </a-tooltip>
          </div>

          <div style="margin-bottom: 10px; font-size: 12px; color: #8c8c8c">
            Showing <strong>{{ retiredMembers.length }}</strong> member(s)
            with retirement dates {{ rmDateFrom || 'beginning' }} → {{ rmDateTo || 'today' }}
            <span v-if="rmDesignation"> · designation: {{ rmDesignation }}</span>
            <span v-if="rmStatus && rmStatus !== 'all'"> · status: {{ rmStatus }}</span>
            <span v-if="rmSearch"> · search: “{{ rmSearch }}”</span>
          </div>

          <a-table
            :dataSource="retiredMembers"
            rowKey="id"
            size="small"
            :loading="rmLoading"
            :scroll="{ x: 1620 }"
            :pagination="{ pageSize: 20, showSizeChanger: true, pageSizeOptions: ['10', '20', '50', '100'], showTotal: (t) => `${t} members` }"
          >
            <a-table-column title="#" key="rowNo" width="56" align="right" fixed="left">
              <template #default="{ index }">{{ index + 1 }}</template>
            </a-table-column>
            <a-table-column title="Name" dataIndex="name" width="210" fixed="left" :ellipsis="{ showTitle: true }" :sorter="(a, b) => a.name.localeCompare(b.name)" />
            <a-table-column title="NIC" dataIndex="nic" width="125" />
            <a-table-column title="Service No" dataIndex="service_no" width="110" />
            <a-table-column title="Designation" dataIndex="designation" width="200" :ellipsis="{ showTitle: true }">
              <template #default="{ record }">{{ record.designation || '-' }}</template>
            </a-table-column>
            <a-table-column title="Computer No" dataIndex="computer_no" width="110" />
            <a-table-column title="Retirement Date" dataIndex="retirement_date" width="135" :sorter="(a, b) => (a.retirement_date || '').localeCompare(b.retirement_date || '')" />
            <a-table-column title="Years Retired" dataIndex="years_retired" width="110" align="center" :sorter="(a, b) => (a.years_retired ?? -1) - (b.years_retired ?? -1)">
              <template #default="{ record }">{{ record.years_retired ?? '-' }}</template>
            </a-table-column>
            <a-table-column title="Status" dataIndex="status" width="100">
              <template #default="{ record }">
                <a-tag :color="memberStatusColor(record.status)" style="text-transform: capitalize">{{ record.status }}</a-tag>
              </template>
            </a-table-column>
            <a-table-column title="Phone" dataIndex="phone" width="130">
              <template #default="{ record }">{{ record.phone || '-' }}</template>
            </a-table-column>
            <a-table-column title="Address" dataIndex="address" width="240" :ellipsis="{ showTitle: true }">
              <template #default="{ record }">{{ record.address || '-' }}</template>
            </a-table-column>
            <a-table-column title="Gift" dataIndex="gift_count" width="90" align="center">
              <template #default="{ record }">
                <a-tag :color="record.gift_count > 0 ? 'green' : 'default'">{{ record.gift_count > 0 ? 'Issued' : 'No' }}</a-tag>
              </template>
            </a-table-column>
          </a-table>
        </a-card>
      </div>

      <!-- Gift History Report -->
      <div v-show="activeKey === 'gift-history'">
        <a-card>
          <template #extra>
            <a-space>
              <a-date-picker v-model:value="ghDateFrom" placeholder="From" value-format="YYYY-MM-DD" />
              <a-date-picker v-model:value="ghDateTo" placeholder="To" value-format="YYYY-MM-DD" />
              <a-select v-model:value="ghGiftId" placeholder="All gifts" style="width: 200px" allow-clear :options="giftOptions" />
              <a-button type="primary" @click="fetchGiftHistory">Search</a-button>
              <a-button type="primary" ghost :disabled="giftMovements.length === 0" @click="exportGiftHistoryExcel">
                <template #icon><FileExcelOutlined /></template>
                Export Excel
              </a-button>
              <a-button :disabled="giftMovements.length === 0" @click="exportCSV(giftMovements, 'gift-history.csv')">CSV</a-button>
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
            <a-table-column title="Unit" dataIndex="unit" width="80" />
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
      </div>

      <!-- Gift Not Issued Report -->
      <div v-show="activeKey === 'gift-not-issued'">
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
      </div>

      <!-- Account Balances -->
      <div v-show="activeKey === 'account-balances'">
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
      </div>

      <!-- Event P&L -->
      <div v-show="activeKey === 'event-pnl'">
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
                <a-statistic title="Budget" :value="eventPnL.budget_total" prefix="Rs." :precision="2" />
              </a-col>
              <a-col :span="6">
                <a-statistic title="Total Income" :value="eventPnL.total_income" prefix="Rs." :precision="2" :value-style="{ color: '#3f8600' }" />
              </a-col>
              <a-col :span="6">
                <a-statistic title="Total Expense" :value="eventPnL.total_expense" prefix="Rs." :precision="2" :value-style="{ color: eventPnL.total_expense > eventPnL.budget_total ? '#cf1322' : 'inherit' }" />
              </a-col>
              <a-col :span="6">
                <a-statistic title="Net Result" :value="eventPnL.net_result" prefix="Rs." :precision="2" :value-style="{ color: (eventPnL.net_result || 0) >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
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
      </div>

      <!-- Budget Planning Tab -->
      <div v-show="activeKey === 'planning'">
        <a-card>
          <template #extra>
            <a-space>
              <a-select v-model:value="planningYear" style="width: 120px" @change="fetchPlanningData">
                <a-select-option v-for="y in planningYears" :key="y" :value="y">{{ y }}</a-select-option>
              </a-select>
              <a-button type="primary" @click="fetchPlanningData">Refresh</a-button>
            </a-space>
          </template>

          <a-row :gutter="[16, 16]" style="margin-bottom: 20px">
            <a-col :span="6">
              <a-card size="small" :bordered="true" :body-style="{ padding: '12px 16px' }" :style="{ borderTop: '3px solid ' + (planningNetBalance >= 0 ? '#3f8600' : '#cf1322') }">
                <a-statistic title="Net Balance" :value="planningNetBalance" prefix="Rs." :precision="2" :value-style="{ color: planningNetBalance >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
              </a-card>
            </a-col>
            <a-col :span="6">
              <a-card size="small" :bordered="true" :body-style="{ padding: '12px 16px' }">
                <a-statistic title="Total Budgeted" :value="planningTotalBudgeted" prefix="Rs." :precision="2" />
              </a-card>
            </a-col>
            <a-col :span="6">
              <a-card size="small" :bordered="true" :body-style="{ padding: '12px 16px' }">
                <a-statistic title="Total Spent" :value="planningTotalSpent" prefix="Rs." :precision="2" :value-style="{ color: planningTotalSpent > planningTotalBudgeted ? '#cf1322' : 'inherit' }" />
              </a-card>
            </a-col>
            <a-col :span="6">
              <a-card size="small" :bordered="true" :body-style="{ padding: '12px 16px' }" :style="{ borderTop: '3px solid ' + (planningAvailable >= 0 ? '#3f8600' : '#cf1322') }">
                <a-statistic title="Available" :value="planningAvailable" prefix="Rs." :precision="2" :value-style="{ color: planningAvailable >= 0 ? '#3f8600' : '#cf1322', fontWeight: 600 }" />
              </a-card>
            </a-col>
          </a-row>

          <a-table :dataSource="planningEvents" rowKey="id" size="small" :pagination="false">
            <a-table-column title="Event" dataIndex="name" ellipsis />
            <a-table-column title="Quarter" dataIndex="quarter" width="100" align="center" />
            <a-table-column title="Status" dataIndex="status" width="110">
              <template #default="{ record }">
                <a-tag :color="statusColor(record.status)">{{ record.status }}</a-tag>
              </template>
            </a-table-column>
            <a-table-column title="Budget" dataIndex="budget_allocated" align="right" width="130">
              <template #default="{ record }">Rs. {{ Number(record.budget_allocated || 0).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Spent" dataIndex="total_expense" align="right" width="130">
              <template #default="{ record }">Rs. {{ Number(record.total_expense || 0).toFixed(2) }}</template>
            </a-table-column>
            <a-table-column title="Remaining" width="130" align="right">
              <template #default="{ record }">
                <span :style="{ color: (record.budget_allocated - (record.total_expense || 0)) >= 0 ? '#3f8600' : '#cf1322' }">
                  Rs. {{ Number(record.budget_allocated - (record.total_expense || 0)).toFixed(2) }}
                </span>
              </template>
            </a-table-column>
            <a-table-column title="Utilization" width="120" align="center">
              <template #default="{ record }">
                <a-progress :percent="record.budget_allocated > 0 ? Math.min(Math.round(((record.total_expense || 0) / record.budget_allocated) * 100), 100) : 0" size="small" :status="(record.total_expense || 0) > record.budget_allocated ? 'exception' : 'active'" />
              </template>
            </a-table-column>
          </a-table>

          <a-divider />
          <a-row :gutter="16">
            <a-col :span="8"><strong>Net Balance:</strong> Rs. {{ Number(planningNetBalance).toFixed(2) }}</a-col>
            <a-col :span="8"><strong>Total Budgeted:</strong> Rs. {{ Number(planningTotalBudgeted).toFixed(2) }}</a-col>
            <a-col :span="8"><strong>Projected After Budget:</strong> <span :style="{ color: planningAvailable >= 0 ? '#3f8600' : '#cf1322' }">Rs. {{ Number(planningAvailable).toFixed(2) }}</span></a-col>
          </a-row>
        </a-card>
      </div>
      </a-col>
    </a-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, markRaw } from 'vue'
import { reports as reportsApi, events as eventApi, giftStock as giftApi } from '../api/index.js'
import { message } from 'ant-design-vue'
import {
  DashboardOutlined, DollarOutlined, TeamOutlined, GiftOutlined,
  AccountBookOutlined, FundOutlined, RiseOutlined, PieChartOutlined,
  BarChartOutlined, FileTextOutlined, UserDeleteOutlined, InboxOutlined,
  SwapOutlined, WalletOutlined, CalendarOutlined, AuditOutlined,
  SearchOutlined, DownloadOutlined,
} from '@ant-design/icons-vue'
import dayjs from 'dayjs'
import * as XLSX from 'xlsx'

// Avoid binary floating-point artefacts (e.g. -1473395.5699999998) in money totals
const round2 = (value) => Math.round((Number(value) || 0) * 100) / 100

const activeKey = ref('dashboard')
const navGroups = [
  {
    label: 'Financial',
    items: [
      { key: 'dashboard', label: 'Dashboard', icon: markRaw(DashboardOutlined) },
      { key: 'income', label: 'Income Statement', icon: markRaw(DollarOutlined) },
      { key: 'account-balances', label: 'Account Balances', icon: markRaw(WalletOutlined) },
      { key: 'quarterly', label: 'Quarterly Summary', icon: markRaw(BarChartOutlined) },
    ],
  },
  {
    label: 'Operations',
    items: [
      { key: 'event-pnl', label: 'Event P&L', icon: markRaw(PieChartOutlined) },
      { key: 'planning', label: 'Budget Planning', icon: markRaw(FundOutlined) },
    ],
  },
  {
    label: 'Members',
    items: [
      { key: 'contributions', label: 'Contributions', icon: markRaw(TeamOutlined) },
      { key: 'retired-members', label: 'Retired Members', icon: markRaw(UserDeleteOutlined) },
    ],
  },
  {
    label: 'Gifts',
    items: [
      { key: 'gift-history', label: 'Gift History', icon: markRaw(GiftOutlined) },
      { key: 'gift-not-issued', label: 'Not Issued', icon: markRaw(InboxOutlined) },
    ],
  },
]

const dashboardData = reactive({ total_income: 0, total_expense: 0, net_balance: 0, active_events: 0 })
const incomeData = reactive({ categories: [], total_income: 0, total_expense: 0, net_income: 0 })
const contributionsData = reactive({ by_retiree: [], by_member: [], grand_total: 0 })
const eventPnL = reactive({ event: null, budget_lines: [], budget_total: 0, total_income: 0, income_lines: [], total_expense: 0, variance: 0, net_result: 0, budget_utilization: 0, unbudgeted_expense: 0 })

const globalNetBalance = ref(0)
const globalBudgeted = ref(0)
const globalProjected = computed(() => round2(globalNetBalance.value - globalBudgeted.value))

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
const rmSearch = ref('')
const rmStatus = ref('all')
const rmDesignation = ref(undefined)
const rmDesignationOptions = ref([])
const retiredMembers = ref([])
const retiredTotal = ref(0)
const retiredSummary = reactive({ total: 0, with_phone: 0, with_address: 0, with_gift: 0, without_gift: 0 })
const retiredDesignationBreakdown = ref([])
const rmLoading = ref(false)
const rmExporting = ref(false)

const rmStatusOptions = [
  { label: 'All statuses', value: 'all' },
  { label: 'Retired', value: 'retired' },
  { label: 'Active', value: 'active' },
  { label: 'Deceased', value: 'deceased' },
  { label: 'Resigned', value: 'resigned' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Dismissed', value: 'dismissed' },
]

const RM_EXPORT_FIELDS = [
  { key: 'name', label: 'Name' },
  { key: 'nic', label: 'NIC' },
  { key: 'service_no', label: 'Service No' },
  { key: 'designation', label: 'Designation' },
  { key: 'computer_no', label: 'Computer No' },
  { key: 'retirement_date', label: 'Retirement Date' },
  { key: 'years_retired', label: 'Years Retired' },
  { key: 'status', label: 'Status' },
  { key: 'phone', label: 'Phone' },
  { key: 'address', label: 'Address' },
  { key: 'gift_count', label: 'Gifts Issued' },
  { key: 'created_at', label: 'Recorded On', value: (r) => (r.created_at ? dayjs(r.created_at).format('YYYY-MM-DD') : '') },
]

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

const planningYear = ref(new Date().getFullYear())
const planningYears = computed(() => {
  const years = new Set()
  eventOptions.value.forEach((e) => { if (e.year) years.add(e.year) })
  const cy = new Date().getFullYear()
  for (let y = cy - 5; y <= cy + 3; y++) years.add(y)
  return [...years].sort((a, b) => b - a)
})
const planningEvents = ref([])
const planningNetBalance = ref(0)
const planningTotalBudgeted = computed(() => round2(planningEvents.value.reduce((s, e) => s + Number(e.budget_allocated || 0), 0)))
const planningTotalSpent = computed(() => round2(planningEvents.value.reduce((s, e) => s + Number(e.total_expense || 0), 0)))
const planningAvailable = computed(() => round2(planningNetBalance.value - planningTotalBudgeted.value))

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
  rmLoading.value = true
  try {
    const res = await reportsApi.retiredMembers({
      date_from: rmDateFrom.value || undefined,
      date_to: rmDateTo.value || undefined,
      search: rmSearch.value || undefined,
      status: rmStatus.value && rmStatus.value !== 'all' ? rmStatus.value : undefined,
      designation: rmDesignation.value || undefined,
    })
    retiredMembers.value = res.data?.members || []
    retiredTotal.value = res.data?.total || 0
    Object.assign(retiredSummary, res.data?.summary || {})
    retiredDesignationBreakdown.value = res.data?.designation_breakdown || []
    rmDesignationOptions.value = (res.data?.designations || []).map((d) => ({
      label: `${d.designation} (${d.total})`,
      value: d.designation,
    }))
  } catch { message.error('Failed to load retired members') }
  finally { rmLoading.value = false }
}

function resetRetiredFilters() {
  rmSearch.value = ''
  rmStatus.value = 'all'
  rmDesignation.value = undefined
  rmDateFrom.value = dayjs().startOf('year').format('YYYY-MM-DD')
  rmDateTo.value = dayjs().format('YYYY-MM-DD')
  fetchRetiredMembers()
}

function memberStatusColor(s) {
  return { active: 'green', retired: 'orange', deceased: 'red', resigned: 'purple', inactive: 'default', dismissed: 'red' }[s] || 'default'
}

async function exportRetiredMembers(allDates) {
  rmExporting.value = true
  try {
    let rows = retiredMembers.value
    if (allDates) {
      // Same search / status / designation filters, but no date restriction
      const res = await reportsApi.retiredMembers({
        search: rmSearch.value || undefined,
        status: rmStatus.value && rmStatus.value !== 'all' ? rmStatus.value : undefined,
        designation: rmDesignation.value || undefined,
      })
      rows = res.data?.members || []
    }
    if (!rows.length) { message.warning('No data to export'); return }
    const stamp = allDates ? 'all-dates' : `${rmDateFrom.value || 'start'}_to_${rmDateTo.value || 'today'}`
    exportRows(rows, RM_EXPORT_FIELDS, `retired-members-${stamp}.csv`)
    message.success(`Exported ${rows.length} member(s) with full details`)
  } catch { message.error('Export failed') }
  finally { rmExporting.value = false }
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
    const movements = res.data?.movements || []
    const seen = new Set()
    const unique = movements.filter((r) => {
      if (seen.has(r.id)) return false
      seen.add(r.id)
      return true
    })
    giftMovements.value = unique
    giftSummary.total_received = unique.filter(r => r.movement_type === 'received').reduce((s, r) => s + Number(r.quantity), 0)
    giftSummary.total_issued = unique.filter(r => r.movement_type === 'issued').reduce((s, r) => s + Number(r.quantity), 0)
    giftSummary.value_received = unique.filter(r => r.movement_type === 'received').reduce((s, r) => s + Number(r.total_value), 0)
    giftSummary.value_issued = unique.filter(r => r.movement_type === 'issued').reduce((s, r) => s + Number(r.total_value), 0)
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

async function fetchPlanningData() {
  try {
    const [dashRes, evtRes] = await Promise.all([
      reportsApi.dashboard({ year: planningYear.value }),
      eventApi.list({ year: planningYear.value, per_page: 100 }),
    ])
    planningNetBalance.value = dashRes.data?.net_balance || 0
    planningEvents.value = evtRes.data?.items || []
  } catch { message.error('Failed to load planning data') }
}

function statusColor(s) {
  return { planned: 'blue', active: 'green', completed: 'default', cancelled: 'red' }[s] || 'default'
}

async function fetchGlobalBalances() {
  try {
    const [dashRes, evtRes] = await Promise.all([
      reportsApi.dashboard({ year: new Date().getFullYear() }),
      eventApi.list({ status: 'active', per_page: 100 }),
    ])
    globalNetBalance.value = dashRes.data?.net_balance || 0
    const activeEvents = evtRes.data?.items || []
    globalBudgeted.value = round2(activeEvents.reduce((sum, e) => sum + Number(e.budget_allocated || 0), 0))
  } catch {}
}

async function fetchEventPnL() {
  if (!selectedEventId.value) return
  try {
    const res = await reportsApi.eventProfitLoss(selectedEventId.value)
    Object.assign(eventPnL, res.data)
  } catch { message.error('Failed to load event P&L') }
}

function csvEscape(value) {
  if (value === null || value === undefined) return '""'
  return '"' + String(value).replace(/"/g, '""') + '"'
}

function downloadCsv(lines, filename) {
  // Prepend a UTF-8 BOM so Excel renders non-ASCII names correctly
  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = filename; a.click()
  URL.revokeObjectURL(url)
}

function exportCSV(data, filename) {
  if (!data || data.length === 0) { message.warning('No data to export'); return }
  const headers = Object.keys(data[0])
  const lines = [
    headers.map(csvEscape).join(','),
    ...data.map((row) => headers.map((h) => csvEscape(row[h])).join(',')),
  ]
  downloadCsv(lines, filename)
}

// Export rows with explicit, friendly column labels (and optional value formatters)
function exportRows(rows, fields, filename) {
  if (!rows || rows.length === 0) { message.warning('No data to export'); return }
  const lines = [
    fields.map((f) => csvEscape(f.label)).join(','),
    ...rows.map((row) => fields.map((f) => csvEscape(typeof f.value === 'function' ? f.value(row) : row[f.key])).join(',')),
  ]
  downloadCsv(lines, filename)
}

// ---------------------------------------------------------------
// Gift History — Excel (.xlsx) export with a data sheet + summary
// ---------------------------------------------------------------
function exportGiftHistoryExcel() {
  const movements = giftMovements.value
  if (!movements || movements.length === 0) { message.warning('No data to export'); return }

  const rows = movements.map((r) => ({
    'Date': r.created_at ? dayjs(r.created_at).toDate() : '',
    'Gift': r.gift_name || '',
    'Unit': r.unit || '',
    'Type': r.movement_type === 'received' ? 'Received' : 'Issued',
    'Quantity': Number(r.quantity) || 0,
    'Unit Price (Rs.)': Number(r.unit_price) || 0,
    'Total Value (Rs.)': Number(r.total_value) || 0,
    'Member': r.member_name || '',
    'Event': r.event_name || '',
    'Received Date': r.received_date ? dayjs(r.received_date).toDate() : '',
    'Recorded By': r.created_by_name || '',
    'Notes': r.notes || '',
  }))

  const ws = XLSX.utils.json_to_sheet(rows)
  const headers = Object.keys(rows[0])
  const colWidths = {
    'Date': 12, 'Gift': 30, 'Unit': 9, 'Type': 10, 'Quantity': 9,
    'Unit Price (Rs.)': 15, 'Total Value (Rs.)': 16, 'Member': 32,
    'Event': 28, 'Received Date': 13, 'Recorded By': 22, 'Notes': 45,
  }
  ws['!cols'] = headers.map((h) => ({ wch: colWidths[h] || 16 }))
  ws['!autofilter'] = {
    ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: rows.length, c: headers.length - 1 } }),
  }

  // Currency / quantity / date number formats
  const qtyCol = headers.indexOf('Quantity')
  const priceCol = headers.indexOf('Unit Price (Rs.)')
  const totalCol = headers.indexOf('Total Value (Rs.)')
  const dateCol = headers.indexOf('Date')
  const receivedCol = headers.indexOf('Received Date')
  const range = XLSX.utils.decode_range(ws['!ref'])
  for (let row = 1; row <= range.e.r; row++) {
    const qty = ws[XLSX.utils.encode_cell({ r: row, c: qtyCol })]
    if (qty) qty.z = '#,##0'
    const price = ws[XLSX.utils.encode_cell({ r: row, c: priceCol })]
    if (price) price.z = '#,##0.00'
    const total = ws[XLSX.utils.encode_cell({ r: row, c: totalCol })]
    if (total) total.z = '#,##0.00'
    const dt = ws[XLSX.utils.encode_cell({ r: row, c: dateCol })]
    if (dt) dt.z = 'yyyy-mm-dd'
    const rd = ws[XLSX.utils.encode_cell({ r: row, c: receivedCol })]
    if (rd) rd.z = 'yyyy-mm-dd'
  }

  const giftLabel = ghGiftId.value
    ? (giftOptions.value.find((g) => g.value === ghGiftId.value)?.label || 'Selected gift')
    : 'All gifts'

  const summary = [
    ['Gift History Report'],
    [],
    ['Date From', ghDateFrom.value || 'All'],
    ['Date To', ghDateTo.value || 'All'],
    ['Gift', giftLabel],
    ['Generated', dayjs().format('YYYY-MM-DD HH:mm')],
    [],
    ['Total Received (units)', Number(giftSummary.total_received) || 0],
    ['Total Issued (units)', Number(giftSummary.total_issued) || 0],
    ['Received Value (Rs.)', Number(giftSummary.value_received) || 0],
    ['Issued Value (Rs.)', Number(giftSummary.value_issued) || 0],
    ['Movements', rows.length],
  ]
  const wsSummary = XLSX.utils.aoa_to_sheet(summary)
  wsSummary['!cols'] = [{ wch: 26 }, { wch: 32 }]
  // Format the money values in the summary
  for (const r of [9, 10]) {
    const cell = wsSummary[XLSX.utils.encode_cell({ r, c: 1 })]
    if (cell) cell.z = '#,##0.00'
  }

  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Gift History')
  XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary')
  XLSX.writeFile(wb, `gift-history-${ghDateFrom.value || 'start'}_to_${ghDateTo.value || 'today'}.xlsx`)
  message.success(`Exported ${rows.length} movement(s) to Excel`)
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
  fetchPlanningData()
})
</script>
