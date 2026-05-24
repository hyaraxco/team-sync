# Plan: Attendance Tabs Pagination + BE Filters

**Status:** COMPLETED  
**Created:** 2026-05-22  
**Branch:** `feat/dark-mode-css-vars`

## Problem

1. **Pagination hilang** di semua tab inline (Leave Requests, Corrections, Records, Overtime, Hybrid)
2. **Filter hardcoded** — seharusnya ambil dari BE, search internal error
3. **AttendanceRecordList** hardcoded status filter (present/late/absent/etc) — BE tidak support status filter

## BE Filter Support (verified)

| Endpoint | Filters Supported |
|----------|-------------------|
| `/api/v1/leave-requests/all/paginated` | `search`, `row_per_page` |
| `/api/v1/attendance-corrections/all/paginated` | `search`, `row_per_page`, `status` (pending/approved/rejected) |
| `/api/v1/attendances/all/paginated` | `search`, `row_per_page` |
| `/api/v1/overtime` | `status`, `staff_member_id`, `overtime_type`, `date_from`, `date_to`, `per_page` |
| `/api/v1/hybrid-work-schedules` | `per_page` |

## Tasks

### Task 1: AttendanceList.vue — Leave Requests Tab
- Add `useSearchFilter` composable with `leaveRequestStore.fetchAllPaginated`
- Add SearchFilter component (search only, no status filter)
- Add Pagination component
- Wire `@search`, `@reset`, `@page-change`, `@per-page-change` events
- Remove hardcoded `leaveRequests` array loop

### Task 2: AttendanceList.vue — Corrections Tab
- Add `useSearchFilter` composable with `attendanceCorrectionStore.fetchAllPaginated`
- Add SearchFilter component with status filter (pending/approved/rejected)
- Add Pagination component
- Wire events
- Remove hardcoded `pendingCorrections` array loop

### Task 3: AttendanceRecordList.vue
- **REMOVE hardcoded status filter** — BE does NOT support status filter
- Keep search only
- Pagination already exists — verify it works

### Task 4: OvertimeManagement.vue
- **VERIFY** current SearchFilter uses BE filters (status, overtime_type, date_from, date_to)
- If hardcoded, replace with BE filters
- Pagination already exists — verify it works

### Task 5: HybridScheduleList.vue
- **VERIFY** pagination works (BE only supports `per_page`)
- No filters needed (BE doesn't support any)

### Task 6: Update Tests
- Update smoke tests for new pagination/filter behavior
- Verify all 1086 tests pass

## Acceptance Criteria

- [ ] All tabs have pagination
- [ ] All filters use BE endpoints (no hardcoded options)
- [ ] Search works without internal error
- [ ] All 1086 FE tests pass
- [ ] No console errors in browser

## Notes

- AttendanceRecordList hardcoded status filter is WRONG — BE doesn't support it
- Overtime already has SearchFilter but need to verify it uses BE filters
- HybridSchedule has no filters (BE limitation)
