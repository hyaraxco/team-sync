# HRIS Team Sync Pro — Patch Plan
## Performance Review & TOPSIS Improvement

> **Tujuan:** Dokumen ini berisi task-by-task patch plan yang dapat dikerjakan oleh AI agent di IDE.
> Setiap task memiliki context, acceptance criteria, dan contoh implementasi yang spesifik.

---

## Ringkasan Patch

| ID | Patch | Prioritas | Estimasi |
|----|-------|-----------|----------|
| P1 | Restrukturisasi TOPSIS: C1 & C2 dihitung otomatis dari section scores | 🔴 HIGH | M |
| P2 | Koneksi C3 & C4 ke Goals module | 🔴 HIGH | M |
| P3 | Koneksi C5 ke Feedback module | 🔴 HIGH | S |
| P4 | Reviewer chain bertingkat per role | 🔴 HIGH | L |
| P5 | Warning validasi data kosong sebelum Finalize Calibration | 🟡 MEDIUM | S |
| P6 | Performance Outcome Rules (bonus, promosi, PIP) | 🟡 MEDIUM | L |
| P7 | Review Template per role (section & weight berbeda) | 🟢 LOW | L |

---

## P1 — Restrukturisasi Cara Hitung C1 & C2 TOPSIS

### Context
Saat ini C1 (Manager Rating) dan C2 (Final Rating) diisi manual sebagai satu angka.
Masalahnya: C2 adalah turunan C1 setelah kalibrasi HR — keduanya tumpang tindih.
C1 dan C2 harus dihitung otomatis dari section scores yang sudah ada di assessment.

### Definisi Baru

```
C1 = Competency Score
   = weighted_avg dari section yang masuk kategori KOMPETENSI:
     - Communication & Collaboration (weight 20%)
     - Leadership & Core Values (weight 20%)
   = (comm_score * 0.20 + leadership_score * 0.20) / (0.20 + 0.20)
   → dinormalisasi ke skala 1–5

C2 = KPI Score
   = weighted_avg dari section yang masuk kategori KPI:
     - Technical Skills & Quality of Work (weight 25%)
     - Productivity & Time Management (weight 20%)
     - Initiative & Problem Solving (weight 15%)
   = (tech_score * 0.25 + productivity_score * 0.20 + initiative_score * 0.15) / (0.25 + 0.20 + 0.15)
   → jika HR mengisi Calibrated Rating pada section tersebut, gunakan nilai HR
   → jika tidak, gunakan Manager Assessment score
   → dinormalisasi ke skala 1–5
```

### Task List

**Task P1-1: Tambah field `topsis_category` pada tabel section/criteria**

```sql
-- Tambah kolom kategori pada tabel review_sections atau criteria_templates
ALTER TABLE review_sections
ADD COLUMN topsis_category VARCHAR(20) DEFAULT 'kpi'
CHECK (topsis_category IN ('kpi', 'competency', 'excluded'));

-- Seed data kategori
UPDATE review_sections SET topsis_category = 'competency'
WHERE name IN ('Communication & Collaboration', 'Leadership & Core Values');

UPDATE review_sections SET topsis_category = 'kpi'
WHERE name IN (
  'Technical Skills & Quality of Work',
  'Productivity & Time Management',
  'Initiative & Problem Solving'
);
```

**Task P1-2: Buat function/service `calculateC1C2(reviewId)`**

```typescript
// services/topsis.service.ts

interface SectionScore {
  name: string;
  weight: number;
  topsisCategory: 'kpi' | 'competency';
  managerScore: number;
  calibratedScore: number | null; // HR override, null jika tidak diisi
}

function calculateC1C2(sections: SectionScore[]): { c1: number; c2: number } {
  const competencySections = sections.filter(s => s.topsisCategory === 'competency');
  const kpiSections = sections.filter(s => s.topsisCategory === 'kpi');

  const getFinalScore = (s: SectionScore) =>
    s.calibratedScore !== null ? s.calibratedScore : s.managerScore;

  const weightedAvg = (items: SectionScore[]) => {
    const totalWeight = items.reduce((sum, s) => sum + s.weight, 0);
    const weightedSum = items.reduce((sum, s) => sum + getFinalScore(s) * s.weight, 0);
    return totalWeight > 0 ? weightedSum / totalWeight : 0;
  };

  return {
    c1: weightedAvg(competencySections), // Competency Score
    c2: weightedAvg(kpiSections),        // KPI Score
  };
}
```

**Task P1-3: Update TOPSIS calculation engine untuk menggunakan C1 & C2 dari function di atas**

- Ganti pengambilan nilai C1 dari field `manager_rating` manual → panggil `calculateC1C2().c1`
- Ganti pengambilan nilai C2 dari field `final_rating` manual → panggil `calculateC1C2().c2`
- Pastikan recalculation terjadi setiap kali:
  - Manager submit assessment
  - HR mengubah calibrated rating
  - HR finalize calibration

**Task P1-4: Update label di UI Criteria Weights Configuration**

```
SEBELUM          →    SESUDAH
─────────────────────────────
C1 Manager Rating     C1 Competency Score
C2 Final Rating       C2 KPI Score
C3 Goal Completion %  C3 Goal Completion %   (tetap)
C4 Goal Completion R  C4 Goal Completion R   (tetap)
C5 Positive Feedback  C5 Positive Feedback   (tetap)
```

### Acceptance Criteria
- [ ] C1 dihitung otomatis dari Communication + Leadership section scores
- [ ] C2 dihitung otomatis dari Technical + Productivity + Initiative section scores
- [ ] Jika HR mengisi Calibrated Rating pada section, nilai tersebut digunakan untuk C2
- [ ] TOPSIS recalculate otomatis setiap ada perubahan score
- [ ] Label di UI sudah diupdate

---

## P2 — Koneksi C3 & C4 ke Goals Module

### Context
C3 (Goal Completion %) dan C4 (Goal Completion Ratio) saat ini selalu bernilai 0
karena tidak terhubung ke data goals yang sudah ada di modul Team Goals / My Goals.
Ini membuat TOPSIS tidak akurat — Ci score 100% meski goals belum selesai.

### Definisi

```
C3 = Goal Completion %
   = (jumlah goals dengan status "Done" atau "Completed") 
     / (total goals yang di-assign ke employee dalam periode review cycle)
   × 100
   → range: 0–100

C4 = Goal Completion Ratio (On-Time)
   = (jumlah goals selesai SEBELUM atau TEPAT deadline)
     / (jumlah goals yang sudah Done/Completed)
   × 100
   → range: 0–100
   → jika tidak ada goals selesai, nilai = 0
```

### Task List

**Task P2-1: Pastikan goals memiliki field yang diperlukan**

```sql
-- Verifikasi tabel goals memiliki kolom berikut:
-- employee_id, status, due_date, completed_at, review_cycle_id (atau created_at untuk filter periode)

-- Jika belum ada review_cycle_id pada goals, tambahkan:
ALTER TABLE goals ADD COLUMN review_cycle_id UUID REFERENCES review_cycles(id);
-- Atau gunakan filter berdasarkan rentang tanggal cycle
```

**Task P2-2: Buat function/query `calculateGoalMetrics(employeeId, cycleId)`**

```typescript
// services/goals.service.ts

interface GoalMetrics {
  c3_goal_completion_pct: number;
  c4_goal_completion_ratio: number;
  total_goals: number;
  completed_goals: number;
  on_time_goals: number;
}

async function calculateGoalMetrics(
  employeeId: string,
  cycleStartDate: Date,
  cycleEndDate: Date
): Promise<GoalMetrics> {
  // Query goals milik employee dalam periode cycle
  const goals = await db.goals.findMany({
    where: {
      employee_id: employeeId,
      created_at: { gte: cycleStartDate, lte: cycleEndDate }
      // atau: review_cycle_id: cycleId jika sudah ada relasi
    }
  });

  const total = goals.length;
  const completed = goals.filter(g =>
    ['done', 'completed'].includes(g.status.toLowerCase())
  );
  const onTime = completed.filter(g =>
    g.completed_at && g.due_date && g.completed_at <= g.due_date
  );

  return {
    c3_goal_completion_pct: total > 0 ? (completed.length / total) * 100 : 0,
    c4_goal_completion_ratio: completed.length > 0 ? (onTime.length / completed.length) * 100 : 0,
    total_goals: total,
    completed_goals: completed.length,
    on_time_goals: onTime.length,
  };
}
```

**Task P2-3: Integrasi ke TOPSIS calculation engine**

- Panggil `calculateGoalMetrics()` saat TOPSIS akan dihitung
- Substitute nilai C3 dan C4 dari function ini
- Recalculate otomatis setiap ada perubahan status goal

**Task P2-4: Tampilkan goal summary di Review Detail halaman Overview**

Tambahkan card kecil di tab Overview:
```
┌─────────────────────────────────┐
│ 📊 Goal Summary                 │
│ Total Goals       : 8           │
│ Completed         : 6 (75%)     │
│ On-Time           : 5 (83%)     │
└─────────────────────────────────┘
```

### Acceptance Criteria
- [ ] C3 tidak lagi bernilai 0 jika employee punya goals yang sudah selesai
- [ ] C4 dihitung berdasarkan ketepatan waktu penyelesaian
- [ ] TOPSIS recalculate otomatis saat status goal berubah
- [ ] Goal summary tampil di Overview tab Review Detail

---

## P3 — Koneksi C5 ke Feedback Module

### Context
C5 (Positive Feedback) saat ini selalu bernilai 0.
Perlu dihitung dari modul Feedback yang sudah ada di sidebar.

### Definisi

```
C5 = Positive Feedback Score
   = jumlah feedback dengan sentiment "positive" atau rating ≥ 4
     yang diterima employee dalam periode review cycle
   → atau bisa juga: rata-rata rating feedback (skala 1–5)
   → pilih definisi yang sesuai dengan struktur tabel feedback yang ada
```

### Task List

**Task P3-1: Audit struktur tabel feedback yang sudah ada**

```sql
-- Cek kolom yang tersedia di tabel feedback
DESCRIBE feedback;
-- atau
SELECT column_name, data_type FROM information_schema.columns
WHERE table_name = 'feedbacks';
```

**Task P3-2: Buat function `calculateFeedbackScore(employeeId, cycleStartDate, cycleEndDate)`**

```typescript
async function calculateFeedbackScore(
  employeeId: string,
  cycleStartDate: Date,
  cycleEndDate: Date
): Promise<number> {
  const feedbacks = await db.feedbacks.findMany({
    where: {
      receiver_id: employeeId,
      created_at: { gte: cycleStartDate, lte: cycleEndDate }
    }
  });

  if (feedbacks.length === 0) return 0;

  // Opsi A: jumlah feedback positif (rating >= 4)
  const positiveFeedbacks = feedbacks.filter(f => f.rating >= 4);
  return positiveFeedbacks.length;

  // Opsi B: rata-rata rating (uncomment jika lebih sesuai)
  // const avgRating = feedbacks.reduce((sum, f) => sum + f.rating, 0) / feedbacks.length;
  // return avgRating;
}
```

**Task P3-3: Integrasi ke TOPSIS calculation engine**

- Substitute nilai C5 dari function ini
- Recalculate otomatis saat feedback baru diterima dalam periode cycle

### Acceptance Criteria
- [ ] C5 tidak lagi bernilai 0 jika employee punya feedback dalam periode cycle
- [ ] Definisi "positive feedback" terdokumentasi di kode (komentar/enum)
- [ ] TOPSIS recalculate otomatis saat feedback baru diterima

---

## P4 — Reviewer Chain Bertingkat Per Role

### Context
Saat ini semua employee direview oleh satu reviewer (Manager).
Masalah: Manager sendiri belum ada reviewernya, HR Staff direview oleh Manager biasa
padahal seharusnya HR Staff direview oleh HR Manager, dan HR Manager direview oleh CEO/Direktur.

### Hierarki Reviewer yang Benar

```
Staff/Employee     → direview oleh → Direct Manager
Manager            → direview oleh → Senior Manager / Dept Head / CEO
HR Staff           → direview oleh → HR Manager
HR Manager         → direview oleh → CEO / Direktur
CEO / Direktur     → direview oleh → Board / Owner (opsional)
```

### Task List

**Task P4-1: Tambah field `role` pada tabel employees (jika belum ada)**

```sql
-- Tambah enum role jika belum ada
CREATE TYPE employee_role AS ENUM (
  'staff',
  'manager',
  'senior_manager',
  'hr_staff',
  'hr_manager',
  'director',
  'ceo',
  'board'
);

ALTER TABLE employees
ADD COLUMN IF NOT EXISTS role employee_role DEFAULT 'staff';
```

**Task P4-2: Tambah tabel `reviewer_rules` untuk konfigurasi per perusahaan**

```sql
CREATE TABLE reviewer_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  company_id UUID NOT NULL REFERENCES companies(id),
  reviewee_role employee_role NOT NULL,
  reviewer_role employee_role NOT NULL,
  fallback_reviewer_id UUID REFERENCES employees(id), -- jika tidak ada yg punya role tersebut
  created_at TIMESTAMP DEFAULT now(),
  UNIQUE(company_id, reviewee_role)
);

-- Seed default rules
INSERT INTO reviewer_rules (company_id, reviewee_role, reviewer_role) VALUES
  ('{{company_id}}', 'staff',       'manager'),
  ('{{company_id}}', 'manager',     'senior_manager'),
  ('{{company_id}}', 'hr_staff',    'hr_manager'),
  ('{{company_id}}', 'hr_manager',  'ceo'),
  ('{{company_id}}', 'director',    'ceo');
```

**Task P4-3: Buat function `resolveReviewer(employeeId, cycleId)`**

```typescript
// services/reviewer.service.ts

async function resolveReviewer(
  employeeId: string,
  companyId: string
): Promise<string> { // returns reviewer employee_id
  // 1. Ambil role dari employee
  const employee = await db.employees.findUnique({ where: { id: employeeId } });

  // 2. Cari aturan reviewer berdasarkan role
  const rule = await db.reviewerRules.findFirst({
    where: { company_id: companyId, reviewee_role: employee.role }
  });

  if (!rule) {
    // Fallback: gunakan direct manager
    return employee.manager_id;
  }

  // 3. Cari employee dengan reviewer_role yang sesuai di company yang sama
  const reviewer = await db.employees.findFirst({
    where: {
      company_id: companyId,
      role: rule.reviewer_role,
      is_active: true
    }
  });

  // 4. Jika tidak ditemukan, gunakan fallback_reviewer_id atau direct manager
  return reviewer?.id ?? rule.fallback_reviewer_id ?? employee.manager_id;
}
```

**Task P4-4: Integrasi ke Review Cycle creation**

- Saat HR membuat review cycle dan assign reviewer, panggil `resolveReviewer()` sebagai default suggestion
- HR tetap bisa override manually jika diperlukan
- Tampilkan badge role reviewer di Overview tab (contoh: "Reviewer: Yudhis (Manager)")

**Task P4-5: Update UI Review Cycle Setup**

Tambahkan section di form pembuatan Review Cycle:
```
┌─────────────────────────────────────────────────┐
│ Reviewer Assignment Rules                       │
├─────────────────────────────────────────────────┤
│ Staff        → [Manager          ▼]             │
│ Manager      → [Senior Manager   ▼]             │
│ HR Staff     → [HR Manager       ▼]             │
│ HR Manager   → [CEO / Director   ▼]             │
└─────────────────────────────────────────────────┘
```

### Acceptance Criteria
- [ ] HR Staff direview oleh HR Manager, bukan Manager departemen lain
- [ ] HR Manager direview oleh CEO/Direktur
- [ ] Manager direview oleh Senior Manager atau Dept Head
- [ ] Reviewer assignment bisa dikonfigurasi per perusahaan
- [ ] HR tetap bisa override reviewer assignment secara manual

---

## P5 — Warning Validasi Data Sebelum Finalize Calibration

### Context
HR bisa menekan tombol "Finalize Calibration" meskipun data C3, C4, C5 masih 0.
Ini menyebabkan hasil TOPSIS tidak akurat dan tidak merepresentasikan performa sebenarnya.

### Task List

**Task P5-1: Buat function `validateReviewReadiness(reviewId)`**

```typescript
interface ValidationResult {
  isReady: boolean;
  warnings: string[];
  blockers: string[];
}

async function validateReviewReadiness(reviewId: string): Promise<ValidationResult> {
  const review = await getReviewWithMetrics(reviewId);
  const warnings: string[] = [];
  const blockers: string[] = [];

  // Cek C3 & C4
  if (review.metrics.total_goals === 0) {
    warnings.push('Employee tidak memiliki goals yang terdaftar dalam periode ini. C3 & C4 akan bernilai 0.');
  }

  // Cek C5
  if (review.metrics.feedback_count === 0) {
    warnings.push('Tidak ada feedback yang diterima employee dalam periode ini. C5 akan bernilai 0.');
  }

  // Cek Manager Assessment
  if (!review.manager_assessment_submitted) {
    blockers.push('Manager Assessment belum disubmit.');
  }

  return {
    isReady: blockers.length === 0,
    warnings,
    blockers,
  };
}
```

**Task P5-2: Tampilkan warning modal sebelum Finalize Calibration**

```
┌──────────────────────────────────────────────┐
│ ⚠️  Perhatian Sebelum Finalisasi             │
├──────────────────────────────────────────────┤
│ Terdapat data yang mungkin belum lengkap:    │
│                                              │
│ ⚠️ Tidak ada goals dalam periode ini.        │
│    C3 & C4 akan bernilai 0 di TOPSIS.       │
│                                              │
│ ⚠️ Tidak ada feedback diterima.              │
│    C5 akan bernilai 0 di TOPSIS.            │
│                                              │
│ Lanjutkan finalisasi?                        │
│                                              │
│ [Batal]          [Ya, Finalisasi Tetap]      │
└──────────────────────────────────────────────┘
```

**Task P5-3: Tampilkan badge "Incomplete Data" di TOPSIS jika C3/C4/C5 = 0**

Tambahkan indikator di halaman TOPSIS Ranking:
```
Agung Ramadhan  4.40  4.40  0.0%  0%  0  100.00%  Outstanding
                                              ⚠️ Data goals & feedback belum tersedia
```

### Acceptance Criteria
- [ ] Warning modal muncul sebelum Finalize jika C3/C4/C5 = 0
- [ ] HR bisa tetap finalize dengan konfirmasi
- [ ] Badge "Incomplete Data" muncul di TOPSIS jika data kurang
- [ ] Manager Assessment yang belum disubmit menjadi blocker (tidak bisa finalize)

---

## P6 — Performance Outcome Rules

### Context
Setelah TOPSIS menghasilkan Ci score dan Label (Outstanding, Exceeds Expectations, dll.),
belum ada mekanisme otomatis yang memetakan hasil ini ke keputusan bisnis:
bonus, kenaikan gaji, rekomendasi promosi, atau PIP.

### Task List

**Task P6-1: Buat tabel `performance_outcome_rules`**

```sql
CREATE TABLE performance_outcome_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  company_id UUID NOT NULL REFERENCES companies(id),
  label VARCHAR(50) NOT NULL,         -- 'Outstanding', 'Exceeds Expectations', dst.
  ci_score_min DECIMAL(5,2) NOT NULL, -- misal: 85.00
  ci_score_max DECIMAL(5,2) NOT NULL, -- misal: 100.00
  bonus_months DECIMAL(3,1),          -- misal: 3.0 bulan gaji
  salary_increase_pct DECIMAL(4,2),   -- misal: 10.00 (%)
  promotion_eligible BOOLEAN DEFAULT false,
  pip_required BOOLEAN DEFAULT false,
  created_at TIMESTAMP DEFAULT now(),
  UNIQUE(company_id, label)
);

-- Seed default rules (bisa diubah per perusahaan)
INSERT INTO performance_outcome_rules
  (company_id, label, ci_score_min, ci_score_max, bonus_months, salary_increase_pct, promotion_eligible, pip_required)
VALUES
  ('{{company_id}}', 'Outstanding',          85, 100, 3.0, 10.0, true,  false),
  ('{{company_id}}', 'Exceeds Expectations', 70,  84, 2.0,  7.5, false, false),
  ('{{company_id}}', 'Meets Expectations',   55,  69, 1.0,  5.0, false, false),
  ('{{company_id}}', 'Below Expectations',   40,  54, 0.0,  0.0, false, false),
  ('{{company_id}}', 'Unsatisfactory',        0,  39, 0.0,  0.0, false, true);
```

**Task P6-2: Tambahkan UI "Performance Outcome Rules" di Settings perusahaan**

```
┌────────────────────────────────────────────────────────────────────┐
│ Performance Outcome Rules                                          │
├──────────────────┬──────────┬──────────┬────────────┬─────────────┤
│ Label            │ Ci Range │ Bonus    │ Salary Inc.│ Promosi     │
├──────────────────┼──────────┼──────────┼────────────┼─────────────┤
│ Outstanding      │ 85–100%  │ 3 bulan  │ 10%        │ ✅ Eligible  │
│ Exceeds Expect.  │ 70–84%   │ 2 bulan  │ 7.5%       │ ❌           │
│ Meets Expect.    │ 55–69%   │ 1 bulan  │ 5%         │ ❌           │
│ Below Expect.    │ 40–54%   │ -        │ -          │ ❌           │
│ Unsatisfactory   │ 0–39%    │ -        │ - (PIP)    │ ❌           │
└──────────────────┴──────────┴──────────┴────────────┴─────────────┘
                                                    [+ Add Rule] [Edit]
```

**Task P6-3: Tampilkan Outcome di Review Detail setelah Finalize**

Setelah HR klik "Finalize Calibration", tampilkan section baru di Overview:
```
┌─────────────────────────────────────────────┐
│ 🎯 Performance Outcome                      │
│ TOPSIS Score : 87.5% — Outstanding          │
│ ─────────────────────────────────────────── │
│ 💰 Bonus          : 3 bulan gaji            │
│ 📈 Salary Inc.    : +10%                    │
│ 🚀 Promosi        : Eligible for Promotion  │
└─────────────────────────────────────────────┘
```

**Task P6-4: Tambah flag `promotion_eligible` dan `pip_required` di tabel review_results**

```sql
ALTER TABLE review_results
ADD COLUMN promotion_eligible BOOLEAN DEFAULT false,
ADD COLUMN pip_required BOOLEAN DEFAULT false,
ADD COLUMN bonus_months DECIMAL(3,1),
ADD COLUMN salary_increase_pct DECIMAL(4,2),
ADD COLUMN outcome_applied_at TIMESTAMP;
```

**Task P6-5: Tampilkan daftar karyawan "Eligible for Promotion" di halaman HR Dashboard**

Tambahkan card/widget di HR Dashboard:
```
🚀 Promotion Eligible This Cycle: 3 employees
   - Agung Ramadhan  (Outstanding, 100%)
   - ...
[View All]
```

### Acceptance Criteria
- [ ] Tabel outcome rules bisa dikonfigurasi per perusahaan
- [ ] Setelah Finalize Calibration, outcome (bonus/promosi/PIP) otomatis dihitung
- [ ] Outcome tampil di Review Detail
- [ ] Widget "Eligible for Promotion" muncul di HR Dashboard
- [ ] PIP flag muncul untuk karyawan dengan label Unsatisfactory

---

## P7 — Review Template Per Role (Opsional / Next Sprint)

### Context
Saat ini semua karyawan menggunakan section yang sama.
Untuk Manager, section "Leadership & Core Values" perlu bobot lebih besar,
dan perlu tambahan section "Team Performance" yang tidak relevan untuk staff biasa.

### Task List

**Task P7-1: Buat tabel `review_templates`**

```sql
CREATE TABLE review_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  company_id UUID NOT NULL REFERENCES companies(id),
  name VARCHAR(100) NOT NULL,            -- 'Staff Template', 'Manager Template', dll.
  applicable_roles employee_role[],      -- ['staff'] atau ['manager', 'senior_manager']
  is_default BOOLEAN DEFAULT false,
  created_at TIMESTAMP DEFAULT now()
);

CREATE TABLE review_template_sections (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  template_id UUID NOT NULL REFERENCES review_templates(id),
  section_name VARCHAR(100) NOT NULL,
  section_description TEXT,
  weight DECIMAL(5,2) NOT NULL,
  topsis_category VARCHAR(20) DEFAULT 'kpi',
  sort_order INT DEFAULT 0
);
```

**Task P7-2: Seed default templates**

```sql
-- Template untuk Staff
INSERT INTO review_templates (company_id, name, applicable_roles, is_default)
VALUES ('{{company_id}}', 'Staff Performance Template', '{staff}', true);

-- Section untuk Staff Template
INSERT INTO review_template_sections (template_id, section_name, weight, topsis_category) VALUES
  ('{{staff_template_id}}', 'Technical Skills & Quality of Work', 25.00, 'kpi'),
  ('{{staff_template_id}}', 'Productivity & Time Management',     20.00, 'kpi'),
  ('{{staff_template_id}}', 'Initiative & Problem Solving',       15.00, 'kpi'),
  ('{{staff_template_id}}', 'Communication & Collaboration',      20.00, 'competency'),
  ('{{staff_template_id}}', 'Leadership & Core Values',           20.00, 'competency');

-- Template untuk Manager (Leadership lebih berat, tambah Team Performance)
INSERT INTO review_templates (company_id, name, applicable_roles)
VALUES ('{{company_id}}', 'Manager Performance Template', '{manager, senior_manager}');

INSERT INTO review_template_sections (template_id, section_name, weight, topsis_category) VALUES
  ('{{mgr_template_id}}', 'Technical Skills & Quality of Work', 15.00, 'kpi'),
  ('{{mgr_template_id}}', 'Productivity & Time Management',     15.00, 'kpi'),
  ('{{mgr_template_id}}', 'Initiative & Problem Solving',       10.00, 'kpi'),
  ('{{mgr_template_id}}', 'Team Performance Score',             20.00, 'kpi'),   -- BARU
  ('{{mgr_template_id}}', 'Communication & Collaboration',      15.00, 'competency'),
  ('{{mgr_template_id}}', 'Leadership & Core Values',           25.00, 'competency'); -- Bobot naik
```

**Task P7-3: Update Review Cycle creation untuk memilih template per role**

Tambahkan dropdown di form Review Cycle:
```
Template per Role:
  Staff           → [Staff Performance Template    ▼]
  Manager         → [Manager Performance Template  ▼]
  HR Staff        → [Staff Performance Template    ▼]
  HR Manager      → [Manager Performance Template  ▼]
```

### Acceptance Criteria
- [ ] HR bisa memilih template berbeda per role saat buat Review Cycle
- [ ] Section dan bobot muncul sesuai template yang dipilih
- [ ] "Team Performance Score" muncul di assessment form Manager
- [ ] Perhitungan C1 dan C2 menggunakan section dari template yang aktif

---

## Urutan Pengerjaan yang Disarankan

```
Sprint 1 (Core Fix):
  P1 → P2 → P3 → P5

Sprint 2 (Governance):
  P4 (Reviewer Chain)

Sprint 3 (Business Value):
  P6 (Performance Outcome Rules)

Sprint 4 (Enhancement):
  P7 (Review Template per Role)
```

---

*Dokumen ini ditujukan untuk AI agent di IDE. Setiap task memiliki context, SQL/TypeScript pseudocode, dan acceptance criteria yang dapat langsung dijadikan prompt atau implementasi.*

*Versi: 1.0 | Dibuat: April 2026*

---

## P8 — Sidebar Permission Fix (Role-Based Menu Access)

### Context
Saat ini role Manager bisa mengakses menu **Pending Calibration** dan **Review Cycles**
padahal kedua menu tersebut seharusnya hanya bisa diakses oleh role HR.
Manager yang membuka menu tersebut seharusnya tidak bisa melihatnya sama sekali di sidebar,
dan jika mengakses langsung via URL pun harus diblokir.

### Hak Akses Per Menu (Target State)

| Menu | Staff | Manager | HR / HR Manager |
|---|---|---|---|
| Dashboard | ✅ | ✅ | ✅ |
| Projects | ✅ | ✅ | ✅ |
| Employees | ❌ | ✅ view only | ✅ full |
| Our Teams | ❌ | ✅ | ✅ |
| Attendance | ✅ own only | ✅ team | ✅ all |
| Payroll | ✅ own only | ✅ own only | ✅ all |
| Analytics | ❌ | ✅ team only | ✅ all |
| Team Reviews | ❌ | ✅ (isi assessment) | ✅ full |
| **Pending Calibration** | ❌ | ❌ | ✅ only |
| **Review Cycles** | ❌ | ❌ | ✅ only |
| My Reviews | ✅ | ✅ | ✅ |
| Team Goals | ❌ | ✅ | ✅ |
| My Goals | ✅ | ✅ | ✅ |
| Feedback | ✅ | ✅ | ✅ |

### Task List

**Task P8-1: Buat konfigurasi menu permissions terpusat**

```typescript
// config/menu-permissions.ts

export type AppRole = 'staff' | 'manager' | 'senior_manager' | 'hr_staff' | 'hr_manager' | 'director' | 'ceo';

export const MENU_PERMISSIONS: Record<string, AppRole[]> = {
  // Hanya HR yang boleh akses
  'pending-calibration': ['hr_staff', 'hr_manager', 'director', 'ceo'],
  'review-cycles':       ['hr_staff', 'hr_manager', 'director', 'ceo'],

  // Manager ke atas
  'team-reviews':        ['manager', 'senior_manager', 'hr_staff', 'hr_manager', 'director', 'ceo'],
  'our-teams':           ['manager', 'senior_manager', 'hr_staff', 'hr_manager', 'director', 'ceo'],
  'team-goals':          ['manager', 'senior_manager', 'hr_staff', 'hr_manager', 'director', 'ceo'],
  'analytics':           ['manager', 'senior_manager', 'hr_staff', 'hr_manager', 'director', 'ceo'],
  'employees':           ['manager', 'senior_manager', 'hr_staff', 'hr_manager', 'director', 'ceo'],

  // Semua role bisa akses (tidak perlu didefinisikan, default allow)
  // 'dashboard', 'projects', 'my-reviews', 'my-goals', 'feedback',
  // 'attendance', 'payroll', 'my-profile'
};

export function canAccess(menuKey: string, role: AppRole): boolean {
  const allowedRoles = MENU_PERMISSIONS[menuKey];
  if (!allowedRoles) return true; // tidak ada restriksi = semua bisa akses
  return allowedRoles.includes(role);
}
```

**Task P8-2: Filter sidebar items berdasarkan role user yang sedang login**

```typescript
// components/Sidebar.tsx (atau file sidebar yang digunakan)

import { canAccess } from '@/config/menu-permissions';

// Definisi semua menu items
const ALL_MENU_ITEMS = [
  { key: 'dashboard',           label: 'Dashboard',           icon: 'home',     section: 'GENERAL' },
  { key: 'projects',            label: 'Projects',            icon: 'folder',   section: 'GENERAL' },
  { key: 'employees',           label: 'Employees',           icon: 'users',    section: 'GENERAL' },
  { key: 'our-teams',           label: 'Our Teams',           icon: 'team',     section: 'GENERAL' },
  { key: 'attendance',          label: 'Attendance',          icon: 'calendar', section: 'GENERAL' },
  { key: 'payroll',             label: 'Payroll',             icon: 'wallet',   section: 'GENERAL' },
  { key: 'analytics',           label: 'Analytics',           icon: 'chart',    section: 'GENERAL' },
  { key: 'team-reviews',        label: 'Team Reviews',        icon: 'review',   section: 'PERFORMANCE' },
  { key: 'pending-calibration', label: 'Pending Calibration', icon: 'scale',    section: 'PERFORMANCE' },
  { key: 'review-cycles',       label: 'Review Cycles',       icon: 'cycle',    section: 'PERFORMANCE' },
  { key: 'my-reviews',          label: 'My Reviews',          icon: 'star',     section: 'PERFORMANCE' },
  { key: 'team-goals',          label: 'Team Goals',          icon: 'target',   section: 'PERFORMANCE' },
  { key: 'my-goals',            label: 'My Goals',            icon: 'flag',     section: 'PERFORMANCE' },
  { key: 'feedback',            label: 'Feedback',            icon: 'chat',     section: 'PERSONAL' },
];

// Filter berdasarkan role
const currentUser = useCurrentUser(); // hook untuk ambil user aktif

const visibleMenuItems = ALL_MENU_ITEMS.filter(menu =>
  canAccess(menu.key, currentUser.role)
);
```

**Task P8-3: Tambah Route Guard di setiap halaman yang dibatasi**

Meskipun menu sudah disembunyikan di sidebar, akses langsung via URL tetap harus diblokir.

```typescript
// middleware/auth.middleware.ts  (atau gunakan middleware framework yang dipakai)

import { canAccess } from '@/config/menu-permissions';

export function withRoleGuard(menuKey: string) {
  return (req, res, next) => {
    const user = req.currentUser;

    if (!canAccess(menuKey, user.role)) {
      // Option A: redirect ke dashboard
      return res.redirect('/dashboard');

      // Option B: return 403 page
      // return res.status(403).render('403', { message: 'Akses ditolak' });
    }

    next();
  };
}

// Contoh penggunaan di route definition:
// router.get('/pending-calibration', withRoleGuard('pending-calibration'), PendingCalibrationController.index);
// router.get('/review-cycles',       withRoleGuard('review-cycles'),       ReviewCyclesController.index);
```

**Task P8-4: Tambah view-only guard untuk menu Employees (role Manager)**

Manager bisa lihat daftar employee tapi tidak bisa edit/delete.

```typescript
// Cek permission detail di dalam halaman Employees
function canEditEmployee(currentUserRole: AppRole): boolean {
  return ['hr_staff', 'hr_manager', 'director', 'ceo'].includes(currentUserRole);
}

// Di komponen/halaman Employees:
// Sembunyikan tombol Edit, Delete, Invite jika canEditEmployee() = false
{canEditEmployee(currentUser.role) && (
  <Button>Edit Employee</Button>
)}
```

**Task P8-5: Test matrix — verifikasi akses per role**

Buat test case untuk memastikan semua permission bekerja benar:

```typescript
// tests/menu-permissions.test.ts

describe('Menu Permission Guard', () => {
  test('staff tidak bisa akses pending-calibration', () => {
    expect(canAccess('pending-calibration', 'staff')).toBe(false);
  });

  test('manager tidak bisa akses pending-calibration', () => {
    expect(canAccess('pending-calibration', 'manager')).toBe(false);
  });

  test('hr_staff bisa akses pending-calibration', () => {
    expect(canAccess('pending-calibration', 'hr_staff')).toBe(true);
  });

  test('manager tidak bisa akses review-cycles', () => {
    expect(canAccess('review-cycles', 'manager')).toBe(false);
  });

  test('hr_manager bisa akses review-cycles', () => {
    expect(canAccess('review-cycles', 'hr_manager')).toBe(true);
  });

  test('staff tidak bisa akses team-reviews', () => {
    expect(canAccess('team-reviews', 'staff')).toBe(false);
  });

  test('manager bisa akses team-reviews', () => {
    expect(canAccess('team-reviews', 'manager')).toBe(true);
  });

  test('semua role bisa akses dashboard', () => {
    const roles: AppRole[] = ['staff', 'manager', 'hr_staff', 'hr_manager', 'ceo'];
    roles.forEach(role => {
      expect(canAccess('dashboard', role)).toBe(true);
    });
  });
});
```

### Acceptance Criteria
- [ ] Menu `Pending Calibration` tidak muncul di sidebar untuk role Manager dan Staff
- [ ] Menu `Review Cycles` tidak muncul di sidebar untuk role Manager dan Staff
- [ ] Akses langsung via URL ke `/pending-calibration` oleh Manager → redirect ke dashboard
- [ ] Akses langsung via URL ke `/review-cycles` oleh Manager → redirect ke dashboard
- [ ] Menu `Employees` muncul untuk Manager tapi tombol Edit/Delete/Invite disembunyikan
- [x] Semua test case di P8-5 passed

### Urutan Pengerjaan P8
```
P8-1 → Buat config menu-permissions.ts
P8-2 → Update Sidebar component
P8-3 → Tambah route guard di halaman yang dibatasi
P8-4 → Tambah view-only guard di halaman Employees
P8-5 → Tulis & jalankan test matrix
```

---

## Urutan Pengerjaan Keseluruhan (Updated)

```
Sprint 1 (Core Fix & Security):
  P8 → P1 → P2 → P3 → P5
  (Sidebar permission fix dulu sebelum fitur baru)

Sprint 2 (Governance):
  P4 (Reviewer Chain Bertingkat)

Sprint 3 (Business Value):
  P6 (Performance Outcome Rules)

Sprint 4 (Enhancement):
  P7 (Review Template per Role)
```
