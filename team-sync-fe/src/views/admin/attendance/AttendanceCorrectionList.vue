<script setup>
import { onMounted, ref } from 'vue';
import { DEFAULT_AVATAR } from '@/helpers/format';
import { storeToRefs } from 'pinia';
import { Check, X, ClipboardList } from 'lucide-vue-next';
import { useAttendanceCorrectionStore } from '@/stores/attendanceCorrection';
import { formatDateShort, formatTime as formatTimeUtil } from '@/utils/dateUtils';
import SearchFilter from '@/components/common/SearchFilter.vue';
import Pagination from '@/components/admin/team/Pagination.vue';
import Alert from '@/components/common/Alert.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ModalWrapper from '@/components/common/ModalWrapper.vue';
import { useSearchFilter } from '@/composables/useSearchFilter';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useToast } from '@/composables/useToast';

const store = useAttendanceCorrectionStore();
const { paginatedCorrections, meta, loading, error } = storeToRefs(store);
const toast = useToast();

const {
  filters,
  fetchData,
  handleSearch,
  handleReset,
  handlePageChange,
  handlePerPageChange,
} = useSearchFilter({
  defaultFilters: { search: null, status: '' },
  fetchFn: store.fetchAllPaginated,
});

onMounted(() => {
  fetchData();
});

const formatTime = (timeStr) => timeStr ? formatTimeUtil(timeStr) : '-';

const getStatusBadge = (status) => {
  switch (status) {
    case "approved": return "bg-green-100 text-green-700";
    case "rejected": return "bg-red-100 text-red-700";
    default: return "bg-amber-100 text-amber-700";
  }
};

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
    toast.success('Approved', 'Attendance correction has been approved.');
    await fetchData();
  },
});

const confirmApprove = () =>
  doApprove(async (correction) => {
      await store.approveCorrection(correction.id, { review_notes: 'Approved via Admin Dashboard' });
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
    toast.success('Rejected', 'Attendance correction has been rejected.');
    await fetchData();
  },
});

const rejectReason = ref('');

const confirmReject = () =>
  doReject(async (correction) => {
      if (!rejectReason.value.trim()) throw new Error('Reason is required');
      await store.rejectCorrection(correction.id, { review_notes: rejectReason.value });
      rejectReason.value = '';
  });

const onRejectAction = (req) => {
    rejectReason.value = '';
    showRejectModal(req);
};
</script>

<template>
  <div class="mb-6">
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
  </div>

  <Alert
    type="error"
    title="Error"
    :message="error || ''"
    :show="Boolean(error)"
  />

  <div class="bg-white border border-[#DCDEDD] rounded-[20px] mb-6 p-5">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-[#0C1C3C] font-['Plus_Jakarta_Sans'] text-[20px] font-bold">
          Attendance Corrections
        </h3>
        <p class="text-[#6B7280] font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">
          Showing {{ meta.from || 0 }} - {{ meta.to || 0 }} of {{ meta.total || 0 }} requests
        </p>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto w-full mb-6">
      <table class="w-full min-w-[800px]">
        <thead>
          <tr class="border-y border-[#DCDEDD]">
            <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Employee</th>
            <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Date</th>
            <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Requested Times</th>
            <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Reason</th>
            <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Status</th>
            <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-if="loading"
            class="border-b border-[#DCDEDD] animate-pulse"
          >
             <td colspan="6" class="py-8 text-center text-gray-500">Loading...</td>
          </tr>
          <tr
             v-else-if="!paginatedCorrections || paginatedCorrections.length === 0"
             class="border-b border-[#DCDEDD]"
          >
             <td colspan="6" class="py-8">
                 <EmptyState icon="ClipboardList" title="No Corrections Found" description="There are no attendance correction requests currently matching filters." />
             </td>
          </tr>
          <tr
            v-else
            v-for="correction in paginatedCorrections"
            :key="correction.id"
            class="border-b border-[#DCDEDD] hover:bg-gray-50 transition-colors"
          >
            <td class="py-4 px-4">
              <div class="flex items-center gap-3">
                <img
                  :src="correction.employee?.user?.profile_photo || DEFAULT_AVATAR"
                  alt="Avatar"
                  class="w-10 h-10 rounded-full object-cover"
                />
                <div>
                  <p class="text-sm font-semibold text-brand-dark">{{ correction.employee?.user?.name }}</p>
                  <p class="text-xs text-brand-light">{{ correction.employee?.employee_id }}</p>
                </div>
              </div>
            </td>
            <td class="py-4 px-4 text-sm text-brand-dark font-medium">
              {{ correction.attendance ? formatDateShort(correction.attendance.date) : '-' }}
            </td>
            <td class="py-4 px-4">
              <div class="flex flex-col gap-1">
                 <p class="text-xs text-brand-dark"><strong>In:</strong> {{ formatTime(correction.requested_check_in) }}</p>
                 <p class="text-xs text-brand-dark"><strong>Out:</strong> {{ formatTime(correction.requested_check_out) }}</p>
              </div>
            </td>
            <td class="py-4 px-4">
               <p class="text-sm text-brand-light max-w-[200px] truncate" :title="correction.reason">{{ correction.reason }}</p>
            </td>
            <td class="py-4 px-4">
              <span :class="['px-3 py-1 rounded-full text-xs font-semibold capitalize whitespace-nowrap', getStatusBadge(correction.status)]">
                {{ correction.status }}
              </span>
            </td>
            <td class="py-4 px-4">
               <div class="flex items-center gap-2" v-if="correction.status === 'pending'">
                  <button
                    @click="showApproveModal(correction)"
                    class="btn-secondary flex items-center justify-center gap-2 border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:bg-blue-50 transition-all duration-300 px-3 py-2"
                  >
                    <Check class="w-4 h-4 text-green-600" />
                    <span class="text-brand-dark text-xs font-semibold">Approve</span>
                  </button>
                  <button
                    @click="onRejectAction(correction)"
                    class="btn-secondary flex items-center justify-center gap-2 border border-[#DCDEDD] rounded-[8px] hover:border-red-500 hover:bg-red-50 transition-all duration-300 px-3 py-2"
                  >
                    <X class="w-4 h-4 text-red-600" />
                    <span class="text-brand-dark text-xs font-semibold">Reject</span>
                  </button>
               </div>
               <div v-else class="text-xs text-brand-light">
                    Reviewed by {{ correction.reviewer?.name || 'Admin' }}
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

  <!-- Approve Modal -->
  <ModalWrapper
    :show="showApproveModalState"
    title="Approve Correction"
    maxWidth="md"
    @close="closeApproveModal"
  >
    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center shrink-0">
        <Check class="w-6 h-6 text-green-600" />
      </div>
      <div>
        <p class="text-brand-light text-sm">
          Confirm approval for this attendance correction.
        </p>
      </div>
    </div>
    <div v-if="selectedApproveCorrection" class="mb-6 space-y-3">
        <div class="border border-[#DCDEDD] rounded-[12px] p-4 text-sm">
           <p class="font-bold text-brand-dark mb-2">{{ selectedApproveCorrection.employee?.user?.name }}</p>
           <p><strong>Date:</strong> {{ selectedApproveCorrection.attendance ? formatDateShort(selectedApproveCorrection.attendance.date) : '-' }}</p>
           <p><strong>Requested In:</strong> {{ formatTime(selectedApproveCorrection.requested_check_in) }}</p>
           <p><strong>Requested Out:</strong> {{ formatTime(selectedApproveCorrection.requested_check_out) }}</p>
           <p class="mt-2 text-brand-light italic">"{{ selectedApproveCorrection.reason }}"</p>
        </div>
    </div>
    <template #footer>
      <div class="flex gap-3">
        <button
          @click="closeApproveModal"
          :disabled="processingApprove"
          class="flex-1 px-4 py-3 border border-[#DCDEDD] rounded-[12px] text-brand-dark text-sm font-semibold hover:border-[#0C51D9] hover:border-2 transition-all duration-300"
        >
          Cancel
        </button>
        <button
          @click="confirmApprove"
          :disabled="processingApprove"
          class="flex-1 px-4 py-3 bg-green-600 text-white rounded-[12px] text-sm font-semibold hover:bg-green-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ processingApprove ? "Approving..." : "Approve" }}
        </button>
      </div>
    </template>
  </ModalWrapper>

  <!-- Reject Modal -->
  <ModalWrapper
    :show="showRejectModalState"
    title="Reject Correction"
    maxWidth="md"
    @close="closeRejectModal"
  >
    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
        <X class="w-6 h-6 text-red-600" />
      </div>
      <div>
        <p class="text-brand-light text-sm">
          Confirm rejection for this attendance correction.
        </p>
      </div>
    </div>
    <div v-if="selectedRejectCorrection" class="mb-4">
        <!-- Details Card -->
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm mb-5 shadow-sm">
           <div class="flex items-center gap-3 mb-3 border-b border-gray-200 pb-3">
             <img :src="selectedRejectCorrection.employee?.user?.profile_photo || DEFAULT_AVATAR" class="w-10 h-10 rounded-full object-cover" />
             <div>
               <p class="font-bold text-gray-900">{{ selectedRejectCorrection.employee?.user?.name }}</p>
               <p class="text-xs text-gray-500">{{ selectedRejectCorrection.employee?.employee_id }}</p>
             </div>
           </div>
           <div class="grid grid-cols-2 gap-y-2">
             <p class="text-gray-500 text-xs">Date</p>
             <p class="text-gray-900 font-medium text-right text-xs">{{ selectedRejectCorrection.attendance ? formatDateShort(selectedRejectCorrection.attendance.date) : '-' }}</p>

             <p class="text-gray-500 text-xs">Requested In</p>
             <p class="text-gray-900 font-medium text-right text-xs">{{ formatTime(selectedRejectCorrection.requested_check_in) }}</p>

             <p class="text-gray-500 text-xs">Requested Out</p>
             <p class="text-gray-900 font-medium text-right text-xs">{{ formatTime(selectedRejectCorrection.requested_check_out) }}</p>
           </div>
        </div>

        <!-- Form elements -->
        <div class="flex flex-col gap-2">
           <label class="block text-sm font-bold text-gray-800">Reason for Rejection <span class="text-red-500">*</span></label>
           <textarea 
             v-model="rejectReason" 
             class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all bg-white" 
             rows="3" 
             placeholder="Please explain why this correction is denied so the employee understands..."></textarea>
        </div>
    </div>
    <template #footer>
      <div class="flex gap-3">
        <button
          @click="closeRejectModal"
          :disabled="processingReject"
          class="flex-1 px-4 py-3 border border-[#DCDEDD] rounded-[12px] text-brand-dark text-sm font-semibold hover:border-[#0C51D9] hover:border-2 transition-all duration-300"
        >
          Cancel
        </button>
        <button
          @click="confirmReject"
          :disabled="processingReject || !rejectReason.trim()"
          class="flex-1 px-4 py-3 bg-red-600 text-white rounded-[12px] text-sm font-semibold hover:bg-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ processingReject ? "Rejecting..." : "Reject" }}
        </button>
      </div>
    </template>
  </ModalWrapper>
</template>
