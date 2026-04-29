<script setup lang="ts">
import { computed, onMounted } from "vue";
import { RouterLink } from "vue-router";
import {
  UserPlusIcon,
  UsersIcon,
  BanknoteIcon,
  CalendarPlusIcon,
  Clock3Icon,
} from "lucide-vue-next";
import { can, canOneOf } from "@/helpers/permissionHelper";
import { useAttendanceStore } from "@/stores/attendance";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import { useToast } from "@/composables/useToast";

type QuickActionLink = {
  name: string;
  query?: Record<string, string>;
};

type ActionableQuickAction = {
  id: string;
  label: string | (() => string);
  icon: unknown;
  to?: QuickActionLink;
  action?: () => void;
  isPlaceholder?: false;
  isVisible: () => boolean;
  isDisabled?: () => boolean;
};

type PlaceholderQuickAction = {
  id: string;
  label: string;
  icon: unknown;
  isPlaceholder: true;
  isVisible: () => boolean;
  to?: never;
  action?: never;
  isDisabled?: never;
};

type QuickAction = ActionableQuickAction | PlaceholderQuickAction;

const actionConfigs: ActionableQuickAction[] = [
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
      } catch (e: any) {
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
      if (workLocation === 'remote') return false;
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
      canOneOf([
        "attendance-my-attendances",
        "attendance-check-in",
        "attendance-check-out",
      ]),
  },
];

const placeholderAction: PlaceholderQuickAction = {
  id: "schedule-meeting",
  label: "Schedule Meeting",
  icon: CalendarPlusIcon,
  isPlaceholder: true,
  isVisible: () => true,
};

const actionableActions = computed(() =>
  actionConfigs.filter((action) => action.isVisible())
);

const visibleActions = computed(() => [
  ...actionableActions.value,
  placeholderAction,
]);

const primaryActionId = computed(() => actionableActions.value[0]?.id ?? null);

const isPrimaryAction = (action: QuickAction) =>
  !action.isPlaceholder && action.id === primaryActionId.value;

const getActionClasses = (action: QuickAction) => {
  if (action.isPlaceholder) {
    return "w-full text-left border border-[#DCDEDD] rounded-[16px] bg-gray-50 cursor-not-allowed opacity-70 px-4 py-3 flex items-center gap-2";
  }

  if (isPrimaryAction(action)) {
    return "btn-secondary w-full text-left rounded-[12px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2";
  }

  return "btn-secondary w-full text-left border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:rounded-[12px] focus:border-[#0C51D9] focus:border-2 focus:rounded-[12px] focus:bg-white transition-all duration-300 px-4 py-3 flex items-center gap-2";
};

const getIconClasses = (action: QuickAction) => {
  if (action.isPlaceholder) {
    return "w-4 h-4 text-gray-400";
  }

  return isPrimaryAction(action)
    ? "w-4 h-4 text-white"
    : "w-4 h-4 text-gray-600";
};

const getLabelClasses = (action: QuickAction) => {
  if (action.isPlaceholder) {
    return "text-brand-dark text-sm font-medium";
  }

  return isPrimaryAction(action)
    ? "text-brand-white text-sm font-semibold"
    : "text-brand-dark text-sm font-medium";
};

const resolveLabel = (action: QuickAction) => {
  if (typeof action.label === 'function') {
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
    class="lg:row-span-2 bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
  >
    <h3 class="text-brand-dark text-lg font-bold mb-4">Quick Actions</h3>
    <div class="space-y-3">
      <template v-for="action in visibleActions" :key="action.id">
        <!-- Route Link Action -->
        <RouterLink
          v-if="!action.isPlaceholder && action.to"
          :to="action.to"
          :class="getActionClasses(action)"
          :data-action-id="action.id"
        >
          <component :is="action.icon" :class="getIconClasses(action)" />
          <span :class="getLabelClasses(action)">{{ resolveLabel(action) }}</span>
        </RouterLink>

        <!-- Button Action (Click Handler) -->
        <button
          v-else-if="!action.isPlaceholder && action.action"
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

        <button
          v-else
          type="button"
          disabled
          :class="getActionClasses(action)"
          :data-action-id="action.id"
        >
          <component :is="action.icon" :class="getIconClasses(action)" />
          <div class="flex items-center justify-between w-full gap-2">
            <span :class="getLabelClasses(action)">{{ resolveLabel(action) }}</span>
            <span class="text-xs font-semibold text-gray-400">Coming soon</span>
          </div>
        </button>
      </template>
    </div>
  </div>
</template>
