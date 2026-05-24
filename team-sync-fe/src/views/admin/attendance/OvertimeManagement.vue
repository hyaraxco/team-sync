<script setup>
import { onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { Check, X, Plus, Clock } from "lucide-vue-next";
import { useOvertimeStore } from "@/stores/overtime";
import { useStaffMemberStore } from "@/stores/staffMember";
import { formatDateShort, formatTime as formatTimeUtil } from "@/utils/dateUtils";
import DataTableCard from "@/components/common/DataTableCard.vue";
import TableStateRows from "@/components/common/TableStateRows.vue";
import EmployeeCell from "@/components/common/EmployeeCell.vue";
import ModalFooterActions from "@/components/common/ModalFooterActions.vue";
import ModalConfirmBanner from "@/components/common/ModalConfirmBanner.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { useRejectWithReason } from "@/composables/useRejectWithReason";
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

const showCreateModal = ref(false);

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: (params) => store.fetchOvertimeRecords(params),
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

const formatTime = (timeStr) => (timeStr ? formatTimeUtil(timeStr) : "-");
const formatDate = (dateStr) => (dateStr ? formatDateShort(dateStr) : "-");

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
const {
    showRejectModal,
    rejectingItem: rejectingRecord,
    rejectReason: rejectionReason,
    processingReject,
    isReasonValid,
    openRejectModal,
    closeRejectModal,
    confirmReject,
    minLength: rejectMinLength,
} = useRejectWithReason({
    rejectFn: async (record, reason) => {
        await store.rejectOvertime(record.id, reason);
    },
    onSuccess: async () => {
        toast.success("Rejected", "Overtime record has been rejected.");
        await fetchData();
        store.fetchOvertimeSummary();
    },
    minLength: 10,
});

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
                class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
            >
                <Plus class="h-4 w-4 text-white" />
                <span class="text-brand-white text-sm font-semibold">Record Overtime</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="mb-6">
            <SearchFilter
                placeholder="Search overtime records..."
                :filters="[
                    {
                        key: 'status',
                        label: 'All Status',
                        icon: 'CheckCircle',
                        options: [
                            { value: 'pending', label: 'Pending' },
                            { value: 'approved', label: 'Approved' },
                            { value: 'rejected', label: 'Rejected' },
                        ],
                    },
                ]"
                @search="handleSearch"
                @reset="handleReset"
            />
        </div>

        <!-- Table -->
        <DataTableCard :meta="meta" :loading="loading" @page-change="handlePageChange" @per-page-change="handlePerPageChange">
                <table class="min-w-full divide-y divide-brand-border">
                    <thead>
                        <tr class="bg-brand-border/20 border-b border-brand-border">
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Employee
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Date
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Time
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Hours
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Type
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Status
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <TableStateRows
                            :loading="loading"
                            :empty="!records || records.length === 0"
                            :colspan="7"
                            empty-icon="Timer"
                            empty-title="No overtime records found"
                            empty-subtitle="Adjust your filters or wait for employees to log overtime."
                        />
                        <template v-if="records && records.length > 0 && !loading">
                        <tr v-for="record in records" :key="record.id" class="hover:bg-brand-gray/50">
                            <td class="py-4 px-6">
                                <EmployeeCell
                                    :photo="record.staff_member?.user?.profile_photo"
                                    :name="record.staff_member?.user?.name || record.staff_member?.full_name || ''"
                                    :subtitle="record.staff_member?.code || ''"
                                />
                            </td>

                            <td class="py-4 px-6 text-center">
                                <span class="text-sm text-brand-dark font-medium">{{ formatDate(record.date) }}</span>
                            </td>

                            <td class="py-4 px-6 text-center">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-border/20 rounded-lg border border-brand-border">
                                    <Clock class="w-3.5 h-3.5 text-brand-light" />
                                    <span class="text-sm font-medium text-brand-dark tabular-nums">
                                        {{ formatTime(record.start_time) }} - {{ formatTime(record.end_time) }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-center">
                                <span class="text-sm font-semibold text-brand-dark tabular-nums">
                                    {{ Number(record.hours || 0).toFixed(1) }}h
                                </span>
                            </td>

                            <td class="py-4 px-6 text-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold capitalize"
                                    :class="getTypeBadge(record.overtime_type)"
                                >
                                    {{ record.overtime_type }}
                                </span>
                            </td>

                            <td class="py-4 px-6 text-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold capitalize"
                                    :class="getStatusBadge(record.status)"
                                >
                                    {{ record.status }}
                                </span>
                            </td>

                            <td class="py-4 px-6">
                                <div v-if="record.status === 'pending'" class="flex items-center justify-center gap-2">
                                    <button
                                        v-if="can('overtime-approve')"
                                        @click="showApproveModal(record)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 transition-all duration-300"
                                        aria-label="Approve overtime"
                                    >
                                        <Check class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="can('overtime-reject')"
                                        @click="openRejectModal(record)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-all duration-300"
                                        aria-label="Reject overtime"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                                <span v-else class="text-xs text-brand-light">—</span>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
        </DataTableCard>

        <!-- Approve Confirmation Modal -->
        <ModalWrapper :show="showApproveModalState" @close="closeApproveModal" title="Approve Overtime" maxWidth="md">
            <div class="space-y-4">
                <ModalConfirmBanner variant="green" message="Confirm approval for this overtime record." />
                <div v-if="selectedApproveRecord" class="rounded-xl border border-brand-border p-4 text-sm text-brand-dark space-y-1">
                    <p><span class="font-semibold">Employee:</span> {{ selectedApproveRecord?.staff_member?.user?.name }}</p>
                    <p><span class="font-semibold">Date:</span> {{ formatDateShort(selectedApproveRecord?.date) }}</p>
                    <p><span class="font-semibold">Hours:</span> {{ selectedApproveRecord?.hours }}h ({{ selectedApproveRecord?.overtime_type }})</p>
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

        <!-- Reject Modal -->
        <ModalWrapper :show="showRejectModal" @close="closeRejectModal" title="Reject Overtime" maxWidth="md">
            <div class="space-y-4">
                <ModalConfirmBanner variant="red" message="Provide rejection reason for this overtime record." />
                <div v-if="rejectingRecord" class="rounded-xl border border-brand-border p-4 text-sm text-brand-dark space-y-1">
                    <p><span class="font-semibold">Employee:</span> {{ rejectingRecord?.staff_member?.user?.name }}</p>
                    <p><span class="font-semibold">Date:</span> {{ formatDateShort(rejectingRecord?.date) }}</p>
                    <p><span class="font-semibold">Hours:</span> {{ rejectingRecord?.hours }}h ({{ rejectingRecord?.overtime_type }})</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-1">Rejection Reason</label>
                    <textarea
                        v-model="rejectionReason"
                        rows="3"
                        class="w-full rounded-lg border-brand-border shadow-sm focus:border-brand-primary focus:ring-brand-primary text-sm"
                        :placeholder="`Provide a reason for rejection (min ${rejectMinLength} characters)...`"
                    ></textarea>
                    <p
                        v-if="rejectionReason.length > 0 && !isReasonValid"
                        class="text-xs text-red-500 mt-1"
                    >
                        Minimum {{ rejectMinLength }} characters required
                    </p>
                </div>
            </div>
            <template #footer>
                <ModalFooterActions
                    :processing="processingReject"
                    :confirm-disabled="!isReasonValid"
                    confirm-label="Reject"
                    confirm-color="red"
                    @cancel="closeRejectModal"
                    @confirm="confirmReject"
                />
            </template>
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
            </div>
            <template #footer>
                <ModalFooterActions
                    :processing="loading"
                    confirm-label="Create"
                    confirm-color="blue"
                    @cancel="showCreateModal = false"
                    @confirm="submitCreate"
                />
            </template>
        </ModalWrapper>
    </div>
</template>
