<script setup>
import { computed, onMounted, ref } from "vue";
import { BadgeCheck, Clock3, FileWarning, RefreshCw, SlidersHorizontal } from "lucide-vue-next";
import { usePayrollStore } from "@/stores/payroll";
import { useToast } from "@/composables/useToast";
import EmptyState from "@/components/common/EmptyState.vue";
import { formatRupiah } from "@/utils/formatUtils";

const payrollStore = usePayrollStore();
const toast = useToast();
const payrollAdjustments = computed(() => payrollStore.payrollAdjustments || []);
const meta = computed(
    () =>
        payrollStore.meta || {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
        },
);
const loading = computed(() => payrollStore.loading);

const filters = ref({
    status: "",
    page: 1,
    per_page: 15,
});
const approvingId = ref(null);

const statusOptions = [
    { label: "All statuses", value: "" },
    { label: "Pending", value: "pending" },
    { label: "Approved", value: "approved" },
    { label: "Applied", value: "applied" },
];

const totalAmount = computed(() =>
    payrollAdjustments.value.reduce((total, adjustment) => total + Number(adjustment.amount_delta || 0), 0),
);
const pendingCount = computed(() => payrollAdjustments.value.filter((item) => item.status === "pending").length);
const approvedCount = computed(() => payrollAdjustments.value.filter((item) => item.status === "approved").length);
const appliedCount = computed(() => payrollAdjustments.value.filter((item) => item.status === "applied").length);

const loadAdjustments = async () => {
    try {
        await payrollStore.fetchPayrollAdjustments({
            page: filters.value.page,
            per_page: filters.value.per_page,
            ...(filters.value.status ? { status: filters.value.status } : {}),
        });
    } catch (error) {
        toast.error(
            "Failed to load payroll adjustments",
            payrollStore.error || error?.response?.data?.message || "Please try again.",
        );
    }
};

const handleStatusChange = async () => {
    filters.value.page = 1;
    await loadAdjustments();
};

const handlePageChange = async (page) => {
    if (page < 1 || page > Number(meta.value.last_page || 1)) {
        return;
    }

    filters.value.page = page;
    await loadAdjustments();
};

const approveAdjustment = async (adjustment) => {
    approvingId.value = adjustment.id;
    try {
        await payrollStore.approvePayrollAdjustment(adjustment.id);
        toast.success(
            "Payroll adjustment approved",
            "The adjustment is now ready to be applied in the target payroll period.",
        );
        await loadAdjustments();
    } catch (error) {
        toast.error(
            "Failed to approve adjustment",
            payrollStore.error || error?.response?.data?.message || "Only pending adjustments can be approved.",
        );
    } finally {
        approvingId.value = null;
    }
};

const getStaffName = (adjustment) =>
    adjustment?.staff_member?.user?.name ||
    adjustment?.staff_member?.name ||
    adjustment?.staff_member?.employee_name ||
    `Staff #${adjustment.staff_member_id}`;

const getStaffCode = (adjustment) => adjustment?.staff_member?.employee_code || adjustment?.staff_member?.code || "-";

const formatAdjustmentKind = (kind) => {
    if (!kind) {
        return "Unknown adjustment";
    }

    return kind
        .split("_")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
};

const formatPeriod = (period) => {
    if (!period) {
        return "-";
    }

    if (period.month_key) {
        return period.month_key;
    }

    if (period.start_date && period.end_date) {
        return `${period.start_date} — ${period.end_date}`;
    }

    return `#${period.id}`;
};

const formatSignedRupiah = (value) => {
    const amount = Number(value || 0);
    const formatted = formatRupiah(Math.abs(amount));

    if (amount > 0) {
        return `+${formatted}`;
    }

    if (amount < 0) {
        return `-${formatted}`;
    }

    return formatted;
};

const getStatusClass = (status) =>
    ({
        pending: "bg-amber-100 text-amber-700",
        approved: "bg-blue-100 text-blue-700",
        applied: "bg-green-100 text-green-700",
    })[status] || "bg-gray-100 text-gray-700";

onMounted(loadAdjustments);
</script>

<template>
    <div class="space-y-6" data-testid="payroll-adjustment-queue">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-brand-dark text-[32px] font-bold leading-tight flex items-center gap-3">
                    <FileWarning class="w-8 h-8 text-amber-600" />
                    Antrian Penyesuaian Payroll
                </h1>
                <p class="text-brand-light text-base font-normal mt-2 max-w-3xl">
                    Review kredit dan potongan koreksi yang dihasilkan setelah perubahan absensi atau cuti.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-3 text-sm font-semibold text-brand-dark hover:border-brand-primary disabled:opacity-50"
                :disabled="loading"
                @click="loadAdjustments"
            >
                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="flex items-center gap-2 text-brand-light text-sm font-medium">
                    <SlidersHorizontal class="w-4 h-4" />
                    Loaded Adjustments
                </div>
                <p class="mt-3 text-3xl font-bold text-brand-dark">{{ meta.total || payrollAdjustments.length }}</p>
            </div>
            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="flex items-center gap-2 text-brand-light text-sm font-medium">
                    <Clock3 class="w-4 h-4 text-amber-600" />
                    Pending on This Page
                </div>
                <p class="mt-3 text-3xl font-bold text-amber-700">{{ pendingCount }}</p>
            </div>
            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="flex items-center gap-2 text-brand-light text-sm font-medium">
                    <BadgeCheck class="w-4 h-4 text-blue-600" />
                    Approved on This Page
                </div>
                <p class="mt-3 text-3xl font-bold text-blue-700">{{ approvedCount }}</p>
            </div>
            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="flex items-center gap-2 text-brand-light text-sm font-medium">
                    <BadgeCheck class="w-4 h-4 text-green-600" />
                    Page Net Impact
                </div>
                <p
                    class="mt-3 text-2xl font-bold"
                    :class="Number(totalAmount) >= 0 ? 'text-green-700' : 'text-red-700'"
                >
                    {{ formatSignedRupiah(totalAmount) }}
                </p>
                <p class="mt-1 text-xs text-brand-light">Applied on page: {{ appliedCount }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-brand-border bg-white p-5">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-brand-dark">Antrian Penyesuaian</h2>
                    <p class="text-sm text-brand-light">Filter berdasarkan status siklus dan setujui antrian tertunda.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-semibold text-brand-dark">
                    Status
                    <select
                        v-model="filters.status"
                        class="rounded-lg border border-brand-border bg-white px-3 py-2 text-sm font-medium"
                        @change="handleStatusChange"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>
            </div>

            <div v-if="loading && payrollAdjustments.length === 0" class="py-12 text-center text-brand-light">
                Loading payroll adjustments...
            </div>
            <EmptyState
                v-else-if="payrollAdjustments.length === 0"
                icon="FileText"
                title="No payroll adjustments found"
                subtitle="There are no records for the selected status filter."
            />
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Employee</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Kind</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Source Period</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Target Period</th>
                            <th class="px-4 py-3 text-right font-semibold text-brand-dark">Days</th>
                            <th class="px-4 py-3 text-right font-semibold text-brand-dark">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-brand-dark">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="adjustment in payrollAdjustments" :key="adjustment.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-brand-dark">{{ getStaffName(adjustment) }}</p>
                                <p class="text-xs text-brand-light">{{ getStaffCode(adjustment) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-brand-dark">
                                    {{ formatAdjustmentKind(adjustment.adjustment_kind) }}
                                </p>
                                <p class="mt-1 max-w-xs text-xs text-brand-light">
                                    {{ adjustment.reason || "No reason provided" }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-brand-light">{{ formatPeriod(adjustment.source_period) }}</td>
                            <td class="px-4 py-3 text-brand-light">{{ formatPeriod(adjustment.target_period) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-brand-dark">
                                {{ Number(adjustment.days_delta || 0) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-semibold"
                                :class="Number(adjustment.amount_delta || 0) >= 0 ? 'text-green-700' : 'text-red-700'"
                            >
                                {{ formatSignedRupiah(adjustment.amount_delta) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="getStatusClass(adjustment.status)"
                                >
                                    {{ adjustment.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="adjustment.status === 'pending'"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-brand-dark px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                    :disabled="approvingId === adjustment.id"
                                    @click="approveAdjustment(adjustment)"
                                >
                                    {{ approvingId === adjustment.id ? "Approving..." : "Approve" }}
                                </button>
                                <span v-else class="text-xs text-brand-light">No action</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="meta.last_page > 1"
                class="mt-5 flex items-center justify-between border-t border-gray-100 pt-4"
            >
                <p class="text-sm text-brand-light">
                    Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} adjustments
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-brand-border px-4 py-2 text-sm font-semibold disabled:opacity-50"
                        :disabled="meta.current_page <= 1 || loading"
                        @click="handlePageChange(meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-brand-border px-4 py-2 text-sm font-semibold disabled:opacity-50"
                        :disabled="meta.current_page >= meta.last_page || loading"
                        @click="handlePageChange(meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
