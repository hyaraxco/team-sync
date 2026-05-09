<script setup>
import { ref, computed } from "vue";
import { can } from "@/helpers/permissionHelper";
import Statistics from "@/components/admin/dashboard/Statistics.vue";
import EmployeeStatistics from "@/components/admin/dashboard/EmployeeStatistics.vue";
import SearchSection from "@/components/admin/dashboard/SearchSection.vue";
import LatestEmployees from "@/components/admin/dashboard/LatestEmployees.vue";
import LatestTeams from "@/components/admin/dashboard/LatestTeams.vue";
import TeamPulseOverview from "@/components/admin/dashboard/TeamPulseOverview.vue";
import TodayAttendanceOverview from "@/components/admin/dashboard/TodayAttendanceOverview.vue";
import UpcomingMeetings from "@/components/common/UpcomingMeetings.vue";
import UpcomingCutiBersama from "@/components/staff-member/UpcomingCutiBersama.vue";

import PayrollAnalyticsEnhanced from "@/components/admin/analytics/PayrollAnalyticsEnhanced.vue";

// Permission-based dashboard branching (no role checks)
const hasSelfView = computed(() => can("dashboard-self-view"));
const hasFinanceView = computed(() => can("dashboard-finance-view"));
const hasTeamView = computed(() => can("dashboard-team-view"));
const hasHrView = computed(() => can("dashboard-hr-view"));
const showTeamPulse = computed(() => can("review-manager-submit"));

// Search params shared between SearchSection and Latest components
const searchParams = ref({});

const handleSearch = (params) => {
    searchParams.value = { ...params };
};
</script>

<template>
    <div class="space-y-6">
        <!-- Staff: self-service dashboard (dashboard-self-view only, no other dashboard perms) -->
        <template v-if="hasSelfView && !hasFinanceView && !hasTeamView && !hasHrView">
            <div class="space-y-6">
                <EmployeeStatistics />
                <UpcomingCutiBersama />
                <UpcomingMeetings />
            </div>
        </template>

        <!-- Finance: payroll analytics dashboard -->
        <template v-else-if="hasFinanceView && !hasHrView">
            <div class="space-y-6">
                <PayrollAnalyticsEnhanced />
            </div>
        </template>

        <!-- Manager: team-scoped dashboard -->
        <template v-else-if="hasTeamView && !hasHrView">
            <div class="space-y-6">
                <TeamPulseOverview v-if="showTeamPulse" />
                <EmployeeStatistics />
                <UpcomingMeetings />
            </div>
        </template>

        <!-- HR / Superadmin: full company-wide dashboard -->
        <template v-else>
            <div class="space-y-6">
                <TeamPulseOverview v-if="showTeamPulse" />
                <Statistics v-if="hasHrView" />
                <SearchSection @search="handleSearch" />
                <div v-if="hasHrView" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <LatestEmployees :searchParams="searchParams" />
                    <LatestTeams :searchParams="searchParams" />
                    <TodayAttendanceOverview />
                </div>
                <UpcomingMeetings />
            </div>
        </template>
    </div>
</template>
