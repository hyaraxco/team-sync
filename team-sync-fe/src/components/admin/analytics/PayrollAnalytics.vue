<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import { formatRupiahCompact, formatRupiah, capitalize } from "@/utils/formatUtils";
import { WalletIcon } from "lucide-vue-next";

const analyticsStore = useAnalyticsStore();
const { payroll, payrollLoading } = storeToRefs(analyticsStore);

// ── Total Payroll Cost Trend (Area) ─────────────────────────────────
const costTrendOptions = computed(() => ({
    chart: { type: "area", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#0C51D9", "#ef4444"],
    fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: {
        categories: (payroll.value?.cost_trend || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { formatter: (v) => formatRupiahCompact(v), style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    legend: { position: "top", horizontalAlign: "left", fontSize: "12px" },
    tooltip: { y: { formatter: (v) => formatRupiah(v) } },
    dataLabels: { enabled: false },
}));

const costTrendSeries = computed(() => {
    const data = payroll.value?.cost_trend || [];
    return [
        { name: "Total Salary", data: data.map((d) => d.total_salary) },
        { name: "Total Deductions", data: data.map((d) => d.total_deductions) },
    ];
});

// ── Salary Distribution (Bar) ───────────────────────────────────────
const salaryDistOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { borderRadius: 6, columnWidth: "60%" } },
    colors: ["#8b5cf6"],
    xaxis: {
        categories: (payroll.value?.salary_distribution || []).map((d) => d.range),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => `${v} employees` } },
    dataLabels: { enabled: false },
}));

const salaryDistSeries = computed(() => [
    {
        name: "Employees",
        data: (payroll.value?.salary_distribution || []).map((d) => d.count),
    },
]);

// ── Tax & BPJS Trend (Stacked Area) ────────────────────────────────
const taxBpjsOptions = computed(() => ({
    chart: {
        type: "area",
        height: 300,
        stacked: true,
        toolbar: { show: false },
        fontFamily: "Plus Jakarta Sans, sans-serif",
    },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#f59e0b", "#10b981", "#06b6d4"],
    fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: {
        categories: (payroll.value?.tax_bpjs_trend || []).map((d) => d.month),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: { labels: { formatter: (v) => formatRupiahCompact(v), style: { colors: "#94a3b8" } } },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    legend: { position: "top", horizontalAlign: "left", fontSize: "12px" },
    tooltip: { y: { formatter: (v) => formatRupiah(v) } },
    dataLabels: { enabled: false },
}));

const taxBpjsSeries = computed(() => {
    const data = payroll.value?.tax_bpjs_trend || [];
    return [
        { name: "PPh21", data: data.map((d) => d.pph21) },
        { name: "BPJS TK", data: data.map((d) => d.bpjs_tk) },
        { name: "BPJS Kes", data: data.map((d) => d.bpjs_kes) },
    ];
});

// ── Cost by Department (Horizontal Bar) ─────────────────────────────
const costByDeptOptions = computed(() => ({
    chart: { type: "bar", height: 300, toolbar: { show: false }, fontFamily: "Plus Jakarta Sans, sans-serif" },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: "60%" } },
    colors: ["#0C51D9"],
    xaxis: {
        labels: { formatter: (v) => formatRupiahCompact(v), style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    yaxis: {
        categories: (payroll.value?.cost_by_department || []).map((d) => capitalize(d.department)),
        labels: { style: { colors: "#94a3b8", fontSize: "11px" } },
    },
    grid: { strokeDashArray: 4, borderColor: "#e2e8f0" },
    tooltip: { y: { formatter: (v) => formatRupiah(v) } },
    dataLabels: { enabled: false },
}));

const costByDeptSeries = computed(() => [
    {
        name: "Total Cost",
        data: (payroll.value?.cost_by_department || []).map((d) => d.total_cost),
    },
]);

// ── Deduction Breakdown (Donut) ─────────────────────────────────────
const deductionDonutOptions = computed(() => {
    const data = payroll.value?.deduction_breakdown || [];
    return {
        chart: { type: "donut", height: 300, fontFamily: "Plus Jakarta Sans, sans-serif" },
        labels: data.map((d) => d.category),
        colors: ["#ef4444", "#f59e0b", "#10b981", "#06b6d4"],
        legend: { position: "bottom", fontSize: "12px" },
        plotOptions: {
            pie: {
                donut: {
                    size: "60%",
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: "Total",
                            fontSize: "14px",
                            formatter: (w) => formatRupiahCompact(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
                        },
                    },
                },
            },
        },
        tooltip: { y: { formatter: (v) => formatRupiah(v) } },
        dataLabels: { enabled: false },
    };
});

const deductionDonutSeries = computed(() => (payroll.value?.deduction_breakdown || []).map((d) => d.amount));
</script>

<template>
    <!-- Loading State -->
    <div v-if="payrollLoading" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-for="i in 6" :key="i" class="h-80 bg-gray-100 rounded-2xl animate-pulse" />
        </div>
    </div>

    <!-- Content -->
    <div v-else-if="payroll" class="space-y-6">
        <!-- Period Label -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-block w-2 h-2 rounded-full bg-brand-primary"></span>
            {{ payroll.period?.label }}
            <span class="text-gray-300">|</span>
            {{ payroll.period?.start }} - {{ payroll.period?.end }}
        </div>

        <!-- Row 1: Cost Trend (wide) + Salary Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Total Payroll Cost Trend</h3>
                <p class="text-xs text-gray-400 mb-4">Monthly total salary vs total deductions</p>
                <VueApexCharts
                    v-if="costTrendSeries[0]?.data?.length"
                    type="area"
                    height="300"
                    :options="costTrendOptions"
                    :series="costTrendSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Salary Distribution</h3>
                <p class="text-xs text-gray-400 mb-4">Employee count by salary range</p>
                <VueApexCharts
                    v-if="salaryDistSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="salaryDistOptions"
                    :series="salaryDistSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>
        </div>

        <!-- Row 2: Tax & BPJS Trend + Cost by Department -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Tax & BPJS Trend</h3>
                <p class="text-xs text-gray-400 mb-4">Monthly PPh21, BPJS TK, and BPJS Kesehatan contributions</p>
                <VueApexCharts
                    v-if="taxBpjsSeries[0]?.data?.length"
                    type="area"
                    height="300"
                    :options="taxBpjsOptions"
                    :series="taxBpjsSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>

            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Cost by Department</h3>
                <p class="text-xs text-gray-400 mb-4">Total payroll cost per department</p>
                <VueApexCharts
                    v-if="costByDeptSeries[0]?.data?.length"
                    type="bar"
                    height="300"
                    :options="costByDeptOptions"
                    :series="costByDeptSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>
        </div>

        <!-- Row 3: Deduction Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white border border-brand-border rounded-2xl p-6">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Deduction Breakdown</h3>
                <p class="text-xs text-gray-400 mb-4">Proportion of each deduction category</p>
                <VueApexCharts
                    v-if="deductionDonutSeries.length"
                    type="donut"
                    height="300"
                    :options="deductionDonutOptions"
                    :series="deductionDonutSeries"
                />
                <div v-else class="flex items-center justify-center h-64 text-gray-400 text-sm">Data kosong</div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
        <WalletIcon class="w-16 h-16 mb-4 opacity-50" />
        <p class="text-lg font-medium">Analitik penggajian belum tersedia</p>
        <p class="text-sm mt-1">Try adjusting the period or filters</p>
    </div>
</template>
