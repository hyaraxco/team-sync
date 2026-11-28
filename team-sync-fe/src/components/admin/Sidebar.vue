<script setup>
import {
    BuildingIcon,
    HomeIcon,
    FileTextIcon,
    UsersIcon,
    CrownIcon,
    ArrowRightIcon,
    CalendarIcon,
    WalletIcon,
    FileWarningIcon,
    BarChart3Icon,
    XIcon,
    SettingsIcon,
    TrendingUpIcon,
    TargetIcon,
    MessageSquareIcon,
    StarIcon,
    ScaleIcon,
    AwardIcon,
    VideoIcon,
    PanelLeftIcon,
    UserIcon,
} from "lucide-vue-next";

import { can, canOneOf } from "@/helpers/permissionHelper";
import { useSidebar } from "@/composables/useSidebar";
import { RouterLink, useRoute } from "vue-router";
import { computed } from "vue";

const { isOpen, isCollapsed, toggleCollapse, closeMobile } = useSidebar();
const route = useRoute();

const onNavigate = () => closeMobile();

const isActiveRoute = (item) => {
    const name = route.name;
    if (!name) return false;

    if (item.activeRoutes) {
        return item.activeRoutes.some((r) => name === r);
    }
    if (item.activePrefix) {
        return name.startsWith(item.activePrefix);
    }
    return name === item.route;
};

const isItemVisible = (item) => {
    if (item.permission) return can(item.permission);
    if (item.anyPermissions) return canOneOf(item.anyPermissions);
    return true;
};

const isSectionVisible = (section) => {
    if (section.permission) return can(section.permission);
    if (section.anyPermissions) return canOneOf(section.anyPermissions);
    return section.items.some(isItemVisible);
};

const navSections = [
    {
        key: "general",
        label: "GENERAL",
        items: [
            {
                route: "admin.dashboard",
                label: "Dashboard",
                icon: HomeIcon,
                permission: "dashboard-menu",
            },
            {
                route: "admin.projects",
                label: "Projects",
                icon: FileTextIcon,
                permission: "project-menu",
                activePrefix: "admin.project",
            },
            {
                route: "admin.staffMembers",
                label: "Employees",
                icon: UsersIcon,
                permission: "staff-member-menu",
                activePrefix: "admin.staffMember",
            },
            {
                route: "admin.teams",
                label: "Our Teams",
                icon: UsersIcon,
                permission: "team-menu",
                activePrefix: "admin.team",
            },
            {
                route: "admin.meetings",
                label: "Meetings",
                icon: VideoIcon,
                permission: "meeting-menu",
                activePrefix: "admin.meeting",
            },
            {
                route: "admin.attendances",
                label: "Attendance",
                icon: CalendarIcon,
                permission: "attendance-menu",
            },
            {
                route: "admin.payroll.dashboard",
                label: "Payroll",
                icon: WalletIcon,
                permission: "payroll-menu",
                activePrefix: "admin.payroll",
                activeExclude: ["admin.payroll.adjustments"],
            },
            {
                route: "admin.payroll.adjustments",
                label: "Payroll Adjustments",
                icon: FileWarningIcon,
                permission: "payroll-menu",
            },
            {
                route: "admin.analytics",
                label: "Analytics",
                icon: BarChart3Icon,
                permission: "analytics-menu",
                activePrefix: "admin.analytics",
            },
        ],
    },
    {
        key: "performance",
        label: "PERFORMANCE",
        permission: "performance-menu",
        items: [
            {
                route: "admin.performance.team-reviews",
                label: "Team Reviews",
                icon: UsersIcon,
                permission: "review-manager-submit",
            },
            {
                route: "admin.performance.pending-calibration",
                label: "Pending Calibration",
                icon: ScaleIcon,
                permission: "review-calibrate",
            },
            {
                route: "admin.performance.cycles",
                label: "Review Cycles",
                icon: CalendarIcon,
                permission: "review-cycle-manage",
                activePrefix: "admin.performance.cycles",
            },
            {
                route: "admin.performance.outcome-rules",
                label: "Outcome Rules",
                icon: AwardIcon,
                permission: "review-cycle-manage",
            },
            {
                route: "admin.performance.templates",
                label: "Review Templates",
                icon: FileTextIcon,
                permission: "review-cycle-manage",
            },
            {
                route: "admin.performance.my-reviews",
                label: "My Reviews",
                icon: StarIcon,
            },
            {
                route: "admin.performance.team-goals",
                label: "Team Goals",
                icon: TrendingUpIcon,
                permission: "goal-assign-team",
            },
            {
                route: "admin.performance.my-goals",
                label: "My Goals",
                icon: TargetIcon,
            },
            {
                route: "admin.performance.feedback.received",
                label: "Feedback",
                icon: MessageSquareIcon,
                activeRoutes: [
                    "admin.performance.feedback.received",
                    "admin.performance.feedback.given",
                ],
            },
        ],
    },
    {
        key: "personal",
        label: "PERSONAL",
        anyPermissions: [
            "profile-menu",
            "payslip-view",
            "team-view",
            "attendance-my-attendances",
            "attendance-check-in",
            "attendance-check-out",
        ],
        items: [
            {
                route: "staffMember.attendance.my-attendances",
                label: "My Attendance",
                icon: CalendarIcon,
                anyPermissions: [
                    "attendance-my-attendances",
                    "attendance-check-in",
                    "attendance-check-out",
                ],
                activeRoutes: [
                    "staffMember.attendance.my-attendances",
                    "staffMember.attendance.clock",
                ],
            },
            {
                route: "staffMember.team",
                label: "My Team",
                icon: UsersIcon,
                permission: "team-view",
            },
            {
                route: "staffMember.payroll",
                label: "My Payroll",
                icon: WalletIcon,
                permission: "payslip-view",
                activeRoutes: ["staffMember.payroll", "staffMember.payroll.detail"],
            },
            {
                route: "staffMember.profile",
                label: "My Profile",
                icon: UserIcon,
                permission: "profile-menu",
                activeRoutes: ["staffMember.profile", "staffMember.profile.edit"],
            },
        ],
    },
    {
        key: "preferences",
        label: "PREFERENCES",
        items: [
            {
                route: "admin.settings",
                label: "Settings",
                icon: SettingsIcon,
                anyPermissions: [
                    "settings-hr-manage",
                    "settings-finance-manage",
                    "settings-system-manage",
                ],
            },
        ],
    },
];

const isPayrollActive = (item) => {
    const name = route.name;
    if (!name) return false;
    if (item.activeExclude && item.activePrefix) {
        return name.startsWith(item.activePrefix) && !item.activeExclude.includes(name);
    }
    return isActiveRoute(item);
};

const isActive = (item) => {
    if (item.activeExclude) return isPayrollActive(item);
    return isActiveRoute(item);
};

const visibleSections = computed(() =>
    navSections
        .filter(isSectionVisible)
        .map((section) => ({
            ...section,
            items: section.items.filter(isItemVisible),
        }))
        .filter((section) => section.items.length > 0),
);
</script>

<template>
    <!-- Sidebar -->
    <aside
        id="sidebar"
        :role="isOpen ? 'dialog' : undefined"
        :aria-modal="isOpen ? 'true' : undefined"
        aria-label="Main sidebar"
        class="fixed lg:relative inset-y-0 left-0 z-50 border-r flex flex-col transform transition-all duration-200 ease-in-out"
        style="background-color: var(--sidebar-bg); border-color: var(--sidebar-border)"
        :style="{ width: isCollapsed ? '68px' : '256px', minWidth: isCollapsed ? '68px' : '256px' }"
        :class="[
            isOpen ? 'translate-x-0' : '-translate-x-full',
            'lg:translate-x-0',
            isCollapsed ? 'sidebar-collapsed' : '',
        ]"
    >
        <!-- Logo Section -->
        <div
            class="border-b border-brand-border flex transition-all duration-300 shrink-0"
            :class="isCollapsed ? 'px-3 py-3 flex-col items-center gap-2' : 'px-6 py-3 items-center justify-between'"
        >
            <div class="flex items-center" :class="isCollapsed ? '' : 'gap-4'">
                <div
                    class="relative flex items-center justify-center shrink-0"
                    :class="isCollapsed ? 'w-10 h-10' : 'w-14 h-14'"
                >
                    <div
                        class="absolute bg-gradient-to-br from-primary-100 to-primary-200 rounded-full transition-all duration-300"
                        :class="isCollapsed ? 'w-10 h-10' : 'w-14 h-14'"
                    ></div>
                    <div
                        class="absolute bg-brand-primary rounded-full opacity-90 transition-all duration-300"
                        :class="isCollapsed ? 'w-7 h-7' : 'w-10 h-10'"
                    ></div>
                    <BuildingIcon
                        class="text-white relative z-10 transition-all duration-300"
                        :class="isCollapsed ? 'w-3.5 h-3.5' : 'w-5 h-5'"
                    />
                </div>
                <div
                    class="overflow-hidden transition-all duration-300"
                    :class="isCollapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
                >
                    <h1 class="text-brand-dark text-lg font-bold whitespace-nowrap">Team Sync Pro</h1>
                    <p class="text-brand-dark text-xs font-normal whitespace-nowrap">HRIS Dashboard</p>
                </div>
            </div>
            <!-- Mobile: close button -->
            <button
                type="button"
                aria-label="Close sidebar"
                class="lg:hidden w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                @click="onNavigate"
            >
                <XIcon class="w-5 h-5 text-brand-light" />
            </button>
            <!-- Desktop: collapse toggle -->
            <button
                type="button"
                aria-label="Toggle sidebar"
                class="hidden lg:flex w-8 h-8 rounded-lg border border-brand-border items-center justify-center hover:bg-surface-overlay hover:border-brand-border transition-all duration-200 shrink-0"
                @click="toggleCollapse"
            >
                <PanelLeftIcon
                    class="w-4 h-4 text-brand-light transition-transform duration-300"
                    :class="{ 'rotate-180': isCollapsed }"
                />
            </button>
        </div>

        <!-- Navigation Menu -->
        <nav
            aria-label="Main navigation"
            class="py-4 flex-1 transition-all duration-300"
            :class="isCollapsed ? 'px-2 space-y-2 overflow-visible' : 'px-6 space-y-6 overflow-y-auto scrollbar-hide'"
        >
            <div
                v-for="section in visibleSections"
                :key="section.key"
                :data-testid="`sidebar-section-${section.key}`"
            >
                <h3 v-show="!isCollapsed" class="section-title">{{ section.label }}</h3>
                <div class="space-y-3">
                    <RouterLink
                        v-for="item in section.items"
                        :key="item.route"
                        :to="{ name: item.route }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus-visible:ring-2 focus-visible:ring-brand-primary transition-all duration-300"
                        :class="{ 'nav-link-active': isActive(item) }"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        @click="onNavigate"
                    >
                        <component
                            :is="item.icon"
                            class="w-5 h-5 text-brand-light"
                            :class="{ 'text-white': isActive(item) }"
                        />
                        <span
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': isActive(item) }"
                        >
                            {{ item.label }}
                        </span>
                    </RouterLink>
                </div>
            </div>
        </nav>

        <!-- Upgrade to Pro Box -->
        <div v-show="!isCollapsed" class="px-6 pb-6 mt-auto">
            <div
                class="upgrade-card bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-2xl relative overflow-hidden p-5"
            >
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-5">
                    <div class="absolute top-2 right-4 w-8 h-8 bg-blue-500 rounded-full"></div>
                    <div class="absolute bottom-4 left-2 w-6 h-6 bg-blue-400 rounded-full"></div>
                    <div class="absolute top-1/2 left-1/2 w-4 h-4 bg-blue-600 rounded-full"></div>
                </div>

                <div class="relative z-10">
                    <!-- Icon -->
                    <div
                        class="w-10 h-10 bg-brand-primary rounded-xl flex items-center justify-center mb-3"
                    >
                        <CrownIcon class="w-5 h-5 text-white" />
                    </div>

                    <!-- Content -->
                    <h4 class="text-brand-dark text-base font-bold mb-1">Team Sync Pro</h4>
                    <p class="text-brand-dark text-sm font-normal leading-5 mb-4">
                        Multi-tenancy, SSO, dan laporan lanjutan.
                    </p>

                    <!-- CTA Button -->
                    <RouterLink
                        :to="{ name: 'admin.upgrade-plan' }"
                        @click="onNavigate"
                        class="flex items-center justify-center w-full rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3"
                    >
                        <span class="text-brand-white text-sm font-semibold mr-2">Upgrade Now</span>
                        <ArrowRightIcon class="w-4 h-4 text-white" aria-hidden="true" />
                    </RouterLink>
                </div>
            </div>
        </div>
    </aside>
</template>
