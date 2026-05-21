<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { debounce } from "lodash";
import { AlertCircle, CalendarDays, CheckCircle2, Clock3, Hourglass, RefreshCw, Timer, XCircle } from "lucide-vue-next";
import { useOvertimeStore } from "@/stores/overtime";
import Pagination from "@/components/admin/team/Pagination.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const overtimeStore = useOvertimeStore();
const { myRecords, meta, loading, error } = storeToRefs(overtimeStore);
const { fetchMyOvertime } = overtimeStore;

const filters = ref({
    status: "",
});

const serverOptions = ref({
    page: 1,
    per_page: 12,
});

const statusOptions = [
    { value: "", label: "All statuses" },
    { value: "pending", label: "Pending" },
    { value: "approved", label: "Approved" },
    { value: "rejected", label: "Rejected" },
];

const fetchData = async () => {
    await fetchMyOvertime({
        ...serverOptions.value,
        ...filters.value,
    });
};

onMounted(() => {
    fetchData();
});

watch(
    filters,
    debounce(() => {
        serverOptions.value.page = 1;
        fetchData();
    }, 250),
    { deep: true },
);

const totalHours = computed(() => myRecords.value.reduce((sum, record) => sum + Number(record.hours || 0), 0));

const approvedHours = computed(() =>
    myRecords.value
        .filter((record) => record.status === "approved")
        .reduce((sum, record) => sum + Number(record.hours || 0), 0),
);

const pendingCount = computed(() => myRecords.value.filter((record) => record.status === "pending").length);

const rejectedCount = computed(() => myRecords.value.filter((record) => record.status === "rejected").length);

const hasActiveFilter = computed(() => Boolean(filters.value.status));

const handlePageChange = (page) => {
    serverOptions.value.page = page;
    fetchData();
};

const handlePerPageChange = (perPage) => {
    serverOptions.value.per_page = perPage;
    serverOptions.value.page = 1;
    fetchData();
};

const clearFilters = () => {
    filters.value.status = "";
};

const formatDate = (value) => {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
};

const formatTime = (value) => {
    if (!value) {
        return "-";
    }

    return String(value).slice(0, 5);
};

const formatHours = (value) => `${Number(value || 0).toFixed(1)}h`;

const formatType = (value) => {
    if (!value) {
        return "Overtime";
    }

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const statusConfig = (status) => {
    switch (status) {
        case "approved":
            return {
                label: "Approved",
                icon: CheckCircle2,
                badge: "bg-green-100 text-green-700",
                iconClass: "bg-green-50 text-green-600",
            };
        case "rejected":
            return {
                label: "Rejected",
                icon: XCircle,
                badge: "bg-red-100 text-red-700",
                iconClass: "bg-red-50 text-red-600",
            };
        case "pending":
        default:
            return {
                label: "Pending",
                icon: Hourglass,
                badge: "bg-yellow-100 text-yellow-700",
                iconClass: "bg-yellow-50 text-yellow-600",
            };
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-primary">Self Service</p>
                <h1 class="text-2xl font-bold text-brand-dark">My Overtime</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Track submitted overtime hours, approvals, and rejection notes.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary-100 px-4 py-2 text-sm font-semibold text-brand-primary transition-colors hover:bg-primary-50 disabled:opacity-50"
                :disabled="loading"
                @click="fetchData"
            >
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50">
                    <Timer class="h-6 w-6 text-blue-600" />
                </div>
                <p class="text-sm font-medium text-gray-500">Loaded Hours</p>
                <p class="mt-1 text-2xl font-extrabold text-brand-dark">
                    {{ formatHours(totalHours) }}
                </p>
            </div>

            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-green-50">
                    <CheckCircle2 class="h-6 w-6 text-green-600" />
                </div>
                <p class="text-sm font-medium text-gray-500">Approved Hours</p>
                <p class="mt-1 text-2xl font-extrabold text-brand-dark">
                    {{ formatHours(approvedHours) }}
                </p>
            </div>

            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-yellow-50">
                    <Hourglass class="h-6 w-6 text-yellow-600" />
                </div>
                <p class="text-sm font-medium text-gray-500">Pending Records</p>
                <p class="mt-1 text-2xl font-extrabold text-brand-dark">{{ pendingCount }}</p>
            </div>

            <div class="rounded-2xl border border-brand-border bg-white p-5">
                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50">
                    <XCircle class="h-6 w-6 text-red-600" />
                </div>
                <p class="text-sm font-medium text-gray-500">Rejected Records</p>
                <p class="mt-1 text-2xl font-extrabold text-brand-dark">{{ rejectedCount }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-brand-border bg-white p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-brand-dark">Overtime history</h2>
                    <p class="text-sm text-gray-500">
                        Showing {{ meta.from || 0 }} - {{ meta.to || myRecords.length }} of
                        {{ meta.total || 0 }} records
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select
                        v-model="filters.status"
                        class="rounded-xl border border-brand-border px-4 py-2 text-sm text-brand-dark outline-none transition-all hover:border-brand-primary focus:border-brand-primary"
                    >
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <button
                        v-if="hasActiveFilter"
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold text-brand-primary hover:bg-primary-50"
                        @click="clearFilters"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div
                v-if="error"
                class="mt-5 flex items-start gap-3 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-red-700"
            >
                <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0" />
                <div>
                    <p class="font-semibold">Gagal memuat data lembur.</p>
                    <p class="text-sm">{{ error }}</p>
                </div>
            </div>

            <div v-if="loading" class="mt-5 grid gap-3">
                <div
                    v-for="index in 4"
                    :key="`my-overtime-skeleton-${index}`"
                    class="h-24 animate-pulse rounded-2xl bg-slate-100"
                ></div>
            </div>

            <div v-else-if="!error && myRecords.length === 0" class="mt-5">
                <EmptyState
                    icon="CalendarClock"
                    title="No overtime records found"
                    :subtitle="
                        hasActiveFilter
                            ? 'No records match the selected status.'
                            : 'Your approved or pending overtime history will appear here.'
                    "
                />
            </div>

            <div v-else-if="!error" class="mt-5 space-y-3">
                <article
                    v-for="record in myRecords"
                    :key="record.id"
                    class="rounded-2xl border border-gray-200 p-4 transition-all hover:border-brand-primary hover:shadow-sm"
                    :data-testid="`my-overtime-record-${record.id}`"
                >
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="flex gap-3">
                            <div
                                class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl"
                                :class="statusConfig(record.status).iconClass"
                            >
                                <component :is="statusConfig(record.status).icon" class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-brand-dark">
                                        {{ formatType(record.overtime_type) }}
                                    </h3>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="statusConfig(record.status).badge"
                                    >
                                        {{ statusConfig(record.status).label }}
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-500">
                                    <span class="inline-flex items-center gap-1.5">
                                        <CalendarDays class="h-4 w-4" />
                                        {{ formatDate(record.date) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <Clock3 class="h-4 w-4" />
                                        {{ formatTime(record.start_time) }} - {{ formatTime(record.end_time) }}
                                    </span>
                                </div>
                                <p v-if="record.notes" class="mt-3 text-sm text-gray-600">
                                    {{ record.notes }}
                                </p>
                                <p
                                    v-if="record.rejection_reason"
                                    class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700"
                                >
                                    {{ record.rejection_reason }}
                                </p>
                            </div>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-4 py-3 text-left md:text-right">
                            <p class="text-xs font-semibold uppercase tracking-[0.1em] text-gray-500">Hours</p>
                            <p class="text-2xl font-extrabold text-brand-dark">
                                {{ formatHours(record.hours) }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <Pagination
                v-if="!error && (myRecords.length > 0 || meta.total > 0)"
                class="mt-5 border-t border-gray-200 pt-4"
                :meta="meta"
                :loading="loading"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
            />
        </div>
    </div>
</template>
