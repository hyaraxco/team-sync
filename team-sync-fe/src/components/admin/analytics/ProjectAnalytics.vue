<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import { capitalize } from "@/utils/formatUtils";
import { FolderKanbanIcon } from "lucide-vue-next";

const analyticsStore = useAnalyticsStore();
const { projects, projectsLoading } = storeToRefs(analyticsStore);

// ── Task Velocity Area Chart ────────────────────────────────────────
const taskVelocityOptions = computed(() => ({
    chart: { type: "area", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#0C51D9"],
    fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: {
        categories: (projects.value?.task_velocity || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} tasks` } },
    dataLabels: { enabled: false },
}));

const taskVelocitySeries = computed(() => [
    {
        name: "Completed",
        data: (projects.value?.task_velocity || []).map((d) => d.completed),
    },
]);

// ── Task Status Distribution Donut ──────────────────────────────────
const taskStatusColors = {
    done: "#10b981",
    in_progress: "#3b82f6",
    review: "#f59e0b",
    todo: "#94a3b8",
    rejected: "#ef4444",
    cancelled: "#6b7280",
};

const taskStatusDonutOptions = computed(() => {
    const data = projects.value?.task_status_distribution || [];
    return {
        chart: { type: "donut", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
        labels: data.map((d) => capitalize(d.status)),
        colors: data.map((d) => taskStatusColors[d.status] || "#94a3b8"),
        legend: { position: "bottom", fontSize: "12px" },
        plotOptions: {
            pie: {
                donut: { size: "60%", labels: { show: true, total: { show: true, label: "Total", fontSize: "14px" } } },
            },
        },
        dataLabels: { enabled: false },
    };
});

const taskStatusDonutSeries = computed(() => (projects.value?.task_status_distribution || []).map((d) => d.count));

// ── Task Priority Distribution Bar Chart ────────────────────────────
const taskPriorityColors = {
    urgent: "#ef4444",
    high: "#f59e0b",
    medium: "#3b82f6",
    low: "#94a3b8",
};

const taskPriorityOptions = computed(() => {
    const data = projects.value?.task_priority_distribution || [];
    return {
        chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
        plotOptions: { bar: { borderRadius: 4, columnWidth: "60%", distributed: true } },
        colors: data.map((d) => taskPriorityColors[d.priority] || "#94a3b8"),
        xaxis: {
            categories: data.map((d) => capitalize(d.priority)),
            labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
        },
        yaxis: { labels: { style: { colors: "#94a3b8" } } },
        grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
        legend: { show: false },
        tooltip: { y: { formatter: (v) => `${v} tasks` } },
        dataLabels: { enabled: false },
    };
});

const taskPrioritySeries = computed(() => [
    {
        name: "Tasks",
        data: (projects.value?.task_priority_distribution || []).map((d) => d.count),
    },
]);

// ── Overdue Tasks KPI ───────────────────────────────────────────────
const overdueCount = computed(() => projects.value?.overdue_tasks ?? 0);
const totalActiveTasks = computed(() => projects.value?.total_active_tasks ?? 0);
const overduePercentage = computed(() => {
    if (!totalActiveTasks.value) return 0;
    return Math.round((overdueCount.value / totalActiveTasks.value) * 100);
});

// ── Project Status Overview Donut ───────────────────────────────────
const projectStatusColors = {
    active: "#10b981",
    completed: "#3b82f6",
    planning: "#f59e0b",
    on_hold: "#94a3b8",
    draft: "#6b7280",
    cancelled: "#ef4444",
};

const projectStatusDonutOptions = computed(() => {
    const data = projects.value?.project_status_overview || [];
    return {
        chart: { type: "donut", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
        labels: data.map((d) => capitalize(d.status)),
        colors: data.map((d) => projectStatusColors[d.status] || "#94a3b8"),
        legend: { position: "bottom", fontSize: "12px" },
        plotOptions: {
            pie: {
                donut: { size: "60%", labels: { show: true, total: { show: true, label: "Total", fontSize: "14px" } } },
            },
        },
        dataLabels: { enabled: false },
    };
});

const projectStatusDonutSeries = computed(() => (projects.value?.project_status_overview || []).map((d) => d.count));

// ── Project Type Distribution Horizontal Bar ────────────────────────
const projectTypeOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: "60%" } },
    colors: ["#8b5cf6"],
    xaxis: {
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: {
        categories: (projects.value?.project_type_distribution || []).map((d) => capitalize(d.type)),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} projects` } },
    dataLabels: { enabled: false },
}));

const projectTypeSeries = computed(() => [
    {
        name: "Projects",
        data: (projects.value?.project_type_distribution || []).map((d) => d.count),
    },
]);

// ── Team Productivity Bar Chart ─────────────────────────────────────
const teamProductivityOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { borderRadius: 6, columnWidth: "60%" } },
    colors: ["#10b981"],
    xaxis: {
        categories: (projects.value?.team_productivity || []).map((d) => d.team_name),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} tasks completed` } },
    dataLabels: { enabled: false },
}));

const teamProductivitySeries = computed(() => [
    {
        name: "Completed",
        data: (projects.value?.team_productivity || []).map((d) => d.completed),
    },
]);
</script>

<template>
    <!-- Loading State -->
    <div v-if="projectsLoading" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-for="i in 7" :key="i" class="h-80 bg-gray-100 rounded-2xl animate-pulse" />
        </div>
    </div>

    <!-- Content -->
    <div v-else-if="projects" class="space-y-6">
        <!-- Period Label -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-block w-2 h-2 rounded-full bg-brand-primary"></span>
            {{ projects.period?.label }}
            <span class="text-gray-300">|</span>
            {{ projects.period?.start }} - {{ projects.period?.end }}
        </div>

        <!-- Row 1: Task Velocity + Task Status Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Task Velocity</h3>
                <p class="text-xs text-gray-400 mb-4">Tasks completed per month</p>
                <VueApexCharts
                    v-if="taskVelocitySeries[0]?.data?.length"
                    type="area"
                    height="300"
                    :options="taskVelocityOptions"
                    :series="taskVelocitySeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Task Status Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Breakdown of task statuses</p>
                <VueApexCharts
                    v-if="taskStatusDonutSeries.length"
                    type="donut"
                    height="300"
                    :options="taskStatusDonutOptions"
                    :series="taskStatusDonutSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>
        </div>

        <!-- Row 2: Task Priority + Overdue Tasks KPI -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Task Priority Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Tasks grouped by priority level</p>
                <VueApexCharts
                    v-if="taskPrioritySeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="taskPriorityOptions"
                    :series="taskPrioritySeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Overdue Tasks</h3>
                <p class="text-xs text-gray-400 mb-4">Tasks past their due date</p>
                <div class="flex flex-col items-center justify-center h-64">
                    <span class="text-6xl font-bold" :class="overdueCount > 0 ? 'text-red-500' : 'text-brand-dark'">
                        {{ overdueCount }}
                    </span>
                    <span class="text-sm text-gray-400 mt-2">of {{ totalActiveTasks }} active tasks</span>
                    <span
                        class="text-2xl font-semibold mt-3"
                        :class="overdueCount > 0 ? 'text-red-400' : 'text-gray-300'"
                    >
                        {{ overduePercentage }}%
                    </span>
                    <span class="text-xs text-gray-400 mt-1">overdue rate</span>
                </div>
            </div>
        </div>

        <!-- Row 3: Project Status Overview + Project Type Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Project Status Overview</h3>
                <p class="text-xs text-gray-400 mb-4">Current status of all projects</p>
                <VueApexCharts
                    v-if="projectStatusDonutSeries.length"
                    type="donut"
                    height="300"
                    :options="projectStatusDonutOptions"
                    :series="projectStatusDonutSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Project Type Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Projects grouped by type</p>
                <VueApexCharts
                    v-if="projectTypeSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="projectTypeOptions"
                    :series="projectTypeSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>
        </div>

        <!-- Row 4: Team Productivity -->
        <div class="grid grid-cols-1 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Team Productivity</h3>
                <p class="text-xs text-gray-400 mb-4">Tasks completed by each team</p>
                <VueApexCharts
                    v-if="teamProductivitySeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="teamProductivityOptions"
                    :series="teamProductivitySeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
        <FolderKanbanIcon class="w-16 h-16 mb-4 opacity-50" />
        <p class="text-lg font-medium">Analitik proyek belum tersedia</p>
        <p class="text-sm mt-1">Try adjusting the period or filters</p>
    </div>
</template>
