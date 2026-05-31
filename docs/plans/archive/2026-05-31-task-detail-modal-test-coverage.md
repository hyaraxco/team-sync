# TaskDetailModal Permission Gate — Test Coverage

> **Phase:** 3 of 4 (Frontend Permissions)
> **Status:** COMPLETED (PR #67)
> **Dependencies:** Phase 2 (Frontend Permissions — PR #65)

---

## Overview

Phase 3 migrated `canReviewTask` and `canCollaborateTask` from `hasRole()` to `can()` permission gates. Audit revealed 42 missing test cases across 13 permission-gated computed properties.

---

## Missing Tests (by priority)

### 1. canSearchStaffMembers — ZERO tests
- HR (has staff-member-list) → true
- staff (no staff-member-list) → false
- manager → false
- project-leader → false

### 2. canRejectReview — missing positive path
- manager, review → true
- project leader, review → true

### 3. canCollaborateTask — PL path untested
- project leader, todo → true
- project leader, review → true
- staff, review, own → false
- manager, in_progress → false
- manager, rejected → false
- HR, todo → false
- staff, in_progress, own, NO task-edit → false

### 4. canReviewTask — negative combos
- project-leader WITHOUT task-edit → false
- manager with task-edit but NO project-edit → false
- finance role → false
- superadmin → true

### 5. canReopenDoneAsRejected — thin coverage
- project leader, done → true
- staff, done → false
- HR, done → false

### 6. canMutateEntityOwner — edge cases
- owner, todo, staff, own assigned → true
- owner, todo, staff, NOT assigned → false
- owner, in_progress, manager → true
- no employee_profile → false

### 7. canApproveReview — missing combos
- project leader, review → true
- HR, review → false

### 8. canManageAssignee — edge cases
- in_progress + all perms → true
- rejected + all perms → true

### 9. canStartRework — edge cases
- staff, rejected, NOT own → false
- manager, rejected, own → false
- staff, done, own → false

### 10. canStartTask — edge cases
- HR, own todo task → false
- cancelled status → false

### 11. canSubmitForReview — edge cases
- manager, in_progress, own → false
- staff, rejected, own → false

---

## Completion Checklist

- [x] Add missing tests to TaskDetailModal.test.js
- [x] Run `bun run test` — all tests pass (1129 tests, 145 files)
- [x] Archive this plan
