Kalo saya ingin kasih PR ini ke Codex tahapan plannya untuk kerjain ini gimana

Berdasarkan best practice Codex untuk long-horizon tasks, kuncinya adalah tiga file yang jadi kontrak antara kamu dan Codex: `SPEC.md`, `PLANS.md`, dan `IMPLEMENT.md`. Berikut struktur lengkapnya:[^1]

---

## Cara Kerja Codex untuk PR Sebesar Ini

Codex bekerja paling baik dengan **milestones kecil yang punya acceptance criteria sendiri** — bukan satu prompt besar. PR ini terlalu kompleks untuk dikerjakan satu shot. Strategi yang benar adalah:[^2][^1]

```
SPEC.md        → kontrak bisnis final (PR yang sudah fixed)
PLANS.md       → milestone sequence + acceptance criteria per milestone
IMPLEMENT.md   → runbook cara Codex harus beroperasi
```

---

## Step 0 — Siapkan Tiga File Sebelum Menyentuh Codex

### `SPEC.md`

Isi dengan **seluruh PR fixed version** yang sudah kita finalisasi — verbatim. Ini adalah source of truth yang tidak boleh Codex interpretasikan ulang. Tambahkan header ini di paling atas:

```markdown
# SPEC — Attendance for Fair Payroll Phase 1

> This file is the single source of truth.
> Codex must NOT deviate from any contract defined here.
> If anything is unclear, STOP and ask before implementing.
```

### `PLANS.md`

Isi dengan milestone sequence di bawah ini (section selanjutnya).

### `IMPLEMENT.md`

```markdown
# IMPLEMENT — Runbook for Codex

## Rules

- Read SPEC.md fully before starting any milestone.
- Work one milestone at a time. Do not start next until current passes validation.
- Keep diffs scoped: only touch files relevant to current milestone.
- After each milestone, run: `<your test command>` and fix failures before continuing.
- Do not add columns, fields, or logic not defined in SPEC.md.
- If a decision is ambiguous, write a comment `// AMBIGUOUS: [reason]` and stop.

## Source of Truth Order

1. SPEC.md (business contract)
2. PLANS.md (milestone sequence)
3. Existing codebase conventions (naming, folder structure)
```

---

## `PLANS.md` — Milestone Sequence

Ini urutan yang harus dikerjakan Codex. **Setiap milestone harus selesai dan hijau sebelum lanjut.**

---

### Milestone 1 — Foundation Migrations

**Scope:** Buat semua tabel baru sebagai database migrations, tanpa logic apapun.

**Tables yang dibuat:**

```
attendance_policies
holiday_calendars
hybrid_work_schedules
hybrid_schedule_overrides
attendance_policy_mismatches
leave_entitlements
attendance_periods
payroll_adjustments
```

**Perubahan ke tabel existing:**

```
attendance_records: tambah check_out_time, worked_minutes, actual_work_mode, policy_mismatch_flag, period_id
```

**Acceptance criteria:**

```bash
# Semua migration berjalan clean
rails db:migrate   # atau prisma migrate / equivalent stack kamu
# Tidak ada error, semua tabel terbuat dengan schema yang sesuai SPEC
```

**Prompt ke Codex:**

```
Read SPEC.md section "Entities & Data Model" completely.
Create database migrations for all new entities listed.
Use existing migration conventions in this codebase.
Do NOT add any model logic, validations, or associations yet.
Only schema. One migration file per entity.
```

---

### Milestone 2 — Model Layer + Seed Data

**Scope:** Buat model files dengan associations dan validations. Seed default policy per employment type.

**Acceptance criteria:**

```bash
rails db:seed    # seed berjalan tanpa error
# Query: AttendancePolicy.count == 4 (full_time, contract, intern, part_time)
# Query: LeaveEntitlement.count == expected rows per SPEC matrix
```

**Prompt ke Codex:**

```
Read SPEC.md sections "attendance_policies" and "leave_entitlements".
Create model files with associations and validations.
Create seed file that populates default attendance_policies and leave_entitlements
exactly as defined in the SPEC tables. Do not invent values not in SPEC.
```

---

### Milestone 3 — Holiday Calendar + Effective Working Days Calculator

**Scope:** Logic untuk menghitung `effective_working_days` per employee per periode, dengan join ke `holiday_calendars` dan `attendance_policies.default_working_weekdays`.

Ini adalah **milestone paling kritis secara logika** — harus punya unit test yang exhaustive sebelum lanjut.

**Acceptance criteria:**

```
# Test case 1: part-time Mon-Wed-Fri, bulan dengan holiday hari Selasa
# Result: effective_working_days TIDAK berkurang

# Test case 2: full-time Mon-Fri, bulan yang sama
# Result: effective_working_days BERKURANG 1

# Test case 3: holiday applies_to = ['full_time'] only
# part-time effective_working_days tidak terpengaruh
```

**Prompt ke Codex:**

```
Read SPEC.md section "Effective Working Days" and "holiday_calendars".
Implement a WorkingDaysCalculator service/module.
Input: employee_id, period (start_date, end_date)
Output: integer (effective_working_days)

Logic must:
1. Read employee's employment_type → get attendance_policy → get default_working_weekdays
2. Count calendar days in period that fall on those weekdays
3. Subtract holidays WHERE applies_to covers this employment_type
   AND holiday date falls on a scheduled weekday for this employment_type

Write unit tests for all 3 test cases in PLANS.md Milestone 3 before implementing.
```

---

### Milestone 4 — Hybrid Schedule Resolver

**Scope:** Logic untuk menentukan `planned_work_mode` seorang employee pada tanggal tertentu, dengan membaca `hybrid_work_schedules` dan `hybrid_schedule_overrides`.

**Acceptance criteria:**

```
# Test 1: tanggal ada di override approved → return override mode
# Test 2: tidak ada override → return base weekly schedule
# Test 3: employee bukan hybrid → return null (no planned mode)
# Test 4: tidak ada schedule setup → return null + flag
```

**Prompt ke Codex:**

```
Read SPEC.md sections "hybrid_work_schedules" and "hybrid_schedule_overrides".
Implement a HybridScheduleResolver service.
Input: employee_id, date
Output: { planned_mode: 'office' | 'remote' | null, source: 'override' | 'base_schedule' | 'none' }

Override takes priority over base schedule.
Only applies to employees with work_location = 'hybrid'.
Write unit tests for all 4 test cases in PLANS.md Milestone 4.
```

---

### Milestone 5 — Attendance Classification Engine

**Scope:** Core logic klasifikasi status harian per employee per tanggal. Bergantung pada Milestone 2, 3, 4.

Ini adalah **engine paling sentral** — semua rule dari SPEC harus diimplementasikan di sini dengan urutan yang benar.

**Acceptance criteria:**

```
# Semua rule klasifikasi dari SPEC harus punya test case:
present / late / half_day / absent (no checkout) /
absent (no attendance no leave) / holiday / each leave type /
leave with invalid entitlement → absent
```

**Prompt ke Codex:**

```
Read SPEC.md section "Classification Rules" completely.
Implement AttendanceClassifier service.
Input: employee_id, date
Output: { status: AttendanceStatus, source: 'attendance' | 'leave' | 'holiday' | 'absent' }

Rules must be applied IN ORDER as written in SPEC.
Rule 1: holiday check (use WorkingDaysCalculator holiday list)
Rule 2: approved leave check (validate entitlement + quota)
Rule 3: attendance record check (use worked_minutes for half_day vs absent)
Rule 4: fallback absent

worked_minutes = null must be treated as absent (not half_day).
Write one test per rule branch before implementing.
```

---

### Milestone 6 — Attendance Period Management

**Scope:** Logic lifecycle `open → review → locked`, auto-create via cron, dan correction window enforcement.

**Acceptance criteria:**

```
# Cron job membuat periode baru di awal bulan dengan status open
# Cutoff tercapai → auto transition ke review
# HR trigger generate payroll draft → locked
# Attempt correction saat locked → rejected dengan error yang jelas
```

**Prompt ke Codex:**

```
Read SPEC.md section "attendance_periods".
Implement:
1. AttendancePeriod model with status transitions (open → review → locked)
2. Cron job / scheduled task that creates new period on first day of month
   with default cutoff_date = 25th of that month
3. Guard method: can_submit_correction?(date) → false if period is locked
4. Guard method: can_generate_payroll?() → true only if no blocked employees

Write tests for each transition and guard.
```

---

### Milestone 7 — Leave Entitlement Validator

**Scope:** Service yang memvalidasi apakah suatu leave request sah untuk employee berdasarkan entitlement, quota, dan sick proof requirement.

**Acceptance criteria:**

```
# annual_leave melebihi quota → invalid
# sick_leave tanpa attachment → invalid
# leave type tidak eligible untuk employment_type → invalid
# emergency_leave tanpa reason → invalid
# valid leave → passes
```

**Prompt ke Codex:**

```
Read SPEC.md sections "leave_entitlements" and "Leave Entitlement Validator".
Implement LeaveEntitlementValidator service.
Input: leave_request object
Output: { valid: boolean, errors: string[] }

Validations:
1. employment_type eligible for leave_type
2. quota remaining (if quota_scope is annual or per_occurrence)
3. sick_leave requires attachment (pdf|jpg|jpeg|png, max 5MB)
4. emergency_leave requires non-empty reason field

This validator is called by AttendanceClassifier Rule 2.
Write tests for all 5 acceptance criteria cases.
```

---

### Milestone 8 — Payroll Calculation Engine

**Scope:** Formula kalkulasi gaji dari hasil classification, dengan breakdown lengkap. Bergantung pada semua milestone sebelumnya.

**Acceptance criteria:**

```
# full-time dengan 1 absent, 1 half_day, 1 annual_leave → benar
# part-time: effective_working_days beda dari full-time di bulan yang sama
# holiday mengurangi effective_working_days → daily_rate lebih tinggi
# sick_leave unpaid (no proof) → dipotong sebagai absent
# final_salary tidak pernah negatif (floor ke 0)
```

**Prompt ke Codex:**

```
Read SPEC.md section "Payroll Calculation" completely.
Implement PayrollCalculator service.
Input: employee_id, period_id
Output: PayrollDetail object with ALL fields defined in SPEC "Payroll Detail Breakdown"

Steps:
1. Get effective_working_days via WorkingDaysCalculator
2. Compute daily_rate = monthly_salary / effective_working_days
3. Classify each scheduled working day via AttendanceClassifier
4. Sum present_days, late_days, half_day_count, etc.
5. Apply deduction formula from SPEC
6. Round per existing rounding policy
7. Return full breakdown — no field from SPEC may be omitted

Write tests for all 5 acceptance criteria. Test part-time separately.
```

---

### Milestone 9 — Post-Lock Payroll Adjustments

**Scope:** Logic membuat `payroll_adjustments` setelah period locked, dan logic payroll draft bulan berikutnya menarik adjustments tersebut.

**Acceptance criteria:**

```
# Correction approved saat period locked → adjustment dibuat, payroll lama tidak berubah
# sick_proof_approved → amount_delta = daily_rate dari source_period × days
# Payroll draft bulan berikutnya memuat section adjustments terpisah
# Double-count tidak terjadi: adjustment tidak masuk ke formula bulan berjalan
```

**Prompt ke Codex:**

```
Read SPEC.md section "payroll_adjustments" including adjustment_kind enum and amount_delta rules.
Implement:
1. PayrollAdjustmentService: creates adjustment record when correction approved post-lock
   amount_delta = net delta (positive = pay more, negative = deduct more)
   source_daily_rate is read from source period's payroll record
2. Extension to PayrollCalculator: pull approved adjustments for target_period
   and append them as separate section in PayrollDetail.adjustments[]
   Do NOT mix adjustment amounts into main formula

Write test: absent Oct → sick proof approved Nov → Oct payroll unchanged,
Nov payroll has adjustment section with correct amount_delta.
```

---

### Milestone 10 — Readiness Workspace API

**Scope:** Endpoint yang mengembalikan readiness status per employee per periode untuk HR workspace.

**Acceptance criteria:**

```
# Employee dengan uncovered working day → blocked
# Employee dengan mismatch unresolved → warning
# Employee clean → ready
# Generate payroll blocked jika ada 1+ blocked employee
# Response shape sesuai SPEC section readiness
```

**Prompt ke Codex:**

```
Read SPEC.md section "Readiness Workspace".
Implement readiness endpoint: GET /api/payroll/readiness?period_id=X
Response must include per-employee status (ready|warning|blocked),
blocker_reasons[], warning_flags[], and org-level summary.

Implement can_generate_payroll? check that returns false + reasons
if any employee is blocked.

Write integration tests for all 3 employee status scenarios.
```

---

### Milestone 11 — Mismatch Lifecycle + Auto Escalation

**Scope:** Lifecycle management untuk `attendance_policy_mismatches` termasuk auto-escalation setelah 3 hari kerja.

**Acceptance criteria:**

```
# Mismatch dibuat saat actual_mode ≠ planned_mode
# Manager acknowledge → status = acknowledged
# 3 hari kerja tidak di-acknowledge → auto escalate ke HR
# HR resolve → resolved
```

**Prompt ke Codex:**

```
Read SPEC.md section "attendance_policy_mismatches" including lifecycle table.
Implement:
1. MismatchDetector: called after check-in for hybrid employees
2. Lifecycle transitions as defined in SPEC
3. Scheduled job: escalate mismatches pending_review for 3+ working days
   (use WorkingDaysCalculator to count working days since mismatch date)

Write tests for each transition including auto-escalation timing.
```

---

### Milestone 12 — E2E Integration Tests

**Scope:** Full flow tests yang cover semua E2E scenarios dari SPEC test plan.

**Prompt ke Codex:**

```
Read SPEC.md section "Test Plan - E2E".
Write integration/E2E tests for ALL scenarios listed:
1. part-time payroll fair day-count vs full-time same month
2. hybrid swap approved → no mismatch created
3. absent → sick proof approved post-lock → adjustment in next period
4. HR blocked from generating payroll until all blockers resolved
5. Finance sees payroll detail with daily_rate and effective_working_days explicit

Each test must run against a real test database (not mocks for these tests).
```

---

## Aturan Operasional Saat Bekerja dengan Codex

Berdasarkan best practice untuk long-horizon tasks:[^2][^1]

- **Gunakan `/plan` mode** sebelum setiap milestone baru — biarkan Codex baca konteks, baru eksekusi
- **Satu thread per milestone** — jangan campur Milestone 3 dan 4 dalam satu thread[^2]
- **Selalu run validation** setelah setiap milestone sebelum lanjut
- **Jika Codex menulis `// AMBIGUOUS`** — stop, jawab ambiguitynya, jangan biarkan Codex guess
- **Review diff setiap milestone** — pastikan Codex tidak expand scope ke file yang tidak relevan[^1]
- **Jika milestone gagal validation** — jangan lanjut, perbaiki di thread yang sama sebelum ke milestone berikutnya[^3]
- **Jangan menambahkan logic, field, atau kolom yang tidak ada di SPEC** — ini kontrak bisnis, bukan blueprint teknis. Deviating = breaking contract[^1]
