# PRD Final — Role Dashboard, Sidebar, Settings, and Data Exposure Alignment

**Date:** 2026-05-07
**Status:** Final product requirements / implementation pending
**Owner:** Product + Engineering
**Decision policy:** Strict least-privilege
**Supersedes:** `docs/requirements/2026-05-07-role-dashboard-sidebar-settings-alignment.md`
**Related tracking:** `TODO.md` deferred role matrix item

---

## 1. Executive Summary

Team Sync Pro sudah memiliki banyak modul operasional HRIS: staff, attendance, leave, payroll, THR, overtime, projects, performance, analytics, notifications, setup, dan license. Masalah yang tersisa bukan lagi sekadar menu yang salah tampil, melainkan ketidaksinkronan menyeluruh antara:

- role dan permission seeder,
- middleware/backend route access,
- frontend router guards,
- sidebar visibility,
- dashboard widgets,
- analytics audience,
- staff directory data exposure,
- dan settings ownership.

PRD ini menetapkan satu kebijakan final agar pengalaman setiap role konsisten dari UI sampai API. Kebijakan yang dipilih adalah **strict least-privilege**: setiap role hanya mendapatkan menu, route, endpoint, widget, dan data yang langsung dibutuhkan untuk pekerjaannya.

### Final policy decisions

1. **Payroll operations adalah Finance-owned.** HR tidak menjalankan payroll operasional, kecuali akses read-only payroll context yang benar-benar dibutuhkan untuk readiness/koordinasi data.
2. **Manager tidak mendapat admin staff directory sampai team-scoped staff API tersedia.** Untuk fase ini, manager memakai team/project/performance views yang memang team-scoped.
3. **Finance hanya mendapat payroll-safe staff subset.** Finance tidak boleh melihat profil HR penuh, dokumen personal, data sensitif non-payroll, atau HR-only analytics.
4. **Dashboard tetap satu route adaptif:** `/admin/dashboard` menggunakan role-aware widgets dan role-aware API calls. Tidak membuat banyak dashboard route baru kecuali terbukti diperlukan nanti.
5. **Sidebar, route guard, dan backend permission harus match.** Menu tersembunyi tidak boleh tetap bisa diakses lewat URL/API kecuali endpoint self-service yang memang hidden dan self-scoped.

---

## 2. Problem Statement

Saat ini beberapa area role UX/RBAC masih berpotensi tidak konsisten:

- role bisa memiliki backend permission lebih luas daripada menu yang terlihat;
- sidebar menyembunyikan sebagian fitur, tetapi direct route/API bisa tetap terbuka jika permission terlalu broad;
- dashboard masih berisiko memanggil statistik company-wide untuk role yang seharusnya self/team/payroll-scoped;
- analytics belum dipisahkan jelas antara HR, Finance, Manager, dan Staff;
- staff directory terlalu sensitif untuk dibuka broad ke Staff/Manager/Finance;
- settings masih campur antara attendance/leave, payroll, performance, setup, dan license.

Dampaknya:

- risiko kebocoran data karyawan/payroll;
- user melihat modul yang bukan job-to-be-done role mereka;
- regression sulit dideteksi karena sidebar, route, dan backend tidak memiliki matriks final yang sama;
- tim implementasi berisiko membuat patch ad-hoc yang menyelesaikan satu role tetapi membuka celah role lain.

---

## 3. Goals and Non-Goals

### 3.1 Goals

- Menetapkan matriks final role → dashboard → sidebar → route → API → settings.
- Menutup akses company-wide untuk role yang hanya boleh self/team/payroll-scoped.
- Memastikan staff directory exposure eksplisit dan minim data.
- Memisahkan analytics berdasarkan audience bisnis.
- Menjadikan `/admin/dashboard` adaptif per role tanpa route churn besar.
- Menyediakan acceptance criteria dan test matrix yang bisa langsung dipakai implementasi.

### 3.2 Non-Goals

- Tidak melakukan redesign visual total dashboard/sidebar.
- Tidak memperkenalkan multi-tenant/company scoping baru; aplikasi tetap single-company/self-hosted.
- Tidak mengganti Spatie permission model.
- Tidak membuat native mobile app.
- Tidak mengubah payroll formula, BPJS/PPh 21 logic, atau THR calculation logic kecuali perlu untuk guard akses.
- Tidak membersihkan perubahan/deletion besar di luar scope yang sudah ada di working tree (`.agent`, `.agents`, `_bmad`, dan lain-lain).

---

## 4. Product Principles

1. **Least privilege by default** — permission baru harus narrow dan role-scoped.
2. **UI is not security** — sidebar hiding hanya convenience; backend tetap sumber enforcement.
3. **One role, one coherent daily job** — dashboard dan sidebar harus mendukung pekerjaan harian role tersebut.
4. **Self-service stays self-scoped** — Staff boleh melihat data sendiri, bukan data admin.
5. **Payroll data is sensitive** — Finance boleh melihat payroll context, tetapi bukan HR profile penuh.
6. **Manager sees team operations only** — Manager tidak melihat company-wide workforce/payroll data.
7. **Superadmin is operational owner of the instance** — full system/setup/license access.

---

## 5. Target Roles and Job-to-Be-Done

| Role | Job-to-be-done utama | Scope data final |
|---|---|---|
| `staff` | Self-service absensi, leave, overtime, payslip, profile, task/performance pribadi | Self only |
| `manager` | Mengelola operasi tim: attendance exception, leave/overtime approvals, project/task progress, team performance | Team-scoped only |
| `hr` | Workforce administration, attendance/leave policy, staff lifecycle, performance administration, meeting coordination | HR/company workforce scope; payroll read-only context only when needed |
| `finance` | Payroll readiness, payroll generation/finalization, adjustments, approval matrix, THR, payslip delivery, finance analytics | Payroll/finance scope + payroll-safe staff identity/job subset |
| `superadmin` | Setup, license, diagnostics, all administrative modules | Full instance scope |

---

## 6. Final Role Matrix

### 6.1 Dashboard focus

| Role | `/admin/dashboard` final content | Explicitly forbidden |
|---|---|---|
| Staff | My attendance status, leave balance, my overtime status, recent payslips, my tasks/goals/reviews, notifications | Company-wide stats, admin staff list, admin analytics, payroll ops |
| Manager | Team pulse, team attendance exceptions, pending team leave/overtime approvals, project/task blockers, team performance summary, meeting reminders | Company-wide workforce analytics, payroll data, full staff directory, global settings |
| HR | Workforce headcount, attendance/leave exceptions, staff lifecycle alerts, performance admin status, meeting reminders, HR analytics summaries | Payroll processing/finalization actions; finance-only payroll settings unless explicitly read-only |
| Finance | Payroll readiness, pending payroll approvals, adjustment queue, THR status, payslip delivery, payroll cost/finance analytics | HR full profile details, HR-only workforce analytics, attendance/leave settings |
| Superadmin | Setup status, license status, app doctor/system health, global module status, full overview | None within app policy |

### 6.2 Sidebar/menu access

| Module/Menu | Staff | Manager | HR | Finance | Superadmin |
|---|---:|---:|---:|---:|---:|
| Dashboard | Yes (self widgets) | Yes (team widgets) | Yes (HR widgets) | Yes (finance widgets) | Yes (system/all widgets) |
| My Attendance | Yes | Optional self-service | Optional self-service | Optional self-service | Yes |
| My Overtime | Yes | Optional self-service | Optional self-service | Optional self-service | Yes |
| My Payroll / Payslips | Yes | Optional self-service | Optional self-service | Optional self-service | Yes |
| My Profile | Yes | Yes | Yes | Yes | Yes |
| Projects/Tasks | Own assigned tasks only | Team/project scope | HR-visible only if product needs it | No by default | Yes |
| Teams | No admin menu | Team-scoped view | Full/team administration | No by default | Yes |
| Staff Directory Admin | No | **No until team-scoped API exists** | Full CRUD | Payroll-safe read-only subset only | Full CRUD |
| Attendance Admin | No | Team exception/review only if team-scoped | Full attendance policy/records/admin | No by default | Full |
| Leave Admin | No | Team approvals only | Full leave admin | No | Full |
| Overtime Admin | No | Team approvals only | HR visibility/coordination if needed | Finance payroll processing visibility if needed | Full |
| Payroll | No admin menu | No | Read-only payroll readiness/context only | Full payroll operations | Full |
| Payroll Adjustments | No | No | No by default | Full | Full |
| THR | No admin menu | No | Read-only readiness/context only if needed; no generate/approve/process | Generate/approve/process/finalize | Full |
| Analytics | Personal history only through self screens | Team-scoped analytics only | HR analytics | Finance analytics | Full |
| Meetings | Receive/join only | Team meeting view if needed | Create/broadcast/manage | No by default | Full |
| Settings | No global settings | No global settings | HR settings | Payroll settings | System/all settings |
| Setup/License/App Doctor | No | No | No | No | Full |

### 6.3 Settings ownership

| Settings area | Owner role | Secondary access | Notes |
|---|---|---|---|
| Attendance policies | HR | Superadmin | Includes work mode, grace period, policy mismatch settings |
| Leave entitlements/types/calendar | HR | Superadmin | Includes leave balance rules and cuti bersama/holiday calendar |
| Hybrid schedule settings | HR | Superadmin | Manager can view team schedules only if team-scoped |
| Payroll settings | Finance | Superadmin; HR read-only only if required for readiness | Includes BPJS/tax baseline, payroll rules, export rules |
| Payroll approval matrix | Finance | Superadmin | HR not owner of approval matrix in strict policy |
| Payroll adjustment queue | Finance | Superadmin | Audit fields required for approval decisions |
| Performance templates/outcome rules | HR | Superadmin | Manager participates in reviews but does not configure global rules |
| License/setup/system diagnostics | Superadmin | None | Includes setup wizard and app doctor status |

---

## 7. Functional Requirements

### FR-01 — Source-of-truth role matrix

The system must have one source-of-truth role matrix covering:

- backend permissions,
- frontend route guards,
- sidebar visibility,
- dashboard widget visibility,
- settings sections,
- analytics audience,
- and staff data exposure.

**Acceptance criteria**

- Matrix is represented in code/tests or documented table used by seeders and tests.
- Every role has explicit allow/deny expectations for dashboard, staff directory, analytics, payroll, attendance, leave, settings, setup/license.
- Seeder tests fail if a role receives a forbidden permission.

### FR-02 — Sidebar and router guard alignment

Frontend sidebar visibility must match router `meta.requiredPermission` / `meta.requiredAnyPermissions`.

**Acceptance criteria**

- If a menu item is hidden for a role, direct navigation to its route redirects/blocks.
- Authenticated routes fail closed unless explicitly marked `allowAuthenticated` or guarded by permission metadata.
- Sidebar smoke tests assert visible and hidden menus per role.

### FR-03 — Backend permission enforcement alignment

Backend middleware must enforce the same access policy as frontend route guards.

**Acceptance criteria**

- Every sensitive API route has permission middleware or explicit self-service ownership guard.
- Staff cannot access admin staff directory, admin analytics, payroll operations, or company-wide dashboard endpoints by direct API call.
- Manager cannot access company-wide analytics/payroll/staff directory APIs.
- Finance cannot access HR full staff profile APIs or HR-only workforce analytics.
- Superadmin has full access.

### FR-04 — Role-adaptive dashboard

`/admin/dashboard` must remain the single dashboard route and render widgets based on role.

**Acceptance criteria**

- Staff dashboard calls only self-scoped endpoints such as my attendance/my stats/my payslips/my notifications.
- Manager dashboard calls only team-scoped endpoints.
- HR dashboard can call company-wide workforce/attendance/leave/performance admin endpoints.
- Finance dashboard calls payroll/THR/adjustment/finance analytics endpoints only.
- Superadmin dashboard includes setup/license/app doctor/system overview.
- Missing widget permission must prevent both render and API call.

### FR-05 — Staff directory exposure hardening

Staff directory access must be explicit and least-privilege.

**Acceptance criteria**

- Staff role does not have `staff-member-menu` or `staff-member-list` for admin directory.
- Manager role does not have admin staff directory access until a team-scoped staff directory API is implemented.
- HR and Superadmin retain full staff directory access according to CRUD permissions.
- Finance uses a payroll-safe staff context endpoint/resource only; it must not reuse full HR staff profile resources.
- Payroll-safe subset should include only data needed for payroll operations, such as staff id, name, job title, employment status, payroll eligibility/readiness flags, bank-readiness indicator, and payroll identifiers where required.
- Payroll-safe subset must exclude personal documents, emergency contacts, full HR profile details, and sensitive non-payroll fields.

### FR-06 — Analytics audience split

Analytics must be split by audience and enforced at API and UI layers.

**Acceptance criteria**

- HR analytics: workforce, attendance, leave, performance, headcount/lifecycle trends.
- Finance analytics: payroll cost, THR, deductions, BPJS/tax, payroll readiness, processing/payment status.
- Manager analytics: team-scoped performance/project/attendance metrics only.
- Staff analytics: personal history only through self-service screens, not admin analytics.
- `analytics-menu` alone must not imply access to every analytics endpoint if endpoint audience differs.

### FR-07 — Payroll operations strict ownership

Payroll operational actions are Finance-owned in the final policy.

**Acceptance criteria**

- Finance can access payroll dashboard/detail/create/readiness/settings/approval matrix/adjustment queue/THR approve/process according to dedicated permissions.
- HR does not have payroll create/edit/process/finalize permissions by default.
- HR may have read-only payroll readiness/context permission only if needed to resolve staff data blockers.
- Staff and Manager have no admin payroll permissions.
- Payslip self-service remains available through `payslip-view` and ownership checks.

### FR-08 — Settings domain scoping

Global settings must be split by domain ownership.

**Acceptance criteria**

- Staff and Manager do not see global Settings.
- HR sees HR-owned settings only: attendance, leave, holiday, hybrid schedule, performance templates/outcome rules.
- Finance sees payroll settings only: payroll rules, BPJS/tax baseline, approval matrix, adjustments, THR finance controls.
- Superadmin sees all settings plus license/setup/system diagnostics.
- Direct route access to settings subsections follows the same policy.

### FR-09 — Self-service endpoint ownership

Hidden self-service endpoints must use self-scoped permission and ownership checks, not broad admin permissions.

**Acceptance criteria**

- Staff attendance, leave, overtime, payslip, profile, review, and task self-service actions are constrained to authenticated user’s staff profile.
- Request payload cannot forge `staff_member_id` or actor identity.
- Existing forged attendance/leave/feedback regression tests remain green.

### FR-10 — Notifications/deep links respect role policy

Notifications must not expose deep links to routes the recipient cannot access.

**Acceptance criteria**

- Notification action URLs resolve to recipient-allowed routes.
- Staff payroll notifications link to self-service payslip detail, not admin payroll detail.
- Manager approval notifications link to team-scoped approval views, not company-wide admin lists.
- HR/Finance notifications route to the correct domain-owned work queue.

---

## 8. Recommended Permission Model Changes

This PRD does not require renaming every existing permission immediately, but implementation should move toward narrow permissions where broad permissions are currently overloaded.

### 8.1 Current-risk permissions to audit

| Permission | Risk | Final expectation |
|---|---|---|
| `dashboard-menu`, `dashboard-view` | Too broad if every role calls same dashboard APIs | Keep for route/menu, but widget/API calls must be role-aware |
| `staff-member-menu`, `staff-member-list` | Opens admin directory if granted to non-HR roles | Staff: remove. Manager: remove until team-scoped API exists. Finance: replace with payroll-safe staff context. |
| `analytics-menu`, `analytics-view` | Too broad for mixed HR/Finance/Manager analytics | Split or enforce endpoint-level audience permissions |
| `attendance-menu` | Mixes admin settings and attendance records | Split admin policy/settings vs team review vs self-service where needed |
| `payroll-menu` | Mixes payroll read, process, settings, adjustments | Finance-owned; HR read-only context should use separate permission |
| `payroll-statistics` | Used as settings visibility proxy | Replace with explicit payroll settings/analytics permission if possible |
| `meeting-menu` | Receive/join vs create/broadcast can blur | Keep create/list/admin separate from notification receive/join |

### 8.2 Suggested new or clarified permissions

Implementation may either add these explicitly or map existing permissions to the same semantics in tests:

| Area | Suggested permission | Intended roles |
|---|---|---|
| Dashboard | `dashboard-self-view` | Staff |
| Dashboard | `dashboard-team-view` | Manager |
| Dashboard | `dashboard-hr-view` | HR |
| Dashboard | `dashboard-finance-view` | Finance |
| Dashboard | `dashboard-system-view` | Superadmin |
| Staff data | `staff-member-payroll-context-view` | Finance |
| Staff data | `staff-member-team-context-view` | Manager, only after team-scoped endpoint exists |
| Analytics | `analytics-hr-view` | HR, Superadmin |
| Analytics | `analytics-finance-view` | Finance, Superadmin |
| Analytics | `analytics-team-view` | Manager, Superadmin |
| Payroll | `payroll-readiness-view` | Finance, optional HR read-only |
| Payroll | `payroll-settings-manage` | Finance, Superadmin |
| Settings | `settings-hr-manage` | HR, Superadmin |
| Settings | `settings-finance-manage` | Finance, Superadmin |
| Settings | `settings-system-manage` | Superadmin |

If implementation chooses not to add new permissions in the first PR, it must still prove equivalent behavior via route/API tests.

---

## 9. User Stories

### Staff

- As Staff, I want to see only my attendance, leave, overtime, payslip, profile, tasks/goals/reviews, and notifications so I can complete self-service work without seeing admin data.
- As Staff, I must not be able to open admin staff directory, payroll operations, company analytics, or global settings by URL/API.

### Manager

- As Manager, I want a team dashboard showing attendance exceptions, approvals, blockers, and team performance so I can act on my team quickly.
- As Manager, I must not see payroll, company-wide analytics, full staff directory, or global settings.
- As Manager, I should not receive staff directory admin access until the product has team-scoped staff directory APIs.

### HR

- As HR, I want workforce, attendance, leave, performance, staff lifecycle, and meeting management tools so I can operate HR workflows.
- As HR, I should not execute payroll operations in strict policy, but I may need read-only payroll readiness context to resolve data blockers.
- As HR, I want settings only for HR-owned domains.

### Finance

- As Finance, I want payroll readiness, payroll operations, approval matrix, adjustments, THR, payslip delivery, and finance analytics so monthly payroll can be completed safely.
- As Finance, I need payroll-safe staff context, not full HR staff profile access.
- As Finance, I should not see HR-only workforce analytics or attendance/leave policy settings.

### Superadmin

- As Superadmin, I want full module access plus setup/license/system diagnostics so I can operate and troubleshoot the instance.

---

## 10. Implementation Plan

### Phase 0 — Inventory and matrix lock

1. Create a role-permission matrix artifact for current and target state.
2. Inventory all frontend routes and sidebar entries.
3. Inventory all backend API route middleware for dashboard, analytics, staff directory, payroll, attendance, leave, overtime, settings, setup/license.
4. Mark every route/API as one of: self-scoped, team-scoped, HR-scoped, finance-scoped, system-scoped, public-authenticated.

**Exit gate:** matrix reviewed and no route remains unclassified.

### Phase 1 — Backend RBAC and resource scoping

1. Remove accidental broad permissions from `staff`, `manager`, and `finance` seed assignments.
2. Add narrow permissions/endpoints where existing permissions are too broad.
3. Add payroll-safe staff context resource/endpoint for Finance if Finance still needs staff lookup.
4. Add or enforce team-scoped endpoints before giving Manager any staff/team analytics access.
5. Guard dashboard and analytics endpoints by audience.

**Exit gate:** backend permission/forbidden tests pass for every role.

### Phase 2 — Frontend route/sidebar/settings alignment

1. Update router `meta.requiredPermission` / `requiredAnyPermissions` to match backend.
2. Update Sidebar conditions to match route metadata.
3. Update Settings view into role/domain-owned sections.
4. Update dashboard widget selection and prevent unauthorized API calls.
5. Update notification deep links where routes changed.

**Exit gate:** sidebar and route guard tests pass for each role.

### Phase 3 — Analytics and dashboard split

1. Split dashboard store actions by role/audience.
2. Split analytics store calls by HR/Finance/Manager audience.
3. Add defensive frontend checks so unavailable widgets do not call forbidden APIs.
4. Add empty/forbidden states that explain missing access without leaking data.

**Exit gate:** targeted dashboard/analytics tests and smoke tests pass.

### Phase 4 — E2E smoke and regression hardening

1. Add/refresh Playwright role navigation matrix.
2. Verify each role cannot direct-navigate to forbidden routes.
3. Verify backend returns 403 for forbidden direct API calls.
4. Verify notifications route each role to an allowed destination.

**Exit gate:** focused role E2E smoke green.

---

## 11. Test Strategy

### 11.1 Backend tests

Required test groups:

- `RolePermissionMatrixTest` or equivalent seeder test:
  - staff lacks `staff-member-list`, `analytics-menu`, `payroll-menu`, global settings permissions;
  - manager lacks payroll/admin staff/company analytics permissions;
  - HR lacks payroll process/finalize permissions by default;
  - finance lacks full staff CRUD and HR analytics permissions;
  - superadmin has all permissions.
- API forbidden tests:
  - each role attempts direct GET/POST to forbidden dashboard, analytics, staff directory, payroll, settings endpoints;
  - assert 403, not 200 with filtered data, unless endpoint is explicitly self/team/payroll-safe.
- Resource exposure tests:
  - finance payroll-safe staff endpoint excludes HR-only/sensitive fields;
  - staff self-service endpoints cannot be forged with another `staff_member_id`.

### 11.2 Frontend unit/smoke tests

Required test groups:

- Sidebar matrix smoke test per role.
- Router permission access tests per role.
- Dashboard role widget tests:
  - unauthorized widgets are not rendered;
  - unauthorized store actions/API calls are not invoked.
- Settings section visibility tests per role.
- Analytics tab/section visibility tests per role.

### 11.3 E2E smoke tests

Required scenarios:

1. Staff login:
   - sees self-service menus only;
   - direct `/admin/staff-members`, `/admin/analytics`, `/admin/payroll`, `/admin/settings` are blocked/redirected.
2. Manager login:
   - sees team/project/performance flow only;
   - direct payroll/company analytics/staff directory/settings are blocked.
3. HR login:
   - sees HR staff/attendance/leave/performance/meeting/settings;
   - payroll operational actions are hidden/blocked unless read-only context is explicitly allowed.
4. Finance login:
   - sees payroll/THR/adjustments/approval/finance analytics;
   - HR full staff profile and HR settings are blocked.
5. Superadmin login:
   - sees setup/license/system/all modules.

---

## 12. Data Exposure Rules

### 12.1 Staff self-service

Allowed:

- own attendance/leave/overtime/payslip/profile;
- own assigned tasks/goals/reviews/feedback actions;
- own notifications.

Forbidden:

- other staff profiles;
- admin staff directory;
- payroll batch details;
- company-wide analytics;
- global settings.

### 12.2 Manager team scope

Allowed only when endpoint enforces team membership/manager relationship:

- team attendance exception summary;
- team leave/overtime approvals;
- team projects/tasks;
- team performance summary.

Forbidden until team-scoped endpoint exists:

- full staff directory;
- company workforce analytics;
- payroll and payslip details for team members;
- HR settings.

### 12.3 HR scope

Allowed:

- staff lifecycle/admin profile;
- attendance and leave administration;
- performance administration;
- meeting broadcast/management;
- HR analytics.

Restricted:

- payroll process/finalize/mark paid;
- finance settings ownership.

### 12.4 Finance scope

Allowed:

- payroll periods, details, readiness, approvals, adjustments, THR, payslip delivery;
- payroll-safe staff identity/job/readiness context;
- finance analytics.

Forbidden:

- full HR staff profile;
- personal/emergency/contact documents unrelated to payroll;
- HR workforce analytics not required for payroll;
- attendance/leave/performance global settings.

---

## 13. Notification and Deep Link Rules

| Notification type | Recipient | Link target rule |
|---|---|---|
| Payroll paid / payslip ready | Staff | Self-service payslip detail only |
| Payroll approval/action needed | Finance | Finance payroll detail/approval queue |
| Payroll data blocker | HR if needed | HR-safe readiness/data-quality page, not payroll processing action |
| Leave/overtime approval needed | Manager | Team-scoped approval view |
| Meeting invitation | Staff/Manager/Finance/HR | Meeting join/detail view allowed to recipient |
| Performance review action | Staff/Manager/HR | Role-specific review route according to permission |
| System/license/setup alert | Superadmin | Setup/license/system diagnostics only |

---

## 14. Acceptance Criteria Summary

The feature is done only when all of these are true:

- Staff cannot access admin staff directory, admin analytics, company-wide dashboard statistics, payroll operations, or global settings via sidebar, route, or API.
- Manager cannot access payroll, full staff directory, company-wide analytics, or global settings; any manager data is team-scoped.
- HR can administer workforce, attendance, leave, performance, meetings, and HR settings; HR cannot perform Finance-owned payroll operations by default.
- Finance can operate payroll, THR, adjustments, approval matrix, payroll settings, and finance analytics; Finance sees only payroll-safe staff context.
- Superadmin can access all modules, setup, license, and diagnostics.
- `/admin/dashboard` remains one adaptive route and does not make forbidden API calls for any role.
- Sidebar visibility, router guards, backend middleware, and tests agree for every role.
- Notifications never deep-link a user into a route/API they cannot access.
- Existing validated P0/P1/P2 security fixes remain green.

---

## 15. Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Removing broad permissions breaks existing workflows | Medium/high | Stage changes behind matrix tests and targeted smoke tests |
| Dashboard widgets fail because old endpoint is forbidden | Medium | Split role-specific store actions before tightening backend |
| Finance needs staff data currently only available in full HR resource | High | Create payroll-safe staff context endpoint/resource |
| Manager workflow becomes too limited | Medium | Keep team/project/performance routes; add team-scoped staff endpoint later if truly needed |
| Permission names proliferate | Medium | Prefer clear domain permissions and matrix tests; avoid using `*-menu` as data access permission |
| Existing large unrelated working-tree changes obscure review | High | Keep this PRD-only change scoped; implementation should be separate PR/branch if possible |

---

## 16. Open Items for Implementation Planning

These are not product policy blockers; they are engineering design choices for the implementation plan:

1. Whether to add all suggested narrow permissions in one PR or map existing permissions first and split later.
2. Exact shape/path of Finance payroll-safe staff context endpoint/resource.
3. Exact shape/path of Manager team-scoped analytics endpoints if missing.
4. Whether Settings remains one component with role sections or is split into domain subroutes.
5. Exact dashboard widget component boundaries and store action names.

---

## 17. Verification Commands

Backend:

```bash
cd team-sync-be
composer test
./vendor/bin/pint --test
```

Frontend:

```bash
cd team-sync-fe
bun run test
bun run lint
bun run e2e -- --grep "role|navigation|dashboard|settings|analytics|payroll"
```

Focused commands should be defined in the implementation plan once test filenames are finalized.

---

## 18. Final Product Decision Record

- **Policy:** strict least-privilege.
- **Payroll:** Finance-owned operations; HR read-only payroll context only if required.
- **Manager directory:** hidden/no admin staff directory until team-scoped API exists.
- **Finance directory:** payroll-safe subset only.
- **Dashboard routing:** one adaptive `/admin/dashboard` route.
- **Implementation strategy:** separate planned implementation task because it touches RBAC, route guards, sidebar, dashboard widgets, analytics endpoints, staff resources, and tests.
