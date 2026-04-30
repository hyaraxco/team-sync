<script setup>
import {
  BellIcon,
  ChevronDownIcon,
  MessageCircleIcon,
  UserIcon,
  LogOutIcon,
  MenuIcon,
} from "lucide-vue-next";
import NotificationPanel from "@/components/admin/NotificationPanel.vue";
import { useAuthStore } from "@/stores/auth";
import { useNotificationStore } from "@/stores/notifications";
import { storeToRefs } from "pinia";
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRoute, useRouter, RouterLink } from "vue-router";
import _ from "lodash";
import { DEFAULT_AVATAR } from "@/helpers/format";

const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const { user } = storeToRefs(authStore);
const { logout } = authStore;
const router = useRouter();

const isAccountMenuOpen = ref(false);
const isNotificationPanelOpen = ref(false);
const notificationPanelId = "header-notification-panel";
const unreadPollingIntervalMs = 15000;
const unreadPollingIntervalId = ref(null);
const emit = defineEmits(["toggle-sidebar"]);

const route = useRoute();

const titles = {
  "admin.dashboard": {
    title: "Dashboard",
    subtitle: "Track your team's performance and key metrics at a glance.",
  },
  "admin.notifications": {
    title: "Notifications",
    subtitle: "Stay up to date with your latest activity.",
  },
  "admin.teams": {
    title: "Teams",
    subtitle: "Organize teams and keep everyone aligned.",
  },
  "admin.team.detail": {
    title: "Team Details",
    subtitle: "View team information",
  },
  "admin.team.create": { title: "Create Team", subtitle: "Set up a new team" },
  "admin.team.edit": {
    title: "Edit Team",
    subtitle: "Update team information",
  },
  "admin.staffMembers": {
    title: "Employees",
    subtitle: "Manage employee records",
  },
  "admin.staffMembers.create": {
    title: "Create Staff Member",
    subtitle: "Add new employee",
  },
  "admin.staffMembers.edit": {
    title: "Edit Staff Member",
    subtitle: "Update employee information",
  },
  "admin.staffMembers.detail": {
    title: "Staff Member Details",
    subtitle: "View employee profile",
  },
  "admin.staffMembers.success": {
    title: "Employee Created",
    subtitle: "Employee has been added",
  },
  "admin.attendances": {
    title: "Attendance",
    subtitle: "Review clock-in and clock-out records quickly.",
  },
  "admin.projects": {
    title: "Projects",
    subtitle: "Plan projects and keep tasks on track.",
  },
  "admin.projects.create": {
    title: "Create Project",
    subtitle: "Set up a new project",
  },
  "admin.projects.edit": {
    title: "Edit Project",
    subtitle: "Update project information",
  },
  "admin.projects.detail": {
    title: "Project Details",
    subtitle: "View project information",
  },
  "admin.payroll.dashboard": {
    title: "Payroll",
    subtitle: "Manage payroll with clear payment insights.",
  },
  "admin.payroll.create": {
    title: "Create Payroll",
    subtitle: "Generate payroll for employees",
  },
  "admin.payroll.detail": {
    title: "Payroll Details",
    subtitle: "View payroll summary",
  },
  "staffMember.profile": {
    title: "My Profile",
    subtitle: "Manage your personal information",
  },
  "staffMember.profile.edit": {
    title: "Edit Profile",
    subtitle: "Update your personal information",
  },
  "staffMember.team": { title: "My Team", subtitle: "See your team members" },
  "staffMember.attendance.my-attendances": {
    title: "My Attendance",
    subtitle: "Check your attendance, clock logs, and leave status.",
  },
  "staffMember.attendance.clock": {
    title: "My Attendance",
    subtitle: "Check your attendance, clock logs, and leave status.",
  },
  "staffMember.payroll": {
    title: "My Payroll",
    subtitle: "Review your payroll history and details.",
  },
  "staffMember.payroll.detail": {
    title: "Payroll Details",
    subtitle: "See payroll breakdown",
  },
  "staffMember.payslips": {
    title: "My Payroll",
    subtitle: "Review your payroll history and details.",
  },
  "staffMember.payslips.detail": {
    title: "Payroll Details",
    subtitle: "See payroll breakdown",
  },
};

const pageInfo = computed(() => {
  const name = route.name?.toString() || "";
  return titles[name] || titles["admin.dashboard"];
});

const pageTitle = computed(() => pageInfo.value.title);
const pageSubtitle = computed(() => pageInfo.value.subtitle);
const notifications = computed(() => notificationStore.notifications);
const notificationsLoading = computed(() => notificationStore.loading);
const notificationsError = computed(() => notificationStore.error);
const unreadNotificationCount = computed(() => notificationStore.unreadCount);
const isEmployeeUser = computed(() => {
  const roles = Array.isArray(user.value?.roles) ? user.value.roles : [];
  return roles.some((role) => String(role).toLowerCase() === "staff");
});
const formatUnreadCount = (count, maxCount = 99) => {
  const safeCount = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0;
  return safeCount > maxCount ? `${maxCount}+` : String(safeCount);
};
const unreadBadgeText = computed(() =>
  formatUnreadCount(
    unreadNotificationCount.value,
    isEmployeeUser.value ? 9 : 99,
  ),
);
const unreadBadgeClass = computed(() => {
  if (isEmployeeUser.value) {
    const safeCount = Number.isFinite(unreadNotificationCount.value)
      ? Math.max(0, Math.floor(unreadNotificationCount.value))
      : 0;
    const sizeClass = safeCount > 9 ? "w-6 px-1" : "w-5";

    return `absolute -right-1 -top-1 flex h-5 ${sizeClass} items-center justify-center rounded-full border-2 border-white bg-[#0C51D9] text-[10px] font-bold leading-none text-white`;
  }

  return "absolute right-0 top-0 flex h-5 min-w-[1.75rem] -translate-y-1/2 translate-x-1/3 items-center justify-center rounded-full border-2 border-white bg-[#EE2A3B] px-1.5 text-[11px] font-bold leading-none tracking-tight text-white";
});
const notificationButtonLabel = computed(() => {
  if (unreadNotificationCount.value > 0) {
    return `Notifications, ${unreadBadgeText.value} new`;
  }

  return "Notifications";
});

const fetchUnreadCount = async () => {
  await notificationStore.fetchUnreadCount();
};

const fetchLatestNotifications = async () => {
  await notificationStore.fetchLatestNotifications(5);
};

const toggleNotificationPanel = async () => {
  const nextState = !isNotificationPanelOpen.value;
  isNotificationPanelOpen.value = nextState;

  if (!nextState) {
    return;
  }

  isAccountMenuOpen.value = false;
  await Promise.all([fetchLatestNotifications(), fetchUnreadCount()]);
};

const toggleAccountMenu = () => {
  isAccountMenuOpen.value = !isAccountMenuOpen.value;

  if (isAccountMenuOpen.value) {
    isNotificationPanelOpen.value = false;
  }
};

const handleNotificationSelect = async (notification) => {
  if (!notification?.id) {
    return;
  }

  await notificationStore.markNotificationAsRead(notification.id);
  await fetchUnreadCount();
  isNotificationPanelOpen.value = false;

  const actionUrl =
    typeof notification.action_url === "string"
      ? notification.action_url.trim()
      : "";

  if (!actionUrl) {
    return;
  }

  if (/^https?:\/\//i.test(actionUrl)) {
    window.location.assign(actionUrl);
    return;
  }

  await router.push(actionUrl);
};

const handleLogout = async () => {
  isAccountMenuOpen.value = false;
  await logout();
};

const startUnreadPolling = () => {
  if (unreadPollingIntervalId.value) {
    return;
  }

  unreadPollingIntervalId.value = window.setInterval(() => {
    fetchUnreadCount();
  }, unreadPollingIntervalMs);
};

const stopUnreadPolling = () => {
  if (!unreadPollingIntervalId.value) {
    return;
  }

  window.clearInterval(unreadPollingIntervalId.value);
  unreadPollingIntervalId.value = null;
};

const handleVisibilityChange = () => {
  if (document.visibilityState === "visible") {
    fetchUnreadCount();
    startUnreadPolling();
    return;
  }

  stopUnreadPolling();
};

// Click outside to close open panels
const accountDropdownRef = ref(null);
const notificationDropdownRef = ref(null);
const handleClickOutside = (event) => {
  if (
    accountDropdownRef.value &&
    !accountDropdownRef.value.contains(event.target)
  ) {
    isAccountMenuOpen.value = false;
  }

  if (
    notificationDropdownRef.value &&
    !notificationDropdownRef.value.contains(event.target)
  ) {
    isNotificationPanelOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
  document.addEventListener("visibilitychange", handleVisibilityChange);
  fetchUnreadCount();
  startUnreadPolling();
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  document.removeEventListener("visibilitychange", handleVisibilityChange);
  stopUnreadPolling();
});
</script>

<template>
  <header
    class="page-header bg-white border-b border-[#DCDEDD] px-4 sm:px-6 py-3.5 sm:py-4"
  >
    <div
      class="flex items-start sm:items-center justify-between gap-3 sm:gap-4"
    >
      <div class="min-w-0 flex items-start sm:items-center gap-3 sm:gap-4">
        <button
          type="button"
          aria-label="Toggle sidebar"
          data-testid="header-mobile-menu-toggle"
          class="lg:hidden w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
          @click="emit('toggle-sidebar')"
        >
          <MenuIcon class="w-5 h-5 text-gray-600" />
        </button>
        <div class="min-w-0">
          <h2
            class="text-brand-dark text-xl sm:text-2xl font-extrabold leading-tight truncate"
          >
            {{ pageTitle }}
          </h2>
          <p
            class="hidden md:block text-brand-light text-sm font-normal leading-snug mt-1 truncate"
          >
            {{ pageSubtitle }}
          </p>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-2 sm:gap-4">
        <!-- Action Buttons -->
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="relative z-50" ref="notificationDropdownRef">
            <button
              type="button"
              data-testid="header-notification-toggle"
              :aria-label="notificationButtonLabel"
              aria-haspopup="dialog"
              :aria-expanded="isNotificationPanelOpen ? 'true' : 'false'"
              :aria-controls="notificationPanelId"
              class="relative w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
              @click="toggleNotificationPanel"
            >
              <BellIcon class="w-5 h-5 text-gray-600" />
              <span
                v-if="unreadNotificationCount > 0"
                data-testid="header-notification-unread-badge"
                :class="unreadBadgeClass"
              >
                {{ unreadBadgeText }}
              </span>
            </button>

            <NotificationPanel
              :open="isNotificationPanelOpen"
              :panel-id="notificationPanelId"
              :notifications="notifications"
              :loading="notificationsLoading"
              :error="notificationsError"
              @retry="fetchLatestNotifications"
              @select="handleNotificationSelect"
            />
          </div>
          <button
            type="button"
            aria-label="Messages"
            class="hidden sm:flex w-10 h-10 rounded-full border border-[#DCDEDD] items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
          >
            <MessageCircleIcon class="w-5 h-5 text-gray-600" />
          </button>
        </div>

        <!-- Divider -->
        <div class="hidden sm:block w-px h-8 bg-[#DCDEDD] mx-5"></div>

        <!-- User Profile -->
        <div class="relative z-50" ref="accountDropdownRef">
          <button
            type="button"
            data-testid="header-profile-toggle"
            aria-label="Toggle account menu"
            aria-haspopup="menu"
            :aria-expanded="isAccountMenuOpen ? 'true' : 'false'"
            aria-controls="header-account-menu"
            class="flex items-center gap-2 sm:gap-3 cursor-pointer"
            @click="toggleAccountMenu"
          >
            <img
              :src="user?.profile_photo || DEFAULT_AVATAR"
              alt="User Avatar"
              class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover"
            />
            <div class="hidden md:block text-left">
              <p class="text-brand-dark text-sm sm:text-base font-semibold">
                {{ user?.name }}
              </p>
              <p
                class="text-brand-dark text-sm sm:text-base font-normal leading-7"
              >
                {{ _.join(user?.roles, ", ").toUpperCase() }}
              </p>
            </div>
            <ChevronDownIcon
              class="w-4 h-4 text-gray-400 hidden sm:block"
              :class="{ 'rotate-180': isAccountMenuOpen }"
            />
          </button>

          <!-- Dropdown Menu -->
          <div
            id="header-account-menu"
            data-testid="header-account-menu"
            role="menu"
            class="absolute right-0 top-full mt-2 w-56 bg-white border border-[#DCDEDD] rounded-lg shadow-md py-2 z-[9999]"
            :class="{ hidden: !isAccountMenuOpen }"
          >
            <div class="px-4 py-3 border-b border-[#DCDEDD]">
              <p class="text-sm font-semibold text-gray-900">
                {{ user?.name }}
              </p>
              <p class="text-xs text-gray-600">{{ user?.email }}</p>
              <p class="text-xs text-gray-500">
                {{ _.join(user?.roles, ", ").toUpperCase() }}
              </p>
            </div>

            <div class="py-1">
              <RouterLink
                :to="{ name: 'staffMember.profile' }"
                role="menuitem"
                data-testid="header-profile-menu-item"
                class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer"
                @click="isAccountMenuOpen = false"
              >
                <UserIcon class="w-4 h-4" />
                Profile
              </RouterLink>
            </div>

            <div class="border-t border-[#DCDEDD] py-1">
              <button
                type="button"
                role="menuitem"
                @click="handleLogout"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors cursor-pointer text-left disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <LogOutIcon class="w-4 h-4" />
                Sign Out
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
