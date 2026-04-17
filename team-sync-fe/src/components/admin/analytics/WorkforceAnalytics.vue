<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAnalyticsStore } from '@/stores/analytics'
import { capitalize } from '@/utils/formatUtils'
import { UsersIcon } from 'lucide-vue-next'

const analyticsStore = useAnalyticsStore()
const { workforce, workforceLoading } = storeToRefs(analyticsStore)

// ── Headcount Trend Area Chart (col-span-2) ─────────────────────────
const headcountOptions = computed(() => ({
  chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  stroke: { width: 2, curve: 'smooth' },
  colors: ['#0C51D9'],
  fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  xaxis: {
    categories: (workforce.value?.headcount_trend || []).map(d => d.month),
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
  },
  yaxis: { labels: { style: { colors: '#94a3b8' } } },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  markers: { size: 4, hover: { size: 6 } },
  tooltip: { y: { formatter: v => `${v} employees` } },
  dataLabels: { enabled: false },
}))

const headcountSeries = computed(() => [{
  name: 'Headcount',
  data: (workforce.value?.headcount_trend || []).map(d => d.count),
}])

// ── Gender Distribution Donut ───────────────────────────────────────
const genderColors = { male: '#0C51D9', female: '#ec4899' }

const genderDonutOptions = computed(() => {
  const data = workforce.value?.gender_distribution || []
  return {
    chart: { type: 'donut', height: 300, fontFamily: 'Plus Jakarta Sans, sans-serif' },
    labels: data.map(d => capitalize(d.gender)),
    colors: data.map(d => genderColors[d.gender] || '#94a3b8'),
    legend: { position: 'bottom', fontSize: '12px' },
    plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '14px' } } } } },
    dataLabels: { enabled: false },
  }
})

const genderDonutSeries = computed(() =>
  (workforce.value?.gender_distribution || []).map(d => d.count)
)

// ── Employment Type Donut ───────────────────────────────────────────
const employmentTypeColors = ['#0C51D9', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4']

const employmentDonutOptions = computed(() => {
  const data = workforce.value?.employment_types || []
  return {
    chart: { type: 'donut', height: 300, fontFamily: 'Plus Jakarta Sans, sans-serif' },
    labels: data.map(d => capitalize(d.type)),
    colors: employmentTypeColors.slice(0, data.length),
    legend: { position: 'bottom', fontSize: '12px' },
    plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '14px' } } } } },
    dataLabels: { enabled: false },
  }
})

const employmentDonutSeries = computed(() =>
  (workforce.value?.employment_types || []).map(d => d.count)
)

// ── Work Location Horizontal Bar ────────────────────────────────────
const workLocationOptions = computed(() => ({
  chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '60%' } },
  colors: ['#0C51D9'],
  xaxis: {
    categories: (workforce.value?.work_locations || []).map(d => capitalize(d.location)),
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
  },
  yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  tooltip: { y: { formatter: v => `${v} employees` } },
  dataLabels: { enabled: false },
}))

const workLocationSeries = computed(() => [{
  name: 'Employees',
  data: (workforce.value?.work_locations || []).map(d => d.count),
}])

// ── Department Headcount Horizontal Bar ─────────────────────────────
const departmentOptions = computed(() => ({
  chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '60%' } },
  colors: ['#0C51D9'],
  xaxis: {
    categories: (workforce.value?.department_headcount || []).map(d => capitalize(d.department)),
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
  },
  yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  tooltip: { y: { formatter: v => `${v} employees` } },
  dataLabels: { enabled: false },
}))

const departmentSeries = computed(() => [{
  name: 'Headcount',
  data: (workforce.value?.department_headcount || []).map(d => d.count),
}])

// ── Skill Level Bar Chart ───────────────────────────────────────────
const skillLevelOptions = computed(() => {
  const data = workforce.value?.skill_levels || []
  const len = data.length
  const colors = data.map((_, i) => {
    const ratio = len > 1 ? i / (len - 1) : 0
    const r = Math.round(148 + (12 - 148) * ratio)
    const g = Math.round(163 + (81 - 163) * ratio)
    const b = Math.round(184 + (217 - 184) * ratio)
    return `rgb(${r}, ${g}, ${b})`
  })
  return {
    chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
    plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', distributed: true } },
    colors,
    xaxis: {
      categories: data.map(d => capitalize(d.level)),
      labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
    },
    yaxis: { labels: { style: { colors: '#94a3b8' } } },
    grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
    legend: { show: false },
    tooltip: { y: { formatter: v => `${v} employees` } },
    dataLabels: { enabled: false },
  }
})

const skillLevelSeries = computed(() => [{
  name: 'Employees',
  data: (workforce.value?.skill_levels || []).map(d => d.count),
}])

// ── Age Distribution Bar Chart ──────────────────────────────────────
const ageOptions = computed(() => ({
  chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  colors: ['#8b5cf6'],
  xaxis: {
    categories: (workforce.value?.age_distribution || []).map(d => d.range),
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
  },
  yaxis: { labels: { style: { colors: '#94a3b8' } } },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  tooltip: { y: { formatter: v => `${v} employees` } },
  dataLabels: { enabled: false },
}))

const ageSeries = computed(() => [{
  name: 'Employees',
  data: (workforce.value?.age_distribution || []).map(d => d.count),
}])

// ── Tenure Distribution Bar Chart ───────────────────────────────────
const tenureOptions = computed(() => ({
  chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  colors: ['#10b981'],
  xaxis: {
    categories: (workforce.value?.tenure_distribution || []).map(d => d.range),
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
  },
  yaxis: { labels: { style: { colors: '#94a3b8' } } },
  grid: { strokeDashArray: 4, borderColor: '#e2e8f0' },
  tooltip: { y: { formatter: v => `${v} employees` } },
  dataLabels: { enabled: false },
}))

const tenureSeries = computed(() => [{
  name: 'Employees',
  data: (workforce.value?.tenure_distribution || []).map(d => d.count),
}])
</script>

<template>
  <!-- Loading State -->
  <div v-if="workforceLoading" class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="lg:col-span-2 h-80 bg-gray-100 rounded-[20px] animate-pulse" />
      <div v-for="i in 6" :key="i" class="h-80 bg-gray-100 rounded-[20px] animate-pulse" />
    </div>
  </div>

  <!-- Content -->
  <div v-else-if="workforce" class="space-y-6">
    <!-- Period Label -->
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <span class="inline-block w-2 h-2 rounded-full bg-[#0C51D9]"></span>
      {{ workforce.period?.label }}
      <span class="text-gray-300">|</span>
      {{ workforce.period?.start }} - {{ workforce.period?.end }}
    </div>

    <!-- Row 1: Headcount Trend (full width) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="lg:col-span-2 bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Headcount Trend</h3>
        <p class="text-xs text-gray-400 mb-4">Monthly employee headcount over time</p>
        <VueApexCharts
          v-if="headcountSeries[0]?.data?.length"
          type="area" height="300"
          :options="headcountOptions" :series="headcountSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>
    </div>

    <!-- Row 2: Gender Distribution + Employment Type -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Gender Distribution</h3>
        <p class="text-xs text-gray-400 mb-4">Breakdown of employees by gender</p>
        <VueApexCharts
          v-if="genderDonutSeries.length"
          type="donut" height="300"
          :options="genderDonutOptions" :series="genderDonutSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>

      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Employment Type</h3>
        <p class="text-xs text-gray-400 mb-4">Distribution of employment types</p>
        <VueApexCharts
          v-if="employmentDonutSeries.length"
          type="donut" height="300"
          :options="employmentDonutOptions" :series="employmentDonutSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>
    </div>

    <!-- Row 3: Work Location + Department Headcount -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Work Location</h3>
        <p class="text-xs text-gray-400 mb-4">Employee distribution by work location</p>
        <VueApexCharts
          v-if="workLocationSeries[0]?.data?.length"
          type="bar" height="300"
          :options="workLocationOptions" :series="workLocationSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>

      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Department Headcount</h3>
        <p class="text-xs text-gray-400 mb-4">Number of employees per department</p>
        <VueApexCharts
          v-if="departmentSeries[0]?.data?.length"
          type="bar" height="300"
          :options="departmentOptions" :series="departmentSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>
    </div>

    <!-- Row 4: Skill Level + Age Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Skill Level</h3>
        <p class="text-xs text-gray-400 mb-4">Employee distribution by skill level</p>
        <VueApexCharts
          v-if="skillLevelSeries[0]?.data?.length"
          type="bar" height="300"
          :options="skillLevelOptions" :series="skillLevelSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>

      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Age Distribution</h3>
        <p class="text-xs text-gray-400 mb-4">Employee count by age range</p>
        <VueApexCharts
          v-if="ageSeries[0]?.data?.length"
          type="bar" height="300"
          :options="ageOptions" :series="ageSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>
    </div>

    <!-- Row 5: Tenure Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <h3 class="text-base font-semibold text-brand-dark mb-1">Tenure Distribution</h3>
        <p class="text-xs text-gray-400 mb-4">Employee count by length of service</p>
        <VueApexCharts
          v-if="tenureSeries[0]?.data?.length"
          type="bar" height="300"
          :options="tenureOptions" :series="tenureSeries"
        />
        <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
      </div>
    </div>
  </div>

  <!-- Empty State -->
  <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
    <UsersIcon class="w-16 h-16 mb-4 opacity-30" />
    <p class="text-lg font-medium">No workforce analytics available</p>
    <p class="text-sm mt-1">Try adjusting the period or filters</p>
  </div>
</template>
