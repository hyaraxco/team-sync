# Security Hardening Implementation Plan

> **Status:** COMPLETED (2026-05-10)

**Goal:** Fix 6 security vulnerabilities found in audit — public endpoint exposure, file upload bypass, token lifetime, permission gaps, exception leaking, and permissive CORS.

**Architecture:** All changes in `team-sync-be`. No frontend changes. Each task is independent. Tests use Pest with SQLite :memory:.

**Tech Stack:** Laravel 12, PHP 8.2, Pest 4, Spatie Permission, Sanctum

---

## Correction Note

Finding #4 from the audit (routes without permission middleware) was **incorrect**. `TeamController`, `MeetingController`, `StaffMemberProfileController`, `ProjectController`, and `ProjectTaskController` all implement `HasMiddleware` with `PermissionMiddleware` at the controller level. Routes are protected.

---

### Task 1: Protect `/setup/doctor` Endpoint ✅

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/SetupController.php:50`
- Modify: `team-sync-be/tests/Feature/Setup/SetupControllerTest.php`

**Change:** Added guard — if superadmin exists, return 403. Doctor endpoint only accessible during first-boot.

**Tests:** 9 pass (2 new: `test_doctor_endpoint_returns_forbidden_when_setup_completed`, `test_doctor_endpoint_accessible_when_no_superadmin`)

---

### Task 2: Add MIME Validation to Task Attachment Upload ✅

**Files:**
- Modify: `team-sync-be/app/Http/Requests/ProjectTaskAttachmentStoreRequest.php:26`
- Create: `team-sync-be/tests/Feature/Project/ProjectTaskAttachmentValidationTest.php`

**Change:** Added `mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,txt,zip` to file validation rule.

**Tests:** 6 pass (reject php/exe/sh, accept pdf/jpg/docx)

---

### Task 3: Set Sanctum Token Expiration ✅

**Files:**
- Modify: `team-sync-be/config/sanctum.php:53`
- Modify: `team-sync-be/.env.example`

**Change:** `'expiration' => null` → `'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 43200)` (30 days default, configurable via env)

**Tests:** Existing tests pass

---

### Task 4: Fix HybridScheduleOverrideController ✅

**Files:**
- Create: `team-sync-be/app/Http/Requests/HybridScheduleOverrideStoreRequest.php`
- Create: `team-sync-be/app/Http/Requests/HybridScheduleOverrideRejectRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/HybridScheduleOverrideController.php`

**Change:** Refactored to use FormRequest, HasMiddleware, ResponseHelper. Added `attendance-menu` permission middleware on approve/reject methods. Replaced `response()->json()` with `ResponseHelper::jsonResponse()`. Added proper error handling with logging.

**Tests:** 11 pass (6 HybridWorkSchedule + 5 MyHybridOverrides)

---

### Task 5: Restrict CORS Configuration ✅

**Files:**
- Modify: `team-sync-be/config/cors.php`

**Change:** `allowed_methods: ['*']` → `['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']`. `allowed_headers: ['*']` → explicit list (Accept, Authorization, Content-Type, X-Requested-With, X-XSRF-TOKEN, Origin, Referer). `max_age: 0` → `86400`.

**Tests:** Existing tests pass

---

### Task 6: Add MIME Restriction to Team Icon Upload ✅

**Files:**
- Modify: `team-sync-be/app/Http/Requests/Team/TeamStoreRequest.php:23`
- Modify: `team-sync-be/app/Http/Requests/Team/TeamUpdateRequest.php:17`

**Change:** Added `mimes:jpeg,png,jpg,webp` to icon validation rule (prevents SVG XSS).

**Tests:** 31 pass

---

## Summary

| Task | Files Changed | Tests |
|------|--------------|-------|
| 1. Setup doctor guard | 2 | 9 pass |
| 2. Attachment mimes | 2 | 6 pass |
| 3. Token expiration | 2 | existing pass |
| 4. Hybrid controller | 3 | 11 pass |
| 5. CORS restriction | 1 | existing pass |
| 6. Team icon mimes | 2 | 31 pass |
| **Total** | **12** | **861 pass, 0 fail** |
