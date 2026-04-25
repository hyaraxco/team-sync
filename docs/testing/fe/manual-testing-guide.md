# Manual Testing Guide — Team Sync HRIS

> **Last Updated:** 2026-04-26
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
| **HR** | Tasyia | `tasyia@teamsync.com` | General (all), Performance (all), Personal |
| **HR** | Fajar Pratama | `fajar@teamsync.com` | Same as above |
| **Finance** | Dwimeta | `dwimeta@teamsync.com` | General (Employees, Payroll, Analytics), Performance (self-service), Personal |
| **Finance** | Sari Dewi | `sari@teamsync.com` | Same as above |

---

## Pre-Test Setup

```bash
# 1. Fresh database with demo data
cd team-sync-be
docker compose exec web php artisan migrate:fresh --seed --force

# 2. Start queue worker (for notifications)
docker compose exec -d web php artisan queue:work --tries=1

# 3. Start FE dev server
cd ../team-sync-fe
bun run dev
```

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
- [ ] Payroll Dashboard → View list (if payroll exists)
- [ ] Payroll Create → Can generate payroll
- [ ] **Cannot** access Payroll Settings (finance only)
- [ ] **Cannot** approve/mark-as-paid (finance only)

#### Performance Management
- [ ] Review Cycles → Create new cycle
- [ ] Generate Reviews → Auto-assign reviewers
- [ ] Override reviewer assignment
- [ ] Templates → Create/edit review templates
- [ ] Outcome Rules → Configure rating → outcome mapping
- [ ] Pending Calibration → Calibrate reviews
- [ ] My Reviews → Own review

#### Analytics
- [ ] Navigate to Analytics → All dashboards accessible
- [ ] Workforce, Attendance, Payroll, Performance, Project analytics

#### Self-Service
- [ ] My Profile, My Attendance, My Payroll → All accessible

#### Denied Access
- [ ] URL `/admin/payroll/settings` → Redirects to dashboard

---

### 🟠 Finance (Login as `dwimeta@teamsync.com`)

#### Dashboard
- [ ] Login → Dashboard with payroll-focused stats

#### Employees
- [ ] Navigate to Employees → View list only
- [ ] **Cannot** create/edit/delete

#### Payroll (Full Access)
- [ ] Payroll Dashboard → View all periods
- [ ] Payroll Settings → Configure cutoff day, deduction rate, rounding
- [ ] Payroll Detail → Edit notes, adjust final salary
- [ ] Approve Payroll → Approve pending payroll
- [ ] Mark as Paid → Process payment with date
- [ ] Reopen Payroll → Reopen for correction
- [ ] Export Excel → Download payroll report
- [ ] Export Report → Generate summary report
- [ ] Notification Deliveries → View send history

#### Analytics
- [ ] Navigate to Analytics → Payroll analytics, trends
- [ ] Export → Excel/PDF export

#### Self-Service
- [ ] My Profile, My Attendance, My Payroll → All accessible

#### Denied Access
- [ ] URL `/admin/payroll/create` → Redirects to dashboard
- [ ] URL `/admin/teams` → Redirects to dashboard
- [ ] URL `/admin/projects` → Redirects to dashboard
- [ ] URL `/admin/attendances` → Redirects to dashboard
- [ ] URL `/admin/performance/cycles` → Redirects to dashboard

---

## Cross-Role Interaction Tests

### Payroll Full Cycle
1. **HR** (Tasyia): Generate payroll for current month
2. **Finance** (Dwimeta): Review payroll detail, edit notes
3. **Finance** (Dwimeta): Approve payroll
4. **Finance** (Dwimeta): Mark as paid with today's date
5. **Staff** (Agung): Check My Payroll → Payslip visible
6. **Staff** (Agung): Check notifications → "Payroll Paid" notification

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

---

## Running Playwright E2E Tests

```bash
cd team-sync-fe

# Prepare backend (fresh seed + queue)
bun run e2e:prepare:be

# Run all E2E tests
bun run e2e

# Run only role navigation tests
npx playwright test e2e/role-navigation.spec.ts

# Run with UI mode (interactive)
npx playwright test --ui

# View report after run
bun run e2e:report
```

---

## Troubleshooting

| Issue | Solution |
|-------|---------|
| Login fails | Run `docker compose exec web php artisan migrate:fresh --seed --force` |
| Notifications empty | Start queue worker: `docker compose exec -d web php artisan queue:work --tries=1` |
| Payroll create blocked | Need attendance records for current month — clock in/out first |
| 403 on API calls | Check role permissions — user may not have required permission |
| Meilisearch errors | Set `SCOUT_DRIVER=null` in `.env` or start Meilisearch container |
