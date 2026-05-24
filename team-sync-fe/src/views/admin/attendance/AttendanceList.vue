<script setup>
import { ref, onMounted, computed } from "vue";
import { TrendingUp, CheckCircle, Clock, CalendarClock, CalendarDays, Timer, MapPin } from "lucide-vue-next";
import { useAttendanceStore } from "@/stores/attendance";
import StatsCard from "@/components/common/StatsCard.vue";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { can } from "@/helpers/permissionHelper";
import { useToast } from "@/composables/useToast";
import AttendanceRecordList from "@/views/admin/attendance/AttendanceRecordList.vue";
import OvertimeManagement from "@/views/admin/attendance/OvertimeManagement.vue";
import HybridScheduleList from "@/views/admin/attendance/HybridScheduleList.vue";
import LeaveRequestList from "@/views/admin/attendance/LeaveRequestList.vue";
import AttendanceCorrectionList from "@/views/admin/attendance/AttendanceCorrectionList.vue";

const attendanceStore = useAttendanceStore();
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


const loadingStatistics = ref(false);

const activeTab = ref("leave-requests");

const sections = computed(() => {
    const items = [];
    if (can("leave-request-list")) {
        items.push({ id: "leave-requests", label: "Leave Requests", icon: CalendarClock });
    }
    if (can("attendance-correction-list")) {
        items.push({ id: "corrections", label: "Corrections", icon: Clock });
    }
    if (can("attendance-list")) {
        items.push({ id: "records", label: "Attendance Logs", icon: CalendarDays });
    }
    if (can("overtime-list")) {
        items.push({ id: "overtime", label: "Overtime", icon: Timer });
    }
    items.push({ id: "hybrid", label: "Hybrid Schedules", icon: MapPin });
    return items;
});

const setActiveTab = (id) => {
    activeTab.value = id;
};

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

onMounted(async () => {
    await loadStatistics();
});
</script>

<template>
    <div class="flex-1 flex flex-col overflow-hidden">
        <span class="sr-only" role="heading" aria-level="1">Attendance</span>

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

        <!-- Tabs -->
        <div class="bg-white border border-brand-border rounded-2xl p-3 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    type="button"
                    @click="setActiveTab(section.id)"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeTab === section.id
                            ? 'blue-gradient blue-btn-shadow border border-primary-700 text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-brand-primary/20 bg-white'
                    "
                >
                    <component
                        :is="section.icon"
                        class="w-4 h-4"
                        :class="activeTab === section.id ? 'text-white' : 'text-gray-600'"
                    />
                    <span class="text-sm font-semibold">{{ section.label }}</span>
                </button>
            </div>
        </div>

        <!-- Leave Requests Tab -->
        <div
            v-if="can('leave-request-list') && activeTab === 'leave-requests'"
            class="bg-white border border-brand-border rounded-2xl p-6 mb-6"
        >
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">Leave Requests</h3>
                    <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">View and manage leave requests</p>
                </div>
            </div>
            <LeaveRequestList embedded />
        </div>

        <!-- Corrections Tab -->
        <div
            v-if="can('attendance-correction-list') && activeTab === 'corrections'"
            class="bg-white border border-brand-border rounded-2xl p-6 mb-6"
        >
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">Attendance Corrections</h3>
                    <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">View and manage attendance corrections</p>
                </div>
            </div>
            <AttendanceCorrectionList embedded />
        </div>

        <!-- Attendance Logs Tab -->
        <div
            v-if="can('attendance-list') && activeTab === 'records'"
            class="bg-white border border-brand-border rounded-2xl p-6 mb-6"
        >
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">Attendance Logs</h3>
                    <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">View and manage attendance records</p>
                </div>
            </div>
            <AttendanceRecordList embedded />
        </div>

        <!-- Overtime Tab -->
        <div
            v-if="can('overtime-list') && activeTab === 'overtime'"
            class="bg-white border border-brand-border rounded-2xl p-6 mb-6"
        >
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">Overtime Management</h3>
                    <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">Manage employee overtime records and approvals</p>
                </div>
            </div>
            <OvertimeManagement embedded />
        </div>

        <!-- Hybrid Schedules Tab -->
        <div
            v-if="activeTab === 'hybrid'"
            class="bg-white border border-brand-border rounded-2xl p-6 mb-6"
        >
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">Hybrid Schedules</h3>
                    <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">Manage employee hybrid work schedules and exceptions</p>
                </div>
            </div>
            <HybridScheduleList embedded />
        </div>
    </div>
</template>
