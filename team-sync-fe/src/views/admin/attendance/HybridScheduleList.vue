<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { MapPin, Home, Building, Clock, Check, X } from "lucide-vue-next";
import { useHybridScheduleStore } from "@/stores/hybridSchedule";
import { useToast } from "@/composables/useToast";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { useRejectWithReason } from "@/composables/useRejectWithReason";
import { useSearchFilter } from "@/composables/useSearchFilter";
import { formatRequestDate } from "@/utils/dateUtils";
import Alert from "@/components/common/Alert.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import DataTableCard from "@/components/common/DataTableCard.vue";
import TableStateRows from "@/components/common/TableStateRows.vue";
import EmployeeCell from "@/components/common/EmployeeCell.vue";
import ModalFooterActions from "@/components/common/ModalFooterActions.vue";
import ModalConfirmBanner from "@/components/common/ModalConfirmBanner.vue";

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
});

const store = useHybridScheduleStore();
const {
    paginatedSchedules,
    meta,
    loading,
    paginatedOverrides,
    overridesMeta,
    overridesLoading,
    error,
} = storeToRefs(store);
const toast = useToast();

const activeTab = ref("schedules");

const dayOrder = ["monday", "tuesday", "wednesday", "thursday", "friday"];

const workPatternMap = {
    office: {
        label: "Office",
        icon: Building,
        iconClass: "text-blue-600",
        badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    },
    remote: {
        label: "WFH",
        icon: Home,
        iconClass: "text-violet-600",
        badgeClass: "bg-violet-50 text-violet-700 border-violet-200",
    },
};

const normalizePattern = (pattern) => String(pattern || "").toLowerCase();

const getPatternMeta = (pattern) =>
    workPatternMap[normalizePattern(pattern)] || {
        label: pattern || "-",
        icon: MapPin,
        iconClass: "text-brand-light",
        badgeClass: "border-brand-border",
    };

const getBaseSchedule = (schedule) =>
    schedule?.base_schedule || {
        monday: schedule?.monday,
        tuesday: schedule?.tuesday,
        wednesday: schedule?.wednesday,
        thursday: schedule?.thursday,
        friday: schedule?.friday,
    };

const getEmployeeName = (item) =>
    item?.staff_member?.user?.name || item?.staff_member?.name || item?.user?.name || "-";

const getScheduleStatus = (schedule) => schedule?.status || "active";

const scheduleItems = computed(() => paginatedSchedules.value || []);

// Override items are server-paginated — no more client-side filtering
const overrideItems = computed(() =>
    (paginatedOverrides.value || []).map((override) => ({
        ...override,
        employeeName: override?.staff_member?.user?.name || "-",
        currentLocation: override?.current_schedule_mode || "-",
    })),
);

// --- Schedules tab: search only (no status filter) ---
const {
    fetchData: schedFetchData,
    handleSearch: schedHandleSearch,
    handleReset: schedHandleReset,
    handlePageChange: schedHandlePageChange,
    handlePerPageChange: schedHandlePerPageChange,
} = useSearchFilter({
    defaultFilters: { search: null },
    fetchFn: store.fetchAllPaginated,
});

// --- Exceptions tab: search + status, separate pagination ---
const {
    fetchData: ovFetchData,
    handleSearch: ovHandleSearch,
    handleReset: ovHandleReset,
    handlePageChange: ovHandlePageChange,
    handlePerPageChange: ovHandlePerPageChange,
} = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: store.fetchOverridesPaginated,
});

// Lazy-load overrides on first tab switch
watch(activeTab, (newTab) => {
    if (newTab === "overrides" && !store.paginatedOverrides.length) {
        ovFetchData();
    }
});

onMounted(() => {
    schedFetchData();
});

const {
    isModalOpen: showApproveModalState,
    selectedItem: selectedApproveOverride,
    isProcessing: processingApprove,
    openModal: showApproveModal,
    closeModal: closeApproveModal,
    confirmAction: doApprove,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Approved", "Schedule exception has been approved.");
        await ovFetchData();
    },
});

const confirmApprove = () =>
    doApprove(async (override) => {
        await store.approveOverride(override.id);
    });

const {
    showRejectModal: showRejectModalState,
    rejectingItem: selectedRejectOverride,
    rejectReason,
    processingReject,
    isReasonValid,
    openRejectModal: onRejectAction,
    closeRejectModal,
    confirmReject,
    minLength: rejectMinLength,
} = useRejectWithReason({
    rejectFn: async (override) => {
        await store.rejectOverride(override.id, rejectReason.value.trim());
    },
    onSuccess: async () => {
        toast.success("Rejected", "Schedule exception has been rejected.");
        await ovFetchData();
    },
});
</script>

<template>
    <div :class="embedded ? 'space-y-6' : 'space-y-6 p-3 sm:p-4 md:p-6 lg:p-8'">
        <div class="space-y-6">
            <!-- Tabs -->
            <div class="border border-brand-border rounded-2xl p-3" style="background: var(--color-surface)">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <button
                        @click="activeTab = 'schedules'"
                        type="button"
                        class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                        :class="
                            activeTab === 'schedules'
                                ? 'blue-gradient blue-btn-shadow border border-primary-700 text-white'
                                : 'border-brand-border hover:ring-2 hover:ring-brand-primary/20'
                        "
                        :style="{ color: activeTab === 'schedules' ? 'white' : 'var(--color-text-primary)' }"
                    >
                        <MapPin
                            class="w-4 h-4"
                            :class="activeTab === 'schedules' ? 'text-white' : 'text-gray-600'"
                        />
                        <span class="text-sm font-semibold">Schedules</span>
                    </button>

                    <button
                        @click="activeTab = 'overrides'"
                        type="button"
                        class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                        :class="
                            activeTab === 'overrides'
                                ? 'blue-gradient blue-btn-shadow border border-primary-700 text-white'
                                : 'border-brand-border hover:ring-2 hover:ring-brand-primary/20'
                        "
                        :style="{ color: activeTab === 'overrides' ? 'white' : 'var(--color-text-primary)' }"
                    >
                        <Clock
                            class="w-4 h-4"
                            :class="activeTab === 'overrides' ? 'text-white' : 'text-gray-600'"
                        />
                        <span class="text-sm font-semibold">Exceptions</span>
                    </button>
                </div>
            </div>

            <!-- Schedules Search: search only -->
            <SearchFilter
                v-if="activeTab === 'schedules'"
                placeholder="Search hybrid schedules..."
                :filters="[]"
                @search="schedHandleSearch"
                @reset="schedHandleReset"
            />

            <!-- Exceptions Search: search + status filter -->
            <SearchFilter
                v-else
                placeholder="Search by employee name..."
                :filters="[
                    {
                        key: 'status',
                        label: 'All Statuses',
                        icon: 'CheckCircle',
                        options: [
                            { value: 'pending', label: 'Pending' },
                            { value: 'approved', label: 'Approved' },
                            { value: 'rejected', label: 'Rejected' },
                        ],
                    },
                ]"
                @search="ovHandleSearch"
                @reset="ovHandleReset"
            />

            <Alert v-if="error" type="error" :message="error" dismissible @close="store.error = null" />

            <!-- Schedules Table -->
            <DataTableCard
                v-if="activeTab === 'schedules'"
                :meta="meta"
                :loading="loading"
                @page-change="schedHandlePageChange"
                @per-page-change="schedHandlePerPageChange"
            >
                <table class="min-w-full divide-y divide-brand-border">
                    <thead>
                        <tr class="bg-brand-border/20 border-b border-brand-border">
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Employee
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Work Pattern
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <TableStateRows
                            :loading="loading"
                            :empty="!scheduleItems.length"
                            :colspan="3"
                            empty-icon="FileText"
                            empty-title="No hybrid schedules"
                            empty-subtitle="Employee hybrid schedules will appear here once configured."
                        />
                        <template v-if="scheduleItems.length && !loading">
                        <tr
                            v-for="schedule in scheduleItems"
                            :key="schedule.id"
                            class="hover:bg-brand-gray/50"
                        >
                            <td class="py-4 px-6">
                                <EmployeeCell
                                    :photo="schedule?.staff_member?.user?.profile_photo"
                                    :name="getEmployeeName(schedule)"
                                    :subtitle="schedule?.staff_member?.staff_member_id || ''"
                                />
                            </td>
                            <td class="py-4 px-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    <div
                                        v-for="day in dayOrder"
                                        :key="`${schedule.id}-${day}`"
                                        class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                        :class="getPatternMeta(getBaseSchedule(schedule)[day]).badgeClass"
                                    >
                                        <span class="text-xs font-semibold capitalize">
                                            {{ day.slice(0, 3) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold">
                                            <component
                                                :is="getPatternMeta(getBaseSchedule(schedule)[day]).icon"
                                                class="w-3.5 h-3.5"
                                            />
                                            {{ getPatternMeta(getBaseSchedule(schedule)[day]).label }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <StatusBadge :value="getScheduleStatus(schedule)" type="status" />
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </DataTableCard>

            <!-- Exceptions Table — server-paginated independently -->
            <DataTableCard
                v-else
                :meta="overridesMeta"
                :loading="overridesLoading"
                @page-change="ovHandlePageChange"
                @per-page-change="ovHandlePerPageChange"
            >
                <table class="min-w-full divide-y divide-brand-border">
                    <thead>
                        <tr class="bg-brand-border/20 border-b border-brand-border">
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Employee
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Requested Date
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Current
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Requested
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Reason
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Status
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <TableStateRows
                            :loading="overridesLoading"
                            :empty="!overrideItems.length"
                            :colspan="7"
                            empty-icon="CalendarClock"
                            empty-title="No exceptions found"
                            empty-subtitle="When employees request schedule changes, they appear here for approval."
                        />
                        <template v-if="overrideItems.length && !overridesLoading">
                        <tr
                            v-for="override in overrideItems"
                            :key="override.id"
                            class="hover:bg-brand-gray/50"
                        >
                            <td class="py-4 px-6">
                                <EmployeeCell
                                    :photo="override?.staff_member?.user?.profile_photo"
                                    :name="override.employeeName"
                                />
                            </td>
                            <td class="py-4 px-6 text-sm text-brand-light">
                                {{ formatRequestDate(override.date) }}
                            </td>
                            <td class="py-4 px-6">
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border"
                                    :class="getPatternMeta(override.currentLocation).badgeClass"
                                >
                                    <component
                                        :is="getPatternMeta(override.currentLocation).icon"
                                        class="w-3.5 h-3.5"
                                    />
                                    {{ getPatternMeta(override.currentLocation).label }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border"
                                    :class="getPatternMeta(override.planned_work_mode).badgeClass"
                                >
                                    <component
                                        :is="getPatternMeta(override.planned_work_mode).icon"
                                        class="w-3.5 h-3.5"
                                    />
                                    {{ getPatternMeta(override.planned_work_mode).label }}
                                </span>
                            </td>
                            <td
                                class="py-4 px-6 text-sm text-brand-light max-w-[280px] truncate"
                                :title="override.reason || '-'"
                            >
                                {{ override.reason || "-" }}
                            </td>
                            <td class="py-4 px-6">
                                <StatusBadge :value="override.status || 'pending'" type="leave-status" />
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        v-if="override.status === 'pending'"
                                        @click="showApproveModal(override)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 transition-all duration-300"
                                        aria-label="Approve schedule exception"
                                    >
                                        <Check class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="override.status === 'pending'"
                                        @click="onRejectAction(override)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-all duration-300"
                                        aria-label="Reject schedule exception"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                    <span v-if="override.status !== 'pending'" class="text-xs text-brand-light">—</span>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </DataTableCard>
        </div>

    <ModalWrapper
        :show="showApproveModalState"
        title="Approve Schedule Exception"
        maxWidth="md"
        @close="closeApproveModal"
    >
        <div class="space-y-4">
            <ModalConfirmBanner variant="green" message="Confirm approval for this schedule exception." />

            <div
                v-if="selectedApproveOverride"
                class="rounded-xl border border-brand-border p-4 text-sm text-brand-dark space-y-1"
            >
                <p>
                    <span class="font-semibold">Employee:</span>
                    {{ selectedApproveOverride.employeeName }}
                </p>
                <p>
                    <span class="font-semibold">Date:</span>
                    {{ formatRequestDate(selectedApproveOverride.date) }}
                </p>
                <p>
                    <span class="font-semibold">Current schedule:</span>
                    {{ getPatternMeta(selectedApproveOverride.currentLocation).label }}
                </p>
                <p>
                    <span class="font-semibold">Requested:</span>
                    {{ getPatternMeta(selectedApproveOverride.planned_work_mode).label }}
                </p>
            </div>
        </div>

        <template #footer>
            <ModalFooterActions
                :processing="processingApprove"
                confirm-label="Approve"
                confirm-color="green"
                @cancel="closeApproveModal"
                @confirm="confirmApprove"
            />
        </template>
    </ModalWrapper>

    <ModalWrapper :show="showRejectModalState" title="Reject Schedule Exception" maxWidth="md" @close="closeRejectModal">
        <div class="space-y-4">
            <ModalConfirmBanner variant="red" message="Provide rejection notes for this schedule exception." />

            <div
                v-if="selectedRejectOverride"
                class="rounded-xl border border-brand-border p-4 text-sm text-brand-dark space-y-1"
            >
                <p>
                    <span class="font-semibold">Employee:</span>
                    {{ selectedRejectOverride.employeeName }}
                </p>
                <p>
                    <span class="font-semibold">Date:</span>
                    {{ formatRequestDate(selectedRejectOverride.date) }}
                </p>
                <p>
                    <span class="font-semibold">Requested:</span>
                    {{ getPatternMeta(selectedRejectOverride.planned_work_mode).label }}
                </p>
                <p class="italic text-brand-light">"{{ selectedRejectOverride.reason || "-" }}"</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-brand-dark mb-2">
                    Rejection Notes
                    <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="rejectReason"
                    rows="4"
                    class="w-full border border-brand-border rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                    :placeholder="`Minimum ${rejectMinLength} characters required...`"
                ></textarea>
            </div>
        </div>

        <template #footer>
            <ModalFooterActions
                :processing="processingReject"
                confirm-label="Reject"
                confirm-color="red"
                :confirm-disabled="!isReasonValid"
                @cancel="closeRejectModal"
                @confirm="confirmReject"
            />
        </template>
    </ModalWrapper>
    </div>
</template>
