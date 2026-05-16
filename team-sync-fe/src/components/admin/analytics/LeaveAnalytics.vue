<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import { capitalize } from "@/utils/formatUtils";
import { PalmtreeIcon } from "lucide-vue-next";

const analyticsStore = useAnalyticsStore();
const { leave, leaveLoading } = storeToRefs(analyticsStore);

// ── Monthly Leave Requests Stacked Bar ──────────────────────────────
const monthlyLeaveOptions = computed(() => ({
    chart: {
        type: "bar",
        height: 300,
        stacked: true,
        toolbar: { show: false },
        fontFamily: "Plus Jakarta Sans, sans-serif",
    },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "55%" } },
    colors: ["#10b981", "#ef4444", "#f59e0b"],
    xaxis: {
        categories: (leave.value?.monthly_trend || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    legend: { position: "top", horizontalAlign: "left", fontSize: "12px" },
    tooltip: { y: { formatter: (v) => `${v} requests` } },
    dataLabels: { enabled: false },
}));

const monthlyLeaveSeries = computed(() => {
    const data = leave.value?.monthly_trend || [];
    return [
        { name: "Approved", data: data.map((d) => d.approved) },
        { name: "Rejected", data: data.map((d) => d.rejected) },
        { name: "Pending", data: data.map((d) => d.pending) },
    ];
});

// ── Leave Type Distribution Donut ───────────────────────────────────
const typeColors = ["#3b82f6", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#06b6d4", "#ec4899", "#f97316"];

const typeDonutOptions = computed(() => {
    const data = leave.value?.type_distribution || [];
    return {
        chart: { type: "donut", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
        labels: data.map((d) => capitalize(d.type)),
        colors: data.map((_, i) => typeColors[i % typeColors.length]),
        legend: { position: "bottom", fontSize: "12px" },
        plotOptions: {
            pie: {
                donut: { size: "60%", labels: { show: true, total: { show: true, label: "Total", fontSize: "14px" } } },
            },
        },
        dataLabels: { enabled: false },
    };
});

const typeDonutSeries = computed(() => (leave.value?.type_distribution || []).map((d) => d.count));

// ── Approval Rate Radial Bar ────────────────────────────────────────
const approvalRateOptions = computed(() => ({
    chart: { type: "radialBar", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: {
        radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: { size: "60%" },
            track: { background: "#e2e8f0", strokeWidth: "100%" },
            dataLabels: {
                name: { fontSize: "14px", color: "#94a3b8", offsetY: -10 },
                value: {
                    fontSize: "28px",
                    fontWeight: 700,
                    color: "#1e293b",
                    offsetY: 5,
                    formatter: (v) => `${Math.round(v)}%`,
                },
            },
        },
    },
    colors: ["#10b981"],
    labels: ["Approval Rate"],
    stroke: { lineCap: "round" },
}));

const approvalRateSeries = computed(() => [leave.value?.approval_rate?.approval_percentage ?? 0]);

// ── Average Duration by Type Horizontal Bar ─────────────────────────
const avgDurationOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: "60%" } },
    colors: ["#3b82f6"],
    xaxis: {
        labels: { formatter: (v) => `${v}d`, style: { colors: "#94a3b8" } },
    },
    yaxis: {
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} days` } },
    dataLabels: { enabled: false },
}));

const avgDurationSeries = computed(() => {
    const data = leave.value?.avg_duration_by_type || [];
    return [
        {
            name: "Avg Days",
            data: data.map((d) => ({
                x: capitalize(d.type),
                y: d.avg_days,
            })),
        },
    ];
});

// ── Leave by Department Horizontal Bar ──────────────────────────────
const deptLeaveOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: "60%" } },
    colors: ["#8b5cf6"],
    xaxis: {
        labels: { formatter: (v) => `${v}d`, style: { colors: "#94a3b8" } },
    },
    yaxis: {
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} days` } },
    dataLabels: { enabled: false },
}));

const deptLeaveSeries = computed(() => {
    const data = leave.value?.leave_by_department || [];
    return [
        {
            name: "Total Days",
            data: data.map((d) => ({
                x: capitalize(d.department),
                y: d.total_days,
            })),
        },
    ];
});

// ── Sick Leave Proof Compliance Radial Bar ──────────────────────────
const complianceOptions = computed(() => ({
    chart: { type: "radialBar", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: {
        radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: { size: "60%" },
            track: { background: "#e2e8f0", strokeWidth: "100%" },
            dataLabels: {
                name: { fontSize: "14px", color: "#94a3b8", offsetY: -10 },
                value: {
                    fontSize: "28px",
                    fontWeight: 700,
                    color: "#1e293b",
                    offsetY: 5,
                    formatter: (v) => `${Math.round(v)}%`,
                },
            },
        },
    },
    colors: ["#06b6d4"],
    labels: ["Compliance"],
    stroke: { lineCap: "round" },
}));

const complianceSeries = computed(() => [leave.value?.proof_compliance?.compliance_rate ?? 0]);
</script>

<template>
    <!-- Loading State -->
    <div v-if="leaveLoading" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-for="i in 8" :key="i" class="h-80 bg-gray-100 rounded-2xl animate-pulse" />
        </div>
    </div>

    <!-- Content -->
    <div v-else-if="leave" class="space-y-6">
        <!-- Period Label -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-block w-2 h-2 rounded-full bg-brand-primary"></span>
            {{ leave.period?.label }}
            <span class="text-gray-300">|</span>
            {{ leave.period?.start }} - {{ leave.period?.end }}
        </div>

        <!-- Row 1: Monthly Leave Requests + Leave Type Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Monthly Leave Requests</h3>
                <p class="text-xs text-gray-400 mb-4">Approved, rejected, and pending requests per month</p>
                <VueApexCharts
                    v-if="monthlyLeaveSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="monthlyLeaveOptions"
                    :series="monthlyLeaveSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Leave Type Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Breakdown of leave requests by type</p>
                <VueApexCharts
                    v-if="typeDonutSeries.length"
                    type="donut"
                    height="300"
                    :options="typeDonutOptions"
                    :series="typeDonutSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>
        </div>

        <!-- Row 2: Approval Rate + Average Duration by Type -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Approval Rate</h3>
                <p class="text-xs text-gray-400 mb-4">Overall leave request approval percentage</p>
                <VueApexCharts
                    v-if="leave.approval_rate"
                    type="radialBar"
                    height="300"
                    :options="approvalRateOptions"
                    :series="approvalRateSeries"
                />
                <div v-if="leave.approval_rate" class="flex items-center justify-center gap-6 mt-2">
                    <div class="text-center">
                        <p class="text-lg font-semibold text-emerald-600">{{ leave.approval_rate.approved }}</p>
                        <p class="text-xs text-gray-400">Approved</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-red-500">{{ leave.approval_rate.rejected }}</p>
                        <p class="text-xs text-gray-400">Rejected</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-amber-500">{{ leave.approval_rate.pending }}</p>
                        <p class="text-xs text-gray-400">Pending</p>
                    </div>
                </div>
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Average Duration by Type</h3>
                <p class="text-xs text-gray-400 mb-4">Average number of days per leave type</p>
                <VueApexCharts
                    v-if="avgDurationSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="avgDurationOptions"
                    :series="avgDurationSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>
        </div>

        <!-- Row 3: Leave by Department + Top Leave Takers -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Leave by Department</h3>
                <p class="text-xs text-gray-400 mb-4">Total leave days taken per department</p>
                <VueApexCharts
                    v-if="deptLeaveSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="deptLeaveOptions"
                    :series="deptLeaveSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Top Leave Takers</h3>
                <p class="text-xs text-gray-400 mb-4">Employees with the most leave days</p>
                <div v-if="leave.top_leave_takers?.length" class="space-y-3 mt-2">
                    <div
                        v-for="(emp, idx) in leave.top_leave_takers"
                        :key="emp.employee_code"
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
                        <div class="text-right">
                            <p class="text-sm font-semibold text-amber-600">{{ emp.total_days }}d</p>
                            <p class="text-xs text-gray-400">{{ emp.request_count }} req</p>
                        </div>
                    </div>
                </div>
                <div v-else class="flex items-center justify-center h-48 text-gray-400 text-sm">No leave records</div>
            </div>
        </div>

        <!-- Row 4: Sick Leave Proof Compliance -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Sick Leave Proof Compliance</h3>
                <p class="text-xs text-gray-400 mb-4">Percentage of sick leaves with valid proof</p>
                <VueApexCharts
                    v-if="leave.proof_compliance"
                    type="radialBar"
                    height="300"
                    :options="complianceOptions"
                    :series="complianceSeries"
                />
                <div v-if="leave.proof_compliance" class="flex items-center justify-center gap-6 mt-2">
                    <div class="text-center">
                        <p class="text-lg font-semibold text-cyan-600">{{ leave.proof_compliance.with_proof }}</p>
                        <p class="text-xs text-gray-400">With Proof</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-gray-500">
                            {{ leave.proof_compliance.total_sick_leaves }}
                        </p>
                        <p class="text-xs text-gray-400">Total Sick</p>
                    </div>
                </div>
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">No data</div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
        <PalmtreeIcon class="w-16 h-16 mb-4 opacity-50" />
        <p class="text-lg font-medium">No leave analytics available</p>
        <p class="text-sm mt-1">Try adjusting the period or filters</p>
    </div>
</template>
