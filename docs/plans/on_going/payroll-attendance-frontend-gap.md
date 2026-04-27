# GAP ANALYSIS: Payroll & Attendance Phase 1 (Frontend)

> **Date:** 2026-04-26 (updated 2026-04-27)
> **Context:** Audit of `docs/plans/` vs Codebase
> **Status:** ⚠️ PARTIALLY RESOLVED — See remaining gaps below

## Executive Summary
After auditing the `docs/plans` directory and comparing the Business Requirements (BE) to the Functional Requirements (FE), a **significant gap** was identified in the **Attendance for Fair Payroll Phase 1** implementation.

While the Backend (BE) had implemented the foundation (migrations, logic, calculators) defined in `payroll-attendance-spec.md` and `payroll-attendance-plans.md`, there were missing Frontend views and missing Backend API controllers.

**On 2026-04-26**, a sprint was executed (conversation `b7180c83`) that created FE views and BE API controllers to close the major gaps. The sprint was merged to `main` as `b9b1f28`.

---

## ✅ Resolved Items (Merged to main)

### 1. Holiday Calendars (`holiday_calendars`)
- **BE:** `HolidayCalendarController` — CRUD API with permission middleware ✅
- **FE:** `AttendanceSettings.vue` — Holiday Calendars tab ✅
- **Store:** `holidayCalendar.js` ✅

### 2. Hybrid Work Schedules (`hybrid_work_schedules` & `hybrid_schedule_overrides`)
- **BE:** `HybridWorkScheduleController` — `index`, `mySchedule` endpoints ✅
- **FE:** `HybridSchedules.vue` — Staff schedule view with override requests ✅
- **Store:** `hybridSchedule.js` ✅

### 3. Attendance Policy Mismatches (`attendance_policy_mismatches`)
- **BE:** Controller methods (`acknowledgePolicyMismatch`, `resolvePolicyMismatch`) already existed ✅
- **FE:** `PolicyMismatches.vue` — Mismatch dashboard with Acknowledge/Resolve actions ✅
- **Store:** `attendance.js` — added mismatch endpoints ✅

### 4. Attendance Periods (`attendance_periods`)
- **BE:** `AttendancePeriodController` — CRUD API ✅
- **FE:** `AttendancePeriods.vue` — Period monitoring + readiness workspace ✅
- **Store:** `attendancePeriod.js` ✅

### 5. Leave Entitlements — Sick Proofs
- **FE:** `LeaveRequestList.vue` — Proof upload, proof review UI ✅
- **BE:** Sick leave proof endpoints already existed ✅

### 6. Payroll Adjustments Visibility
- **FE:** `PayrollDetail` — adjustments section ✅ (needs final verification)

---

## 🔴 Remaining Gaps (Still Open)

### GAP-A: Attendance Policies — No API Controller
- **BE Status:** `AttendancePolicy` model and seeder exist, BUT there is **no `AttendancePolicyController`** and no API routes in `routes/api.php`.
- **FE Status:** `AttendanceSettings.vue` has an "Attendance Policies" tab, but it cannot load or save data.
- **Impact:** HR cannot configure `late_grace_minutes`, `half_day_min_hours`, `work_days_per_week`, etc. per employment type.
- **Spec Violation:** "Fairness by configuration, not by hardcode" — currently hardcoded in seeder.

### GAP-B: Leave Entitlements — No API Controller, No FE Config
- **BE Status:** `LeaveEntitlement` model, seeder (28 rows), and `LeaveEntitlementValidator` service exist. BUT there is **no `LeaveEntitlementController`** and no API routes.
- **FE Status:** No UI for viewing or managing leave entitlement configurations.
- **Impact:** HR cannot view or modify leave quotas, carry-over rules, or attachment requirements.
- **Spec Violation:** Same as GAP-A — leave rules are seeded once and immutable from UI.

### GAP-C: Indonesian Holiday Seed Data — FE Only
- **Status:** Indonesian 2026 holidays are seeded as mock data in the FE `holidayCalendar.js` store (DEV/TEST fallback). They are NOT seeded in the BE `HolidayCalendarSeeder`.
- **Impact:** Production deployments will have no holidays unless HR manually creates them.

---

## Recommended Next Steps

1. **Create `AttendancePolicyController`** — `GET` (index) + `PUT` (update per employment type)
2. **Create `LeaveEntitlementController`** — `GET` (index, grouped by employment type) + `PUT` (update)
3. **Create `HolidayCalendarSeeder`** with Indonesian 2026 national holidays
4. **Add Leave Entitlements tab** to `AttendanceSettings.vue`
