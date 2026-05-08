<template>
    <div class="space-y-6">
        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MetricCard
                title="Average Tenure"
                :value="averageTenure?.average_tenure_years || 0"
                format="number"
                :subtitle="`${averageTenure?.average_tenure_days || 0} days`"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Turnover Rate"
                :value="turnoverRate?.latest || 0"
                format="percentage"
                :trend="calculateTrend(turnoverRate?.data)"
                subtitle="Last month"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="New Hires"
                :value="newHireTrends?.data?.[newHireTrends?.data?.length - 1]?.value || 0"
                format="number"
                subtitle="This month"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Total Staff Members"
                :value="workforce?.total_employees || 0"
                format="number"
                :trend="workforce?.employee_growth"
                subtitle="Active employees"
                :loading="workforceLoading"
            />
        </div>

        <!-- Turnover Rate Trend -->
        <TrendChart
            v-if="turnoverRate?.data"
            title="Turnover Rate Trend"
            subtitle="Monthly turnover percentage"
            :chart-data="turnoverRate.data"
            chart-type="line"
            x-key="period"
            y-key="value"
            y-label="Turnover Rate (%)"
            :loading="enhancedMetricsLoading"
        />

        <!-- New Hire Trends -->
        <TrendChart
            v-if="newHireTrends?.data"
            title="New Hire Trends"
            subtitle="Monthly new hires"
            :chart-data="newHireTrends.data"
            chart-type="bar"
            x-key="period"
            y-key="value"
            y-label="New Hires"
            :loading="enhancedMetricsLoading"
        />

        <!-- Existing Workforce Analytics -->
        <div class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6">
            <h3 class="text-lg font-bold text-[#202020] mb-6">Headcount Trend</h3>
            <VueApexCharts
                v-if="!workforceLoading && workforce?.headcount_trend"
                type="area"
                height="300"
                :options="headcountOptions"
                :series="headcountSeries"
            />
            <div v-else class="flex items-center justify-center h-64">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        </div>

        <!-- Demographics Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Gender Distribution -->
            <div
                class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-[#202020] mb-6">Gender Distribution</h3>
                <VueApexCharts
                    v-if="!workforceLoading && workforce?.gender_distribution"
                    type="donut"
                    height="300"
                    :options="genderDonutOptions"
                    :series="genderDonutSeries"
                />
            </div>

            <!-- Employment Type Distribution -->
            <div
                class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-[#202020] mb-6">Employment Type</h3>
                <VueApexCharts
                    v-if="!workforceLoading && workforce?.employment_types"
                    type="donut"
                    height="300"
                    :options="employmentDonutOptions"
                    :series="employmentDonutSeries"
                />
            </div>
        </div>

        <!-- Department Distribution -->
        <div class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6">
            <h3 class="text-lg font-bold text-[#202020] mb-6">Department Headcount</h3>
            <VueApexCharts
                v-if="!workforceLoading && workforce?.department_headcount"
                type="bar"
                height="300"
                :options="departmentOptions"
                :series="departmentSeries"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import MetricCard from "./MetricCard.vue";
import TrendChart from "./TrendChart.vue";
import { capitalize } from "@/helpers/format";

const analyticsStore = useAnalyticsStore();
const { workforce, workforceLoading, turnoverRate, averageTenure, newHireTrends, enhancedMetricsLoading } =
    storeToRefs(analyticsStore);

onMounted(async () => {
    await Promise.all([
        analyticsStore.fetchWorkforceAnalytics(),
        analyticsStore.fetchTurnoverRate(),
        analyticsStore.fetchAverageTenure(),
        analyticsStore.fetchNewHireTrends(),
    ]);
});

const calculateTrend = (data) => {
    if (!data || data.length < 2) return null;
    const latest = data[data.length - 1].value;
    const previous = data[data.length - 2].value;
    if (previous === 0) return null;
    return ((latest - previous) / previous) * 100;
};

// Chart configurations
const headcountOptions = computed(() => ({
    chart: { type: "area", height: 300, toolbar: { show: false } },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#3b82f6"],
    fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: {
        categories: (workforce.value?.headcount_trend || []).map((d) => d.month),
    },
    yaxis: { labels: { formatter: (v) => Math.round(v) } },
    tooltip: { y: { formatter: (v) => `${v} employees` } },
    dataLabels: { enabled: false },
}));

const headcountSeries = computed(() => [
    {
        name: "Headcount",
        data: (workforce.value?.headcount_trend || []).map((d) => d.count),
    },
]);

const genderDonutOptions = computed(() => {
    const data = workforce.value?.gender_distribution || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.gender)),
        colors: ["#3b82f6", "#ec4899"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const genderDonutSeries = computed(() => (workforce.value?.gender_distribution || []).map((d) => d.count));

const employmentDonutOptions = computed(() => {
    const data = workforce.value?.employment_types || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.type)),
        colors: ["#3b82f6", "#8b5cf6", "#10b981", "#f59e0b"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const employmentDonutSeries = computed(() => (workforce.value?.employment_types || []).map((d) => d.count));

const departmentOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
    colors: ["#3b82f6"],
    xaxis: {
        categories: (workforce.value?.department_headcount || []).map((d) => capitalize(d.department)),
    },
    tooltip: { y: { formatter: (v) => `${v} employees` } },
    dataLabels: { enabled: false },
}));

const departmentSeries = computed(() => [
    {
        name: "Employees",
        data: (workforce.value?.department_headcount || []).map((d) => d.count),
    },
]);
</script>
