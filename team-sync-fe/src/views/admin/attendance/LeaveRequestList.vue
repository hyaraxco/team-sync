<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { useLeaveRequestStore } from "@/stores/leaveRequest";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { useSearchFilter } from "@/composables/useSearchFilter";
import { useToast } from "@/composables/useToast";
import { formatDateShort } from "@/utils/dateUtils";
import { can } from "@/helpers/permissionHelper";
import { Check, X, CalendarDays, List, FileSearch, ExternalLink } from "lucide-vue-next";
import SearchFilter from "@/components/common/SearchFilter.vue";
import DataTableCard from "@/components/common/DataTableCard.vue";
import TableStateRows from "@/components/common/TableStateRows.vue";
import EmployeeCell from "@/components/common/EmployeeCell.vue";
import ModalFooterActions from "@/components/common/ModalFooterActions.vue";
import ModalConfirmBanner from "@/components/common/ModalConfirmBanner.vue";
import DatePagination from "@/components/admin/attendance/DatePagination.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import { DateTime } from "luxon";

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
});

const store = useLeaveRequestStore();
const { leaveRequests, meta, loading, calendarData, error } = storeToRefs(store);
const toast = useToast();
const selectedIds = ref([]);
const processingBulkAction = ref(false);

const activeTab = ref("list"); // 'list' or 'calendar'

// ---- LIST VIEW LOGIC ----
const { filters, serverOptions, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "", date_from: null, date_to: null },
    fetchFn: store.fetchLeaveRequestsPaginated,
});

const leaveStatusFilter = ref("");

const filteredLeaveRequests = computed(() => {
    if (!leaveStatusFilter.value) {
        return leaveRequests.value || [];
    }
    return (leaveRequests.value || []).filter((request) => request.status === leaveStatusFilter.value);
});

// ---- DATE NAVIGATION ----
const now = DateTime.now();
const dateRange = ref({
    from: now.startOf("month").toISODate(),
    to: now.endOf("month").toISODate(),
});

const handleDateChange = (range) => {
    dateRange.value = range;
    filters.value.date_from = range.from;
    filters.value.date_to = range.to;
    serverOptions.value.page = 1;
    fetchData();
};

const resetWithDateRange = () => {
    const today = DateTime.now();
    dateRange.value = {
        from: today.startOf("month").toISODate(),
        to: today.endOf("month").toISODate(),
    };
    Object.assign(filters.value, {
        search: null,
        status: "",
        date_from: dateRange.value.from,
        date_to: dateRange.value.to,
    });
    serverOptions.value.page = 1;
    fetchData();
};

// ---- CALENDAR VIEW LOGIC ----
const currentMonth = ref(DateTime.now().startOf("month"));
const calendarDateRange = computed({
    get: () => ({
        from: currentMonth.value.startOf("month").toISODate(),
        to: currentMonth.value.endOf("month").toISODate(),
    }),
    set: (range) => {
        if (range?.from) {
            const dt = DateTime.fromISO(range.from);
            if (dt.isValid) {
                currentMonth.value = dt.startOf("month");
            }
        }
    },
});
const calendarGrid = computed(() => {
    const start = currentMonth.value.startOf("week"); // Monday
    const end = currentMonth.value.endOf("month").endOf("week"); // Sunday

    const days = [];
    let curr = start;
    while (curr <= end) {
        days.push(curr);
        curr = curr.plus({ days: 1 });
    }
    return days;
});

const fetchMonthData = async () => {
    const monthStr = currentMonth.value.toFormat("yyyy-MM");
    await store.fetchCalendarData(monthStr);
};

const handleCalendarDateChange = (range) => {
    calendarDateRange.value = range;
    fetchMonthData();
};

const getLeavesForDate = (date) => {
    return calendarData.value.filter((req) => {
        const start = DateTime.fromISO(req.start_date).startOf("day");
        const end = DateTime.fromISO(req.end_date).startOf("day");
        return date >= start && date <= end;
    });
};

watch(activeTab, (newTab) => {
    if (newTab === "calendar" && (!calendarData.value || calendarData.value.length === 0)) {
        fetchMonthData();
    }
});

watch(
    leaveRequests,
    (requests) => {
        const pendingIds = (requests || [])
            .filter((request) => request.status === "pending")
            .map((request) => request.id);

        selectedIds.value = selectedIds.value.filter((id) => pendingIds.includes(id));
    },
    { deep: false },
);

// ---- APPROVAL WORKFLOW ----
const {
    isModalOpen: showApproveModalState,
    selectedItem: selectedApproveRequest,
    isProcessing: processingApprove,
    openModal: showApproveModal,
    closeModal: closeApproveModal,
    confirmAction: doApprove,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Approved", "Leave request has been approved.");
        if (activeTab.value === "list") {
            await fetchData();
        } else {
            await fetchMonthData();
        }
    },
});

const confirmApprove = () =>
    doApprove(async (request) => {
        await store.approveLeaveRequest(request.id);
    });

// ---- REJECTION WORKFLOW ----
const {
    isModalOpen: showRejectModalState,
    selectedItem: selectedRejectRequest,
    isProcessing: processingReject,
    openModal: showRejectModal,
    closeModal: closeRejectModal,
    confirmAction: doReject,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Rejected", "Leave request has been rejected.");
        if (activeTab.value === "list") {
            await fetchData();
        } else {
            await fetchMonthData();
        }
    },
});

const confirmReject = () =>
    doReject(async (request) => {
        await store.rejectLeaveRequest(request.id);
    });

const selectableRequests = computed(() =>
    (leaveRequests.value || []).filter((request) => request.status === "pending"),
);

const selectedPendingCount = computed(() => selectedIds.value.length);

const allSelectableSelected = computed(() => {
    if (!selectableRequests.value.length) {
        return false;
    }

    return selectableRequests.value.every((request) => selectedIds.value.includes(request.id));
});

const toggleSelectAll = (event) => {
    if (event.target.checked) {
        selectedIds.value = selectableRequests.value.map((request) => request.id);
        return;
    }

    selectedIds.value = [];
};

const normalizeErrorMessage = (axiosError) => {
    const responseData = axiosError?.response?.data;
    const message = responseData?.message;
    const validationErrors = responseData?.errors;

    if (typeof message === "string" && message.trim().length > 0) {
        return message;
    }

    if (validationErrors && typeof validationErrors === "object") {
        const firstError = Object.values(validationErrors).flat()[0];
        if (typeof firstError === "string" && firstError.trim().length > 0) {
            return firstError;
        }
    }

    if (typeof error.value === "string" && error.value.trim().length > 0) {
        return error.value;
    }

    return "Failed to process selected leave requests.";
};

const runBulkAction = async (action) => {
    if (!selectedIds.value.length) {
        toast.warning("No Selection", "Please select at least one pending leave request.");
        return;
    }

    processingBulkAction.value = true;

    try {
        const result = await store.bulkAction(selectedIds.value, action);
        if (result.failed && result.failed.length > 0) {
            toast.warning("Partial Success", `${result.succeeded.length} ${action}d, ${result.failed.length} failed`);
        } else {
            toast.success("Success", `${result.succeeded.length} requests ${action}d`);
        }
        selectedIds.value = [];
        await fetchData();
    } catch (axiosError) {
        toast.error("Bulk Action Failed", normalizeErrorMessage(axiosError));
    } finally {
        processingBulkAction.value = false;
    }
};

// ---- PROOF REVIEW WORKFLOW ----
const proofReviewForm = ref({
    status: "approved",
    notes: "",
});

const {
    isModalOpen: showReviewProofModalState,
    selectedItem: selectedProofRequest,
    isProcessing: processingProofReview,
    openModal: showReviewProofModal,
    closeModal: closeReviewProofModal,
    confirmAction: doReviewProof,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Proof Reviewed", "Sick leave proof has been reviewed.");
        if (activeTab.value === "list") {
            await fetchData();
        } else {
            await fetchMonthData();
        }
    },
});

const confirmReviewProof = () =>
    doReviewProof(async (request) => {
        await store.reviewProof(request.id, {
            proof_review_status: proofReviewForm.value.status,
            proof_review_notes: proofReviewForm.value.notes,
        });
    });

const openReviewProof = (request) => {
    proofReviewForm.value = { status: "approved", notes: "" };
    showReviewProofModal(request);
};

const getProofUrl = (path) => {
    if (!path) return "#";
    const baseUrl = import.meta.env.VITE_API_BASE_URL.replace("/api/v1", "");
    return `${baseUrl}/storage/${path}`;
};

onMounted(() => {
    filters.value.date_from = dateRange.value.from;
    filters.value.date_to = dateRange.value.to;
    fetchData();
});
</script>

<template>
    <div :class="embedded ? '' : 'p-3 sm:p-4 md:p-6 lg:p-8'">
        <div :class="['space-y-6', !embedded && 'max-w-7xl mx-auto']">

    <div v-if="!embedded" class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <p class="text-2xl font-bold text-brand-dark">Leave Requests</p>
            <p class="text-sm text-brand-light mt-1">Manage and monitor employee leave requests.</p>
        </div>

        <!-- Tab Switcher -->
        <div class="bg-brand-border/20 p-1 flex rounded-lg">
            <button
                @click="activeTab = 'list'"
                :class="[
                    'px-4 py-2 text-sm font-semibold rounded-md flex items-center gap-2 transition-all duration-200',
                    activeTab === 'list' ? 'bg-white shadow text-brand-dark' : 'text-brand-light hover:text-brand-dark',
                ]"
            >
                <List class="w-4 h-4" />
                List
            </button>
            <button
                @click="activeTab = 'calendar'"
                :class="[
                    'px-4 py-2 text-sm font-semibold rounded-md flex items-center gap-2 transition-all duration-200',
                    activeTab === 'calendar'
                        ? 'bg-white shadow text-brand-dark'
                        : 'text-brand-light hover:text-brand-dark',
                ]"
            >
                <CalendarDays class="w-4 h-4" />
                Calendar
            </button>
        </div>
    </div>

    <!-- LIST VIEW -->
    <div v-if="activeTab === 'list' || embedded">
        <div class="mb-6">
            <SearchFilter
                placeholder="Search by Employee..."
                :filters="[
                    {
                        key: 'status',
                        label: 'All Statuses',
                        icon: 'CheckCircle',
                        options: [
                            { id: 'pending', name: 'Pending' },
                            { id: 'approved', name: 'Approved' },
                            { id: 'rejected', name: 'Rejected' },
                        ],
                    },
                ]"
                @search="handleSearch"
                @reset="() => { resetWithDateRange(); leaveStatusFilter = ''; }"
                @update:modelValue="leaveStatusFilter = $event.status || ''"
            />
        </div>

        <!-- Date Navigation -->
        <div class="mb-4">
            <DatePagination v-model="dateRange" :loading="loading" @update:modelValue="handleDateChange" />
        </div>

        <div v-if="!embedded" class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <p class="text-sm text-brand-light">
                {{ selectedPendingCount }} pending request{{ selectedPendingCount > 1 ? "s" : "" }} selected
            </p>
            <div class="flex items-center gap-2">
                <button
                    @click="runBulkAction('approve')"
                    :disabled="loading || processingBulkAction || selectedPendingCount === 0"
                    class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ processingBulkAction ? "Processing..." : "Approve Selected" }}
                </button>
                <button
                    @click="runBulkAction('reject')"
                    :disabled="loading || processingBulkAction || selectedPendingCount === 0"
                    class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ processingBulkAction ? "Processing..." : "Reject Selected" }}
                </button>
            </div>
        </div>

        <DataTableCard :meta="meta" :loading="loading" @page-change="handlePageChange" @per-page-change="handlePerPageChange">
        <table class="min-w-full divide-y divide-brand-border">
                    <thead>
                        <tr class="bg-brand-border/20 border-b border-brand-border">
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider w-[48px]">
                                <input
                                    type="checkbox"
                                    :checked="allSelectableSelected"
                                    :disabled="loading || !selectableRequests.length || processingBulkAction"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-brand-border text-brand-dark focus:ring-brand-dark disabled:opacity-50"
                                    title="Select all pending requests"
                                />
                            </th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Employee</th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Date</th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Reason & Type</th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Proof</th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Status</th>
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <TableStateRows
                            :loading="loading"
                            :empty="!filteredLeaveRequests || filteredLeaveRequests.length === 0"
                            :colspan="7"
                            empty-icon="ClipboardList"
                            empty-title="No leave requests found"
                            empty-subtitle="Adjust filters or wait for employees to submit leave requests."
                        />
                        <template v-if="filteredLeaveRequests && filteredLeaveRequests.length > 0 && !loading">
                        <tr
                            v-for="request in filteredLeaveRequests"
                            :key="request.id"
                            class="hover:bg-brand-gray/50"
                        >
                            <td class="px-6 py-4">
                                <input
                                    v-if="request.status === 'pending'"
                                    v-model="selectedIds"
                                    type="checkbox"
                                    :value="request.id"
                                    :disabled="loading || processingBulkAction"
                                    class="w-4 h-4 rounded border-brand-border text-brand-dark focus:ring-brand-dark disabled:opacity-50"
                                    :aria-label="`Select leave request ${request.id}`"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <EmployeeCell
                                    :photo="request.staff_member?.user?.profile_photo"
                                    :name="request.staff_member?.user?.name || ''"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-brand-dark font-medium">
                                    {{ formatDateShort(request.start_date) }} - {{ formatDateShort(request.end_date) }}
                                </div>
                                <div class="text-xs text-brand-light">{{ request.days }} Days</div>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge type="leave-type" :value="request.type" class="mb-1" />
                                <p class="text-sm text-brand-dark max-w-[200px] truncate" :title="request.reason">
                                    {{ request.reason }}
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="request.type === 'sick_leave'" class="flex flex-col gap-1">
                                    <a
                                        v-if="request.proof_file_path"
                                        :href="getProofUrl(request.proof_file_path)"
                                        target="_blank"
                                        class="text-xs text-blue-600 hover:underline flex items-center gap-1"
                                    >
                                        <ExternalLink class="w-3 h-3" />
                                        View Proof
                                    </a>
                                    <span v-else class="text-xs text-brand-light italic">No proof</span>

                                    <div v-if="request.proof_file_path" class="mt-1">
                                        <span
                                            :class="[
                                                'text-xs px-2 py-0.5 rounded',
                                                request.proof_review_status === 'approved'
                                                    ? 'bg-green-100 text-green-700'
                                                    : request.proof_review_status === 'rejected'
                                                      ? 'bg-red-100 text-red-700'
                                                      : 'bg-yellow-100 text-yellow-700',
                                            ]"
                                        >
                                            {{ request.proof_review_status || "pending" }}
                                        </span>
                                    </div>
                                </div>
                                <span v-else class="text-xs text-brand-light">-</span>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge type="leave-status" :value="request.status" />
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="flex items-center gap-2"
                                    v-if="
                                        (request.status === 'pending' && can('leave-request-approve')) ||
                                        (request.type === 'sick_leave' &&
                                            request.proof_file_path &&
                                            (!request.proof_review_status || request.proof_review_status === 'pending'))
                                    "
                                >
                                    <button
                                        v-if="request.status === 'pending'"
                                        @click="showApproveModal(request)"
                                        title="Approve Leave"
                                        class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:border-brand-primary hover:bg-blue-50 transition-all duration-300 px-3 py-2"
                                    >
                                        <Check class="w-4 h-4 text-green-600" />
                                    </button>
                                    <button
                                        v-if="request.status === 'pending'"
                                        @click="showRejectModal(request)"
                                        title="Reject Leave"
                                        class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:border-red-500 hover:bg-red-50 transition-all duration-300 px-3 py-2"
                                    >
                                        <X class="w-4 h-4 text-red-600" />
                                    </button>
                                    <button
                                        v-if="
                                            request.type === 'sick_leave' &&
                                            request.proof_file_path &&
                                            (!request.proof_review_status || request.proof_review_status === 'pending')
                                        "
                                        @click="openReviewProof(request)"
                                        title="Review Proof"
                                        class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:border-yellow-500 hover:bg-yellow-50 transition-all duration-300 px-3 py-2"
                                    >
                                        <FileSearch class="w-4 h-4 text-yellow-600" />
                                    </button>
                                </div>
                                <div v-else class="text-xs text-brand-light">-</div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
        </DataTableCard>
    </div>

    <!-- CALENDAR VIEW -->
    <div v-else-if="!embedded" class="bg-white border border-brand-border rounded-2xl p-5">
        <div class="mb-6">
            <DatePagination v-model="calendarDateRange" :loading="loading" @update:modelValue="handleCalendarDateChange" />
        </div>

        <div class="grid grid-cols-7 gap-px bg-brand-border border border-brand-border rounded-lg overflow-hidden">
            <!-- Calendar Header -->
            <div
                v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                :key="day"
                class="bg-brand-border/20 py-2 text-center text-xs font-semibold text-brand-light uppercase tracking-wider"
            >
                {{ day }}
            </div>

            <!-- Calendar Days -->
            <div
                v-for="date in calendarGrid"
                :key="date.toISODate()"
                :class="[
                    'bg-white min-h-[120px] p-2 hover:bg-brand-border/20 transition-colors',
                    { 'opacity-50 bg-brand-border/20': date.month !== currentMonth.month },
                ]"
            >
                <div class="flex justify-between items-start mb-2">
                    <span
                        :class="[
                            'text-sm font-medium w-6 h-6 flex items-center justify-center rounded-full',
                            date.toISODate() === DateTime.now().toISODate()
                                ? 'bg-brand-dark text-white'
                                : 'text-brand-dark',
                        ]"
                    >
                        {{ date.toFormat("d") }}
                    </span>
                </div>

                <!-- Leaves for this day -->
                <div class="flex flex-col gap-1">
                    <div
                        v-for="req in getLeavesForDate(date)"
                        :key="req.id"
                        :class="[
                            'px-2 py-1 text-xs rounded truncate cursor-pointer',
                            req.status === 'approved'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-amber-100 text-amber-800 border border-amber-200 border-dashed',
                        ]"
                        :title="req.staff_member?.user?.name + ' - ' + req.type"
                    >
                        {{ req.staff_member?.user?.name }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <ModalWrapper :show="showApproveModalState" title="Approve Leave Request" maxWidth="md" @close="closeApproveModal">
        <ModalConfirmBanner variant="green" message="Confirm approval for this leave request." />
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

    <!-- Reject Modal -->
    <ModalWrapper :show="showRejectModalState" title="Reject Leave Request" maxWidth="md" @close="closeRejectModal">
        <ModalConfirmBanner variant="red" message="Confirm rejection for this leave request." />
        <template #footer>
            <ModalFooterActions
                :processing="processingReject"
                confirm-label="Reject"
                confirm-color="red"
                @cancel="closeRejectModal"
                @confirm="confirmReject"
            />
        </template>
    </ModalWrapper>

    <!-- Review Proof Modal -->
    <ModalWrapper
        :show="showReviewProofModalState"
        title="Review Sick Leave Proof"
        maxWidth="md"
        @close="closeReviewProofModal"
    >
        <div class="mb-4">
            <p class="text-brand-light text-sm mb-4">
                Please review the medical certificate for this sick leave request.
            </p>

            <div
                v-if="selectedProofRequest?.proof_file_path"
                class="mb-4 p-3 bg-brand-border/20 border border-brand-border rounded flex justify-between items-center"
            >
                <span class="text-sm font-medium text-brand-dark">{{ selectedProofRequest.proof_file_name }}</span>
                <a
                    :href="getProofUrl(selectedProofRequest.proof_file_path)"
                    target="_blank"
                    class="text-blue-600 hover:underline text-sm flex items-center gap-1"
                >
                    <ExternalLink class="w-4 h-4" />
                    View File
                </a>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1.5">Review Decision *</label>
                    <select
                        v-model="proofReviewForm.status"
                        class="w-full px-4 py-2 border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary"
                    >
                        <option value="approved">Approve Proof</option>
                        <option value="rejected">Reject Proof</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1.5">Review Notes</label>
                    <textarea
                        v-model="proofReviewForm.notes"
                        rows="3"
                        class="w-full px-4 py-2 border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary resize-none"
                        placeholder="Add any notes about this proof..."
                    ></textarea>
                </div>
            </div>
        </div>
        <template #footer>
            <ModalFooterActions
                :processing="processingProofReview"
                confirm-label="Submit Review"
                confirm-color="blue"
                @cancel="closeReviewProofModal"
                @confirm="confirmReviewProof"
            />
        </template>
    </ModalWrapper>
        </div>
    </div>
</template>
