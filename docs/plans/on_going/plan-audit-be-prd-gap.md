# Plan: Audit BE Implementation vs PRD — Gap Analysis

> **Status**: 📋 IN PROGRESS
> **Date**: 2026-05-11
> **Scope**: Backend-only. Cross-reference PRD features against actual BE implementation.

---

## Executive Summary

Audited the full BE codebase (`team-sync-be/`) against `docs/references/prd.md`. Found **22 findings** across 6 domains: **3 CRITICAL, 6 HIGH, 9 MEDIUM, 4 LOW**. Key risks cluster around:
1. **Payroll state machine** — `processing → paid` can skip `approved` (no guard)
2. **TOPSIS criteria mismatch** — PRD specifies 5 criteria with different weights; BE uses 7 criteria with different default weights
3. **THR no reopen** — payroll has reopen, THR doesn't
4. **Missing `status` on Payroll model** — no enum/constants for lifecycle states
5. **Leave entitlement validation not enforced on store** — only on approve

---

## 1. PAYROLL SYSTEM

### 1.1 [CRITICAL] Payroll Status Lifecycle — No State Machine Constants
**Category**: state  
**Priority**: CRITICAL  
**File**: `app/Models/Payroll.php:9-21`  
**Expected per PRD**: Status lifecycle `processing → pending → approved → paid` with clear state constants  
**Actual**: Payroll model has NO status constants. Status values are hardcoded strings scattered across `PayrollRepository.php` (`'processing'`, `'pending'`, `'approved'`, `'paid'`). No enum, no model constants.  
**Impact**: Prone to typos, hard to maintain, no IDE autocomplete, violates project convention (other models like `ThrPayroll`, `OvertimeRecord` use constants).  
**Reproduction**: Search for `'processing'` across the codebase — used as raw string in `PayrollRepository.php:237`, `PayrollRepository.php:309`, `PayrollRepository.php:707`, etc.  
**Fix**: Add `STATUS_PROCESSING`, `STATUS_PENDING`, `STATUS_APPROVED`, `STATUS_PAID` constants to Payroll model. Replace all hardcoded strings.

---

### 1.2 [CRITICAL] Payroll `markAsPaid` — Reconciliation Blocks Without Clear Error ✅ DONE
**Category**: edge case  
**Priority**: CRITICAL  
**File**: `app/Repositories/PayrollRepository.php:910-934`  
**Expected per PRD**: Mark as paid should work after approval  
**Actual**: `markAsPaid()` runs reconciliation check. If critical reconciliation issues exist, it throws an exception. BUT the controller (`PayrollController.php:317-338`) catches `\Exception` and returns generic error message. The user gets "Payroll Not Found" or generic error instead of "Critical reconciliation issues remain".  
**Impact**: Finance users get confusing error messages when trying to pay.  
**Reproduction**: 
1. Generate payroll with employee who has no bank info
2. Approve payroll
3. Try `POST /api/v1/payrolls/{id}/mark-as-paid`
4. Get generic error instead of actionable message  
**Fix**: In `PayrollController::markAsPaid()`, catch the reconciliation block exception specifically and return a structured 422 response with the critical issues list.

---

### 1.3 [HIGH] Payroll `approvePayroll` — No Guard Against Processing Status ✅ DONE
**Category**: state  
**Priority**: HIGH  
**File**: `app/Repositories/PayrollRepository.php:744-881`  
**Expected per PRD**: Status must be `pending` before approval. Only `pending → approved`.  
**Actual**: The code checks `$payroll->status !== 'pending'` at line 760, but the error message is in English while other messages in same method are in Indonesian (`'Payroll yang sudah dibayar tidak dapat disetujui ulang'`). Mixed language. More importantly, there's no check preventing approval when status is `processing` — the generic exception message is unclear.  
**Impact**: User experience inconsistency. Status guard exists but error messaging is confusing.  
**Reproduction**: Trigger approval on a payroll still in `processing` status (race condition during job execution).  
**Fix**: Add explicit status check with clear Indonesian message for each state.

---

### 1.4 [HIGH] Payroll `reopenPayroll` — Allows Reopen From `paid` Directly
**Category**: state  
**Priority**: HIGH  
**File**: `app/Repositories/PayrollRepository.php:978-1024`  
**Expected per PRD**: Reopen should go back to `pending` for correction.  
**Actual**: `reopenPayroll()` accepts status `approved` OR `paid` and sets to `pending`. This is correct per PRD. However, there's no limit on how many times a payroll can be reopened. The `correction_count` is tracked but never used as a guard.  
**Impact**: A payroll could be reopened dozens of times, creating confusion in activity logs.  
**Reproduction**: 
1. Generate → approve → pay → reopen → approve → pay → reopen (repeat)
2. Each cycle adds to `correction_count` with no cap  
**Fix**: Consider adding a maximum correction count (e.g., 3) or requiring superadmin override after N corrections.

---

### 1.5 [HIGH] Payroll Detail Update — No Validation on `final_salary` Minimum ✅ DONE
**Category**: validation  
**Priority**: HIGH  
**File**: `app/Http/Controllers/PayrollController.php:263-285`  
**Expected per PRD**: Payroll amounts are IDR (no decimals, non-negative)  
**Actual**: `updateDetail()` validates `'final_salary' => 'nullable|numeric|min:0'` but doesn't enforce integer-only (IDR has no decimal places). Also no maximum check — a user could set `final_salary` to 999999999999.  
**Impact**: Decimal values in payroll detail break IDR formatting. Abnormally high values bypass reconciliation.  
**Reproduction**: `PUT /api/v1/payroll-details/{id}` with `{"final_salary": 12345.67}` — succeeds despite IDR being integer-only.  
**Fix**: Add `'integer'` or `'digits_between:0,15'` rule. Add `'max:500000000'` sanity check (500M IDR ~$30K USD).

---

### 1.6 [MEDIUM] Payroll `generateReadiness` — No Month Boundary Validation
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Http/Controllers/PayrollController.php:172-191`  
**Expected per PRD**: Only generate for valid Y-m months  
**Actual**: `salary_month` is validated as `date_format:Y-m` but there's no check preventing generation for future months (e.g., 2027-01). The readiness check doesn't validate that the requested month isn't in the future.  
**Impact**: HR could accidentally generate payroll for future months.  
**Reproduction**: `POST /api/v1/payrolls/generate` with `{"salary_month": "2030-01"}` — succeeds if readiness passes.  
**Fix**: Add `'before_or_equal:today'` or validate against current month range.

---

### 1.7 [MEDIUM] Overtime Hours — No Midnight Wrap Handling
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Services/OvertimeService.php:67-69`  
**Expected per PRD**: Overtime hours calculated correctly  
**Actual**: `$hours = round(abs($end->diffInMinutes($start)) / 60, 2)` — uses `abs()` which means if end_time wraps past midnight (e.g., start: 23:00, end: 01:00), it calculates 2 hours correctly. BUT there's no validation that the overtime date matches the start_time date.  
**Impact**: Edge case where overtime record says date=2026-05-10 but start_time=23:00 end_time=01:00 (next day) — the `diffInMinutes` is correct but the record is misleading.  
**Reproduction**: Create overtime with date=2026-05-10, start_time=23:00, end_time=01:00.  
**Fix**: Either validate end_time must be same day, or document that cross-midnight overtime is supported.

---

## 2. THR (TUNJANGAN HARI RAYA)

### 2.1 [CRITICAL] TOPSIS Criteria Mismatch vs PRD
**Category**: domain  
**Priority**: CRITICAL  
**File**: `app/Services/TopsisService.php:32-51` and `app/Http/Controllers/PerformanceTopsisController.php:26-34`  
**Expected per PRD (Section 3.2)**:
| Kriteria | Bobot |
|----------|-------|
| Performance Score | 30% |
| Attendance Rate | 20% |
| Goal Completion | 25% |
| Feedback Score | 15% |
| Tenure Factor | 10% |

**Actual BE criteria** (7 criteria, different weights):
| Kriteria | Default Weight |
|----------|---------------|
| avg_manager_rating (C1) | 30% |
| final_rating (C2) | 30% |
| avg_goal_completion (C3) | 20% |
| goal_completion_ratio (C4) | 5% |
| positive_feedback_count (C5) | 5% |
| attendance_quality (C6) | 5% |
| task_completion_quality (C7) | 5% |

**Discrepancies**:
1. PRD has **5 criteria**, BE has **7** (added goal_completion_ratio, task_completion_quality)
2. PRD: Attendance Rate = **20%**, BE: attendance_quality = **5%**
3. PRD: Tenure Factor = **10%**, BE: **no tenure factor at all**
4. PRD: Feedback Score = **15%**, BE: positive_feedback_count = **5%**
5. PRD: Performance Score = **30%** (single), BE: splits into C1+C2 = **60%**

**Impact**: **Academic risk** — thesis defense requires TOPSIS implementation to match the documented algorithm. The PRD is the academic specification.  
**Reproduction**: Compare PRD Section 3.2 with `PerformanceTopsisController::DEFAULT_WEIGHTS`.  
**Fix**: Either update PRD to match BE (if the expanded criteria are intentional improvements) or align BE to PRD. Given academic requirements, recommend updating BE to match PRD exactly, or formally documenting the deviation in thesis.

---

### 2.2 [HIGH] THR No Reopen Endpoint ✅ DONE
**Category**: missing  
**Priority**: HIGH  
**File**: `routes/api.php:208-218`  
**Expected per PRD**: THR has approval workflow (processing → pending → approved → paid)  
**Actual**: THR has `generate`, `approve`, `markAsPaid` but **no reopen endpoint**. Payroll has `reopenPayroll` to go back from `paid`/`approved` to `pending`. THR has no equivalent.  
**Impact**: If THR is marked as paid incorrectly, there's no way to correct it. The status is permanent.  
**Reproduction**: Generate THR → approve → mark as paid → realize mistake → no endpoint to reopen.  
**Fix**: Add `POST /api/v1/thr/{id}/reopen` endpoint with logic similar to Payroll's `reopenPayroll`.

---

### 2.3 [HIGH] THR Simulation — Amounts Use Float Instead of Integer IDR ✅ DONE
**Category**: domain  
**Priority**: HIGH  
**File**: `app/Services/ThrService.php:250-253`  
**Expected per PRD**: IDR has NO decimal places — amounts are integers  
**Actual**: Simulation returns `round($totalGross, 2)` — 2 decimal places. The `ThrCalculationService` also returns `round($grossThr, 2)`.  
**Impact**: Frontend shows decimal amounts for IDR (e.g., `Rp 12.345.678,50` instead of `Rp 12.345.678`).  
**Reproduction**: `POST /api/v1/thr/simulate` — response has decimal amounts.  
**Fix**: Use `round($value, 0)` for all IDR amounts throughout THR calculation.

---

### 2.4 [MEDIUM] THR `getEligibleEmployees` — No Religion Filter Hardening
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Services/Payroll/ThrCalculationService.php:139-152`  
**Expected per PRD**: THR is paid to employees matching the religion event  
**Actual**: `getEligibleEmployees()` filters by `whereIn('religion', $religions)` where `$religions` is derived from `RELIGION_EVENT_MAP`. If an employee has a `null` religion, they're excluded. If an employee has a religion not in the map (e.g., `'protestan'` vs `'kristen'`), they're excluded.  
**Impact**: Edge case where employee's religion string doesn't exactly match the map keys.  
**Reproduction**: Employee with `religion = 'protestan'` won't match `'kristen'` in `RELIGION_EVENT_MAP`.  
**Fix**: Add a note in PRD/documentation that religion values must match the enum exactly. Consider normalizing religion values.

---

## 3. ATTENDANCE

### 3.1 [HIGH] `getMyAttendances` — Hardcoded 7-Day Window ✅ DONE
**Category**: edge case  
**Priority**: HIGH  
**File**: `app/Repositories/AttendanceRepository.php:99-113`  
**Expected per PRD**: My attendance history (implying full history)  
**Actual**: `getMyAttendances()` only returns last 7 days (`now()->subDays(6)`). No pagination, no date filter parameter.  
**Impact**: Employee can't see attendance from 2+ weeks ago. PRD says "My attendance history" implying full access.  
**Reproduction**: Employee checks attendance from 3 weeks ago — empty result.  
**Fix**: Add date range parameters (`from`, `to`) or pagination. The paginated admin endpoint exists but staff self-service is limited to 7 days.

---

### 3.2 [MEDIUM] `checkIn` — No Duplicate Check-In Guard ✅ DONE
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Repositories/AttendanceRepository.php` (checkIn method)  
**Expected per PRD**: One check-in per day per employee  
**Actual**: Need to verify the `checkIn` method prevents double check-in. The `AttendanceCheckInRequest` validates but the repository `checkIn` method should also guard.  
**Impact**: Double check-in could corrupt attendance data.  
**Reproduction**: `POST /api/v1/attendances/check-in` twice on the same day.  
**Fix**: Verify `AttendanceRepository::checkIn()` checks for existing record on same date.

---

### 3.3 [MEDIUM] `getMyAttendanceStatistics` — Sick Days Count Mismatch ✅ DONE
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Repositories/AttendanceRepository.php:152-158`  
**Expected per PRD**: Attendance statistics  
**Actual**: `getMyAttendanceStatistics()` counts sick days as `status = 'sick'` (line 157) but the attendance model uses `status = 'sick_leave'` (from `AttendanceStatus` enum). The query will always return 0 sick days.  
**Impact**: Dashboard shows 0 sick days always for staff self-service.  
**Reproduction**: Staff with approved sick leave checks attendance statistics — sick_days = 0.  
**Fix**: Change `status = 'sick'` to `status = 'sick_leave'`.

---

### 3.4 [MEDIUM] Attendance Period `lockForUpdate` — No Timeout Guard ✅ DONE
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Repositories/PayrollRepository.php:747-750`  
**Expected per PRD**: Concurrent payroll operations should be safe  
**Actual**: `lockForUpdate()` is used correctly for payroll generation, but there's no `NOWAIT` or timeout. If a payroll job is running and another user tries to approve, the request will block until the transaction commits.  
**Impact**: UI appears frozen during concurrent payroll operations.  
**Reproduction**: Start payroll generation job, then immediately try to approve same payroll.  
**Fix**: Use `lockForUpdate()->nowait()` or set a reasonable timeout.

---

## 4. LEAVE MANAGEMENT

### 4.1 [HIGH] Leave Request `store` — No Entitlement Validation on Create ✅ DONE
**Category**: validation  
**Priority**: HIGH  
**File**: `app/Repositories/LeaveRequestRepository.php:107-154`  
**Expected per PRD**: Leave submission should validate quota  
**Actual**: `store()` checks for overlapping requests and locked periods, but does NOT validate leave entitlement/quota. Quota validation only happens during `approve()` via `ensureEntitlementIsValid()`.  
**Impact**: Employee can submit a leave request exceeding their annual quota. It gets approved later,才发现 quota exceeded. Creates bad UX.  
**Reproduction**: Employee with 12 annual leave days already used submits 10 more days — request is created successfully (status: pending). Approval will fail.  
**Fix**: Add `ensureEntitlementIsValid()` call in `store()` to pre-validate quota, or at minimum add a warning in the response.

---

### 4.2 [MEDIUM] Leave Proof Upload — Only For Sick Leave, No PRD Documented Limitation ✅ DONE
**Category**: validation  
**Priority**: MEDIUM  
**File**: `app/Repositories/LeaveRequestRepository.php:271-279`  
**Expected per PRD**: "Leave proof upload and review" — PRD doesn't specify which leave types  
**Actual**: `uploadProof()` only allows sick leave (`if ($leaveType !== 'sick_leave')`). PRD says "Leave proof upload and review" generically.  
**Impact**: If a company policy requires proof for other leave types (e.g., emergency), the system doesn't support it.  
**Reproduction**: Try uploading proof for `emergency_leave` — gets rejected.  
**Fix**: Either update PRD to specify "sick leave proof" or make proof upload configurable per leave type.

---

### 4.3 [MEDIUM] Leave Balance Service — No Year Rollover Logic ✅ DONE
**Category**: edge case  
**Priority**: MEDIUM  
**File**: `app/Services/Attendance/LeaveBalanceService.php:15-65`  
**Expected per PRD**: Leave balances tracking  
**Actual**: `getEmployeeBalances()` calculates used days within the current year only. If an employee used 10 annual leave days in 2025 and it's now 2026, the balance shows 0 used (correct). But there's no carry-forward mechanism.  
**Impact**: Companies with carry-forward policies can't implement them.  
**Reproduction**: Check balance in January — previous year's usage is gone, no carry-forward.  
**Fix**: Document that carry-forward is not supported, or add a `carry_forward_days` field to `LeaveEntitlement`.

---

## 5. PERFORMANCE REVIEWS + TOPSIS

### 5.1 [HIGH] Review Cycle Status — No Status Transition Guard
**Category**: state  
**Priority**: HIGH  
**File**: `app/Repositories/PerformanceReviewRepository.php:52-63`  
**Expected per PRD**: Review cycles have a lifecycle (draft → active → completed → archived)  
**Actual**: `createCycle()` doesn't validate initial status. `updateCycle()` accepts any data including status changes. `deleteCycle()` has no guard — can delete a cycle with existing reviews. `generateReviews()` checks `completed`/`archived` but the cycle status is never formally transitioned.  
**Impact**: 
1. Can delete a cycle that has generated reviews (orphaned reviews)
2. Can set any status value without transition validation  
**Reproduction**: `DELETE /api/v1/performance/cycles/{id}` on a cycle with 10 generated reviews — succeeds, reviews become orphaned.  
**Fix**: Add guard in `deleteCycle()` to prevent deletion when reviews exist. Add status transition validation.

---

### 5.2 [MEDIUM] Goal Status — No Valid Status Values in Create/Update Request ✅ DONE
**Category**: validation  
**Priority**: MEDIUM  
**File**: `app/Http/Requests/Performance/CreateGoalRequest.php` and `UpdateGoalRequest.php`  
**Expected per PRD**: Goal statuses should follow a defined set  
**Actual**: `CreateGoalRequest` doesn't include `status` field at all. `UpdateGoalRequest` likely allows any string for status. The `PerformanceGoalRepository::updateGoal()` auto-sets `completed_at` based on status but doesn't validate the status value itself.  
**Impact**: A goal could be set to `status = 'banana'` — no validation.  
**Reproduction**: `PUT /api/v1/performance/goals/{id}` with `{"status": "invalid_status"}` — succeeds.  
**Fix**: Add `'status' => 'nullable|in:not_started,in_progress,completed,cancelled'` to `UpdateGoalRequest`.

---

### 5.3 [MEDIUM] Goal `completion_percentage` — No Range Validation ✅ DONE
**Category**: validation  
**Priority**: MEDIUM  
**File**: `app/Http/Requests/Performance/ProgressUpdateGoalRequest.php`  
**Expected per PRD**: Completion percentage should be 0-100  
**Actual**: Need to verify `ProgressUpdateGoalRequest` validates `completion_percentage` range. If it doesn't, values > 100 or < 0 could be stored.  
**Impact**: Invalid progress data affects TOPSIS C3/C4 calculation.  
**Reproduction**: Add progress update with `completion_percentage: 150`.  
**Fix**: Ensure `'completion_percentage' => 'nullable|numeric|min:0|max:100'`.

---

### 5.4 [LOW] Performance Feedback — No `feedback_type` Validation on Store ✅ DONE
**Category**: validation  
**Priority**: LOW  
**File**: `app/Http/Requests/Performance/CreateFeedbackRequest.php`  
**Expected per PRD**: Feedback has positive/negative types  
**Actual**: Need to verify `CreateFeedbackRequest` validates `feedback_type` as an enum value. If not, arbitrary strings could be stored, affecting TOPSIS C5 (positive feedback count).  
**Impact**: Garbage `feedback_type` values could inflate/deflate TOPSIS scores.  
**Reproduction**: Submit feedback with `feedback_type: "maybe"`.  
**Fix**: Validate against `feedback_type` enum: `positive`, `negative`, `neutral`.

---

## 6. ROLE/PERMISSION ENFORCEMENT

### 6.1 [MEDIUM] Payroll Routes — No Role-Based Scope Filtering ✅ DONE
**Category**: permission  
**Priority**: MEDIUM  
**File**: `app/Http/Controllers/PayrollController.php:43-53`  
**Expected per PRD**: Finance is payroll owner, HR has no payroll ops  
**Actual**: Payroll controller uses permission middleware (`payroll-list`, `payroll-create`, etc.) but doesn't scope data by role. A user with `payroll-list` can see ALL payrolls, not just their scope. For single-tenant this is fine, but if HR accidentally gets `payroll-list` permission, they see everything.  
**Impact**: Least-privilege violation if permissions are misconfigured.  
**Reproduction**: Assign HR user `payroll-list` permission — they can see all payroll data.  
**Fix**: This is acceptable for single-tenant. Document that permission assignment is the access control mechanism.

---

### 6.2 [MEDIUM] Leave Request Approval — Manager Scope Check Not in Bulk
**Category**: permission  
**Priority**: MEDIUM  
**File**: `app/Repositories/LeaveRequestRepository.php:213-268`  
**Expected per PRD**: Managers can only approve/reject their team's leave  
**Actual**: `bulkAction()` calls `authorizeManagerScope()` for each leave request, which is correct. However, the `approve()` and `reject()` single methods also call `getById()` which calls `authorizeManagerScope()`. This is correctly enforced.  
**Impact**: None — this is a positive finding. Scope is correctly enforced.  
**Note**: Including for completeness — this works correctly.

---

### 6.3 [LOW] Dashboard Team Pulse — Nudge Endpoint Missing Permission ✅ DONE
**Category**: permission  
**Priority**: LOW  
**File**: `routes/api.php:282`  
**Expected per PRD**: Manager can nudge team members  
**Actual**: `POST /dashboard/team-pulse/{staffMemberId}/nudge` has NO permission middleware. Any authenticated user can nudge anyone.  
**Impact**: Staff members could nudge other staff members.  
**Reproduction**: Staff user calls `POST /dashboard/team-pulse/1/nudge` — succeeds.  
**Fix**: Add permission middleware or ownership check.

---

### 6.4 [LOW] Meeting Create — No Permission Middleware ✅ DONE
**Category**: permission  
**Priority**: LOW  
**File**: `routes/api.php:75-77`  
**Expected per PRD**: "Schedule meetings — HR broadcasts meeting links"  
**Actual**: `meetings` resource routes (including `store`) have NO permission middleware. Any authenticated user can create meetings.  
**Impact**: Non-HR users can create meetings, which contradicts PRD's "HR broadcasts meeting links".  
**Reproduction**: Staff user calls `POST /api/v1/meetings` — succeeds.  
**Fix**: Add `PermissionMiddleware::using('meeting-create')` or at minimum restrict to HR/manager roles.

---

## 7. INDONESIAN DOMAIN

### 7.1 [MEDIUM] PPh 21 — No TER (Tarif Efektif Rata-rata) Method
**Category**: domain  
**Priority**: MEDIUM  
**File**: `app/Services/Payroll/TaxCalculationService.php:101-173`  
**Expected per PRD**: "PPh 21 calculation (TER 2024 method)" — TER is the monthly simplified method using effective rates  
**Actual**: `calculateMonthlyPph21()` uses **annualized progressive calculation** (calculate full annual tax, divide by 12). This is the **annual method**, not TER. TER uses a simplified table of effective rates per PTKP status and salary bracket.  
**Impact**: Results may differ from official PPh 21 TER calculation. For monthly payroll, TER is the standard method in Indonesia. The annualized method is used for year-end true-up (December).  
**Reproduction**: Compare BE calculation with official PPh 21 calculator for salary Rp 15,000,000 with TK/0 — results will differ.  
**Fix**: Implement TER 2024 table as the monthly method. Keep annualized method for December true-up. This is a significant business logic gap.

---

### 7.2 [LOW] THR Proration — Uses `diffInMonths` Which Can Be Imprecise
**Category**: edge case  
**Priority**: LOW  
**File**: `app/Services/Payroll/ThrCalculationService.php:50-51`  
**Expected per PRD**: THR proration based on months worked  
**Actual**: `$tenureMonths = (int) $startDate->diffInMonths($paymentDate)` — Carbon's `diffInMonths` counts calendar months, not exact 30-day periods. An employee who started on Jan 31 and THR is calculated for Apr 15 would get 2 months (Feb, Mar) not 3.  
**Impact**: Edge case where employee started late in a month gets slightly less THR than expected.  
**Reproduction**: Employee start_date = 2025-01-31, THR payment_date = 2025-04-01. `diffInMonths` = 2, but they worked ~2.03 months.  
**Fix**: Document that proration uses calendar months. This is standard practice in Indonesia.

---

## Summary Table

| # | Domain | Category | Priority | File | Issue |
|---|--------|----------|----------|------|-------|
| 1.1 | Payroll | state | CRITICAL | Payroll.php | No status constants — hardcoded strings |
| 1.2 | Payroll | edge case | CRITICAL | PayrollRepository.php:910 | Reconciliation block error swallowed |
| 1.3 | Payroll | state | HIGH | PayrollRepository.php:744 | Mixed language error messages |
| 1.4 | Payroll | state | HIGH | PayrollRepository.php:978 | No reopen limit |
| 1.5 | Payroll | validation | HIGH | PayrollController.php:263 | No integer-only IDR validation |
| 1.6 | Payroll | edge case | MEDIUM | PayrollController.php:172 | No future month guard |
| 1.7 | Overtime | edge case | MEDIUM | OvertimeService.php:67 | No midnight wrap documentation |
| 2.1 | TOPSIS | domain | CRITICAL | TopsisService.php | Criteria mismatch vs PRD (7 vs 5, different weights) |
| 2.2 | THR | missing | HIGH | api.php:208 | No reopen endpoint |
| 2.3 | THR | domain | HIGH | ThrService.php:250 | Float amounts instead of integer IDR |
| 2.4 | THR | edge case | MEDIUM | ThrCalculationService.php:139 | Religion string matching |
| 3.1 | Attendance | edge case | HIGH | AttendanceRepository.php:99 | Hardcoded 7-day window |
| 3.2 | Attendance | edge case | MEDIUM | AttendanceRepository | No duplicate check-in guard |
| 3.3 | Attendance | edge case | MEDIUM | AttendanceRepository.php:157 | Sick days status mismatch |
| 3.4 | Attendance | edge case | MEDIUM | PayrollRepository.php:747 | No lock timeout |
| 4.1 | Leave | validation | HIGH | LeaveRequestRepository.php:107 | No quota validation on store |
| 4.2 | Leave | validation | MEDIUM | LeaveRequestRepository.php:271 | Proof only for sick leave |
| 4.3 | Leave | edge case | MEDIUM | LeaveBalanceService.php | No carry-forward |
| 5.1 | Performance | state | HIGH | PerformanceReviewRepository.php:52 | No cycle delete guard |
| 5.2 | Performance | validation | MEDIUM | CreateGoalRequest.php | No goal status validation |
| 5.3 | Performance | validation | MEDIUM | ProgressUpdateGoalRequest | No completion % range |
| 5.4 | Performance | validation | LOW | CreateFeedbackRequest.php | No feedback_type validation |
| 6.1 | Permissions | permission | MEDIUM | PayrollController.php | No role-based scope |
| 6.2 | Permissions | permission | MEDIUM | LeaveRequestRepository | ✅ Correctly enforced |
| 6.3 | Permissions | permission | LOW | api.php:282 | Nudge endpoint no permission |
| 6.4 | Permissions | permission | LOW | api.php:75 | Meeting create no permission |
| 7.1 | Indonesian | domain | MEDIUM | TaxCalculationService.php | Annual method instead of TER |
| 7.2 | Indonesian | edge case | LOW | ThrCalculationService.php:50 | Calendar month proration |

---

## Recommended Fix Priority

### Phase 1 (Before Thesis Defense) — CRITICAL
1. **TOPSIS criteria alignment** (#2.1) — Academic requirement
2. **Payroll status constants** (#1.1) — Code quality
3. **PPh 21 TER method** (#7.1) — Indonesian compliance

### Phase 2 (Before Release) — HIGH
4. **THR reopen endpoint** (#2.2)
5. **THR IDR integer amounts** (#2.3)
6. **Leave quota pre-validation** (#4.1)
7. **Attendance 7-day window** (#3.1)
8. **Payroll error messaging** (#1.2, #1.3)
9. **Payroll IDR validation** (#1.5)

### Phase 3 (Polish) — MEDIUM
10. All MEDIUM items

### Phase 4 (Nice to Have) — LOW
11. All LOW items
