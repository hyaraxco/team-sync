<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { DEFAULT_AVATAR } from '@/helpers/format';
import { storeToRefs } from 'pinia';
import { useLeaveRequestStore } from '@/stores/leaveRequest';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useSearchFilter } from '@/composables/useSearchFilter';
import { useToast } from '@/composables/useToast';
import { formatDateShort } from '@/utils/dateUtils';
import { Check, X, ClipboardList, CalendarDays, List, ChevronLeft, ChevronRight, FileSearch, ExternalLink } from 'lucide-vue-next';
import SearchFilter from '@/components/common/SearchFilter.vue';
import Pagination from '@/components/admin/team/Pagination.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ModalWrapper from '@/components/common/ModalWrapper.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { DateTime } from 'luxon';

const store = useLeaveRequestStore();
const { leaveRequests, meta, loading, calendarData, error } = storeToRefs(store);
const toast = useToast();
const selectedIds = ref([]);
const processingBulkAction = ref(false);

const activeTab = ref('list'); // 'list' or 'calendar'

// ---- LIST VIEW LOGIC ----
const {
  filters,
  fetchData,
  handleSearch,
  handleReset,
  handlePageChange,
  handlePerPageChange,
} = useSearchFilter({
  defaultFilters: { search: null, status: '' },
  fetchFn: store.fetchLeaveRequestsPaginated,
});

// ---- CALENDAR VIEW LOGIC ----
const currentMonth = ref(DateTime.now().startOf('month'));
const calendarGrid = computed(() => {
    const start = currentMonth.value.startOf('week'); // Monday
    const end = currentMonth.value.endOf('month').endOf('week'); // Sunday
    
    const days = [];
    let curr = start;
    while (curr <= end) {
        days.push(curr);
        curr = curr.plus({ days: 1 });
    }
    return days;
});

const fetchMonthData = async () => {
    const monthStr = currentMonth.value.toFormat('yyyy-MM');
    await store.fetchCalendarData(monthStr);
};

const nextMonth = () => {
    currentMonth.value = currentMonth.value.plus({ months: 1 });
    fetchMonthData();
};
const prevMonth = () => {
    currentMonth.value = currentMonth.value.minus({ months: 1 });
    fetchMonthData();
};

const getLeavesForDate = (date) => {
    return calendarData.value.filter(req => {
        const start = DateTime.fromISO(req.start_date).startOf('day');
        const end = DateTime.fromISO(req.end_date).startOf('day');
        return date >= start && date <= end;
    });
};

watch(activeTab, (newTab) => {
    if (newTab === 'calendar' && (!calendarData.value || calendarData.value.length === 0)) {
        fetchMonthData();
    }
});

watch(leaveRequests, (requests) => {
    const pendingIds = (requests || [])
        .filter((request) => request.status === 'pending')
        .map((request) => request.id);

    selectedIds.value = selectedIds.value.filter((id) => pendingIds.includes(id));
}, { deep: false });

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
    toast.success('Approved', 'Leave request has been approved.');
    if (activeTab.value === 'list') {
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
    toast.success('Rejected', 'Leave request has been rejected.');
    if (activeTab.value === 'list') {
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
    (leaveRequests.value || []).filter((request) => request.status === 'pending')
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

    if (typeof message === 'string' && message.trim().length > 0) {
        return message;
    }

    if (validationErrors && typeof validationErrors === 'object') {
        const firstError = Object.values(validationErrors).flat()[0];
        if (typeof firstError === 'string' && firstError.trim().length > 0) {
            return firstError;
        }
    }

    if (typeof error.value === 'string' && error.value.trim().length > 0) {
        return error.value;
    }

    return 'Failed to process selected leave requests.';
};

const runBulkAction = async (action) => {
    if (!selectedIds.value.length) {
        toast.warning('No Selection', 'Please select at least one pending leave request.');
        return;
    }

    processingBulkAction.value = true;

    try {
        await store.bulkAction(selectedIds.value, action);
        const count = selectedIds.value.length;
        toast.success(
            action === 'approve' ? 'Approved' : 'Rejected',
            `${count} leave request${count > 1 ? 's' : ''} ${action === 'approve' ? 'approved' : 'rejected'} successfully.`
        );

        selectedIds.value = [];
        await fetchData();
    } catch (axiosError) {
        toast.error('Bulk Action Failed', normalizeErrorMessage(axiosError));
    } finally {
        processingBulkAction.value = false;
    }
};

// ---- PROOF REVIEW WORKFLOW ----
const proofReviewForm = ref({
    status: 'approved',
    notes: ''
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
    toast.success('Proof Reviewed', 'Sick leave proof has been reviewed.');
    if (activeTab.value === 'list') {
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
          proof_review_notes: proofReviewForm.value.notes
      });
  });

const openReviewProof = (request) => {
    proofReviewForm.value = { status: 'approved', notes: '' };
    showReviewProofModal(request);
};

const getProofUrl = (path) => {
  if (!path) return '#';
  const baseUrl = import.meta.env.VITE_API_BASE_URL.replace('/api/v1', '');
  return `${baseUrl}/storage/${path}`;
};

onMounted(() => {
    fetchData();
});
</script>

<template>
  <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h2 class="text-2xl font-bold text-brand-dark">Leave Requests</h2>
        <p class="text-sm text-brand-light mt-1">Manage and monitor employee leave requests.</p>
      </div>

      <!-- Tab Switcher -->
      <div class="bg-gray-100 p-1 flex rounded-lg">
          <button @click="activeTab = 'list'" :class="['px-4 py-2 text-sm font-semibold rounded-md flex items-center gap-2 transition-all duration-200', activeTab === 'list' ? 'bg-white shadow text-brand-dark' : 'text-gray-500 hover:text-brand-dark']">
              <List class="w-4 h-4" /> List
          </button>
          <button @click="activeTab = 'calendar'" :class="['px-4 py-2 text-sm font-semibold rounded-md flex items-center gap-2 transition-all duration-200', activeTab === 'calendar' ? 'bg-white shadow text-brand-dark' : 'text-gray-500 hover:text-brand-dark']">
              <CalendarDays class="w-4 h-4" /> Calendar
          </button>
      </div>
  </div>

  <!-- LIST VIEW -->
  <div v-if="activeTab === 'list'">
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
          @reset="handleReset"
        />
      </div>

      <div class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <p class="text-sm text-brand-light">
          {{ selectedPendingCount }} pending request{{ selectedPendingCount > 1 ? 's' : '' }} selected
        </p>
        <div class="flex items-center gap-2">
          <button
            @click="runBulkAction('approve')"
            :disabled="loading || processingBulkAction || selectedPendingCount === 0"
            class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-[10px] hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ processingBulkAction ? 'Processing...' : 'Approve Selected' }}
          </button>
          <button
            @click="runBulkAction('reject')"
            :disabled="loading || processingBulkAction || selectedPendingCount === 0"
            class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-[10px] hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ processingBulkAction ? 'Processing...' : 'Reject Selected' }}
          </button>
        </div>
      </div>

      <div class="bg-white border border-[#DCDEDD] rounded-[20px] mb-6 p-5">
        <!-- Table -->
        <div class="overflow-x-auto w-full mb-6">
          <table class="w-full min-w-[860px]">
            <thead>
              <tr class="border-y border-[#DCDEDD]">
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm w-[48px]">
                  <input
                    type="checkbox"
                    :checked="allSelectableSelected"
                    :disabled="loading || !selectableRequests.length || processingBulkAction"
                    @change="toggleSelectAll"
                    class="w-4 h-4 rounded border-gray-300 text-brand-dark focus:ring-brand-dark disabled:opacity-50"
                    title="Select all pending requests"
                  />
                </th>
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Employee</th>
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Date</th>
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Reason & Type</th>
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Proof</th>
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Status</th>
                <th class="py-4 px-4 text-left text-[#6B7280] font-semibold text-sm">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-if="loading"
                class="border-b border-[#DCDEDD] animate-pulse"
              >
                 <td colspan="8" class="py-8 text-center text-gray-500">Loading...</td>
              </tr>
              <tr
                 v-else-if="!leaveRequests || leaveRequests.length === 0"
                 class="border-b border-[#DCDEDD]"
              >
                 <td colspan="8" class="py-8">
                     <EmptyState icon="ClipboardList" title="No Requests Found" description="There are no leave requests currently matching filters." />
                 </td>
              </tr>
              <tr
                v-else
                v-for="request in leaveRequests"
                :key="request.id"
                class="border-b border-[#DCDEDD] hover:bg-gray-50 transition-colors"
              >
                <td class="py-4 px-4">
                  <input
                    v-if="request.status === 'pending'"
                    v-model="selectedIds"
                    type="checkbox"
                    :value="request.id"
                    :disabled="loading || processingBulkAction"
                    class="w-4 h-4 rounded border-gray-300 text-brand-dark focus:ring-brand-dark disabled:opacity-50"
                    :aria-label="`Select leave request ${request.id}`"
                  />
                </td>
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <img
                      :src="request.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                      alt="Avatar"
                      class="w-10 h-10 rounded-full object-cover"
                    />
                    <div>
                      <p class="text-sm font-semibold text-brand-dark">{{ request.staff_member?.user?.name }}</p>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm text-brand-dark font-medium">{{ formatDateShort(request.start_date) }} - {{ formatDateShort(request.end_date) }}</div>
                  <div class="text-xs text-brand-light">{{ request.days }} Days</div>
                </td>
                <td class="py-4 px-4">
                    <StatusBadge type="leave-type" :value="request.type" class="mb-1" />
                    <p class="text-sm text-brand-dark max-w-[200px] truncate" :title="request.reason">{{ request.reason }}</p>
                </td>
                <td class="py-4 px-4">
                    <div v-if="request.type === 'sick'" class="flex flex-col gap-1">
                        <a v-if="request.proof_file_path" :href="getProofUrl(request.proof_file_path)" target="_blank" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                            <ExternalLink class="w-3 h-3" /> View Proof
                        </a>
                        <span v-else class="text-xs text-gray-500 italic">No proof</span>
                        
                        <div v-if="request.proof_file_path" class="mt-1">
                            <span :class="['text-xs px-2 py-0.5 rounded', request.proof_review_status === 'approved' ? 'bg-green-100 text-green-700' : request.proof_review_status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700']">
                                {{ request.proof_review_status || 'pending' }}
                            </span>
                        </div>
                    </div>
                    <span v-else class="text-xs text-gray-400">-</span>
                </td>
                <td class="py-4 px-4">
                    <StatusBadge type="leave-status" :value="request.status" />
                </td>
                <td class="py-4 px-4">
                   <div class="flex items-center gap-2" v-if="request.status === 'pending' || (request.type === 'sick' && request.proof_file_path && request.proof_review_status === 'pending')">
                      <button
                        v-if="request.status === 'pending'"
                        @click="showApproveModal(request)"
                        title="Approve Leave"
                        class="btn-secondary flex items-center justify-center gap-2 border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:bg-blue-50 transition-all duration-300 px-3 py-2"
                      >
                        <Check class="w-4 h-4 text-green-600" />
                      </button>
                      <button
                        v-if="request.status === 'pending'"
                        @click="showRejectModal(request)"
                        title="Reject Leave"
                        class="btn-secondary flex items-center justify-center gap-2 border border-[#DCDEDD] rounded-[8px] hover:border-red-500 hover:bg-red-50 transition-all duration-300 px-3 py-2"
                      >
                        <X class="w-4 h-4 text-red-600" />
                      </button>
                      <button
                        v-if="request.type === 'sick' && request.proof_file_path && request.proof_review_status === 'pending'"
                        @click="openReviewProof(request)"
                        title="Review Proof"
                        class="btn-secondary flex items-center justify-center gap-2 border border-[#DCDEDD] rounded-[8px] hover:border-yellow-500 hover:bg-yellow-50 transition-all duration-300 px-3 py-2"
                      >
                        <FileSearch class="w-4 h-4 text-yellow-600" />
                      </button>
                   </div>
                   <div v-else class="text-xs text-brand-light">-</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <Pagination
          :meta="meta"
          :loading="loading"
          @page-change="handlePageChange"
          @per-page-change="handlePerPageChange"
        />
      </div>
  </div>

  <!-- CALENDAR VIEW -->
  <div v-else class="bg-white border border-[#DCDEDD] rounded-[20px] p-5">
      <div class="flex items-center justify-between mb-6">
          <h3 class="text-[#0C1C3C] text-[20px] font-bold">{{ currentMonth.toFormat('MMMM yyyy') }}</h3>
          <div class="flex gap-2">
              <button @click="prevMonth" class="p-2 border rounded-md hover:bg-gray-50" :disabled="loading">
                  <ChevronLeft class="w-5 h-5 text-gray-600" />
              </button>
              <button @click="currentMonth = DateTime.now().startOf('month'); fetchMonthData()" class="px-4 py-2 text-sm font-semibold border rounded-md hover:bg-gray-50" :disabled="loading">
                  Today
              </button>
              <button @click="nextMonth" class="p-2 border rounded-md hover:bg-gray-50" :disabled="loading">
                  <ChevronRight class="w-5 h-5 text-gray-600" />
              </button>
          </div>
      </div>

      <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden">
          <!-- Calendar Header -->
          <div v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="day" class="bg-gray-50 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
              {{ day }}
          </div>

          <!-- Calendar Days -->
          <div 
             v-for="date in calendarGrid" 
             :key="date.toISODate()"
             :class="[
                 'bg-white min-h-[120px] p-2 hover:bg-gray-50 transition-colors',
                 { 'opacity-50 bg-gray-50': date.month !== currentMonth.month }
             ]"
          >
             <div class="flex justify-between items-start mb-2">
                 <span :class="['text-sm font-medium w-6 h-6 flex items-center justify-center rounded-full', date.toISODate() === DateTime.now().toISODate() ? 'bg-brand-dark text-white' : 'text-gray-700']">
                     {{ date.toFormat('d') }}
                 </span>
             </div>
             
             <!-- Leaves for this day -->
             <div class="flex flex-col gap-1">
                 <div 
                    v-for="req in getLeavesForDate(date)" 
                    :key="req.id"
                    :class="[
                        'px-2 py-1 text-xs rounded truncate cursor-pointer',
                        req.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800 border border-amber-200 border-dashed'
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
    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center shrink-0">
        <Check class="w-6 h-6 text-green-600" />
      </div>
      <p class="text-brand-light text-sm">Confirm approval for this leave request.</p>
    </div>
    <template #footer>
      <div class="flex gap-3">
        <button @click="closeApproveModal" :disabled="processingApprove" class="flex-1 px-4 py-3 border border-[#DCDEDD] rounded-[12px] text-brand-dark text-sm font-semibold hover:border-[#0C51D9] hover:border-2 transition-all duration-300">Cancel</button>
        <button @click="confirmApprove" :disabled="processingApprove" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-[12px] text-sm font-semibold hover:bg-green-700 transition-all duration-300">
          {{ processingApprove ? "Approving..." : "Approve" }}
        </button>
      </div>
    </template>
  </ModalWrapper>

  <!-- Reject Modal -->
  <ModalWrapper :show="showRejectModalState" title="Reject Leave Request" maxWidth="md" @close="closeRejectModal">
    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
        <X class="w-6 h-6 text-red-600" />
      </div>
      <p class="text-brand-light text-sm">Confirm rejection for this leave request.</p>
    </div>
    <template #footer>
      <div class="flex gap-3">
        <button @click="closeRejectModal" :disabled="processingReject" class="flex-1 px-4 py-3 border border-[#DCDEDD] rounded-[12px] text-brand-dark text-sm font-semibold hover:border-[#0C51D9] hover:border-2 transition-all duration-300">Cancel</button>
        <button @click="confirmReject" :disabled="processingReject" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-[12px] text-sm font-semibold hover:bg-red-700 transition-all duration-300">
          {{ processingReject ? "Rejecting..." : "Reject" }}
        </button>
      </div>
    </template>
  </ModalWrapper>

  <!-- Review Proof Modal -->
  <ModalWrapper :show="showReviewProofModalState" title="Review Sick Leave Proof" maxWidth="md" @close="closeReviewProofModal">
    <div class="mb-4">
      <p class="text-brand-light text-sm mb-4">Please review the medical certificate for this sick leave request.</p>
      
      <div v-if="selectedProofRequest?.proof_file_path" class="mb-4 p-3 bg-gray-50 border rounded flex justify-between items-center">
         <span class="text-sm font-medium text-gray-700">{{ selectedProofRequest.proof_file_name }}</span>
         <a :href="getProofUrl(selectedProofRequest.proof_file_path)" target="_blank" class="text-blue-600 hover:underline text-sm flex items-center gap-1">
             <ExternalLink class="w-4 h-4" /> View File
         </a>
      </div>

      <div class="space-y-4">
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Review Decision *</label>
              <select v-model="proofReviewForm.status" class="w-full px-4 py-2 border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] focus:border-[#0C51D9]">
                  <option value="approved">Approve Proof</option>
                  <option value="rejected">Reject Proof</option>
              </select>
          </div>
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Review Notes</label>
              <textarea v-model="proofReviewForm.notes" rows="3" class="w-full px-4 py-2 border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] focus:border-[#0C51D9] resize-none" placeholder="Add any notes about this proof..."></textarea>
          </div>
      </div>
    </div>
    <template #footer>
      <div class="flex gap-3">
        <button @click="closeReviewProofModal" :disabled="processingProofReview" class="flex-1 px-4 py-3 border border-[#DCDEDD] rounded-[12px] text-brand-dark text-sm font-semibold hover:border-[#0C51D9] hover:border-2 transition-all duration-300">Cancel</button>
        <button @click="confirmReviewProof" :disabled="processingProofReview" class="flex-1 px-4 py-3 bg-brand-dark text-white rounded-[12px] text-sm font-semibold hover:bg-opacity-90 transition-all duration-300">
          {{ processingProofReview ? "Submitting..." : "Submit Review" }}
        </button>
      </div>
    </template>
  </ModalWrapper>

</template>
