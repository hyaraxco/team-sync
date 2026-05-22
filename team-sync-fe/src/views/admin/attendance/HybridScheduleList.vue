<script setup>
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { MapPin, Home, Building, Check, X, Clock } from "lucide-vue-next";
import { useHybridScheduleStore } from "@/stores/hybridSchedule";
import { useToast } from "@/composables/useToast";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { formatRequestDate } from "@/utils/dateUtils";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";

const store = useHybridScheduleStore();
const { paginatedSchedules, loading, error } = storeToRefs(store);
const toast = useToast();

const activeTab = ref("schedules");
const rejectReason = ref("");

const dayOrder = ["monday", "tuesday", "wednesday", "thursday", "friday"];

const locationMap = {
    office: {
        label: "Office",
        icon: Building,
        iconClass: "text-blue-600",
        badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    },
    remote: {
        label: "Remote",
        icon: Home,
        iconClass: "text-violet-600",
        badgeClass: "bg-violet-50 text-violet-700 border-violet-200",
    },
};

const normalizeLocation = (location) => String(location || "").toLowerCase();

const getLocationMeta = (location) =>
    locationMap[normalizeLocation(location)] || {
        label: location || "-",
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

const getWeekdayKey = (dateString) => {
    if (!dateString) {
        return null;
    }

    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString("en-US", { weekday: "long" }).toLowerCase();
};

const getEmployeeName = (item) => item?.staff_member?.user?.name || item?.staff_member?.name || item?.user?.name || "-";

const getScheduleStatus = (schedule) => schedule?.status || "active";

const scheduleItems = computed(() => paginatedSchedules.value || []);

const overrideItems = computed(() => {
    const rows = [];

    scheduleItems.value.forEach((schedule) => {
        const overrides = schedule?.overrides || [];

        overrides.forEach((override) => {
            const weekdayKey = getWeekdayKey(override?.date);
            const baseSchedule = getBaseSchedule(schedule);

            rows.push({
                ...override,
                employeeName: getEmployeeName(override) !== "-" ? getEmployeeName(override) : getEmployeeName(schedule),
                currentLocation: override?.current_location || (weekdayKey ? baseSchedule[weekdayKey] : null) || "-",
            });
        });
    });

    return rows.filter((row) => String(row?.status || "").toLowerCase() === "pending");
});

const fetchData = async () => {
    await store.fetchAllPaginated();
};

onMounted(() => {
    fetchData();
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
        toast.success("Approved", "Override request has been approved.");
        await fetchData();
    },
});

const confirmApprove = () =>
    doApprove(async (override) => {
        await store.approveOverride(override.id);
    });

const {
    isModalOpen: showRejectModalState,
    selectedItem: selectedRejectOverride,
    isProcessing: processingReject,
    openModal: showRejectModal,
    closeModal: closeRejectModal,
    confirmAction: doReject,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Rejected", "Override request has been rejected.");
        await fetchData();
    },
    onClose: () => {
        rejectReason.value = "";
    },
});

const onRejectAction = (override) => {
    rejectReason.value = "";
    showRejectModal(override);
};

const confirmReject = () =>
    doReject(async (override) => {
        if (!rejectReason.value.trim()) {
            throw new Error("Rejection notes are required");
        }

        await store.rejectOverride(override.id, rejectReason.value.trim());
    });
</script>

<template>
    <div class="space-y-6">
        <MainCard>
            <div class="space-y-6">
                <div class="flex items-center gap-2 border-b border-brand-border pb-3">
                    <button
                        @click="activeTab = 'schedules'"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-semibold transition-all',
                            activeTab === 'schedules'
                                ? 'bg-brand-primary text-white'
                                : 'border border-brand-border hover:border-brand-primary',
                        ]"
                        :style="activeTab !== 'schedules' ? { background: 'var(--color-surface)', color: 'var(--text-primary)' } : {}"
                    >
                        Schedules
                    </button>

                    <button
                        @click="activeTab = 'overrides'"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-semibold transition-all inline-flex items-center gap-2',
                            activeTab === 'overrides'
                                ? 'bg-brand-primary text-white'
                                : 'border border-brand-border hover:border-brand-primary',
                        ]"
                        :style="activeTab !== 'overrides' ? { background: 'var(--color-surface)', color: 'var(--text-primary)' } : {}"
                    >
                        <Clock class="w-4 h-4" />
                        Override Requests
                    </button>
                </div>

                <div v-if="loading" class="flex justify-center py-14">
                    <div class="w-8 h-8 border-4 border-brand-border border-t-brand-primary rounded-full animate-spin"></div>
                </div>

                <div
                    v-else-if="error"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ error }}
                </div>

                <template v-else>
                    <div v-if="activeTab === 'schedules'" class="overflow-x-auto">
                        <table class="w-full min-w-[900px]">
                            <thead>
                                <tr class="border-y border-brand-border">
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Employee
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Base Schedule (Mon - Fri)
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-if="!scheduleItems.length">
                                    <td colspan="3" class="py-10">
                                        <EmptyState
                                            icon="FileText"
                                            title="No hybrid schedules"
                                            subtitle="Employee hybrid schedules will appear here once configured."
                                        />
                                    </td>
                                </tr>

                                <tr
                                    v-for="schedule in scheduleItems"
                                    v-else
                                    :key="schedule.id"
                                    class="border-b border-brand-border align-top"
                                >
                                    <td class="py-4 px-3 text-sm font-semibold" style="color: var(--text-primary)">
                                        {{ getEmployeeName(schedule) }}
                                    </td>

                                    <td class="py-4 px-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div
                                                v-for="day in dayOrder"
                                                :key="`${schedule.id}-${day}`"
                                                class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                                :class="getLocationMeta(getBaseSchedule(schedule)[day]).badgeClass"
                                            >
                                                <span class="text-xs font-semibold capitalize">
                                                    {{ day.slice(0, 3) }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold">
                                                    <component
                                                        :is="getLocationMeta(getBaseSchedule(schedule)[day]).icon"
                                                        class="w-3.5 h-3.5"
                                                    />
                                                    {{ getLocationMeta(getBaseSchedule(schedule)[day]).label }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-3">
                                        <StatusBadge :value="getScheduleStatus(schedule)" type="status" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[1100px]">
                            <thead>
                                <tr class="border-y border-brand-border">
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Employee
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Requested Date
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Current Location
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Requested Location
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Reason
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="py-3 px-3 text-left text-xs font-semibold uppercase tracking-wide"
                                        style="color: var(--text-secondary)"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-if="!overrideItems.length">
                                    <td colspan="7" class="py-10">
                                        <EmptyState
                                            icon="CalendarClock"
                                            title="No pending overrides"
                                            subtitle="Pending override requests will appear here."
                                        />
                                    </td>
                                </tr>

                                <tr
                                    v-for="override in overrideItems"
                                    v-else
                                    :key="override.id"
                                    class="border-b border-brand-border"
                                >
                                    <td class="py-4 px-3 text-sm font-semibold" style="color: var(--text-primary)">
                                        {{ override.employeeName }}
                                    </td>
                                    <td class="py-4 px-3 text-sm" style="color: var(--text-primary)">
                                        {{ formatRequestDate(override.date) }}
                                    </td>
                                    <td class="py-4 px-3">
                                        <span
                                            class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border"
                                            :class="getLocationMeta(override.currentLocation).badgeClass"
                                        >
                                            <component
                                                :is="getLocationMeta(override.currentLocation).icon"
                                                class="w-3.5 h-3.5"
                                            />
                                            {{ getLocationMeta(override.currentLocation).label }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-3">
                                        <span
                                            class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border"
                                            :class="
                                                getLocationMeta(
                                                    override.planned_work_mode || override.requested_location,
                                                ).badgeClass
                                            "
                                        >
                                            <component
                                                :is="
                                                    getLocationMeta(
                                                        override.planned_work_mode || override.requested_location,
                                                    ).icon
                                                "
                                                class="w-3.5 h-3.5"
                                            />
                                            {{
                                                getLocationMeta(
                                                    override.planned_work_mode || override.requested_location,
                                                ).label
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="py-4 px-3 text-sm max-w-[280px] truncate"
                                        style="color: var(--text-secondary)"
                                        :title="override.reason || '-'"
                                    >
                                        {{ override.reason || "-" }}
                                    </td>
                                    <td class="py-4 px-3">
                                        <StatusBadge :value="override.status || 'pending'" type="status" />
                                    </td>
                                    <td class="py-4 px-3">
                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="showApproveModal(override)"
                                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-brand-border hover:border-green-500 hover:bg-green-50 transition-all"
                                            >
                                                <Check class="w-4 h-4 text-green-600" />
                                                <span class="text-xs font-semibold" style="color: var(--text-primary)">Approve</span>
                                            </button>

                                            <button
                                                @click="onRejectAction(override)"
                                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-brand-border hover:border-red-500 hover:bg-red-50 transition-all"
                                            >
                                                <X class="w-4 h-4 text-red-600" />
                                                <span class="text-xs font-semibold" style="color: var(--text-primary)">Reject</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </MainCard>
    </div>

    <ModalWrapper
        :show="showApproveModalState"
        title="Approve Override Request"
        maxWidth="md"
        @close="closeApproveModal"
    >
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 p-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                    <Check class="w-5 h-5 text-green-600" />
                </div>
                <p class="text-sm" style="color: var(--text-primary)">Confirm approval for this hybrid schedule override request.</p>
            </div>

            <div
                v-if="selectedApproveOverride"
                class="rounded-xl border border-brand-border p-4 text-sm space-y-1"
                style="color: var(--text-primary)"
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
                    <span class="font-semibold">Requested Location:</span>
                    {{
                        getLocationMeta(
                            selectedApproveOverride.planned_work_mode || selectedApproveOverride.requested_location,
                        ).label
                    }}
                </p>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-3">
                <button
                    @click="closeApproveModal"
                    :disabled="processingApprove"
                    class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold"
                >
                    Cancel
                </button>
                <button
                    @click="confirmApprove"
                    :disabled="processingApprove"
                    class="flex-1 px-4 py-3 bg-green-600 text-white rounded-xl text-sm font-semibold disabled:opacity-50"
                >
                    {{ processingApprove ? "Approving..." : "Approve" }}
                </button>
            </div>
        </template>
    </ModalWrapper>

    <ModalWrapper :show="showRejectModalState" title="Reject Override Request" maxWidth="md" @close="closeRejectModal">
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <X class="w-5 h-5 text-red-600" />
                </div>
                <p class="text-sm" style="color: var(--text-primary)">Provide rejection notes for this override request.</p>
            </div>

            <div
                v-if="selectedRejectOverride"
                class="rounded-xl border border-brand-border p-4 text-sm space-y-1"
                style="color: var(--text-primary)"
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
                    <span class="font-semibold">Requested Location:</span>
                    {{
                        getLocationMeta(
                            selectedRejectOverride.planned_work_mode || selectedRejectOverride.requested_location,
                        ).label
                    }}
                </p>
                <p class="italic" style="color: var(--text-secondary)">"{{ selectedRejectOverride.reason || "-" }}"</p>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary)">
                    Rejection Notes
                    <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="rejectReason"
                    rows="4"
                    class="w-full border border-brand-border rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                    style="background: var(--color-surface); color: var(--text-primary)"
                    placeholder="Please provide a clear rejection reason..."
                ></textarea>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-3">
                <button
                    @click="closeRejectModal"
                    :disabled="processingReject"
                    class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold"
                >
                    Cancel
                </button>
                <button
                    @click="confirmReject"
                    :disabled="processingReject || !rejectReason.trim()"
                    class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl text-sm font-semibold disabled:opacity-50"
                >
                    {{ processingReject ? "Rejecting..." : "Reject" }}
                </button>
            </div>
        </template>
    </ModalWrapper>
</template>
