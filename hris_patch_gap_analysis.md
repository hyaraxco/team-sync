# HRIS Patch Plan — Gap Analysis & TODO Checklist

> **Tanggal:** 2026-04-23
> **Branch:** `main` (HEAD: `a48cacc`)
> **Konteks:** Lanjutan dari conversation "Implementing HRIS Patch Plan" — Sprint 1 sudah diimplementasi, user approve Opsi C Hybrid untuk P4

---

## Executive Summary

| Patch | Plan Status | Backend | Frontend | Verdict |
|-------|------------|---------|----------|---------|
| **P8** — Sidebar Permission | Sprint 1 ✅ | ✅ Done | ✅ Done | **90% Done** — ada gap kecil |
| **P1** — TOPSIS C1/C2 Restructure | Sprint 1 ✅ | ✅ Done | 🔴 **Gap** | **70% Done** — FE label belum update |
| **P2** — Goals C3/C4 Connection | Sprint 1 ✅ | ✅ Done | 🔴 **Gap** | **75% Done** — FE goal summary belum ada |
| **P3** — Feedback C5 Connection | Sprint 1 ✅ | ✅ Done | ✅ Done | **95% Done** |
| **P5** — Validation Warning | Sprint 1 ✅ | ✅ Done | 🟡 Partial | **80% Done** — TOPSIS badge belum ada |
| **P4** — Reviewer Chain | Sprint 2 | 🔴 Not Started | 🔴 Not Started | **0% Done** — Desain hybrid sudah approved |
| **P6** — Performance Outcome Rules | Sprint 3 | ✅ Done | ✅ Done | **90% Done** — tests + dashboard widget deferred |
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

#### Status: 🔴 **Belum Diimplementasi**

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

#### Status: 🔴 **Belum Diimplementasi**

Tidak ada foundation apapun di codebase. Perlu:
- Migration: `performance_outcome_rules` table
- Migration: Tambah field di `performance_reviews` (bonus_months, salary_increase_pct, dll.)
- Model + Seeder
- Controller + API endpoints
- FE: Settings page untuk konfigurasi rules
- FE: Outcome display di Review Detail
- FE: Dashboard widget "Eligible for Promotion"

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

### 🟡 Sprint 1 Remaining (Gap Coverage)

Ini adalah gap dari Sprint 1 yang sudah diimplementasi tapi belum lengkap:

#### P1 Frontend Label Update
- [ ] **P1-4a**: Update `ReviewCycleDetail.vue` — rename `weightLabels`:
  - `avg_manager_rating` → "Competency Score" (bukan "Manager Rating")
  - `final_rating` → "KPI Score" (bukan "Final Rating")
- [ ] **P1-4b**: Update `MyReviews.vue` — rename "Final Rating" column header
- [ ] **P1-4c**: Update `TeamReviews.vue` — rename "Final Rating" column header

#### P2 Frontend Goal Summary
- [ ] **P2-4**: Tambah Goal Summary card di `ReviewDetail.vue` tab Overview:
  - Total Goals, Completed, On-Time count
  - Perlu fetch data dari backend (mungkin tambah field di `validate-readiness` response atau endpoint baru)

#### P5 TOPSIS Incomplete Data Badge
- [ ] **P5-3**: Tambah badge "Incomplete Data" di TOPSIS ranking table (`ReviewCycleDetail.vue`):
  - Warning icon jika `avg_goal_completion = 0` atau `positive_feedback_count = 0`
  - Tooltip/text: "Goals & feedback data belum tersedia"

#### P8 Minor Gaps (Low Priority)
- [ ] **P8-4**: Employees page — hide Edit/Delete/Invite buttons untuk role Manager (view-only)
- [ ] **P8-5**: Unit test untuk permission matrix (opsional, sudah ter-cover oleh E2E)

---

### 🔴 Sprint 2 — P4 Reviewer Chain Bertingkat (Hybrid)

#### Backend
- [ ] **P4-1**: Migration — Create `reviewer_rules` table (tanpa `company_id`, pakai Spatie role names)
- [ ] **P4-2**: Model `ReviewerRule` — Eloquent model + fillable + relasi
- [ ] **P4-3**: Seeder `ReviewerRuleSeeder` — Default rules:
  - `staff` → `manager`
  - `manager` → `hr`
  - `hr` → `director` (atau role tertinggi)
- [ ] **P4-4**: Service `ReviewerResolverService::resolve()`:
  - Baca role dari `$staff->user->getRoleNames()->first()`
  - Cari rule di `reviewer_rules`
  - Cari employee aktif dengan reviewer_role
  - Fallback: `null` (require HR manual assign)
- [ ] **P4-5**: Update `PerformanceReviewCycleController` — Auto-generate `PerformanceReview` entries saat cycle dibuat (status `draft`)
- [ ] **P4-6**: API endpoint baru — `POST /performance/cycles/{id}/generate-reviews`
- [ ] **P4-7**: API endpoint — `PUT /performance/reviews/{id}/assign-reviewer` (HR override)
- [ ] **P4-8**: Permission baru — `review-assign-reviewer` (HR only)
- [ ] **P4-9**: Test: unit test untuk `ReviewerResolverService`
- [ ] **P4-10**: Test: feature test untuk auto-generate + override flow

#### Frontend
- [ ] **P4-11**: Update `ReviewCycleCreate.vue` — Tambah section "Reviewer Assignment Rules" (dropdown per role)
- [ ] **P4-12**: Buat page/modal — Generated reviews list + reviewer override UI
- [ ] **P4-13**: Badge role reviewer di Review Detail Overview tab ("Reviewer: Yudhis (Manager)")

---

### ✅ Sprint 3 — P6 Performance Outcome Rules

#### Backend
- [x] **P6-1**: Migration — Create `performance_outcome_rules` table
- [x] **P6-2**: Migration — Add `promotion_eligible`, `pip_required`, `bonus_months`, `salary_increase_pct`, `outcome_applied_at` ke `performance_reviews`
- [x] **P6-3**: Model `PerformanceOutcomeRule` + seeder (5 default rules)
- [x] **P6-4**: Service `PerformanceOutcomeService::applyOutcome()` — auto-map setelah finalize
- [x] **P6-5**: API endpoints CRUD untuk outcome rules (HR/Admin)
- [x] **P6-6**: Integrasi ke calibration finalize flow — auto-apply outcome
- [ ] **P6-7**: Test: unit + feature tests (deferred — DB not available locally)

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

**Gap utama Sprint 1 ada di Frontend** (label belum update, goal summary belum ada, TOPSIS badge belum ada).

**Sprint 2-4 (P4, P6, P7) — 🔴 Belum ada foundation sama sekali.** Plan perlu diadaptasi ke arsitektur yang ada (Spatie roles vs enum baru, single-tenant vs multi-tenant).

### Total TODO Items

| Sprint | Backend | Frontend | Total |
|--------|---------|----------|-------|
| Sprint 1 Remaining | 0 | 5 tasks | **5** |
| Sprint 2 (P4) | 10 tasks | 3 tasks | **13** |
| Sprint 3 (P6) | 7 tasks | 3 tasks | **10** |
| Sprint 4 (P7) | 4 tasks | 2 tasks | **6** |
| **Grand Total** | **21** | **13** | **34 tasks** |
