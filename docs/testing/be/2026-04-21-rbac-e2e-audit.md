# Audit E2E RBAC — Team Sync

> [!NOTE]
> **Executable Test**: [RolePermissionMatrixTest.php](../../../team-sync-be/tests/Unit/RolePermissionMatrixTest.php)

_Tanggal: 2026-04-21_
_Auditor: Project Manager (static code analysis)_
_Scope: Seluruh fitur, 4 role (Employee, Finance, HR, Manager)_

---

## 1. Feature Inventory

### 1.1 Auth & Profile (tanpa RBAC — auth:sanctum saja)

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Auth | Login | `POST /v1/login` | `/auth/login` |
| Auth | Forgot Password | `POST /v1/forgot-password` | `/auth/forgot-password` |
| Auth | Reset Password | `POST /v1/reset-password` | `/auth/reset-password` |
| Auth | Email Verify Send | `POST /v1/email/verify/send` | - |
| Auth | Email Verify | `GET /v1/email/verify/{id}/{hash}` | `/auth/verify-email` |
| Auth | Me | `GET /v1/me` | - |
| Auth | Update Profile | `PUT /v1/me` | - |
| Auth | Logout | `POST /v1/logout` | - |
| Profile | My Profile | `GET /v1/my-profile` | `/admin/my-profile` |
| Profile | Edit Profile | - | `/admin/my-profile/edit` |
| Profile | My Team | `GET /v1/my-team` | `/admin/my-team` |
| Profile | My Team Members | `GET /v1/my-team/members` | - |
| Profile | My Team Projects | `GET /v1/my-team/projects` | - |

### 1.2 Employee Management

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Employee | List | `GET /v1/employees` | `/admin/employees` |
| Employee | List Paginated | `GET /v1/employees/all/paginated` | - |
| Employee | Statistics | `GET /v1/employees/statistics` | - |
| Employee | Detail | `GET /v1/employees/{id}` | `/admin/employees/:id` |
| Employee | Create | `POST /v1/employees` | `/admin/employees/create` |
| Employee | Edit | `PUT /v1/employees/{id}` | `/admin/employees/:id/edit` |
| Employee | Delete | `DELETE /v1/employees/{id}` | - |
| Employee | Check Availability | `POST /v1/employees/check-availability` | - |
| Employee | Perf Statistics | `GET /v1/employees/{id}/performance-statistics` | - |

### 1.3 Team Management

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Team | List | `GET /v1/teams` | `/admin/teams` |
| Team | All Paginated | `GET /v1/teams/all/paginated` | - |
| Team | Statistics | `GET /v1/teams/statistics` | - |
| Team | Detail | `GET /v1/teams/{team}` | `/admin/teams/:id` |
| Team | Team Statistics | `GET /v1/teams/{team}/statistics` | - |
| Team | Chart Data | `GET /v1/teams/{team}/chart-data` | - |
| Team | Create | `POST /v1/teams` | `/admin/teams/create` |
| Team | Edit | `PUT /v1/teams/{team}` | `/admin/teams/edit/:id` |
| Team | Delete | `DELETE /v1/teams/{team}` | - |
| Team | Add Member | `POST /v1/teams/{team}/add-member` | - |
| Team | Remove Member | `POST /v1/teams/{team}/remove-member` | - |

### 1.4 Project & Task

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Project | List | `GET /v1/projects` | `/admin/projects` |
| Project | All Paginated | `GET /v1/projects/all/paginated` | - |
| Project | Statistics | `GET /v1/projects/statistics` | - |
| Project | Detail | `GET /v1/projects/{id}` | `/admin/projects/:id` |
| Project | Squad Summary | `GET /v1/projects/{id}/squad-summary` | - |
| Project | Create | `POST /v1/projects` | `/admin/projects/create` |
| Project | Edit | `PUT /v1/projects/{id}` | `/admin/projects/:id/edit` |
| Project | Delete | `DELETE /v1/projects/{id}` | - |
| Task | List | `GET /v1/project-tasks` | - |
| Task | All Paginated | `GET /v1/project-tasks/all/paginated` | - |
| Task | By Project | `GET /v1/projects/{id}/tasks` | - |
| Task | Detail | `GET /v1/project-tasks/{id}` | - |
| Task | Create | `POST /v1/project-tasks` | - |
| Task | Edit | `PUT /v1/project-tasks/{id}` | - |
| Task | Delete | `DELETE /v1/project-tasks/{id}` | - |
| Task | Comments (CRUD) | `GET/POST/PUT/DELETE /v1/project-tasks/{id}/comments[/{commentId}]` | - |
| Task | Attachments | `GET/POST/DELETE /v1/project-tasks/{id}/attachments[/{attachmentId}]` | - |
| Task | Status Logs | `GET /v1/project-tasks/{id}/status-logs` | - |

### 1.5 Attendance

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Attendance | List | `GET /v1/attendances` | `/admin/attendances` |
| Attendance | All Paginated | `GET /v1/attendances/all/paginated` | - |
| Attendance | Statistics | `GET /v1/attendances/statistics` | - |
| Attendance | Records | - | `/admin/attendance-records` |
| Attendance | Detail | `GET /v1/attendances/{id}` | - |
| Attendance | My Attendances | `GET /v1/my-attendances` | `/admin/attendance/my-attendances` |
| Attendance | My Statistics | `GET /v1/my-attendance-statistics` | - |
| Attendance | Last Attendance | `GET /v1/attendances/last-attendance` | - |
| Attendance | Check In | `POST /v1/attendances/check-in` | - |
| Attendance | Check Out | `POST /v1/attendances/check-out` | - |
| Attendance | Policy Mismatch Acknowledge | `POST /v1/attendance-policy-mismatches/{id}/acknowledge` | - |
| Attendance | Policy Mismatch Resolve | `POST /v1/attendance-policy-mismatches/{id}/resolve` | - |

### 1.6 Attendance Corrections

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Correction | List Paginated | `GET /v1/attendance-corrections/all/paginated` | `/admin/attendance-corrections` |
| Correction | My Corrections | `GET /v1/my-attendance-corrections` | - |
| Correction | Detail | `GET /v1/attendance-corrections/{id}` | - |
| Correction | Create | `POST /v1/attendance-corrections` | - |
| Correction | Approve | `POST /v1/attendance-corrections/{id}/approve` | - |
| Correction | Reject | `POST /v1/attendance-corrections/{id}/reject` | - |

### 1.7 Leave Requests

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Leave | List | `GET /v1/leave-requests` | `/admin/leave-requests` |
| Leave | All Paginated | `GET /v1/leave-requests/all/paginated` | - |
| Leave | My Requests | `GET /v1/my-leave-requests` | - |
| Leave | Detail | `GET /v1/leave-requests/{id}` | - |
| Leave | Create | `POST /v1/leave-requests` | - |
| Leave | Approve | `POST /v1/leave-requests/approve/{id}` | - |
| Leave | Reject | `POST /v1/leave-requests/reject/{id}` | - |
| Leave | Upload Proof | `POST /v1/leave-requests/{id}/proof` | - |
| Leave | Review Proof | `POST /v1/leave-requests/{id}/proof-review` | - |
| Leave | My Balances | `GET /v1/my-leave-balances` | - |

### 1.8 Payroll

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Payroll | List | `GET /v1/payrolls` | - |
| Payroll | All Paginated | `GET /v1/payrolls/all/paginated` | - |
| Payroll | Dashboard | - | `/admin/payroll` |
| Payroll | Statistics | `GET /v1/payrolls/statistics` | - |
| Payroll | Analytics | `GET /v1/payrolls/analytics` | - |
| Payroll | Detail | `GET /v1/payrolls/{id}` | `/admin/payroll/:id` |
| Payroll | Payroll Statistics | `GET /v1/payrolls/{id}/statistics` | - |
| Payroll | Details (items) | `GET /v1/payrolls/{id}/details` | - |
| Payroll | Reconciliation | `GET /v1/payrolls/{id}/reconciliation` | - |
| Payroll | Activity Logs | `GET /v1/payrolls/{id}/activity-logs` | - |
| Payroll | Notification Deliveries | `GET /v1/payrolls/{id}/notification-deliveries` | - |
| Payroll | Export Excel | `GET /v1/payrolls/{id}/export-excel` | - |
| Payroll | Export Report | `GET /v1/payrolls/export-report` | - |
| Payroll | Generate Readiness | `GET /v1/payrolls/generate-readiness` | - |
| Payroll | Readiness Dashboard | `GET /v1/payrolls/readiness-dashboard` | - |
| Payroll | Create | - | `/admin/payroll/create` |
| Payroll | Generate | `POST /v1/payrolls/generate` | - |
| Payroll | Update Detail | `PUT /v1/payroll-details/{id}` | - |
| Payroll | Approve | `POST /v1/payrolls/{id}/approve` | - |
| Payroll | Mark as Paid | `POST /v1/payrolls/{id}/mark-as-paid` | - |
| Payroll | Reopen | `POST /v1/payrolls/{id}/reopen` | - |
| Payroll | Resend Notifications | `POST /v1/payrolls/{id}/resend-notifications` | - |
| Payroll | Settings | `GET /v1/payroll-settings` | `/admin/payroll/settings` |
| Payroll | Settings History | `GET /v1/payroll-settings/history` | - |
| Payroll | Update Settings | `PUT /v1/payroll-settings` | - |
| Payslip | My Payslips | `GET /v1/my-payslips` | `/admin/my-payroll` |
| Payslip | Payslip Detail | `GET /v1/my-payslips/{id}` | `/admin/my-payroll/:id` |
| Payslip | Download | `GET /v1/payslips/{id}/download` | - |

### 1.9 Performance Management

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Review Cycle | List/CRUD | `GET/POST/PUT/DELETE /v1/performance/cycles` | `/admin/performance/cycles` |
| Review Cycle | Detail | `GET /v1/performance/cycles/{id}` | `/admin/performance/cycles/:id` |
| Review Cycle | Create | `POST /v1/performance/cycles` | `/admin/performance/cycles/create` |
| TOPSIS | Ranking | `GET /v1/performance/cycles/{id}/topsis-ranking` | - |
| Review | My Reviews | `GET /v1/performance/reviews/my-reviews` | `/admin/performance/reviews/my-reviews` |
| Review | Team Reviews | `GET /v1/performance/reviews/team-reviews` | `/admin/performance/reviews/team-reviews` |
| Review | Pending Calibration | `GET /v1/performance/reviews/pending-calibration` | `/admin/performance/reviews/pending-calibration` |
| Review | Active Sections | `GET /v1/performance/reviews/sections` | - |
| Review | Detail | `GET /v1/performance/reviews/{id}` | `/admin/performance/reviews/:id` |
| Review | Calibration Context | `GET /v1/performance/reviews/{id}/calibration-context` | - |
| Review | Self Assessment | `POST /v1/performance/reviews/{id}/self-assessment` | - |
| Review | Manager Assessment | `POST /v1/performance/reviews/{id}/manager-assessment` | - |
| Review | Calibrate | `POST /v1/performance/reviews/{id}/calibrate` | - |
| Goal | My Goals | `GET /v1/performance/goals/my-goals` | `/admin/performance/goals/my-goals` |
| Goal | Team Goals | `GET /v1/performance/goals/team-goals` | `/admin/performance/goals/team-goals` |
| Goal | CRUD | `GET/POST/PUT/DELETE /v1/performance/goals[/{id}]` | `/admin/performance/goals/:id` |
| Goal | Progress Updates | `GET/POST /v1/performance/goals/{id}/updates` | - |
| Feedback | Received | `GET /v1/performance/feedback/received` | `/admin/performance/feedback/received` |
| Feedback | Given | `GET /v1/performance/feedback/given` | `/admin/performance/feedback/given` |
| Feedback | Give | `POST /v1/performance/feedback` | `/admin/performance/feedback/give` |
| Feedback | Detail | `GET /v1/performance/feedback/{id}` | - |
| Feedback | Acknowledge | `POST /v1/performance/feedback/{id}/acknowledge` | - |

### 1.10 Analytics

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Analytics | Dashboard | - | `/admin/analytics` |
| Analytics | Executive Summary | `GET /v1/analytics/executive-summary` | - |
| Analytics | Workforce | `GET /v1/analytics/workforce` | - |
| Analytics | Attendance | `GET /v1/analytics/attendance` | - |
| Analytics | Leave | `GET /v1/analytics/leave` | - |
| Analytics | Payroll | `GET /v1/analytics/payroll` | - |
| Analytics | Projects | `GET /v1/analytics/projects` | - |
| Analytics | Export Excel | `GET /v1/analytics/export/excel` | - |
| Analytics | Export PDF | `GET /v1/analytics/export/pdf` | - |
| Analytics | Enhanced (20+ sub-endpoints) | `GET /v1/analytics/workforce/*`, `attendance/*`, `leave/*`, `payroll/*`, `projects/*`, `performance/*` | - |

### 1.11 Dashboard & Notifications

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Dashboard | Statistics | `GET /v1/dashboard/statistics` | `/admin/dashboard` |
| Dashboard | My Statistics | `GET /v1/dashboard/my-statistics` | - |
| Dashboard | Today Overview | `GET /v1/dashboard/today-attendance-overview` | - |
| Notification | Unread Count | `GET /v1/my-notifications/unread-count` | - |
| Notification | My Notifications | `GET /v1/my-notifications` | `/admin/notifications` |
| Notification | Mark as Read | `POST /v1/my-notifications/{id}/mark-as-read` | - |

### 1.12 Options (Reference Data)

| Domain | Feature | BE Endpoint | FE Route |
|--------|---------|------------|----------|
| Options | Departments, Types, Statuses, etc. | `GET /v1/options/*` (12 endpoints) | - |

---

## 2. RBAC Matrix

### Legenda
- ✅ = Full access
- 👁 = Read-only / limited
- ❌ = No access
- ⚠ = Partial (ada tapi incomplete)

### 2.1 Permission per Role (dari RolePermissionSeeder.php)

| Permission | Employee | Finance | HR | Manager |
|-----------|----------|---------|-----|---------|
| **Dashboard** | | | | |
| dashboard-menu | ✅ | ✅ | ✅¹ | ✅ |
| dashboard-view | ✅ | ✅ | ✅¹ | ✅ |
| **Profile** | | | | |
| profile-menu | ✅ | ✅ | ✅ | ✅ |
| profile-view | ✅ | ✅ | ✅ | ✅ |
| **Team** | | | | |
| team-menu | ❌ | ❌ | ✅ | ✅ |
| team-list | ❌ | ❌ | ✅ | ✅ |
| team-create | ❌ | ❌ | ✅ | ✅ |
| team-edit | ❌ | ❌ | ✅ | ✅ |
| team-delete | ❌ | ❌ | ✅ | ✅ |
| team-view | ✅ | ❌ | ✅² | ✅ |
| **Employee** | | | | |
| employee-menu | ❌ | ✅ | ✅ | ✅ |
| employee-list | ✅ | ✅ | ✅ | ✅ |
| employee-create | ❌ | ❌ | ✅ | ✅ |
| employee-edit | ❌ | ❌ | ✅ | ✅ |
| employee-delete | ❌ | ❌ | ✅ | ✅ |
| **Project** | | | | |
| project-menu | ✅ | ❌ | ✅ | ✅ |
| project-statistic | ❌ | ❌ | ✅ | ✅ |
| project-list | ✅ | ❌ | ✅ | ✅ |
| project-create | ❌ | ❌ | ✅ | ✅ |
| project-edit | ❌ | ❌ | ✅ | ✅ |
| project-delete | ❌ | ❌ | ✅ | ✅ |
| **Task** | | | | |
| task-menu | ✅ | ❌ | ✅ | ✅ |
| task-list | ✅ | ❌ | ✅ | ✅ |
| task-create | ✅ | ❌ | ✅ | ✅ |
| task-edit | ✅ | ❌ | ✅ | ✅ |
| task-delete | ❌ | ❌ | ❌ | ✅ |
| **Attendance** | | | | |
| attendance-menu | ❌ | ❌ | ✅ | ✅ |
| attendance-list | ❌ | ❌ | ✅ | ✅ |
| attendance-my-attendances | ✅ | ✅ | ✅ | ✅ |
| attendance-my-statistics | ❌ | ❌ | ✅ | ✅ |
| attendance-check-in | ✅ | ✅ | ✅ | ✅ |
| attendance-check-out | ✅ | ✅ | ✅ | ✅ |
| attendance-last-attendance | ✅ | ✅ | ✅ | ✅ |
| **Attendance Correction** | | | | |
| attendance-correction-list | ❌ | ❌ | ✅ | ✅ |
| attendance-correction-create | ✅ | ✅ | ✅ | ✅ |
| attendance-correction-approve | ❌ | ❌ | ✅ | ✅ |
| **Leave Request** | | | | |
| leave-request-menu | ✅ | ❌³ | ✅ | ✅ |
| leave-request-list | ❌ | ❌ | ✅ | ✅ |
| leave-request-create | ✅ | ❌³ | ✅ | ✅ |
| leave-request-approve | ❌ | ❌ | ✅ | ✅ |
| leave-request-my-requests | ✅ | ❌³ | ✅ | ✅ |
| **Payroll** | | | | |
| payroll-menu | ❌ | ✅ | ✅ | ❌ |
| payroll-list | ❌ | ✅ | ✅ | ❌ |
| payroll-create | ❌ | ❌ | ✅ | ❌ |
| payroll-edit | ❌ | ✅ | ❌⁴ | ❌ |
| payroll-delete | ❌ | ❌ | ❌ | ❌ |
| payroll-process | ❌ | ✅ | ❌ | ❌ |
| payroll-statistics | ❌ | ✅ | ❌⁴ | ❌ |
| payslip-view | ✅ | ✅ | ✅ | ✅ |
| **Analytics** | | | | |
| analytics-menu | ❌ | ✅ | ✅ | ✅ |
| analytics-view | ❌ | ✅ | ✅ | ✅ |
| analytics-export | ❌ | ✅ | ✅ | ✅ |
| **Performance** | | | | |
| performance-menu | ❌⁵ | ❌⁵ | ✅ | ✅ |
| review-cycle-manage | ❌ | ❌ | ✅ | ✅ |
| review-self-submit | ✅ | ✅ | ✅ | ✅ |
| review-manager-submit | ❌ | ❌ | ✅ | ✅ |
| review-calibrate | ❌ | ❌ | ✅ | ✅ |
| goal-create-own | ✅ | ✅ | ✅ | ✅ |
| goal-assign-team | ❌ | ❌ | ✅ | ✅ |
| feedback-give | ✅ | ✅ | ✅ | ✅ |
| performance-analytics-view | ❌ | ❌ | ✅ | ✅ |

**Catatan:**
1. HR dapat `dashboard-*` via prefix match `dashboard-`
2. HR dapat `team-view` via `$employeeSpecific` merge
3. ⚠ Finance TIDAK punya leave request permissions meskipun punya selfServiceBaseline (leave-request ada di selfServiceBaseline tapi Manager exclude-nya — Finance pakai explicit list yang include selfServiceBaseline, jadi Finance PUNYA leave-request-menu/create/my-requests)
4. HR TIDAK punya payroll-edit dan payroll-statistics — hanya list dan create
5. Employee dan Finance TIDAK punya performance-menu, artinya tidak bisa akses FE route performance sama sekali

**Koreksi poin 3:** Setelah re-check, Finance **PUNYA** selfServiceBaseline yang include leave-request-menu/create/my-requests. Jadi Finance bisa manage leave sendiri. ✅

---

## 3. Gap Analysis

### 🔴 CRITICAL

#### GAP-001: Notification Controller — Tidak Ada Permission Check
- **Severity**: Critical
- **Category**: Missing permission definition — SECURITY LEAK
- **Location**: `app/Http/Controllers/NotificationController.php` — entire class
- **Description**: `NotificationController` TIDAK implement `HasMiddleware` interface dan TIDAK punya middleware permission apapun. Siapapun yang authenticated bisa akses semua notification endpoints.
- **Impact**: Rendah secara teknis (endpoint sudah scope ke `$request->user()` untuk data sendiri), tapi melanggar pattern RBAC konsisten. Tidak ada `notification-*` permission defined di PermissionSeeder.
- **Affected role(s)**: Semua
- **Actual Risk**: LOW (data sudah user-scoped) — downgrade ke **Medium**

#### GAP-002: Performance Goal Controller — Tidak Ada Middleware Permission
- **Severity**: Critical
- **Category**: Missing permission definition — SECURITY LEAK
- **Location**: `app/Http/Controllers/PerformanceGoalController.php` — entire class
- **Description**: `PerformanceGoalController` TIDAK implement `HasMiddleware`. Semua method (CRUD goals, progress updates) hanya dilindungi `auth:sanctum`. Siapapun authenticated bisa create/update/delete goal milik orang lain.
- **Impact**: Employee biasa bisa `DELETE /v1/performance/goals/{id}` untuk hapus goal orang lain. Bisa juga assign goal ke orang lain via `POST /v1/performance/goals`.
- **Affected role(s)**: Employee, Finance (seharusnya terbatas)

#### GAP-003: Performance Review Controller — Tidak Ada Middleware Permission
- **Severity**: Critical
- **Category**: Missing permission definition — SECURITY LEAK
- **Location**: `app/Http/Controllers/PerformanceReviewController.php` — entire class
- **Description**: Seperti GoalController, TIDAK ada middleware permission. Method `show()` bisa dipakai untuk melihat review siapapun tanpa ownership check. `submitManagerAssessment` tidak check apakah user benar-benar manager dari reviewee.
- **Impact**: Employee bisa lihat detail review orang lain (`GET /v1/performance/reviews/{id}`). Bisa submit manager assessment padahal bukan manager. Review data sangat sensitif.
- **Affected role(s)**: Employee, Finance

#### GAP-004: Performance Feedback Controller — Tidak Ada Middleware Permission
- **Severity**: Critical
- **Category**: Missing permission definition — SECURITY LEAK
- **Location**: `app/Http/Controllers/PerformanceFeedbackController.php` — entire class
- **Description**: Tidak ada middleware. `show()` dan `acknowledge()` bisa diakses siapapun.
- **Impact**: Employee bisa lihat feedback orang lain, acknowledge feedback yang bukan miliknya.
- **Affected role(s)**: Semua

#### GAP-005: EmployeeProfileResource — Expose Data Sensitif ke Semua Role
- **Severity**: Critical
- **Category**: Data leak in resource
- **Location**: `app/Http/Resources/EmployeeProfileResource.php:17-45`
- **Description**: Resource selalu mengembalikan field sensitif: `identity_number` (NIK/KTP), `npwp`, `bpjs_ketenagakerjaan`, `bpjs_kesehatan`, `ptkp_status`, `address`, `phone`, `date_of_birth`, `bank_information`, `emergency_contacts` — TANPA cek role siapa yang request.
- **Impact**: Employee dengan permission `employee-list` bisa call `GET /v1/employees/{id}` dan lihat NIK, NPWP, BPJS, bank info, alamat employee lain. Finance bisa lihat semua data personal.
- **Affected role(s)**: Employee, Finance (seharusnya tidak lihat data sensitif employee lain)

#### GAP-006: JobInformationResource — Expose `monthly_salary` ke Semua Role
- **Severity**: Critical
- **Category**: Data leak in resource
- **Location**: `app/Http/Resources/JobInformationResource.php:25`
- **Description**: `monthly_salary` ter-expose via `EmployeeProfileResource → JobInformationResource` ke siapapun yang bisa akses employee detail.
- **Impact**: Employee biasa yang punya `employee-list` bisa lihat gaji employee lain.
- **Affected role(s)**: Employee, Finance (gaji seharusnya hanya visible untuk HR/Finance di konteks payroll)

### 🟠 HIGH

#### GAP-007: Dashboard `getStatistics` dan `getTodayAttendanceOverview` — Tidak Ada Data Scoping
- **Severity**: High
- **Category**: Missing data scoping
- **Location**: `app/Http/Controllers/DashboardController.php:30-63`
- **Description**: `dashboard-view` permission dimiliki semua role. `getStatistics()` mengembalikan data company-wide (total employees, total payroll, etc.) tanpa scope per role. Employee bisa lihat total payroll company.
- **Impact**: Employee bisa lihat statistik sensitif company (total salary expenditure, total headcount, etc.)
- **Affected role(s)**: Employee

#### GAP-008: Analytics Controller — Semua Endpoint Cuma Cek `analytics-view`
- **Severity**: High
- **Category**: Insufficient granularity
- **Location**: `app/Http/Controllers/AnalyticsController.php:22-25`
- **Description**: Semua 20+ analytics endpoint (termasuk payroll cost trends, salary distribution, deduction analysis) hanya dilindungi 1 permission: `analytics-view`. Finance, HR, dan Manager semuanya bisa akses SEMUA analytics termasuk data yang seharusnya restricted.
- **Impact**: Manager bisa lihat salary distribution dan payroll cost trends padahal tidak punya akses payroll module.
- **Affected role(s)**: Manager (seharusnya tidak lihat payroll analytics)

#### GAP-009: Performance Employee/Finance — Punya Baseline Permission Tapi FE Blokir
- **Severity**: High
- **Category**: BE-FE mismatch
- **Location**: BE: `RolePermissionSeeder.php` selfServiceBaseline vs FE: `performance.js:7`
- **Description**: Employee dan Finance punya `review-self-submit`, `goal-create-own`, `feedback-give` via selfServiceBaseline, TAPI **TIDAK punya `performance-menu`**. FE memerlukan `performance-menu` untuk akses parent route. Artinya: mereka punya permission BE tapi tidak bisa navigate di FE.
- **Impact**: Employee/Finance tidak bisa submit self-assessment, create goals, atau give feedback via UI meskipun API endpoint-nya accessible. Tapi via direct API call, mereka BISA karena controller-nya bahkan tidak punya middleware (GAP-002/003/004).
- **Affected role(s)**: Employee, Finance

#### GAP-010: Leave Balance — Tidak Ada Permission Check
- **Severity**: High
- **Category**: Missing permission definition
- **Location**: `routes/api.php:111` — `GET /v1/my-leave-balances`
- **Description**: Endpoint `my-leave-balances` tidak ada di LeaveRequestController middleware, dan LeaveBalanceController perlu dicek. Route hanya protected by `auth:sanctum`.
- **Impact**: Mungkin rendah karena data sudah user-scoped, tapi inkonsisten.
- **Affected role(s)**: Semua

#### GAP-011: Finance — Tidak Punya Leave Request Permission Secara Explicit
- **Severity**: High
- **Category**: Design gap
- **Location**: `RolePermissionSeeder.php:114-130`
- **Description**: Finance role punya selfServiceBaseline yang include `leave-request-menu/create/my-requests`. Ini benar. TAPI Finance TIDAK punya `attendance-menu`, `attendance-list`, `attendance-my-statistics` — artinya Finance tidak bisa monitor kehadirannya sendiri via admin attendance view.
- **Impact**: Finance hanya bisa lihat attendance via `my-attendances` (selfServiceBaseline) tapi tidak bisa lihat admin view attendance records.
- **Affected role(s)**: Finance

### 🟡 MEDIUM

#### GAP-012: Payroll — No `payroll-delete` Assigned to Any Role
- **Severity**: Medium
- **Category**: Orphan permission
- **Location**: `PermissionSeeder.php:84` defines `payroll-delete`, `RolePermissionSeeder.php` — none assigned
- **Description**: `payroll-delete` permission exists tapi tidak di-assign ke role manapun. Route `DELETE /v1/payrolls/{id}` ada via apiResource tapi apiResource di-limit `.only(['index', 'show'])`. Jadi route memang tidak ada, tapi permission-nya yatim piatu.
- **Impact**: Rendah — tidak ada route, tapi bisa membingungkan.
- **Affected role(s)**: Tidak ada

#### GAP-013: Payroll Download Payslip — Tidak Ada Ownership Check
- **Severity**: Medium
- **Category**: Missing data scoping
- **Location**: `app/Http/Controllers/PayslipController.php:26` — `payslip-view` middleware
- **Description**: `GET /v1/payslips/{id}/download` hanya cek `payslip-view` permission. Semua role punya `payslip-view`. Employee A bisa download payslip Employee B kalau tahu ID-nya.
- **Impact**: Leak data gaji employee lain via PDF download.
- **Affected role(s)**: Employee, Finance, HR, Manager

#### GAP-014: Options Endpoints — Tidak Ada Permission Check
- **Severity**: Medium
- **Category**: Missing permission definition
- **Location**: `routes/api.php:144-155` — 12 options endpoints
- **Description**: Semua options endpoint (departments, employment types, etc.) hanya dilindungi `auth:sanctum` tanpa permission check. Tapi ini reference data yang tidak sensitif.
- **Impact**: Rendah — reference data.
- **Affected role(s)**: Tidak ada yang bermasalah

#### GAP-015: Project `index` dan `getAllPaginated` — Tidak Ada Data Scoping
- **Severity**: Medium
- **Category**: Missing data scoping
- **Location**: `app/Http/Controllers/ProjectController.php:46-50` dan `app/Repositories/ProjectRepository`
- **Description**: Employee punya `project-list` tapi bisa lihat SEMUA project, bukan hanya yang dia assigned. `EnsureProjectMembership` middleware hanya ada di `show` dan `getSquadSummary`.
- **Impact**: Employee bisa lihat daftar dan detail semua project di company.
- **Affected role(s)**: Employee

#### GAP-016: Attendance Correction — `attendance-correction-list` Tidak Di-Assign ke HR Explicitly
- **Severity**: Medium
- **Category**: Potential gap — VERIFIED OK
- **Location**: `RolePermissionSeeder.php`
- **Description**: HR menggunakan prefix-based assignment `attendance-*`. Ini SUDAH include `attendance-correction-list` dan `attendance-correction-approve`. **Tidak ada gap.** Manager juga punya via `permissionsAllExcept`. VERIFIED OK.
- **Impact**: None.
- **Affected role(s)**: None — false alarm, SKIP

#### GAP-017: PayrollSettings — `payroll-statistics` Guard Untuk Read Access
- **Severity**: Medium
- **Category**: Questionable permission mapping
- **Location**: `app/Http/Controllers/PayrollSettingController.php:19`
- **Description**: Payroll settings `show` dan `history` dilindungi `payroll-statistics` bukan `payroll-list`. HR punya `payroll-list` dan `payroll-create` tapi TIDAK punya `payroll-statistics`. Artinya HR bisa create payroll tapi TIDAK bisa lihat/edit settings.
- **Impact**: HR tidak bisa manage payroll settings meskipun bisa create payroll — workflow terpotong.
- **Affected role(s)**: HR

#### GAP-018: FE Payroll Settings — Guard `payroll-statistics`
- **Severity**: Medium
- **Category**: BE-FE consistent but wrong
- **Location**: FE `payroll.js:33` — `requiredPermission: 'payroll-statistics'`
- **Description**: FE dan BE konsisten pakai `payroll-statistics` untuk settings. Tapi ini salah secara bisnis — settings seharusnya accessible oleh yang manage payroll (HR punya `payroll-create`).
- **Impact**: Sama dengan GAP-017 — HR tidak bisa akses settings page.
- **Affected role(s)**: HR

### 🟢 LOW

#### GAP-019: `attendance-my-statistics` vs `attendance-my-attendances` Overlap
- **Severity**: Low
- **Category**: Design inconsistency
- **Location**: `PermissionSeeder.php:59-60`
- **Description**: Employee punya `attendance-my-attendances` tapi tidak `attendance-my-statistics`. Endpoint `getMyAttendanceStatistics` dilindungi `attendance-my-attendances` di middleware, jadi Employee bisa akses. Tapi nama permission membingungkan.
- **Impact**: Tidak ada functional impact, tapi naming inconsistent.
- **Affected role(s)**: -

#### GAP-020: FE `employee.attendance.records` — Guard `attendance-list` Berbeda Dari `attendance-menu`
- **Severity**: Low
- **Category**: FE-BE minor mismatch
- **Location**: FE `attendance.js:28-29`
- **Description**: Attendance records FE route pakai `attendance-list` bukan `attendance-menu`. Ini sebenarnya lebih granular dan bagus, tapi inconsistent dengan pattern lain yang pakai `-menu`.
- **Impact**: Tidak ada — `attendance-list` lebih restrictive.
- **Affected role(s)**: -

---

## 4. Remediation Plan

### 🚀 Quick Wins (Critical + Low Effort)

| # | Gap | Effort | Approach |
|---|-----|--------|----------|
| 1 | GAP-002 | S | Tambah `HasMiddleware` + middleware ke `PerformanceGoalController`. Map: CRUD → `goal-create-own` / `goal-assign-team`. Tambah ownership check di `update`/`destroy`/`addProgressUpdate`. |
| 2 | GAP-003 | S | Tambah middleware ke `PerformanceReviewController`. `show` → `performance-menu`, `submitSelfAssessment` → `review-self-submit`, `submitManagerAssessment` → `review-manager-submit`, `calibrate` routes sudah ada middleware di routes. Tambah ownership check di `show` dan `submitSelfAssessment`. |
| 3 | GAP-004 | S | Tambah middleware ke `PerformanceFeedbackController`. `store` → `feedback-give`, `received` → `performance-menu`, `given` → `performance-menu`. Tambah ownership check di `acknowledge`. |
| 4 | GAP-001 | S | Opsional — endpoint sudah user-scoped. Tapi untuk konsistensi, tambah `HasMiddleware` dengan `allowAuthenticated: true` atau define `notification-*` permissions. |

### 📦 Grouped by Domain

#### Performance Management (GAP-002, 003, 004, 009)
| # | Gap | Effort | Approach |
|---|-----|--------|----------|
| 1 | GAP-002 | S | Lihat Quick Wins |
| 2 | GAP-003 | S | Lihat Quick Wins |
| 3 | GAP-004 | S | Lihat Quick Wins |
| 4 | GAP-009 | M | Tambah `performance-menu` ke Employee dan Finance di RolePermissionSeeder. Atau: buat route FE yang tidak require `performance-menu` untuk self-service (my-reviews, my-goals, feedback). |

#### Employee & Data Privacy (GAP-005, 006)
| # | Gap | Effort | Approach |
|---|-----|--------|----------|
| 1 | GAP-005 | M | Refactor `EmployeeProfileResource` untuk conditional field exposure berdasarkan requester role. Opsi: `EmployeeProfileResource` cek `auth()->user()->can('employee-edit')` — jika tidak, hide sensitive fields (identity_number, npwp, bpjs_*, bank_info, emergency_contacts). Alternatif: buat `EmployeeProfileSummaryResource` untuk Employee role. |
| 2 | GAP-006 | M | Hide `monthly_salary` dari `JobInformationResource` kecuali requester punya `payroll-list` atau `employee-edit`. Dependency: GAP-005. |

#### Dashboard & Analytics (GAP-007, 008)
| # | Gap | Effort | Approach |
|---|-----|--------|----------|
| 1 | GAP-007 | M | Buat `DashboardRepository::getStatistics()` role-aware. Employee: hanya lihat personal stats. Manager: team stats. HR/Finance: company-wide. |
| 2 | GAP-008 | L | Pecah `analytics-view` jadi granular permissions: `analytics-workforce`, `analytics-payroll`, `analytics-attendance`, `analytics-performance`. Assign sesuai role (Manager: no payroll analytics). Update middleware, seeder, dan FE. |

#### Payroll (GAP-013, 017, 018)
| # | Gap | Effort | Approach |
|---|-----|--------|----------|
| 1 | GAP-013 | S | Tambah ownership check di `PayslipController::show()` dan `download()`: cek `$payrollDetail->employee_id === auth()->user()->employeeProfile->id` kecuali user punya `payroll-list`. |
| 2 | GAP-017/018 | S | Ganti guard payroll settings dari `payroll-statistics` ke `payroll-list` atau buat permission baru `payroll-settings-view`. Assign ke HR dan Finance. |

#### Project (GAP-015)
| # | Gap | Effort | Approach |
|---|-----|--------|----------|
| 1 | GAP-015 | M | Scope `ProjectRepository::getAll()` per role: Employee → hanya project dimana dia member. HR/Manager → semua. Atau apply `EnsureProjectMembership` ke `index` endpoint juga, tapi ini akan break admin view. Lebih baik: conditional query di repository. |

---

## 5. Open Questions

1. **Dashboard scope policy**: Apakah Employee seharusnya bisa lihat company-wide stats di dashboard (total employees, etc.)? Atau hanya personal stats?

2. **Performance menu untuk Employee/Finance**: Apakah Employee dan Finance seharusnya bisa akses performance module (self-review, goals, feedback) via UI? Saat ini mereka punya BE permission tapi FE blocked.

3. **Manager dan Payroll**: Manager sengaja di-exclude dari payroll module. Apakah ini benar? Manager tidak bisa lihat payroll data team-nya?

4. **Finance dan Leave**: Finance punya self-service leave permission via baseline. Apakah Finance juga perlu bisa approve leave (misal: sebagai approver backup)?

5. **Employee Detail visibility**: Apakah Employee boleh lihat detail profile employee lain (nama, team, job title) tanpa data sensitif? Atau seharusnya Employee hanya bisa lihat diri sendiri?

6. **Analytics granularity**: Apakah Manager seharusnya bisa lihat payroll/salary analytics? Atau hanya workforce, attendance, project analytics?

7. **Attendance Correction approve**: `attendance-correction-approve` ada di PermissionSeeder dan assigned ke HR/Manager via prefix/allExcept. Tapi siapa yang seharusnya approve — hanya HR, atau Manager juga?

8. **Payroll Settings access**: HR bisa create payroll tapi tidak bisa lihat settings (GAP-017). Apakah ini intentional, atau HR seharusnya juga bisa lihat settings?
