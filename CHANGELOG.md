# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- Aggregate payroll/THR taxable income by recognition period, include taxable employer BPJS benefits, preserve distinct cash/BPJS bases, and persist consistent tax/net outputs (`team-sync-62b`).
- Reconcile final-period PPh 21 against explicit actual history, carry employee refunds and deadlines, and reject missing or overlapping history instead of inferring withholding from paid status (`team-sync-d9d`).
- Bind payroll approvals to current revisions, amounts and policies; preserve superseded decisions and require reapproval after tax/payment-context changes (`team-sync-v93`).
- Store new taxable corrections as integer rupiah, reject fractional imported history, and document employee-funded withholding separately from government-borne tax credits.
- Report the persisted applied correction total in payroll-detail and payslip APIs while retaining raw per-item adjustment audit amounts.
- Allow verified later employment/subjective end dates to refresh unpaid tax sources and invalidate approvals without changing settled history; preserve provenance and reject retrospective edits.
- Reject active-source government-borne tax until monthly credit allocation is supported; retain imported historical credits for final reconciliation.
- Prevent deletion of approval policies with retained decisions, including superseded revisions; retire them through the existing inactive state.
- Include overtime, applied taxable corrections and rounding in the 20-column payroll XLSX so exported earnings and deductions reconcile to persisted net pay.
- Correct monthly TER B/C thresholds against PP 58/2023 Annex A-C, restore the missing category C 33% band, and cover every A/B/C boundary plus persisted payroll tax regressions (`team-sync-2fo`). Existing paid payroll is not recalculated.
- Select BPJS rates by contribution period instead of a single timeless row per component: payroll, THR, payslip PDF, tax recalc command, and the readiness dashboard now pass the salary month to the calculation service, and JP ceilings are period-qualified (Rp 10.042.300 through Feb 2025, Rp 10.547.400 for Mar 2025-Feb 2026, Rp 11.086.300 from Mar 2026) with provenance recorded in `bpjs_rates` descriptions (PR #85).

### Added
- CI workflow for backend Pest tests (`be-tests.yml`)
- Unit tests for `LicenseService`, `ProjectMembershipService`, `AttendancePeriodService`
- Validation test for `StaffMemberProfileStoreRequest`
- Frontend tests: `thr` store, `useToast`, `useConfirmAction`, `useDarkMode`, `useSearchFilter`
- `ProjectMembershipService` for shared project membership logic
- `HolidayCalendarRepository` with full CRUD
- 20 FormRequest classes for inline validations
- `be-tests.yml` CI workflow for Pest
- Document local Strix GPT 6.1 SOL configuration, credential storage, and deferred container verification.

### Changed
- Update the backend asset and frontend dependencies to address known npm advisories. Pin frontend `glob` to 14.0.6 and `minimatch` to 11.0.9 until the Vue test tooling accepts the patched majors.
- Upgrade backend assets to Vite 8 and Laravel Vite Plugin 3, retain the current Bun dependency baseline, and verify frozen installation and production builds in backend CI.
- Rewrite the root README after audit: correct locked PHP requirements, setup paths, backend asset installation, E2E reset warnings, cache settings, and local Strix guidance.
- Keep local tooling state, environment variants, credentials, private keys, and database dumps out of version control; retain shareable environment examples.
- Gate `/setup/doctor` endpoint behind first-boot check
- Add `mimes:` validation to task attachment upload
- Set Sanctum token expiration to 30 days (configurable via `SANCTUM_TOKEN_EXPIRATION`)
- Refactor `HybridScheduleOverrideController` to use FormRequest, ResponseHelper, HasMiddleware
- Restrict CORS to explicit methods and headers
- Add `mimes:` restriction to team icon upload
- Move 19 Eloquent calls from controllers to repositories
- Extract `isProjectMember()` to `ProjectMembershipService`
- Filter `$request->all()` with `$request->only()` in performance controllers
- Add `lockForUpdate()` to attendance clock-in
- Remove 36 redundant `console.error` calls, replace with toast notifications
- Create isolated Axios instance (`axios.create` instead of global defaults)
- Consolidate 401 handling (single source in Axios interceptor)

### Removed
- Remove the obsolete backend npm lockfile; Bun is the canonical package manager and lockfile.
- Remove the PR screenshot workflow at the owner's request; preserve E2E regression specs and failure-capture support.
- Dead `formatCurrency` (USD) export from `formatUtils.js`
- Unused `teamtnt/laravel-scout-tntsearch-driver` dependency
- Empty `Traits/` directory
- Redundant `console.error` calls across frontend

### Fixed
- Stabilize the leave-date regression test with a scoped clock and verify that a corrected future range can submit without depending on warning text.
- Use distinct dates in hybrid-override list fixtures so random collisions cannot prevent pagination and status-filter assertions.
- Attendance clock-in race condition (added `lockForUpdate()`)
- Duplicate 401 handling in frontend

## [0.1.0] - 2026-05-07

### Added
- Initial HRIS application setup
- Laravel 12 API backend with Sanctum auth
- Vue 3 SPA frontend with Pinia state management
- Role-based access control (Staff, Manager, HR, Finance, Superadmin)
- Payroll system with BPJS, PPh 21 tax calculation
- Attendance system with hybrid work support
- Leave management with cuti bersama
- Performance review system with TOPSIS ranking
- Project and task management
- Meeting scheduling
- Analytics dashboard
- E2E tests with Playwright
