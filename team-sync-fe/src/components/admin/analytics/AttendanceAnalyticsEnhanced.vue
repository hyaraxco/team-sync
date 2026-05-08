<template>
    <div class="space-y-6">
        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MetricCard
                title="Attendance Rate"
                :value="attendance?.attendance_rate || 0"
                format="percentage"
                :trend="attendance?.attendance_rate_change"
                subtitle="Current period"
                :loading="attendanceLoading"
            />

            <MetricCard
                title="Compliance Rate"
                :value="complianceRate?.latest || 0"
                format="percentage"
                :trend="calculateTrend(complianceRate?.data)"
                subtitle="Policy compliance"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Remote Work"
                :value="remoteOfficeRatio?.average_remote_ratio || 0"
                format="percentage"
                subtitle="Average remote ratio"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Late Arrivals"
                :value="attendance?.late_count || 0"
                format="number"
                subtitle="This period"
                :loading="attendanceLoading"
            />
        </div>

        <!-- Compliance Rate Trend -->
        <TrendChart
            v-if="complianceRate?.data"
            title="Attendance Compliance Trend"
            subtitle="Monthly compliance percentage"
            :chart-data="complianceRate.data"
            chart-type="line"
            x-key="period"
            y-key="value"
            y-label="Compliance Rate (%)"
            :loading="enhancedMetricsLoading"
        />

        <!-- Attendance Patterns by Day of Week -->
        <div
            v-if="attendancePatterns?.patterns"
            class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6"
        >
            <h3 class="text-lg font-bold text-[#202020] mb-6">Attendance Patterns by Day</h3>
            <VueApexCharts type="bar" height="300" :options="patternsChartOptions" :series="patternsChartSeries" />
        </div>

        <!-- Remote vs Office Ratio Trend -->
        <TrendChart
            v-if="remoteOfficeRatio?.data"
            title="Remote Work Trend"
            subtitle="Daily remote work percentage"
            :chart-data="remoteOfficeRatio.data"
            chart-type="line"
            x-key="period"
            y-key="value"
            y-label="Remote Ratio (%)"
            :loading="enhancedMetricsLoading"
        />

        <!-- Existing Attendance Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Attendance Trend -->
            <div
                class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-[#202020] mb-6">Attendance Trend</h3>
                <VueApexCharts
                    v-if="!attendanceLoading && attendance?.attendance_trend"
                    type="line"
                    height="300"
                    :options="attendanceTrendOptions"
                    :series="attendanceTrendSeries"
                />
            </div>

            <!-- Status Distribution -->
            <div
                class="bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-[#202020] mb-6">Status Distribution</h3>
                <VueApexCharts
                    v-if="!attendanceLoading && attendance?.status_distribution"
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
const { attendance, attendanceLoading, complianceRate, attendancePatterns, remoteOfficeRatio, enhancedMetricsLoading } =
    storeToRefs(analyticsStore);

onMounted(async () => {
    await Promise.all([
        analyticsStore.fetchAttendanceAnalytics(),
        analyticsStore.fetchComplianceRate(),
        analyticsStore.fetchAttendancePatterns(),
        analyticsStore.fetchRemoteOfficeRatio(),
    ]);
});

const calculateTrend = (data) => {
    if (!data || data.length < 2) return null;
    const latest = data[data.length - 1].value;
    const previous = data[data.length - 2].value;
    if (previous === 0) return null;
    return ((latest - previous) / previous) * 100;
};

// Patterns Chart
const patternsChartOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "60%" } },
    colors: ["#3b82f6"],
    xaxis: {
        categories: (attendancePatterns.value?.patterns || []).map((p) => p.day),
    },
    yaxis: { labels: { formatter: (v) => `${v}%` } },
    tooltip: { y: { formatter: (v) => `${v}%` } },
    dataLabels: { enabled: false },
}));

const patternsChartSeries = computed(() => [
    {
        name: "Attendance Rate",
        data: (attendancePatterns.value?.patterns || []).map((p) => p.attendance_rate),
    },
]);

// Attendance Trend
const attendanceTrendOptions = computed(() => ({
    chart: { type: "line", height: 300, toolbar: { show: false } },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#3b82f6"],
    xaxis: {
        categories: (attendance.value?.attendance_trend || []).map((d) => d.date),
    },
    yaxis: { labels: { formatter: (v) => `${v}%` } },
    tooltip: { y: { formatter: (v) => `${v}%` } },
    dataLabels: { enabled: false },
}));

const attendanceTrendSeries = computed(() => [
    {
        name: "Attendance Rate",
        data: (attendance.value?.attendance_trend || []).map((d) => d.rate),
    },
]);

// Status Distribution
const statusDonutOptions = computed(() => {
    const data = attendance.value?.status_distribution || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.status)),
        colors: ["#10b981", "#f59e0b", "#ef4444", "#6b7280"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const statusDonutSeries = computed(() => (attendance.value?.status_distribution || []).map((d) => d.count));
</script>
