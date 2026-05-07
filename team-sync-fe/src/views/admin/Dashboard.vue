<script setup lang="ts">
import { ref, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import Statistics from "@/components/admin/dashboard/Statistics.vue";
import EmployeeStatistics from "@/components/admin/dashboard/EmployeeStatistics.vue";
import SearchSection from "@/components/admin/dashboard/SearchSection.vue";
import LatestEmployees from "@/components/admin/dashboard/LatestEmployees.vue";
import LatestTeams from "@/components/admin/dashboard/LatestTeams.vue";
import TeamPulseOverview from "@/components/admin/dashboard/TeamPulseOverview.vue";
import TodayAttendanceOverview from "@/components/admin/dashboard/TodayAttendanceOverview.vue";
import UpcomingMeetings from "@/components/common/UpcomingMeetings.vue";

import PayrollAnalyticsEnhanced from "@/components/admin/analytics/PayrollAnalyticsEnhanced.vue";

const authStore = useAuthStore();

// Check if user is employee role
const isEmployee = computed(() => {
  return authStore.user?.roles?.some((role: any) => role === "staff");
});

// Check if user is finance role
const isFinance = computed(() => {
  return authStore.user?.roles?.some((role: any) => role === "finance");
});

// Check if user has dashboard view permission
const hasDashboardPermission = computed(() => {
  return authStore.user?.permissions?.some(
    (permission: any) => permission.name === "dashboard-view"
  );
});

const showTeamPulse = computed(() => {
  return authStore.user?.permissions?.some(
    (permission: any) => permission.name === "review-manager-submit"
  );
});

// Search params shared between SearchSection and Latest components
const searchParams = ref({});

const handleSearch = (params) => {
  searchParams.value = { ...params };
};
</script>

<template>
  <div class="space-y-6">
    <template v-if="isEmployee">
      <div class="space-y-6">
        <EmployeeStatistics />
        <SearchSection v-if="hasDashboardPermission" @search="handleSearch" />
      </div>
    </template>

    <template v-else-if="isFinance">
      <div class="space-y-6">
        <PayrollAnalyticsEnhanced />
      </div>
    </template>

    <template v-else>
      <div class="space-y-6">
        <TeamPulseOverview v-if="showTeamPulse" />
        <Statistics />
        <SearchSection @search="handleSearch" />
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <LatestEmployees :searchParams="searchParams" />
          <LatestTeams :searchParams="searchParams" />
          <TodayAttendanceOverview />
        </div>
        <UpcomingMeetings />
      </div>
    </template>
  </div>
</template>
