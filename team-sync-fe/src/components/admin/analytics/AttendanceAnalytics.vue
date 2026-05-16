<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import { capitalize } from "@/utils/formatUtils";
import { CalendarCheckIcon } from "lucide-vue-next";

const analyticsStore = useAnalyticsStore();
const { attendance, attendanceLoading } = storeToRefs(analyticsStore);

// ── Monthly Attendance Rate Line Chart ──────────────────────────────
const monthlyRateOptions = computed(() => ({
    chart: { type: "line", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    stroke: { width: 3, curve: "smooth" },
    colors: ["#0C51D9"],
    xaxis: {
        categories: (attendance.value?.monthly_attendance_rate || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { formatter: (v) => `${v}%`, style: { colors: "#94a3b8" } }, min: 0, max: 100 },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    markers: { size: 4, hover: { size: 6 } },
    tooltip: { y: { formatter: (v) => `${v}%` } },
    dataLabels: { enabled: false },
}));

const monthlyRateSeries = computed(() => [
    {
        name: "Attendance Rate",
        data: (attendance.value?.monthly_attendance_rate || []).map((d) => d.attendance_rate),
    },
]);

// ── Status Distribution Donut ───────────────────────────────────────
const statusColors = {
    present: "#10b981",
    late: "#f59e0b",
    absent: "#ef4444",
    half_day: "#8b5cf6",
    sick_leave: "#06b6d4",
    annual_leave: "#3b82f6",
};

const statusDonutOptions = computed(() => {
    const data = attendance.value?.status_distribution || [];
    return {
        chart: { type: "donut", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
        labels: data.map((d) => capitalize(d.status)),
        colors: data.map((d) => statusColors[d.status] || "#94a3b8"),
        legend: { position: "bottom", fontSize: "12px" },
        plotOptions: {
            pie: {
                donut: { size: "60%", labels: { show: true, total: { show: true, label: "Total", fontSize: "14px" } } },
            },
        },
        dataLabels: { enabled: false },
    };
});

const statusDonutSeries = computed(() => (attendance.value?.status_distribution || []).map((d) => d.count));

// ── Weekly Lateness Bar Chart ───────────────────────────────────────
const latenessOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "60%" } },
    colors: ["#f59e0b"],
    xaxis: {
        categories: (attendance.value?.lateness_trend || []).map((d) => d.week),
        labels: { style: { colors: "#94a3b8", fontSize: "10px" }, rotate: -45 },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} late arrivals` } },
    dataLabels: { enabled: false },
}));

const latenessSeries = computed(() => [
    {
        name: "Late Count",
        data: (attendance.value?.lateness_trend || []).map((d) => d.late_count),
    },
]);

// ── Average Hours Worked Line Chart ─────────────────────────────────
const avgHoursOptions = computed(() => ({
    chart: { type: "area", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#10b981"],
    fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: {
        categories: (attendance.value?.avg_hours_trend || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { formatter: (v) => `${v}h`, style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} hours` } },
    dataLabels: { enabled: false },
}));

const avgHoursSeries = computed(() => [
    {
        name: "Avg Hours",
        data: (attendance.value?.avg_hours_trend || []).map((d) => d.avg_hours),
    },
]);

// ── Work Mode Stacked Bar ───────────────────────────────────────────
const workModeOptions = computed(() => ({
    chart: {
        type: "bar",
        height: 300,
        stacked: true,
        toolbar: { show: false },
        fontFamily: "Plus Jakarta Sans, sans-serif",
    },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "55%" } },
    colors: ["#0C51D9", "#8b5cf6", "#10b981"],
    xaxis: {
        categories: (attendance.value?.work_mode_distribution || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    legend: { position: "top", horizontalAlign: "left", fontSize: "12px" },
    dataLabels: { enabled: false },
}));

const workModeSeries = computed(() => {
    const data = attendance.value?.work_mode_distribution || [];
    return [
        { name: "Office", data: data.map((d) => d.office) },
        { name: "Remote", data: data.map((d) => d.remote) },
        { name: "Hybrid", data: data.map((d) => d.hybrid) },
    ];
});

// ── Policy Mismatch Trend ───────────────────────────────────────────
const mismatchOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "55%" } },
    colors: ["#ef4444", "#10b981"],
    xaxis: {
        categories: (attendance.value?.policy_mismatch_trend || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    legend: { position: "top", horizontalAlign: "left", fontSize: "12px" },
    dataLabels: { enabled: false },
}));

const mismatchSeries = computed(() => {
    const data = attendance.value?.policy_mismatch_trend || [];
    return [
        { name: "Total Mismatches", data: data.map((d) => d.total) },
        { name: "Resolved", data: data.map((d) => d.resolved) },
    ];
});

// ── Correction Request Trend ────────────────────────────────────────
const correctionOptions = computed(() => ({
    chart: { type: "line", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    stroke: { width: [3, 3, 2], curve: "smooth", dashArray: [0, 0, 5] },
    colors: ["#0C51D9", "#10b981", "#ef4444"],
    xaxis: {
        categories: (attendance.value?.correction_trend || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    legend: { position: "top", horizontalAlign: "left", fontSize: "12px" },
    markers: { size: 3, hover: { size: 5 } },
    dataLabels: { enabled: false },
}));

const correctionSeries = computed(() => {
    const data = attendance.value?.correction_trend || [];
    return [
        { name: "Total Requests", data: data.map((d) => d.total) },
        { name: "Approved", data: data.map((d) => d.approved) },
        { name: "Rejected", data: data.map((d) => d.rejected) },
    ];
});
</script>

<template>
    <!-- Loading State -->
    <div v-if="attendanceLoading" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-for="i in 8" :key="i" class="h-80 bg-gray-100 rounded-2xl animate-pulse" />
        </div>
    </div>

    <!-- Content -->
    <div v-else-if="attendance" class="space-y-6">
        <!-- Period Label -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-block w-2 h-2 rounded-full bg-brand-primary"></span>
            {{ attendance.period?.label }}
            <span class="text-gray-300">|</span>
            {{ attendance.period?.start }} - {{ attendance.period?.end }}
        </div>

        <!-- Row 1: Monthly Rate + Status Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Monthly Attendance Rate</h3>
                <p class="text-xs text-gray-400 mb-4">Percentage of employees present (including late & half-day)</p>
                <VueApexCharts
                    v-if="monthlyRateSeries[0]?.data?.length"
                    type="line"
                    height="300"
                    :options="monthlyRateOptions"
                    :series="monthlyRateSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Status Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Breakdown of attendance statuses</p>
                <VueApexCharts
                    v-if="statusDonutSeries.length"
                    type="donut"
                    height="300"
                    :options="statusDonutOptions"
                    :series="statusDonutSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>
        </div>

        <!-- Row 2: Lateness Trend + Average Hours -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Weekly Lateness Trend</h3>
                <p class="text-xs text-gray-400 mb-4">Number of late arrivals per week</p>
                <VueApexCharts
                    v-if="latenessSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="latenessOptions"
                    :series="latenessSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Average Hours Worked</h3>
                <p class="text-xs text-gray-400 mb-4">Monthly average working hours per employee</p>
                <VueApexCharts
                    v-if="avgHoursSeries[0]?.data?.length"
                    type="area"
                    height="300"
                    :options="avgHoursOptions"
                    :series="avgHoursSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>
        </div>

        <!-- Row 3: Work Mode + Top Late Employees -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Work Mode Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Office vs Remote vs Hybrid attendance by month</p>
                <VueApexCharts
                    v-if="workModeSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="workModeOptions"
                    :series="workModeSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Top Late Employees</h3>
                <p class="text-xs text-gray-400 mb-4">Most frequent late arrivals</p>
                <div v-if="attendance.top_late_employees?.length" class="space-y-3 mt-2">
                    <div
                        v-for="(emp, idx) in attendance.top_late_employees"
                        :key="emp.staff_member_id"
                        class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold"
                                :class="idx < 3 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ idx + 1 }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-brand-dark">{{ emp.employee_name }}</p>
                                <p class="text-xs text-gray-400">{{ emp.employee_code }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-amber-600">{{ emp.late_count }}x</span>
                    </div>
                </div>
                <div v-else class="flex items-center justify-center h-48 text-gray-400 text-sm">No late records</div>
            </div>
        </div>

        <!-- Row 4: Policy Mismatch + Correction Requests -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Policy Mismatch Trend</h3>
                <p class="text-xs text-gray-400 mb-4">Work mode mismatches vs resolved cases</p>
                <VueApexCharts
                    v-if="mismatchSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="mismatchOptions"
                    :series="mismatchSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Correction Requests</h3>
                <p class="text-xs text-gray-400 mb-4">Attendance correction submissions and approval rate</p>
                <VueApexCharts
                    v-if="correctionSeries[0]?.data?.length"
                    type="line"
                    height="300"
                    :options="correctionOptions"
                    :series="correctionSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
        <CalendarCheckIcon class="w-16 h-16 mb-4 opacity-50" />
        <p class="text-lg font-medium">No attendance analytics available</p>
        <p class="text-sm mt-1">Try adjusting the period or filters</p>
    </div>
</template>
