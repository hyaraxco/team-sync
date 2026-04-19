<template>
  <div class="space-y-6">
    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <MetricCard
        title="Total Payroll Cost"
        :value="payroll?.total_payroll_cost || 0"
        format="currency"
        :trend="payroll?.payroll_cost_change"
        subtitle="This period"
        :loading="payrollLoading"
      />

      <MetricCard
        title="Cost Per Employee"
        :value="payroll?.average_salary || 0"
        format="currency"
        subtitle="Average"
        :loading="payrollLoading"
      />

      <MetricCard
        title="Deduction Rate"
        :value="deductionAnalysis?.latest || 0"
        format="percentage"
        :trend="calculateTrend(deductionAnalysis?.data)"
        subtitle="Average deductions"
        :loading="enhancedMetricsLoading"
      />

      <MetricCard
        title="Employees Paid"
        :value="payroll?.employees_paid || 0"
        format="number"
        subtitle="This period"
        :loading="payrollLoading"
      />
    </div>

    <!-- Charts Section 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
      <!-- Total Cost Trend -->
      <div class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6">
        <h3 class="text-lg font-bold text-[#202020] mb-6">
          Payroll Cost Trends
        </h3>
        <VueApexCharts
          v-if="payrollCostTrends?.total_cost_trend"
          type="line"
          height="300"
          :options="costTrendOptions"
          :series="costTrendSeries"
        />
        <div v-else class="flex flex-col items-center justify-center h-[300px] bg-gray-50/50 rounded-[12px] border border-dashed border-gray-200">
          <p class="text-sm font-medium text-[#737373]">No trend data</p>
        </div>
      </div>

      <!-- Salary Distribution -->
      <div class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6">
        <h3 class="text-lg font-bold text-[#202020] mb-6">
          Salary Distribution
        </h3>
        <VueApexCharts
          type="bar"
          height="300"
          :options="salaryDistChartOptions"
          :series="salaryDistChartSeries"
        />
      </div>
    </div>

    <!-- Charts Section 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
      <!-- Deduction Trends -->
      <TrendChart
        v-if="deductionAnalysis?.data"
        title="Deduction Rate Trend"
        subtitle="Monthly deduction percentage"
        :chart-data="deductionAnalysis.data"
        chart-type="line"
        x-key="period"
        y-key="value"
        y-label="Deduction Rate (%)"
        :loading="enhancedMetricsLoading"
      />

      <!-- Cost by Department -->
      <div class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6">
        <h3 class="text-lg font-bold text-[#202020] mb-6">
          Cost by Department
        </h3>
        <VueApexCharts
          v-if="!payrollLoading && payroll?.department_costs"
          type="donut"
          height="300"
          :options="departmentCostOptions"
          :series="departmentCostSeries"
        />
        <div v-else class="flex flex-col items-center justify-center h-[300px] bg-gray-50/50 rounded-[12px] border border-dashed border-gray-200">
          <p class="text-sm font-medium text-[#737373]">No department data</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import MetricCard from "./MetricCard.vue";
import TrendChart from "./TrendChart.vue";
import { capitalize } from "@/utils/formatUtils";

const analyticsStore = useAnalyticsStore();
const {
  payroll,
  payrollLoading,
  payrollCostTrends,
  salaryDistribution,
  deductionAnalysis,
  enhancedMetricsLoading,
} = storeToRefs(analyticsStore);

onMounted(async () => {
  await Promise.all([
    analyticsStore.fetchPayrollAnalytics(),
    analyticsStore.fetchPayrollCostTrends(),
    analyticsStore.fetchSalaryDistribution(),
    analyticsStore.fetchDeductionAnalysis(),
  ]);
});

const calculateTrend = (data) => {
  if (!data || data.length < 2) return null;
  const latest = data[data.length - 1].value;
  const previous = data[data.length - 2].value;
  if (previous === 0) return null;
  return ((latest - previous) / previous) * 100;
};

// Cost Trend Chart
const costTrendOptions = computed(() => ({
  chart: { type: "line", height: 300, toolbar: { show: false } },
  stroke: { width: [2, 2], curve: "smooth" },
  colors: ["#3b82f6", "#10b981"],
  xaxis: {
    categories: (payrollCostTrends.value?.total_cost_trend || []).map(
      (d) => d.period,
    ),
  },
  yaxis: {
    labels: {
      formatter: (v) =>
        new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          minimumFractionDigits: 0,
        }).format(v),
    },
  },
  tooltip: {
    y: {
      formatter: (v) =>
        new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          minimumFractionDigits: 0,
        }).format(v),
    },
  },
  dataLabels: { enabled: false },
}));

const costTrendSeries = computed(() => {
  const totalCost = payrollCostTrends.value?.total_cost_trend || [];
  const perEmployee = payrollCostTrends.value?.cost_per_employee_trend || [];

  return [
    {
      name: "Total Cost",
      data: totalCost.map((d) => d.value),
    },
    {
      name: "Cost Per Employee",
      data: perEmployee.map((d) => d.value),
    },
  ];
});

// Salary Distribution Chart
const salaryDistChartOptions = computed(() => ({
  chart: { type: "bar", height: 300, toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true } },
  colors: ["#3b82f6"],
  xaxis: {
    categories: (salaryDistribution.value?.distribution || []).map(
      (d) => d.range,
    ),
  },
  yaxis: { labels: { formatter: (v) => Math.round(v) } },
  tooltip: { y: { formatter: (v) => `${v} employees` } },
  dataLabels: { enabled: false },
}));

const salaryDistChartSeries = computed(() => [
  {
    name: "Employees",
    data: (salaryDistribution.value?.distribution || []).map((d) => d.count),
  },
]);

// Payroll Trend
const payrollTrendOptions = computed(() => ({
  chart: { type: "area", height: 300, toolbar: { show: false } },
  stroke: { width: 2, curve: "smooth" },
  colors: ["#3b82f6"],
  fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  xaxis: {
    categories: (payroll.value?.payroll_trend || []).map((d) => d.month),
  },
  yaxis: {
    labels: {
      formatter: (v) =>
        new Intl.NumberFormat("id-ID", {
          notation: "compact",
          compactDisplay: "short",
        }).format(v),
    },
  },
  tooltip: {
    y: {
      formatter: (v) =>
        new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          minimumFractionDigits: 0,
        }).format(v),
    },
  },
  dataLabels: { enabled: false },
}));

const payrollTrendSeries = computed(() => [
  {
    name: "Payroll Cost",
    data: (payroll.value?.payroll_trend || []).map((d) => d.amount),
  },
]);

// Department Cost Distribution
const departmentCostOptions = computed(() => {
  const data = payroll.value?.department_costs || [];
  return {
    chart: { type: "donut", height: 300 },
    labels: data.map((d) => capitalize(d.department)),
    colors: ["#3b82f6", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6"],
    legend: { position: "bottom" },
    plotOptions: { pie: { donut: { size: "60%" } } },
    tooltip: {
      y: {
        formatter: (v) =>
          new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
          }).format(v),
      },
    },
  };
});

const departmentCostSeries = computed(() =>
  (payroll.value?.department_costs || []).map((d) => d.cost),
);
</script>
