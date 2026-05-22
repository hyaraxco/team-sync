<script setup>
import { onMounted, ref } from "vue";
import { DEFAULT_AVATAR } from "@/helpers/format";
import { storeToRefs } from "pinia";
import { Check, X } from "lucide-vue-next";
import { useAttendanceCorrectionStore } from "@/stores/attendanceCorrection";
import { formatDateShort, formatTime as formatTimeUtil } from "@/utils/dateUtils";
import SearchFilter from "@/components/common/SearchFilter.vue";
import Pagination from "@/components/admin/team/Pagination.vue";
import Alert from "@/components/common/Alert.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { useToast } from "@/composables/useToast";
import { can } from "@/helpers/permissionHelper";

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
    isModalOpen: showRejectModalState,
    selectedItem: selectedRejectCorrection,
    isProcessing: processingReject,
    openModal: showRejectModal,
    closeModal: closeRejectModal,
    confirmAction: doReject,
} = useConfirmAction({
    onSuccess: async () => {
        toast.success("Rejected", "Attendance correction has been rejected.");
        await fetchData();
    },
});

const rejectReason = ref("");

const confirmReject = () =>
    doReject(async (correction) => {
        if (!rejectReason.value.trim()) throw new Error("Reason is required");
        await store.rejectCorrection(correction.id, { review_notes: rejectReason.value });
        rejectReason.value = "";
    });

const onRejectAction = (req) => {
    rejectReason.value = "";
    showRejectModal(req);
};
</script>

<template>
    <div class="p-3 sm:p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <span class="sr-only" role="heading" aria-level="1">Attendance Corrections</span>

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

            <div class="bg-white border border-brand-border rounded-2xl p-5">
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">Attendance Corrections</p>
                <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">
                    Showing {{ meta.from || 0 }} - {{ meta.to || 0 }} of {{ meta.total || 0 }} requests
                </p>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto w-full mb-6">
            <table class="w-full min-w-[800px]">
                <thead>
                    <tr class="border-y border-brand-border">
                        <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Employee</th>
                        <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Date</th>
                        <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Requested Times</th>
                        <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Reason</th>
                        <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Status</th>
                        <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading" class="border-b border-brand-border animate-pulse">
                        <td colspan="6" class="py-8 text-center text-brand-light">Loading...</td>
                    </tr>
                    <tr
                        v-else-if="!paginatedCorrections || paginatedCorrections.length === 0"
                        class="border-b border-brand-border"
                    >
                        <td colspan="6" class="py-8">
                            <EmptyState
                                icon="ClipboardList"
                                title="Data koreksi kosong"
                                subtitle="Tidak ada pengajuan koreksi absensi yang cocok dengan filter."
                            />
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="correction in paginatedCorrections"
                        :key="correction.id"
                        class="border-b border-brand-border hover:bg-brand-border/20 transition-colors"
                    >
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-3">
                                <img loading="lazy"
                                    :src="correction.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                                    alt="Avatar"
                                    class="w-10 h-10 rounded-full object-cover"
                                />
                                <div>
                                    <p class="text-sm font-semibold text-brand-dark">
                                        {{ correction.staff_member?.user?.name }}
                                    </p>
                                    <p class="text-xs text-brand-light">
                                        {{ correction.staff_member?.staff_member_id }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 text-sm text-brand-dark font-medium">
                            {{ correction.attendance ? formatDateShort(correction.attendance.date) : "-" }}
                        </td>
                        <td class="py-4 px-4">
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
                        <td class="py-4 px-4">
                            <p class="text-sm text-brand-light max-w-[200px] truncate" :title="correction.reason">
                                {{ correction.reason }}
                            </p>
                        </td>
                        <td class="py-4 px-4">
                            <StatusBadge type="leave-status" :value="correction.status" :label="correction.status" />
                        </td>
                        <td class="py-4 px-4">
                            <div
                                class="flex items-center gap-2"
                                v-if="correction.status === 'pending' && can('attendance-correction-approve')"
                            >
                                <button
                                    @click="showApproveModal(correction)"
                                    class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:border-brand-primary hover:bg-blue-50 transition-all duration-300 px-3 py-2"
                                >
                                    <Check class="w-4 h-4 text-green-600" />
                                    <span class="text-brand-dark text-xs font-semibold">Approve</span>
                                </button>
                                <button
                                    @click="onRejectAction(correction)"
                                    class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:border-red-500 hover:bg-red-50 transition-all duration-300 px-3 py-2"
                                >
                                    <X class="w-4 h-4 text-red-600" />
                                    <span class="text-brand-dark text-xs font-semibold">Reject</span>
                                </button>
                            </div>
                            <div v-else class="text-xs text-brand-light">
                                {{
                                    correction.status === "pending"
                                        ? "Pending Review"
                                        : `Reviewed by ${correction.reviewer?.name || "Admin"}`
                                }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <Pagination
            :meta="meta"
            :loading="loading"
            @page-change="handlePageChange"
            @per-page-change="handlePerPageChange"
        />
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <ModalWrapper :show="showApproveModalState" title="Approve Correction" maxWidth="md" @close="closeApproveModal">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                <Check class="w-6 h-6 text-green-600" />
            </div>
            <div>
                <p class="text-brand-light text-sm">Confirm approval for this attendance correction.</p>
            </div>
        </div>
        <div v-if="selectedApproveCorrection" class="mb-6 space-y-3">
            <div class="border border-brand-border rounded-xl p-4 text-sm">
                <p class="font-bold text-brand-dark mb-2">{{ selectedApproveCorrection.staff_member?.user?.name }}</p>
                <p>
                    <strong>Date:</strong>
                    {{
                        selectedApproveCorrection.attendance
                            ? formatDateShort(selectedApproveCorrection.attendance.date)
                            : "-"
                    }}
                </p>
                <p>
                    <strong>Requested In:</strong>
                    {{ formatTime(selectedApproveCorrection.requested_check_in) }}
                </p>
                <p>
                    <strong>Requested Out:</strong>
                    {{ formatTime(selectedApproveCorrection.requested_check_out) }}
                </p>
                <p class="mt-2 text-brand-light italic">"{{ selectedApproveCorrection.reason }}"</p>
            </div>
        </div>
        <template #footer>
            <div class="flex gap-3">
                <button
                    @click="closeApproveModal"
                    :disabled="processingApprove"
                    class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300"
                >
                    Cancel
                </button>
                <button
                    @click="confirmApprove"
                    :disabled="processingApprove"
                    class="flex-1 px-4 py-3 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ processingApprove ? "Approving..." : "Approve" }}
                </button>
            </div>
        </template>
    </ModalWrapper>

    <!-- Reject Modal -->
    <ModalWrapper :show="showRejectModalState" title="Reject Correction" maxWidth="md" @close="closeRejectModal">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                <X class="w-6 h-6 text-red-600" />
            </div>
            <div>
                <p class="text-brand-light text-sm">Confirm rejection for this attendance correction.</p>
            </div>
        </div>
        <div v-if="selectedRejectCorrection" class="mb-4">
            <!-- Details Card -->
            <div class="bg-brand-border/20 border border-brand-border rounded-xl p-4 text-sm mb-5 shadow-sm">
                <div class="flex items-center gap-3 mb-3 border-b border-brand-border pb-3">
                    <img loading="lazy"
                        :src="selectedRejectCorrection.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                        class="w-10 h-10 rounded-full object-cover"
                    />
                    <div>
                        <p class="font-bold text-brand-dark">{{ selectedRejectCorrection.staff_member?.user?.name }}</p>
                        <p class="text-xs text-brand-light">
                            {{ selectedRejectCorrection.staff_member?.staff_member_id }}
                        </p>
                    </div>
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

            <!-- Form elements -->
            <div class="flex flex-col gap-2">
                <label class="block text-sm font-bold text-brand-dark">
                    Reason for Rejection
                    <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="rejectReason"
                    class="w-full border border-brand-border rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all bg-white"
                    rows="3"
                    placeholder="Please explain why this correction is denied so the employee understands..."
                ></textarea>
            </div>
        </div>
        <template #footer>
            <div class="flex gap-3">
                <button
                    @click="closeRejectModal"
                    :disabled="processingReject"
                    class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300"
                >
                    Cancel
                </button>
                <button
                    @click="confirmReject"
                    :disabled="processingReject || !rejectReason.trim()"
                    class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ processingReject ? "Rejecting..." : "Reject" }}
                </button>
            </div>
        </template>
    </ModalWrapper>
</template>
