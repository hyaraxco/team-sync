# Indonesian Localization Cleanup — Final Batch

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking.
> **Branch:** New branch from `main` (do not mix with dark-mode PR #55)

**Goal:** Replace all remaining English-only user-facing text across all FE pages/components with concise Indonesian, following the same patterns as the previous slop cleanup.

**Architecture:** Pure template text changes — no behavior, logic, or data changes. Each module gets its own task. Changes are isolated to Vue template `<h1>`, `<h2>`, `<h3>`, `<p>`, and component prop strings only.

**Tech Stack:** Vue 3, JavaScript (no TS), Tailwind CSS

**Mapping principles:**
- Page headings (h1): `"X"` → `"X"` in ID, consistent with existing (`Pengajuan Cuti`, `Lembur Saya`)
- Section headings (h2/h3): same pattern, shorter is better
- Descriptions: AI slop → concise ID, same pattern as `"Buat siklus review pertama untuk mulai mengevaluasi performa tim."`
- Buttons: imperative ID. `"Create New Payroll"` → `"Buat Payroll Baru"`
- Loading/Error: `"Loading..."` → `"Memuat..."`, `"Unable to load"` → `"Gagal memuat"`

---

### Task 1: Performance Module — Page Headings

**Files — Modify:**
- `team-sync-fe/src/views/admin/performance/FeedbackGiven.vue:59` — `<h1>Feedback Given</h1>`
- `team-sync-fe/src/views/admin/performance/FeedbackReceived.vue:74` — `<h1>Feedback Received</h1>`
- `team-sync-fe/src/views/admin/performance/GiveFeedback.vue:75` — `<h1>Give Feedback</h1>`
- `team-sync-fe/src/views/admin/performance/GoalDetail.vue:143` — `<h1>Goal Details</h1>`
- `team-sync-fe/src/views/admin/performance/MyGoals.vue:260` — `<h1>My Goals</h1>`
- `team-sync-fe/src/views/admin/performance/MyReviews.vue:88` — `<h1>My Performance Reviews</h1>`
- `team-sync-fe/src/views/admin/performance/OutcomeRulesSettings.vue:117` — `<h1>Performance Outcome Rules</h1>`
- `team-sync-fe/src/views/admin/performance/PendingCalibration.vue:100` — `<h1>Pending Calibration</h1>`
- `team-sync-fe/src/views/admin/performance/ReviewCycleCreate.vue:100` — `<h1>Create Review Cycle</h1>`
- `team-sync-fe/src/views/admin/performance/ReviewCycleList.vue:129` — `<h1>Performance Review Cycles</h1>`
- `team-sync-fe/src/views/admin/performance/ReviewDetail.vue:447` — `<h1>Review Detail</h1>`
- `team-sync-fe/src/views/admin/performance/TeamGoals.vue:88` — `<h1>Team Goals</h1>`
- `team-sync-fe/src/views/admin/performance/TeamReviews.vue:112` — `<h1>Team Performance Reviews</h1>`
- `team-sync-fe/src/views/admin/performance/TemplateManagement.vue:150` — `<h1>Review Templates</h1>`

**Changes:**

| File | English | → Indonesian |
|------|---------|-------------|
| FeedbackGiven.vue | Feedback Given | Feedback Diberikan |
| FeedbackReceived.vue | Feedback Received | Feedback Diterima |
| GiveFeedback.vue | Give Feedback | Beri Feedback |
| GoalDetail.vue | Goal Details | Detail Sasaran |
| MyGoals.vue | My Goals | Sasaran Saya |
| MyReviews.vue | My Performance Reviews | Review Performa Saya |
| OutcomeRulesSettings.vue | Performance Outcome Rules | Aturan Outcome Performa |
| PendingCalibration.vue | Pending Calibration | Kalibrasi Tertunda |
| ReviewCycleCreate.vue | Create Review Cycle | Buat Siklus Review |
| ReviewCycleList.vue | Performance Review Cycles | Siklus Review Performa |
| ReviewDetail.vue | Review Detail | Detail Review |
| TeamGoals.vue | Team Goals | Sasaran Tim |
| TeamReviews.vue | Team Performance Reviews | Review Performa Tim |
| TemplateManagement.vue | Review Templates | Template Review |

- [ ] **Step 1:** Edit each file's h1 text to Indonesian
- [ ] **Step 2:** Verify no broken template syntax

---

### Task 2: Analytics Module — Page Headings

**Files — Modify:**
- `team-sync-fe/src/views/admin/analytics/AnalyticsDashboard.vue:247` — `<h1>Analytics</h1>` → `<h1>Analitik</h1>`

- [ ] **Step 1:** Edit h1 text
- [ ] **Step 2:** Verify

---

### Task 3: Attendance Module — Page Headings + Descriptions

**Files — Modify:**
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionList.vue:120` — `<h1>Attendance Corrections</h1>`
- `team-sync-fe/src/views/admin/attendance/AttendanceList.vue:142` — `<h1>Attendance Overview</h1>`
- `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue:6` — `<h1>Attendance Periods</h1>`
- `team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue:35` — `<h1>Attendance Logs</h1>`
- `team-sync-fe/src/views/admin/attendance/AttendanceSettings.vue:6` — `<h1>System Configuration</h1>`
- `team-sync-fe/src/views/admin/attendance/HolidayCalendar.vue:156-160` — `<h1>Holiday Calendar</h1>` + description `<p>Manage national holidays...</p>`
- `team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue:159-160` — `<h1>Hybrid Work Schedules</h1>` + description
- `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue:257` — description `<p>Manage and monitor employee leave requests.</p>`
- `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue:149` — `<h1>Overtime Management</h1>`
- `team-sync-fe/src/views/admin/attendance/PolicyMismatches.vue:6` — `<h1>Policy Mismatches</h1>`
- `team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue:36` — description `<p>Review historical attendance logs...</p>`

**Changes:**

| File | English | → Indonesian |
|------|---------|-------------|
| AttendanceCorrectionList | Attendance Corrections | Koreksi Absensi |
| AttendanceList | Attendance Overview | Ringkasan Absensi |
| AttendancePeriods | Attendance Periods | Periode Absensi |
| AttendanceRecordList | Attendance Logs | Log Absensi |
| AttendanceSettings | System Configuration | Konfigurasi Sistem |
| HolidayCalendar h1 | Holiday Calendar | Kalender Libur |
| HolidayCalendar desc | Manage national holidays... | Kelola hari libur nasional dan cuti bersama. |
| HybridScheduleList | Hybrid Work Schedules | Jadwal Hybrid |
| HybridScheduleList desc | Manage employee hybrid schedules... | Kelola jadwal hybrid dan pengajuan override. |
| LeaveRequestList desc | Manage and monitor... | Kelola dan pantau pengajuan cuti karyawan. |
| OvertimeManagement | Overtime Management | Manajemen Lembur |
| PolicyMismatches | Policy Mismatches | Mismatch Kebijakan |
| AttendanceRecordList desc | Review historical... | Lihat riwayat log absensi seluruh organisasi. |

- [ ] **Step 1:** Edit all h1 and description text
- [ ] **Step 2:** Verify

---

### Task 4: Staff-Member Views — Page Headings + Empty State

**Files — Modify:**
- `team-sync-fe/src/views/staff-member/MyPayslips.vue:138` — `<h2>My Payroll</h2>`
- `team-sync-fe/src/views/staff-member/MyPayslips.vue:245` — `<h3>All Payroll Periods</h3>`
- `team-sync-fe/src/views/staff-member/PayslipDetail.vue:221` — `<h1>My Payroll</h1>`
- `team-sync-fe/src/views/staff-member/PayslipDetail.vue:519` — EmptyState `title="Payroll detail not found"`
- `team-sync-fe/src/views/staff-member/HybridSchedules.vue:6` — `<h1>Hybrid Schedule</h1>`
- `team-sync-fe/src/views/staff-member/MyAttendance.vue:522` — `<h1>Attendance Overview</h1>`
- `team-sync-fe/src/views/staff-member/MyAttendance.vue:913` — Modal title `Request New Leave`
- `team-sync-fe/src/views/staff-member/HybridSchedules.vue:111` — Modal title `Request Schedule Override`

**Changes:**

| File | English | → Indonesian |
|------|---------|-------------|
| MyPayslips:138 | My Payroll | Payroll Saya |
| MyPayslips:245 | All Payroll Periods | Semua Periode Payroll |
| PayslipDetail:221 | My Payroll | Payroll Saya |
| PayslipDetail:519 | Payroll detail not found | Detail payroll tidak ditemukan |
| HybridSchedules:6 | Hybrid Schedule | Jadwal Hybrid |
| MyAttendance:522 | Attendance Overview | Ringkasan Absensi |
| MyAttendance:913 | Request New Leave | Ajukan Cuti Baru |
| HybridSchedules:111 | Request Schedule Override | Ajukan Override Jadwal |

- [ ] **Step 1:** Edit all text
- [ ] **Step 2:** Verify

---

### Task 5: Payroll Module — Page Headings + Descriptions

**Files — Modify:**
- `team-sync-fe/src/views/admin/payroll/PayrollDashboard.vue:241` — `<h1>Payroll Dashboard</h1>`
- `team-sync-fe/src/views/admin/payroll/PayrollDashboard.vue:284,566,600` — `<h2>Payroll Actions</h2>`
- `team-sync-fe/src/views/admin/payroll/PayrollDashboard.vue:292,574,608` — `<span>Create New Payroll</span>`
- `team-sync-fe/src/views/admin/payroll/PayrollDetail.vue:783` — `<h1>Payroll Detail</h1>`
- `team-sync-fe/src/views/admin/payroll/PayrollSettings.vue:367` — `<h1>Payroll Settings</h1>`
- `team-sync-fe/src/views/admin/payroll/PayrollComparison.vue:78` — `<h1>Month-over-Month Comparison</h1>`
- `team-sync-fe/src/views/admin/payroll/PayrollReadiness.vue:379` — `<h1>Attendance-to-Payroll Readiness</h1>`
- `team-sync-fe/src/views/admin/payroll/ThrManagement.vue:176` — `<h1>THR Management</h1>`
- `team-sync-fe/src/views/admin/payroll/PayrollAdjustmentQueue.vue:156` — `<h1>Adjustment Queue</h1>` (check exact text)
- `team-sync-fe/src/views/admin/payroll/PayrollAdjustmentQueue.vue:217` — description `<p>Filter by lifecycle status...</p>`
- `team-sync-fe/src/views/admin/payroll/PayrollComparison.vue:80` — description `<p>Compare payroll expenditures...</p>`
- `team-sync-fe/src/views/admin/payroll/PayrollApprovalMatrix.vue:152` — `<h1>Payroll Approval Matrix</h1>`

**Changes:**

| File | English | → Indonesian |
|------|---------|-------------|
| PayrollDashboard h1 | Payroll Dashboard | Dashboard Payroll |
| PayrollDashboard h2 | Payroll Actions | Aksi Payroll |
| PayrollDashboard span | Create New Payroll | Buat Payroll Baru |
| PayrollDetail | Payroll Detail | Detail Payroll |
| PayrollSettings | Payroll Settings | Pengaturan Payroll |
| PayrollComparison | Month-over-Month Comparison | Perbandingan Bulanan |
| PayrollReadiness | Attendance-to-Payroll Readiness | Kesiapan Absensi-ke-Payroll |
| ThrManagement | THR Management | Manajemen THR |
| PayrollAdjustmentQueue h1 | Adjustment Queue (check) | Antrian Penyesuaian |
| PayrollAdjustmentQueue desc | Filter by lifecycle status... | Filter berdasarkan status siklus... |
| PayrollComparison desc | Compare payroll... | Bandingkan pengeluaran payroll antar periode. |
| PayrollApprovalMatrix | Payroll Approval Matrix | Matriks Persetujuan Payroll |

- [ ] **Step 1:** Read exact text in each file (some may differ from grep output)
- [ ] **Step 2:** Edit all text
- [ ] **Step 3:** Verify

---

### Task 6: Settings + Auth + Other — Page Headings

**Files — Modify:**
- `team-sync-fe/src/views/admin/Settings.vue:94` — `<h1>Settings</h1>`
- `team-sync-fe/src/views/auth/Login.vue:45` — `<h1>Welcome back</h1>`
- `team-sync-fe/src/views/auth/ForgotPassword.vue:32` — `<h1>Check your email</h1>`
- `team-sync-fe/src/views/auth/ForgotPassword.vue:53` — `<h1>Reset password</h1>`
- `team-sync-fe/src/views/auth/ResetPassword.vue:38` — `<h1>Password updated</h1>`
- `team-sync-fe/src/views/auth/ResetPassword.vue:58` — `<h1>Invalid reset link</h1>`
- `team-sync-fe/src/views/auth/ResetPassword.vue:88` — `<h1>New password</h1>`
- `team-sync-fe/src/views/auth/VerifyEmailResult.vue:22` — `<h1>Email verification</h1>` (check exact)
- `team-sync-fe/src/views/staff-member/StaffMemberSuccess.vue:42` — `<h1>Added Successfully!</h1>`
- `team-sync-fe/src/views/NotFound.vue:4,6` — `<h1>404</h1>` + `<p>The page you are looking for...</p>`

**Changes:**

| File | English | → Indonesian |
|------|---------|-------------|
| Settings | Settings | Pengaturan |
| Login | Welcome back | Selamat Datang |
| ForgotPassword:32 | Check your email | Cek Email Anda |
| ForgotPassword:53 | Reset password | Atur Ulang Password |
| ResetPassword:38 | Password updated | Password Diperbarui |
| ResetPassword:58 | Invalid reset link | Tautan Reset Tidak Valid |
| ResetPassword:88 | New password | Password Baru |
| StaffMemberSuccess | Added Successfully! | Berhasil Ditambahkan! |
| NotFound desc | The page you are looking for... | Halaman yang Anda cari tidak ditemukan. |

- [ ] **Step 1:** Edit all text
- [ ] **Step 2:** Verify

---

### Task 7: Section Headings (h2/h3) — Batch 1: Team + Project

**Files — Modify:**
- `team-sync-fe/src/views/admin/team/TeamDetail.vue` — `Team Lead`, `Team Settings`, `Team Responsibilities`, `Team Members`, `Recent Activity`, `Team Resources`, `Danger Zone`, `Disband Team`, `Add Team Member`
- `team-sync-fe/src/views/admin/team/TeamCreate.vue` — `Team Information`, `Team Lead`, `Team Responsibilities`, `Team Settings`, `Select Team Lead`
- `team-sync-fe/src/views/admin/team/TeamEdit.vue` — same sections as TeamCreate
- `team-sync-fe/src/views/admin/project/ProjectCreate.vue` — `Project Information`, `Project Leader`, `Team Assignment`, `Project Settings`, `Select Project Leader`
- `team-sync-fe/src/views/admin/project/ProjectEdit.vue` — same sections as ProjectCreate
- `team-sync-fe/src/views/admin/project/ProjectList.vue` — `<h3>All Projects</h3>`
- `team-sync-fe/src/views/admin/project/ProjectDetail.vue` — section headings (read exact)
- `team-sync-fe/src/views/admin/staff-member/StaffMemberDetail.vue` — `Team Information`, `Contact Details`, `Personal Information`, `Emergency Contact`, `Address Information`, `Employment Details`, `Administrative Information`, `Bank Information`, `Danger Zone`, `Delete Staff Member Profile`

**Changes (template):**

| English | → Indonesian |
|---------|-------------|
| Team Information | Informasi Tim |
| Team Lead | Pimpinan Tim |
| Team Responsibilities | Tanggung Jawab Tim |
| Team Settings | Pengaturan Tim |
| Team Members | Anggota Tim |
| Recent Activity | Aktivitas Terkini |
| Team Resources | Sumber Daya Tim |
| Danger Zone | Zona Berbahaya |
| Disband Team | Bubarkan Tim |
| Add Team Member | Tambah Anggota |
| Project Information | Informasi Proyek |
| Project Leader | Pimpinan Proyek |
| Team Assignment | Penugasan Tim |
| Project Settings | Pengaturan Proyek |
| Contact Details | Detail Kontak |
| Personal Information | Informasi Pribadi |
| Emergency Contact | Kontak Darurat |
| Address Information | Informasi Alamat |
| Employment Details | Detail Pekerjaan |
| Administrative Information | Informasi Administrasi |
| Bank Information | Informasi Bank |
| All Projects | Semua Proyek |
| Select Team Lead | Pilih Pimpinan Tim |
| Select Project Leader | Pilih Pimpinan Proyek |

- [ ] **Step 1:** Edit each file's section headings
- [ ] **Step 2:** Verify

---

### Task 8: Section Headings (h2/h3) — Batch 2: Payroll Detail + Attendance

**Files — Modify:**
- `team-sync-fe/src/views/admin/payroll/PayrollDetail.vue` — `Payroll Draft Review`, `Staff Member Details`, `Approval Chain`, `Payroll Activity`, `Settings Used`, `Reconciliation Check`, `Actions`
- `team-sync-fe/src/views/admin/payroll/PayrollSettings.vue` — `Payroll Schedule`, `Calculation Rules`, `Payroll Note Template`, `Payroll Bank Partner`, `Preview Result`, `Version History`, `BPJS Rate History`
- `team-sync-fe/src/views/admin/attendance/AttendanceList.vue:313` — `<h2>Latest Leave Requests</h2>`
- `team-sync-fe/src/views/admin/attendance/AttendanceList.vue:383` — `<h2>Pending Corrections</h2>`
- `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue:30` — `<h2>Period History</h2>`
- `team-sync-fe/src/views/admin/attendance/AttendanceSettings.vue:220` — `<h2>Upcoming Holidays</h2>`
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionModal.vue:65` — `<h3>Request Correction</h3>`
- `team-sync-fe/src/views/admin/attendance/HolidayCalendar.vue` — section headings
- `team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue` — section headings

**Changes:**

| English | → Indonesian |
|---------|-------------|
| Payroll Draft Review | Review Draft Payroll |
| Staff Member Details | Detail Karyawan |
| Approval Chain | Rantai Persetujuan |
| Payroll Activity | Aktivitas Payroll |
| Settings Used | Pengaturan Digunakan |
| Reconciliation Check | Cek Rekonsiliasi |
| Actions | Aksi |
| Payroll Schedule | Jadwal Payroll |
| Calculation Rules | Aturan Kalkulasi |
| Payroll Note Template | Template Catatan Payroll |
| Payroll Bank Partner | Mitra Bank Payroll |
| Preview Result | Pratinjau Hasil |
| Version History | Riwayat Versi |
| BPJS Rate History | Riwayat Tarif BPJS |
| Latest Leave Requests | Pengajuan Cuti Terbaru |
| Pending Corrections | Koreksi Tertunda |
| Period History | Riwayat Periode |
| Upcoming Holidays | Libur Mendatang |
| Request Correction | Ajukan Koreksi |

- [ ] **Step 1:** Edit each file's section headings
- [ ] **Step 2:** Verify

---

### Task 9: Page Descriptions (p tags) — Confirmation + Error Text

**Files — Modify:**
- `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue:581` — `Confirm approval for this leave request.`
- `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue:609` — `Confirm rejection for this leave request.`
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionList.vue:258` — `Confirm approval for this attendance correction.`
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionList.vue:310` — `Confirm rejection for this attendance correction.`
- `team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue:426` — `Confirm approval for this hybrid schedule override request.`
- `team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue:478` — `Provide rejection notes for this override request.`
- `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue:109` — `Select an attendance period to view payroll readiness.`
- `team-sync-fe/src/views/admin/attendance/PolicyMismatches.vue:19` — `Unable to load policy mismatches. Please try again later.`
- `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue:38` — `Failed to load attendance periods. Please try again later.`
- `team-sync-fe/src/views/admin/Notifications.vue:202` — `Unable to load notifications.`
- `team-sync-fe/src/views/admin/payroll/PayrollSettings.vue:885` — `Please make sure to review your settings.`
- `team-sync-fe/src/views/admin/payroll/PayrollDetail.vue:1666` — `Review is complete. Finance can now mark this payroll as paid.`
- `team-sync-fe/src/views/staff-member/HybridSchedules.vue:26` — `Failed to load schedule. The service might be temporarily unavailable.`
- `team-sync-fe/src/views/admin/attendance/PolicyMismatches.vue:34` — `All attendance logs match their scheduled locations.`
- `team-sync-fe/src/components/admin/dashboard/EmployeeStatistics.vue:488` — `Failed to load activities.`
- `team-sync-fe/src/components/admin/staff-member/create/steps/Step2JobInfo.vue:493,511` — `No. BPJS Jamsostek...`, `No. BPJS Kesehatan...`
- `team-sync-fe/src/components/admin/staff-member/create/steps/Step2JobInfo.vue:538` — `Loading bank configuration...`
- `team-sync-fe/src/views/admin/performance/ReviewCycleCreate.vue:177` — `Cycle Start Date *` (form label)

**Changes:**

| English | → Indonesian |
|---------|-------------|
| Confirm approval for... | Konfirmasi persetujuan untuk... |
| Confirm rejection for... | Konfirmasi penolakan untuk... |
| Provide rejection notes... | Berikan catatan penolakan... |
| Select an attendance period... | Pilih periode absensi... |
| Unable to load... | Gagal memuat... |
| Failed to load... | Gagal memuat... |
| Please make sure to review... | Pastikan untuk meninjau pengaturan Anda. |
| Review is complete... | Review selesai. Finance dapat menandai payroll sebagai dibayar. |
| All attendance logs match... | Semua log absensi sesuai dengan lokasi terjadwal. |
| No. BPJS Jamsostek (JHT, JKK, JKM, JP) | No. BPJS Ketenagakerjaan (JHT, JKK, JKM, JP) |
| No. BPJS Kesehatan / JKN | No. BPJS Kesehatan / JKN (already fine) |
| Loading bank configuration... | Memuat konfigurasi bank... |
| Cycle Start Date * | Tanggal Mulai Siklus * |

- [ ] **Step 1:** Edit all text
- [ ] **Step 2:** Verify

---

### Task 10: Loading Text — Standardize All

**Files — Modify:** (~15 files)

Replace all `"Loading..."` with `"Memuat..."` across:
- `team-sync-fe/src/views/admin/attendance/AttendanceList.vue:319,389`
- `team-sync-fe/src/views/staff-member/StaffMemberProfile.vue:68`
- `team-sync-fe/src/views/staff-member/StaffMemberTeam.vue:135,320,399`
- `team-sync-fe/src/views/staff-member/MyAttendance.vue:663,825`
- `team-sync-fe/src/views/staff-member/PayslipDetail.vue:214`
- `team-sync-fe/src/components/admin/dashboard/EmployeeStatistics.vue` — already ID mostly
- `team-sync-fe/src/components/staff-member/attendance/AttendanceStatsCards.vue:129`
- `team-sync-fe/src/components/admin/team/detail/Chart.vue:172,199`
- `team-sync-fe/src/components/admin/NotificationPanel.vue:222`

- [ ] **Step 1:** Search all `"Loading..."` in .vue files
- [ ] **Step 2:** Replace with `"Memuat..."`
- [ ] **Step 3:** Also replace `"Loading staff member data..."` → `"Memuat data karyawan..."`
- [ ] **Step 4:** Also replace `"Loading your team workspace..."` → `"Memuat workspace tim..."`

---

### Task 11: Update Test Assertions

After all template changes, update any failing test assertions.

Run: `cd team-sync-fe && bun run test`

Expected: All 1022 tests pass.

If any fail, grep for the old English text in test files and update to match new Indonesian text.

- [ ] **Step 1:** Run test suite
- [ ] **Step 2:** Fix any failing test assertions
- [ ] **Step 3:** Run test suite again to confirm all pass

---

### Task 12: Create PR

After all changes and tests green:

```bash
git checkout -b feat/id-localization-cleanup
git add -A
git commit -m "chore: replace remaining English UI text with concise Indonesian"
git push origin feat/id-localization-cleanup
```

Then create PR via GitHub CLI or UI.

- [ ] **Step 1:** Create branch + commit + push
- [ ] **Step 2:** Create PR
- [ ] **Step 3:** Archive plan to `docs/plans/archive/`
