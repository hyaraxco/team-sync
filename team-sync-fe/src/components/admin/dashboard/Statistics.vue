<script setup lang="ts">
import { onMounted, computed } from "vue";
import { TrendingUpIcon, UsersIcon, CalendarCheckIcon, AlertTriangleIcon, StarIcon } from "lucide-vue-next";
import QuickActions from "./QuickActions.vue";
import StatsCard from "@/components/common/StatsCard.vue";
import MainCard from "@/components/common/MainCard.vue";
import { useDashboardStore } from "@/stores/dashboard";

const dashboardStore = useDashboardStore();

onMounted(() => {
    dashboardStore.fetchStatistics();
});

// Computed properties for statistics with safe defaults
const employees = computed(
    () =>
        dashboardStore.statistics?.staffMembers ??
        dashboardStore.statistics?.employees ?? { total: 0, active: 0, new_hires: 0 },
);
const teams = computed(() => dashboardStore.statistics?.teams ?? { total: 0, new_teams: 0 });
const attendance = computed(() => dashboardStore.statistics?.attendance ?? { rate: 0, change: 0 });
const performance = computed(
    () => dashboardStore.statistics?.performance ?? { promotion_eligible: 0, pip_required: 0 },
);
const loading = computed(() => dashboardStore.loading);
</script>

<template>
    <!-- Stats Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <!-- Our Employees Card (spans 2 rows on the left) -->
        <MainCard
            class="lg:row-span-2"
            title="Our Employees"
            :value="employees.total.toLocaleString()"
            subtitle="Active team members"
            iconName="UsersIcon"
            :trendLabel="`+${employees.added_this_month} this month`"
            :isTrendUp="employees.added_this_month >= 0"
            :loading="loading"
        >
            <template #footer>
                <div class="flex items-center gap-1">
                    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-brand-white-70 text-xs font-normal">Active Status</span>
                </div>
                <div class="flex items-center gap-1">
                    <StarIcon class="w-3 h-3 text-white opacity-70" />
                    <span class="text-brand-white-70 text-xs font-normal">Top Performers</span>
                </div>
            </template>
        </MainCard>

        <!-- Row 1 Stats Cards -->
        <!-- Total Teams -->
        <StatsCard
            title="Total Teams"
            :value="teams.total"
            :subtitle="`+${teams.new_teams} new teams`"
            subtitleColor="text-success"
            iconName="UsersIcon"
            colorScheme="blue"
            :loading="loading"
        />

        <!-- Attendance Rate -->
        <StatsCard
            title="Attendance Rate"
            :value="`${attendance.rate}%`"
            :subtitle="`${attendance.change >= 0 ? '+' : ''}${attendance.change}% from last week`"
            :subtitleColor="attendance.change >= 0 ? 'text-success' : 'text-danger'"
            iconName="CalendarCheckIcon"
            colorScheme="green"
            :loading="loading"
        />

        <!-- Quick Actions Card (spans 2 rows on the right) -->
        <QuickActions />

        <!-- Row 2 Stats Cards -->
        <!-- Promotion Eligible -->
        <StatsCard
            title="Promotion Eligible"
            :value="performance.promotion_eligible"
            subtitle="From completed reviews"
            subtitleColor="text-purple-600"
            iconName="TrendingUpIcon"
            colorScheme="purple"
            :loading="loading"
        />

        <!-- PIP Required -->
        <StatsCard
            title="PIP Required"
            :value="performance.pip_required"
            subtitle="Need improvement plan"
            :subtitleColor="performance.pip_required > 0 ? 'text-danger' : 'text-success'"
            iconName="AlertTriangleIcon"
            colorScheme="red"
            :loading="loading"
        />
    </div>
</template>
