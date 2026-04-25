# Error Handling Standardization — Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Eliminate all raw exception message leaks to API responses and silent error swallows in frontend.

**Architecture:** Replace `$e->getMessage()` in API responses with safe generic messages while keeping server-side logging. Fix empty catch blocks in frontend with `console.error`. Categorize exceptions: AuthorizationException → 403 safe message, domain \Exception → 400 safe message, \Throwable → 500 generic + log.

**Tech Stack:** Laravel 12 (PHP), Vue 3 (JS/TS), Pest tests

---

### Task 1: Fix AuthController — High Risk (4 leaks)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/AuthController.php`
- Modify: `team-sync-be/app/Repositories/AuthRepository.php`

**Step 1: Replace getMessage leaks in AuthController**

Pattern: `AuthRepository` throws domain exceptions with safe messages (e.g., "Unauthorized"). These are acceptable for auth flows. But the fallback `catch (\Exception)` on `me()` and `logout()` should NOT expose raw messages on 500.

- `login()` L31: Keep — AuthRepository throws controlled "Unauthorized" with code 401
- `me()` L42: Fix — generic 500 message
- `logout()` L53: Fix — generic 500 message
- `updateProfile()` L76: Keep — AuthRepository throws controlled messages with proper codes

**Step 2: Run BE tests to verify**

Run: `docker compose exec -T web php artisan test --filter=Auth`

**Step 3: Commit**

```
fix(security): AuthController — stop leaking raw exception on me/logout 500
```

---

### Task 2: Fix PerformanceGoalController (1 leak)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceGoalController.php`

**Step 1: Replace getMessage in destroy()**

L109: `$e->getMessage()` → safe domain message. The repository throws "Cannot delete a goal linked to a completed performance review." which is a safe business message, but we should not trust all future exceptions. Use safe message.

**Step 2: Run BE tests**

Run: `docker compose exec -T web php artisan test --filter=Performance`

**Step 3: Commit**

```
fix(security): PerformanceGoalController — safe error message on delete
```

---

### Task 3: Fix PerformanceReviewTemplateController (1 leak)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewTemplateController.php`

**Step 1: Fix store() L66**

`'Failed to create template: ' . $e->getMessage()` → `'Failed to create template'` (already logged above)

**Step 2: Run BE tests**

Run: `docker compose exec -T web php artisan test --filter=Template`

**Step 3: Commit**

```
fix(security): PerformanceReviewTemplateController — stop leaking exception in store
```

---

### Task 4: Fix PayrollController (9 leaks)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php`

**Step 1: Replace all \Exception getMessage leaks**

Pattern: PayrollController catches `\Exception` for domain errors (400) and `\Throwable` for unexpected (500). The `\Exception` catches come from `PayrollRepository` which throws controlled domain messages. These are SAFE business messages (e.g., "Payroll must be pending before it can be approved"). Keep them but add logging.

Exception: L415 `'Internal Server Error: '.$e->getMessage()` on `getAnalytics()` — this is a `\Throwable` catch leaking to 500. Fix this one.

**Step 2: Run BE tests**

Run: `docker compose exec -T web php artisan test --filter=Payroll`

**Step 3: Commit**

```
fix(security): PayrollController — stop leaking Throwable message on analytics 500
```

---

### Task 5: Fix TeamController (2 leaks)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/TeamController.php`

**Step 1: Review L208, L227**

These catch `\Exception` with 400 status — domain validation from TeamRepository. The messages are controlled business logic. Keep but add Log::warning for observability.

**Step 2: Commit**

```
fix(security): TeamController — add logging for domain exception responses
```

---

### Task 6: Fix AttendanceController + AttendanceCorrectionController (7 leaks)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/AttendanceController.php`
- Modify: `team-sync-be/app/Http/Controllers/AttendanceCorrectionController.php`

**Step 1: Review \Exception catches with 400**

These are domain exceptions from repositories (e.g., period locked, correction not allowed). Messages are controlled business logic. Keep but add Log::warning.

**Step 2: Run BE tests**

Run: `docker compose exec -T web php artisan test --filter=Attendance`

**Step 3: Commit**

```
fix(security): Attendance controllers — add logging for domain exception responses
```

---

### Task 7: Fix LeaveRequestController (10 leaks)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/LeaveRequestController.php`

**Step 1: Review patterns**

- AuthorizationException → 403 with `$e->getMessage()`: These are Laravel's own safe messages. Keep.
- `\Exception` → 400: Domain validation. Keep but add Log::warning.

**Step 2: Run BE tests**

Run: `docker compose exec -T web php artisan test --filter=Leave`

**Step 3: Commit**

```
fix(security): LeaveRequestController — add logging for domain exceptions
```

---

### Task 8: Fix ProjectTaskController (14 leaks)

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/ProjectTaskController.php`

**Step 1: Review patterns**

All 14 are `AuthorizationException` → 403. Laravel's authorization messages are safe. Keep as-is.

No changes needed — already safe.

---

### Task 9: Fix Frontend — Empty catch + silent swallows

**Files:**
- Modify: `team-sync-fe/src/views/admin/staff-member/StaffMemberDetail.vue`
- Modify: `team-sync-fe/src/components/admin/dashboard/EmployeeStatistics.vue`

**Step 1: StaffMemberDetail.vue L98**

`catch (error) {}` → `catch (error) { console.error('Failed to delete staff member:', error) }`

**Step 2: EmployeeStatistics.vue L325**

`catch (error) {}` → `catch (error) { console.error('Failed to fetch statistics:', error) }`

**Step 3: Run FE tests**

Run: `bun run test`

**Step 4: Commit**

```
fix(fe): replace silent error swallows with console.error
```

---

### Task 10: Run full test suites + verify

**Step 1:** Run full BE test suite
**Step 2:** Run full FE test suite
**Step 3:** Verify getMessage leak count is reduced

---
