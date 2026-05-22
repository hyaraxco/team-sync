<script setup>
import { onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { Check, X, Plus, Timer, AlertCircle } from "lucide-vue-next";
import { useOvertimeStore } from "@/stores/overtime";
import { useStaffMemberStore } from "@/stores/staffMember";
import { formatDateShort } from "@/utils/dateUtils";
import Pagination from "@/components/admin/team/Pagination.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import StatsCard from "@/components/common/StatsCard.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { useToast } from "@/composables/useToast";
import { can } from "@/helpers/permissionHelper";

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
});

const store = useOvertimeStore();
const staffMemberStore = useStaffMemberStore();
const { records, meta, loading, error, summary } = storeToRefs(store);
const toast = useToast();

const statusFilter = ref("");
const showCreateModal = ref(false);
const showRejectModal = ref(false);
const rejectingRecord = ref(null);
const rejectionReason = ref("");

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: (params) => store.fetchOvertimeRecords({ ...params, status: statusFilter.value }),
});

const createForm = ref({
    staff_member_id: "",
    date: "",
    start_time: "17:00",
    end_time: "19:00",
    overtime_type: "workday",
    notes: "",
});

onMounted(async () => {
    fetchData();
    store.fetchOvertimeSummary();
});

const handleStatusFilter = (status) => {
    statusFilter.value = status;
    fetchData();
};

const getStatusBadge = (status) => {
    switch (status) {
        case "approved":
            return "bg-green-100 text-green-700";
        case "rejected":
            return "bg-red-100 text-red-700";
        default:
            return "bg-amber-100 text-amber-700";
    }
};

const getTypeBadge = (type) => {
    switch (type) {
        case "workday":
            return "bg-blue-100 text-blue-700";
        case "weekend":
            return "bg-purple-100 text-purple-700";
        case "holiday":
            return "bg-orange-100 text-orange-700";
        default:
            return "bg-gray-100 text-gray-700";
    }
};

// Approve
const {
    isModalOpen: showApproveModalState,
    selectedItem: selectedApproveRecord,
    isProcessing: processingApprove,
    openModal: showApproveModal,
    closeModal: closeApproveModal,
    confirmAction: doApprove,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Approved", "Overtime record has been approved.");
        await fetchData();
        store.fetchOvertimeSummary();
    },
});

const confirmApprove = () =>
    doApprove(async (record) => {
        await store.approveOvertime(record.id);
    });

// Reject
const openRejectModal = (record) => {
    rejectingRecord.value = record;
    rejectionReason.value = "";
    showRejectModal.value = true;
};

const confirmReject = async () => {
    if (!rejectingRecord.value || rejectionReason.value.length < 10) return;
    try {
        await store.rejectOvertime(rejectingRecord.value.id, rejectionReason.value);
        toast.success("Rejected", "Overtime record has been rejected.");
        showRejectModal.value = false;
        rejectingRecord.value = null;
        rejectionReason.value = "";
        await fetchData();
        store.fetchOvertimeSummary();
    } catch (_e) {
        toast.error("Error", "Failed to reject overtime record.");
    }
};

// Create
const openCreateModal = () => {
    createForm.value = {
        staff_member_id: "",
        date: "",
        start_time: "17:00",
        end_time: "19:00",
        overtime_type: "workday",
        notes: "",
    };
    showCreateModal.value = true;
};

const submitCreate = async () => {
    try {
        await store.createOvertime(createForm.value);
        toast.success("Created", "Overtime record has been created.");
        showCreateModal.value = false;
        await fetchData();
        store.fetchOvertimeSummary();
    } catch (_e) {
        toast.error("Error", store.error || "Failed to create overtime record.");
    }
};
</script>

<template>
    <div :class="embedded ? 'space-y-6' : 'space-y-6 p-3 sm:p-4 md:p-6 lg:p-8'">
        <!-- Header -->
        <div v-if="!embedded" class="flex items-center justify-between">
            <div></div>
            <button
                v-if="can('overtime-create')"
                @click="openCreateModal"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition"
            >
                <Plus class="h-4 w-4" />
                Record Overtime
            </button>
        </div>

        <!-- Summary Cards -->
        <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <StatsCard title="Pending" :value="summary.total_pending" iconName="AlertCircle" colorScheme="orange" />
            <StatsCard title="Approved" :value="summary.approved_this_month" iconName="Check" colorScheme="green" />
            <StatsCard title="Hours This Month" :value="summary.total_hours_this_month" iconName="Timer" colorScheme="blue" />
            <StatsCard title="Rejected" :value="summary.rejected_this_month" iconName="X" colorScheme="red" />
        </div>

        <!-- Filters -->
        <div class="bg-white border border-brand-border rounded-2xl p-4">
            <div class="flex flex-wrap items-center gap-3">
                <button
                    @click="handleStatusFilter('')"
                    :class="[
                        'px-3 py-1.5 rounded-full text-sm font-medium transition',
                        statusFilter === '' ? 'bg-gray-900 text-white' : 'text-brand-light hover:bg-gray-200',
                    ]"
                >
                    All
                </button>
                <button
                    @click="handleStatusFilter('pending')"
                    :class="[
                        'px-3 py-1.5 rounded-full text-sm font-medium transition',
                        statusFilter === 'pending'
                            ? 'bg-amber-600 text-white'
                            : 'bg-amber-50 text-amber-700 hover:bg-amber-100',
                    ]"
                >
                    Pending
                </button>
                <button
                    @click="handleStatusFilter('approved')"
                    :class="[
                        'px-3 py-1.5 rounded-full text-sm font-medium transition',
                        statusFilter === 'approved'
                            ? 'bg-green-600 text-white'
                            : 'bg-green-50 text-green-700 hover:bg-green-100',
                    ]"
                >
                    Approved
                </button>
                <button
                    @click="handleStatusFilter('rejected')"
                    :class="[
                        'px-3 py-1.5 rounded-full text-sm font-medium transition',
                        statusFilter === 'rejected'
                            ? 'bg-red-600 text-white'
                            : 'bg-red-50 text-red-700 hover:bg-red-100',
                    ]"
                >
                    Rejected
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-brand-border overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-brand-light">Loading...</div>
            <EmptyState
                v-else-if="!records.length"
                title="No overtime records"
                description="Overtime records will appear here once submitted."
            />
            <table v-else class="min-w-full divide-y divide-brand-border">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Employee</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Hours</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-brand-light uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-brand-dark">
                            {{ record.staff_member?.user?.name || "-" }}
                        </td>
                        <td class="px-4 py-3 text-sm text-brand-light">
                            {{ formatDateShort(record.date) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-brand-light">{{ record.start_time }} - {{ record.end_time }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-brand-dark">{{ record.hours }}h</td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                    getTypeBadge(record.overtime_type),
                                ]"
                            >
                                {{ record.overtime_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="['px-2 py-0.5 rounded-full text-xs font-medium', getStatusBadge(record.status)]"
                            >
                                {{ record.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="record.status === 'pending' && can('overtime-approve')"
                                class="flex items-center gap-2"
                            >
                                <button
                                    @click="showApproveModal(record)"
                                    class="p-1.5 rounded-md bg-green-50 text-green-600 hover:bg-green-100 transition"
                                    title="Approve"
                                >
                                    <Check class="h-4 w-4" />
                                </button>
                                <button
                                    @click="openRejectModal(record)"
                                    class="p-1.5 rounded-md bg-red-50 text-red-600 hover:bg-red-100 transition"
                                    title="Reject"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <span v-else class="text-xs text-brand-light">-</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="records.length" class="border-t px-4 py-3">
                <Pagination :meta="meta" @page-change="handlePageChange" />
            </div>
        </div>

        <!-- Approve Confirmation Modal -->
        <ModalWrapper :show="showApproveModalState" @close="closeApproveModal" title="Approve Overtime">
            <div class="space-y-4">
                <p class="text-sm text-brand-light">
                    Are you sure you want to approve this overtime record for
                    <strong>{{ selectedApproveRecord?.staff_member?.user?.name }}</strong>
                    ?
                </p>
                <p class="text-sm text-brand-light">
                    {{ selectedApproveRecord?.hours }}h on {{ formatDateShort(selectedApproveRecord?.date) }} ({{
                        selectedApproveRecord?.overtime_type
                    }})
                </p>
                <div class="flex justify-end gap-3">
                    <button @click="closeApproveModal" class="px-4 py-2 text-sm text-brand-light hover:text-brand-dark">
                        Cancel
                    </button>
                    <button
                        @click="confirmApprove"
                        :disabled="processingApprove"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ processingApprove ? "Approving..." : "Approve" }}
                    </button>
                </div>
            </div>
        </ModalWrapper>

        <!-- Reject Modal -->
        <ModalWrapper :show="showRejectModal" @close="showRejectModal = false" title="Reject Overtime">
            <div class="space-y-4">
                <p class="text-sm text-brand-light">
                    Rejecting overtime record for
                    <strong>{{ rejectingRecord?.staff_member?.user?.name }}</strong>
                </p>
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1">Rejection Reason</label>
                    <textarea
                        v-model="rejectionReason"
                        rows="3"
                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        placeholder="Provide a reason for rejection (min 10 characters)..."
                    ></textarea>
                    <p
                        v-if="rejectionReason.length > 0 && rejectionReason.length < 10"
                        class="text-xs text-red-500 mt-1"
                    >
                        Minimum 10 characters required
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showRejectModal = false"
                        class="px-4 py-2 text-sm text-brand-light hover:text-brand-dark"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmReject"
                        :disabled="rejectionReason.length < 10"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
                    >
                        Reject
                    </button>
                </div>
            </div>
        </ModalWrapper>

        <!-- Create Modal -->
        <ModalWrapper :show="showCreateModal" @close="showCreateModal = false" title="Record Overtime">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1">Staff Member ID</label>
                    <input
                        v-model="createForm.staff_member_id"
                        type="number"
                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        placeholder="Staff member ID"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1">Date</label>
                    <input
                        v-model="createForm.date"
                        type="date"
                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-1">Start Time</label>
                        <input
                            v-model="createForm.start_time"
                            type="time"
                            class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-1">End Time</label>
                        <input
                            v-model="createForm.end_time"
                            type="time"
                            class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1">Overtime Type</label>
                    <select
                        v-model="createForm.overtime_type"
                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                    >
                        <option value="workday">Workday</option>
                        <option value="weekend">Weekend</option>
                        <option value="holiday">Holiday</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1">Notes</label>
                    <textarea
                        v-model="createForm.notes"
                        rows="2"
                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        placeholder="Optional notes..."
                    ></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showCreateModal = false"
                        class="px-4 py-2 text-sm text-brand-light hover:text-brand-dark"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitCreate"
                        :disabled="loading"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ loading ? "Creating..." : "Create" }}
                    </button>
                </div>
            </div>
        </ModalWrapper>
    </div>
</template>
