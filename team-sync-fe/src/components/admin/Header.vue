<script setup>
import {
    BellIcon,
    ChevronDownIcon,
    MessageCircleIcon,
    UserIcon,
    LogOutIcon,
    MenuIcon,
    SunIcon,
    MoonIcon,
} from "lucide-vue-next";
import NotificationPanel from "@/components/admin/NotificationPanel.vue";
import { useAuthStore } from "@/stores/auth";
import { useNotificationStore } from "@/stores/notifications";
import { useToast } from "@/composables/useToast";
import { useDarkMode } from "@/composables/useDarkMode";
import { storeToRefs } from "pinia";
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRoute, useRouter, RouterLink } from "vue-router";
import { join } from "lodash-es";

const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const { user } = storeToRefs(authStore);
const { logout } = authStore;
const router = useRouter();
const { info: toastInfo } = useToast();
const { isDark, toggle: toggleDark } = useDarkMode();

const isAccountMenuOpen = ref(false);
const isNotificationPanelOpen = ref(false);
const notificationPanelId = "header-notification-panel";
const unreadPollingIntervalMs = 15000;
const unreadPollingIntervalId = ref(null);
const previousUnreadCount = ref(0);
const emit = defineEmits(["toggle-sidebar"]);

const route = useRoute();

const titles = {
    "admin.dashboard": {
        title: "Dashboard",
        subtitle: "Overview ringkas aktivitas tim dan perusahaan.",
    },
    "admin.notifications": {
        title: "Notifications",
        subtitle: "Recent activity that needs attention.",
    },
    "admin.settings": {
        title: "Settings",
        subtitle: "System configuration and preferences.",
    },
    "admin.analytics": {
        title: "Analytics",
        subtitle: "Company-wide insights and metrics.",
    },
    "admin.meetings": {
        title: "Meetings",
        subtitle: "Schedule and manage team meetings.",
    },
    "admin.teams": {
        title: "Teams",
        subtitle: "Manage team structure and members.",
    },
    "admin.team.detail": {
        title: "Team Details",
        subtitle: "Team information and members.",
    },
    "admin.team.create": { title: "Create Team", subtitle: "Add a new team to the organization." },
    "admin.team.edit": {
        title: "Edit Team",
        subtitle: "Update team information.",
    },
    "admin.staffMembers": {
        title: "Staff Members",
        subtitle: "Data and profiles for all staff members.",
    },
    "admin.staffMembers.create": {
        title: "Add Staff Member",
        subtitle: "Register a new staff member.",
    },
    "admin.staffMembers.edit": {
        title: "Edit Staff Member",
        subtitle: "Update staff member data.",
    },
    "admin.staffMembers.detail": {
        title: "Staff Profile",
        subtitle: "Complete staff member details.",
    },
    "admin.staffMembers.success": {
        title: "Staff Member Added",
        subtitle: "Staff member data has been saved.",
    },
    "admin.attendances": {
        title: "Attendance",
        subtitle: "Attendance, clock-in, and clock-out summary.",
    },
    "admin.attendance.settings": {
        title: "Attendance Settings",
        subtitle: "Configure attendance policies and rules.",
    },
    "admin.attendance.periods": {
        title: "Attendance Periods",
        subtitle: "Manage attendance tracking periods.",
    },
    "admin.attendance.mismatches": {
        title: "Policy Mismatches",
        subtitle: "Review attendance policy violations.",
    },
    "admin.attendance.corrections": {
        title: "Attendance Corrections",
        subtitle: "Review and approve attendance adjustments.",
    },
    "admin.attendance.records": {
        title: "Attendance Records",
        subtitle: "Detailed attendance logs and history.",
    },
    "admin.attendance.leave-requests": {
        title: "Leave Requests",
        subtitle: "Approve or reject employee leave requests.",
    },
    "admin.attendance.holidays": {
        title: "Holiday Calendar",
        subtitle: "Manage company holidays and observances.",
    },
    "admin.attendance.hybrid-schedules": {
        title: "Hybrid Schedules",
        subtitle: "Configure remote and office work schedules.",
    },
    "admin.attendance.overtime": {
        title: "Overtime Management",
        subtitle: "Track and approve overtime requests.",
    },
    "admin.projects": {
        title: "Projects",
        subtitle: "Active and archived projects.",
    },
    "admin.projects.create": {
        title: "Create Project",
        subtitle: "Start a new project.",
    },
    "admin.projects.edit": {
        title: "Edit Project",
        subtitle: "Update project details.",
    },
    "admin.projects.detail": {
        title: "Project Details",
        subtitle: "Progress, tasks, and team members.",
    },
    "admin.performance.cycles": {
        title: "Review Cycles",
        subtitle: "Manage performance review cycles.",
    },
    "admin.performance.cycles.create": {
        title: "Create Review Cycle",
        subtitle: "Set up a new performance review cycle.",
    },
    "admin.performance.cycles.detail": {
        title: "Review Cycle Details",
        subtitle: "View cycle progress and participants.",
    },
    "admin.performance.outcome-rules": {
        title: "Outcome Rules",
        subtitle: "Configure performance rating rules.",
    },
    "admin.performance.templates": {
        title: "Review Templates",
        subtitle: "Manage performance review templates.",
    },
    "admin.performance.my-reviews": {
        title: "My Reviews",
        subtitle: "Performance reviews assigned to you.",
    },
    "admin.performance.team-reviews": {
        title: "Team Reviews",
        subtitle: "Review performance submissions for your team.",
    },
    "admin.performance.pending-calibration": {
        title: "Pending Calibration",
        subtitle: "Reviews awaiting calibration.",
    },
    "admin.performance.review.detail": {
        title: "Review Details",
        subtitle: "View and complete performance review.",
    },
    "admin.performance.my-goals": {
        title: "My Goals",
        subtitle: "Track your performance goals.",
    },
    "admin.performance.team-goals": {
        title: "Team Goals",
        subtitle: "Monitor team performance objectives.",
    },
    "admin.performance.goal.detail": {
        title: "Goal Details",
        subtitle: "Track goal progress and updates.",
    },
    "admin.performance.feedback.received": {
        title: "Feedback Received",
        subtitle: "View feedback from colleagues.",
    },
    "admin.performance.feedback.given": {
        title: "Feedback Given",
        subtitle: "Feedback you have provided.",
    },
    "admin.performance.feedback.give": {
        title: "Give Feedback",
        subtitle: "Provide feedback to a colleague.",
    },
    "admin.payroll.dashboard": {
        title: "Payroll",
        subtitle: "Process and review employee payroll history.",
    },
    "admin.payroll.readiness": {
        title: "Payroll Readiness",
        subtitle: "Check payroll data completeness before generation.",
    },
    "admin.payroll.create": {
        title: "Create Payroll",
        subtitle: "Generate payroll for the current period.",
    },
    "admin.payroll.settings": {
        title: "Payroll Settings",
        subtitle: "Configure payroll rules and rates.",
    },
    "admin.payroll.approval-matrix": {
        title: "Approval Matrix",
        subtitle: "Manage payroll approval rules.",
    },
    "admin.payroll.adjustments": {
        title: "Payroll Adjustments",
        subtitle: "Review payroll additions and deductions.",
    },
    "admin.payroll.comparison": {
        title: "Payroll Comparison",
        subtitle: "Compare payroll changes across periods.",
    },
    "admin.payroll.thr": {
        title: "THR Payroll",
        subtitle: "Manage holiday allowance payroll.",
    },
    "admin.payroll.thr.detail": {
        title: "THR Details",
        subtitle: "Review holiday allowance payroll details.",
    },
    "admin.payroll.detail": {
        title: "Payroll Details",
        subtitle: "Salary components and deduction details.",
    },
    "admin.upgrade-plan": {
        title: "Upgrade Plan",
        subtitle: "Review available plan options.",
    },
    "staffMember.profile": {
        title: "My Profile",
        subtitle: "Personal data and job information.",
    },
    "staffMember.profile.edit": {
        title: "Edit Profile",
        subtitle: "Update personal information.",
    },
    "staffMember.team": { title: "My Team", subtitle: "Team members and contacts." },
    "staffMember.attendance.my-attendances": {
        title: "My Attendance",
        subtitle: "Attendance, permissions, and leave summary.",
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
const unreadBadgeText = computed(() => formatUnreadCount(unreadNotificationCount.value, isEmployeeUser.value ? 9 : 99));
const unreadBadgeClass = computed(() => {
    const safeCount = Number.isFinite(unreadNotificationCount.value)
        ? Math.max(0, Math.floor(unreadNotificationCount.value))
        : 0;
    const sizeClass = safeCount > 9 ? "w-6 px-1" : "w-5";

    // Standardized red badge for all roles
    return `absolute -right-1 -top-1 flex h-5 ${sizeClass} items-center justify-center rounded-full border-2 border-white bg-danger-500 text-[10px] font-bold leading-none text-white`;
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

const handleMarkAllRead = async () => {
    await notificationStore.markAllAsRead();
};

const handleNotificationSelect = async (notification) => {
    if (!notification?.id) {
        return;
    }

    await notificationStore.markNotificationAsRead(notification.id);
    await fetchUnreadCount();
    isNotificationPanelOpen.value = false;

    const actionUrl = typeof notification.action_url === "string" ? notification.action_url.trim() : "";

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

    unreadPollingIntervalId.value = window.setInterval(async () => {
        await fetchUnreadCount();
        if (unreadNotificationCount.value > previousUnreadCount.value && previousUnreadCount.value > 0) {
            toastInfo("New Notification", "You have new notifications");
        }
        previousUnreadCount.value = unreadNotificationCount.value;
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
    if (accountDropdownRef.value && !accountDropdownRef.value.contains(event.target)) {
        isAccountMenuOpen.value = false;
    }

    if (notificationDropdownRef.value && !notificationDropdownRef.value.contains(event.target)) {
        isNotificationPanelOpen.value = false;
    }
};

onMounted(async () => {
    document.addEventListener("click", handleClickOutside);
    document.addEventListener("visibilitychange", handleVisibilityChange);
    await fetchUnreadCount();
    previousUnreadCount.value = unreadNotificationCount.value;
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
        class="page-header border-b border-brand-border px-4 sm:px-6 py-3.5 sm:py-4 transition-colors duration-200"
        style="background-color: var(--header-bg)"
    >
        <div class="flex items-start sm:items-center justify-between gap-3 sm:gap-4">
            <div class="min-w-0 flex items-start sm:items-center gap-3 sm:gap-4">
                <button
                    type="button"
                    aria-label="Toggle sidebar"
                    data-testid="header-mobile-menu-toggle"
                    class="lg:hidden w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                    @click="emit('toggle-sidebar')"
                >
                    <MenuIcon class="w-5 h-5 text-gray-600" />
                </button>
                <div class="min-w-0">
                    <h1
                        data-testid="page-title"
                        class="text-brand-dark text-xl sm:text-2xl font-extrabold leading-tight truncate"
                    >
                        {{ pageTitle }}
                    </h1>
                    <p class="hidden md:block text-brand-light text-sm font-normal leading-snug mt-1 truncate">
                        {{ pageSubtitle }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                <!-- Action Buttons -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Dark Mode Toggle -->
                    <button
                        type="button"
                        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                        class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                        @click="toggleDark"
                    >
                        <SunIcon v-if="isDark" class="w-5 h-5 text-brand-light" />
                        <MoonIcon v-else class="w-5 h-5 text-brand-light" />
                    </button>

                    <div class="relative z-50" ref="notificationDropdownRef">
                        <button
                            type="button"
                            data-testid="header-notification-toggle"
                            :aria-label="notificationButtonLabel"
                            aria-haspopup="dialog"
                            :aria-expanded="isNotificationPanelOpen ? 'true' : 'false'"
                            :aria-controls="notificationPanelId"
                            class="relative w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
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
                            :marking-all-read="notificationStore.markingAllRead"
                            @retry="fetchLatestNotifications"
                            @select="handleNotificationSelect"
                            @mark-all-read="handleMarkAllRead"
                            @close="isNotificationPanelOpen = false"
                        />
                    </div>
                    <button
                        type="button"
                        aria-label="Messages"
                        class="hidden sm:flex w-10 h-10 rounded-full border border-brand-border items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                    >
                        <MessageCircleIcon class="w-5 h-5 text-gray-600" />
                    </button>
                </div>

                <!-- Divider -->
                <div class="hidden sm:block w-px h-8 bg-brand-border mx-5"></div>

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
                            loading="eager"
                            :src="user?.profile_photo"
                            alt="User Avatar"
                            class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover"
                            v-if="user?.profile_photo"
                        />
                        <div
                            class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center bg-gray-100"
                            v-else
                        >
                            <UserIcon class="w-5 h-5 text-gray-400" />
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-brand-dark text-sm sm:text-base font-semibold">
                                {{ user?.name }}
                            </p>
                            <p class="text-brand-dark text-sm sm:text-base font-normal leading-7">
                                {{ join(user?.roles, ", ").toUpperCase() }}
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
                        class="absolute right-0 top-full mt-2 w-56 border border-brand-border rounded-lg shadow-md py-2 z-[9999] transition-colors duration-200"
                        style="background-color: var(--color-surface)"
                        :class="{ hidden: !isAccountMenuOpen }"
                    >
                        <div class="px-4 py-3 border-b border-brand-border">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ user?.name }}
                            </p>
                            <p class="text-xs text-gray-600">{{ user?.email }}</p>
                            <p class="text-xs text-gray-500">
                                {{ join(user?.roles, ", ").toUpperCase() }}
                            </p>
                        </div>

                        <div class="py-1">
                            <RouterLink
                                :to="{ name: 'staffMember.profile' }"
                                role="menuitem"
                                data-testid="header-profile-menu-item"
                                class="flex items-center gap-3 px-4 py-2 text-sm text-brand-dark hover:bg-surface-overlay transition-colors cursor-pointer"
                                @click="isAccountMenuOpen = false"
                            >
                                <UserIcon class="w-4 h-4" />
                                Profile
                            </RouterLink>
                        </div>

                        <div class="border-t border-brand-border py-1">
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
