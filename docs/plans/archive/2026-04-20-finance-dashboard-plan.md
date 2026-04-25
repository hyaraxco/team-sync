# Finance Dashboard Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Provide the `Finance` role with a tailored dashboard that renders `PayrollAnalyticsEnhanced` rather than standard generic HR metrics, resulting in a cleaner and highly relevant UI.

**Architecture:** We will modify `Dashboard.vue` to evaluate `isFinance` via a computed property (checking user roles). If the user is a `finance` role (and not an employee), they will skip the default back-office dashboard metrics and immediately view the specialized Payroll Analytics widgets.

**Tech Stack:** Vue 3, Tailwind CSS, Pinia.

---

### Task 1: Update Dashboard.vue

**Files:**
- Modify: `team-sync-fe/src/views/admin/Dashboard.vue`

**Step 1: Write the minimal implementation**

```vue
<!-- Update imports to include PayrollAnalyticsEnhanced -->
<script setup lang="ts">
import { ref, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import Statistics from "@/components/admin/dashboard/Statistics.vue";
import EmployeeStatistics from "@/components/admin/dashboard/EmployeeStatistics.vue";
import SearchSection from "@/components/admin/dashboard/SearchSection.vue";
import LatestEmployees from "@/components/admin/dashboard/LatestEmployees.vue";
import LatestTeams from "@/components/admin/dashboard/LatestTeams.vue";
import TodayAttendanceOverview from "@/components/admin/dashboard/TodayAttendanceOverview.vue";
import PayrollAnalyticsEnhanced from "@/components/admin/analytics/PayrollAnalyticsEnhanced.vue";

const authStore = useAuthStore();

// Check if user is employee role
const isEmployee = computed(() => {
  return authStore.user?.roles?.some((role: any) => role === "employee");
});

// Check if user is finance role
const isFinance = computed(() => {
  return authStore.user?.roles?.some((role: any) => role === "finance");
});

// ... existing code ...
```

**Step 2: Update the template logic**

```vue
<!-- Replace existing fallback template with v-else-if and v-else -->
<template>
  <div class="space-y-6">
    <template v-if="isEmployee">
      <!-- existing employee dashboard -->
    </template>

    <template v-else-if="isFinance">
      <div class="space-y-6">
        <div class="flex items-center justify-between mb-4">
          <h1 class="text-2xl font-bold text-gray-900">Finance Dashboard</h1>
        </div>
        <PayrollAnalyticsEnhanced />
      </div>
    </template>

    <template v-else>
      <div class="space-y-6">
        <Statistics />
        <SearchSection @search="handleSearch" />
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <LatestEmployees :searchParams="searchParams" />
          <LatestTeams :searchParams="searchParams" />
          <TodayAttendanceOverview />
        </div>
      </div>
    </template>
  </div>
</template>
```

**Step 3: Commit the changes**

```bash
git add team-sync-fe/src/views/admin/Dashboard.vue
git commit -m "feat(dashboard): implement role-based dashboard for finance"
```
