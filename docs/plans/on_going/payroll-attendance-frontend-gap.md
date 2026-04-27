# GAP ANALYSIS: Payroll & Attendance Phase 1 (Frontend Missing)

> **Date:** 2026-04-26
> **Context:** Audit of `docs/plans/` vs Codebase

## Executive Summary
After auditing the `docs/plans` directory and comparing the Business Requirements (BE) to the Functional Requirements (FE), a **significant gap** was identified in the **Attendance for Fair Payroll Phase 1** implementation. 

While the Backend (BE) has successfully implemented the foundation (migrations, logic, calculators, and API endpoints) defined in `payroll-attendance-spec.md` and `payroll-attendance-plans.md` (Milestones 1-12), **there is absolutely no Frontend (FE) UI to support these features.**

The `payroll-attendance-plans.md` sequence entirely omitted Frontend milestones, causing a direct gap where the Backend is ready, but users (HR, Finance, Managers, Staff) cannot interact with the new systems.

---

## Identified Missing Frontend Scopes

### 1. Attendance Policies (`attendance_policies`)
- **BE Status:** Models and seeders are implemented, BUT the API controllers and routes (`routes/api.php`) are **missing**.
- **FE Gap:** No UI for HR/Finance to view or edit `attendance_policies` (e.g., configuring late grace minutes, half-day hour limits, working days per employment type).

### 2. Holiday Calendars (`holiday_calendars`)
- **BE Status:** Models, tables, and `WorkingDaysCalculator` are implemented, BUT the API controllers and routes (`routes/api.php`) are **missing**.
- **FE Gap:** No UI for HR to view, create, or manage Company or National holidays, nor specify which employment types they apply to.

### 3. Hybrid Work Schedules (`hybrid_work_schedules` & `hybrid_schedule_overrides`)
- **BE Status:** Migrations, logic, and `HybridScheduleResolver` are implemented, BUT the API controllers and routes (`routes/api.php`) are **missing**.
- **FE Gap:** 
  - No UI for Staff/Managers to set up base weekly hybrid schedules (office vs. remote days).
  - No UI for Staff to request a daily override/swap.
  - No UI for Managers to approve/reject schedule overrides.

### 4. Attendance Policy Mismatches (`attendance_policy_mismatches`)
- **BE Status:** Controller methods (`acknowledgePolicyMismatch`, `resolvePolicyMismatch`), detector logic, and cron job for auto-escalation are implemented.
- **FE Gap:** 
  - No UI/Dashboard for Managers to review and acknowledge daily attendance mismatches (e.g., employee scheduled for "office" but worked "remote").
  - No UI for HR to resolve escalated mismatches.

### 5. Attendance Periods (`attendance_periods`)
- **BE Status:** Cron jobs (`attendance-periods:sync`) and transition logics (`open` → `review` → `locked`) are implemented, BUT the API controllers and routes (`routes/api.php`) are **missing**.
- **FE Gap:** No UI for HR/Finance to monitor the active period's status, trigger manual cutoffs, or view the readiness workspace before generating payroll.

### 6. Leave Entitlements & Sick Proofs (`leave_entitlements`)
- **BE Status:** Sick leave logic requiring proofs is implemented.
- **FE Gap:** 
  - No UI for uploading medical certificates (proof attachments) during sick leave requests.
  - No UI for HR to review and approve/reject these sick proofs.
  - No UI to view current leave quotas/entitlement configurations.

### 7. Payroll Adjustments (`payroll_adjustments`)
- **BE Status:** BE logic to handle post-lock corrections and apply delta amounts to the next period.
- **FE Gap:** No UI for Finance to review pending adjustments or see the explicit adjustments breakdown in the next month's generated payslip.

---

## Recommended Next Steps

To close this gap, we need to author a **Payroll & Attendance Frontend Implementation Plan** containing the missing milestones:
1. **Hybrid Schedule Management UI**
2. **Attendance Mismatches & Exceptions Dashboard**
3. **Leave Entitlements & Proof Review UI**
4. **Attendance Periods & Payroll Readiness UI**
5. **System Configuration UI (Holidays, Policies)**
