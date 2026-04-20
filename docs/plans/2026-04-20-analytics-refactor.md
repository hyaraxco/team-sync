# Analytics Refactor Design

**Goal**: Improve the maintainability of the Analytics module by breaking down complex repository methods and centralizing common frontend utilities.

## Proposed Changes

### Backend: AnalyticsRepository.php
Refactor `getExecutiveSummary` to use private helper methods.

- **Current State**: 300+ line method performing ~10 different database queries.
- **Proposed State**:
  - `getExecutiveSummary()`: Acts as a coordinator.
  - `getEmployeeKpis(array $filteredEmployeeIds, Carbon $start, Carbon $prevStart)`
  - `getAttendanceKpis(array $filteredEmployeeIds, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd)`
  - `getSalaryKpis(array $filteredEmployeeIds, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd)`
  - `getProjectKpis(array $filteredEmployeeIds)`
  - `getLeaveKpis(array $filteredEmployeeIds, int $totalEmployees, Carbon $start, Carbon $end)`
  - `getTrendData(array $filteredEmployeeIds, Carbon $start, Carbon $end)`
  - `getTeamPerformanceData(Carbon $start, Carbon $end, ?string $department, ?int $teamId)`

### Frontend: formatUtils.js
Centralize currency formatting.

- **Current State**: Inline `Intl.NumberFormat` calls in multiple Vue components.
- **Proposed State**:
  ```javascript
  export const formatCurrency = (value) => {
    return new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
    }).format(value);
  };
  ```

## Implementation Plan

### Task 1: Frontend Utility Abstraction
- **Files**:
  - Modify: `team-sync-fe/src/utils/formatUtils.js`
  - Modify: `team-sync-fe/src/components/admin/analytics/PayrollAnalyticsEnhanced.vue`
- **Steps**:
  1. Add `formatCurrency` to `formatUtils.js`.
  2. Import and use `formatCurrency` in `PayrollAnalyticsEnhanced.vue`.
  3. Replace all inline `Intl.NumberFormat` calls.

### Task 2: Backend Repository Refactoring
- **Files**:
  - Modify: `team-sync-be/app/Repositories/AnalyticsRepository.php`
- **Steps**:
  1. Extract Individual KPI logic into private methods.
  2. Extract Trend Data logic.
  3. Extract Team Performance logic.
  4. Update `getExecutiveSummary` to call these methods.
  5. Verify consistency of results (manual data verification).
