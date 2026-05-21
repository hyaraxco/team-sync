# Product Requirements Document (PRD)
# Team Sync — HRIS Indonesia

> **Version**: 2.1
> **Last Updated**: 2026-05-21
> **Status**: Active Development (Thesis Project)
> **Business Plans**: See `docs/plans/future_plan.md`

---

## 1. Product Overview

**Team Sync** adalah aplikasi HRIS (Human Resource Information System) berbasis web yang dirancang khusus untuk konteks workforce Indonesia. Aplikasi ini mencakup manajemen karyawan, absensi, penggajian, cuti, proyek, dan evaluasi kinerja dengan integrasi algoritma **TOPSIS** untuk penilaian KPI karyawan.

### 1.1 Latar Belakang

Aplikasi ini merupakan proyek skripsi mahasiswa yang mengintegrasikan algoritma **TOPSIS (Technique for Order of Preference by Similarity to Ideal Solution)** ke dalam sistem manajemen kinerja karyawan. Integrasi TOPSIS adalah **nilai jual utama** dan **kebutuhan akademis** agar skripsi diterima.

### 1.2 Target Market

| Segmen | Deskripsi |
|--------|-----------|
| **Primary** | UMKM dan perusahaan menengah Indonesia (10–500 karyawan) |
| **Secondary** | Startup dan perusahaan tech yang butuh HRIS modern |
| **Tertiary** | Perusahaan besar yang ingin solusi HRIS cost-effective |

### 1.3 Unique Selling Points

1. **TOPSIS KPI Integration** — Algoritma multi-criteria decision analysis untuk ranking dan evaluasi kinerja karyawan secara objektif
2. **Indonesian-First** — Dirancang untuk konteks Indonesia: BPJS, PPh 21 TER, THR, cuti bersama, format mata uang IDR
3. **Complete HRIS** — Bukan hanya absensi atau payroll, tapi satu platform terintegrasi

---

## 2. Feature Breakdown

### 2.1 Core Features

#### Authentication & Setup
- Login, forgot password, reset password
- Email verification
- Setup wizard (first-time installation)
- License activation

#### Onboarding Flow
```
Register → Email Verification → Welcome Screen → Company Setup
→ First Admin → Quick Tour → (Optional) Sample Data → Dashboard
```
- Guided setup wizard
- Sample data untuk coba fitur tanpa input manual
- Quick tour highlight 3-4 fitur utama

#### Dashboard
- Company statistics overview
- Employee self-service statistics
- Today's attendance overview
- Team pulse (manager view)

#### Staff Management
- CRUD karyawan (create, read, update, delete)
- Staff member profiles (personal info, job info, bank info, emergency contact)
- Staff statistics
- Search and filter
- Paginated list

#### Teams
- CRUD teams
- Add/remove team members
- Team statistics and chart data
- Team hierarchy

#### Attendance
- Check-in / Check-out
- My attendance history
- Attendance statistics
- Last attendance status
- Attendance periods management
- Attendance policies configuration
- Attendance corrections (request, approve, reject)
- Policy mismatches detection and resolution
- Hybrid work schedules
- Schedule overrides (approve/reject)
- Holiday calendar management

#### Leave Management
- Leave request submission
- Leave approval/rejection workflow
- Bulk action (approve/reject multiple)
- Leave proof upload and review
- Leave balances tracking
- Cuti bersama calendar
- Leave entitlements management
- Calendar view

#### Overtime Management
- Overtime request submission
- Overtime approval/rejection
- Overtime summary
- My overtime history

#### Notifications
- Real-time unread count
- Mark as read / mark all as read
- Paginated notification list

#### Meetings
- Schedule meetings
- Broadcast meeting links to divisions/teams
- Upcoming meetings view
- Meeting reminders (via scheduler)

---

### 2.2 Pro Features

#### Payroll System
- Generate payroll (batch processing)
- Payroll detail management
- PPh 21 calculation (TER 2024 method)
- BPJS deduction (JHT, JKK, JKM, JP, Kesehatan)
- Payroll approval workflow
- Mark as paid / Reopen
- Export Excel / PDF
- Payroll analytics and comparison
- Payroll reconciliation
- Activity logs
- Notification delivery tracking

#### THR (Tunjangan Hari Raya)
- THR simulation
- THR generation
- THR approval workflow
- Year summary

#### Project Management
- CRUD projects
- Project task management (CRUD)
- Task status workflow (todo → in_progress → review → done)
- Task comments & attachments
- Task status logs
- Project team membership
- Squad summary

#### Performance Reviews + TOPSIS KPI ⭐
- **Review Cycles** — Create, manage, generate reviews
- **Review Templates** — Customizable review templates
- **Self Assessment** — Employee self-evaluation
- **Manager Assessment** — Manager evaluates team members
- **Calibration** — HR calibrates review scores
- **Reviewer Assignment** — Assign reviewers to reviews
- **TOPSIS Ranking** — Multi-criteria ranking of employees
- **Outcome Rules** — Define outcomes based on performance tiers
- **Goals Management** — Create, track, update goals
- **Feedback** — Give and receive feedback

#### Advanced Analytics
- Executive summary
- Workforce analytics (turnover rate, average tenure, new hire trends, demographics)
- Attendance analytics (compliance rate, patterns, remote/office ratio, correction frequency)
- Leave analytics (utilization rate, balance trends, peak periods, approval turnaround, type distribution)
- Payroll analytics (cost trends, salary distribution, deduction analysis, cost per employee, processing time)
- Project analytics (timeline adherence, task velocity, overdue trends, resource utilization)
- Performance analytics (team summary, company summary, rating distribution, goal completion rate, feedback metrics)
- Export Excel / PDF

#### Settings
- HR settings management
- Finance settings management
- System settings management
- Payroll settings (including BPJS rate history, version diff)
- Payroll approval policies
- Payroll adjustments

---

## 3. TOPSIS KPI Integration (Academic Requirement)

### 3.1 Deskripsi Algoritma

**TOPSIS (Technique for Order of Preference by Similarity to Ideal Solution)** adalah metode multi-criteria decision analysis yang digunakan untuk menentukan ranking karyawan berdasarkan beberapa kriteria sekaligus.

### 3.2 Kriteria Penilaian

| Kriteria | Bobot | Deskripsi |
|----------|-------|-----------|
| Performance Score | 30% | Skor dari review cycle (self + manager assessment) |
| Attendance Rate | 20% | Kehadiran dan ketepatan waktu |
| Goal Completion | 25% | Persentase goals yang tercapai |
| Feedback Score | 15% | Skor dari feedback yang diterima |
| Tenure Factor | 10% | Lama bekerja dan pengalaman |

### 3.3 Implementasi

1. **Data Collection** — Sistem mengumpulkan data dari modul: performance reviews, attendance, goals, feedback
2. **Normalization** — Data dinormalisasi menggunakan metode vector normalization
3. **Weighted Matrix** — Matriks keputusan dikalikan dengan bobot kriteria
4. **Ideal Solutions** — Tentukan solusi ideal positif (A+) dan negatif (A-)
5. **Distance Calculation** — Hitung jarak setiap alternatif ke A+ dan A-
6. **Relative Closeness** — Hitung nilai preferensi (Ci*) untuk setiap karyawan
7. **Ranking** — Ranking karyawan berdasarkan Ci* (semakin tinggi semakin baik)

### 3.4 Endpoint

```
GET /api/v1/performance/cycles/{cycleId}/topsis-ranking
```

---

## 4. Technical Architecture

### 4.1 Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Vue 3 (Composition API, JavaScript) |
| Database | MySQL (prod) / SQLite (test) |
| Auth | Laravel Sanctum (SPA cookie-based) |
| Permissions | Spatie Laravel Permission |
| Search | Laravel Scout + Meilisearch |
| Queue | Database driver |
| Cache | Redis |
| Build | Vite 7 |
| State | Pinia 3 |
| Styling | Tailwind CSS 3 |

### 4.2 Backend Layering
- **Strict layer order**: Controller (thin) → Service (business logic) → Repository (data access) → Interface (contract)
- DTOs for cross-layer data transfer
- FormRequest for validation
- JsonResource for API responses
- Enums for fixed option sets

### 4.3 Frontend Patterns
- API calls in Pinia stores only (never in components)
- One store per domain
- Composables for reusable logic
- Views split by role: admin/ vs staff-member/

---

## 5. Constraints & Rules

### 5.1 Indonesian Context
- **Currency**: IDR, no decimal places
- **Date Format**: Y-m-d (API), Y-m-d H:i:s (datetime)
- **Timezone**: Configurable per company (default: WIB / Asia/Jakarta)
- **Tax**: PPh 21 TER 2024, +20% surcharge if no NPWP
- **BPJS**: Database-driven rates (JHT, JKK, JKM, JP, Kesehatan)

### 5.2 Role Hierarchy
| Role | Scope |
|------|-------|
| Staff | Self-service only |
| Manager | Team-scoped |
| HR | Workforce-wide (no payroll ops) |
| Finance | Payroll/THR owner |
| Superadmin | All permissions |

### 5.3 Key Enums
- `TaskStatus`: todo → in_progress → review → done / rejected / cancelled
- `ProjectStatus`: draft → planning → active → on_hold / completed / cancelled
- `AttendanceStatus`: present, late, absent, half_day, sick_leave, annual_leave
- `JobStatus`: active, on_leave, resigned
- Payroll status: processing → pending → approved → paid

---

## 6. Current Status

### Completed
- [x] Core HRIS features (staff, attendance, teams)
- [x] Payroll system dengan BPJS dan PPh 21 (TER 2024 + Desember annualized)
- [x] THR generation, approval, reopen, year summary
- [x] Leave management (request, approve/reject, bulk action, proof upload + re-upload)
- [x] Overtime management (request, approve, payroll link)
- [x] Project management (CRUD, tasks, comments, attachments dengan 5MB limit)
- [x] Performance reviews + TOPSIS ranking, Goals, Feedback (semua flow wired ke store)
- [x] Review templates management (create, update, delete)
- [x] Calibration flow (pending list + submit via ReviewDetail)
- [x] Analytics dashboard (workforce, attendance, leave, payroll, project, performance) + Excel/PDF export
- [x] Notifications (real-time toast polling, mark read, paginated list)
- [x] Meetings (schedule, broadcast, reminders via scheduler)
- [x] Multi-timezone support (per-company, formatToClientTimezone helper)
- [x] BPJS rate history + payroll setting versioning (audit trail)
- [x] Attendance period lock (with confirm modal)
- [x] EmptyState component adopted across 90+ views
- [x] Optimistic locking for payroll detail edits (ConcurrentModificationException + HTTP 409)
- [x] License system (basic)

### In Progress
- [ ] Sample data seeding UI for onboarding wizard (`docs/plans/future/onboarding-enhancements.md`)
- [ ] Quick tour component for first-time users (`docs/plans/future/onboarding-enhancements.md`)

### Future Plans
See `docs/plans/future_plan.md` for:
- SaaS pricing & business model
- Multi-instance hosting architecture
- Payment gateway integration
- Revenue projections
- Growth roadmap
