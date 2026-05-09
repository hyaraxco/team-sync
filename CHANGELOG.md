# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- CI workflow for backend Pest tests (`be-tests.yml`)
- Unit tests for `LicenseService`, `ProjectMembershipService`, `AttendancePeriodService`
- Validation test for `StaffMemberProfileStoreRequest`
- Frontend tests: `thr` store, `useToast`, `useConfirmAction`, `useDarkMode`, `useSearchFilter`
- `ProjectMembershipService` for shared project membership logic
- `HolidayCalendarRepository` with full CRUD
- 20 FormRequest classes for inline validations
- `be-tests.yml` CI workflow for Pest

### Changed
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
- Dead `formatCurrency` (USD) export from `formatUtils.js`
- Unused `teamtnt/laravel-scout-tntsearch-driver` dependency
- Empty `Traits/` directory
- Redundant `console.error` calls across frontend

### Fixed
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
