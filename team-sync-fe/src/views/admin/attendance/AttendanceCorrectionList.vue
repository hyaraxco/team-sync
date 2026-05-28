<script setup>
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { Check, X } from "lucide-vue-next";
import { useAttendanceCorrectionStore } from "@/stores/attendanceCorrection";
import { formatDateShort, formatTime as formatTimeUtil } from "@/utils/dateUtils";
import SearchFilter from "@/components/common/SearchFilter.vue";
import DataTableCard from "@/components/common/DataTableCard.vue";
import TableStateRows from "@/components/common/TableStateRows.vue";
import EmployeeCell from "@/components/common/EmployeeCell.vue";
import ModalFooterActions from "@/components/common/ModalFooterActions.vue";
import ModalConfirmBanner from "@/components/common/ModalConfirmBanner.vue";
import Alert from "@/components/common/Alert.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
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

const store = useAttendanceCorrectionStore();
const { paginatedCorrections, meta, loading, error } = storeToRefs(store);
const toast = useToast();

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: store.fetchAllPaginated,
});

onMounted(() => {
    fetchData();
});

const formatTime = (timeStr) => (timeStr ? formatTimeUtil(timeStr) : "-");

// Approval Workflow
const {
    isModalOpen: showApproveModalState,
    selectedItem: selectedApproveCorrection,
    isProcessing: processingApprove,
    openModal: showApproveModal,
    closeModal: closeApproveModal,
    confirmAction: doApprove,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Approved", "Attendance correction has been approved.");
        await fetchData();
    },
});

const confirmApprove = () =>
    doApprove(async (correction) => {
        await store.approveCorrection(correction.id, { review_notes: "Approved via Admin Dashboard" });
    });

// Rejection Workflow
const {
    showRejectModal: showRejectModalState,
    rejectingItem: selectedRejectCorrection,
    rejectReason,
    processingReject,
    isReasonValid,
    openRejectModal: onRejectAction,
    closeRejectModal,
    confirmReject,
    minLength: rejectMinLength,
} = useRejectWithReason({
    rejectFn: async (correction) => {
        await store.rejectCorrection(correction.id, { review_notes: rejectReason.value.trim() });
    },
    onSuccess: async () => {
        toast.success("Rejected", "Attendance correction has been rejected.");
        await fetchData();
    },
});
</script>

<template>
    <div :class="embedded ? 'space-y-6' : 'space-y-6 p-3 sm:p-4 md:p-6 lg:p-8'">
        <div class="space-y-6">
            <span v-if="!embedded" class="sr-only" role="heading" aria-level="1">Attendance Corrections</span>

            <SearchFilter
                placeholder="Search by Employee or ID..."
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
                @search="handleSearch"
                @reset="handleReset"
            />

            <Alert type="error" title="Error" :message="error || ''" :show="Boolean(error)" />

            <DataTableCard :meta="meta" :loading="loading" @page-change="handlePageChange" @per-page-change="handlePerPageChange">
                <table class="min-w-full divide-y divide-brand-border">
                    <thead>
                        <tr class="bg-brand-border/20 border-b border-brand-border">
                            <th class="py-4 px-6 text-left text-xs font-semibold text-brand-dark uppercase tracking-wider">Employee</th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">Date</th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">Requested Times</th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">Reason</th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">Status</th>
                            <th class="py-4 px-6 text-center text-xs font-semibold text-brand-dark uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        <TableStateRows
                            :loading="loading"
                            :empty="!paginatedCorrections || paginatedCorrections.length === 0"
                            :colspan="6"
                            empty-icon="ClipboardList"
                            empty-title="No corrections found"
                            empty-subtitle="Attendance correction requests will appear here."
                        />
                        <template v-if="paginatedCorrections && paginatedCorrections.length > 0 && !loading">
                        <tr
                            v-for="correction in paginatedCorrections"
                            :key="correction.id"
                            class="hover:bg-brand-gray/50"
                        >
                            <td class="py-4 px-6">
                                <EmployeeCell
                                    :photo="correction.staff_member?.user?.profile_photo"
                                    :name="correction.staff_member?.user?.name || '-'"
                                    :subtitle="correction.staff_member?.staff_member_id"
                                />
                            </td>
                            <td class="py-4 px-6 text-center">
                                <span class="text-sm text-brand-dark font-medium">
                                    {{ correction.attendance ? formatDateShort(correction.attendance.date) : "-" }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs text-brand-dark">
                                        <strong>In:</strong>
                                        {{ formatTime(correction.requested_check_in) }}
                                    </p>
                                    <p class="text-xs text-brand-dark">
                                        <strong>Out:</strong>
                                        {{ formatTime(correction.requested_check_out) }}
                                    </p>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <p
                                    class="text-sm text-brand-light max-w-[200px] truncate"
                                    :title="correction.reason"
                                >
                                    {{ correction.reason }}
                                </p>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <StatusBadge
                                    type="leave-status"
                                    :value="correction.status"
                                    :label="correction.status"
                                />
                            </td>
                            <td class="py-4 px-6">
                                <div
                                    v-if="correction.status === 'pending' && can('attendance-correction-approve')"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <button
                                        @click="showApproveModal(correction)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 transition-all duration-300"
                                        aria-label="Approve correction"
                                    >
                                        <Check class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="onRejectAction(correction)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-all duration-300"
                                        aria-label="Reject correction"
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
        </div>



    <!-- Approve Modal -->
    <ModalWrapper :show="showApproveModalState" title="Approve Correction" maxWidth="md" @close="closeApproveModal">
        <div class="space-y-4">
            <ModalConfirmBanner variant="green" message="Confirm approval for this attendance correction." />
            <div v-if="selectedApproveCorrection" class="rounded-xl border border-brand-border p-4 text-sm space-y-1">
                <p class="font-bold text-brand-dark">{{ selectedApproveCorrection.staff_member?.user?.name }}</p>
                <p>
                    <span class="font-semibold">Date:</span>
                    {{
                        selectedApproveCorrection.attendance
                            ? formatDateShort(selectedApproveCorrection.attendance.date)
                            : "-"
                    }}
                </p>
                <p>
                    <span class="font-semibold">Requested In:</span>
                    {{ formatTime(selectedApproveCorrection.requested_check_in) }}
                </p>
                <p>
                    <span class="font-semibold">Requested Out:</span>
                    {{ formatTime(selectedApproveCorrection.requested_check_out) }}
                </p>
                <p class="mt-2 text-brand-light italic">"{{ selectedApproveCorrection.reason }}"</p>
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
    <ModalWrapper :show="showRejectModalState" title="Reject Correction" maxWidth="md" @close="closeRejectModal">
        <div class="space-y-4">
            <ModalConfirmBanner variant="red" message="Confirm rejection for this attendance correction." />
            <div v-if="selectedRejectCorrection">
                <!-- Details Card -->
                <div class="bg-brand-border/20 border border-brand-border rounded-xl p-4 text-sm shadow-sm">
                    <div class="flex items-center gap-3 mb-3 border-b border-brand-border pb-3">
                        <EmployeeCell
                            :photo="selectedRejectCorrection.staff_member?.user?.profile_photo"
                            :name="selectedRejectCorrection.staff_member?.user?.name || '-'"
                            :subtitle="selectedRejectCorrection.staff_member?.staff_member_id"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-y-2">
                        <p class="text-brand-light text-xs">Date</p>
                        <p class="text-brand-dark font-medium text-right text-xs">
                            {{
                                selectedRejectCorrection.attendance
                                    ? formatDateShort(selectedRejectCorrection.attendance.date)
                                    : "-"
                            }}
                        </p>

                        <p class="text-brand-light text-xs">Requested In</p>
                        <p class="text-brand-dark font-medium text-right text-xs">
                            {{ formatTime(selectedRejectCorrection.requested_check_in) }}
                        </p>

                        <p class="text-brand-light text-xs">Requested Out</p>
                        <p class="text-brand-dark font-medium text-right text-xs">
                            {{ formatTime(selectedRejectCorrection.requested_check_out) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form elements -->
            <div class="flex flex-col gap-2">
                <label class="block text-sm font-bold text-brand-dark">
                    Reason for Rejection
                    <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="rejectReason"
                    class="w-full border border-brand-border rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all"
                    style="background: var(--color-surface); color: var(--color-text-primary)"
                    rows="3"
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
