<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  CalendarCheck,
  CalendarDays,
  Clock,
  Plus,
  User,
  CalendarPlus,
  Eye,
  CalendarX,
  PenSquare,
  List,
  Grid3x3,
  ChevronLeft,
  ChevronRight,
} from "lucide-vue-next";
import { DateTime } from "luxon";
import { useAttendanceCorrectionStore } from "@/stores/attendanceCorrection";
import AttendanceCorrectionsList from "@/components/staff-member/attendance/AttendanceCorrectionsList.vue";
import AttendanceCorrectionModal from "@/components/staff-member/attendance/AttendanceCorrectionModal.vue";
import { useAttendanceStore } from "@/stores/attendance";
import { useLeaveRequestStore } from "@/stores/leaveRequest";
import { useOptionStore } from "@/stores/option";
import { storeToRefs } from "pinia";
import { useToast } from "@/composables/useToast";
import { can } from "@/helpers/permissionHelper";
import { useAuthStore } from "@/stores/auth";

import {
  formatDateShort,
  formatDateLong,
  formatRequestDate,
  formatRequestDateLong,
  getDayName,
  formatTime,
  calculateWorkingHours,
  calculateWorkingDays,
} from "@/utils/dateUtils";
import { getStatusConfig, formatLeaveType } from "@/utils/attendanceUtils";
import AttendanceStatsCards from "@/components/staff-member/attendance/AttendanceStatsCards.vue";
import LeaveRequestSuccessModal from "@/components/staff-member/attendance/LeaveRequestSuccessModal.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const toast = useToast();
const route = useRoute();
const router = useRouter();
const attendanceStore = useAttendanceStore();
const {
  loading: attendanceLoading,
  attendances,
  statistics,
  todayAttendance,
} = storeToRefs(attendanceStore);
const { fetchAttendances, fetchStatistics, checkIn, checkOut, fetchTodayAttendance } = attendanceStore;

const leaveRequestStore = useLeaveRequestStore();
const { loading: leaveLoading, myLeaveRequests, myLeaveBalances } =
  storeToRefs(leaveRequestStore);
const { fetchMyLeaveRequests, fetchMyLeaveBalances, createLeaveRequest } = leaveRequestStore;

const attendanceCorrectionStore = useAttendanceCorrectionStore();
const { loading: correctionLoading, myCorrections } = storeToRefs(attendanceCorrectionStore);
const { fetchMyCorrections, requestCorrection } = attendanceCorrectionStore;

const optionStore = useOptionStore();
const { leaveTypes } = storeToRefs(optionStore);
const { fetchLeaveTypes } = optionStore;

const showLeaveRequestModal = ref(false);
const showLeaveDetailsModal = ref(false);
const showSuccessModal = ref(false);
const showCorrectionModal = ref(false);
const selectedAttendanceForCorrection = ref(null);
const selectedLeaveRequest = ref(null);
const submittedLeaveData = ref(null);
const activeSection = ref("overview");
const attendanceViewMode = ref("list");
const calendarMonth = ref(DateTime.now().startOf("month"));
const leaveForm = ref({
  leave_type: "",
  start_date: "",
  end_date: "",
  reason: "",
  emergency_contact: "",
  proof_file: null,
});

// Computed
const recentAttendances = computed(() => {
  return attendances.value.slice(0, 7);
});

const attendanceLegend = [
  { key: "present", label: "Present", class: "bg-green-500" },
  { key: "absent", label: "Absent", class: "bg-red-500" },
  { key: "late", label: "Late", class: "bg-yellow-400" },
  { key: "leave", label: "Leave", class: "bg-blue-500" },
];

const calendarMonthLabel = computed(() =>
  calendarMonth.value.toFormat("LLLL yyyy"),
);

const monthAttendancesByDate = computed(() => {
  const monthKey = calendarMonth.value.toFormat("yyyy-MM");

  return attendances.value.reduce((acc, record) => {
    if (!record?.date) {
      return acc;
    }

    const recordDate = DateTime.fromISO(record.date);
    if (!recordDate.isValid || recordDate.toFormat("yyyy-MM") !== monthKey) {
      return acc;
    }

    const normalizedStatus = record.status === "sick" ? "leave" : record.status;
    acc[record.date] = normalizedStatus;
    return acc;
  }, {});
});

const attendanceCalendarDays = computed(() => {
  const monthStart = calendarMonth.value.startOf("month");
  const monthEnd = calendarMonth.value.endOf("month");
  const leadingDays = monthStart.weekday - 1;
  const trailingDays = 7 - monthEnd.weekday;
  const calendarStart = monthStart.minus({ days: leadingDays });
  const totalDays = leadingDays + monthEnd.day + trailingDays;

  return Array.from({ length: totalDays }, (_, index) => {
    const date = calendarStart.plus({ days: index });
    const isoDate = date.toISODate();
    const status = monthAttendancesByDate.value[isoDate] || null;

    return {
      isoDate,
      day: date.day,
      inCurrentMonth: date.month === calendarMonth.value.month,
      isToday: date.hasSame(DateTime.now(), "day"),
      status,
    };
  });
});

const getAttendanceStatusDotClass = (status) => {
  switch (status) {
    case "present":
      return "bg-green-500";
    case "absent":
      return "bg-red-500";
    case "late":
      return "bg-yellow-400";
    case "leave":
      return "bg-blue-500";
    default:
      return "bg-gray-300";
  }
};

const goToPreviousMonth = () => {
  calendarMonth.value = calendarMonth.value.minus({ months: 1 }).startOf("month");
};

const goToNextMonth = () => {
  calendarMonth.value = calendarMonth.value.plus({ months: 1 }).startOf("month");
};

const totalDaysCalculated = computed(() => {
  if (!leaveForm.value.start_date || !leaveForm.value.end_date) {
    return 0;
  }
  return calculateWorkingDays(
    leaveForm.value.start_date,
    leaveForm.value.end_date,
  );
});

const pendingRequestsCount = computed(() => {
  return myLeaveRequests.value.filter((r) => r.status === "pending").length;
});

const authStore = useAuthStore();
const workLocation = computed(
  () => authStore.user?.employee_profile?.job_information?.work_location || 'office'
);
const isRemote = computed(() => workLocation.value === 'remote');

const canUseClockActions = computed(
  () => !isRemote.value && (can("attendance-check-in") || can("attendance-check-out")),
);

const canViewMyAttendanceData = computed(() =>
  can("attendance-my-attendances"),
);

const canViewMyLeaveRequests = computed(() => can("leave-request-my-requests"));

const canCreateLeaveRequest = computed(() => can("leave-request-create"));
const canCreateCorrection = computed(() => can("attendance-correction-create"));

const sections = computed(() =>
  [
    {
      id: "overview",
      label: "Overview",
      icon: CalendarDays,
      isVisible: true,
    },
    {
      id: "corrections",
      label: "Corrections",
      icon: PenSquare,
      isVisible: canCreateCorrection.value,
    },
  ].filter((section) => section.isVisible),
);

const openLeaveRequestModal = () => {
  if (!canCreateLeaveRequest.value) {
    return;
  }

  showLeaveRequestModal.value = true;
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  leaveForm.value.start_date = tomorrow.toISOString().split("T")[0];
  leaveForm.value.end_date = tomorrow.toISOString().split("T")[0];
};

const closeLeaveRequestModal = () => {
  showLeaveRequestModal.value = false;
  leaveForm.value = {
    leave_type: "",
    start_date: "",
    end_date: "",
    reason: "",
    emergency_contact: "",
    proof_file: null,
  };
};

const handleProofFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      toast.warning("File Too Large", "Proof file must be less than 5MB.");
      event.target.value = "";
      return;
    }
    leaveForm.value.proof_file = file;
  }
};

const submitLeaveRequest = async () => {
  if (!canCreateLeaveRequest.value) {
    toast.warning(
      "Unauthorized",
      "You do not have permission to request leave.",
    );
    return;
  }

  const startDate = new Date(leaveForm.value.start_date);
  const endDate = new Date(leaveForm.value.end_date);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (startDate <= today) {
    toast.warning("Invalid Date", "Start date must be at least tomorrow.");
    return;
  }

  if (endDate < startDate) {
    toast.warning("Invalid Date", "End date must be after start date.");
    return;
  }

  const totalDays = totalDaysCalculated.value;

  if (leaveForm.value.leave_type === 'sick' && !leaveForm.value.proof_file) {
    toast.warning("Missing Proof", "A medical certificate or proof is required for sick leave.");
    return;
  }

  try {
    const createdRequest = await createLeaveRequest({
      ...leaveForm.value,
      total_days: totalDays,
    });

    if (leaveForm.value.leave_type === 'sick' && leaveForm.value.proof_file) {
      try {
        await leaveRequestStore.uploadProof(createdRequest.id, leaveForm.value.proof_file);
      } catch (uploadError) {
        toast.warning(
          "Proof Upload Failed",
          "Your sick leave was submitted, but the medical certificate failed to upload. You may need to provide it to HR separately."
        );
      }
    }

    submittedLeaveData.value = {
      leave_type: leaveForm.value.leave_type,
      start_date: leaveForm.value.start_date,
      end_date: leaveForm.value.end_date,
      total_days: totalDays,
    };
    showSuccessModal.value = true;

    closeLeaveRequestModal();
    if (canViewMyLeaveRequests.value) {
      await fetchMyLeaveRequests();
    }
  } catch (error) {
    toast.error(
      "Failed to submit leave request",
      leaveRequestStore.error ||
        error?.response?.data?.message ||
        "Failed to submit leave request.",
    );
  }
};

const closeSuccessModal = () => {
  showSuccessModal.value = false;
  submittedLeaveData.value = null;
};

const openLeaveDetailsModal = (requestId) => {
  const request = myLeaveRequests.value.find((r) => r.id === requestId);
  if (!request) return;
  selectedLeaveRequest.value = request;
  showLeaveDetailsModal.value = true;
};

const closeLeaveDetailsModal = () => {
  showLeaveDetailsModal.value = false;
  selectedLeaveRequest.value = null;
};

const updateEndDateMin = () => {
  if (leaveForm.value.start_date && leaveForm.value.end_date) {
    if (
      new Date(leaveForm.value.end_date) < new Date(leaveForm.value.start_date)
    ) {
      leaveForm.value.end_date = leaveForm.value.start_date;
    }
  }
};

const openCorrectionModal = (record) => {
    selectedAttendanceForCorrection.value = record;
    showCorrectionModal.value = true;
};

const submitCorrection = async (payload) => {
    try {
        await requestCorrection(payload);
        showCorrectionModal.value = false;
        toast.success("Success", "Correction request submitted.");
    } catch (error) {
        toast.error("Error", "Failed to submit correction request.");
    }
};

const isCheckedIn = computed(() => {
  return todayAttendance.value?.check_in && !todayAttendance.value?.check_out;
});

const isClockOutDisabled = computed(() => {
  if (!isCheckedIn.value || !todayAttendance.value?.check_in) return true;
  if (attendanceLoading.value) return true;
  const checkInDate = new Date(todayAttendance.value.check_in);
  const diff = Date.now() - checkInDate.getTime();
  return diff < 8 * 60 * 60 * 1000;
});

const handleCheckIn = async () => {
  if (attendanceLoading.value) return;
  try {
    await checkIn({ check_in_lat: null, check_in_long: null });
    toast.success("Clocked In", "You have successfully clocked in!");
    await fetchTodayAttendance();
    await fetchAttendances();
  } catch (error) {
    toast.error("Check-in Failed", error?.response?.data?.message || "Failed to check in. Please try again.");
  }
};

const handleCheckOut = async () => {
  if (attendanceLoading.value) return;
  try {
    await checkOut({ check_out_lat: null, check_out_long: null });
    toast.success("Clocked Out", "You have successfully clocked out!");
    await fetchTodayAttendance();
    await fetchAttendances();
  } catch (error) {
    toast.error("Check-out Failed", error?.response?.data?.message || "Failed to check out. Please try again.");
  }
};

const clearLeaveRequestActionQuery = async () => {
  const { action, ...query } = route.query;

  await router.replace({
    name: route.name,
    params: route.params,
    query,
    hash: route.hash,
  });
};

const handleRouteActionQuery = async () => {
  if (
    !["request-leave", "leave"].includes(route.query.action) ||
    !canCreateLeaveRequest.value
  ) {
    return;
  }

  openLeaveRequestModal();
  await clearLeaveRequestActionQuery();
};

const setActiveSection = (sectionId) => {
  activeSection.value = sectionId;
};

onMounted(async () => {
  const requests = [fetchLeaveTypes()];

  if (canViewMyAttendanceData.value) {
    requests.push(fetchAttendances(), fetchStatistics());
  }

  if (canUseClockActions.value) {
    requests.push(fetchTodayAttendance());
  }

  if (canViewMyLeaveRequests.value) {
    requests.push(fetchMyLeaveRequests(), fetchMyLeaveBalances());
  }

  if (canCreateCorrection.value) {
    requests.push(fetchMyCorrections());
  }

  await Promise.all(requests);

  await handleRouteActionQuery();
});
</script>

<template>
  <div class="p-5">
    <div
      class="relative rounded-[20px] mb-6 overflow-hidden h-[200px]"
      style="
        background-image: url(&quot;https://images.unsplash.com/photo-1497366216548-37526070297c&quot;);
        background-size: cover;
        background-position: center;
      "
    >
      <div class="absolute inset-0 bg-black/40"></div>

      <div class="relative z-10 p-6 h-full flex flex-col justify-center">
        <div class="flex items-center gap-4">
          <div
            class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-[16px] flex items-center justify-center"
          >
            <CalendarCheck class="w-8 h-8 text-white" />
          </div>
          <div>
            <h3 class="text-white text-2xl font-bold">Attendance Overview</h3>
            <p class="text-white/90 text-base font-normal">
              Track your daily presence and manage leave requests efficiently
            </p>
          </div>
        </div>
      </div>

      <div class="absolute bottom-4 right-6 flex items-center gap-[10px] z-10">
        <div
          v-if="isRemote"
          class="bg-white/90 backdrop-blur-sm text-brand-dark rounded-[8px] border border-green-300 px-4 py-3 flex items-center gap-2 shadow-lg"
        >
          <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
          <span class="text-green-700 text-sm font-semibold">Auto-present · Remote</span>
        </div>

        <button
          v-if="canUseClockActions && !isCheckedIn"
          @click="handleCheckIn"
          :disabled="attendanceLoading"
          class="bg-white text-brand-dark rounded-[8px] border border-[#DCDEDD] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 px-4 py-3 flex items-center gap-2 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Clock class="w-4 h-4 text-[#0C51D9]" />
          <span class="text-brand-dark text-sm font-semibold">Clock In</span>
        </button>
        <button
          v-else-if="canUseClockActions && isCheckedIn"
          @click="handleCheckOut"
          :disabled="isClockOutDisabled"
          class="bg-white text-brand-dark rounded-[8px] border border-[#EE2A3B] hover:border-[#EE2A3B] hover:border-2 transition-all duration-300 px-4 py-3 flex items-center gap-2 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Clock class="w-4 h-4 text-[#EE2A3B]" />
          <span class="text-brand-dark text-sm font-semibold">Clock Out</span>
        </button>

        <button
          v-if="canCreateLeaveRequest"
          @click="openLeaveRequestModal"
          class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2 shadow-lg"
        >
          <Plus class="w-4 h-4 text-white" />
          <span class="text-white text-sm font-semibold">Request Leave</span>
        </button>
      </div>
    </div>

    <AttendanceStatsCards
      :statistics="statistics"
      :pending-requests-count="pendingRequestsCount"
    />

    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-3 mb-6 dark:bg-gray-800 dark:border-gray-700">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
       
        <button
          v-for="section in sections"
          :key="section.id"
          type="button"
          @click="setActiveSection(section.id)"
          class="rounded-[8px] px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
          :class="
            activeSection === section.id
              ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
              : 'border-[#DCDEDD] text-brand-dark hover:border-[#0C51D9] hover:border-2 bg-white'
          "
        >
          <component
            :is="section.icon"
            class="w-4 h-4"
            :class="
              activeSection === section.id ? 'text-white' : 'text-gray-600'
            "
          />
          <span class="text-sm font-semibold">{{ section.label }}</span>
        </button>
      </div>
    </div>

    <div
      v-if="activeSection === 'overview'"
      class="grid grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <div
        v-if="canViewMyLeaveRequests"
        class="lg:col-span-2 bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-6 mb-2 dark:bg-gray-800 dark:border-gray-700"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
            >
              <CalendarCheck class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                Leave Entitlements
              </h3>
              <p class="text-brand-light text-sm">Your remaining leave quotas</p>
            </div>
          </div>
        </div>

        <div v-if="leaveLoading" class="text-center py-4">
          <p class="text-brand-light">Loading...</p>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div
            v-for="balance in myLeaveBalances"
            :key="balance.leave_type"
            class="border border-[#DCDEDD] rounded-[16px] p-4 bg-gray-50 flex flex-col"
          >
             <h4 class="text-brand-dark text-base font-semibold mb-1">
               {{ formatLeaveType(balance.leave_type) }}
             </h4>
             <span class="text-xs text-brand-light mb-3 block capitalize">{{ balance.quota_scope }} Quota</span>
             
             <div class="mt-auto">
                 <div v-if="balance.quota_days !== null">
                     <div class="flex justify-between items-end mb-1">
                         <span class="text-3xl font-bold text-brand-dark">{{ balance.remaining_days }}</span>
                         <span class="text-sm font-medium text-brand-light pb-1">/ {{ balance.quota_days }} left</span>
                     </div>
                     <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                         <div class="bg-blue-600 h-1.5 rounded-full" :style="{ width: `${(balance.remaining_days / balance.quota_days) * 100}%` }"></div>
                     </div>
                 </div>
                 <div v-else>
                     <span class="text-xl font-bold text-brand-dark">Unlimited</span>
                     <p class="text-sm text-brand-light mt-1">{{ balance.used_days }} day(s) used</p>
                 </div>
             </div>
          </div>
          <EmptyState
            v-if="!leaveLoading && myLeaveBalances.length === 0"
            icon="CalendarX"
            title="No entitlements found"
            class="col-span-full"
          />
        </div>
      </div>

      <div
        v-if="canViewMyLeaveRequests"
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-6"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
            >
              <CalendarCheck class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                Recent Attendance
              </h3>
              <p class="text-brand-light text-sm">Last 7 days</p>
            </div>
          </div>
          <div class="border border-[#DCDEDD] rounded-[10px] p-1 flex items-center gap-1">
            <button
              type="button"
              @click="attendanceViewMode = 'list'"
              class="px-3 py-2 rounded-[8px] text-xs font-semibold flex items-center gap-1.5 transition-all duration-200"
              :class="attendanceViewMode === 'list' ? 'bg-blue-600 text-white' : 'text-brand-dark hover:bg-gray-100'"
            >
              <List class="w-3.5 h-3.5" />
              List
            </button>
            <button
              type="button"
              @click="attendanceViewMode = 'calendar'"
              class="px-3 py-2 rounded-[8px] text-xs font-semibold flex items-center gap-1.5 transition-all duration-200"
              :class="attendanceViewMode === 'calendar' ? 'bg-blue-600 text-white' : 'text-brand-dark hover:bg-gray-100'"
            >
              <Grid3x3 class="w-3.5 h-3.5" />
              Calendar
            </button>
          </div>
        </div>

        <div v-if="attendanceLoading" class="text-center py-8">
          <p class="text-brand-light">Loading...</p>
        </div>

        <div v-else-if="attendanceViewMode === 'list'" class="space-y-3">
          <div
            v-for="record in recentAttendances"
            :key="record.id"
            class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px] flex items-center justify-center"
                >
                  <User class="w-5 h-5 text-white" />
                </div>
                <div>
                  <h4 class="text-brand-dark text-sm font-semibold">
                    {{ getDayName(record.date) }}
                  </h4>
                  <p class="text-brand-light text-sm">
                    {{ formatDateShort(record.date) }}
                  </p>
                </div>
              </div>
              <span
                :class="getStatusConfig(record.status).class"
                class="px-2 py-1 rounded-md text-sm font-semibold"
              >
                {{ getStatusConfig(record.status).text }}
              </span>
            </div>

            <div class="border-b border-[#DCDEDD] mb-3"></div>

            <div v-if="record.check_in" class="space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >Check-in</span
                >
                <span class="text-brand-dark text-sm font-semibold">{{
                  formatTime(record.check_in)
                }}</span>
              </div>
              <div v-if="record.check_out">
                <div class="flex items-center justify-between">
                  <span class="text-brand-dark text-sm font-medium"
                    >Check-out</span
                  >
                  <span class="text-brand-dark text-sm font-semibold">{{
                    formatTime(record.check_out)
                  }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-brand-dark text-sm font-medium"
                    >Total Hours</span
                  >
                  <span class="text-brand-dark text-sm font-semibold">{{
                    calculateWorkingHours(record.check_in, record.check_out)
                  }}</span>
                </div>
              </div>
              <div v-else class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium">Status</span>
                <span
                  class="text-green-600 text-sm font-semibold flex items-center gap-1"
                >
                  <div
                    class="w-2 h-2 bg-green-500 rounded-full animate-pulse"
                  ></div>
                  Currently Working
                </span>
              </div>
            </div>
            <div v-else class="flex items-center justify-center py-2">
              <span class="text-brand-light text-sm italic"
                >No attendance data</span
              >
            </div>
          </div>

          <EmptyState
            v-if="!attendanceLoading && recentAttendances.length === 0"
            icon="CalendarClock"
            title="No attendance records found"
          />
        </div>

        <div v-else class="space-y-4">
          <div class="flex items-center justify-between">
            <button
              type="button"
              @click="goToPreviousMonth"
              class="w-9 h-9 border border-[#DCDEDD] rounded-[10px] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
            >
              <ChevronLeft class="w-4 h-4 text-brand-dark" />
            </button>
            <p class="text-brand-dark text-base font-bold">{{ calendarMonthLabel }}</p>
            <button
              type="button"
              @click="goToNextMonth"
              class="w-9 h-9 border border-[#DCDEDD] rounded-[10px] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
            >
              <ChevronRight class="w-4 h-4 text-brand-dark" />
            </button>
          </div>

          <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-brand-light">
            <div v-for="weekday in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="weekday" class="py-1">
              {{ weekday }}
            </div>
          </div>

          <div class="grid grid-cols-7 gap-1">
            <div
              v-for="day in attendanceCalendarDays"
              :key="day.isoDate"
              class="border rounded-[10px] min-h-[62px] px-2 py-1.5 flex flex-col items-start transition-all duration-200"
              :class="[
                day.inCurrentMonth ? 'border-[#DCDEDD] bg-white' : 'border-transparent bg-gray-50',
                day.isToday ? 'ring-1 ring-blue-500' : '',
              ]"
            >
              <span class="text-xs font-semibold" :class="day.inCurrentMonth ? 'text-brand-dark' : 'text-gray-400'">
                {{ day.day }}
              </span>
              <span
                v-if="day.status"
                class="mt-1.5 w-2.5 h-2.5 rounded-full"
                :class="getAttendanceStatusDotClass(day.status)"
              ></span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <div
              v-for="legend in attendanceLegend"
              :key="legend.key"
              class="flex items-center gap-1.5 text-xs text-brand-dark"
            >
              <span class="w-2.5 h-2.5 rounded-full" :class="legend.class"></span>
              <span>{{ legend.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-6"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
            >
              <CalendarX class="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                My Leave Requests
              </h3>
              <p class="text-brand-light text-sm">Recent requests</p>
            </div>
          </div>
        </div>

        <div v-if="leaveLoading" class="text-center py-8">
          <p class="text-brand-light">Loading...</p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="request in myLeaveRequests"
            :key="request.id"
            class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px] flex items-center justify-center"
                >
                  <CalendarPlus class="w-5 h-5 text-white" />
                </div>
                <div>
                  <h4 class="text-brand-dark text-sm font-semibold">
                    {{ formatLeaveType(request.leave_type) }}
                  </h4>
                  <p class="text-brand-light text-sm">
                    {{ request.total_days }}
                    {{ request.total_days === 1 ? "day" : "days" }}
                  </p>
                </div>
              </div>
              <span
                :class="getStatusConfig(request.status).class"
                class="px-2 py-1 rounded-md text-sm font-semibold"
              >
                {{ getStatusConfig(request.status).text }}
              </span>
            </div>

            <div class="border-b border-[#DCDEDD] mb-3"></div>

            <div class="space-y-2 mb-3">
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >Start Date</span
                >
                <span class="text-brand-dark text-sm font-semibold">{{
                  formatDateShort(request.start_date)
                }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >End Date</span
                >
                <span class="text-brand-dark text-sm font-semibold">{{
                  formatDateShort(request.end_date)
                }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >Requested</span
                >
                <span class="text-brand-light text-sm">{{
                  formatRequestDate(request.created_at)
                }}</span>
              </div>
            </div>

            <button
              @click="openLeaveDetailsModal(request.id)"
              class="w-full border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
            >
              <Eye class="w-4 h-4 text-gray-600" />
              <span class="text-brand-dark text-sm font-semibold"
                >View Details</span
              >
            </button>
          </div>

          <EmptyState
            v-if="!leaveLoading && myLeaveRequests.length === 0"
            icon="CalendarX"
            title="No leave requests found"
          />
        </div>
      </div>
    </div>

    <div v-else-if="activeSection === 'corrections'">
      <AttendanceCorrectionsList :corrections="myCorrections" />
    </div>

    <div v-else class="space-y-6">
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div
            class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
          >
            <CalendarPlus class="w-6 h-6 text-orange-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">
              Manage Leave Requests
            </h3>
            <p class="text-brand-light text-sm">
              Submit new leave requests and review the status of existing ones.
            </p>
          </div>
        </div>
        <button
          v-if="canCreateLeaveRequest"
          @click="openLeaveRequestModal"
          class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
        >
          <Plus class="w-4 h-4 text-white" />
          <span class="text-white text-sm font-semibold">Request Leave</span>
        </button>
      </div>

      <div
        v-if="canViewMyLeaveRequests"
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-6"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
            >
              <CalendarX class="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                My Leave Requests
              </h3>
              <p class="text-brand-light text-sm">Recent requests</p>
            </div>
          </div>
        </div>

        <div v-if="leaveLoading" class="text-center py-8">
          <p class="text-brand-light">Loading...</p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="request in myLeaveRequests"
            :key="request.id"
            class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px] flex items-center justify-center"
                >
                  <CalendarPlus class="w-5 h-5 text-white" />
                </div>
                <div>
                  <h4 class="text-brand-dark text-sm font-semibold">
                    {{ formatLeaveType(request.leave_type) }}
                  </h4>
                  <p class="text-brand-light text-sm">
                    {{ request.total_days }}
                    {{ request.total_days === 1 ? "day" : "days" }}
                  </p>
                </div>
              </div>
              <span
                :class="getStatusConfig(request.status).class"
                class="px-2 py-1 rounded-md text-sm font-semibold"
              >
                {{ getStatusConfig(request.status).text }}
              </span>
            </div>

            <div class="border-b border-[#DCDEDD] mb-3"></div>

            <div class="space-y-2 mb-3">
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >Start Date</span
                >
                <span class="text-brand-dark text-sm font-semibold">{{
                  formatDateShort(request.start_date)
                }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >End Date</span
                >
                <span class="text-brand-dark text-sm font-semibold">{{
                  formatDateShort(request.end_date)
                }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-brand-dark text-sm font-medium"
                  >Requested</span
                >
                <span class="text-brand-light text-sm">{{
                  formatRequestDate(request.created_at)
                }}</span>
              </div>
            </div>

            <button
              @click="openLeaveDetailsModal(request.id)"
              class="w-full border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
            >
              <Eye class="w-4 h-4 text-gray-600" />
              <span class="text-brand-dark text-sm font-semibold"
                >View Details</span
              >
            </button>
          </div>

          <EmptyState
            v-if="!leaveLoading && myLeaveRequests.length === 0"
            icon="CalendarX"
            title="No leave requests found"
          />
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="showLeaveRequestModal"
        class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center"
        @click.self="closeLeaveRequestModal"
      >
        <div
          class="bg-white rounded-[20px] border border-[#DCDEDD] w-full max-w-3xl mx-4 max-h-[90vh] overflow-hidden"
        >
          <div class="p-6 border-b border-[#DCDEDD]">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
                >
                  <CalendarPlus class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <h3 class="text-brand-dark text-xl font-bold">
                    Request New Leave
                  </h3>
                  <p class="text-brand-light text-sm font-normal">
                    Submit a leave request for approval
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="closeLeaveRequestModal"
                class="w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
              >
                <span class="text-gray-600 text-xl">×</span>
              </button>
            </div>
          </div>

          <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <form @submit.prevent="submitLeaveRequest" class="space-y-6">
              <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div class="md:col-span-2">
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >Leave Type *</label
                    >
                    <select
                      v-model="leaveForm.leave_type"
                      required
                      class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 font-semibold"
                    >
                      <option value="">Select leave type</option>
                      <option
                        v-for="type in leaveTypes"
                        :key="type.value"
                        :value="type.value"
                      >
                        {{ type.label }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >Start Date *</label
                    >
                    <input
                      type="date"
                      v-model="leaveForm.start_date"
                      @change="updateEndDateMin"
                      required
                      data-testid="leave-start-date"
                      class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 font-semibold"
                    />
                  </div>

                  <div>
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >End Date *</label
                    >
                    <input
                      type="date"
                      v-model="leaveForm.end_date"
                      :min="leaveForm.start_date"
                      required
                      data-testid="leave-end-date"
                      class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 font-semibold"
                    />
                  </div>

                  <div class="md:col-span-2">
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >Total Days</label
                    >
                    <div
                      class="p-4 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <div class="flex items-center gap-3">
                        <div
                          class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center"
                        >
                          <Clock class="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                          <p class="text-brand-dark text-lg font-bold">
                            {{ totalDaysCalculated }}
                            {{ totalDaysCalculated === 1 ? "day" : "days" }}
                          </p>
                          <p class="text-brand-light text-sm">
                            Excluding weekends
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
                <div class="space-y-4">
                  <div>
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >Reason for Leave *</label
                    >
                    <textarea
                      v-model="leaveForm.reason"
                      required
                      rows="4"
                      data-testid="leave-reason"
                      class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 font-semibold resize-none"
                      placeholder="Please provide a detailed reason for your leave request..."
                    ></textarea>
                  </div>

                  <div>
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >Emergency Contact (Optional)</label
                    >
                    <input
                      type="tel"
                      v-model="leaveForm.emergency_contact"
                      class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 font-semibold"
                      placeholder="Phone number for emergency contact"
                    />
                  </div>

                  <div v-if="leaveForm.leave_type === 'sick'">
                    <label
                      class="block text-sm font-medium text-gray-700 mb-1.5"
                      >Medical Certificate / Proof *</label
                    >
                    <input
                      type="file"
                      @change="handleProofFileChange"
                      accept=".pdf,.jpg,.jpeg,.png"
                      required
                      class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 text-sm font-medium text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    <p class="text-xs text-brand-light mt-1.5">Max size: 5MB. Formats: PDF, JPG, PNG.</p>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-4">
                <button
                  type="button"
                  @click="closeLeaveRequestModal"
                  class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center gap-2"
                >
                  <span class="text-brand-dark text-base font-semibold"
                    >Cancel</span
                  >
                </button>
                <button
                  type="submit"
                  class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
                >
                  <span class="text-brand-white text-base font-semibold"
                    >Submit Request</span
                  >
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="showLeaveDetailsModal && selectedLeaveRequest"
        class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center"
        @click.self="closeLeaveDetailsModal"
      >
        <div
          class="bg-white rounded-[20px] border border-[#DCDEDD] w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden"
        >
          <div class="p-6 border-b border-[#DCDEDD]">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
                >
                  <CalendarCheck class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <h3 class="text-brand-dark text-xl font-bold">
                    Leave Request Details
                  </h3>
                  <p class="text-brand-light text-sm font-normal">
                    Complete information about this leave request
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="closeLeaveDetailsModal"
                class="w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
              >
                <span class="text-gray-600 text-xl">×</span>
              </button>
            </div>
          </div>

          <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <div class="space-y-6">
              <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div>
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >Leave Type</label
                    >
                    <div
                      class="p-3 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <span class="text-brand-dark text-base font-medium">{{
                        formatLeaveType(selectedLeaveRequest.leave_type)
                      }}</span>
                    </div>
                  </div>

                  <div>
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >Status</label
                    >
                    <div
                      class="p-3 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <span
                        :class="
                          getStatusConfig(selectedLeaveRequest.status).class
                        "
                        class="text-base font-semibold"
                      >
                        {{ getStatusConfig(selectedLeaveRequest.status).text }}
                      </span>
                    </div>
                  </div>

                  <div>
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >Start Date</label
                    >
                    <div
                      class="p-3 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <span class="text-brand-dark text-base font-medium">{{
                        formatDateLong(selectedLeaveRequest.start_date)
                      }}</span>
                    </div>
                  </div>

                  <div>
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >End Date</label
                    >
                    <div
                      class="p-3 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <span class="text-brand-dark text-base font-medium">{{
                        formatDateLong(selectedLeaveRequest.end_date)
                      }}</span>
                    </div>
                  </div>

                  <div class="md:col-span-2">
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >Total Duration</label
                    >
                    <div
                      class="p-4 bg-blue-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <div class="flex items-center gap-3">
                        <div
                          class="w-10 h-10 bg-blue-100 rounded-[12px] flex items-center justify-center"
                        >
                          <Clock class="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                          <p class="text-brand-dark text-lg font-bold">
                            {{ selectedLeaveRequest.total_days }}
                            {{
                              selectedLeaveRequest.total_days === 1
                                ? "day"
                                : "days"
                            }}
                          </p>
                          <p class="text-brand-light text-sm">
                            Working days (excluding weekends)
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
                <div class="space-y-4">
                  <div>
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >Reason for Leave</label
                    >
                    <div
                      class="p-4 bg-gray-50 rounded-[12px] border border-[#DCDEDD] min-h-[100px]"
                    >
                      <p class="text-brand-dark text-base leading-relaxed">
                        {{ selectedLeaveRequest.reason }}
                      </p>
                    </div>
                  </div>

                  <div>
                    <label
                      class="block text-brand-dark text-base font-semibold mb-2"
                      >Request Submitted</label
                    >
                    <div
                      class="p-3 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                    >
                      <span class="text-brand-dark text-base font-medium">{{
                        formatRequestDateLong(selectedLeaveRequest.created_at)
                      }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-end gap-4">
                <button
                  type="button"
                  @click="closeLeaveDetailsModal"
                  class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center gap-2"
                >
                  <span class="text-brand-dark text-base font-semibold"
                    >Close</span
                  >
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <LeaveRequestSuccessModal
      :show="showSuccessModal"
      :leave-data="submittedLeaveData"
      @close="closeSuccessModal"
    />
  </div>
</template>
