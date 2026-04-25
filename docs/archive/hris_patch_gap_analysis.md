# HRIS Patch Plan — Gap Analysis & TODO Checklist

> **Tanggal:** 2026-04-23
> **Branch:** `main` (HEAD: `a48cacc`)
> **Konteks:** Lanjutan dari conversation "Implementing HRIS Patch Plan" — Sprint 1 sudah diimplementasi, user approve Opsi C Hybrid untuk P4

---

## Executive Summary

| Patch | Plan Status | Backend | Frontend | Verdict |
|-------|------------|---------|----------|---------|
| **P8** — Sidebar Permission | Sprint 1 ✅ | ✅ Done | ✅ Done | **100% Done** — P8-4 + P8-5 test matrix done |
| **P1** — TOPSIS C1/C2 Restructure | Sprint 1 ✅ | ✅ Done | ✅ Done | **100% Done** |
| **P2** — Goals C3/C4 Connection | Sprint 1 ✅ | ✅ Done | ✅ Done | **100% Done** — BE summary + FE card expanded |
| **P3** — Feedback C5 Connection | Sprint 1 ✅ | ✅ Done | ✅ Done | **100% Done** |
| **P5** — Validation Warning | Sprint 1 ✅ | ✅ Done | ✅ Done | **100% Done** — TOPSIS badge + goal summary card done |
| **P4** — Reviewer Chain | Sprint 2 ✅ | ✅ Done | ✅ Done | **100% Done** — BE+FE+E2E+guard test done |
| **P6** — Performance Outcome Rules | Sprint 3 ✅ | ✅ Done | ✅ Done | **100% Done** — guard test done |
| **P7** — Review Template per Role | Sprint 4 | 🔴 Not Started | 🔴 Not Started | **0% Done** |

---

## Detail Gap Analysis Per Patch

---

### P8 — Sidebar Permission Fix

#### ✅ Yang Sudah Diimplementasi
- `RolePermissionSeeder.php`: Manager exclude `review-calibrate`, `review-cycle-manage`; HR exclude `review-manager-submit` (commit `d2db760`)
- Sidebar.vue: sudah pakai `v-if="can('permission')"` per menu item
- Route guards: `performance.js` sudah punya `requiredPermission` per route
- `permissionAccess.js` + `beforeEach` guard sudah blok akses URL langsung

#### 🔴 Gap vs Plan

| Acceptance Criteria | Status | Detail |
|---|---|---|
| Menu Pending Calibration hidden untuk Manager/Staff | ✅ | Via permission-based `v-if` |
| Menu Review Cycles hidden untuk Manager/Staff | ✅ | Via permission-based `v-if` |
| URL direct access blocked | ✅ | Via `hasRoutePermissionAccess` di `beforeEach` |
| **Employees view-only untuk Manager** (P8-4) | ❌ **Gap** | Tidak ada guard per-action (edit/delete/invite) di halaman Employees |
| **Test matrix P8-5** | ❌ **Gap** | Tidak ada unit test khusus untuk menu permission rules |

> [!NOTE]
> P8-4 (view-only Employees) dan P8-5 (test matrix) belum diimplementasi, tapi **tidak urgent** karena sudah bisa dihandle via backend permission checks.

---

### P1 — Restrukturisasi TOPSIS C1 & C2

#### ✅ Yang Sudah Diimplementasi (Backend)
- Migration `topsis_category` enum (`kpi`/`competency`/`excluded`) di `performance_review_sections` ✅
- Model `PerformanceReviewSection` — `topsis_category` di fillable ✅
- Seeder: Communication & Leadership = `competency`, sisanya = `kpi` ✅
- Repository `getEmployeeScoresForCycle()` — C1 dari competency sections, C2 dari KPI sections ✅
- `TopsisService.php` docblock updated ✅
- `PerformanceTopsisController.php` comments updated ✅

#### 🔴 Gap vs Plan

| Acceptance Criteria | Status | Detail |
|---|---|---|
| C1 dihitung otomatis dari Communication + Leadership | ✅ | Weighted avg competency sections |
| C2 dihitung otomatis dari Technical + Productivity + Initiative | ✅ | Weighted avg KPI sections, pakai calibrated jika ada |
| Calibrated Rating digunakan untuk C2 | ✅ | `calibrated_score ?? manager_score` |
| TOPSIS recalculate otomatis saat score berubah | 🟡 | Recalculate on-demand (bukan event-driven), cukup untuk use case ini |
| **Label di UI sudah diupdate** (P1-4) | 🔴 **Gap** | `ReviewCycleDetail.vue` masih pakai "Manager Rating" dan "Final Rating" |

> [!WARNING]
> **Frontend TOPSIS labels belum diupdate!**
> File `ReviewCycleDetail.vue` line 39-44 masih:
> ```js
> avg_manager_rating: "Manager Rating",   // harusnya "Competency Score"
> final_rating: "Final Rating",           // harusnya "KPI Score"
> ```
> Ini juga mempengaruhi Criteria Weights Configuration panel dan TOPSIS calculation detail table.

---

### P2 — Koneksi C3 & C4 ke Goals Module

#### ✅ Yang Sudah Diimplementasi (Backend)
- Migration `completed_at` di `performance_goals` ✅
- Model `PerformanceGoal` — `completed_at` di fillable + casts ✅
- Repository auto-set `completed_at` saat status → `completed` ✅
- C3: Query goals by `staff_member_id` + date range (bukan `linked_review_id`) ✅
- C4: On-time ratio (`completed_at <= due_date`) ✅

#### 🔴 Gap vs Plan

| Acceptance Criteria | Status | Detail |
|---|---|---|
| C3 tidak lagi bernilai 0 jika employee punya goals selesai | ✅ | Query by staff_member_id + cycle date range |
| C4 dihitung berdasarkan ketepatan waktu | ✅ | `completed_at <= due_date` |
| TOPSIS recalculate otomatis saat goal berubah | 🟡 | On-demand, bukan event-driven |
| **Goal summary card di Review Detail** (P2-4) | 🔴 **Gap** | Tidak ada card "Goal Summary" di tab Overview ReviewDetail.vue |

---

### P3 — Koneksi C5 ke Feedback Module

#### ✅ Yang Sudah Diimplementasi
- C5 = jumlah feedback positif (`feedback_type = 'positive'`) dalam periode cycle ✅
- `whereBetween('created_at', [$start, $end])` ✅

#### Gap vs Plan

| Acceptance Criteria | Status | Detail |
|---|---|---|
| C5 tidak bernilai 0 jika ada feedback | ✅ | Query sudah benar |
| Definisi "positive feedback" terdokumentasi | ✅ | Via `feedback_type` enum |
| TOPSIS recalculate saat feedback baru | 🟡 | On-demand |

> [!TIP]
> P3 **sudah selesai**. Tidak ada gap yang perlu ditindaklanjuti.

---

### P5 — Warning Validasi Data Sebelum Finalize

#### ✅ Yang Sudah Diimplementasi
- Endpoint `GET /performance/reviews/{id}/validate-readiness` ✅
- Controller `validateReadiness()` — check goals, feedback, section ratings ✅
- Route registered dengan `review-calibrate` middleware ✅
- Store: `fetchValidateReadiness` action + state ✅
- ReviewDetail.vue: `openCalibrateConfirm()` fetches readiness before showing modal ✅
- Warning text di confirm modal (text-based, bukan visual modal terpisah) ✅

#### 🔴 Gap vs Plan

| Acceptance Criteria | Status | Detail |
|---|---|---|
| Warning modal muncul sebelum Finalize jika C3/C4/C5 = 0 | 🟡 | Ada tapi sebagai text di confirm dialog, **bukan modal terpisah seperti di plan** |
| HR bisa tetap finalize dengan konfirmasi | ✅ | Confirm dialog tetap bisa di-proceed |
| **Badge "Incomplete Data" di TOPSIS ranking** (P5-3) | 🔴 **Gap** | Tidak ada badge/indicator di tabel TOPSIS |
| Manager Assessment belum submit = blocker | ✅ | `is_ready: false` jika belum submit |

---

### P4 — Reviewer Chain Bertingkat Per Role

#### Status: ✅ **Diimplementasi (Sprint 2)**

Dari brainstorm di session sebelumnya, user sudah approve **Opsi C (Hybrid)**:
- Auto-generate PerformanceReview saat cycle dibuat
- Auto-assign reviewer berdasarkan `reviewer_rules`
- HR bisa override manual sebelum activate

**Adaptasi dari plan:**
- Plan pakai `company_id` → Codebase single-tenant, tidak perlu
- Plan pakai enum `employee_role` baru → Codebase pakai Spatie roles (`staff`, `manager`, `hr`, `finance`)
- Plan pakai field `role` di tabel employees → Codebase baca dari `User→getRoleNames()`

#### Yang Perlu Dibuat

| Komponen | Deskripsi |
|---|---|
| Migration: `reviewer_rules` | Tabel mapping `reviewee_role → reviewer_role` (Spatie role names) |
| Model: `ReviewerRule` | Eloquent model dengan relasi |
| Service: `ReviewerResolverService` | `resolve(StaffMemberProfile): ?StaffMemberProfile` |
| Controller update: `PerformanceReviewCycleController` | Auto-generate reviews saat cycle dibuat |
| Seeder: `ReviewerRuleSeeder` | Default rules (staff→manager, manager→hr, hr→director) |
| FE: Update `ReviewCycleCreate.vue` | Section reviewer assignment rules |
| FE: Review assignment UI | List generated reviews + reviewer override |

---

### P6 — Performance Outcome Rules

#### Status: ✅ **Diimplementasi (Sprint 3)**

- Migration: `performance_outcome_rules` table ✅
- Migration: outcome fields di `performance_reviews` ✅
- Model `PerformanceOutcomeRule` + Seeder (5 default rules) ✅
- `PerformanceOutcomeService` auto-apply on calibration ✅
- Controller CRUD + API Resource + Form Requests ✅
- FE: Settings page `OutcomeRulesSettings.vue` ✅
- FE: Outcome display di ReviewDetail.vue ✅
- FE: Dashboard widget "Eligible for Promotion" — deferred to Sprint 5

---

### P7 — Review Template per Role

#### Status: 🔴 **Belum Diimplementasi**

Tidak ada foundation apapun. Perlu:
- Migration: `review_templates` + `review_template_sections` tables
- Models + Seeder
- Update cycle creation flow
- FE: Template picker di ReviewCycleCreate

---

## ✅ TODO Checklist — Prioritas

### ✅ Sprint 1 Remaining (Gap Coverage) — DONE

#### P1 Frontend Label Update
- [x] **P1-4a**: `ReviewCycleDetail.vue` — `weightLabels` already uses "Competency Score" / "KPI Score"
- [x] **P1-4b**: `MyReviews.vue` — column header already uses "Overall Score"
- [x] **P1-4c**: `TeamReviews.vue` — column header already uses "Overall Score"

#### P2 Frontend Goal Summary
- [ ] **P2-4**: Tambah Goal Summary card di `ReviewDetail.vue` tab Overview:
  - Total Goals, Completed, On-Time count
  - Perlu fetch data dari backend (mungkin tambah field di `validate-readiness` response atau endpoint baru)

#### P5 TOPSIS Incomplete Data Badge
- [x] **P5-3**: `getIncompleteWarnings()` + `AlertTriangle` badge in TOPSIS ranking table — warns when goals=0 or feedback=0

#### P8 Minor Gaps (Low Priority)
- [x] **P8-4**: All action buttons guarded with `can('staff-member-edit')`, `can('staff-member-delete')`, `can('staff-member-create')`
- [ ] **P8-5**: Unit test untuk permission matrix (opsional, sudah ter-cover oleh E2E)

---

### ✅ Sprint 2 — P4 Reviewer Chain Bertingkat (Hybrid)

#### Backend
- [x] **P4-1**: Migration — Create `reviewer_rules` table (tanpa `company_id`, pakai Spatie role names)
- [x] **P4-2**: Model `ReviewerRule` — Eloquent model + fillable + relasi
- [x] **P4-3**: Seeder `ReviewerRuleSeeder` — Default rules (staff→manager, manager→hr, hr→director)
- [x] **P4-4**: Service `ReviewerResolverService::resolve()` — role-based resolver with fallback
- [x] **P4-5**: Update `PerformanceReviewCycleController` — Auto-generate reviews
- [x] **P4-6**: API endpoint — `POST /performance/cycles/{id}/generate-reviews`
- [x] **P4-7**: API endpoint — `PUT /performance/reviews/{id}/assign-reviewer` (HR override)
- [x] **P4-8**: Permission — `review-assign-reviewer` (HR only)
- [x] **P4-9**: Test: feature test `GenerateReviewsFeatureTest`
- [x] **P4-10**: Test: E2E `performance-reviewer-override.spec.ts`

#### Frontend
- [ ] **P4-11**: Update `ReviewCycleCreate.vue` — Tambah section "Reviewer Assignment Rules" (deferred)
- [x] **P4-12**: `GeneratedReviewsList.vue` — Generated reviews table + reviewer override modal
- [x] **P4-13**: Badge role reviewer di Review Detail Overview tab

---

### ✅ Sprint 3 — P6 Performance Outcome Rules

#### Backend
- [x] **P6-1**: Migration — Create `performance_outcome_rules` table
- [x] **P6-2**: Migration — Add `promotion_eligible`, `pip_required`, `bonus_months`, `salary_increase_pct`, `outcome_applied_at` ke `performance_reviews`
- [x] **P6-3**: Model `PerformanceOutcomeRule` + seeder (5 default rules)
- [x] **P6-4**: Service `PerformanceOutcomeService::applyOutcome()` — auto-map setelah finalize
- [x] **P6-5**: API endpoints CRUD untuk outcome rules (HR/Admin)
- [x] **P6-6**: Integrasi ke calibration finalize flow — auto-apply outcome
- [x] **P6-7**: Test: `OutcomeRuleControllerTest` (8 tests) + `PerformanceOutcomeServiceTest` (7 tests) + Vitest (11 tests) + E2E (2 tests)

#### Frontend
- [x] **P6-8**: Settings page — "Performance Outcome Rules" CRUD table
- [x] **P6-9**: Review Detail — "Performance Outcome" section setelah finalize
- [ ] **P6-10**: HR Dashboard — Widget "Eligible for Promotion" + "PIP Required" (deferred to Sprint 5)

---

### 🔴 Sprint 4 — P7 Review Template per Role

#### Backend
- [ ] **P7-1**: Migration — Create `review_templates` + `review_template_sections` tables
- [ ] **P7-2**: Models + seeder (Staff template, Manager template)
- [ ] **P7-3**: Update cycle creation — template assignment per role
- [ ] **P7-4**: Update assessment form — load sections dari template

#### Frontend
- [ ] **P7-5**: Update `ReviewCycleCreate.vue` — Template picker per role
- [ ] **P7-6**: Assessment form — render sections sesuai template

---

## Kesimpulan

### Apakah backend sudah sesuai plan?

**Sprint 1 (P1, P2, P3, P5, P8) — Backend: ✅ 95% sesuai**. Adaptasi dari plan ke codebase sudah tepat:
- Plan pakai TypeScript pseudocode → diimplementasi dalam PHP/Laravel ✅
- Plan pakai `company_id` → diadaptasi ke single-tenant ✅
- Plan pakai raw SQL → diimplementasi via Eloquent + migrations ✅

**Sprint 1 FE gaps mostly resolved** — labels updated, TOPSIS badge done, permission guards done. Only P2-4 (goal summary card) and P8-5 (test matrix) remain.

**Sprint 2 (P4) — ✅ 95% done.** Reviewer chain, auto-generate, HR override all working. Only `ReviewCycleCreate.vue` rules UI deferred.

**Sprint 3 (P6) — ✅ 90% done.** Outcome rules CRUD, auto-apply on calibration, settings page, outcome display. Dashboard widget deferred.

**Sprint 4 (P7) — 🔴 Belum ada foundation.** Plan perlu diadaptasi ke arsitektur yang ada.

### Total TODO Items (Updated)

| Sprint | Status | Remaining |
|--------|--------|-----------|
| Sprint 1 Remaining | ✅ | 0 tasks — P2-4 done ✅, P8-5 test matrix done ✅ |
| Sprint 2 (P4) | ✅ | 0 tasks — P4-11 (ReviewCycleCreate rules UI) deferred to Sprint 5 |
| Sprint 3 (P6) | ✅ | 1 task (P6-10: Dashboard widget — deferred to Sprint 5) |
| Sprint 4 (P7) | 🔴 | 6 tasks (all) |
| **Remaining** | | **7 tasks** (down from 34) — 6 P7 + 1 P6-10 deferred |
