<template>
    <div class="space-y-6">
        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MetricCard
                title="Utilization Rate"
                :value="leaveUtilization?.latest || 0"
                format="percentage"
                :trend="calculateTrend(leaveUtilization?.data)"
                subtitle="Leave usage"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Pending Requests"
                :value="leave?.pending_requests || 0"
                format="number"
                subtitle="Awaiting approval"
                :loading="leaveLoading"
            />

            <MetricCard
                title="Approval Rate"
                :value="leave?.approval_rate || 0"
                format="percentage"
                subtitle="This period"
                :loading="leaveLoading"
            />

            <MetricCard
                title="Total Leave Days"
                :value="leave?.total_leave_days || 0"
                format="number"
                subtitle="Taken this period"
                :loading="leaveLoading"
            />
        </div>

        <!-- Leave Utilization Trend -->
        <TrendChart
            v-if="leaveUtilization?.data"
            title="Leave Utilization Trend"
            subtitle="Monthly leave usage percentage"
            :chart-data="leaveUtilization.data"
            chart-type="line"
            x-key="period"
            y-key="value"
            y-label="Utilization Rate (%)"
            :loading="enhancedMetricsLoading"
        />

        <!-- Peak Leave Periods -->
        <div
            v-if="peakLeavePeriods?.peak_periods"
            class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
        >
            <h3 class="text-lg font-bold text-brand-dark mb-6">Peak Leave Periods</h3>
            <VueApexCharts
                type="bar"
                height="300"
                :options="peakPeriodsChartOptions"
                :series="peakPeriodsChartSeries"
            />
        </div>

        <!-- Leave Balance Trends by Type -->
        <div
            v-if="leaveBalanceTrends?.trends"
            class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
        >
            <h3 class="text-lg font-bold text-brand-dark mb-6">Leave Balance by Type</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="trend in leaveBalanceTrends.trends"
                    :key="trend.leave_type"
                    class="bg-gray-50 rounded-lg p-4"
                >
                    <h4 class="text-sm font-medium text-gray-700 mb-2">
                        {{ capitalize(trend.leave_type) }}
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Entitled:</span>
                            <span class="font-semibold">{{ trend.entitled }} days</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Used:</span>
                            <span class="font-semibold">{{ trend.used }} days</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Remaining:</span>
                            <span class="font-semibold text-blue-600">{{ trend.remaining }} days</span>
                        </div>
                        <div class="mt-2 bg-gray-200 rounded-full h-2">
                            <div
                                class="bg-blue-600 h-2 rounded-full"
                                :style="{ width: `${trend.utilization_rate}%` }"
                            ></div>
                        </div>
                        <div class="text-xs text-gray-500 text-center">{{ trend.utilization_rate }}% utilized</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Leave Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Leave Type Distribution -->
            <div
                class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-brand-dark mb-6">Leave Type Distribution</h3>
                <VueApexCharts
                    v-if="!leaveLoading && leave?.leave_type_distribution"
                    type="donut"
                    height="300"
                    :options="leaveTypeDonutOptions"
                    :series="leaveTypeDonutSeries"
                />
            </div>

            <!-- Leave Status Distribution -->
            <div
                class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-brand-dark mb-6">Leave Status</h3>
                <VueApexCharts
                    v-if="!leaveLoading && leave?.status_distribution"
                    type="donut"
                    height="300"
                    :options="statusDonutOptions"
                    :series="statusDonutSeries"
                />
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
import { capitalize } from "@/helpers/format";

const analyticsStore = useAnalyticsStore();
const { leave, leaveLoading, leaveUtilization, leaveBalanceTrends, peakLeavePeriods, enhancedMetricsLoading } =
    storeToRefs(analyticsStore);

onMounted(async () => {
    await Promise.all([
        analyticsStore.fetchLeaveAnalytics(),
        analyticsStore.fetchLeaveUtilization(),
        analyticsStore.fetchLeaveBalanceTrends(),
        analyticsStore.fetchPeakLeavePeriods(),
    ]);
});

const calculateTrend = (data) => {
    if (!data || data.length < 2) return null;
    const latest = data[data.length - 1].value;
    const previous = data[data.length - 2].value;
    if (previous === 0) return null;
    return ((latest - previous) / previous) * 100;
};

// Peak Periods Chart
const peakPeriodsChartOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "60%" } },
    colors: ["#3b82f6"],
    xaxis: {
        categories: (peakLeavePeriods.value?.peak_periods || []).map((p) => p.month),
    },
    yaxis: { labels: { formatter: (v) => Math.round(v) } },
    tooltip: { y: { formatter: (v) => `${v} days` } },
    dataLabels: { enabled: false },
}));

const peakPeriodsChartSeries = computed(() => [
    {
        name: "Leave Days",
        data: (peakLeavePeriods.value?.peak_periods || []).map((p) => p.total_days),
    },
]);

// Leave Type Distribution
const leaveTypeDonutOptions = computed(() => {
    const data = leave.value?.leave_type_distribution || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.type)),
        colors: ["#3b82f6", "#10b981", "#f59e0b", "#ef4444"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const leaveTypeDonutSeries = computed(() => (leave.value?.leave_type_distribution || []).map((d) => d.count));

// Status Distribution
const statusDonutOptions = computed(() => {
    const data = leave.value?.status_distribution || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.status)),
        colors: ["#10b981", "#f59e0b", "#ef4444"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const statusDonutSeries = computed(() => (leave.value?.status_distribution || []).map((d) => d.count));
</script>
