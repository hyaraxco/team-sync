<script setup>
import { onMounted, computed } from "vue";
import { TrendingUp, Users, UserPlus, Star, UserCheck, CalendarX, Trophy } from "lucide-vue-next";
import { useStaffMemberStore } from "@/stores/staffMember";
import AnimatedValue from "@/components/common/AnimatedValue.vue";

const staffMemberStore = useStaffMemberStore();

onMounted(() => {
    staffMemberStore.fetchStatistics();
});

// Computed properties for statistics
const total = computed(() => staffMemberStore.statistics.total);
const addedThisMonth = computed(() => staffMemberStore.statistics.added_this_month);
const active = computed(() => staffMemberStore.statistics.active);
const activeChange = computed(() => staffMemberStore.statistics.active_change);
const onLeave = computed(() => staffMemberStore.statistics.on_leave);
const onLeaveChange = computed(() => staffMemberStore.statistics.on_leave_change);
const averageSalary = computed(() => staffMemberStore.statistics.average_salary);
const newEmployees = computed(() => staffMemberStore.statistics.new_employees);
const loading = computed(() => staffMemberStore.loadingStatistics);
</script>

<template>
    <!-- Stats Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <!-- Total Staff Members Card (spans 2 rows on the left) -->
        <div class="lg:row-span-2 rounded-2xl border border-[#0B1042] relative overflow-hidden main-card p-5">
            <div class="flex flex-col justify-center h-full relative z-10">
                <!-- Trending Badge -->
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex items-center gap-1 px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                        <TrendingUp class="w-3 h-3 text-white" />
                        <span class="text-brand-white text-xs font-semibold">
                            +{{ addedThisMonth.toLocaleString() }} this month
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-brand-white-90 text-sm font-medium">Total Staff Members</p>
                        <p class="text-brand-white text-5xl font-extrabold leading-none my-4">
                            <template v-if="loading">...</template>
                            <AnimatedValue v-else :value="total" />
                        </p>
                        <p class="text-brand-white-80 text-base font-normal">Company workforce</p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                        <Users class="w-8 h-8 text-white" />
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="flex items-center gap-3 mt-auto">
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-brand-white-70 text-xs font-normal">All Departments</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Star class="w-3 h-3 text-white opacity-70" />
                        <span class="text-brand-white-70 text-xs font-normal">Growing Team</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 1 Stats Cards -->
        <!-- Active Staff Members -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">Active Staff Members</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="active" />
                    </p>
                    <p :class="activeChange >= 0 ? 'text-success' : 'text-danger'" class="text-sm font-medium">
                        {{ activeChange >= 0 ? "+" : "" }}{{ activeChange }} this week
                    </p>
                </div>
                <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center">
                    <UserCheck class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </div>

        <!-- New Hires -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">New Hires</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="newEmployees" />
                    </p>
                    <p class="text-success text-sm font-medium">This month</p>
                </div>
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center">
                    <UserPlus class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>

        <!-- Row 2 Stats Cards -->
        <!-- Average Salary -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">Average Salary</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        {{ loading ? "..." : `Rp ${averageSalary.toLocaleString()}` }}
                    </p>
                    <p class="text-success text-sm font-medium">Company average</p>
                </div>
                <div class="w-14 h-14 bg-yellow-50 rounded-2xl flex items-center justify-center">
                    <Trophy class="w-6 h-6 text-yellow-600" />
                </div>
            </div>
        </div>

        <!-- On Leave -->
        <div
            class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-brand-dark text-sm font-medium">On Leave</p>
                    <p class="text-brand-dark text-3xl font-extrabold leading-none my-2">
                        <template v-if="loading">...</template>
                        <AnimatedValue v-else :value="onLeave" />
                    </p>
                    <p :class="onLeaveChange >= 0 ? 'text-danger' : 'text-success'" class="text-sm font-medium">
                        {{ onLeaveChange >= 0 ? "+" : "" }}{{ onLeaveChange }} from last week
                    </p>
                </div>
                <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center">
                    <CalendarX class="w-6 h-6 text-red-600" />
                </div>
            </div>
        </div>
    </div>
</template>
