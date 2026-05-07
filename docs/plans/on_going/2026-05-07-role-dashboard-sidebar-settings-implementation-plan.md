# Implementation Plan — Role Dashboard, Sidebar, Settings Alignment

> Phase 0 inventory for `docs/requirements/2026-05-07-final-role-dashboard-sidebar-settings-prd.md`.
> Status: Phase 0 complete, ready for Phase 1 backend implementation.
> Scope: documentation and implementation planning only. No application code changed in this phase.

## 1. Objective

Align Team Sync role exposure to the strict least-privilege PRD:

- Staff gets self-service only.
- Manager gets team/project/performance management, but no company-wide staff directory until team-scoped staff APIs exist.
- HR owns workforce, attendance, leave, meetings, performance, and HR analytics; payroll/THR is read-only context only if explicitly needed.
- Finance owns payroll, THR, payroll settings, adjustments, approval matrix, payroll-safe staff context, and finance analytics.
- Superadmin keeps full access.
- Dashboard remains one adaptive route: `/admin/dashboard`.

## 2. Phase 0 Inventory Summary

### 2.1 Current role assignments from `RolePermissionSeeder.php`

| Role | Current broad grants | PRD verdict |
|---|---|---|
| `staff` | `dashboard-menu/view`, `staff-member-list`, `team-view`, `project-menu/list`, `task-* create/list/edit`, self-service payroll/attendance/leave/performance | Too broad for staff on staff directory and admin project/task routes; should be self-scoped/personal workspace only. |
| `manager` | Almost every permission except payroll/license, HR-only review calibration/cycle, staff create/edit/delete, meeting list/create | Too broad. Manager inherits `staff-member-menu/list`, analytics, attendance, teams, projects, tasks, overtime, THR permissions through allow-all-except pattern. |
| `hr` | `dashboard-*`, team, full staff-member, project, task, attendance, leave, analytics, performance, review, goal, feedback, meeting, overtime, plus `payroll-menu/list/create`, `thr-list/generate` | Too much payroll/THR operation for final policy. HR should not generate payroll/THR unless product reopens policy. |
| `finance` | dashboard, staff-member menu/list, payroll create/edit/process/statistics, analytics view/export, overtime all, THR approve/process, self-service | Mostly aligned for payroll, but missing `thr-generate`; too broad on staff directory and overtime approval; analytics too broad. |
| `superadmin` | all permissions | Aligned. |

### 2.2 Current permission vocabulary gaps

Existing permissions are coarse:

- `dashboard-menu`, `dashboard-view`
- `analytics-menu`, `analytics-view`, `analytics-export`
- `staff-member-menu`, `staff-member-list`, `staff-member-create`, `staff-member-edit`, `staff-member-delete`
- `payroll-menu`, `payroll-list`, `payroll-create`, `payroll-edit`, `payroll-delete`, `payroll-process`, `payroll-statistics`
- `thr-list`, `thr-generate`, `thr-approve`, `thr-process`

PRD needs narrower semantics that do not currently exist:

- `dashboard-hr-view`, `dashboard-finance-view`, `dashboard-manager-view`, `dashboard-staff-view` or equivalent role-aware backend logic.
- `analytics-hr-view`, `analytics-finance-view`, `analytics-manager-team-view`, `analytics-export-finance`, `analytics-export-hr` or equivalent controller scoping.
- `staff-member-payroll-context` for Finance payroll-safe staff lookup.
- `staff-member-team-list` for Manager if/when team-scoped staff directory is allowed.
- `payroll-readiness-view` if HR needs read-only payroll readiness without generate/process authority.
- Optional `settings-attendance-manage`, `settings-payroll-manage`, `settings-performance-manage` if settings route stays aggregated.

## 3. Backend Inventory

### 3.1 Routes with no explicit route middleware but controller middleware exists

These are acceptable only if controller `HasMiddleware` fully matches PRD scope:

| Area | Routes | Current controller guard | Gap |
|---|---|---|---|
| Dashboard | `/dashboard/statistics`, `/dashboard/my-statistics`, `/dashboard/today-attendance-overview`, `/dashboard/team-pulse` | `dashboard-view` for broad stats; `review-manager-submit` for team pulse | `dashboard-view` is too broad and not role/audience scoped. |
| Analytics | `/analytics/*` | `analytics-view`; export uses `analytics-export` | Too broad. HR, Finance, Manager analytics need separate audience scoping. |
| Staff directory | `/staff-members*` | list/show/statistics via `staff-member-list|create|edit|delete`; menu route uses `staff-member-menu` on FE | Finance and Manager currently get/derive too much. Need finance-safe context and no manager directory until team-scoped endpoint. |
| Payroll | `/payrolls*`, `/payroll-settings*`, `/payroll-approval-policies*` | mostly `payroll-list/create/edit/process/statistics` | HR currently has `payroll-create`; Finance owns create/process/settings under PRD. |
| THR | `/thr*` | `thr-list/generate/approve/process` | HR currently has generate; Finance lacks generate. PRD says Finance owns all THR operations. |
| Attendance/leave | `/attendances*`, `/leave-requests*`, settings resources | `attendance-*`, `leave-request-*`, broad `attendance-menu` for admin settings | Need confirm Manager vs HR split; Finance should not get attendance admin. |
| Overtime | `/overtime*` | `overtime-list/create/approve` | Finance currently gets approval; PRD likely makes HR/Manager approval owner, Finance payroll context only if needed. |
| License/setup | `/licenses*`, `/setup*` | license controller guarded by `license-view/manage`; setup public first-boot | Manager currently excludes license; OK. Verify superadmin-only for license manage. |

### 3.2 Backend route classification

| Route group | Classification target | Notes |
|---|---|---|
| `/me`, `/my-profile`, `/my-notifications` | self-scoped authenticated | Keep available to all authenticated roles. |
| `/dashboard/my-statistics` | self-scoped dashboard | Staff dashboard only; current guard uses `dashboard-view`. |
| `/dashboard/statistics`, `/dashboard/today-attendance-overview` | HR-scoped dashboard | Current `dashboard-view` allows staff/finance/manager if granted. |
| `/dashboard/team-pulse` | manager/team-scoped | Current `review-manager-submit`; acceptable if repository enforces team scope. |
| `/analytics/workforce`, `/analytics/attendance`, `/analytics/leave`, `/analytics/projects`, executive summary | HR-scoped or manager-team-scoped subset | Current `analytics-view` is too broad. |
| `/analytics/payroll`, `/analytics/payroll/*` | finance-scoped | Current `analytics-view` is too broad. |
| `/analytics/performance/company-summary` | HR-scoped | Current `analytics-view` is too broad. |
| `/analytics/performance/team-summary` | manager team-scoped or HR | Current `analytics-view` is too broad. |
| `/staff-members`, `/staff-members/all/paginated`, `/staff-members/{id}` | HR-scoped full directory | Finance and Manager should not use this full resource. |
| `/staff-members/statistics` | HR-scoped workforce stats | Current guard tied to `staff-member-list`. |
| `/my-team*` | self/team-scoped | Keep for staff/manager self-service; verify repository scoping. |
| `/payrolls*`, `/payroll-settings*`, `/payroll-approval-policies*`, `/payroll-adjustments*` | finance-scoped | HR only read-only readiness if needed. |
| `/my-payslips*`, `/payslips/{id}/*` | self-scoped payroll | Keep for staff/manager/HR/finance own payslips. |
| `/thr*` | finance-scoped | Move generate from HR to Finance. |
| `/attendances*`, `/attendance-*`, `/leave-*`, `/holiday-calendars`, `/hybrid-*` | HR-scoped admin + self-scoped personal endpoints | Separate admin endpoints from personal endpoints. |
| `/overtime*` | HR/manager approval + self create/list | Finance should not have broad approval unless PRD adds payroll-only read context. |
| `/teams*`, `/projects*`, `/project-tasks*` | HR/admin or manager/team/project scoped | Manager access must be constrained by repository/middleware. |
| `/licenses*` | superadmin/system-scoped | Current permissions exist; ensure only superadmin gets them. |
| `/setup*` | public first-boot | Leave unchanged unless setup hardening is requested. |

### 3.3 Backend data exposure findings

- `StaffMemberProfileResource` exposes sensitive identity fields only to own profile or `staff-member-edit`. This protects identity/bank/emergency contacts from Finance if Finance only has `staff-member-list`.
- `JobInformationResource` exposes `monthly_salary` to own profile, `staff-member-edit`, or `payroll-list`. Finance can see salary via staff resource when it has `payroll-list`, which may be acceptable only in payroll-safe context, not in full HR directory.
- `StaffMemberProfileResource` still exposes personal fields such as date of birth, gender, religion, marital status, address, and city to anyone who can list/show staff. Under PRD, Finance should not use full staff directory.
- Current full staff list route is not team-scoped for Manager by route contract. Manager directory should be removed until a dedicated team-scoped resource exists.

## 4. Frontend Inventory

### 4.1 Router guard model

- Global guard fails closed for authenticated routes unless `requiredPermission`, `requiredAnyPermissions`, or `allowAuthenticated` is present.
- Some child routes rely on parent `requiresAuth`; standalone admin routes include `requiresAuth` directly.
- `permissionHelper.can()` expects `authStore.user.permissions` to be an array of strings. Backend `UserResource` returns loaded permissions as string names, so this is consistent.

### 4.2 Current route guard gaps

| Route | Current guard | Gap vs PRD |
|---|---|---|
| `admin.dashboard` | `dashboard-menu` | Should be allowed for all roles but widget/API calls must be audience-safe. Guard is OK if all roles keep menu. |
| `admin.settings` | any of `payroll-statistics`, `attendance-menu`, `review-cycle-manage` | OK for aggregate settings shell, but settings sections need stricter domain permissions. |
| `admin.staffMembers` | `staff-member-menu` | Finance currently has menu; PRD says Finance should not see full directory. Manager should not get full directory. |
| `admin.staffMembers.detail` | `staff-member-menu` | Weaker than backend show guard (`staff-member-list|create|edit|delete`). Should match list/read permission and target scope. |
| `admin.analytics` | `analytics-menu` | Too broad; Analytics tabs/API calls need audience permissions. |
| `admin.payroll.readiness/create` | `payroll-create` | HR currently has create; PRD says Finance owns generate. HR read-only readiness should use a separate permission if needed. |
| `admin.payroll.settings`, `approval-matrix`, `comparison` | `payroll-statistics` | Settings and approval matrix mutate via backend `payroll-edit`; route should not be statistics-only. |
| `admin.payroll.adjustments` | `payroll-menu` | Too broad for approve queue; should align to payroll adjustment/finance permission. |
| `admin.payroll.thr` | `thr-list` | OK for read; actions inside view must gate generate/approve/process. Finance needs generate. |
| Attendance admin settings/routes | `attendance-menu` | Broad but HR-owned; Manager should not get attendance admin unless approved. |
| Overtime admin | `overtime-list` | Finance currently has; PRD likely wants HR/Manager approval or payroll-read context only. |
| Personal routes | self-service permissions | Mostly aligned. |

### 4.3 Sidebar gaps

| Sidebar item | Current visibility | Gap vs PRD |
|---|---|---|
| Dashboard | `dashboard-menu` | OK if all roles keep adaptive dashboard. |
| Projects | `project-menu` | Staff currently may see admin projects. Confirm self/project membership scope or move staff to personal/project-specific views only. |
| Employees | `staff-member-menu` | Finance currently sees full Employees menu; Manager likely sees via broad permissions. Must hide until role-specific safe endpoint exists. |
| Our Teams | `team-menu` | Manager/HR OK if data scoped; staff should use My Team. |
| Meetings | `meeting-menu` | HR owns create/list; staff may have menu baseline but no list route permission. Potential menu/route mismatch if staff has `meeting-menu` but not `meeting-list`. |
| Attendance | `attendance-menu` | HR admin only. Ensure Manager/Finance do not get it by broad role grants. |
| Payroll | `payroll-menu` | Finance primary. HR currently sees payroll due role grants; must be removed or read-only view split. |
| Payroll Adjustments | `payroll-menu` | Too broad; should be finance adjustment permission. |
| Analytics | `analytics-menu` | Too broad; split HR/Finance/Manager tab visibility and calls. |
| Settings | any `payroll-statistics`, `attendance-menu`, `review-cycle-manage` | Fine as shell, but individual cards need domain permissions. |

### 4.4 Dashboard and analytics frontend findings

- `Dashboard.vue` uses roles to branch:
  - `staff` → `EmployeeStatistics`, optional search.
  - `finance` → `PayrollAnalyticsEnhanced`.
  - all other roles → TeamPulse, Statistics, Search, LatestEmployees, LatestTeams, TodayAttendanceOverview, UpcomingMeetings.
- `hasDashboardPermission` checks `permission.name`, but permissions are strings. This makes `SearchSection` for staff likely not render as intended. It is a bug, but not the main PRD blocker.
- Finance dashboard imports `PayrollAnalyticsEnhanced` directly instead of a dedicated dashboard store action. It must be verified for endpoint permissions and payroll-only data exposure.
- Analytics dashboard defines all tabs unconditionally: executive, workforce, attendance, leave, payroll, projects, performance.
- Analytics store has actions for all HR, finance, project, and performance metrics under a single `analytics-view` guard. This will call forbidden or overbroad APIs unless tab visibility and store methods are split by role/audience.

### 4.5 Settings frontend findings

- `Settings.vue` already groups Payroll & Finance, Attendance & Time, Performance & Growth.
- Payroll settings card uses `payroll-statistics`; backend update uses `payroll-edit`. Approval matrix route also uses `payroll-statistics` but mutates via `payroll-edit` endpoints.
- Attendance settings use `attendance-menu` and should be HR-only under final PRD.
- Performance settings use `review-cycle-manage` and should be HR-only.

## 5. Target Role Matrix for Implementation

| Domain | Staff | Manager | HR | Finance | Superadmin |
|---|---|---|---|---|---|
| Dashboard route | Self widgets | Team widgets | HR widgets | Finance widgets | Full/system widgets |
| Staff directory | No admin directory | No admin directory until team-scoped API | Full HR directory | Payroll-safe context only | Full |
| Teams/projects/tasks | Personal/project membership only | Team/project scoped | HR/admin scoped | No unless payroll context needs basic team labels | Full |
| Attendance admin | No | Optional team view only if scoped | Full | No | Full |
| Leave admin | Own requests only | Optional team approvals if scoped | Full approval/admin | No | Full |
| Payroll operations | Own payslips | Own payslips | Read-only readiness/context only if needed | Generate/approve/process/pay/reopen/settings/adjustments/reports | Full |
| THR | Own payslip context only | No admin | Read-only readiness/context only if needed | Generate/approve/process/finalize | Full |
| Analytics | No admin analytics | Team-scoped performance/project only | Workforce/attendance/leave/performance HR analytics | Payroll/finance analytics | Full |
| Settings | Profile/preferences only | No global settings unless team-scoped config exists | Attendance/performance/HR settings | Payroll/finance settings | Full |
| License/setup | No | No | No | No | Full |

## 6. Gap Backlog

### P0 — Backend role assignment corrections

1. Replace Manager allow-all-except seeding with explicit allowlist.
2. Remove `staff-member-menu/list` from Finance; replace with a payroll-safe context permission/endpoint if needed.
3. Remove payroll generate/create and THR generate from HR; add read-only readiness/context only if needed.
4. Add `thr-generate` to Finance and keep `thr-approve`/`thr-process`.
5. Remove overtime approval from Finance unless a finance-specific read context is approved.
6. Remove staff admin directory and admin project/task exposure from Staff unless repository scoping proves safe.

### P1 — Backend route/resource scoping

1. Add finance-safe staff resource/endpoint for payroll lookup, or ensure payroll resources already provide enough staff identity/job context.
2. Add manager team-scoped staff endpoint before exposing any staff directory to Manager.
3. Split dashboard controller methods by audience or add internal role-aware filtering.
4. Split analytics permissions/endpoints by HR, Finance, Manager-team audiences.
5. Add forbidden-access tests for staff, manager, HR, finance, superadmin across route groups.

### P2 — Frontend guard/sidebar alignment

1. Update route metadata to match backend target permissions.
2. Update sidebar visibility to hide full staff directory from Finance/Manager/Staff.
3. Split payroll menu visibility so HR does not see operational payroll unless read-only flow is implemented.
4. Gate payroll adjustment and approval matrix routes by mutation-safe finance permissions, not `payroll-menu`/`payroll-statistics` alone.
5. Fix meeting menu/list mismatch for roles with `meeting-menu` but no `meeting-list`.

### P3 — Dashboard and analytics audience split

1. Replace dashboard role checks with permission/audience helper that matches PRD.
2. Ensure each dashboard variant calls only APIs allowed for that role.
3. Hide analytics tabs that the current role cannot access.
4. Split analytics store calls into HR, Finance, Manager-team, and Superadmin audiences or guard calls before execution.
5. Add empty/forbidden states that do not leak sensitive metrics.

### P4 — Regression and E2E hardening

1. Add backend role-forbidden tests for sensitive direct API calls.
2. Add frontend route-guard/sidebar visibility tests for each role.
3. Add Playwright smoke matrix for direct navigation and sidebar navigation.
4. Verify notification deep links land on allowed destinations for target roles.
5. Run focused backend, frontend, and E2E verification.

## 7. Recommended Execution Order

### Phase 1A — Permission vocabulary and role seeders

- Add any missing narrow permissions required by the PRD.
- Replace Manager allow-all-except seeding with explicit allowlist.
- Tighten Staff, HR, and Finance grants.
- Add/adjust role matrix tests first so the seeder changes are measurable.

### Phase 1B — Backend access and resource scoping

- Protect full staff directory as HR/Superadmin only.
- Add finance-safe staff context only if payroll screens still need a staff lookup outside payroll details.
- Add manager team-scoped endpoint only if manager staff visibility is in scope.
- Split dashboard/analytics access or add role-aware guards and filtering.
- Add direct API forbidden tests for each sensitive domain.

### Phase 2 — Frontend navigation alignment

- Update router metadata after backend permissions are settled.
- Update Sidebar visibility conditions to match route metadata.
- Update Settings sections to use domain-specific permissions.
- Update payroll/THR/adjustment/approval matrix route guards.

### Phase 3 — Dashboard and analytics UI

- Keep `/admin/dashboard` adaptive, but ensure each branch calls only allowed APIs.
- Split Analytics tabs by role.
- Add defensive tests for unauthorized API calls not being fired.

### Phase 4 — Verification

- Backend targeted tests: role seeder/matrix, staff directory forbidden, payroll/THR forbidden, analytics forbidden.
- Frontend targeted tests: router permission access, sidebar role matrix, settings cards, dashboard role API calls.
- E2E smoke: Staff, Manager, HR, Finance, Superadmin navigation.

## 8. Phase 1 Starting Checklist

Before editing application code in Phase 1:

- [x] Confirm whether HR should have any read-only payroll readiness route, or no payroll admin route at all.
  → **YES**: HR gets read-only payroll readiness via new `payroll-readiness-view` permission.
- [x] Confirm whether Finance needs a standalone payroll-safe staff lookup, or payroll detail resources are enough.
  → **NO**: Payroll detail resources already embed name/job/salary. No new staff endpoint needed.
- [x] Confirm whether Manager team-scoped staff directory should be built now or deferred exactly as the PRD default says.
  → **DEFERRED**: No team-scoped staff directory until proper team-scoped API is designed and tested.
- [ ] Decide permission naming for audience-specific dashboard and analytics.
- [ ] Add tests before changing role assignments.

## 9. Phase 0 Exit Gate

- [x] Current backend permissions and route groups inventoried.
- [x] Current frontend route guards, sidebar, dashboard, analytics, and settings inventoried.
- [x] High-risk gaps identified.
- [x] Implementation order defined.
- [ ] Product confirmations in Section 8 resolved before Phase 1 code changes.
