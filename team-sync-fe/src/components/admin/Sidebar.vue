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
  WalletIcon,
  BarChart3Icon,
  XIcon,
  SettingsIcon,
  TrendingUpIcon,
  TargetIcon,
  MessageSquareIcon,
  StarIcon,
} from "lucide-vue-next";

import { can, canOneOf } from "@/helpers/permissionHelper";
import { RouterLink } from "vue-router";

const props = defineProps(["isOpen"]);
const emit = defineEmits(["navigate"]);

const onNavigate = () => emit("navigate");
</script>

<template>
  <!-- Mobile Overlay handled by layout -->

  <!-- Sidebar -->
  <aside
    id="sidebar"
    class="fixed lg:relative inset-y-0 left-0 z-50 w-64 bg-white/80 backdrop-blur-xl border-r border-gray-200/50 flex flex-col transform transition-all duration-300 ease-in-out"
    :class="[
      props.isOpen ? 'translate-x-0' : '-translate-x-full',
      'lg:translate-x-0',
    ]"
    data-collapsed="false"
  >
    <!-- Logo Section -->
    <div
      class="px-6 py-4 border-b border-[#DCDEDD] flex items-center justify-between"
    >
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 relative flex items-center justify-center">
          <!-- Background circle -->
          <div
            class="w-14 h-14 absolute bg-gradient-to-br from-primary-100 to-primary-200 rounded-full"
          ></div>
          <!-- Overlapping smaller circle -->
          <div
            class="w-10 h-10 absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full opacity-90"
          ></div>
          <!-- Lucide icon -->
          <BuildingIcon class="w-5 h-5 text-white relative z-10" />
        </div>
        <div>
          <h1 class="text-brand-dark text-lg font-bold">Team Sync Pro</h1>
          <p class="text-brand-dark text-xs font-normal">HRIS Dashboard</p>
        </div>
      </div>
      <button
        type="button"
        aria-label="Close sidebar"
        class="lg:hidden w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
        @click="onNavigate"
      >
        <XIcon class="w-5 h-5 text-gray-600" />
      </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="px-6 py-4 space-y-6">
      <!-- GENERAL Section -->
      <div data-testid="sidebar-section-general">
        <h3 class="section-title">GENERAL</h3>
        <div class="space-y-3">
          <!-- 1. Dashboard (all roles) -->
          <RouterLink
            :to="{ name: 'admin.dashboard' }"
            :class="{
              'nav-link-active': $route.name === 'admin.dashboard',
            }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            v-if="can('dashboard-menu')"
            @click="onNavigate"
          >
            <HomeIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name === 'admin.dashboard',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name === 'admin.dashboard',
              }"
              >Dashboard</span
            >
          </RouterLink>

          <!-- 2. Projects (employee daily, manager daily) -->
          <RouterLink
            :to="{ name: 'admin.projects' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.project'),
            }"
            v-if="can('project-menu')"
            @click="onNavigate"
          >
            <FileTextIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('admin.project'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name?.startsWith('admin.project'),
              }"
              >Projects</span
            >
          </RouterLink>

          <!-- 3. Employees (HR/Manager core) -->
          <RouterLink
            :to="{ name: 'admin.staffMembers' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.employee'),
            }"
            v-if="can('staff-member-menu')"
            @click="onNavigate"
          >
            <UsersIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('admin.employee'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name?.startsWith('admin.employee'),
              }"
              >Employees</span
            >
          </RouterLink>

          <!-- 4. Our Teams (HR/Manager) -->
          <RouterLink
            :to="{ name: 'admin.teams' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.team'),
            }"
            v-if="can('team-menu')"
            @click="onNavigate"
          >
            <UsersIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('admin.team'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name?.startsWith('admin.team'),
              }"
              >Our Teams</span
            >
          </RouterLink>

          <!-- 5. Attendance (HR/Manager admin) -->
          <RouterLink
            :to="{ name: 'admin.attendances' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'admin.attendances',
            }"
            v-if="can('attendance-menu')"
            @click="onNavigate"
          >
            <CalendarIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name === 'admin.attendances',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name === 'admin.attendances',
              }"
              >Attendance</span
            >
          </RouterLink>

          <!-- 6. Payroll (HR/Finance core) -->
          <RouterLink
            :to="{ name: 'admin.payroll.dashboard' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.payroll'),
            }"
            v-if="can('payroll-menu')"
            @click="onNavigate"
          >
            <WalletIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('admin.payroll'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name?.startsWith('admin.payroll'),
              }"
              >Payroll</span
            >
          </RouterLink>

          <!-- 7. Analytics (periodic) -->
          <RouterLink
            :to="{ name: 'admin.analytics' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.analytics'),
            }"
            v-if="can('analytics-menu')"
            @click="onNavigate"
          >
            <BarChart3Icon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('admin.analytics'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name?.startsWith('admin.analytics'),
              }"
              >Analytics</span
            >
          </RouterLink>
        </div>
      </div>

      <!-- PERFORMANCE Section -->
      <div
        v-if="can('performance-menu')"
        data-testid="sidebar-section-performance"
      >
        <h3 class="section-title">PERFORMANCE</h3>
        <div class="space-y-3">

          <!-- 1. Team Reviews (Manager/HR primary action) -->
          <RouterLink
            v-if="can('review-manager-submit')"
            :to="{ name: 'admin.performance.team-reviews' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'admin.performance.team-reviews',
            }"
            @click="onNavigate"
          >
            <UsersIcon
              class="w-5 h-5 text-gray-600"
              :class="{ 'text-white': $route.name === 'admin.performance.team-reviews' }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{ 'text-brand-white': $route.name === 'admin.performance.team-reviews' }"
              >Team Reviews</span
            >
          </RouterLink>

          <!-- 2. Review Cycles (HR manages) -->
          <RouterLink
            v-if="can('review-cycle-manage')"
            :to="{ name: 'admin.performance.cycles' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.performance.cycles'),
            }"
            @click="onNavigate"
          >
            <CalendarIcon
              class="w-5 h-5 text-gray-600"
              :class="{ 'text-white': $route.name?.startsWith('admin.performance.cycles') }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{ 'text-brand-white': $route.name?.startsWith('admin.performance.cycles') }"
              >Review Cycles</span
            >
          </RouterLink>

          <!-- 3. My Reviews (personal) -->
          <RouterLink
            :to="{ name: 'admin.performance.my-reviews' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'admin.performance.my-reviews',
            }"
            @click="onNavigate"
          >
            <StarIcon
              class="w-5 h-5 text-gray-600"
              :class="{ 'text-white': $route.name === 'admin.performance.my-reviews' }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{ 'text-brand-white': $route.name === 'admin.performance.my-reviews' }"
              >My Reviews</span
            >
          </RouterLink>

          <!-- 4. Team Goals (Manager/HR) -->
          <RouterLink
            v-if="can('goal-assign-team')"
            :to="{ name: 'admin.performance.team-goals' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'admin.performance.team-goals',
            }"
            @click="onNavigate"
          >
            <TrendingUpIcon
              class="w-5 h-5 text-gray-600"
              :class="{ 'text-white': $route.name === 'admin.performance.team-goals' }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{ 'text-brand-white': $route.name === 'admin.performance.team-goals' }"
              >Team Goals</span
            >
          </RouterLink>

          <!-- 5. My Goals (personal) -->
          <RouterLink
            :to="{ name: 'admin.performance.my-goals' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'admin.performance.my-goals',
            }"
            @click="onNavigate"
          >
            <TargetIcon
              class="w-5 h-5 text-gray-600"
              :class="{ 'text-white': $route.name === 'admin.performance.my-goals' }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{ 'text-brand-white': $route.name === 'admin.performance.my-goals' }"
              >My Goals</span
            >
          </RouterLink>

          <!-- 6. Feedback (least frequent) -->
          <RouterLink
            :to="{ name: 'admin.performance.feedback.received' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'admin.performance.feedback.received' || $route.name === 'admin.performance.feedback.given',
            }"
            @click="onNavigate"
          >
            <MessageSquareIcon
              class="w-5 h-5 text-gray-600"
              :class="{ 'text-white': $route.name === 'admin.performance.feedback.received' || $route.name === 'admin.performance.feedback.given' }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{ 'text-brand-white': $route.name === 'admin.performance.feedback.received' || $route.name === 'admin.performance.feedback.given' }"
              >Feedback</span
            >
          </RouterLink>

        </div>
      </div>

      <!-- PERSONAL Section -->
      <div
        v-if="can('profile-menu') || can('payslip-view') || can('team-view') || canOneOf(['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'])"
        data-testid="sidebar-section-personal"
      >
        <h3 class="section-title">PERSONAL</h3>
        <div class="space-y-3">

          <!-- 1. My Attendance (daily check-in/out) -->
          <RouterLink
            :to="{ name: 'staffMember.attendance.my-attendances' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active':
                $route.name === 'staffMember.attendance.my-attendances' ||
                $route.name === 'staffMember.attendance.clock',
            }"
            v-if="canOneOf(['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'])"
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
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white':
                  $route.name === 'staffMember.attendance.my-attendances' ||
                  $route.name === 'staffMember.attendance.clock',
              }"
              >My Attendance</span
            >
          </RouterLink>

          <!-- 2. My Team (frequent collaboration) -->
          <RouterLink
            :to="{ name: 'staffMember.team' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'staffMember.team',
            }"
            v-if="can('team-view')"
            @click="onNavigate"
          >
            <UsersIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name === 'staffMember.team',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name === 'staffMember.team',
              }"
              >My Team</span
            >
          </RouterLink>

          <!-- 3. My Payroll (monthly) -->
          <RouterLink
            :to="{ name: 'staffMember.payroll' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('staffMember.payroll') || $route.name?.startsWith('staffMember.payslips'),
            }"
            v-if="can('payslip-view')"
            @click="onNavigate"
          >
            <WalletIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('staffMember.payroll') || $route.name?.startsWith('staffMember.payslips'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white':
                  $route.name?.startsWith('staffMember.payroll') || $route.name?.startsWith('staffMember.payslips'),
              }"
              >My Payroll</span
            >
          </RouterLink>

          <!-- 4. My Profile (rarely used) -->
          <RouterLink
            :to="{ name: 'staffMember.profile' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'staffMember.profile',
            }"
            v-if="can('profile-menu')"
            @click="onNavigate"
          >
            <UserIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name === 'staffMember.profile',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name === 'staffMember.profile',
              }"
              >My Profile</span
            >
          </RouterLink>
        </div>
      </div>

      <!-- PREFERENCES Section -->
      <div data-testid="sidebar-section-preferences">
        <h3 class="section-title">PREFERENCES</h3>
        <div class="space-y-3">
          <button
            type="button"
            disabled
            class="nav-link w-full text-left border border-[#DCDEDD] rounded-[20px] bg-gray-50 cursor-not-allowed opacity-70 flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <SettingsIcon class="w-5 h-5 text-gray-400" />
              <span class="text-brand-dark text-base font-medium">Settings</span>
            </div>
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider bg-gray-200 px-2 py-0.5 rounded-full">Soon</span>
          </button>
        </div>
      </div>
    </nav>

    <!-- Upgrade to Pro Box -->
    <div class="px-6 pb-6 mt-auto">
      <div
        class="upgrade-card bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-[16px] relative overflow-hidden p-5"
      >
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-5">
          <div
            class="absolute top-2 right-4 w-8 h-8 bg-blue-500 rounded-full"
          ></div>
          <div
            class="absolute bottom-4 left-2 w-6 h-6 bg-blue-400 rounded-full"
          ></div>
          <div
            class="absolute top-1/2 left-1/2 w-4 h-4 bg-blue-600 rounded-full"
          ></div>
        </div>

        <div class="relative z-10">
          <!-- Icon -->
          <div
            class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-[12px] flex items-center justify-center mb-3"
          >
            <CrownIcon class="w-5 h-5 text-white" />
          </div>

          <!-- Content -->
          <h4 class="text-brand-dark text-base font-bold mb-1">
            Upgrade to Pro
          </h4>
          <p class="text-brand-dark text-sm font-normal leading-5 mb-4">
            Unlock advanced features and insights
          </p>

          <!-- CTA Button -->
          <button
            class="btn-primary w-full rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3"
          >
            <span class="text-brand-white text-sm font-semibold"
              >Upgrade Now</span
            >
            <ArrowRightIcon class="w-4 h-4 text-white" />
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>
