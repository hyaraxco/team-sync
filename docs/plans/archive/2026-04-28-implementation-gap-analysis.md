# Implementation Gap Analysis v2 — Full Cross-Layer Audit

> Tanggal: 2026-04-28
> Scope: DB Schema, Backend, Frontend — semua module
> Method: 3 parallel audit agents + Momus review + Librarian deep scan
> Revisi: v2 — incorporates Momus rejection feedback + Librarian scope findings

---

## Executive Summary

| Layer | Total Items | OK | Gaps Found |
|-------|-------------|-----|------------|
| DB Schema vs BE | 44 models | 44 matched | 0 dead schema |
| BE Endpoints vs FE Stores | ~120 routes | 95%+ matched | 5 unused BE endpoints, 1 anti-pattern |
| FE Views vs Store Wiring | 52 views | 35 COMPLETE | 4 STUB, 13 PARTIAL |
| BE Controller Guards | 34 controllers | 30 guarded | 4 missing HasMiddleware |
| FE Null Safety | 52 views | ~48 safe | 2 crash-prone views (5 instances) |

---

## Case A — FE Exists, BE Missing

**Tidak ditemukan.** Semua axios call di FE stores punya matching route di api.php.

---

## Case B — BE Exists, FE Missing (Unused Endpoints)

| Endpoint | Controller | Issue | Priority |
|----------|-----------|-------|----------|
| GET /options/task-priorities | OptionController@getTaskPriorities | FE hardcode priorities | LOW |
| GET /options/task-statuses | OptionController@getTaskStatuses | FE hardcode statuses | LOW |
| GET /options/skill-levels | OptionController@getSkillLevels | FE hardcode skill levels | LOW |
| GET /projects/{id}/tasks | ProjectTaskController@getByProject | FE uses /project-tasks?project_id= instead | LOW |
| GET /attendance-corrections/{id} | AttendanceCorrectionController@show | FE only uses list + approve/reject | LOW |

> Catatan: GET /performance/reviews/sections DIHAPUS dari daftar ini (Momus correction) — endpoint ini DIPAKAI oleh performanceReview store fetchActiveSections() line 244.

---

## Case C — Both Exist, Wiring Incomplete (STUB/PARTIAL Views)

### STUB Views (Prioritas Tinggi — Tidak Fungsional)

| # | View | Store | Masalah | Action Required |
|---|------|-------|---------|-----------------|
| 1 | GiveFeedback.vue | None | Tidak ada store import, submitFeedback = console.log | Connect ke performanceFeedback store, implement form + submission |
| 2 | GoalDetail.vue | None | Tidak ada data fetching, render static ID | Connect ke performanceGoal store, implement fetchGoalDetail + progress timeline |
| 3 | TeamGoals.vue | performanceGoal | Fetch data tapi render "TODO placeholder" | Replace placeholder dengan goal cards + filter |
| 4 | FeedbackGiven.vue | performanceFeedback | Fetch data tapi render "TODO placeholder" | Replace placeholder dengan feedback list |

### PARTIAL Views (Prioritas Sedang — Fungsional tapi Ada TODO)

| # | View | Missing Feature | Priority |
|---|------|-----------------|----------|
| 5 | MyGoals.vue | Create Goal modal/navigation | MEDIUM |
| 6 | ReviewCycleCreate.vue | createCycle action = console.log | MEDIUM |
| 7 | ReviewDetail.vue | Minor calibration scoring TODOs | LOW |
| 8 | TemplateManagement.vue | Edit/Create placeholder comments | LOW |
| 9 | PendingCalibration.vue | Calibration submission stubbed | MEDIUM |
| 10 | PayrollDetail.vue | Export PDF, Bulk Actions | LOW |
| 11 | PayrollSettings.vue | BPJS Rate History | LOW |
| 12 | LeaveRequestList.vue | Bulk Approval | LOW |
| 13 | TeamList.vue | Team Lead assignment | LOW |
| 14 | ProjectCreate.vue | Task Template selection | LOW |
| 15 | MyAttendance.vue | Calendar View toggle | LOW |
| 16 | MyPayslips.vue | Email Payslip button | LOW |

---

## Case D — Security: Controllers Missing HasMiddleware (Librarian Finding)

4 performance controllers tidak implement HasMiddleware, artinya permission enforcement hanya di route-level tanpa defense-in-depth di controller:

| Controller | Route-Level Guard | Controller-Level Guard | Risk |
|------------|------------------|----------------------|------|
| PerformanceReviewTemplateController | review-cycle-manage (api.php:269) | NONE | MEDIUM — CRUD tanpa internal guard |
| PerformanceReviewCycleController | review-cycle-manage (api.php:269) | NONE | HIGH — generateReviews mass insert |
| PerformanceOutcomeRuleController | review-cycle-manage (api.php:269) | NONE | MEDIUM — salary/bonus config |
| PerformanceTopsisController | review-calibrate (api.php) | NONE | LOW — read-only ranking |

> Catatan: Route-level middleware di api.php SUDAH ada untuk semua endpoint ini. Risiko hanya muncul jika route middleware terlewat/salah config. Tapi best practice Laravel adalah defense-in-depth (HasMiddleware di controller juga).

---

## Case E — FE Null Safety: Crash-Prone Property Access (Librarian Finding)

Akses properti deeply-nested tanpa optional chaining yang bisa crash saat rapid navigation:

| # | File | Line | Expression | Risk |
|---|------|------|-----------|------|
| 1 | ReviewDetail.vue | 627 | review.reviewer.user.roles[0].name | HIGH — crash jika reviewer/user/roles null |
| 2 | StaffMemberDetail.vue | 459 | staffMember.emergency_contacts[0].full_name | MEDIUM — crash jika contacts kosong |
| 3 | StaffMemberDetail.vue | 465 | staffMember.emergency_contacts[0].relationship | MEDIUM |
| 4 | StaffMemberDetail.vue | 471 | staffMember.emergency_contacts[0].phone | MEDIUM |
| 5 | StaffMemberDetail.vue | 477 | staffMember.emergency_contacts[0].email | MEDIUM |

> Catatan: StaffMemberProfile.vue (staff-member view) sudah pakai optional chaining (line 392-410). Hanya admin StaffMemberDetail.vue yang belum.

---

## Anti-Pattern Found

| File | Line | Issue | Fix | Verified |
|------|------|-------|-----|----------|
| EmployeeStatistics.vue | 302 | Direct axiosInstance.get("/dashboard/my-statistics") bypassing dashboard store | Move to dashboard.js store action | CONFIRMED — file+line valid |

---

## Temuan yang DIBATALKAN (False Positives)

| Sumber | Claim | Fakta | Status |
|--------|-------|-------|--------|
| Audit v1 | GET /performance/reviews/sections unused | Store punya fetchActiveSections() line 244 | REMOVED dari Case B |
| Librarian | task.js error state tidak di-reset | task.js punya this.error = null di 13 actions | FALSE — sudah benar |

---

## Prioritized Action Plan

### Phase 1 — Fix STUB Views (4 views, ~4-6 jam)

Semua BE endpoint sudah ada. Tinggal connect FE ke store.

```
Agent 1: GiveFeedback.vue + FeedbackGiven.vue (feedback scope)
Agent 2: GoalDetail.vue + TeamGoals.vue + MyGoals.vue create modal (goals scope)
```

QA Phase 1:
- Login sebagai manager, navigate ke Performance > Feedback > Give Feedback → form muncul, submit berhasil
- Login sebagai manager, navigate ke Performance > Team Goals → goal cards muncul dengan data
- Login sebagai staff, navigate ke Performance > My Goals → klik card → GoalDetail render dengan progress
- Login sebagai manager, navigate ke Performance > Feedback → tab "Given" menampilkan list
- bun run test --run → 212+ pass
- php artisan test → 429+ pass

### Phase 2 — Fix Security + Null Safety (quick wins, ~1-2 jam)

```
Agent 3: Add HasMiddleware ke 4 controllers (PerformanceReviewTemplateController, PerformanceReviewCycleController, PerformanceOutcomeRuleController, PerformanceTopsisController)
Agent 4: Fix 5 null-safety crash points (ReviewDetail.vue line 627, StaffMemberDetail.vue lines 459-477) + EmployeeStatistics.vue anti-pattern
```

QA Phase 2:
- ReviewDetail.vue: buka review yang reviewer-nya null → tidak crash, tampil fallback "-"
- StaffMemberDetail.vue: buka employee tanpa emergency contact → tidak crash
- EmployeeStatistics.vue: dashboard load → network tab menunjukkan call via store, bukan direct axios
- php artisan test → 429+ pass (HasMiddleware tidak break existing routes)

### Phase 3 — Fix PARTIAL Views Critical (3 views, ~2-3 jam)

```
Agent 5: ReviewCycleCreate.vue createCycle + PendingCalibration.vue submission
```

QA Phase 3:
- Login HR, create review cycle → form submit berhasil, cycle muncul di list
- Login HR, buka pending calibration → klik calibrate → submission berhasil

### Phase 4 — Minor TODOs (incremental, backlog)

```
- PayrollDetail export PDF
- PayrollSettings BPJS Rate History
- LeaveRequestList Bulk Approval
- Other LOW priority items
```

---

## Validation Criteria (Global)

Setelah Phase 1-3 selesai:
- 0 STUB views remaining
- 0 controller tanpa HasMiddleware di performance module
- 0 crash-prone null access di views
- Semua performance views bisa navigate + fetch + render data
- FE tests tetap 212+ pass
- BE tests tetap 429+ pass
<!-- OMO_INTERNAL_INITIATOR -->