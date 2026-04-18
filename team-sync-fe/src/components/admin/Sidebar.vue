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

          <RouterLink
            :to="{ name: 'admin.employees' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('admin.employee'),
            }"
            v-if="can('employee-menu')"
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

      <!-- PERSONAL Section -->
      <div
        v-if="can('profile-menu') || can('payslip-view') || can('team-view') || canOneOf(['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'])"
        data-testid="sidebar-section-personal"
      >
        <h3 class="section-title">PERSONAL</h3>
        <div class="space-y-3">

          <RouterLink
            :to="{ name: 'employee.payroll' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name?.startsWith('employee.payroll') || $route.name?.startsWith('employee.payslips'),
            }"
            v-if="can('payslip-view')"
            @click="onNavigate"
          >
            <WalletIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name?.startsWith('employee.payroll') || $route.name?.startsWith('employee.payslips'),
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white':
                  $route.name?.startsWith('employee.payroll') || $route.name?.startsWith('employee.payslips'),
              }"
              >My Payroll</span
            >
          </RouterLink>

          <RouterLink
            :to="{ name: 'employee.attendance.my-attendances' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active':
                $route.name === 'employee.attendance.my-attendances' ||
                $route.name === 'employee.attendance.clock',
            }"
            v-if="canOneOf(['attendance-my-attendances', 'attendance-check-in', 'attendance-check-out'])"
            @click="onNavigate"
          >
            <FileTextIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white':
                  $route.name === 'employee.attendance.my-attendances' ||
                  $route.name === 'employee.attendance.clock',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white':
                  $route.name === 'employee.attendance.my-attendances' ||
                  $route.name === 'employee.attendance.clock',
              }"
              >My Attendance</span
            >
          </RouterLink>

          <RouterLink
            :to="{ name: 'employee.profile' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'employee.profile',
            }"
            v-if="can('profile-menu')"
            @click="onNavigate"
          >
            <UserIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name === 'employee.profile',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name === 'employee.profile',
              }"
              >My Profile</span
            >
          </RouterLink>

          <RouterLink
            :to="{ name: 'employee.team' }"
            class="nav-link border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 focus:bg-white transition-all duration-300"
            :class="{
              'nav-link-active': $route.name === 'employee.team',
            }"
            v-if="can('team-view')"
            @click="onNavigate"
          >
            <UsersIcon
              class="w-5 h-5 text-gray-600"
              :class="{
                'text-white': $route.name === 'employee.team',
              }"
            />
            <span
              class="text-brand-dark text-base font-medium"
              :class="{
                'text-brand-white': $route.name === 'employee.team',
              }"
              >My Team</span
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
