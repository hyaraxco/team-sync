<script setup>
import { ref, onMounted, computed } from "vue";
import { usePayrollStore } from "@/stores/payroll";
import { useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { can } from "@/helpers/permissionHelper";
import { DateTime } from "luxon";
import {
    UserCheck,
    Banknote,
    Plus,
    FileText,
    Download,
    FileWarning,
    Settings,
    Calendar,
    Users,
} from "lucide-vue-next";
import Alert from "@/components/common/Alert.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import StatsCard from "@/components/common/StatsCard.vue";
import MainCard from "@/components/common/MainCard.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import { formatRupiah, formatRupiahCompact } from "@/utils/formatUtils";
import { useToast } from "@/composables/useToast";

const router = useRouter();
const payrollStore = usePayrollStore();
const { payrolls, statistics, analytics, loading, loadingAnalytics, success } = storeToRefs(payrollStore);
const hasPayrollStatistics = computed(() => can("payroll-statistics"));
const hasPayrollCreate = computed(() => can("payroll-create"));
const hasPayrollList = computed(() => can("payroll-list"));
const toast = useToast();
const showExportReportModal = ref(false);
const exportFilters = ref({
    report_type: "summary",
    status: "all",
    period_type: "monthly",
    month: new Date().toISOString().slice(0, 7),
    year: String(new Date().getFullYear()),
});

onMounted(async () => {
    if (hasPayrollStatistics.value) {
        await Promise.all([payrollStore.fetchStatistics(), payrollStore.fetchPayrollAnalytics(6)]);
    }

    await payrollStore.fetchPayrolls({ page: 1, row_per_page: 10 });
});

const formatDate = (date) => {
    return DateTime.fromISO(date).setLocale("id").toLocaleString({ year: "numeric", month: "long" });
};

const formatProcessedDate = (date) => {
    return DateTime.fromISO(date).setLocale("id").toLocaleString({ year: "numeric", month: "long", day: "numeric" });
};

const formatSignedPercent = (value) => {
    const numeric = Number(value || 0);
    const sign = numeric > 0 ? "+" : "";

    return `${sign}${numeric.toFixed(2)}%`;
};

const formatPercent = (value) => `${(Number(value || 0) * 100).toFixed(2)}%`;

const analyticsTrendPoints = computed(() => analytics.value?.trends ?? []);
const hasAnalyticsData = computed(() => analyticsTrendPoints.value.length > 0);

// Finance Insights data
const averageSalaryTrend = computed(() => analytics.value?.average_salary_trend ?? []);
const bpjsContributionTrend = computed(() => analytics.value?.bpjs_contribution_trend ?? []);
const topDeductionReasons = computed(() => analytics.value?.top_deduction_reasons ?? []);
const headcountVsPayrollGrowth = computed(() => analytics.value?.headcount_vs_payroll_growth ?? []);

const averageSalaryChartSeries = computed(() => [
    {
        name: "Average Salary",
        data: averageSalaryTrend.value.map((p) => Number(p.average_salary || 0)),
    },
]);

const averageSalaryChartOptions = computed(() => ({
    chart: { type: "area", toolbar: { show: false }, sparkline: { enabled: false } },
    stroke: { width: 2, curve: "smooth" },
    colors: ["#0C51D9"],
    fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    xaxis: {
        categories: averageSalaryTrend.value.map((p) => p.label),
        labels: { style: { fontSize: "11px", colors: "#6B7280" } },
    },
    yaxis: { labels: { formatter: (v) => formatRupiahCompact(v), style: { fontSize: "11px", colors: "#6B7280" } } },
    dataLabels: { enabled: false },
    grid: { borderColor: "#E5E7EB", strokeDashArray: 4 },
    tooltip: { y: { formatter: (v) => formatRupiah(v) } },
}));

const bpjsChartSeries = computed(() => [
    { name: "Employee BPJS", data: bpjsContributionTrend.value.map((p) => Number(p.bpjs_employee_total || 0)) },
    { name: "Employer BPJS", data: bpjsContributionTrend.value.map((p) => Number(p.bpjs_employer_total || 0)) },
]);

const bpjsChartOptions = computed(() => ({
    chart: { type: "bar", toolbar: { show: false }, stacked: true },
    colors: ["#14B8A6", "#0EA5E9"],
    plotOptions: { bar: { borderRadius: 4, columnWidth: "50%" } },
    xaxis: {
        categories: bpjsContributionTrend.value.map((p) => p.label),
        labels: { style: { fontSize: "11px", colors: "#6B7280" } },
    },
    yaxis: { labels: { formatter: (v) => formatRupiahCompact(v), style: { fontSize: "11px", colors: "#6B7280" } } },
    dataLabels: { enabled: false },
    grid: { borderColor: "#E5E7EB", strokeDashArray: 4 },
    legend: { position: "top", horizontalAlign: "right" },
    tooltip: { y: { formatter: (v) => formatRupiah(v) } },
}));

const deductionReasonLabels = { absent: "Absent", half_day: "Half Day", unpaid_leave: "Unpaid Leave" };
const formatDeductionReason = (reason) => deductionReasonLabels[reason] || reason;

const analyticsTrendSeries = computed(() => [
    {
        name: "Total Payroll",
        data: analyticsTrendPoints.value.map((point) => Number(point.total_amount || 0)),
    },
    {
        name: "Total Deductions",
        data: analyticsTrendPoints.value.map((point) => Number(point.total_deductions || 0)),
    },
]);

const analyticsTrendOptions = computed(() => ({
    chart: {
        type: "line",
        toolbar: { show: false },
        animations: {
            enabled: true,
            easing: "easeinout",
            speed: 700,
        },
    },
    stroke: {
        width: [3, 2],
        curve: "smooth",
    },
    colors: ["#0C51D9", "#14B8A6"],
    xaxis: {
        categories: analyticsTrendPoints.value.map((point) => point.label),
        labels: {
            style: {
                colors: "#6B7280",
                fontSize: "12px",
            },
        },
        axisBorder: {
            show: false,
        },
        axisTicks: {
            show: false,
        },
    },
    yaxis: {
        labels: {
            style: {
                colors: "#6B7280",
                fontSize: "12px",
            },
            formatter: (value) => formatRupiahCompact(value),
        },
    },
    grid: {
        borderColor: "#E5E7EB",
        strokeDashArray: 4,
    },
    dataLabels: {
        enabled: false,
    },
    legend: {
        position: "top",
        horizontalAlign: "right",
    },
    markers: {
        size: 4,
        strokeWidth: 2,
        hover: {
            size: 6,
        },
    },
    tooltip: {
        shared: true,
        y: {
            formatter: (value) => formatRupiah(value),
        },
    },
}));

const getStatusColor = (status) => {
    const colors = {
        draft: "bg-gray-100 text-gray-800",
        pending: "bg-yellow-100 text-yellow-800",
        approved: "bg-blue-100 text-blue-800",
        finalized: "bg-green-100 text-green-800",
        rejected: "bg-red-100 text-red-800",
    };
    return colors[status] || colors.draft;
};

const viewDetails = (id) => {
    router.push({ name: "admin.payroll.detail", params: { id } });
};

const openExportReportModal = () => {
    showExportReportModal.value = true;
};

const closeExportReportModal = () => {
    showExportReportModal.value = false;
};

const handleExportReport = async () => {
    try {
        await payrollStore.exportPayrollReport({
            report_type: exportFilters.value.report_type,
            status: exportFilters.value.status,
            period_type: exportFilters.value.period_type,
            month: exportFilters.value.period_type === "monthly" ? exportFilters.value.month : undefined,
            year: exportFilters.value.period_type === "yearly" ? exportFilters.value.year : undefined,
        });
        toast.success("Report exported", "Payroll report downloaded successfully.");
        closeExportReportModal();
    } catch (_error) {
        toast.error("Export report failed", payrollStore.error || "Failed to export payroll report. Please try again.");
    }
};
</script>

<template>
    <div>
        <h1 class="text-2xl font-semibold text-brand-dark">Dashboard Payroll</h1>
        <template v-if="hasPayrollStatistics">
            <!-- Stats Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Payroll Amount Card (spans 2 rows on the left) -->
                <MainCard
                    class="lg:row-span-2"
                    title="Total Payroll Amount"
                    :value="formatRupiahCompact(statistics.total_amount)"
                    subtitle="Monthly compensation"
                    iconName="DollarSign"
                    trendLabel="+5.2% this month"
                    :isTrendUp="true"
                    :loading="loading"
                >

                </MainCard>

                <!-- Row 1 Stats Cards -->
                <StatsCard
                    title="Employees Paid"
                    :value="statistics.total_payroll"
                    subtitle="This month"
                    subtitleColor="text-success"
                    iconName="UserCheck"
                    colorScheme="green"
                    :loading="loading"
                />

                <StatsCard
                    title="Pending Payments"
                    :value="statistics.pending_review"
                    subtitle="Need approval"
                    subtitleColor="text-danger"
                    iconName="Clock"
                    colorScheme="red"
                    :loading="loading"
                />

                <!-- Quick Actions Card (spans 2 rows on the right) -->
                <div
                    class="lg:row-span-2 bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-6"
                >
                    <h2 class="text-lg font-semibold text-brand-dark mb-4">Aksi Payroll</h2>
                    <div class="space-y-3">
                        <RouterLink
                            v-if="hasPayrollCreate"
                            :to="{ name: 'admin.payroll.create' }"
                            class="btn-secondary w-full text-left rounded-xl hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
                        >
                            <Plus class="w-4 h-4 text-white" />
                            <span class="text-brand-white text-sm font-semibold">Buat Payroll Baru</span>
                        </RouterLink>

                        <RouterLink
                            v-if="hasPayrollCreate"
                            :to="{ name: 'admin.payroll.readiness' }"
                            data-testid="payroll-readiness-link"
                            class="w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <UserCheck class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-medium">Readiness Dashboard</span>
                        </RouterLink>

                        <button
                            v-if="hasPayrollStatistics && hasPayrollList"
                            type="button"
                            data-testid="payroll-export-report-open"
                            @click="openExportReportModal"
                            class="btn-secondary w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <Download class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-medium">Export Payroll Report</span>
                        </button>

                        <RouterLink
                            v-if="hasPayrollStatistics"
                            :to="{ name: 'admin.payroll.comparison' }"
                            data-testid="payroll-comparison-link"
                            class="w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <Banknote class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-medium">MoM Comparison</span>
                        </RouterLink>

                        <RouterLink
                            v-if="hasPayrollList"
                            :to="{ name: 'admin.payroll.adjustments' }"
                            data-testid="payroll-adjustment-queue-link"
                            class="w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <FileWarning class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-medium">Adjustment Queue</span>
                        </RouterLink>

                        <RouterLink
                            v-if="hasPayrollStatistics"
                            :to="{ name: 'admin.payroll.settings' }"
                            data-testid="payroll-settings-link"
                            class="w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <Settings class="w-4 h-4 text-gray-400" />
                            <div class="flex items-center justify-between w-full gap-2">
                                <div class="flex flex-col items-start">
                                    <span class="text-brand-dark text-sm font-medium">Payroll Settings</span>
                                    <span class="text-xs font-normal text-gray-500">
                                        Finance only • Configure payroll rules
                                    </span>
                                </div>
                            </div>
                        </RouterLink>
                    </div>
                </div>

                <!-- Row 2 Stats Cards -->
                <StatsCard
                    title="Average Salary"
                    :value="formatRupiahCompact(statistics.average_salary)"
                    :subtitle="`+${formatRupiahCompact(1900000)} from last month`"
                    subtitleColor="text-success"
                    iconName="Banknote"
                    colorScheme="blue"
                    :loading="loading"
                />

                <StatsCard
                    title="Finalized"
                    :value="statistics.finalized"
                    subtitle="This month"
                    subtitleColor="text-purple-600"
                    iconName="Clock"
                    colorScheme="purple"
                    :loading="loading"
                />
            </div>

            <div
                data-testid="payroll-analytics-section"
                class="bg-white border border-brand-border rounded-2xl p-6"
            >
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-dark">Payroll Analytics (Last 6 Periods)</h2>
                        <p class="text-brand-light text-sm font-normal">
                            Finance insight from approved and paid payroll periods.
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-brand-light">As of</p>
                        <p class="text-sm font-semibold text-brand-dark">
                            {{
                                analytics.reporting_period?.as_of_timestamp
                                    ? new Date(analytics.reporting_period.as_of_timestamp).toLocaleDateString("id-ID")
                                    : "-"
                            }}
                        </p>
                    </div>
                </div>

                <div v-if="loadingAnalytics" class="py-10 text-center text-brand-light text-sm">
                    Loading payroll analytics...
                </div>

                <template v-else-if="hasAnalyticsData">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                        <div
                            class="border border-brand-border rounded-2xl p-4"
                            data-testid="payroll-analytics-total-amount"
                        >
                            <p class="text-xs uppercase tracking-wide text-brand-light mb-1">Total Amount</p>
                            <p class="text-brand-dark text-lg font-bold tabular-nums">
                                {{ formatRupiahCompact(analytics.summary.total_amount) }}
                            </p>
                        </div>
                        <div
                            class="border border-brand-border rounded-2xl p-4"
                            data-testid="payroll-analytics-deduction-rate"
                        >
                            <p class="text-xs uppercase tracking-wide text-brand-light mb-1">Average Deduction Rate</p>
                            <p class="text-brand-dark text-lg font-bold">
                                {{ formatPercent(analytics.summary.average_deduction_rate) }}
                            </p>
                        </div>
                        <div class="border border-brand-border rounded-2xl p-4" data-testid="payroll-analytics-growth">
                            <p class="text-xs uppercase tracking-wide text-brand-light mb-1">Salary Growth</p>
                            <p class="text-brand-dark text-lg font-bold">
                                {{ formatSignedPercent(analytics.growth_metrics.salary_growth_percentage) }}
                            </p>
                        </div>
                    </div>

                    <VueApexCharts
                        data-testid="payroll-analytics-chart"
                        type="line"
                        height="280"
                        :options="analyticsTrendOptions"
                        :series="analyticsTrendSeries"
                    />
                </template>

                <EmptyState v-else data-testid="payroll-analytics-empty" icon="FileText" title="No analytics data yet" subtitle="Generate your first payroll to see analytics here." size="lg" />
            </div>

            <!-- Finance Insights Section -->
            <div v-if="hasAnalyticsData" data-testid="payroll-finance-insights" class="space-y-6">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-brand-dark">Finance Insights</h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Average Salary Trend -->
                    <div
                        data-testid="payroll-insights-avg-salary"
                        class="bg-white border border-brand-border rounded-2xl p-6"
                    >
                        <h3 class="text-base font-semibold text-brand-dark mb-3">Average Salary Trend</h3>
                        <VueApexCharts
                            type="area"
                            height="200"
                            :options="averageSalaryChartOptions"
                            :series="averageSalaryChartSeries"
                        />
                    </div>

                    <!-- BPJS Contribution Trend -->
                    <div
                        data-testid="payroll-insights-bpjs"
                        class="bg-white border border-brand-border rounded-2xl p-6"
                    >
                        <h3 class="text-base font-semibold text-brand-dark mb-3">BPJS Contribution Trend</h3>
                        <VueApexCharts type="bar" height="200" :options="bpjsChartOptions" :series="bpjsChartSeries" />
                    </div>

                    <!-- Top Deduction Reasons -->
                    <div
                        data-testid="payroll-insights-deduction-reasons"
                        class="bg-white border border-brand-border rounded-2xl p-6"
                    >
                        <h3 class="text-base font-semibold text-brand-dark mb-3">Top Deduction Reasons</h3>
                        <div class="space-y-3">
                            <div
                                v-for="reason in topDeductionReasons"
                                :key="reason.reason"
                                class="flex items-center justify-between"
                            >
                                <span class="text-brand-dark text-sm font-medium">
                                    {{ formatDeductionReason(reason.reason) }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <div class="w-32 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-blue-500 rounded-full"
                                            :style="{
                                                width: `${Math.min(100, (reason.days / Math.max(1, topDeductionReasons[0]?.days || 1)) * 100)}%`,
                                            }"
                                        ></div>
                                    </div>
                                    <span class="text-brand-light text-xs font-semibold w-12 text-right">
                                        {{ reason.days }}d
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Headcount vs Payroll Growth -->
                    <div
                        data-testid="payroll-insights-headcount"
                        class="bg-white border border-brand-border rounded-2xl p-6"
                    >
                        <h3 class="text-base font-semibold text-brand-dark mb-3">Headcount vs Payroll</h3>
                        <div class="space-y-2">
                            <div
                                v-for="point in headcountVsPayrollGrowth"
                                :key="point.salary_month"
                                class="flex items-center justify-between border-b border-gray-100 pb-2 last:border-0"
                            >
                                <span class="text-brand-dark text-sm">{{ point.label }}</span>
                                <div class="flex items-center gap-4">
                                    <span class="text-brand-light text-xs">{{ point.employee_count }} staff</span>
                                    <span class="text-brand-dark text-sm font-semibold tabular-nums">
                                        {{ formatRupiahCompact(point.total_amount) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div
                    class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <FileText class="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-brand-dark">Payroll Operations</h2>
                            <p class="text-brand-light text-sm font-normal">
                                Prepare payroll drafts from validated attendance and employee data.
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="border border-brand-border rounded-2xl p-4">
                            <p class="text-brand-dark text-sm font-semibold mb-1">Your responsibility</p>
                            <p class="text-brand-light text-sm">
                                Generate payroll drafts and monitor each period until Finance finalizes payment.
                            </p>
                        </div>
                        <div class="border border-brand-border rounded-2xl p-4">
                            <p class="text-brand-dark text-sm font-semibold mb-1">Restricted data</p>
                            <p class="text-brand-light text-sm">
                                Company-wide payroll statistics stay hidden in this view to keep salary reporting with
                                Finance.
                            </p>
                        </div>
                    </div>
                </div>

        <div class="bg-white border border-brand-border rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-brand-dark mb-4">Aksi Payroll</h2>
                    <div class="space-y-3">
                        <RouterLink
                            v-if="hasPayrollCreate"
                            :to="{ name: 'admin.payroll.create' }"
                            class="btn-secondary w-full text-left rounded-xl hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
                        >
                            <Plus class="w-4 h-4 text-white" />
                            <span class="text-brand-white text-sm font-semibold">Buat Payroll Baru</span>
                        </RouterLink>
                        <RouterLink
                            v-if="hasPayrollCreate"
                            :to="{ name: 'admin.payroll.readiness' }"
                            data-testid="payroll-readiness-link-alt"
                            class="w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <UserCheck class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-medium">Readiness Dashboard</span>
                        </RouterLink>
                        <div class="border border-brand-border rounded-2xl px-4 py-3">
                            <p class="text-brand-dark text-sm font-semibold">Draft monitoring</p>
                            <p class="text-brand-light text-xs mt-1">
                                Review payroll periods below and open details when a draft is ready.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <Alert type="success" :title="success || ''" :message="success || ''" :show="Boolean(success)" />

        <!-- Monthly Payroll Periods -->
                <div class="bg-white border border-brand-border rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-brand-dark mb-4">Aksi Payroll</h2>
                    <div class="space-y-3">
                        <RouterLink
                            v-if="hasPayrollCreate"
                            :to="{ name: 'admin.payroll.create' }"
                            class="btn-secondary w-full text-left rounded-xl hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
                        >
                            <Plus class="w-4 h-4 text-white" />
                            <span class="text-brand-white text-sm font-semibold">Buat Payroll Baru</span>
                        </RouterLink>
                        <RouterLink
                            v-if="hasPayrollCreate"
                            :to="{ name: 'admin.payroll.readiness' }"
                            data-testid="payroll-readiness-link-alt"
                            class="w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2"
                        >
                            <UserCheck class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-medium">Readiness Dashboard</span>
                        </RouterLink>
                    </div>
                </div>
            <div class="space-y-4">
                <div
                    v-for="payroll in payrolls"
                    :key="payroll.id"
                    :data-testid="`payroll-row-${payroll.id}`"
                    :data-payroll-period="payroll.period"
                    class="flex items-center gap-4 p-4 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300"
                >
                    <div class="w-16 h-16 flex items-center justify-center bg-brand-primary rounded-xl">
                        <Calendar class="w-8 h-8 text-white" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-brand-dark text-lg font-bold">
                                {{ formatDate(payroll.period) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Users class="w-4 h-4 text-gray-600" />
                            <p class="text-brand-dark text-sm font-normal">
                                {{ payroll.employee_count || 0 }} employees • All departments
                            </p>
                        </div>
                        <p class="text-brand-light text-xs font-normal mt-1">
                            Processed on {{ formatProcessedDate(payroll.created_at) }}
                        </p>
                    </div>
                    <div class="flex flex-col justify-center items-center gap-1.5">
                        <StatusBadge type="payroll" :value="payroll.status" />
                        <div
                            v-if="
                                payroll.reconciliation_summary &&
                                (payroll.reconciliation_summary.unresolved_critical_count > 0 ||
                                    payroll.reconciliation_summary.warning_count > 0)
                            "
                            class="flex items-center gap-1.5"
                            data-testid="payroll-reconciliation-badge"
                        >
                            <span
                                v-if="payroll.reconciliation_summary.unresolved_critical_count > 0"
                                class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700"
                            >
                                {{ payroll.reconciliation_summary.unresolved_critical_count }} critical
                            </span>
                            <span
                                v-if="payroll.reconciliation_summary.warning_count > 0"
                                class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                            >
                                {{ payroll.reconciliation_summary.warning_count }} warnings
                            </span>
                        </div>
                    </div>
                    <div v-if="hasPayrollStatistics" class="flex-1 flex flex-col justify-center items-center">
                        <div class="text-left">
                            <p class="text-brand-dark text-lg font-bold tabular-nums">
                                {{ formatRupiahCompact(payroll.total_amount) }}
                            </p>
                            <p class="text-brand-light text-sm font-normal">Total payroll</p>
                        </div>
                    </div>
                    <button
                        @click="viewDetails(payroll.id)"
                        :data-testid="`payroll-detail-btn-${payroll.id}`"
                        class="btn-details border border-brand-border rounded-xl hover:ring-2 hover:ring-brand-primary hover:text-brand-primary transition-all duration-300 py-[14px] px-5 flex items-center justify-center"
                    >
                        <span class="text-brand-dark text-base font-medium">Details</span>
                    </button>
                </div>

                <EmptyState v-if="!loading && payrolls.length === 0" icon="Inbox" title="No payroll data found" subtitle="Create your first payroll to get started" />
            </div>
        </div>

        <ModalWrapper
            :show="showExportReportModal"
            title="Export Payroll Report"
            maxWidth="md"
            @close="closeExportReportModal"
        >
            <div class="space-y-4">
                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Report Type</label>
                    <select
                        v-model="exportFilters.report_type"
                        data-testid="payroll-report-type"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-brand-primary focus:border-brand-primary focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    >
                        <option value="summary">Summary</option>
                        <option value="detail">Detail per Employee</option>
                    </select>
                </div>

                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Status</label>
                    <select
                        v-model="exportFilters.status"
                        data-testid="payroll-report-status"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-brand-primary focus:border-brand-primary focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    >
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>

                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Period Type</label>
                    <select
                        v-model="exportFilters.period_type"
                        data-testid="payroll-report-period-type"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-brand-primary focus:border-brand-primary focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    >
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>

                <div v-if="exportFilters.period_type === 'monthly'">
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Month</label>
                    <input
                        v-model="exportFilters.month"
                        type="month"
                        data-testid="payroll-report-month"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-brand-primary focus:border-brand-primary focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    />
                </div>

                <div v-else>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Year</label>
                    <input
                        v-model="exportFilters.year"
                        type="number"
                        min="2000"
                        max="2100"
                        data-testid="payroll-report-year"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-brand-primary focus:border-brand-primary focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="handleExportReport"
                        data-testid="payroll-report-submit"
                        class="flex-1 btn-primary rounded-xl hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2"
                    >
                        <Download class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">
                            Export {{ exportFilters.report_type === "detail" ? "Detail" : "Report" }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="closeExportReportModal"
                        class="flex-1 border border-brand-border rounded-xl hover:border-brand-primary hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Cancel</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>
</template>
