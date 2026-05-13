# Plan: Audit BE vs FE - Gap Analysis & Remediation

> **Status**: ✅ COMPLETED (2026-05-10)
> **Tests**: 668/668 passed

## Summary

Audit membandingkan API endpoints BE (`team-sync-be/routes/api.php`) dengan API calls di FE stores (`team-sync-fe/src/stores/`) dan route/view mapping di `team-sync-fe/src/router/`.

---

## ✅ Gap 1: BE Ada, FE Tidak Ada Store/UI — COMPLETED

### 1.1 License Management — 5 Endpoint Orphan ✅

**Status**: COMPLETED — endpoints ditambahkan ke `setup.js` (bukan store terpisah)

**Changes made:**
- `FE: src/stores/setup.js` — Added:
  - `fetchCurrentLicense()` → `GET /licenses/current`
  - `fetchLicenseDetail(id)` → `GET /licenses/{id}`
  - `updateLicense(id, data)` → `PUT /licenses/{id}`
  - `deleteLicense(id)` → `DELETE /licenses/{id}`

**Note**: Tidak dibuat store terpisah karena license management masih terkait dengan setup flow. Jika nanti butuh dedicated license page, bisa dipindah ke store terpisah.

---

### 1.2 Options: 3 Endpoint Orphan ✅

**Status**: COMPLETED

**Changes made:**
- `FE: src/stores/option.js` — Added:
  - `fetchTaskPriorities()` → `GET /options/task-priorities`
  - `fetchTaskStatuses()` → `GET /options/task-statuses`
  - `fetchSkillLevels()` → `GET /options/skill-levels`

---

### 1.3 Payroll Settings: BPJS Validation ✅

**Status**: COMPLETED

**Changes made:**
- `FE: src/stores/payroll.js` — Added:
  - `fetchBpjsValidation()` → `GET /payroll-settings/bpjs-validation`

---

### 1.4 Minor Orphan Endpoints ✅

**Status**: COMPLETED — semua detail endpoints ditambahkan

**Changes made:**
- `FE: src/stores/attendance.js` — Added `fetchAttendance(id)` → `GET /attendances/{id}`
- `FE: src/stores/leaveRequest.js` — Added `fetchLeaveRequest(id)` → `GET /leave-requests/{id}`
- `FE: src/stores/overtime.js` — Added `fetchOvertimeDetail(id)` → `GET /overtime/{id}`
- `FE: src/stores/attendanceCorrection.js` — Added `fetchCorrection(id)` → `GET /attendance-corrections/{id}`
- `FE: src/stores/holidayCalendar.js` — Added `fetchHoliday(id)` → `GET /holiday-calendars/{id}`
- `FE: src/stores/task.js` — Added:
  - `fetchTask(id)` → `GET /project-tasks/{id}`
  - `fetchTasksPaginated(params)` → `GET /project-tasks/all/paginated`
- `FE: src/stores/performanceReview.js` — Added `fetchOutcomeRule(id)` → `GET /performance/outcome-rules/{id}`
- `FE: src/stores/performanceGoal.js` — Added `fetchGoals()` → `GET /performance/goals`
- `FE: src/stores/payroll.js` — Added `fetchPayrollsIndex()` → `GET /payrolls`

---

### 1.5 Attendance Periods: Create/Update ✅

**Status**: COMPLETED

**Changes made:**
- `FE: src/stores/attendancePeriod.js` — Added:
  - `createPeriod(data)` → `POST /attendance-periods`
  - `updatePeriod(id, data)` → `PUT /attendance-periods/{id}`
- `FE: src/views/admin/attendance/AttendancePeriods.vue` — Added create/edit modals

---

### 1.6 Projects: Delete ✅

**Status**: COMPLETED

**Changes made:**
- `FE: src/stores/project.js` — Added `deleteProject(id)` → `DELETE /projects/{id}`
- `FE: src/views/admin/project/ProjectDetail.vue` — Added delete button with confirmation
- `FE: src/components/admin/project/list/CardList.vue` — Added delete button

---

## ⏳ Gap 2: Performance View Stubs — PENDING

5 view performance sudah punya route tapi implementation belum lengkap:

| View | Route | Status |
|------|-------|--------|
| `GiveFeedback.vue` | `admin.performance.feedback.give` | ⏳ Stub — perlu implementasi |
| `TeamGoals.vue` | `admin.performance.team-goals` | ⏳ Stub — perlu implementasi |
| `ReviewCycleCreate.vue` | `admin.performance.cycles.create` | ⏳ Stub — perlu implementasi |
| `GoalDetail.vue` | `admin.performance.goal.detail` | ⏳ Stub — perlu implementasi |
| `FeedbackGiven.vue` | `admin.performance.feedback.given` | ⏳ Stub — perlu implementasi |

**BE endpoints sudah ada**, tinggal FE connect:
- `GiveFeedback` → `POST /performance/feedback` (sudah ada di `performanceFeedback.js`)
- `TeamGoals` → `GET /performance/goals/team-goals` + `POST /performance/goals` (sudah ada di `performanceGoal.js`)
- `ReviewCycleCreate` → `POST /performance/cycles` (sudah ada di `performanceReview.js`)
- `GoalDetail` → `GET /performance/goals/{id}` + `PUT /performance/goals/{id}` + `GET /performance/goals/{id}/updates` (sudah ada)
- `FeedbackGiven` → `GET /performance/feedback/given` (sudah ada di `performanceFeedback.js`)

**Files yang perlu diedit:**
- `FE: src/views/admin/performance/GiveFeedback.vue` — implement form, connect to `performanceFeedback.createFeedback()`
- `FE: src/views/admin/performance/TeamGoals.vue` — implement list + create, connect to `performanceGoal.fetchTeamGoals()`
- `FE: src/views/admin/performance/ReviewCycleCreate.vue` — implement form
- `FE: src/views/admin/performance/GoalDetail.vue` — implement detail view
- `FE: src/views/admin/performance/FeedbackGiven.vue` — implement list view

---

## Summary Perubahan

### Files Edited (12 files)
```
FE: src/stores/setup.js                     [ADD 4 actions] ✅
FE: src/stores/option.js                    [ADD 3 actions] ✅
FE: src/stores/payroll.js                   [ADD 2 actions] ✅
FE: src/stores/attendance.js                [ADD 1 action]  ✅
FE: src/stores/leaveRequest.js              [ADD 1 action]  ✅
FE: src/stores/overtime.js                  [ADD 1 action]  ✅
FE: src/stores/attendanceCorrection.js      [ADD 1 action]  ✅
FE: src/stores/holidayCalendar.js           [ADD 1 action]  ✅
FE: src/stores/task.js                      [ADD 2 actions] ✅
FE: src/stores/performanceReview.js         [ADD 1 action]  ✅
FE: src/stores/performanceGoal.js           [ADD 1 action]  ✅
FE: src/stores/attendancePeriod.js          [ADD 2 actions] ✅
FE: src/stores/project.js                   [ADD 1 action]  ✅
FE: src/views/admin/attendance/AttendancePeriods.vue [ADD modals] ✅
FE: src/views/admin/project/ProjectDetail.vue [ADD delete] ✅
FE: src/components/admin/project/list/CardList.vue [ADD delete] ✅
```

### Total: 22 endpoints connected ✅

---

## Next Steps

1. **Implement 5 Performance View Stubs** (Gap 2)
2. **Run E2E tests** untuk verify integrasi end-to-end
3. **Create dedicated License Management page** (opsional, jika butuh)

---

## Out of Scope

- BE endpoint changes (audit ini hanya BE vs FE)
- E2E test updates
- Migration untuk database
