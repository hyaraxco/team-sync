# Employee to StaffMember Full Rename - Design Document

> **Date:** 2026-04-21
> **Status:** Approved
> **Execution:** Use the **executing-plans** skill after the implementation plan is written.

## Problem

The term "employee" is used simultaneously as:
1. **Base entity** - `EmployeeProfile` model representing every person in the company
2. **RBAC role** - Spatie role `'employee'` (alongside `manager`, `hr`, `finance`)
3. **UI label** - "Employee" displayed throughout the frontend

This creates semantic collision: a manager IS an employee (base entity), but also has a different role than the `employee` role. The naming is confusing.

## Decision

**Full hard cutover rename** with no backward compatibility layer:

| Layer | Current | Target |
|-------|---------|--------|
| Domain term | Employee | **StaffMember** |
| Model class | `EmployeeProfile` | `StaffMemberProfile` |
| DB table | `employee_profiles` | `staff_member_profiles` |
| FK columns | `employee_id` | `staff_member_id` |
| API endpoints | `/employees` | `/staff-members` |
| RBAC role | `'employee'` | `'staff'` |
| Frontend store | `useEmployeeStore` | `useStaffMemberStore` |
| UI labels | "Employee" | "Staff Member" / "Staff" |
| Route paths | `/admin/employees` | `/admin/staff-members` |
| Route names | `admin.employees.*` | `admin.staffMembers.*` |

## Scope

### Backend (team-sync-be/)

**Models & Relations:**
- `EmployeeProfile` -> `StaffMemberProfile` (class, file, table, relations)
- All models with `employee_id` FK: User, JobInformation, BankInformation, EmergencyContact, Attendance, AttendanceCorrection, PayrollDetail, TeamMember, LeaveRequest, PerformanceReview, PerformanceGoal, PerformanceFeedback, HybridWorkSchedule, PayrollNotificationDelivery
- Relationship methods: `employeeProfile()` -> `staffMemberProfile()`, `employees()` -> `staffMembers()`

**Controllers:**
- `EmployeeProfileController` -> `StaffMemberProfileController`
- All controllers referencing employee variables/relations (Analytics, Payroll, Performance, Leave, Attendance)

**Repositories:**
- `EmployeeProfileRepository` + Interface -> `StaffMemberProfileRepository` + Interface
- All repositories with employee queries (Payroll, Attendance, Leave, Team, Project, Dashboard, Performance, Auth, BankInformation)

**Resources:**
- `EmployeeProfileResource` -> `StaffMemberProfileResource`
- All resources exposing `employee` keys in JSON (User, Payslip, PayrollDetail, Attendance, Team, Project, Leave)

**Requests:**
- `EmployeeProfileStoreRequest` -> `StaffMemberProfileStoreRequest`
- `EmployeeProfileUpdateRequest` -> `StaffMemberProfileUpdateRequest`
- All requests with employee field validation

**DTOs:**
- `EmployeeProfileDto` -> `StaffMemberProfileDto`
- Performance DTOs with employee fields

**Services:**
- `EmailService` - hasRole('employee') checks -> hasRole('staff')
- `PayslipPdfService`, `TopsisService`, `DailyMetricsCalculator`, Attendance services

**Middleware:**
- `EnsureProjectMembership` - hasRole('employee') -> hasRole('staff')

**Seeders & Factories:**
- `RoleSeeder` - role 'employee' -> 'staff'
- `RolePermissionSeeder` - permission bindings
- `EmployeeSeeder`, `EmployeeProfileSeeder`, `EmployeeIdentitySeeder` -> rename
- `EmployeeProfileFactory` -> `StaffMemberProfileFactory`
- All seeders assigning employee role (Hr, Manager, Finance, Mobile)

**Migrations (new forward migrations):**
- Rename table `employee_profiles` -> `staff_member_profiles`
- Rename all `employee_id` columns -> `staff_member_id` across ~15 tables
- Rename Spatie role row 'employee' -> 'staff'
- Update FK constraint names

**Routes:**
- `/employees` -> `/staff-members` in routes/api.php
- Route names and model binding updates

**Console Commands:**
- `SeedEmployeeIdentityAndGeneratePayrollCommand` -> rename
- `PreparePayrollQaCommand`, `RecalculatePayrollTaxCommand` - variable updates

**Notifications:**
- LeaveRequest, AttendanceCorrection, AttendanceMismatch notifications - payload key updates

**Exports & Views:**
- `PayrollExport` - column headings
- `analytics-pdf.blade.php` - label text

**Constants:**
- `CacheConstants` - cache keys with 'employee'

**Tests (~40+ test files):**
- Feature tests: Employee, Leave, Payroll, Attendance, Notification, Performance, Project
- Unit tests: DTOs, Helpers, Services

### Frontend (team-sync-fe/)

**Store:**
- `src/stores/employee.js` -> `src/stores/staffMember.js`
- Store ID, export name, all action/state names, API endpoint strings

**Router:**
- `src/router/employee.js` -> `src/router/staffMember.js`
- Route paths, names, imports in index.js, payroll.js, attendance.js

**Views (~20 files):**
- `src/views/employee/` directory -> `src/views/staff-member/`
- `src/views/admin/employee/` directory -> `src/views/admin/staff-member/`
- All UI labels, imports, variable names

**Components (~30 files):**
- `src/components/admin/employee/` -> `src/components/admin/staff-member/`
- `src/components/employee/` -> `src/components/staff-member/`
- Dashboard components (LatestEmployees, EmployeeStatistics, QuickActions)
- Analytics, Payroll, Attendance, Performance, Project, Team components with employee labels

**Layouts:**
- `EmployeeCreateLayout.vue` -> `StaffMemberCreateLayout.vue`

**RBAC checks (frontend):**
- `hasRole("employee")` -> `hasRole("staff")` in TaskBoard, TaskDetailModal, ReviewDetail, Dashboard

**Tests (~15 files):**
- Unit/smoke tests under `src/tests/employee/` -> `src/tests/staff-member/`
- Router guard tests, admin component tests
- E2E: `e2e/helpers/auth.ts`, `notification-employee-task.spec.ts`, `payroll-roles.spec.ts`

### Documentation (~20 files)

All markdown files under `docs/` referencing "Employee" terminology.

## Naming Convention

| Context | Pattern | Example |
|---------|---------|---------|
| PHP class | PascalCase | `StaffMemberProfile` |
| PHP variable | camelCase | `$staffMemberProfile` |
| DB table | snake_case | `staff_member_profiles` |
| DB column | snake_case | `staff_member_id` |
| API endpoint | kebab-case | `/staff-members` |
| Route name | camelCase | `admin.staffMembers.index` |
| JS store file | camelCase | `staffMember.js` |
| JS export | camelCase | `useStaffMemberStore` |
| Vue directory | kebab-case | `staff-member/` |
| RBAC role | lowercase | `staff` |
| UI label | Title Case | "Staff Member" |

## Execution Order

1. **DB migrations** - rename tables, columns, FKs, role row
2. **Backend models & relations** - class renames, relationship methods
3. **Backend infrastructure** - repositories, interfaces, DTOs, providers
4. **Backend API layer** - controllers, resources, requests, routes
5. **Backend services & middleware** - business logic, RBAC checks
6. **Backend seeders, factories, commands** - data layer
7. **Backend tests** - update all test files
8. **Frontend store** - rename file, store ID, actions, endpoints
9. **Frontend router** - paths, names, imports
10. **Frontend views & components** - file renames, UI labels, imports
11. **Frontend tests** - unit, smoke, e2e
12. **Documentation** - all markdown files

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Missed reference causes runtime error | Run full test suite after each phase; grep sweep at end |
| DB migration breaks existing data | Test migration on staging DB dump first |
| Frontend/backend deploy mismatch | Deploy backend first, then frontend immediately after |
| Permission string confusion (employee-menu vs role) | Permission strings (employee-menu, employee-create etc.) are separate from role name; decide whether to also rename permissions or keep as-is |

## Open Question

**Permission strings:** Current permissions like `employee-menu`, `employee-create`, `employee-list` are separate from the role name. These could optionally be renamed to `staff-member-menu`, `staff-member-create`, etc. for full consistency, but this adds scope. **Recommendation:** rename permissions too for full consistency since this is a hard cutover.
