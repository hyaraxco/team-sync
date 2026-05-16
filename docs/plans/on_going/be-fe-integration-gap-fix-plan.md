# BE-FE Integration Gap Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix remaining BE-FE integration gaps identified in audit report (2026-05-14)

**Architecture:** FE-only changes for most tasks (timezone, polling, toast, empty states, validation). BE changes needed for THR reopen endpoint, leave proof re-upload endpoint, and attendance period lock endpoint.

**Tech Stack:** Vue 3 Composition API, Pinia, Tailwind CSS, Luxon, Laravel 12, Pest

---

## Audit Reality Check

Before implementing, verify these audit items against actual codebase:

| Audit Item | Audit Says | Actual Status | Action |
|---|---|---|---|
| Payroll reopen confirmation | Missing | **ALREADY IMPLEMENTED** — `PayrollDetail.vue:2171-2232` has full modal | Skip |
| Notification polling | Missing | **PARTIALLY FIXED** — `Header.vue:235-261` polls unread count every 15s | Add toast trigger only |
| Permission mismatch | Critical | **MINOR** — Route guard loose but buttons correctly gated via `can()` | Low priority fix |
| Timezone handling | Critical | **CONFIRMED** — uses browser TZ not company TZ | Fix |
| THR reopen | Missing | **CONFIRMED** — no store action, no UI | Full implementation |
| Attendance period lock | Missing | **CONFIRMED** — no lock button, no store action | Full implementation |
| Leave proof re-upload | Missing | **CONFIRMED** — one-shot upload only | Full implementation |
| Concurrent edit protection | Missing | **CONFIRMED** — zero protection in any store | Add BE + FE |
| Overtime-payroll link | Missing | **CONFIRMED** — no display in payroll detail | FE display |
| Notification toast | Missing | **CONFIRMED** — polling exists, no toast trigger | Add toast |
| Empty state | Partial | **CONFIRMED** — component exists, inconsistent adoption | Replace inline |
| File upload validation | Inconsistent | **CONFIRMED** — task attachments have no size check | Add validation |
| Bulk action feedback | All-or-nothing | **CONFIRMED** — DB transaction, no partial failure | Requires BE change |
| Confirmation dialogs | Partial | **CONFIRMED** — component exists, missing from some flows | Add to remaining |

---

## Task 1: Timezone — Use Company Timezone Instead of Browser

**Files:**
- Modify: `team-sync-fe/src/helpers/format.js:5-11`
- Modify: `team-sync-fe/src/stores/auth.js` — ensure company timezone is stored
- Test: `team-sync-fe/src/tests/utils/format.test.js` (if exists)

**Context:** `formatToClientTimezone` currently uses `Intl.DateTimeFormat().resolvedOptions().timeZone` (browser TZ). Should use company timezone from auth store (BE has configurable per-company timezone, default `Asia/Jakarta`).

- [ ] **Step 1: Check auth store for company timezone**

Read `team-sync-fe/src/stores/auth.js` — check if `user.company.timezone` is available after login.

- [ ] **Step 2: Create `formatToCompanyTimezone` helper**

```js
// team-sync-fe/src/helpers/format.js
export function formatToCompanyTimezone(date, format = "dd MMM yyyy HH:mm") {
    const authStore = useAuthStore();
    const companyTz = authStore.user?.company?.timezone || "Asia/Jakarta";
    return DateTime.fromISO(date, { zone: "utc" })
        .setZone(companyTz)
        .setLocale("id")
        .toFormat(format);
}
```

- [ ] **Step 3: Update `formatToClientTimezone` to use company timezone**

Replace `Intl.DateTimeFormat().resolvedOptions().timeZone` with company timezone from auth store. Keep `formatToClientTimezone` as the function name to avoid breaking changes across all call sites.

```js
export function formatToClientTimezone(date, format = "dd MMM yyyy HH:mm") {
    const authStore = useAuthStore();
    const timezone = authStore.user?.company?.timezone || "Asia/Jakarta";
    return DateTime.fromISO(date, { zone: "utc" })
        .setZone(timezone)
        .setLocale("id")
        .toFormat(format);
}
```

- [ ] **Step 4: Verify auth store exposes company timezone**

If `user.company` doesn't include timezone, check BE `AuthController` or `UserProfileResource` and add `timezone` to the response. Check `team-sync-be/app/Http/Resources/` for the user resource.

- [ ] **Step 5: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-fe/src/helpers/format.js
git commit -m "fix: use company timezone instead of browser timezone in formatToClientTimezone"
```

---

## Task 2: Notification Toast — Show Toast on New Notifications

**Files:**
- Modify: `team-sync-fe/src/components/admin/Header.vue:235-261`
- Modify: `team-sync-fe/src/stores/notifications.js`

**Context:** Header.vue already polls `fetchUnreadCount()` every 15s. When count increases, should show a toast "You have new notifications" and optionally refetch the notification list.

- [ ] **Step 1: Track previous unread count in Header.vue**

```js
// team-sync-fe/src/components/admin/Header.vue
const previousUnreadCount = ref(0);
```

- [ ] **Step 2: Add toast trigger when count increases**

In the polling callback (around line 240), compare new count with previous:

```js
unreadPollingIntervalId.value = window.setInterval(async () => {
    await fetchUnreadCount();
    if (unreadCount.value > previousUnreadCount.value && previousUnreadCount.value > 0) {
        toast.info("New Notification", "You have new notifications");
    }
    previousUnreadCount.value = unreadCount.value;
}, unreadPollingIntervalMs);
```

Note: Skip toast on first load (`previousUnreadCount.value > 0` guard) to avoid toast on page refresh.

- [ ] **Step 3: Initialize previous count on mount**

After the first `fetchUnreadCount()` call, set `previousUnreadCount.value = unreadCount.value`.

- [ ] **Step 4: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 5: Commit**

```bash
git add team-sync-fe/src/components/admin/Header.vue
git commit -m "feat: show toast notification when unread count increases"
```

---

## Task 3: THR Reopen — Add Store Action + UI

**Files:**
- Modify: `team-sync-fe/src/stores/thr.js` — add `reopenThr` action
- Modify: `team-sync-fe/src/views/admin/payroll/ThrManagement.vue` — add reopen button + modal
- Test: `team-sync-fe/src/tests/stores/thr.test.js` (if exists)

**Context:** `thr.js` has simulate, generate, approve, markAsPaid but NO reopen. `ThrManagement.vue` only shows View/Approve/Mark Paid buttons. Need to add reopen similar to `payroll.js::reopenPayroll` pattern.

- [ ] **Step 1: Add `reopenThr` to store**

```js
// team-sync-fe/src/stores/thr.js
async reopenThr(id, payload) {
    this.loading = true;
    try {
        const response = await axiosInstance.post(`/thr/${id}/reopen`, payload);
        this.success = response.data.message;
        return response.data.data;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    } finally {
        this.loading = false;
    }
},
```

- [ ] **Step 2: Add reopen button in ThrManagement.vue**

In the action column (around line 253-275), add reopen button for `approved` or `paid` status:

```vue
<button
    v-if="thr.status === 'approved' || thr.status === 'paid'"
    @click="openReopenModal(thr)"
    class="text-amber-600 hover:text-amber-800"
>
    Reopen
</button>
```

- [ ] **Step 3: Add reopen confirmation modal**

Follow the pattern from `PayrollDetail.vue:2171-2232` — use `ModalWrapper` with:
- Warning text about re-approval requirement
- Required textarea for reopen reason (min 10 chars)
- Confirm/Cancel buttons with loading state

- [ ] **Step 4: Wire up handler**

```js
const handleReopenThr = async () => {
    if (reopenReason.value.length < 10) return;
    await store.reopenThr(selectedThr.value.id, { reason: reopenReason.value });
    toast.success("THR Reopened", "THR has been reopened and requires re-approval");
    showReopenModal.value = false;
    reopenReason.value = "";
    await store.fetchThrPayrolls();
};
```

- [ ] **Step 5: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-fe/src/stores/thr.js team-sync-fe/src/views/admin/payroll/ThrManagement.vue
git commit -m "feat: add THR reopen action and UI with confirmation modal"
```

---

## Task 4: Attendance Period Lock — Add Lock/Unlock Flow

**Files:**
- Modify: `team-sync-fe/src/stores/attendancePeriod.js` — add `lockPeriod` action
- Modify: `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue` — add lock button
- Check BE: `team-sync-be/app/Http/Controllers/AttendancePeriodController.php` — verify lock endpoint exists

**Context:** `attendancePeriod.js` has `fetchAllPaginated`, `fetchReadiness`, `createPeriod`, `updatePeriod`. No lock action. `AttendancePeriods.vue` shows status but no way to transition to `locked`.

- [ ] **Step 1: Check BE for lock endpoint**

Verify if `AttendancePeriodController` has a `lock` or `transition` endpoint. If not, need to add one.

- [ ] **Step 2: Add `lockPeriod` to store**

```js
// team-sync-fe/src/stores/attendancePeriod.js
async lockPeriod(id) {
    this.loading = true;
    try {
        const response = await axiosInstance.post(`/attendance-periods/${id}/lock`);
        this.success = response.data.message;
        return response.data.data;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    } finally {
        this.loading = false;
    }
},
```

- [ ] **Step 3: Add lock button in AttendancePeriods.vue**

Add lock button visible only when `status === 'review'`:

```vue
<button
    v-if="selectedPeriod.status === 'review'"
    @click="handleLockPeriod"
    class="blue-gradient text-white px-4 py-2 rounded-xl"
>
    Lock Period
</button>
```

- [ ] **Step 4: Add confirmation dialog**

Use `ConfirmationModal` with warning that locking is required before payroll generation.

- [ ] **Step 5: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-fe/src/stores/attendancePeriod.js team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue
git commit -m "feat: add attendance period lock action and UI"
```

---

## Task 5: Leave Proof Re-upload — Allow Re-upload After Rejection

**Files:**
- Modify: `team-sync-fe/src/stores/leaveRequest.js` — add `reuploadProof` action
- Modify: `team-sync-fe/src/views/staff-member/MyAttendance.vue` — add re-upload UI in detail modal
- Check BE: verify `POST /leave-requests/{id}/proof` allows re-upload when `proof_review_status === 'rejected'`

**Context:** Currently proof upload is one-shot during leave request creation. If HR rejects proof, employee has no way to re-upload. Need to add re-upload path in the leave detail modal.

- [ ] **Step 1: Check BE proof endpoint**

Verify `LeaveRequestController::uploadProof` allows re-upload when `proof_review_status === 'rejected'`. If it rejects, need to modify BE to allow re-upload.

- [ ] **Step 2: Add proof status display in MyAttendance.vue detail modal**

In the leave detail modal (around line 1160-1306), add proof section:

```vue
<div v-if="selectedLeave.proof_file" class="mt-4">
    <h4 class="font-medium text-gray-700">Proof Document</h4>
    <div class="flex items-center gap-2 mt-2">
        <a :href="selectedLeave.proof_file_url" target="_blank" class="text-primary-600 underline">
            View Proof
        </a>
        <span
            v-if="selectedLeave.proof_review_status === 'rejected'"
            class="text-red-600 text-sm"
        >
            Rejected: {{ selectedLeave.proof_review_notes }}
        </span>
        <span
            v-else-if="selectedLeave.proof_review_status === 'approved'"
            class="text-green-600 text-sm"
        >
            Approved
        </span>
    </div>
</div>
```

- [ ] **Step 3: Add re-upload button for rejected proofs**

```vue
<div v-if="selectedLeave.proof_review_status === 'rejected'" class="mt-3">
    <label class="btn-secondary cursor-pointer">
        <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleReuploadProof" />
        Upload New Proof
    </label>
    <p class="text-xs text-gray-500 mt-1">Max 5MB, PDF/JPG/PNG</p>
</div>
```

- [ ] **Step 4: Add `reuploadProof` handler**

```js
const handleReuploadProof = async (event) => {
    const file = event.target.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        toast.error("File Too Large", "Proof file must be under 5MB");
        return;
    }
    await leaveRequestStore.uploadProof(selectedLeave.value.id, file);
    toast.success("Proof Uploaded", "New proof has been submitted for review");
    await fetchLeaveRequests();
};
```

- [ ] **Step 5: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-fe/src/views/staff-member/MyAttendance.vue
git commit -m "feat: allow leave proof re-upload after rejection"
```

---

## Task 6: Confirmation Dialogs — Add to Remaining Destructive Actions

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue` — add confirm for generate payroll
- Modify: `team-sync-fe/src/views/admin/payroll/ThrManagement.vue` — add confirm for mark-as-paid

**Context:** `ConfirmationModal.vue` and `useConfirmAction.js` composable exist. Used in PayrollDetail, TemplateManagement, ReviewCycleList. Missing from AttendancePeriods and THR mark-as-paid.

- [ ] **Step 1: Add confirmation to AttendancePeriods generate payroll**

Replace direct `generatePayroll` call with `useConfirmAction` composable pattern:

```js
const { isOpen, isLoading, openModal, closeModal, confirmAction } = useConfirmAction();

const handleGeneratePayroll = () => {
    openModal(
        "Generate Payroll?",
        `Generate payroll for the period "${selectedPeriod.value.name}"? This will create payroll records for all eligible employees.`,
        async () => {
            await payrollStore.generatePayroll({ attendance_period_id: selectedPeriod.value.id });
            toast.success("Payroll Generated", "Payroll has been generated successfully");
        }
    );
};
```

- [ ] **Step 2: Add confirmation to THR mark-as-paid**

In `ThrManagement.vue`, replace direct `markAsPaid` call with confirmation:

```js
const handleMarkAsPaid = () => {
    openModal(
        "Mark THR as Paid?",
        `Mark THR for ${selectedThr.value.details_count} employees as paid? Total: ${formatIDR(selectedThr.value.total_amount)}`,
        async () => {
            await store.markAsPaid(selectedThr.value.id, { payment_date: paymentDate.value });
            toast.success("THR Paid", "THR has been marked as paid");
        }
    );
};
```

- [ ] **Step 3: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 4: Commit**

```bash
git add team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue team-sync-fe/src/views/admin/payroll/ThrManagement.vue
git commit -m "feat: add confirmation dialogs to attendance period and THR actions"
```

---

## Task 7: Empty State — Replace Inline Empty States

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/AttendanceList.vue:380,441`
- Modify: `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue`
- Modify: `team-sync-fe/src/views/admin/payroll/PayrollComparison.vue:124-127`
- Modify: `team-sync-fe/src/views/admin/payroll/PayrollCreate.vue:551-557`

**Context:** `EmptyState.vue` exists in `components/common/`. Many views use it, but some still use inline `<div class="text-center...">` or have no empty state at all.

- [ ] **Step 1: Replace inline empty states in AttendanceList.vue**

Replace lines 380 and 441 inline divs with:

```vue
<EmptyState
    icon="CalendarX"
    title="No Attendance Records"
    subtitle="No attendance records found for the selected filters."
/>
```

- [ ] **Step 2: Add empty state to AttendancePeriods.vue**

Add `EmptyState` when period list is empty:

```vue
<EmptyState
    v-if="!loading && periods.length === 0"
    icon="Calendar"
    title="No Attendance Periods"
    subtitle="Create your first attendance period to start tracking."
    size="md"
/>
```

- [ ] **Step 3: Replace inline empty states in PayrollComparison.vue and PayrollCreate.vue**

Replace inline divs with `EmptyState` component.

- [ ] **Step 4: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 5: Commit**

```bash
git add team-sync-fe/src/views/admin/attendance/AttendanceList.vue team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue team-sync-fe/src/views/admin/payroll/PayrollComparison.vue team-sync-fe/src/views/admin/payroll/PayrollCreate.vue
git commit -m "fix: replace inline empty states with EmptyState component"
```

---

## Task 8: File Upload Validation — Add Client-side Size Check for Task Attachments

**Files:**
- Modify: `team-sync-fe/src/components/admin/project/detail/TaskDetailModal.vue:396-413`
- Modify: `team-sync-fe/src/stores/task.js:213-226` — optional: add validation in store too

**Context:** Leave proof and staff photo have client-side size validation. Task attachments have zero validation — user uploads 10MB file, waits, then gets 422.

- [ ] **Step 1: Add file size check in TaskDetailModal.vue**

In `handleAttachmentSelected` (line 396), add validation before upload:

```js
const handleAttachmentSelected = async (event) => {
    const file = event?.target?.files?.[0];
    event.target.value = "";
    if (!file || !canCollaborateTask.value) return;

    if (file.size > 5 * 1024 * 1024) {
        toast.error("File Too Large", "Task attachment must be under 5MB");
        return;
    }

    isUploadingAttachment.value = true;
    try {
        await uploadTaskAttachment(props.task.id, file);
        toast.success("Attachment Uploaded", "File has been attached to the task");
    } catch (error) {
        toast.error("Upload Failed", normalizeErrorMessage(error));
    } finally {
        isUploadingAttachment.value = false;
    }
};
```

- [ ] **Step 2: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 3: Commit**

```bash
git add team-sync-fe/src/components/admin/project/detail/TaskDetailModal.vue
git commit -m "fix: add client-side file size validation for task attachments"
```

---

## Task 9: Overtime-Payroll Link — Display Overtime in Payroll Detail

**Files:**
- Modify: `team-sync-fe/src/views/admin/payroll/PayrollDetail.vue` — add overtime column or section
- Check BE: `team-sync-be/app/Http/Resources/PayrollDetailResource.php` — verify overtime fields are included

**Context:** PayrollDetail.vue shows Employee, Job Position, Bank Account, Attendance, Basic Salary, Deductions, Adjustments, Net Salary, Status. No overtime display. Overtime module is fully independent.

- [ ] **Step 1: Check BE PayrollDetailResource for overtime fields**

Read `team-sync-be/app/Http/Resources/PayrollDetailResource.php` — check if `overtime_hours` and `overtime_amount` are returned.

- [ ] **Step 2: If missing, add overtime fields to BE resource**

Add to `PayrollDetailResource::toArray()`:

```php
'overtime_hours' => $this->overtime_hours,
'overtime_amount' => $this->overtime_amount,
```

- [ ] **Step 3: Add overtime display in PayrollDetail.vue**

In the employee detail section (around line 1033-1043), add overtime info:

```vue
<div v-if="detail.overtime_hours > 0" class="text-sm text-gray-600">
    <span class="font-medium">Overtime:</span>
    {{ detail.overtime_hours }}h — {{ formatIDR(detail.overtime_amount) }}
</div>
```

- [ ] **Step 4: Run tests**

```bash
cd team-sync-fe && bun run test
cd team-sync-be && composer test
```

- [ ] **Step 5: Commit**

```bash
git add team-sync-fe/src/views/admin/payroll/PayrollDetail.vue team-sync-be/app/Http/Resources/PayrollDetailResource.php
git commit -m "feat: display overtime hours and amount in payroll detail"
```

---

## Task 10: Permission Alignment — Tighten Payroll Route Guard

**Files:**
- Modify: `team-sync-fe/src/router/payroll.js:92-98`

**Context:** PayrollDetail route requires `payroll-list` (read-only). Action buttons inside are correctly gated with `can()`. But a user with only `payroll-list` (e.g., HR) can navigate to the detail page and see an empty/non-functional page. Consider tightening to `payroll-process` or keeping as-is if read-only access is intentional.

- [ ] **Step 1: Verify if HR should see payroll detail**

Check if `payroll-list` permission is intentionally given to HR for viewing purposes. If yes, keep route guard as-is (buttons are correctly hidden). If no, change to `payroll-process`.

- [ ] **Step 2: If tightening needed, update route meta**

```js
// team-sync-fe/src/router/payroll.js:92-98
meta: {
    requiredPermission: "payroll-process", // was: payroll-list
}
```

- [ ] **Step 3: Run tests**

```bash
cd team-sync-fe && bun run test
```

- [ ] **Step 4: Commit**

```bash
git add team-sync-fe/src/router/payroll.js
git commit -m "fix: align payroll route guard permission with BE requirements"
```

---

## Task 11: Concurrent Edit Protection — Add BE Optimistic Locking

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` — add `updated_at` check
- Modify: `team-sync-be/app/Http/Requests/` — add `updated_at` field to update requests
- Modify: `team-sync-fe/src/stores/payroll.js` — send `updated_at` in update payloads
- Modify: `team-sync-fe/src/stores/payroll.js` — handle 409 responses

**Context:** No optimistic locking anywhere. Two users can edit same payroll detail simultaneously, last write wins. Add `updated_at` timestamp check before save.

- [ ] **Step 1: Add `updated_at` to PayrollDetail update request**

In the FormRequest for payroll detail update, add `updated_at` as a required field.

- [ ] **Step 2: Add timestamp check in BE controller/service**

```php
// In PayrollService or PayrollController
if ($request->updated_at && $request->updated_at !== $detail->updated_at->toISOString()) {
    return response()->json([
        'success' => false,
        'message' => 'Record was modified by another user. Please refresh and try again.',
    ], 409);
}
```

- [ ] **Step 3: Send `updated_at` from FE store**

In `payroll.js::updateDetail`, include `updated_at` from the fetched record:

```js
async updateDetail(payrollId, detailId, payload) {
    const response = await axiosInstance.put(
        `/payrolls/${payrollId}/details/${detailId}`,
        { ...payload, updated_at: payload.updated_at }
    );
    return response.data;
}
```

- [ ] **Step 4: Handle 409 in FE**

In the component calling updateDetail, catch 409 and show specific error:

```js
catch (error) {
    if (error.response?.status === 409) {
        toast.error("Conflict", "This record was modified by another user. Refreshing data...");
        await store.fetchPayrollDetails(payrollId);
    }
}
```

- [ ] **Step 5: Add BE tests**

Test that updating with stale `updated_at` returns 409.

- [ ] **Step 6: Run all tests**

```bash
cd team-sync-be && composer test
cd team-sync-fe && bun run test
```

- [ ] **Step 7: Commit**

```bash
git add team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/app/Services/Payroll/ team-sync-fe/src/stores/payroll.js
git commit -m "feat: add optimistic locking for payroll detail edits"
```

---

## Task 12: Bulk Action Feedback — Improve Partial Failure Reporting

**Files:**
- Modify: `team-sync-be/app/Repositories/LeaveRequestRepository.php:239-294`
- Modify: `team-sync-be/app/Http/Controllers/LeaveRequestController.php`
- Modify: `team-sync-fe/src/stores/leaveRequest.js:161-179`
- Modify: `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue:181-204`

**Context:** Bulk leave action runs in DB transaction — all-or-nothing. If any record fails, entire batch fails. Need to allow partial success.

- [ ] **Step 1: Modify BE to allow partial success**

Change `LeaveRequestRepository::bulkAction` from transaction-wrapped all-or-nothing to per-item processing:

```php
public function bulkAction(array $ids, string $action): array
{
    $succeeded = [];
    $failed = [];

    foreach ($ids as $id) {
        try {
            $leaveRequest = $this->model->findOrFail($id);
            // validate scope, status, entitlement
            if ($action === 'approve') {
                $leaveRequest->update(['status' => 'approved']);
            } else {
                $leaveRequest->update(['status' => 'rejected']);
            }
            $succeeded[] = $leaveRequest->id;
        } catch (\Exception $e) {
            $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
        }
    }

    return ['succeeded' => $succeeded, 'failed' => $failed];
}
```

- [ ] **Step 2: Update controller response**

Return `{ succeeded: [...], failed: [...] }` shape.

- [ ] **Step 3: Update FE to handle partial results**

```js
// leaveRequest.js
async bulkAction(ids, action) {
    const response = await axiosInstance.post("leave-requests/bulk-action", { ids, action });
    return response.data.data; // { succeeded: [], failed: [] }
}
```

- [ ] **Step 4: Show detailed feedback in LeaveRequestList.vue**

```js
const result = await store.bulkAction(selectedIds.value, action);
if (result.failed.length > 0) {
    toast.warning("Partial Success", `${result.succeeded.length} ${action}d, ${result.failed.length} failed`);
} else {
    toast.success("Success", `${result.succeeded.length} requests ${action}d`);
}
```

- [ ] **Step 5: Run all tests**

```bash
cd team-sync-be && composer test
cd team-sync-fe && bun run test
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Repositories/LeaveRequestRepository.php team-sync-be/app/Http/Controllers/LeaveRequestController.php team-sync-fe/src/stores/leaveRequest.js team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue
git commit -m "feat: support partial success in bulk leave actions"
```

---

## Execution Order

| Order | Task | Priority | Effort | Dependencies |
|-------|------|----------|--------|-------------|
| 1 | Task 1: Timezone | Critical | Small | None |
| 2 | Task 2: Notification Toast | Critical | Small | None |
| 3 | Task 8: File Upload Validation | Medium | Tiny | None |
| 4 | Task 7: Empty States | Medium | Small | None |
| 5 | Task 3: THR Reopen | High | Medium | None |
| 6 | Task 4: Attendance Period Lock | High | Medium | BE endpoint check |
| 7 | Task 5: Leave Proof Re-upload | High | Medium | BE endpoint check |
| 8 | Task 6: Confirmation Dialogs | Medium | Small | None |
| 9 | Task 10: Permission Alignment | Low | Tiny | Decision needed |
| 10 | Task 9: Overtime-Payroll Link | Medium | Medium | BE resource check |
| 11 | Task 11: Concurrent Edit Protection | Medium | Large | BE + FE |
| 12 | Task 12: Bulk Action Feedback | Medium | Large | BE + FE |

**Suggested PR grouping:**
- **PR 1 (Quick wins):** Tasks 1, 2, 7, 8 — FE-only, small changes, no BE dependency
- **PR 2 (Missing features):** Tasks 3, 4, 5, 6 — FE features with BE verification
- **PR 3 (Integration):** Tasks 9, 10, 11, 12 — BE+FE changes, larger scope

---

## Testing Checklist

After all tasks:
- [ ] `cd team-sync-fe && bun run test` — all 981+ tests pass
- [ ] `cd team-sync-be && composer test` — all 1478+ tests pass
- [ ] `cd team-sync-fe && bun run e2e` — all 109 E2E tests pass
- [ ] Manual: timezone displays company TZ not browser TZ
- [ ] Manual: toast appears when new notification arrives
- [ ] Manual: THR reopen works from ThrManagement page
- [ ] Manual: attendance period can be locked from review status
- [ ] Manual: leave proof can be re-uploaded after rejection
- [ ] Manual: task attachment shows error for files > 5MB
