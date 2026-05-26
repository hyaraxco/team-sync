<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    CalendarCheck,
    CalendarDays,
    Clock,
    Plus,
    User,
    CalendarPlus,
    CalendarX,
    PenSquare,
    List,
    Grid3x3,
    ChevronLeft,
    ChevronRight,
    FileText,
    Timer,
    MapPin,
} from "lucide-vue-next";
import { DateTime } from "luxon";
import { useAttendanceCorrectionStore } from "@/stores/attendanceCorrection";
import AttendanceCorrectionsList from "@/components/staff-member/attendance/AttendanceCorrectionsList.vue";
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
import MyOvertime from "@/views/staff-member/MyOvertime.vue";
import HybridSchedules from "@/views/staff-member/HybridSchedules.vue";

const toast = useToast();
const route = useRoute();
const router = useRouter();
const attendanceStore = useAttendanceStore();
const { loading: attendanceLoading, attendances, statistics, todayAttendance } = storeToRefs(attendanceStore);
const { fetchAttendances, fetchStatistics, checkIn, checkOut, fetchTodayAttendance } = attendanceStore;

const leaveRequestStore = useLeaveRequestStore();
const { loading: leaveLoading, myLeaveRequests, myLeaveBalances } = storeToRefs(leaveRequestStore);
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
const currentTime = ref(Date.now());
let clockInterval = null;
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

const calendarMonthLabel = computed(() => calendarMonth.value.toFormat("LLLL yyyy"));

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

const getAttendanceStatusLabel = (status) => {
    switch (status) {
        case "present":
            return "Present";
        case "absent":
            return "Absent";
        case "late":
            return "Late";
        case "leave":
            return "Leave";
        case "half_day":
            return "Half Day";
        default:
            return status;
    }
};

const getAttendanceStatusLabelClass = (status) => {
    switch (status) {
        case "present":
            return "bg-green-100 text-green-700";
        case "absent":
            return "bg-red-100 text-red-700";
        case "late":
            return "bg-yellow-100 text-yellow-700";
        case "leave":
            return "bg-blue-100 text-blue-700";
        case "half_day":
            return "bg-orange-100 text-orange-700";
        default:
            return "bg-gray-100 text-gray-600";
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
    return calculateWorkingDays(leaveForm.value.start_date, leaveForm.value.end_date);
});

const pendingRequestsCount = computed(() => {
    return myLeaveRequests.value.filter((r) => r.status === "pending").length;
});

const authStore = useAuthStore();
const workLocation = computed(() => authStore.user?.employee_profile?.job_information?.work_location || "office");
const isRemote = computed(() => workLocation.value === "remote");
const isHybrid = computed(() => workLocation.value === "hybrid");
const actualWorkMode = ref("office");

const canUseClockActions = computed(
    () => !isRemote.value && (can("attendance-check-in") || can("attendance-check-out")),
);

const canViewMyAttendanceData = computed(() => can("attendance-my-attendances"));

const canViewMyLeaveRequests = computed(() => can("leave-request-my-requests"));

const canCreateLeaveRequest = computed(() => can("leave-request-create"));
const canCreateCorrection = computed(() => can("attendance-correction-create"));

const sections = computed(() =>
    [
        {
            id: "overview",
            label: "Overview",
            icon: CalendarDays,
            isVisible: canViewMyAttendanceData.value,
        },
        {
            id: "corrections",
            label: "Corrections",
            icon: PenSquare,
            isVisible: canCreateCorrection.value,
        },
        {
            id: "overtime",
            label: "Overtime",
            icon: Timer,
            isVisible: true,
        },
        {
            id: "hybrid",
            label: "Hybrid Schedule",
            icon: MapPin,
            isVisible: isHybrid.value,
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
        toast.warning("Unauthorized", "You do not have permission to request leave.");
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

    if (leaveForm.value.leave_type === "sick_leave" && !leaveForm.value.proof_file) {
        toast.warning("Missing Proof", "A medical certificate or proof is required for sick leave.");
        return;
    }

    try {
        const createdRequest = await createLeaveRequest({
            ...leaveForm.value,
            total_days: totalDays,
        });

        if (leaveForm.value.leave_type === "sick_leave" && leaveForm.value.proof_file) {
            try {
                await leaveRequestStore.uploadProof(createdRequest.id, leaveForm.value.proof_file);
            } catch (uploadError) {
                toast.warning(
                    "Proof Upload Failed",
                    "Your sick leave was submitted, but the medical certificate failed to upload. You may need to provide it to HR separately.",
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
            leaveRequestStore.error || error?.response?.data?.message || "Failed to submit leave request.",
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

const handleReuploadProof = async (event) => {
    const file = event.target.files[0];
    event.target.value = "";
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        toast.warning("File Too Large", "Proof file must be less than 5MB.");
        return;
    }

    try {
        await leaveRequestStore.uploadProof(selectedLeaveRequest.value.id, file);
        toast.success("Proof Uploaded", "New proof has been submitted for review");
        const updated = await leaveRequestStore.fetchLeaveRequest(selectedLeaveRequest.value.id);
        selectedLeaveRequest.value = updated;
        await fetchMyLeaveRequests();
    } catch (error) {
        toast.error("Upload Failed", leaveRequestStore.error || "Failed to upload proof");
    }
};

const updateEndDateMin = () => {
    if (leaveForm.value.start_date && leaveForm.value.end_date) {
        if (new Date(leaveForm.value.end_date) < new Date(leaveForm.value.start_date)) {
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
    } catch (_error) {
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
    const diff = currentTime.value - checkInDate.getTime();
    return diff < 8 * 60 * 60 * 1000;
});

const handleCheckIn = async () => {
    if (attendanceLoading.value) return;
    try {
        await checkIn({
            check_in_lat: null,
            check_in_long: null,
            actual_work_mode: isHybrid.value ? actualWorkMode.value : "office",
        });
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
    if (!["request-leave", "leave"].includes(route.query.action) || !canCreateLeaveRequest.value) {
        return;
    }

    openLeaveRequestModal();
    await clearLeaveRequestActionQuery();
};

const handleRouteTabQuery = () => {
    const tab = route.query.tab;
    if (!tab) return;
    const valid = sections.value.some((s) => s.id === tab);
    if (valid) {
        activeSection.value = tab;
    }
};

const setActiveSection = (sectionId) => {
    activeSection.value = sectionId;
};

onMounted(async () => {
    clockInterval = setInterval(() => {
        currentTime.value = Date.now();
    }, 60_000);

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
    handleRouteTabQuery();

    if (!sections.value.some((s) => s.id === activeSection.value) && sections.value.length > 0) {
        activeSection.value = sections.value[0].id;
    }
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }
});
</script>

<template>
    <div class="p-5">
        <div
            class="relative rounded-2xl mb-6 overflow-hidden h-[200px]"
            style="
                background-image: url(&quot;https://images.unsplash.com/photo-1497366216548-37526070297c&quot;);
                background-size: cover;
                background-position: center;
            "
        >
            <div class="absolute inset-0 bg-black/40"></div>

            <div class="relative z-10 p-6 h-full flex flex-col justify-center">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                        <CalendarCheck class="w-8 h-8 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold text-white">Attendance Overview</h1>
                        <p class="text-white/90 text-base font-normal">
                            Track attendance and manage leave requests
                        </p>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-4 right-6 flex items-center gap-2.5 z-10">
                <div
                    v-if="isRemote"
                    class="bg-white/90 backdrop-blur-sm text-brand-dark rounded-lg border border-green-300 px-4 py-3 flex items-center gap-2 shadow-md"
                >
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-green-700 text-sm font-semibold">Auto-present · Remote</span>
                </div>

                <div
                    v-if="canUseClockActions && !isCheckedIn && isHybrid"
                    class="bg-white/95 backdrop-blur-sm text-brand-dark rounded-lg border border-brand-border px-3 py-2 shadow-md"
                >
                    <label for="actual-work-mode" class="sr-only">Actual work mode</label>
                    <select
                        id="actual-work-mode"
                        v-model="actualWorkMode"
                        :disabled="attendanceLoading"
                        class="bg-transparent text-sm font-semibold text-brand-dark focus:outline-none disabled:opacity-50"
                    >
                        <option value="office">Office</option>
                        <option value="remote">Remote</option>
                    </select>
                </div>

                <button
                    v-if="canUseClockActions && !isCheckedIn"
                    type="button"
                    @click="handleCheckIn"
                    :disabled="attendanceLoading"
                    class="bg-white text-brand-dark rounded-lg border border-brand-border hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 px-4 py-3 flex items-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Clock class="w-4 h-4 text-brand-primary" />
                    <span data-testid="clock-in-button-label" class="text-brand-dark text-sm font-semibold">
                        Clock In
                    </span>
                </button>
                <button
                    v-else-if="canUseClockActions && isCheckedIn"
                    type="button"
                    @click="handleCheckOut"
                    :disabled="isClockOutDisabled"
                    class="bg-white text-brand-dark rounded-lg border border-danger-600 hover:ring-2 hover:ring-danger-500/20 transition-all duration-300 px-4 py-3 flex items-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Clock class="w-4 h-4 text-danger-600" />
                    <span class="text-brand-dark text-sm font-semibold">Clock Out</span>
                </button>

                    <button
                    v-if="canCreateLeaveRequest"
                    @click="openLeaveRequestModal"
                    class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2 shadow-md"
                >
                    <Plus class="w-4 h-4 text-white" />
                    <span class="text-white text-sm font-semibold">Request Leave</span>
                </button>
            </div>
        </div>

        <AttendanceStatsCards
            :statistics="statistics"
            :pending-requests-count="pendingRequestsCount"
            :leave-balances="myLeaveBalances"
            :leave-loading="leaveLoading"
        />

        <div class="bg-white border border-brand-border rounded-2xl p-3 mb-6">
            <div
                class="grid gap-3"
                :class="{
                    'grid-cols-1': sections.length === 1,
                    'grid-cols-1 md:grid-cols-2': sections.length === 2,
                    'grid-cols-1 md:grid-cols-3': sections.length === 3,
                    'grid-cols-2 md:grid-cols-4': sections.length === 4,
                }"
            >
                <button
                    v-for="section in sections"
                    :key="section.id"
                    type="button"
                    @click="setActiveSection(section.id)"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeSection === section.id
                            ? 'blue-gradient blue-btn-shadow border border-primary-700 text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-brand-primary/20 bg-white'
                    "
                >
                    <component
                        :is="section.icon"
                        class="w-4 h-4"
                        :class="activeSection === section.id ? 'text-white' : 'text-gray-600'"
                    />
                    <span class="text-sm font-semibold">{{ section.label }}</span>
                </button>
            </div>
        </div>

        <div v-if="activeSection === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div
                v-if="canViewMyLeaveRequests"
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-6"
            >
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                            <CalendarCheck class="w-6 h-6 text-green-600" />
                        </div>
                        <h2 class="text-lg font-semibold text-brand-dark">Recent Attendance</h2>
                    </div>
                    <div class="border border-brand-border rounded-lg p-1 flex items-center gap-1">
                        <button
                            type="button"
                            @click="attendanceViewMode = 'list'"
                            class="px-3 py-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all duration-200"
                            :class="
                                attendanceViewMode === 'list'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-brand-dark hover:bg-gray-100'
                            "
                        >
                            <List class="w-3.5 h-3.5" />
                            List
                        </button>
                        <button
                            type="button"
                            @click="attendanceViewMode = 'calendar'"
                            class="px-3 py-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all duration-200"
                            :class="
                                attendanceViewMode === 'calendar'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-brand-dark hover:bg-gray-100'
                            "
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
                        class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-md transition-all duration-300 p-4"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 bg-brand-primary rounded-xl flex items-center justify-center"
                                >
                                    <User class="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-brand-dark">
                                        {{ getDayName(record.date) }}
                                    </p>
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

                        <div class="border-b border-brand-border mb-3"></div>

                        <div v-if="record.check_in" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-brand-dark text-sm font-medium">Check-in</span>
                                <span class="text-brand-dark text-sm font-semibold">
                                    {{ formatTime(record.check_in) }}
                                </span>
                            </div>
                            <div v-if="record.check_out">
                                <div class="flex items-center justify-between">
                                    <span class="text-brand-dark text-sm font-medium">Check-out</span>
                                    <span class="text-brand-dark text-sm font-semibold">
                                        {{ formatTime(record.check_out) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-brand-dark text-sm font-medium">Total Hours</span>
                                    <span class="text-brand-dark text-sm font-semibold">
                                        {{ calculateWorkingHours(record.check_in, record.check_out) }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-between">
                                <span class="text-brand-dark text-sm font-medium">Status</span>
                                <span class="text-green-600 text-sm font-semibold flex items-center gap-1">
                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                    Currently Working
                                </span>
                            </div>
                        </div>
                        <div v-else class="flex items-center justify-center py-2">
                            <span class="text-brand-light text-sm italic">Data kehadiran kosong</span>
                        </div>
                    </div>

                    <EmptyState
                        v-if="!attendanceLoading && recentAttendances.length === 0"
                        icon="CalendarClock"
                        title="Data kehadiran tidak ditemukan"
                    />
                </div>

                <div v-else class="space-y-4">
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            @click="goToPreviousMonth"
                            aria-label="Previous month"
                            class="w-9 h-9 border border-brand-border rounded-lg flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                        >
                            <ChevronLeft class="w-4 h-4 text-brand-dark" aria-hidden="true" />
                        </button>
                        <p class="text-brand-dark text-base font-bold">{{ calendarMonthLabel }}</p>
                        <button
                            type="button"
                            @click="goToNextMonth"
                            aria-label="Next month"
                            class="w-9 h-9 border border-brand-border rounded-lg flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                        >
                            <ChevronRight class="w-4 h-4 text-brand-dark" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="grid grid-cols-7 border-b border-brand-border">
                        <div
                            v-for="weekday in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                            :key="weekday"
                            class="py-2 text-center text-xs font-semibold text-brand-light"
                        >
                            {{ weekday }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 border-l border-brand-border">
                        <div
                            v-for="day in attendanceCalendarDays"
                            :key="day.isoDate"
                            class="border-r border-b border-brand-border min-h-[72px] p-1.5 flex flex-col transition-all duration-200"
                            :class="[
                                day.inCurrentMonth ? 'bg-white' : 'bg-gray-50/50',
                                day.isToday ? 'bg-blue-50/50' : '',
                            ]"
                        >
                            <span
                                class="text-xs font-semibold mb-1 w-6 h-6 flex items-center justify-center rounded-full"
                                :class="[
                                    day.inCurrentMonth ? 'text-brand-dark' : 'text-gray-400',
                                    day.isToday ? 'bg-brand-primary text-white' : '',
                                ]"
                            >
                                {{ day.day }}
                            </span>
                            <span
                                v-if="day.status && day.inCurrentMonth"
                                class="text-xs font-medium px-1.5 py-0.5 rounded truncate leading-tight"
                                :class="getAttendanceStatusLabelClass(day.status)"
                            >
                                {{ getAttendanceStatusLabel(day.status) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <div
                            v-for="legend in attendanceLegend"
                            :key="legend.key"
                            class="flex items-center gap-1.5 text-xs text-brand-dark"
                        >
                            <span class="w-2.5 h-2.5 rounded" :class="legend.class"></span>
                            <span>{{ legend.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-6"
            >
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                            <CalendarX class="w-6 h-6 text-orange-600" />
                        </div>
                        <h2 class="text-lg font-semibold text-brand-dark">My Leave Requests</h2>
                    </div>
                </div>

                <div v-if="leaveLoading" class="text-center py-8">
                    <p class="text-brand-light">Loading...</p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="request in myLeaveRequests"
                        :key="request.id"
                        class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-md transition-all duration-300 p-4 cursor-pointer"
                        @click="openLeaveDetailsModal(request.id)"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 bg-brand-primary rounded-xl flex items-center justify-center"
                                >
                                    <CalendarPlus class="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-brand-dark">
                                        {{ formatLeaveType(request.leave_type) }}
                                    </p>
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

                        <div class="border-b border-brand-border mb-3"></div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-brand-dark text-sm font-medium">Start Date</span>
                                <span class="text-brand-dark text-sm font-semibold">
                                    {{ formatDateShort(request.start_date) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-brand-dark text-sm font-medium">End Date</span>
                                <span class="text-brand-dark text-sm font-semibold">
                                    {{ formatDateShort(request.end_date) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-brand-dark text-sm font-medium">Requested</span>
                                <span class="text-brand-light text-sm">
                                    {{ formatRequestDate(request.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <EmptyState
                        v-if="!leaveLoading && myLeaveRequests.length === 0"
                        icon="CalendarX"
                        title="Data pengajuan cuti kosong"
                    />
                </div>
            </div>
        </div>

        <div v-else-if="activeSection === 'corrections'">
            <AttendanceCorrectionsList :corrections="myCorrections" />
        </div>

        <div v-else-if="activeSection === 'overtime'">
            <MyOvertime embedded />
        </div>

        <div v-else-if="activeSection === 'hybrid' && isHybrid">
            <HybridSchedules embedded />
        </div>

        <Teleport to="body">
            <div
                v-if="showLeaveRequestModal"
                class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center"
                @click.self="closeLeaveRequestModal"
            >
                <div
                    class="bg-white rounded-2xl border border-brand-border w-full max-w-3xl mx-4 max-h-[90vh] overflow-hidden"
                >
                    <div class="p-6 border-b border-brand-border">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                                    <CalendarPlus class="w-6 h-6 text-blue-600" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-semibold text-brand-dark">Request New Leave</h2>
                                    <p class="text-brand-light text-sm font-normal">
                                        Submit a leave request for approval
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="closeLeaveRequestModal"
                                class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                            >
                                <span class="text-gray-600 text-xl">×</span>
                            </button>
                        </div>
                    </div>

                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                        <form @submit.prevent="submitLeaveRequest" class="space-y-6">
                            <div class="bg-white border border-brand-border rounded-2xl p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            Leave Type *
                                        </label>
                                        <select
                                            v-model="leaveForm.leave_type"
                                            required
                                            class="w-full px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold"
                                        >
                                            <option value="">Select leave type</option>
                                            <option v-for="type in leaveTypes" :key="type.value" :value="type.value">
                                                {{ type.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            Start Date *
                                        </label>
                                        <input
                                            type="date"
                                            v-model="leaveForm.start_date"
                                            @change="updateEndDateMin"
                                            required
                                            data-testid="leave-start-date"
                                            class="w-full px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">End Date *</label>
                                        <input
                                            type="date"
                                            v-model="leaveForm.end_date"
                                            :min="leaveForm.start_date"
                                            required
                                            data-testid="leave-end-date"
                                            class="w-full px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold"
                                        />
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Total Days</label>
                                        <div class="p-4 bg-gray-50 rounded-xl border border-brand-border">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center"
                                                >
                                                    <Clock class="w-5 h-5 text-blue-600" />
                                                </div>
                                                <div>
                                                    <p class="text-brand-dark text-lg font-bold">
                                                        {{ totalDaysCalculated }}
                                                        {{ totalDaysCalculated === 1 ? "day" : "days" }}
                                                    </p>
                                                    <p class="text-brand-light text-sm">Excluding weekends</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white border border-brand-border rounded-2xl p-6">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            Reason for Leave *
                                        </label>
                                        <textarea
                                            v-model="leaveForm.reason"
                                            required
                                            rows="4"
                                            data-testid="leave-reason"
                                            class="w-full px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold resize-none"
                                            placeholder="Please provide a detailed reason for your leave request..."
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            Emergency Contact (Optional)
                                        </label>
                                        <input
                                            type="tel"
                                            v-model="leaveForm.emergency_contact"
                                            class="w-full px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold"
                                            placeholder="Phone number for emergency contact"
                                        />
                                    </div>

                                    <div v-if="leaveForm.leave_type === 'sick_leave'">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            Medical Certificate / Proof *
                                        </label>
                                        <input
                                            type="file"
                                            @change="handleProofFileChange"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            required
                                            class="w-full px-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 text-sm font-medium text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                        />
                                        <p class="text-xs text-brand-light mt-1.5">
                                            Max size: 5MB. Formats: PDF, JPG, PNG.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <button
                                    type="button"
                                    @click="closeLeaveRequestModal"
                                    class="border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center gap-2"
                                >
                                    <span class="text-brand-dark text-base font-semibold">Cancel</span>
                                </button>
                                <button
                                    type="submit"
                                    class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
                                >
                                    <span class="text-brand-white text-base font-semibold">Submit Request</span>
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
                    class="bg-white rounded-2xl border border-brand-border w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden"
                >
                    <div class="p-6 border-b border-brand-border">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                                    <CalendarCheck class="w-6 h-6 text-blue-600" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-semibold text-brand-dark">Leave Request Details</h2>
                                    <p class="text-brand-light text-sm font-normal">
                                        Complete information about this leave request
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="closeLeaveDetailsModal"
                                class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                            >
                                <span class="text-gray-600 text-xl">×</span>
                            </button>
                        </div>
                    </div>

                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                        <div class="space-y-6">
                            <div class="bg-white border border-brand-border rounded-2xl p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            Leave Type
                                        </label>
                                        <div class="p-3 bg-gray-50 rounded-xl border border-brand-border">
                                            <span class="text-brand-dark text-base font-medium">
                                                {{ formatLeaveType(selectedLeaveRequest.leave_type) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">Status</label>
                                        <div class="p-3 bg-gray-50 rounded-xl border border-brand-border">
                                            <span
                                                :class="getStatusConfig(selectedLeaveRequest.status).class"
                                                class="text-base font-semibold"
                                            >
                                                {{ getStatusConfig(selectedLeaveRequest.status).text }}
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            Start Date
                                        </label>
                                        <div class="p-3 bg-gray-50 rounded-xl border border-brand-border">
                                            <span class="text-brand-dark text-base font-medium">
                                                {{ formatDateLong(selectedLeaveRequest.start_date) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            End Date
                                        </label>
                                        <div class="p-3 bg-gray-50 rounded-xl border border-brand-border">
                                            <span class="text-brand-dark text-base font-medium">
                                                {{ formatDateLong(selectedLeaveRequest.end_date) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            Total Duration
                                        </label>
                                        <div class="p-4 bg-blue-50 rounded-xl border border-brand-border">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"
                                                >
                                                    <Clock class="w-5 h-5 text-blue-600" />
                                                </div>
                                                <div>
                                                    <p class="text-brand-dark text-lg font-bold">
                                                        {{ selectedLeaveRequest.total_days }}
                                                        {{ selectedLeaveRequest.total_days === 1 ? "day" : "days" }}
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

                            <div class="bg-white border border-brand-border rounded-2xl p-6">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            Reason for Leave
                                        </label>
                                        <div
                                            class="p-4 bg-gray-50 rounded-xl border border-brand-border min-h-[100px]"
                                        >
                                            <p class="text-brand-dark text-base leading-relaxed">
                                                {{ selectedLeaveRequest.reason }}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            Request Submitted
                                        </label>
                                        <div class="p-3 bg-gray-50 rounded-xl border border-brand-border">
                                            <span class="text-brand-dark text-base font-medium">
                                                {{ formatRequestDateLong(selectedLeaveRequest.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Proof Document Section -->
                            <div
                                v-if="selectedLeaveRequest.leave_type === 'sick_leave' && selectedLeaveRequest.proof_file_path"
                                class="bg-white border border-brand-border rounded-2xl p-6"
                            >
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-brand-dark text-base font-semibold mb-2">
                                            Proof Document
                                        </label>
                                        <div class="p-4 bg-gray-50 rounded-xl border border-brand-border">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"
                                                    >
                                                        <FileText class="w-5 h-5 text-blue-600" />
                                                    </div>
                                                    <div>
                                                        <p class="text-brand-dark text-base font-medium">
                                                            {{ selectedLeaveRequest.proof_file_name || 'Proof Document' }}
                                                        </p>
                                                        <p class="text-brand-light text-sm">
                                                            {{ selectedLeaveRequest.proof_size_kb ? `${selectedLeaveRequest.proof_size_kb} KB` : '' }}
                                                            {{ selectedLeaveRequest.proof_uploaded_at ? `• Uploaded ${formatRequestDateLong(selectedLeaveRequest.proof_uploaded_at)}` : '' }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        v-if="selectedLeaveRequest.proof_review_status === 'approved'"
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-green-50 border border-green-200 text-green-700"
                                                    >
                                                        Approved
                                                    </span>
                                                    <span
                                                        v-else-if="selectedLeaveRequest.proof_review_status === 'rejected'"
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-red-50 border border-red-200 text-red-700"
                                                    >
                                                        Rejected
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-50 border border-yellow-200 text-yellow-700"
                                                    >
                                                        Pending Review
                                                    </span>
                                                </div>
                                            </div>
                                            <!-- Rejection notes -->
                                            <div
                                                v-if="selectedLeaveRequest.proof_review_status === 'rejected' && selectedLeaveRequest.proof_review_notes"
                                                class="mt-3 p-3 bg-red-50 rounded-lg border border-red-200"
                                            >
                                                <p class="text-sm text-red-700">
                                                    <span class="font-semibold">Rejection reason:</span>
                                                    {{ selectedLeaveRequest.proof_review_notes }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Re-upload section for rejected proofs -->
                            <div
                                v-if="selectedLeaveRequest.leave_type === 'sick_leave' && selectedLeaveRequest.proof_review_status === 'rejected'"
                                class="bg-white border border-brand-border rounded-2xl p-6"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-brand-dark">Re-upload Proof</h3>
                                        <p class="text-brand-light text-sm mt-1">
                                            Your proof was rejected. You can upload a new document.
                                        </p>
                                    </div>
                                    <label
                                        class="px-4 py-2 rounded-lg blue-gradient blue-btn-shadow text-white font-medium text-sm hover:brightness-110 transition-all cursor-pointer"
                                    >
                                        <input
                                            type="file"
                                            class="hidden"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            @change="handleReuploadProof"
                                        />
                                        Upload New Proof
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-4">
                                <button
                                    type="button"
                                    @click="closeLeaveDetailsModal"
                                    class="border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center gap-2"
                                >
                                    <span class="text-brand-dark text-base font-semibold">Close</span>
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
