<template>
    <div class="space-y-6">
        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MetricCard
                title="Timeline Adherence"
                :value="timelineAdherence?.adherence_rate || 0"
                format="percentage"
                subtitle="On-time delivery"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Task Velocity"
                :value="taskVelocity?.latest || 0"
                format="number"
                :trend="calculateTrend(taskVelocity?.data)"
                subtitle="Tasks/day"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Overdue Tasks"
                :value="overdueTrends?.current || 0"
                format="number"
                subtitle="Currently overdue"
                :loading="enhancedMetricsLoading"
            />

            <MetricCard
                title="Active Projects"
                :value="projects?.active_projects || 0"
                format="number"
                subtitle="In progress"
                :loading="projectsLoading"
            />
        </div>

        <!-- Timeline Adherence Details -->
        <div
            v-if="timelineAdherence"
            class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
        >
            <h3 class="text-lg font-bold text-brand-dark mb-6">Project Timeline Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium mb-1">On Time</div>
                    <div class="text-2xl font-bold text-green-700">
                        {{ timelineAdherence.on_time }}
                    </div>
                </div>
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="text-sm text-red-600 font-medium mb-1">Late</div>
                    <div class="text-2xl font-bold text-red-700">
                        {{ timelineAdherence.late }}
                    </div>
                </div>
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="text-sm text-blue-600 font-medium mb-1">Total Completed</div>
                    <div class="text-2xl font-bold text-blue-700">
                        {{ timelineAdherence.total_completed }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Velocity Trend -->
        <TrendChart
            v-if="taskVelocity?.data"
            title="Task Completion Velocity"
            subtitle="Tasks completed per day"
            :chart-data="taskVelocity.data"
            chart-type="line"
            x-key="period"
            y-key="value"
            y-label="Tasks/Day"
            :loading="enhancedMetricsLoading"
        />

        <!-- Overdue Tasks Trend -->
        <TrendChart
            v-if="overdueTrends?.data"
            title="Overdue Tasks Trend"
            subtitle="Daily overdue task count"
            :chart-data="overdueTrends.data"
            chart-type="bar"
            x-key="period"
            y-key="value"
            y-label="Overdue Tasks"
            :loading="enhancedMetricsLoading"
        />

        <!-- Existing Project Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Project Status Distribution -->
            <div
                class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-brand-dark mb-6">Project Status</h3>
                <VueApexCharts
                    v-if="!projectsLoading && projects?.project_status_overview"
                    type="donut"
                    height="300"
                    :options="projectStatusOptions"
                    :series="projectStatusSeries"
                />
            </div>

            <!-- Task Status Distribution -->
            <div
                class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
            >
                <h3 class="text-lg font-bold text-brand-dark mb-6">Task Status</h3>
                <VueApexCharts
                    v-if="!projectsLoading && projects?.task_status_distribution"
                    type="donut"
                    height="300"
                    :options="taskStatusOptions"
                    :series="taskStatusSeries"
                />
            </div>
        </div>

        <!-- Team Productivity -->
        <div
            v-if="projects?.team_productivity"
            class="bg-white rounded-2xl border border-brand-border hover:shadow-md transition-shadow duration-300 p-6"
        >
            <h3 class="text-lg font-bold text-brand-dark mb-6">Team Productivity</h3>
            <VueApexCharts
                type="bar"
                height="300"
                :options="teamProductivityOptions"
                :series="teamProductivitySeries"
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
const { projects, projectsLoading, timelineAdherence, taskVelocity, overdueTrends, enhancedMetricsLoading } =
    storeToRefs(analyticsStore);

onMounted(async () => {
    await Promise.all([
        analyticsStore.fetchProjectAnalytics(),
        analyticsStore.fetchTimelineAdherence(),
        analyticsStore.fetchTaskVelocity(),
        analyticsStore.fetchOverdueTrends(),
    ]);
});

const calculateTrend = (data) => {
    if (!data || data.length < 2) return null;
    const latest = data[data.length - 1].value;
    const previous = data[data.length - 2].value;
    if (previous === 0) return null;
    return ((latest - previous) / previous) * 100;
};

// Project Status Distribution
const projectStatusOptions = computed(() => {
    const data = projects.value?.project_status_overview || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.status)),
        colors: ["#10b981", "#3b82f6", "#f59e0b", "#ef4444"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const projectStatusSeries = computed(() => (projects.value?.project_status_overview || []).map((d) => d.count));

// Task Status Distribution
const taskStatusOptions = computed(() => {
    const data = projects.value?.task_status_distribution || [];
    return {
        chart: { type: "donut", height: 300 },
        labels: data.map((d) => capitalize(d.status)),
        colors: ["#10b981", "#3b82f6", "#f59e0b", "#ef4444", "#6b7280"],
        legend: { position: "bottom" },
        plotOptions: { pie: { donut: { size: "60%" } } },
    };
});

const taskStatusSeries = computed(() => (projects.value?.task_status_distribution || []).map((d) => d.count));

// Team Productivity
const teamProductivityOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
    colors: ["#3b82f6"],
    xaxis: {
        categories: (projects.value?.team_productivity || []).map((d) => d.team_name),
    },
    yaxis: { labels: { formatter: (v) => Math.round(v) } },
    tooltip: { y: { formatter: (v) => `${v} tasks completed` } },
    dataLabels: { enabled: false },
}));

const teamProductivitySeries = computed(() => [
    {
        name: "Tasks Completed",
        data: (projects.value?.team_productivity || []).map((d) => d.completed),
    },
]);
</script>
