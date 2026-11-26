# Manual Testing Guide — Team Sync HRIS

> **Payroll role expectations updated:** 2026-11-23 (source review only, not a fresh E2E sign-off). Other scenarios retain the 2026-04-26 baseline and need revalidation.
> **Prerequisites:** `docker compose up -d` (BE) + `bun run dev` (FE)
> **Login:** All users use password `teamsync`

---

## Test Accounts

| Role | Name | Email | Sidebar Sections |
|------|------|-------|-----------------|
| **Staff** | Agung Ramadhan | `agung@teamsync.com` | General, Performance, Personal |
| **Staff** | Budi Santoso | `budi@teamsync.com` | Same as above |
| **Staff** | Dina Maharani | `dina@teamsync.com` | Same as above |
| **Manager** | Yudhis | `yudhis@teamsync.com` | General (+ Employees, Teams, Attendance), Performance (+ Team Reviews), Personal |
| **Manager** | Rina Wulandari | `rina@teamsync.com` | Same as above |
| **HR** | Tasyia | `tasyia@teamsync.com` | Workforce/attendance/leave, Performance, Personal; payroll readiness/THR read-only |
| **HR** | Fajar Pratama | `fajar@teamsync.com` | Same as above |
| **Finance** | Dwimeta | `dwimeta@teamsync.com` | Payroll/THR, payroll analytics, projects (view-only), Performance (self-service), Personal |
| **Finance** | Sari Dewi | `sari@teamsync.com` | Same as above |

---

## Pre-Test Setup

The default Docker Compose project and persistent `mysql-data` volume must never be reset for these manual scenarios. Manual seeders and `qa:payroll-ready` operate on the configured database, so first prove that database is disposable.

The normal Playwright wrapper now selects the pinned `teamsync-e2e-isolated` Compose target and fails closed for unapproved Compose files. Its refusal/preflight tests pass; no service startup or real database reset was run in this change. Use `bun run e2e` only with that approved target, never with development or production resources.

For destructive role tests without MySQL, use the guarded SQLite sandbox at [`../../../scripts/sandbox/README.md`](../../../scripts/sandbox/README.md). It creates a fresh `/scratch` database and refuses unsafe paths. For manual browser scenarios, use a separately verified disposable backend.

To start only the frontend against an already-running backend, run `bun run dev` from `team-sync-fe/` and confirm that the configured API is not production or a working database.

---

## Test Scenarios Per Role

### 🟢 Staff (Login as `agung@teamsync.com`)

#### Dashboard
- [ ] Login → Dashboard loads with "Welcome back, Agung Ramadhan! 👋"
- [ ] Attendance Rate card shows 0% (no attendance yet)
- [ ] Hours Worked shows "-h"
- [ ] Tasks Done shows 0
- [ ] Active Projects shows count
- [ ] Quick Actions: "Request Leave" button visible
- [ ] Upcoming Tasks section visible (empty)
- [ ] Recent Activities section visible

#### My Profile
- [ ] Navigate to My Profile → Data lengkap muncul
- [ ] Name: Agung Ramadhan
- [ ] Job: Software Engineer, Full Time, Remote
- [ ] Bank: BCA, 1234567890
- [ ] Identity: NPWP, BPJS visible
- [ ] Edit Profile → Can update name, photo

#### My Team
- [ ] Navigate to My Team → "Engineering" team visible
- [ ] Team Lead: Yudhis
- [ ] Members: Agung, Budi, Yudhis (3 members)

#### Clock In/Out
- [ ] Navigate to My Attendance
- [ ] Click "Clock In" → Timestamp recorded
- [ ] Status shows "Present" or "Late" depending on time
- [ ] Click "Clock Out" → Worked hours calculated
- [ ] Attendance record appears in list

#### Projects
- [ ] Navigate to Projects → "Team Sync HRIS Platform" visible
- [ ] Click project → Detail page loads
- [ ] Squad Summary widget visible

#### Performance
- [ ] My Reviews → Q1 2026 review visible
- [ ] My Goals → Empty (can create new goal)
- [ ] Create goal: "Learn Laravel Testing", type: development, due: next month
- [ ] Feedback → Can give feedback to team members

#### Leave Request
- [ ] Quick Actions → "Request Leave"
- [ ] Create sick leave for tomorrow
- [ ] My Requests → New request visible with "Pending" status

#### Denied Access
- [ ] URL `/admin/payroll` → Redirects to dashboard
- [ ] URL `/admin/staff-members/create` → Redirects to dashboard
- [ ] URL `/admin/attendances` → Redirects to dashboard
- [ ] URL `/admin/analytics` → Redirects to dashboard
- [ ] URL `/admin/performance/cycles` → Redirects to dashboard

---

### 🔵 Manager (Login as `yudhis@teamsync.com`)

#### Dashboard
- [ ] Login → Admin dashboard with statistics
- [ ] Employee count, project stats visible

#### Employees
- [ ] Navigate to Employees → List visible
- [ ] Can view employee details
- [ ] **Cannot** see Create/Edit/Delete buttons (view-only)

#### Teams
- [ ] Navigate to Our Teams → Team list visible
- [ ] Engineering team → Can edit team details
- [ ] Can add/remove members

#### Projects
- [ ] Navigate to Projects → HRIS Platform + Mobile App visible
- [ ] Can create new project
- [ ] Can edit existing project
- [ ] Task Board → Can create task, assign to Agung/Budi

#### Attendance Admin
- [ ] Navigate to Attendance → Admin list visible
- [ ] Can see all employee attendance records
- [ ] Attendance Corrections → Can approve/reject

#### Performance
- [ ] Team Reviews → See reviews for team members
- [ ] Submit manager assessment for Agung's review
- [ ] Team Goals → See/assign goals to team members
- [ ] My Reviews → Own review visible
- [ ] Feedback → Can give feedback

#### Self-Service
- [ ] My Profile → Full data visible
- [ ] My Attendance → Own records
- [ ] My Payroll → Own payslips (empty until payroll generated)

#### Denied Access
- [ ] URL `/admin/payroll` → Redirects to dashboard
- [ ] URL `/admin/performance/cycles` → Redirects to dashboard
- [ ] URL `/admin/performance/reviews/pending-calibration` → Redirects to dashboard
- [ ] URL `/admin/staff-members/create` → Redirects to dashboard

---

### 🟣 HR (Login as `tasyia@teamsync.com`)

#### Dashboard
- [ ] Login → Dashboard with admin stats

#### Employees (Full CRUD)
- [ ] Navigate to Employees → Full list
- [ ] Create new employee → Fill all steps → Success
- [ ] Edit existing employee → Update job info
- [ ] View employee detail → All data visible
- [ ] Delete employee (test with caution)

#### Teams (Full CRUD)
- [ ] Navigate to Our Teams → Create new team
- [ ] Edit team → Change leader, add members
- [ ] Delete team

#### Projects (Full CRUD)
- [ ] Navigate to Projects → Create new project
- [ ] Edit project → Change status, budget
- [ ] Task Board → Create/edit tasks

#### Attendance
- [ ] Attendance admin list → All records
- [ ] Attendance Corrections → Approve/reject
- [ ] Leave Requests → Approve/reject pending requests

#### Payroll
- [ ] Payroll Dashboard, Create, Detail and Settings are denied; `Process Payroll` quick action is hidden
- [ ] `/admin/payroll/readiness` and `/admin/payroll/thr` are accessible read-only for HR coordination
- [ ] Payroll generate/approve/pay and THR generate/approve/process actions are unavailable; mutation APIs return `403`

#### Performance Management
- [ ] Review Cycles → Create new cycle
- [ ] Generate Reviews → Auto-assign reviewers
- [ ] Override reviewer assignment
- [ ] Templates → Create/edit review templates
- [ ] Outcome Rules → Configure rating → outcome mapping
- [ ] Pending Calibration → Calibrate reviews
- [ ] My Reviews → Own review

#### Analytics
- [ ] Workforce, Attendance, Leave, Performance and Project analytics are accessible
- [ ] Finance-only payroll analytics are unavailable

#### Self-Service
- [ ] My Profile, My Attendance, My Payroll → All accessible

#### Denied Access
- [ ] URLs `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/1`, `/admin/payroll/settings` redirect to dashboard; corresponding admin APIs return `403`

---

### 🟠 Finance (Login as `dwimeta@teamsync.com`)

#### Dashboard
- [ ] Login → Dashboard with payroll-focused stats

#### Employees
- [ ] Staff directory and staff create/edit/delete routes are denied; own profile remains accessible

#### Payroll (Full Access)
- [ ] Payroll Dashboard → View all periods
- [ ] `Process Payroll` quick action and `/admin/payroll/create` are available
- [ ] Payroll Create: inspect readiness, generate an eligible month, wait for `pending`; duplicate month is rejected
- [ ] Payroll Settings → Configure cutoff day, deduction rate, rounding
- [ ] Payroll Detail → Edit notes, adjust final salary
- [ ] Approve Payroll: complete required approval decisions for pending payroll
- [ ] Mark as Paid: process approved payroll with payment date
- [ ] Reopen Payroll → Reopen for correction
- [ ] Export Excel → Download payroll report
- [ ] Export Report → Generate summary report
- [ ] Notification Deliveries → View send history
- [ ] THR list and generate/approve/process actions are available to Finance

#### Analytics
- [ ] Navigate to Analytics → Payroll analytics, trends
- [ ] Export → Excel/PDF export

#### Self-Service
- [ ] My Profile, My Attendance, My Payroll → All accessible

#### Denied Access
- [ ] URL `/admin/staff-members` → Redirects to dashboard
- [ ] URL `/admin/teams` → Redirects to dashboard
- [ ] `/admin/projects` allows view-only project access; project create/edit/delete remain denied
- [ ] URL `/admin/attendances` → Redirects to dashboard
- [ ] URL `/admin/performance/cycles` → Redirects to dashboard

---

## Cross-Role Interaction Tests

### Payroll Full Cycle
> Finance owns payroll/THR operations. HR retains read-only readiness/THR access, but cannot generate, approve or pay. These are current expectations, not newly executed QA results.
1. **Finance** (Dwimeta): Generate payroll for current month
2. **Finance** (Dwimeta): Review payroll detail, edit notes
3. **Finance** (Dwimeta): Complete required approval decisions; payroll becomes `approved`
4. **Finance** (Dwimeta): Mark as paid with today's date
5. **HR** (Tasyia): Verify payroll dashboard/create/detail/settings and mutation APIs are denied; readiness/THR remain read-only
6. **Staff** (Agung): Check My Payroll → Payslip visible
7. **Staff** (Agung): Check notifications → "Payroll Paid" notification

### Task Assignment Flow
1. **Manager** (Yudhis): Create task in HRIS project, assign to Agung
2. **Staff** (Agung): Check dashboard → Task in "Upcoming Tasks"
3. **Staff** (Agung): Check notifications → "Task Assigned" notification
4. **Staff** (Agung): Update task status to "In Progress"

### Leave Request Flow
1. **Staff** (Agung): Create leave request (sick, 1 day)
2. **HR** (Tasyia): Navigate to Leave Requests → Approve request
3. **Staff** (Agung): Check My Requests → Status "Approved"

### Performance Review Flow
1. **HR** (Tasyia): Create review cycle → Generate reviews
2. **Staff** (Agung): Submit self-assessment
3. **Manager** (Yudhis): Submit manager assessment
4. **HR** (Tasyia): Calibrate review → Finalize


## Troubleshooting

| Issue | Safe action |
|---|---|
| Login fails in the isolated role sandbox | Rebuild or reseed only through the sandbox command in `scripts/sandbox/README.md`. |
| Normal Playwright seed is required | Wait for `team-sync-wlk` to pass and use its documented isolated target. Do not use the default Compose database. |
| Notifications empty | Start a queue worker only against a verified disposable backend. |
| Meeting reminders not delivered | Start the scheduler only against a verified disposable backend. |
| 403 on API calls | Check the assigned role permissions and the API authorization boundary. |
