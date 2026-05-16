# Bulk Action Feedback — Partial Failure Reporting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve bulk leave approve/reject to allow partial success with detailed feedback instead of all-or-nothing failure.

**Architecture:** Modify backend repository to catch exceptions per-item, return succeeded/failed arrays. Update controller to return new shape. Update frontend store and view to handle partial results.

**Tech Stack:** Laravel 12, PHP 8.2, Vue 3 Composition API, Pinia, Tailwind CSS

---

## Task 1: Modify Backend Repository

**Files:**
- Modify: `team-sync-be/app/Repositories/LeaveRequestRepository.php:239-294`

**Context:** Currently wraps everything in DB::transaction and throws on any failure. Need to catch exceptions per-item while maintaining data integrity.

- [ ] **Step 1: Read current implementation**

Read `team-sync-be/app/Repositories/LeaveRequestRepository.php` lines 239-294 to understand current structure.

- [ ] **Step 2: Replace loop with per-item error handling**

Replace lines 255-291 with:

```php
$succeeded = [];
$failed = [];

foreach ($ids as $id) {
    try {
        $leaveRequest = $leaveRequestsById->get($id);

        if (! $leaveRequest) {
            $failed[] = ['id' => $id, 'reason' => 'Leave Request Not Found'];
            continue;
        }

        $this->authorizeManagerScope($leaveRequest);
        $this->ensurePending($leaveRequest);

        $isApproveAction = $action === 'approve';

        if ($isApproveAction) {
            $this->ensureEntitlementIsValid($leaveRequest);
        }

        $data = [
            'status' => $isApproveAction ? 'approved' : 'rejected',
            'approved_by' => $approverProfileId,
        ];

        $leaveRequestDto = LeaveRequestDto::fromArrayForUpdate($data, $leaveRequest);
        $leaveRequest->update($leaveRequestDto->toArray());

        $updatedLeaveRequest = $leaveRequest->fresh(['staffMember.user', 'approver.user']);
        $succeeded[] = $updatedLeaveRequest;

        DB::afterCommit(function () use ($updatedLeaveRequest, $action) {
            if ($action === 'approve') {
                $this->emailService->sendLeaveRequestApprovedNotification($updatedLeaveRequest);
                return;
            }
            $this->emailService->sendLeaveRequestRejectedNotification($updatedLeaveRequest);
        });
    } catch (\Exception $e) {
        $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
    }
}

return ['succeeded' => collect($succeeded), 'failed' => $failed];
```

- [ ] **Step 3: Verify DB::transaction and lockForUpdate are preserved**

Ensure lines 241-251 (transaction wrapper and lockForUpdate) remain unchanged.

- [ ] **Step 4: Run backend tests**

Run: `cd /Users/hyarax/Documents/project/team-sync/team-sync-be && composer test`
Expected: All tests pass (1478+ tests)

- [ ] **Step 5: Commit**

```bash
git add team-sync-be/app/Repositories/LeaveRequestRepository.php
git commit -m "feat: modify bulkAction to allow partial success with per-item error handling"
```

---

## Task 2: Update Backend Controller

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/LeaveRequestController.php:181-207`

**Context:** Currently expects collection return, needs to handle new array shape with succeeded/failed.

- [ ] **Step 1: Read current implementation**

Read `team-sync-be/app/Http/Controllers/LeaveRequestController.php` lines 181-207.

- [ ] **Step 2: Update bulkAction method**

Replace lines 181-207 with:

```php
public function bulkAction(LeaveRequestBulkActionRequest $request)
{
    $data = $request->validated();

    try {
        $result = $this->leaveRequestRepository->bulkAction($data['ids'], $data['action']);

        $message = count($result['succeeded']) . ' ' . $data['action'] . 'd';
        if (count($result['failed']) > 0) {
            $message .= ', ' . count($result['failed']) . ' failed';
        }

        return ResponseHelper::jsonResponse(
            true,
            $message,
            [
                'succeeded' => LeaveRequestResource::collection($result['succeeded']),
                'failed' => $result['failed'],
            ],
            200
        );
    } catch (AuthorizationException $e) {
        return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
    } catch (\Throwable $e) {
        Log::error('LeaveRequestController@bulkAction Error: '.$e->getMessage());
        return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
    }
}
```

- [ ] **Step 3: Remove unused exception imports if any**

Check if `ModelNotFoundException` import is still needed (now handled in repository). Remove if unused.

- [ ] **Step 4: Run backend tests**

Run: `cd /Users/hyarax/Documents/project/team-sync/team-sync-be && composer test`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add team-sync-be/app/Http/Controllers/LeaveRequestController.php
git commit -m "feat: update controller to handle partial success response shape"
```

---

## Task 3: Update Frontend Store

**Files:**
- Modify: `team-sync-fe/src/stores/leaveRequest.js:161-179`

**Context:** Currently returns `response.data.data` which is a collection. Need to return the new shape `{ succeeded: [], failed: [] }`.

- [ ] **Step 1: Read current implementation**

Read `team-sync-fe/src/stores/leaveRequest.js` lines 161-179.

- [ ] **Step 2: Update bulkAction method**

Replace lines 161-179 with:

```js
async bulkAction(ids, action) {
    this.loading = true;
    this.error = null;

    try {
        const response = await axiosInstance.post("leave-requests/bulk-action", {
            ids,
            action,
        });

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

Note: The store method already returns `response.data.data` which will now be `{ succeeded: [], failed: [] }`. No change needed in store logic, only documentation.

- [ ] **Step 3: Verify store tests**

Check if store tests exist in `team-sync-fe/src/tests/stores/leaveRequest.test.js`. If yes, ensure they pass.

- [ ] **Step 4: Run frontend tests**

Run: `cd /Users/hyarax/Documents/project/team-sync/team-sync-fe && bun run test`
Expected: All tests pass (981+ tests)

- [ ] **Step 5: Commit**

```bash
git add team-sync-fe/src/stores/leaveRequest.js
git commit -m "feat: update store to handle partial success response shape"
```

---

## Task 4: Update Frontend View

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue:181-204`

**Context:** Currently shows success for all or error for all. Need to handle partial success with detailed feedback.

- [ ] **Step 1: Read current implementation**

Read `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue` lines 181-204.

- [ ] **Step 2: Update runBulkAction function**

Replace lines 181-204 with:

```js
const runBulkAction = async (action) => {
    if (!selectedIds.value.length) {
        toast.warning("No Selection", "Please select at least one pending leave request.");
        return;
    }

    processingBulkAction.value = true;

    try {
        const result = await store.bulkAction(selectedIds.value, action);

        if (result.failed && result.failed.length > 0) {
            toast.warning("Partial Success", `${result.succeeded.length} ${action}d, ${result.failed.length} failed`);
        } else {
            toast.success("Success", `${result.succeeded.length} requests ${action}d`);
        }

        selectedIds.value = [];
        await fetchData();
    } catch (axiosError) {
        toast.error("Bulk Action Failed", normalizeErrorMessage(axiosError));
    } finally {
        processingBulkAction.value = false;
    }
};
```

- [ ] **Step 3: Verify toast component supports warning type**

Check if toast.warning() is available in the toast composable. If not, use toast.info() or toast.error() as appropriate.

- [ ] **Step 4: Run frontend tests**

Run: `cd /Users/hyarax/Documents/project/team-sync/team-sync-fe && bun run test`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue
git commit -m "feat: update view to handle partial success with detailed feedback"
```

---

## Task 5: Integration Testing

**Files:**
- None (manual verification)

**Context:** Verify the entire flow works end-to-end.

- [ ] **Step 1: Run backend tests**

Run: `cd /Users/hyarax/Documents/project/team-sync/team-sync-be && composer test`
Expected: All tests pass

- [ ] **Step 2: Run frontend tests**

Run: `cd /Users/hyarax/Documents/project/team-sync/team-sync-fe && bun run test`
Expected: All tests pass

- [ ] **Step 3: Manual verification (if possible)**

Test bulk action with mix of valid and invalid leave requests. Verify partial success feedback appears.

- [ ] **Step 4: Final commit if needed**

If any fixes were made during testing, commit them.

---

## Execution Order

| Order | Task | Priority | Effort | Dependencies |
|-------|------|----------|--------|-------------|
| 1 | Task 1: Modify Backend Repository | Critical | Medium | None |
| 2 | Task 2: Update Backend Controller | Critical | Small | Task 1 |
| 3 | Task 3: Update Frontend Store | High | Small | Task 2 |
| 4 | Task 4: Update Frontend View | High | Small | Task 3 |
| 5 | Task 5: Integration Testing | Medium | Small | All |

---

## Testing Checklist

After all tasks:
- [ ] `cd team-sync-be && composer test` — all 1478+ tests pass
- [ ] `cd team-sync-fe && bun run test` — all 981+ tests pass
- [ ] Manual: bulk action with mixed valid/invalid requests shows partial success
- [ ] Manual: all valid requests show full success
- [ ] Manual: all invalid requests show failure with reasons