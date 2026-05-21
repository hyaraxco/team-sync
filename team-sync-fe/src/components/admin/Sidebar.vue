<script setup>
import {
    BuildingIcon,
    HomeIcon,
    FileTextIcon,
    UsersIcon,
    CrownIcon,
    ArrowRightIcon,
    UserIcon,
    CalendarIcon,
    Clock3Icon,
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
} from "lucide-vue-next";

import { can, canOneOf } from "@/helpers/permissionHelper";
import { useSidebar } from "@/composables/useSidebar";
import { RouterLink } from "vue-router";

const { isOpen, isCollapsed, toggleCollapse, closeMobile } = useSidebar();

const onNavigate = () => closeMobile();
</script>

<template>
    <!-- Mobile Overlay handled by layout -->

    <!-- Sidebar -->
    <aside
        id="sidebar"
        class="fixed lg:relative inset-y-0 left-0 z-50 bg-white border-r border-gray-200 flex flex-col transform transition-all duration-300 ease-in-out"
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
                <XIcon class="w-5 h-5 text-gray-600" />
            </button>
            <!-- Desktop: collapse toggle -->
            <button
                type="button"
                aria-label="Toggle sidebar"
                class="hidden lg:flex w-8 h-8 rounded-lg border border-gray-200 items-center justify-center hover:bg-gray-100 hover:border-gray-300 transition-all duration-200 shrink-0"
                @click="toggleCollapse"
            >
                <PanelLeftIcon
                    class="w-4 h-4 text-gray-500 transition-transform duration-300"
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
            <!-- GENERAL Section -->
            <div data-testid="sidebar-section-general">
                <h3 v-show="!isCollapsed" class="section-title">GENERAL</h3>
                <div class="space-y-3">
                    <!-- 1. Dashboard (all roles) -->
                    <RouterLink
                        :to="{ name: 'admin.dashboard' }"
                        :class="{
                            'nav-link-active': $route.name === 'admin.dashboard',
                        }"
                        :aria-current="$route.name === 'admin.dashboard' ? 'page' : undefined"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        v-if="can('dashboard-menu')"
                        data-tooltip="Dashboard"
                        @click="onNavigate"
                    >
                        <HomeIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'admin.dashboard',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'admin.dashboard',
                            }"
                        >
                            Dashboard
                        </span>
                    </RouterLink>

                    <!-- 2. Projects (employee daily, manager daily) -->
                    <RouterLink
                        :to="{ name: 'admin.projects' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name?.startsWith('admin.project'),
                        }"
                        :aria-current="$route.name?.startsWith('admin.project') ? 'page' : undefined"
                        v-if="can('project-menu')"
                        data-tooltip="Projects"
                        @click="onNavigate"
                    >
                        <FileTextIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name?.startsWith('admin.project'),
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name?.startsWith('admin.project'),
                            }"
                        >
                            Projects
                        </span>
                    </RouterLink>

                    <!-- 3. Employees (HR/Manager core) -->
                    <RouterLink
                        :to="{ name: 'admin.staffMembers' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name?.startsWith('admin.staffMember'),
                        }"
                        :aria-current="$route.name?.startsWith('admin.staffMember') ? 'page' : undefined"
                        v-if="can('staff-member-menu')"
                        data-tooltip="Employees"
                        @click="onNavigate"
                    >
                        <UsersIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name?.startsWith('admin.staffMember'),
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name?.startsWith('admin.staffMember'),
                            }"
                        >
                            Employees
                        </span>
                    </RouterLink>

                    <!-- 4. Our Teams (HR/Manager) -->
                    <RouterLink
                        :to="{ name: 'admin.teams' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name?.startsWith('admin.team'),
                        }"
                        :aria-current="$route.name?.startsWith('admin.team') ? 'page' : undefined"
                        v-if="can('team-menu')"
                        data-tooltip="Our Teams"
                        @click="onNavigate"
                    >
                        <UsersIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name?.startsWith('admin.team'),
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name?.startsWith('admin.team'),
                            }"
                        >
                            Our Teams
                        </span>
                    </RouterLink>

                    <!-- 5. Meetings -->
                    <RouterLink
                        :to="{ name: 'admin.meetings' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name?.startsWith('admin.meeting'),
                        }"
                        :aria-current="$route.name?.startsWith('admin.meeting') ? 'page' : undefined"
                        v-if="can('meeting-menu')"
                        data-tooltip="Meetings"
                        @click="onNavigate"
                    >
                        <VideoIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name?.startsWith('admin.meeting'),
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name?.startsWith('admin.meeting'),
                            }"
                        >
                            Meetings
                        </span>
                    </RouterLink>

                    <!-- 6. Attendance (HR/Manager admin) -->
                    <RouterLink
                        :to="{ name: 'admin.attendances' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.attendances',
                        }"
                        :aria-current="$route.name === 'admin.attendances' ? 'page' : undefined"
                        v-if="can('attendance-menu')"
                        data-tooltip="Attendance"
                        @click="onNavigate"
                    >
                        <CalendarIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'admin.attendances',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'admin.attendances',
                            }"
                        >
                            Attendance
                        </span>
                    </RouterLink>

                    <!-- 6. Payroll (HR/Finance core) -->
                    <RouterLink
                        :to="{ name: 'admin.payroll.dashboard' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active':
                                $route.name?.startsWith('admin.payroll') && $route.name !== 'admin.payroll.adjustments',
                        }"
                        :aria-current="$route.name?.startsWith('admin.payroll') && $route.name !== 'admin.payroll.adjustments' ? 'page' : undefined"
                        v-if="can('payroll-menu')"
                        data-tooltip="Payroll"
                        @click="onNavigate"
                    >
                        <WalletIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white':
                                    $route.name?.startsWith('admin.payroll') &&
                                    $route.name !== 'admin.payroll.adjustments',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white':
                                    $route.name?.startsWith('admin.payroll') &&
                                    $route.name !== 'admin.payroll.adjustments',
                            }"
                        >
                            Payroll
                        </span>
                    </RouterLink>

                    <RouterLink
                        :to="{ name: 'admin.payroll.adjustments' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.payroll.adjustments',
                        }"
                        :aria-current="$route.name === 'admin.payroll.adjustments' ? 'page' : undefined"
                        v-if="can('payroll-menu')"
                        data-tooltip="Payroll Adjustments"
                        @click="onNavigate"
                    >
                        <FileWarningIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'admin.payroll.adjustments',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'admin.payroll.adjustments',
                            }"
                        >
                            Payroll Adjustments
                        </span>
                    </RouterLink>

                    <!-- 7. Analytics (periodic) -->
                    <RouterLink
                        :to="{ name: 'admin.analytics' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name?.startsWith('admin.analytics'),
                        }"
                        :aria-current="$route.name?.startsWith('admin.analytics') ? 'page' : undefined"
                        v-if="can('analytics-menu')"
                        data-tooltip="Analytics"
                        @click="onNavigate"
                    >
                        <BarChart3Icon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name?.startsWith('admin.analytics'),
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name?.startsWith('admin.analytics'),
                            }"
                        >
                            Analytics
                        </span>
                    </RouterLink>
                </div>
            </div>

            <!-- PERFORMANCE Section -->
            <div v-if="can('performance-menu')" data-testid="sidebar-section-performance">
                <h3 v-show="!isCollapsed" class="section-title">PERFORMANCE</h3>
                <div class="space-y-3">
                    <!-- 1. Team Reviews (Manager/HR primary action) -->
                    <RouterLink
                        v-if="can('review-manager-submit')"
                        :to="{ name: 'admin.performance.team-reviews' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.team-reviews',
                        }"
                        :aria-current="$route.name === 'admin.performance.team-reviews' ? 'page' : undefined"
                        data-tooltip="Team Reviews"
                        @click="onNavigate"
                    >
                        <UsersIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.team-reviews' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.team-reviews' }"
                        >
                            Team Reviews
                        </span>
                    </RouterLink>

                    <!-- 2. Pending Calibration (HR calibrates) -->
                    <RouterLink
                        v-if="can('review-calibrate')"
                        :to="{ name: 'admin.performance.pending-calibration' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.pending-calibration',
                        }"
                        :aria-current="$route.name === 'admin.performance.pending-calibration' ? 'page' : undefined"
                        data-tooltip="Pending Calibration"
                        @click="onNavigate"
                    >
                        <ScaleIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.pending-calibration' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.pending-calibration' }"
                        >
                            Pending Calibration
                        </span>
                    </RouterLink>

                    <!-- 3. Review Cycles (HR manages) -->
                    <RouterLink
                        v-if="can('review-cycle-manage')"
                        :to="{ name: 'admin.performance.cycles' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name?.startsWith('admin.performance.cycles'),
                        }"
                        :aria-current="$route.name?.startsWith('admin.performance.cycles') ? 'page' : undefined"
                        data-tooltip="Review Cycles"
                        @click="onNavigate"
                    >
                        <CalendarIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name?.startsWith('admin.performance.cycles') }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name?.startsWith('admin.performance.cycles') }"
                        >
                            Review Cycles
                        </span>
                    </RouterLink>

                    <RouterLink
                        v-if="can('review-cycle-manage')"
                        :to="{ name: 'admin.performance.outcome-rules' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.outcome-rules',
                        }"
                        :aria-current="$route.name === 'admin.performance.outcome-rules' ? 'page' : undefined"
                        data-tooltip="Outcome Rules"
                        @click="onNavigate"
                    >
                        <AwardIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.outcome-rules' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.outcome-rules' }"
                        >
                            Outcome Rules
                        </span>
                    </RouterLink>

                    <RouterLink
                        v-if="can('review-cycle-manage')"
                        :to="{ name: 'admin.performance.templates' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.templates',
                        }"
                        :aria-current="$route.name === 'admin.performance.templates' ? 'page' : undefined"
                        data-tooltip="Review Templates"
                        @click="onNavigate"
                    >
                        <FileTextIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.templates' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.templates' }"
                        >
                            Review Templates
                        </span>
                    </RouterLink>

                    <!-- 3. My Reviews (personal) -->
                    <RouterLink
                        :to="{ name: 'admin.performance.my-reviews' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.my-reviews',
                        }"
                        data-tooltip="My Reviews"
                        @click="onNavigate"
                    >
                        <StarIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.my-reviews' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.my-reviews' }"
                        >
                            My Reviews
                        </span>
                    </RouterLink>

                    <!-- 4. Team Goals (Manager/HR) -->
                    <RouterLink
                        v-if="can('goal-assign-team')"
                        :to="{ name: 'admin.performance.team-goals' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.team-goals',
                        }"
                        data-tooltip="Team Goals"
                        @click="onNavigate"
                    >
                        <TrendingUpIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.team-goals' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.team-goals' }"
                        >
                            Team Goals
                        </span>
                    </RouterLink>

                    <!-- 5. My Goals (personal) -->
                    <RouterLink
                        :to="{ name: 'admin.performance.my-goals' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.performance.my-goals',
                        }"
                        data-tooltip="My Goals"
                        @click="onNavigate"
                    >
                        <TargetIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{ 'text-white': $route.name === 'admin.performance.my-goals' }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{ 'text-brand-white': $route.name === 'admin.performance.my-goals' }"
                        >
                            My Goals
                        </span>
                    </RouterLink>

                    <!-- 6. Feedback (least frequent) -->
                    <RouterLink
                        :to="{ name: 'admin.performance.feedback.received' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active':
                                $route.name === 'admin.performance.feedback.received' ||
                                $route.name === 'admin.performance.feedback.given',
                        }"
                        data-tooltip="Feedback"
                        @click="onNavigate"
                    >
                        <MessageSquareIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white':
                                    $route.name === 'admin.performance.feedback.received' ||
                                    $route.name === 'admin.performance.feedback.given',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white':
                                    $route.name === 'admin.performance.feedback.received' ||
                                    $route.name === 'admin.performance.feedback.given',
                            }"
                        >
                            Feedback
                        </span>
                    </RouterLink>
                </div>
            </div>

            <!-- PERSONAL Section -->
            <div
                v-if="
                    can('profile-menu') ||
                    can('payslip-view') ||
                    can('team-view') ||
                    canOneOf(['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'])
                "
                data-testid="sidebar-section-personal"
            >
                <h3 v-show="!isCollapsed" class="section-title">PERSONAL</h3>
                <div class="space-y-3">
                    <!-- 1. My Attendance (daily check-in/out) -->
                    <RouterLink
                        :to="{ name: 'staffMember.attendance.my-attendances' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active':
                                $route.name === 'staffMember.attendance.my-attendances' ||
                                $route.name === 'staffMember.attendance.clock',
                        }"
                        v-if="canOneOf(['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'])"
                        data-tooltip="My Attendance"
                        @click="onNavigate"
                    >
                        <CalendarIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white':
                                    $route.name === 'staffMember.attendance.my-attendances' ||
                                    $route.name === 'staffMember.attendance.clock',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white':
                                    $route.name === 'staffMember.attendance.my-attendances' ||
                                    $route.name === 'staffMember.attendance.clock',
                            }"
                        >
                            My Attendance
                        </span>
                    </RouterLink>

                    <!-- 2. My Overtime (payroll/attendance self service) -->
                    <RouterLink
                        :to="{ name: 'staffMember.attendance.my-overtime' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'staffMember.attendance.my-overtime',
                        }"
                        v-if="canOneOf(['attendance-my-attendances', 'overtime-list', 'overtime-create'])"
                        data-tooltip="My Overtime"
                        @click="onNavigate"
                    >
                        <Clock3Icon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'staffMember.attendance.my-overtime',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'staffMember.attendance.my-overtime',
                            }"
                        >
                            My Overtime
                        </span>
                    </RouterLink>

                    <!-- 3. My Team (frequent collaboration) -->
                    <RouterLink
                        :to="{ name: 'staffMember.team' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'staffMember.team',
                        }"
                        v-if="can('team-view')"
                        data-tooltip="My Team"
                        @click="onNavigate"
                    >
                        <UsersIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'staffMember.team',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'staffMember.team',
                            }"
                        >
                            My Team
                        </span>
                    </RouterLink>

                    <!-- 4. My Payroll (monthly) -->
                    <RouterLink
                        :to="{ name: 'staffMember.payroll' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active':
                                $route.name === 'staffMember.payroll' || $route.name === 'staffMember.payroll.detail',
                        }"
                        v-if="can('payslip-view')"
                        data-tooltip="My Payroll"
                        @click="onNavigate"
                    >
                        <WalletIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white':
                                    $route.name === 'staffMember.payroll' ||
                                    $route.name === 'staffMember.payroll.detail',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white':
                                    $route.name === 'staffMember.payroll' ||
                                    $route.name === 'staffMember.payroll.detail',
                            }"
                        >
                            My Payroll
                        </span>
                    </RouterLink>

                    <!-- 5. My Profile (rarely used) -->
                    <RouterLink
                        :to="{ name: 'staffMember.profile' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'staffMember.profile',
                        }"
                        v-if="can('profile-menu')"
                        data-tooltip="My Profile"
                        @click="onNavigate"
                    >
                        <UserIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'staffMember.profile',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'staffMember.profile',
                            }"
                        >
                            My Profile
                        </span>
                    </RouterLink>
                </div>
            </div>

            <!-- PREFERENCES Section -->
            <div data-testid="sidebar-section-preferences">
                <h3 v-show="!isCollapsed" class="section-title">PREFERENCES</h3>
                <div class="space-y-3">
                    <RouterLink
                        :to="{ name: 'admin.settings' }"
                        class="nav-link border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:bg-white transition-all duration-300"
                        :class="{
                            'nav-link-active': $route.name === 'admin.settings',
                        }"
                        v-if="canOneOf(['settings-hr-manage', 'settings-finance-manage', 'settings-system-manage'])"
                        data-tooltip="Settings"
                        @click="onNavigate"
                    >
                        <SettingsIcon
                            class="w-5 h-5 text-gray-600"
                            :class="{
                                'text-white': $route.name === 'admin.settings',
                            }"
                        />
                        <span
                            v-show="!isCollapsed"
                            class="text-brand-dark text-base font-medium"
                            :class="{
                                'text-brand-white': $route.name === 'admin.settings',
                            }"
                        >
                            Settings
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
                    <h4 class="text-brand-dark text-base font-bold mb-1">Upgrade to Pro</h4>
                    <p class="text-brand-dark text-sm font-normal leading-5 mb-4">
                        Unlock advanced features and insights
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
