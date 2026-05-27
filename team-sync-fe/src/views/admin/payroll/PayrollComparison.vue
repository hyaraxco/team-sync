<script setup>
import { ref, onMounted } from "vue";
import { usePayrollStore } from "@/stores/payroll";
import { storeToRefs } from "pinia";
import { formatRupiah } from "@/utils/formatUtils";
import { ArrowLeft, TrendingUp, TrendingDown, Minus, AlertCircle } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const payrollStore = usePayrollStore();
const { payrollComparison, loadingAnalytics, error } = storeToRefs(payrollStore);

const getYearMonthString = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    return `${year}-${month}`;
};

const now = new Date();
const currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
const previousMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);

const month1 = ref(getYearMonthString(previousMonth));
const month2 = ref(getYearMonthString(currentMonth));

const loadComparison = async () => {
    if (!month1.value || !month2.value) return;
    await payrollStore.fetchPayrollComparison(month1.value, month2.value);
};

onMounted(() => {
    loadComparison();
});

const getVarianceColor = (metric, diff, _pct) => {
    if (diff === 0) return "text-gray-500";

    // Higher is generally "worse" for cost from company perspective, but we'll highlight green for positive variance for most, except deductions
    if (metric === "deductions" || metric === "tax_amount") {
        return diff > 0 ? "text-red-500" : "text-green-500";
    }

    // For net_salary, gross_salary, bpjs:
    return diff > 0 ? "text-green-500" : "text-red-500";
};

const getVarianceIcon = (diff) => {
    if (diff > 0) return TrendingUp;
    if (diff < 0) return TrendingDown;
    return Minus;
};

const formatValue = (metric, value) => {
    if (metric === "employee_count") return value;
    return formatRupiah(value);
};

const metrics = [
    { key: "employee_count", label: "Employee Count" },
    { key: "gross_salary", label: "Gross Salary" },
    { key: "allowances", label: "Allowances" },
    { key: "deductions", label: "Deductions" },
    { key: "bpjs_deductions", label: "BPJS Deductions (Employee)" },
    { key: "bpjs_employer", label: "BPJS Employer" },
    { key: "tax_amount", label: "PPh 21 Tax" },
    { key: "net_salary", label: "Net Salary" },
];
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex flex-col">
                <div class="flex items-center gap-2 mb-1">
                    <RouterLink :to="{ name: 'admin.payroll.dashboard' }" class="text-gray-500 hover:text-gray-700">
                        <ArrowLeft class="w-5 h-5" />
                    </RouterLink>
                    <h1 class="text-2xl font-bold text-gray-900">Month-over-Month Comparison</h1>
                </div>
                <p class="text-sm text-gray-500 ml-7">Compare payroll expenditures between two periods.</p>
            </div>
        </div>

        <MainCard>
            <div class="flex flex-col sm:flex-row items-end gap-4 mb-6">
                <div class="w-full sm:w-1/3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Base Month (Month 1)</label>
                    <input
                        type="month"
                        v-model="month1"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-brand-primary focus:border-brand-primary"
                    />
                </div>
                <div class="w-full sm:w-1/3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compare Month (Month 2)</label>
                    <input
                        type="month"
                        v-model="month2"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-brand-primary focus:border-brand-primary"
                    />
                </div>
                <div class="w-full sm:w-1/3 pb-0.5">
                    <button
                        @click="loadComparison"
                        :disabled="loadingAnalytics"
                        class="btn-primary w-full justify-center"
                    >
                        <span v-if="loadingAnalytics">Loading...</span>
                        <span v-else>Compare</span>
                    </button>
                </div>
            </div>

            <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-2">
                <AlertCircle class="w-5 h-5 flex-shrink-0 mt-0.5" />
                <div>
                    <h3 class="font-medium">Failed to load comparison</h3>
                    <p class="text-sm">{{ error }}</p>
                </div>
            </div>

            <div v-else-if="payrollComparison && !loadingAnalytics" class="overflow-x-auto">
                <EmptyState v-if="!payrollComparison.month1.found && !payrollComparison.month2.found" icon="Inbox" title="No payroll data found" />
                <div
                    v-else-if="!payrollComparison.month1.found"
                    class="text-center py-4 text-orange-600 bg-orange-50 rounded mb-4"
                >
                    Warning: No payroll data found for {{ payrollComparison.month1.period }}.
                </div>
                <div
                    v-else-if="!payrollComparison.month2.found"
                    class="text-center py-4 text-orange-600 bg-orange-50 rounded mb-4"
                >
                    Warning: No payroll data found for {{ payrollComparison.month2.period }}.
                </div>

                <table
                    v-if="payrollComparison.month1.found || payrollComparison.month2.found"
                    class="w-full text-left border-collapse"
                >
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-3 px-4 font-semibold text-gray-700 bg-gray-50 rounded-tl-lg">Metric</th>
                            <th class="py-3 px-4 font-semibold text-gray-700 bg-gray-50">
                                {{ payrollComparison.month1.period }}
                            </th>
                            <th class="py-3 px-4 font-semibold text-gray-700 bg-gray-50">
                                {{ payrollComparison.month2.period }}
                            </th>
                            <th class="py-3 px-4 font-semibold text-gray-700 bg-gray-50 rounded-tr-lg">Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="metric in metrics" :key="metric.key" class="hover:bg-gray-50 transition-colors">
                            <td class="py-4 px-4 font-medium text-gray-900">{{ metric.label }}</td>
                            <td class="py-4 px-4 text-gray-600">
                                {{ formatValue(metric.key, payrollComparison.month1[metric.key]) }}
                            </td>
                            <td class="py-4 px-4 text-gray-600">
                                {{ formatValue(metric.key, payrollComparison.month2[metric.key]) }}
                            </td>
                            <td class="py-4 px-4">
                                <div
                                    class="flex items-center gap-1 font-medium"
                                    :class="
                                        getVarianceColor(
                                            metric.key,
                                            payrollComparison.variances[metric.key].difference,
                                            payrollComparison.variances[metric.key].percentage,
                                        )
                                    "
                                >
                                    <component
                                        :is="getVarianceIcon(payrollComparison.variances[metric.key].difference)"
                                        class="w-4 h-4"
                                    />
                                    <span>
                                        {{
                                            formatValue(
                                                metric.key,
                                                Math.abs(payrollComparison.variances[metric.key].difference),
                                            )
                                        }}
                                    </span>
                                    <span class="text-xs opacity-75 ml-1">
                                        ({{ payrollComparison.variances[metric.key].percentage > 0 ? "+" : ""
                                        }}{{ payrollComparison.variances[metric.key].percentage }}%)
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="loadingAnalytics" class="py-12 flex justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>
        </MainCard>
    </div>
</template>
