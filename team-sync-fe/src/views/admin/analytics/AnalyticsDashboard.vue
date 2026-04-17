<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAnalyticsStore } from '@/stores/analytics'
import { useTeamStore } from '@/stores/team'
import { useOptionStore } from '@/stores/option'
import StatsCard from '@/components/common/StatsCard.vue'
import MainCard from '@/components/common/MainCard.vue'
import AttendanceAnalytics from '@/components/admin/analytics/AttendanceAnalytics.vue'
import WorkforceAnalytics from '@/components/admin/analytics/WorkforceAnalytics.vue'
import LeaveAnalytics from '@/components/admin/analytics/LeaveAnalytics.vue'
import PayrollAnalytics from '@/components/admin/analytics/PayrollAnalytics.vue'
import ProjectAnalytics from '@/components/admin/analytics/ProjectAnalytics.vue'
import { formatRupiahCompact, formatRupiah } from '@/utils/formatUtils'
import { can } from '@/helpers/permissionHelper'
import {
  BarChart3Icon,
  UsersIcon,
  CalendarCheckIcon,
  WalletIcon,
  FolderKanbanIcon,
  PalmtreeIcon,
  TrendingUpIcon,
  TrendingDownIcon,
  FilterIcon,
  DownloadIcon,
  FileSpreadsheetIcon,
  FileTextIcon,
} from 'lucide-vue-next'

const exportLoading = ref(false)
const showExportMenu = ref(false)

async function handleExportExcel() {
  exportLoading.value = true
  showExportMenu.value = false
  await analyticsStore.exportExcel(activeTab.value)
  exportLoading.value = false
}

async function handleExportPdf() {
  exportLoading.value = true
  showExportMenu.value = false
  await analyticsStore.exportPdf(activeTab.value)
  exportLoading.value = false
}

const analyticsStore = useAnalyticsStore()
const teamStore = useTeamStore()
const optionStore = useOptionStore()
const { executiveSummary, executiveSummaryLoading, period, department, teamId } = storeToRefs(analyticsStore)

const activeTab = ref('executive')

const tabs = [
  { id: 'executive', label: 'Executive Summary', icon: BarChart3Icon },
  { id: 'workforce', label: 'Workforce', icon: UsersIcon },
  { id: 'attendance', label: 'Attendance', icon: CalendarCheckIcon },
  { id: 'leave', label: 'Leave', icon: PalmtreeIcon },
  { id: 'payroll', label: 'Payroll', icon: WalletIcon },
  { id: 'projects', label: 'Projects', icon: FolderKanbanIcon },
]

const periodOptions = [
  { value: '3m', label: 'Last 3 Months' },
  { value: '6m', label: 'Last 6 Months' },
  { value: '12m', label: 'Last 12 Months' },
  { value: 'ytd', label: 'Year to Date' },
]

const departments = computed(() => optionStore.departments || [])
const teams = computed(() => teamStore.teamsAll || [])

const kpis = computed(() => executiveSummary.value?.kpis || {})

// Chart options for Attendance vs Deduction trend
const attendanceDeductionOptions = computed(() => ({
  chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  stroke: { width: [3, 3], curve: 'smooth' },
  colors: ['#0C51D9', '#ef4444'],
  xaxis: {
    categories: (executiveSummary.value?.attendance_vs_deduction_trend || []).map(d => d.month),
    labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
  },
  yaxis: [
    {
      title: { text: 'Attendance Rate (%)', style: { color: '#0C51D9', fontSize: '12px' } },
      labels: { formatter: v => `${v}%`, style: { colors: '#94a3b8' } },
      min: 0, max: 100,
    },
    {
      opposite: true,
      title: { text: 'Total Deductions', style: { color: '#ef4444', fontSize: '12px' } },
      labels: { formatter: v => formatRupiahCompact(v), style: { colors: '#94a3b8' } },
    },
  ],
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  legend: { position: 'top', horizontalAlign: 'left', fontSize: '13px', markers: { radius: 3 } },
  tooltip: {
    shared: true,
    y: { formatter: (v, { seriesIndex }) => seriesIndex === 0 ? `${v}%` : formatRupiah(v) },
  },
  dataLabels: { enabled: false },
}))

const attendanceDeductionSeries = computed(() => {
  const data = executiveSummary.value?.attendance_vs_deduction_trend || []
  return [
    { name: 'Attendance Rate', data: data.map(d => d.attendance_rate) },
    { name: 'Total Deductions', data: data.map(d => d.total_deductions) },
  ]
})

// Chart options for Monthly HR Cost
const hrCostOptions = computed(() => ({
  chart: { type: 'area', height: 320, stacked: true, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  stroke: { width: 2, curve: 'smooth' },
  colors: ['#0C51D9', '#f59e0b', '#10b981', '#ef4444'],
  fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.1 } },
  xaxis: {
    categories: (executiveSummary.value?.monthly_hr_cost || []).map(d => d.month),
    labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
  },
  yaxis: {
    labels: { formatter: v => formatRupiahCompact(v), style: { colors: '#94a3b8' } },
  },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  legend: { position: 'top', horizontalAlign: 'left', fontSize: '13px', markers: { radius: 3 } },
  tooltip: { y: { formatter: v => formatRupiah(v) } },
  dataLabels: { enabled: false },
}))

const hrCostSeries = computed(() => {
  const data = executiveSummary.value?.monthly_hr_cost || []
  return [
    { name: 'Salary', data: data.map(d => d.salary) },
    { name: 'Tax (PPh21)', data: data.map(d => d.tax) },
    { name: 'BPJS', data: data.map(d => d.bpjs) },
    { name: 'Deductions', data: data.map(d => d.deductions) },
  ]
})

// Chart options for Team Performance
const teamPerformanceOptions = computed(() => ({
  chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 6 } },
  colors: ['#0C51D9', '#10b981'],
  xaxis: {
    categories: (executiveSummary.value?.team_performance || []).map(t => t.team_name),
    labels: { style: { colors: '#94a3b8', fontSize: '12px' } },
  },
  yaxis: {
    labels: { formatter: v => `${v}%`, style: { colors: '#94a3b8' } },
    max: 100,
  },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  legend: { position: 'top', horizontalAlign: 'left', fontSize: '13px', markers: { radius: 3 } },
  tooltip: { y: { formatter: v => `${v}%` } },
  dataLabels: { enabled: false },
}))

const teamPerformanceSeries = computed(() => {
  const data = executiveSummary.value?.team_performance || []
  return [
    { name: 'Attendance Rate', data: data.map(t => t.attendance_rate) },
    { name: 'Task Completion', data: data.map(t => t.task_completion) },
  ]
})

function onFilterChange() {
  analyticsStore.setFilters({
    period: period.value,
    department: department.value,
    teamId: teamId.value,
  })
  fetchActiveTab()
}

function fetchActiveTab() {
  switch (activeTab.value) {
    case 'executive': analyticsStore.fetchExecutiveSummary(); break
    case 'workforce': analyticsStore.fetchWorkforceAnalytics(); break
    case 'attendance': analyticsStore.fetchAttendanceAnalytics(); break
    case 'leave': analyticsStore.fetchLeaveAnalytics(); break
    case 'payroll': analyticsStore.fetchPayrollAnalytics(); break
    case 'projects': analyticsStore.fetchProjectAnalytics(); break
  }
}

watch(activeTab, () => fetchActiveTab())

onMounted(() => {
  analyticsStore.fetchExecutiveSummary()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-brand-dark">Analytics</h1>
        <p class="text-sm text-gray-500 mt-1">
          Comprehensive HR metrics and insights
        </p>
      </div>

      <!-- Global Filters + Export -->
      <div class="flex items-center gap-3 flex-wrap">
        <div class="flex items-center gap-2 px-3 py-2 bg-white border border-[#DCDEDD] rounded-[12px]">
          <FilterIcon class="w-4 h-4 text-gray-400" />
          <select
            v-model="period"
            @change="onFilterChange"
            class="text-sm text-brand-dark bg-transparent border-none outline-none cursor-pointer pr-6"
          >
            <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <select
          v-model="department"
          @change="onFilterChange"
          class="text-sm text-brand-dark bg-white border border-[#DCDEDD] rounded-[12px] px-3 py-2 outline-none cursor-pointer"
        >
          <option :value="null">All Departments</option>
          <option v-for="dept in departments" :key="dept.value" :value="dept.value">
            {{ dept.label }}
          </option>
        </select>

        <!-- Export Dropdown -->
        <div v-if="can('analytics-export')" class="relative">
          <button
            @click="showExportMenu = !showExportMenu"
            :disabled="exportLoading"
            class="flex items-center gap-2 px-3 py-2 bg-[#0C51D9] text-white text-sm font-medium rounded-[12px] hover:bg-[#0a44b8] transition-colors disabled:opacity-50"
          >
            <DownloadIcon class="w-4 h-4" />
            <span v-if="exportLoading">Exporting...</span>
            <span v-else>Export</span>
          </button>
          <div
            v-if="showExportMenu"
            class="absolute right-0 mt-2 w-48 bg-white border border-[#DCDEDD] rounded-[12px] shadow-lg z-10 overflow-hidden"
          >
            <button
              @click="handleExportExcel"
              class="flex items-center gap-3 w-full px-4 py-3 text-sm text-brand-dark hover:bg-gray-50 transition-colors"
            >
              <FileSpreadsheetIcon class="w-4 h-4 text-green-600" />
              Export as Excel
            </button>
            <button
              @click="handleExportPdf"
              class="flex items-center gap-3 w-full px-4 py-3 text-sm text-brand-dark hover:bg-gray-50 transition-colors border-t border-gray-100"
            >
              <FileTextIcon class="w-4 h-4 text-red-500" />
              Export as PDF
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        class="flex items-center gap-2 px-4 py-2.5 rounded-[12px] border text-sm font-medium whitespace-nowrap transition-all duration-200"
        :class="activeTab === tab.id
          ? 'bg-[#0C51D9] text-white border-[#0C51D9] shadow-md'
          : 'bg-white text-gray-600 border-[#DCDEDD] hover:border-[#0C51D9] hover:text-[#0C51D9]'"
      >
        <component :is="tab.icon" class="w-4 h-4" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab Content -->
    <div v-if="activeTab === 'executive'">
      <!-- Loading State -->
      <div v-if="executiveSummaryLoading" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="i in 6" :key="i" class="h-28 bg-gray-100 rounded-[16px] animate-pulse" />
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="h-96 bg-gray-100 rounded-[20px] animate-pulse" />
          <div class="h-96 bg-gray-100 rounded-[20px] animate-pulse" />
        </div>
      </div>

      <!-- Executive Summary Content -->
      <div v-else-if="executiveSummary" class="space-y-6">
        <!-- Period Label -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
          <span class="inline-block w-2 h-2 rounded-full bg-[#0C51D9]"></span>
          {{ executiveSummary.period?.label }}
          <span class="text-gray-300">|</span>
          {{ executiveSummary.period?.start }} - {{ executiveSummary.period?.end }}
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <StatsCard
            title="Total Employees"
            :value="kpis.total_employees || 0"
            :subtitle="`${kpis.employee_growth >= 0 ? '+' : ''}${kpis.employee_growth?.toFixed(1) || 0}% growth`"
            :subtitleColor="kpis.employee_growth >= 0 ? 'success' : 'danger'"
            color="blue"
          >
            <template #icon>
              <UsersIcon class="w-5 h-5" />
            </template>
          </StatsCard>

          <StatsCard
            title="Attendance Rate"
            :value="`${kpis.attendance_rate?.toFixed(1) || 0}%`"
            :subtitle="`${kpis.attendance_rate_change >= 0 ? '+' : ''}${kpis.attendance_rate_change?.toFixed(1) || 0}% vs prev`"
            :subtitleColor="kpis.attendance_rate_change >= 0 ? 'success' : 'danger'"
            color="green"
          >
            <template #icon>
              <CalendarCheckIcon class="w-5 h-5" />
            </template>
          </StatsCard>

          <StatsCard
            title="Average Salary"
            :value="formatRupiahCompact(kpis.average_salary || 0)"
            :subtitle="`${kpis.salary_change >= 0 ? '+' : ''}${kpis.salary_change?.toFixed(1) || 0}% change`"
            :subtitleColor="kpis.salary_change >= 0 ? 'success' : 'danger'"
            color="purple"
          >
            <template #icon>
              <WalletIcon class="w-5 h-5" />
            </template>
          </StatsCard>

          <StatsCard
            title="Active Projects"
            :value="kpis.active_projects || 0"
            subtitle="Currently active"
            subtitleColor="info"
            color="orange"
          >
            <template #icon>
              <FolderKanbanIcon class="w-5 h-5" />
            </template>
          </StatsCard>

          <StatsCard
            title="Task Completion"
            :value="`${kpis.task_completion_rate?.toFixed(1) || 0}%`"
            subtitle="Done / Total tasks"
            subtitleColor="info"
            color="teal"
          >
            <template #icon>
              <TrendingUpIcon class="w-5 h-5" />
            </template>
          </StatsCard>

          <StatsCard
            title="Leave Utilization"
            :value="`${kpis.leave_utilization?.toFixed(1) || 0}%`"
            subtitle="Used / Available quota"
            subtitleColor="info"
            color="cyan"
          >
            <template #icon>
              <PalmtreeIcon class="w-5 h-5" />
            </template>
          </StatsCard>
        </div>

        <!-- Charts Row 1: Attendance vs Deduction + HR Cost -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Attendance vs Deduction Trend -->
          <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
            <h3 class="text-base font-semibold text-brand-dark mb-1">Attendance vs Deduction Impact</h3>
            <p class="text-xs text-gray-400 mb-4">Correlation between attendance rate and payroll deductions</p>
            <VueApexCharts
              v-if="attendanceDeductionSeries[0]?.data?.length"
              type="line"
              height="320"
              :options="attendanceDeductionOptions"
              :series="attendanceDeductionSeries"
            />
            <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">
              No data available for this period
            </div>
          </div>

          <!-- Monthly HR Cost Breakdown -->
          <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
            <h3 class="text-base font-semibold text-brand-dark mb-1">Monthly HR Cost Breakdown</h3>
            <p class="text-xs text-gray-400 mb-4">Salary, tax, BPJS contributions, and deductions</p>
            <VueApexCharts
              v-if="hrCostSeries[0]?.data?.length"
              type="area"
              height="320"
              :options="hrCostOptions"
              :series="hrCostSeries"
            />
            <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">
              No data available for this period
            </div>
          </div>
        </div>

        <!-- Charts Row 2: Team Performance -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
          <h3 class="text-base font-semibold text-brand-dark mb-1">Team Performance Comparison</h3>
          <p class="text-xs text-gray-400 mb-4">Attendance rate and task completion by team</p>
          <VueApexCharts
            v-if="teamPerformanceSeries[0]?.data?.length"
            type="bar"
            height="320"
            :options="teamPerformanceOptions"
            :series="teamPerformanceSeries"
          />
          <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">
            No team data available for this period
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
        <BarChart3Icon class="w-16 h-16 mb-4 opacity-30" />
        <p class="text-lg font-medium">No analytics data available</p>
        <p class="text-sm mt-1">Try adjusting the period or filters</p>
      </div>
    </div>

    <!-- Workforce Analytics Tab -->
    <WorkforceAnalytics v-else-if="activeTab === 'workforce'" />

    <!-- Attendance Analytics Tab -->
    <AttendanceAnalytics v-else-if="activeTab === 'attendance'" />

    <!-- Leave Analytics Tab -->
    <LeaveAnalytics v-else-if="activeTab === 'leave'" />

    <!-- Payroll Analytics Tab -->
    <PayrollAnalytics v-else-if="activeTab === 'payroll'" />

    <!-- Project Analytics Tab -->
    <ProjectAnalytics v-else-if="activeTab === 'projects'" />
  </div>
</template>
