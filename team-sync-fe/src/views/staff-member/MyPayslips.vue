<script setup>
import { ref, onMounted, watch, computed } from "vue";
import { usePayrollStore } from "@/stores/payroll";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { debounce } from "lodash";
import {
    Search,
    Download,
    Eye,
    Calendar,
    DollarSign,
    FileText,
    TrendingUp,
    ArrowUpRight,
    ArrowDownRight,
    X,
} from "lucide-vue-next";
import Pagination from "@/components/admin/team/Pagination.vue";
import MainCard from "@/components/common/MainCard.vue";
import { useToast } from "@/composables/useToast";

const router = useRouter();
const payrollStore = usePayrollStore();
const toast = useToast();
const { payslips, meta, loading } = storeToRefs(payrollStore);
const { fetchMyPayslips, downloadPayslip } = payrollStore;

const serverOptions = ref({
    page: 1,
    row_per_page: 12,
});

const filters = ref({
    search: null,
    year: new Date().getFullYear(),
});

const currentYear = new Date().getFullYear();

const fetchData = async () => {
    await fetchMyPayslips({
        ...serverOptions.value,
        ...filters.value,
    });
};

onMounted(async () => {
    await fetchData();
});

watch(
    filters,
    debounce(() => {
        serverOptions.value.page = 1;
        fetchData();
    }, 300),
    { deep: true },
);

const handlePageChange = (page) => {
    serverOptions.value.page = page;
    fetchData();
};

const handlePerPageChange = (perPage) => {
    serverOptions.value.row_per_page = perPage;
    serverOptions.value.page = 1;
    fetchData();
};

const handleDownload = async (id) => {
    try {
        const blob = await downloadPayslip(id);
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `payslip-${id}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toast.error(
            "Download failed",
            payrollStore.error || error?.response?.data?.message || "Failed to download payslip.",
        );
    }
};

const viewDetails = (id) => {
    router.push({ name: "staffMember.payroll.detail", params: { id } });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "long",
    });
};

const totalEarnings = computed(() => {
    return payslips.value.reduce((sum, slip) => sum + (slip.gross_salary || 0), 0);
});

const totalDeductions = computed(() => {
    return payslips.value.reduce((sum, slip) => sum + (slip.total_deductions || 0), 0);
});

const totalNetReceived = computed(() => {
    return payslips.value.reduce((sum, slip) => sum + (slip.net_salary || 0), 0);
});

const averageNetSalary = computed(() => {
    if (!payslips.value.length) {
        return 0;
    }

    return totalNetReceived.value / payslips.value.length;
});

const availableYears = computed(() => Array.from({ length: 6 }, (_, index) => currentYear - index));

const clearSearch = () => {
    filters.value.search = null;
};
</script>

<template>
    <div>
        <div class="mb-6">
            <h2 class="text-brand-dark font-bold text-2xl mb-2">My Payroll</h2>
            <p class="text-gray-600">View and download your payroll history</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <MainCard
                title="Payroll Periods"
                :value="String(meta.total || 0)"
                :subtitle="`For ${filters.year}`"
                iconName="FileText"
                :trendLabel="`Year ${filters.year}`"
                :isTrendUp="true"
            />

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center">
                        <ArrowUpRight class="w-6 h-6 text-green-600" />
                    </div>
                </div>
                <p class="text-brand-dark text-sm font-medium mb-2">Gross Earnings</p>
                <p class="text-brand-dark text-xl font-extrabold tabular-nums">
                    {{ formatCurrency(totalEarnings) }}
                </p>
                <p class="text-success text-sm font-medium mt-1">Loaded for {{ filters.year }}</p>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center">
                        <ArrowDownRight class="w-6 h-6 text-red-600" />
                    </div>
                </div>
                <p class="text-brand-dark text-sm font-medium mb-2">Total Deductions</p>
                <p class="text-brand-dark text-xl font-extrabold tabular-nums">
                    {{ formatCurrency(totalDeductions) }}
                </p>
                <p class="text-danger text-sm font-medium mt-1">Loaded for {{ filters.year }}</p>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center">
                        <TrendingUp class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
                <p class="text-brand-dark text-sm font-medium mb-2">Average Net Salary</p>
                <p class="text-brand-dark text-xl font-extrabold tabular-nums">
                    {{ formatCurrency(averageNetSalary) }}
                </p>
                <p class="text-brand-light text-sm font-medium mt-1">Across loaded periods</p>
            </div>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl mb-6 p-4">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        type="text"
                        class="w-full pl-12 pr-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 transition-all duration-300"
                        placeholder="Search payroll periods by month or year..."
                        v-model="filters.search"
                    />
                </div>

                <select
                    v-model.number="filters.year"
                    data-testid="my-payroll-year"
                    class="px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 transition-all duration-300"
                >
                    <option v-for="year in availableYears" :key="year" :value="year">
                        {{ year }}
                    </option>
                </select>
            </div>

            <div
                v-if="filters.search"
                class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-blue-50 px-4 py-3"
            >
                <p class="text-sm text-blue-900">
                    Showing results for
                    <span class="font-semibold">"{{ filters.search }}"</span>
                </p>
                <button
                    type="button"
                    @click="clearSearch"
                    data-testid="my-payroll-clear-search"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900"
                >
                    <X class="w-4 h-4" />
                    Clear
                </button>
            </div>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl p-5 mb-6">
            <div class="mb-6">
                <h3 class="text-brand-dark font-bold text-xl">All Payroll Periods</h3>
                <p class="text-gray-600 text-sm mt-1">
                    Showing {{ meta.from }} - {{ meta.to }} of {{ meta.total }} payroll periods
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
                <div
                    v-for="payslip in payslips"
                    :key="payslip.id"
                    :data-testid="`my-payroll-card-${payslip.id}`"
                    class="border border-brand-border rounded-2xl p-5 hover:ring-2 hover:ring-brand-primary/20 hover:shadow-lg transition-all duration-300 cursor-pointer group"
                    @click="viewDetails(payslip.id)"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div
                            class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition-all duration-300"
                        >
                            <FileText class="w-6 h-6 text-blue-600" />
                        </div>
                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full"
                            :class="
                                payslip.status === 'paid'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-yellow-100 text-yellow-800'
                            "
                        >
                            {{ payslip.status === "paid" ? "Paid" : payslip.status || "Paid" }}
                        </span>
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <Calendar class="w-4 h-4 text-gray-400" />
                            <p class="text-brand-dark font-bold">
                                {{ formatDate(payslip.period) }}
                            </p>
                        </div>
                        <p class="text-brand-light text-sm mb-2">
                            Payment date:
                            {{ formatDate(payslip.payment_date || payslip.created_at) }}
                        </p>
                        <div class="flex items-center gap-2">
                            <DollarSign class="w-4 h-4 text-gray-400" />
                            <p class="text-brand-dark text-xl font-extrabold">
                                {{ formatCurrency(payslip.net_salary) }}
                            </p>
                        </div>
                        <p class="text-brand-light text-sm mt-2">
                            Gross {{ formatCurrency(payslip.gross_salary) }} • Deductions
                            {{ formatCurrency(payslip.total_deductions) }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-gray-200 flex items-center gap-2">
                        <button
                            :data-testid="`my-payroll-view-${payslip.id}`"
                            @click.stop="viewDetails(payslip.id)"
                            class="flex-1 px-3 py-2 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 flex items-center justify-center gap-2 text-sm font-semibold"
                        >
                            <Eye class="w-4 h-4" />
                            View
                        </button>
                        <button
                            :data-testid="`my-payroll-download-${payslip.id}`"
                            @click.stop="handleDownload(payslip.id)"
                            class="btn-primary flex-1 px-3 py-2 text-sm font-semibold"
                        >
                            <Download class="w-4 h-4" />
                            PDF
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="!loading && payslips.length === 0"
                data-testid="my-payroll-empty"
                class="text-center py-12 text-gray-500"
            >
                <FileText class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                <template v-if="filters.year === currentYear">
                    <p class="text-lg font-semibold" data-testid="my-payroll-empty-processing">
                        Your payslip is being processed
                    </p>
                    <p class="text-sm">
                        Payroll for the current period is still being prepared. You will be notified once it is ready.
                    </p>
                </template>
                <template v-else>
                    <p class="text-lg font-semibold" data-testid="my-payroll-empty-none">
                        No payslip available for this period
                    </p>
                    <p class="text-sm">
                        There are no payroll records for {{ filters.year }}. Try selecting a different year.
                    </p>
                </template>
            </div>

            <Pagination
                :meta="meta"
                :loading="loading"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
            />
        </div>
    </div>
</template>
