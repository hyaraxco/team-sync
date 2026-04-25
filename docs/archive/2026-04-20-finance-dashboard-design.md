# Role-Specific Dashboard Design: Finance

## Goal
Customize the `Dashboard.vue` layout to provide Role-Based Dashboards (Option 2), starting with the `Finance` role, so that Finance personnel see relevant payroll statistics instead of general HR metrics and employees/teams.

## Architecture & Components
1. **`team-sync-fe/src/views/admin/Dashboard.vue`**
   - Add a computed property `isFinance` to check if `authStore.user?.roles` includes `"finance"`.
   - Update the Vue `<template>` layout with `v-else-if="isFinance"`.
   - Import `PayrollAnalyticsEnhanced.vue` and render it for the Finance role.
   - The Finance dashboard will consist of an introductory header ("Finance Dashboard") followed by the full `PayrollAnalyticsEnhanced` component which automatically mounts and fetches payroll statistics.

2. **Data Flow & Reusability**
   - `PayrollAnalyticsEnhanced.vue` is completely self-contained and manages its own state via `useAnalyticsStore()`. It automatically triggers `fetchPayrollAnalytics()` and related endpoints `onMounted`.
   - This ensures we do not pollute `Dashboard.vue` with specific data-fetching logic.

## Security & Access
- The Finance role natively has `payroll-statistics` access (from `RolePermissionSeeder`), which is exactly what the fallback endpoints use.
- The `isFinance` check operates purely on the UI layer. Security is preserved by backend route guardians returning 403 on disallowed API calls. Since Finance does explicitly retain `payroll-statistics` capability, loading this analytics widget is safely authorized.

## UI/UX Considerations
- Retain the base padding and whitespace design structure of the overarching Dashboard view (`.space-y-6`).
- It prevents the Finance user from encountering irrelevant blank widgets or API permission errors when standard dashboards load `AttendanceOverview` or `RecentEmployees`.
