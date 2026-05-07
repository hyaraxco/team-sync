# Payroll & Attendance FE Gap Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Implement the missing Frontend views for the Attendance for Fair Payroll Phase 1 features.

**Architecture:** We will create new Vue components mapped to new routes under the Admin and Staff namespaces. We will utilize Pinia stores to communicate with the existing backend endpoints.

**Tech Stack:** Vue 3 (Composition API, `<script setup>`), Pinia, Tailwind CSS, Vue Router.

---

### Task 1: Setup Pinia Stores

**Files:**
- Create: `team-sync-fe/src/stores/hybridSchedule.js`
- Create: `team-sync-fe/src/stores/holidayCalendar.js`
- Create: `team-sync-fe/src/stores/attendancePeriod.js`
- Modify: `team-sync-fe/src/stores/attendance.js` (add mismatch endpoints)

**Step 1: Write the Pinia stores**
We need basic CRUD stores using the Axios instance to fetch endpoints:
- `api/v1/hybrid-schedules`
- `api/v1/hybrid-schedule-overrides`
- `api/v1/holiday-calendars`
- `api/v1/attendance-periods`
- `api/v1/attendance-policy-mismatches`

### Task 2: Attendance Policies & Holiday Calendars (System Config)

**Files:**
- Create: `team-sync-fe/src/views/admin/attendance/AttendanceSettings.vue`
- Modify: `team-sync-fe/src/router/attendance.js`

**Step 1: Create AttendanceSettings view**
Implement a dual-tab layout:
1. **Attendance Policies**: List policies by employment type. Form to edit `late_grace_minutes`, `half_day_min_hours`, etc.
2. **Holiday Calendars**: Data table to view holidays, and a modal form to add/edit holidays with `applies_to` selection.

**Step 2: Add route**
Add `/admin/attendance-settings` to `router/attendance.js` with `attendance-settings` permission.

### Task 3: Attendance Periods & Readiness Workspace

**Files:**
- Create: `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue`
- Modify: `team-sync-fe/src/router/attendance.js`

**Step 1: Create AttendancePeriods view**
A table displaying the monthly periods (`open`, `review`, `locked`).
Clicking a period opens a "Readiness Workspace" modal or nested view showing which employees are `ready`, `warning`, or `blocked`.

### Task 4: Attendance Policy Mismatches Dashboard

**Files:**
- Create: `team-sync-fe/src/views/admin/attendance/PolicyMismatches.vue`
- Modify: `team-sync-fe/src/router/attendance.js`

**Step 1: Create PolicyMismatches view**
A table showing pending and escalated mismatches.
Add Actions: "Acknowledge" (for Managers) and "Resolve" (for HR) with a notes modal.

### Task 5: Hybrid Schedule Management

**Files:**
- Create: `team-sync-fe/src/views/admin/attendance/HybridSchedules.vue`
- Create: `team-sync-fe/src/views/staff-member/MySchedule.vue`
- Modify: `team-sync-fe/src/router/attendance.js`

**Step 1: Create Admin HybridSchedules**
Allow HR to set the base weekly schedules (Mon-Fri -> office/remote/off) for hybrid employees.

**Step 2: Create Staff MySchedule**
Allow hybrid employees to see their schedule and request an override/swap (e.g., specific date -> remote).
Add approval UI for managers.

### Task 6: Leave Entitlements & Sick Proofs

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue`
- Modify: `team-sync-fe/src/views/staff-member/MyLeaveRequests.vue` (or equivalent)

**Step 1: Add Proof Upload to Request Form**
When `leave_type` == 'sick_leave', require a file upload (`proof_file`).

**Step 2: Add Proof Review to HR view**
In `LeaveRequestList`, if it's a sick leave, show the attachment and a "Review Proof" button.

### Task 7: Payroll Adjustments Visibility

**Files:**
- Modify: `team-sync-fe/src/views/admin/payroll/PayrollDetail.vue`

**Step 1: Show Adjustments Array**
Render the `adjustments[]` array returned from the payroll backend detail endpoint as a separate table section in the payslip view.

---

**Next step: use the executing-plans skill to execute this plan task-by-task in single-flow mode.**
