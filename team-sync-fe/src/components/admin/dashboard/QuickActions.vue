<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import { UserPlusIcon, UsersIcon, BanknoteIcon, CalendarPlusIcon, VideoIcon, Clock3Icon } from "lucide-vue-next";
import { can, canOneOf } from "@/helpers/permissionHelper";
import { useAttendanceStore } from "@/stores/attendance";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import { useToast } from "@/composables/useToast";
import MeetingCreateModal from "@/components/admin/meeting/MeetingCreateModal.vue";

const showMeetingModal = ref(false);

const actionConfigs = [
    {
        id: "add-employee",
        label: "Add Staff Member",
        icon: UserPlusIcon,
        to: { name: "admin.staffMembers.create" },
        isVisible: () => can("staff-member-create"),
    },
    {
        id: "create-team",
        label: "Create New Team",
        icon: UsersIcon,
        to: { name: "admin.team.create" },
        isVisible: () => can("team-create"),
    },
    {
        id: "process-payroll",
        label: "Process Payroll",
        icon: BanknoteIcon,
        to: { name: "admin.payroll.create" },
        isVisible: () => can("payroll-create"),
    },
    {
        id: "clock-in-out",
        label: () => {
            const { todayAttendance } = storeToRefs(useAttendanceStore());
            const isCheckedIn = todayAttendance.value?.check_in && !todayAttendance.value?.check_out;
            return isCheckedIn ? "Clock Out" : "Clock In";
        },
        icon: Clock3Icon,
        action: async () => {
            const attendanceStore = useAttendanceStore();
            const toast = useToast();
            const { todayAttendance } = storeToRefs(attendanceStore);
            const isCheckedIn = todayAttendance.value?.check_in && !todayAttendance.value?.check_out;

            try {
                if (!isCheckedIn) {
                    await attendanceStore.checkIn({ check_in_lat: null, check_in_long: null });
                    toast.success("Clocked In", "You have successfully clocked in.");
                } else {
                    await attendanceStore.checkOut({ check_out_lat: null, check_out_long: null });
                    toast.success("Clocked Out", "You have successfully clocked out.");
                }
                await attendanceStore.fetchTodayAttendance();
            } catch (e) {
                toast.error("Action Failed", e?.response?.data?.message || "Failed to process attendance action.");
            }
        },
        isDisabled: () => {
            const attendanceStore = useAttendanceStore();
            const { todayAttendance, loading } = storeToRefs(attendanceStore);
            if (loading.value) return true;
            const isCheckedIn = todayAttendance.value?.check_in && !todayAttendance.value?.check_out;
            if (isCheckedIn) {
                const checkInDate = new Date(todayAttendance.value.check_in);
                const diff = Date.now() - checkInDate.getTime();
                // Require 8 hours gap for clock out
                return diff < 8 * 60 * 60 * 1000;
            }
            return false;
        },
        isVisible: () => {
            const workLocation = useAuthStore().user?.employee_profile?.job_information?.work_location;
            if (workLocation === "remote") return false;
            return canOneOf(["attendance-check-in", "attendance-check-out"]);
        },
    },
    {
        id: "request-leave",
        label: "Request Leave",
        icon: CalendarPlusIcon,
        to: {
            name: "staffMember.attendance.my-attendances",
            query: { action: "request-leave" },
        },
        isVisible: () =>
            can("leave-request-create") &&
            canOneOf(["attendance-my-attendances", "attendance-check-in", "attendance-check-out"]),
    },
    {
        id: "schedule-meeting",
        label: "Schedule Meeting",
        icon: VideoIcon,
        action: () => {
            showMeetingModal.value = true;
        },
        isVisible: () => can("meeting-create"),
    },
];

const actionableActions = computed(() => actionConfigs.filter((action) => action.isVisible()));

const visibleActions = computed(() => actionableActions.value);

const primaryActionId = computed(() => actionableActions.value[0]?.id ?? null);

const isPrimaryAction = (action) => action.id === primaryActionId.value;

const getActionClasses = (action) => {
    if (isPrimaryAction(action)) {
        return "btn-secondary w-full text-left rounded-xl border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2";
    }

    return "btn-secondary w-full text-left border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:rounded-xl focus:border-brand-primary focus:border-2 focus:rounded-xl focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2";
};

const getIconClasses = (action) => {
    return isPrimaryAction(action) ? "w-4 h-4 text-white" : "w-4 h-4 text-gray-600";
};

const getLabelClasses = (action) => {
    return isPrimaryAction(action) ? "text-brand-white text-sm font-semibold" : "text-brand-dark text-sm font-medium";
};

const resolveLabel = (action) => {
    if (typeof action.label === "function") {
        return action.label();
    }
    return action.label;
};

onMounted(async () => {
    if (canOneOf(["attendance-check-in", "attendance-check-out"])) {
        const attendanceStore = useAttendanceStore();
        await attendanceStore.fetchTodayAttendance();
    }
});
</script>

<template>
    <!-- Quick Actions Card (spans 2 rows on the right) -->
    <div
        class="lg:row-span-2 bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
    >
        <h3 class="text-brand-dark text-lg font-bold mb-4">Quick Actions</h3>
        <div class="space-y-3">
            <template v-for="action in visibleActions" :key="action.id">
                <!-- Route Link Action -->
                <RouterLink
                    v-if="action.to"
                    :to="action.to"
                    :class="getActionClasses(action)"
                    :data-action-id="action.id"
                >
                    <component :is="action.icon" :class="getIconClasses(action)" />
                    <span :class="getLabelClasses(action)">{{ resolveLabel(action) }}</span>
                </RouterLink>

                <!-- Button Action (Click Handler) -->
                <button
                    v-else-if="action.action"
                    type="button"
                    :disabled="action.isDisabled?.()"
                    @click="action.action"
                    class="disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="getActionClasses(action)"
                    :data-action-id="action.id"
                >
                    <component :is="action.icon" :class="getIconClasses(action)" />
                    <span :class="getLabelClasses(action)">{{ resolveLabel(action) }}</span>
                </button>
            </template>
        </div>

        <MeetingCreateModal
            :show="showMeetingModal"
            @close="showMeetingModal = false"
            @created="showMeetingModal = false"
        />
    </div>
</template>
