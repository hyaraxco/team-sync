# Unit Testing Gaps Plan

> **Status:** IN PROGRESS

**Goal:** Close unit testing gaps found in audit — add tests for untested services, composables, and helpers.

**Tech Stack:** Pest 4 (backend), Vitest (frontend)

---

### Task 1: Backend Service Unit Tests (HIGH)

**Untested services:**

| Service | Priority | Tests to Write |
|---------|----------|----------------|
| `ThrService` | HIGH | simulate, generate, approve, markAsPaid |
| `ThrCalculationService` | HIGH | proration, eligibility, tax calculation |
| `PayslipPdfService` | HIGH | render, formatRupiah, calculateBpjsBreakdown |
| `EmailService` | HIGH | sendAttendanceCheckedIn/Out, sendProjectTaskCommentAdded |
| `LicenseService` | HIGH | isFeatureEnabled, getActive |
| `FeedbackService` | MEDIUM | CRUD operations |
| `GoalService` | MEDIUM | CRUD operations |
| `PerformanceReviewService` | MEDIUM | submitSelfAssessment, submitManagerAssessment |
| `ReviewCycleService` | MEDIUM | CRUD operations |
| `ProjectMembershipService` | MEDIUM | isMember, isMemberById |

---

### Task 2: Frontend Composable Tests (HIGH)

**Untested composables:**

| Composable | Tests to Write |
|------------|----------------|
| `useToast` | addToast, removeToast, success/error/warning/info |
| `useSearchFilter` | search, filter, pagination |
| `useConfirmAction` | openModal, closeModal, confirmAction |
| `useDarkMode` | toggle, persist preference |
| `useAnimatedNumber` | animate from 0 to target |
| `useSidebar` | toggle, mobile state |

---

### Task 3: Frontend Helper/Utils Tests (MEDIUM)

| File | Tests to Write |
|------|----------------|
| `permissionHelper.js` | can(), canOneOf() |
| `formatUtils.js` | formatRupiah, formatIDR, capitalize |
| `dateUtils.js` | formatDateShort, formatDateTime |
| `errorHelper.js` | handleError for different status codes |
| `badgeUtils.js` | getStatusBadge, getLeaveTypeBadge |

---

### Task 4: Verify

- Run `composer test` — all backend tests pass
- Run `bun run test` — all frontend tests pass
