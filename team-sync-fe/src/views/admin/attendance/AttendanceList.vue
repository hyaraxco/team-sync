<script setup>
import { ref, onMounted } from "vue";
import { DEFAULT_AVATAR } from "@/helpers/format";
import { RouterLink } from "vue-router";
import { TrendingUp, CheckCircle, Star, Clock, CalendarClock, CalendarDays, Check, X } from "lucide-vue-next";
import { useAttendanceStore } from "@/stores/attendance";
import { useLeaveRequestStore } from "@/stores/leaveRequest";
import { useAttendanceCorrectionStore } from "@/stores/attendanceCorrection";
import StatusBadge from "@/components/common/StatusBadge.vue";
import StatsCard from "@/components/common/StatsCard.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import { formatDateShort, formatTime as formatTimeUtil } from "@/utils/dateUtils.js";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { can } from "@/helpers/permissionHelper";
import { useConfirmAction } from "@/composables/useConfirmAction";
import { useToast } from "@/composables/useToast";

const attendanceStore = useAttendanceStore();
const leaveRequestStore = useLeaveRequestStore();
const attendanceCorrectionStore = useAttendanceCorrectionStore();
const toast = useToast();

const statistics = ref({
    present_today: 0,
    present_change: 0,
    absent_today: 0,
    absent_change: 0,
    late_today: 0,
    on_leave_today: 0,
    remote_today: 0,
    attendance_rate: 0,
    rate_change: 0,
    pending_requests: 0,
});
const leaveRequests = ref([]);
const pendingCorrections = ref([]);
const loadingLeaveRequests = ref(false);
const loadingCorrections = ref(false);

const {
    isModalOpen: showApproveModalState,
    selectedItem: selectedApproveRequest,
    isProcessing: processingApprove,
    openModal: showApproveModal,
    closeModal: closeApproveModal,
    confirmAction: doApprove,
} = useConfirmAction({
    onSuccess: async () => {
        await loadLeaveRequests();
        await loadStatistics();
    },
});

const {
    isModalOpen: showRejectModalState,
    selectedItem: selectedRejectRequest,
    isProcessing: processingReject,
    openModal: showRejectModal,
    closeModal: closeRejectModal,
    confirmAction: doReject,
} = useConfirmAction({
    onSuccess: async () => {
        await loadLeaveRequests();
        await loadStatistics();
    },
});
const loadingStatistics = ref(false);
const loadStatistics = async () => {
    loadingStatistics.value = true;
    try {
        const data = await attendanceStore.fetchAdminStatistics();
        statistics.value = {
            present_today: data?.present_today || 0,
            present_change: data?.present_change || 0,
            absent_today: data?.absent_today || 0,
            absent_change: data?.absent_change || 0,
            late_today: data?.late_today || 0,
            on_leave_today: data?.on_leave_today || 0,
            remote_today: data?.remote_today || 0,
            attendance_rate: data?.attendance_rate || 0,
            rate_change: data?.rate_change || 0,
            pending_requests: data?.pending_requests || 0,
        };
    } catch (error) {
        toast.error(
            "Failed to load attendance statistics",
            attendanceStore.error || error?.response?.data?.message || "Failed to load statistics.",
        );
    } finally {
        loadingStatistics.value = false;
    }
};

const loadLeaveRequests = async () => {
    if (!can("leave-request-list")) return;
    loadingLeaveRequests.value = true;
    try {
        leaveRequests.value = await leaveRequestStore.fetchLatestLeaveRequests(5);
    } catch (error) {
        toast.error(
            "Failed to load leave requests",
            leaveRequestStore.error || error?.response?.data?.message || "Failed to load leave requests.",
        );
    } finally {
        loadingLeaveRequests.value = false;
    }
};

const loadCorrections = async () => {
    if (!can("attendance-correction-list")) return;
    loadingCorrections.value = true;
    try {
        await attendanceCorrectionStore.fetchAllPaginated({ status: "pending", row_per_page: 5, page: 1 });
        pendingCorrections.value = attendanceCorrectionStore.paginatedCorrections || [];
    } catch (error) {
        toast.error(
            "Failed to load attendance corrections",
            attendanceCorrectionStore.error || error?.response?.data?.message || "Failed to load corrections.",
        );
    } finally {
        loadingCorrections.value = false;
    }
};

const formatDate = (date) => (date ? formatDateShort(date) : "N/A");
const formatTime = (time) => (time ? formatTimeUtil(time) : "N/A");

const confirmApprove = () => doApprove((req) => leaveRequestStore.approveLeaveRequest(req.id));

const confirmReject = () => doReject((req) => leaveRequestStore.rejectLeaveRequest(req.id));

onMounted(async () => {
    await Promise.all([loadStatistics(), loadLeaveRequests(), loadCorrections()]);
});
</script>

<template>
    <div class="flex-1 flex flex-col overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-brand-dark">Attendance Overview</h1>
                <p class="text-sm text-brand-light mt-1">Monitor real-time presence and manage requests.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <RouterLink
                :to="{ name: 'admin.attendance.records' }"
                v-if="can('attendance-list')"
                class="bg-white border border-brand-border rounded-2xl p-4 flex items-center gap-4 hover:border-brand-primary hover:shadow-md transition-all duration-300 group"
            >
                <div
                    class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition-colors"
                >
                    <CalendarDays class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-base font-semibold group-hover:text-brand-primary transition-colors">
                        Attendance Logs
                    </h3>
                    <p class="text-brand-light text-xs mt-0.5">View historical records</p>
                </div>
            </RouterLink>

            <RouterLink
                :to="{ name: 'admin.attendance.leave-requests' }"
                v-if="can('leave-request-list')"
                class="bg-white border border-brand-border rounded-2xl p-4 flex items-center gap-4 hover:border-brand-primary hover:shadow-md transition-all duration-300 group"
            >
                <div
                    class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center group-hover:bg-purple-100 transition-colors"
                >
                    <CalendarClock class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-base font-semibold group-hover:text-brand-primary transition-colors">
                        Leave Requests
                    </h3>
                    <p class="text-brand-light text-xs mt-0.5">Manage employee time off</p>
                </div>
            </RouterLink>

            <RouterLink
                :to="{ name: 'admin.attendance.corrections' }"
                v-if="can('attendance-correction-list')"
                class="bg-white border border-brand-border rounded-2xl p-4 flex items-center gap-4 hover:border-brand-primary hover:shadow-md transition-all duration-300 group"
            >
                <div
                    class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center group-hover:bg-orange-100 transition-colors"
                >
                    <Clock class="w-6 h-6 text-orange-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-base font-semibold group-hover:text-brand-primary transition-colors">
                        Corrections
                    </h3>
                    <p class="text-brand-light text-xs mt-0.5">Manage clock-in disputes</p>
                </div>
            </RouterLink>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="main-card rounded-2xl border border-brand-dark relative overflow-hidden p-5">
                <div class="flex flex-col justify-center h-full relative z-10">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center gap-1 px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                            <TrendingUp class="w-3 h-3 text-white" />
                            <span class="text-brand-white text-xs font-semibold">
                                +{{ statistics.present_change || 0 }} from yesterday
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-brand-white-90 text-sm font-medium">Present Today</p>
                            <p class="text-brand-white text-5xl font-extrabold leading-none my-4">
                                <AnimatedValue :value="statistics.present_today || 0" />
                            </p>
                            <p class="text-brand-white-80 text-base font-normal">Active employees</p>
                        </div>
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                            <CheckCircle class="w-8 h-8 text-white" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mt-auto">
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                            <span class="text-brand-white-70 text-xs font-normal">Real-time</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Star class="w-3 h-3 text-white opacity-70" />
                            <span class="text-brand-white-70 text-xs font-normal">High Attendance</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <StatsCard
                    title="Absent Today"
                    :value="statistics.absent_today || 0"
                    :subtitle="`${statistics.absent_change >= 0 ? '+' : ''}${statistics.absent_change || 0} from yesterday`"
                    subtitleColor="text-danger"
                    iconName="XCircle"
                    colorScheme="red"
                    :loading="loadingStatistics"
                />

                <StatsCard
                    title="Late Arrivals"
                    :value="statistics.late_today || 0"
                    subtitle="After 9:00 AM"
                    subtitleColor="text-warning"
                    iconName="Clock"
                    colorScheme="orange"
                    :loading="loadingStatistics"
                />
            </div>

            <div class="flex flex-col gap-4">
                <StatsCard
                    title="On Leave"
                    :value="statistics.on_leave_today || 0"
                    subtitle="Approved requests"
                    subtitleColor="text-brand-light"
                    iconName="CalendarX"
                    colorScheme="yellow"
                    :loading="loadingStatistics"
                />

                <StatsCard
                    title="Remote Workers"
                    :value="statistics.remote_today || 0"
                    subtitle="Working from home"
                    subtitleColor="text-brand-light"
                    iconName="Laptop"
                    colorScheme="purple"
                    :loading="loadingStatistics"
                />
            </div>

            <div class="flex flex-col gap-4">
                <StatsCard
                    title="Attendance Rate"
                    :value="`${statistics.attendance_rate || 0}%`"
                    :subtitle="`${statistics.rate_change >= 0 ? '+' : ''}${statistics.rate_change || 0}% from last week`"
                    :subtitleColor="statistics.rate_change >= 0 ? 'text-success' : 'text-danger'"
                    iconName="TrendingUp"
                    colorScheme="blue"
                    :loading="loadingStatistics"
                />

                <StatsCard
                    title="Pending Requests"
                    :value="statistics.pending_requests || 0"
                    subtitle="Awaiting approval"
                    subtitleColor="text-warning"
                    iconName="Clock4"
                    colorScheme="orange"
                    :loading="loadingStatistics"
                />
            </div>
        </div>

        <div
            :class="[
                'grid grid-cols-1 gap-6',
                can('leave-request-list') && can('attendance-correction-list') ? 'lg:grid-cols-2' : '',
            ]"
        >
            <div v-if="can('leave-request-list')" class="bg-white border border-brand-border rounded-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                            <CalendarClock class="w-6 h-6 text-orange-600" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-brand-dark">Latest Leave Requests</h2>
                            <p class="text-brand-light text-sm">Recent 5 leave applications</p>
                        </div>
                    </div>
                </div>

                <div v-if="loadingLeaveRequests" class="text-center py-12">
                    <p class="text-gray-500 text-lg font-medium">Loading...</p>
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="request in leaveRequests"
                        :key="request.id"
                        class="flex items-center gap-4 p-4 border border-brand-border rounded-xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300"
                    >
                        <img loading="lazy"
                            :src="request.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                            :alt="request.staff_member?.user?.name"
                            class="w-12 h-12 rounded-full object-cover"
                        />
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-sm font-semibold text-brand-dark">
                                    {{ request.staff_member?.user?.name }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <StatusBadge type="leave-type" :value="request.type" />
                                <span class="text-brand-dark text-xs">
                                    {{ formatDate(request.start_date) }} - {{ formatDate(request.end_date) }} ({{
                                        request.days
                                    }}
                                    days)
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="request.status === 'pending' && can('leave-request-approve')"
                            class="flex flex-col gap-2"
                        >
                            <button
                                @click="showApproveModal(request)"
                                class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2"
                            >
                                <Check class="w-4 h-4 text-green-600" />
                                <span class="text-brand-dark text-xs font-semibold">Approve</span>
                            </button>
                            <button
                                @click="showRejectModal(request)"
                                class="btn-secondary flex items-center justify-center gap-2 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2"
                            >
                                <X class="w-4 h-4 text-red-600" />
                                <span class="text-brand-dark text-xs font-semibold">Reject</span>
                            </button>
                        </div>
                        <div v-else>
                            <StatusBadge type="leave-status" :value="request.status" />
                        </div>
                    </div>

                    <EmptyState v-if="!loadingLeaveRequests && leaveRequests.length === 0" icon="CalendarClock" title="No leave requests found" />
                </div>
            </div>

            <div v-if="can('attendance-correction-list')" class="bg-white border border-brand-border rounded-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <Clock class="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-brand-dark">Pending Corrections</h2>
                            <p class="text-brand-light text-sm">Attendance sync requests</p>
                        </div>
                    </div>
                </div>

                <div v-if="loadingCorrections" class="text-center py-12">
                    <p class="text-gray-500 text-lg font-medium">Loading...</p>
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="correction in pendingCorrections"
                        :key="correction.id"
                        class="flex items-center gap-4 p-4 border border-brand-border rounded-xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300"
                    >
                        <img loading="lazy"
                            :src="correction.staff_member?.user?.profile_photo || DEFAULT_AVATAR"
                            :alt="correction.staff_member?.user?.name"
                            class="w-12 h-12 rounded-full object-cover"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-sm font-semibold text-brand-dark truncate">
                                    {{ correction.staff_member?.user?.name }}
                                </p>
                            </div>
                            <p class="text-brand-light text-xs truncate max-w-[200px]">
                                {{ correction.reason }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-right border-l pl-3 border-gray-100">
                                <div class="flex items-center justify-end gap-1 text-brand-dark text-xs font-semibold">
                                    <span class="text-gray-400 font-normal">In:</span>
                                    {{ formatTime(correction.requested_check_in) }}
                                </div>
                                <div
                                    class="flex items-center justify-end gap-1 text-brand-dark text-xs font-semibold mt-0.5"
                                >
                                    <span class="text-gray-400 font-normal">Out:</span>
                                    {{ formatTime(correction.requested_check_out) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <EmptyState v-if="!loadingCorrections && pendingCorrections.length === 0" icon="Clock" title="No pending corrections" />
                </div>
            </div>
        </div>

        <ModalWrapper
            :show="showApproveModalState"
            title="Approve Leave Request"
            maxWidth="md"
            @close="closeApproveModal"
        >
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                    <Check class="w-6 h-6 text-green-600" />
                </div>
                <div>
                    <p class="text-brand-light text-sm">Confirm approval for this leave request</p>
                </div>
            </div>

            <div v-if="selectedApproveRequest" class="mb-6 space-y-3">
                <div class="border border-brand-border rounded-xl p-4">
                    <p class="text-brand-dark text-sm font-semibold mb-2">
                        {{ selectedApproveRequest.staff_member?.user?.name }}
                    </p>
                    <div class="flex items-center gap-2 mb-2">
                        <StatusBadge type="leave-type" :value="selectedApproveRequest.type" />
                    </div>
                    <p class="text-brand-dark text-sm">
                        {{ formatDate(selectedApproveRequest.start_date) }} -
                        {{ formatDate(selectedApproveRequest.end_date) }} ({{ selectedApproveRequest.days }}
                        days)
                    </p>
                    <p class="text-brand-light text-sm mt-2">
                        {{ selectedApproveRequest.reason }}
                    </p>
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

        <ModalWrapper :show="showRejectModalState" title="Reject Leave Request" maxWidth="md" @close="closeRejectModal">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                    <X class="w-6 h-6 text-red-600" />
                </div>
                <div>
                    <p class="text-brand-light text-sm">Confirm rejection for this leave request</p>
                </div>
            </div>

            <div v-if="selectedRejectRequest" class="mb-6 space-y-3">
                <div class="border border-brand-border rounded-xl p-4">
                    <p class="text-brand-dark text-sm font-semibold mb-2">
                        {{ selectedRejectRequest.staff_member?.user?.name }}
                    </p>
                    <div class="flex items-center gap-2 mb-2">
                        <StatusBadge type="leave-type" :value="selectedRejectRequest.type" />
                    </div>
                    <p class="text-brand-dark text-sm">
                        {{ formatDate(selectedRejectRequest.start_date) }} -
                        {{ formatDate(selectedRejectRequest.end_date) }} ({{ selectedRejectRequest.days }}
                        days)
                    </p>
                    <p class="text-brand-light text-sm mt-2">
                        {{ selectedRejectRequest.reason }}
                    </p>
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
                        :disabled="processingReject"
                        class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ processingReject ? "Rejecting..." : "Reject" }}
                    </button>
                </div>
            </template>
        </ModalWrapper>
    </div>
</template>
