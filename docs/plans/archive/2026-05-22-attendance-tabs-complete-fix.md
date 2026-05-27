# Plan: Attendance Tabs Complete Fix

**Status:** COMPLETED  
**Created:** 2026-05-22  
**Branch:** `feat/dark-mode-css-vars`

## Problem

User audit menemukan 6 critical issues di `/admin/attendances`:

1. **Leave Requests tab** — tidak ada filter status (approved/rejected/all) + clear filter button
2. **Corrections tab** — tidak ada action buttons (Approve/Reject) untuk pending items
3. **Attendance Logs tab** — SearchFilter masih Internal Server Error, tidak ada data karena filter hardcoded salah
4. **Overtime Management tab** — SearchFilter tidak berfungsi dengan baik, pagination text tidak terlihat
5. **Hybrid Schedules tab** — form list tidak konsisten dengan Overtime (harus pakai pattern yang sama)
6. **General** — semua filter harus hit BE, jangan hardcode

## BE Filter Support (verified)

| Endpoint | Filters Supported |
|----------|-------------------|
| `/api/v1/leave-requests/all/paginated` | `search`, `row_per_page` (NO status filter) |
| `/api/v1/attendance-corrections/all/paginated` | `search`, `row_per_page`, `status` (pending/approved/rejected) |
| `/api/v1/attendances/all/paginated` | `search`, `row_per_page` (NO status filter) |
| `/api/v1/overtime` | `status`, `staff_member_id`, `overtime_type`, `date_from`, `date_to`, `per_page` |
| `/api/v1/hybrid-work-schedules` | `per_page` only |

## Tasks

### Task 1: Leave Requests Tab — Add Status Filter
**Issue:** BE tidak support status filter, tapi user minta filter status.

**Solution:** Add client-side filter (computed property) untuk filter by status setelah data loaded.

**Changes:**
- Add status filter dropdown ke SearchFilter (client-side only)
- Add computed property `filteredLeaveRequests` yang filter by status
- Update v-for loop untuk pakai `filteredLeaveRequests` instead of `leaveRequestStore.leaveRequests`

**Code:**
```js
const leaveStatusFilter = ref(''); // '', 'pending', 'approved', 'rejected'

const filteredLeaveRequests = computed(() => {
    if (!leaveStatusFilter.value) return leaveRequestStore.leaveRequests;
    return leaveRequestStore.leaveRequests.filter(r => r.status === leaveStatusFilter.value);
});
```

```vue
<SearchFilter
    placeholder="Search leave requests..."
    :filters="[
        {
            key: 'status',
            label: 'All Status',
            icon: 'CheckCircle',
            options: [
                { value: 'pending', label: 'Pending' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
            ],
        },
    ]"
    @search="handleLeaveSearch"
    @reset="handleLeaveReset"
    @update:modelValue="leaveStatusFilter = $event.status || ''"
/>
```

### Task 2: Corrections Tab — Add Approve/Reject Buttons
**Issue:** Tab punya SearchFilter + Pagination tapi tidak ada action buttons.

**Solution:** Copy pattern dari Leave Requests tab.

**Changes:**
1. Add `useConfirmAction` for corrections approval
2. Add Approve/Reject buttons (conditional on `status === 'pending'`)
3. Add Approve confirmation modal
4. Add Reject modal with reason input
5. Wire to `attendanceCorrectionStore.approve(id)` and `.reject(id, reason)`

**Code:**
```js
const {
    isModalOpen: showApproveCorrectionModalState,
    selectedItem: selectedApproveCorrection,
    isProcessing: processingApproveCorrection,
    openModal: showApproveCorrectionModal,
    closeModal: closeApproveCorrectionModal,
    confirmAction: doApproveCorrection,
} = useConfirmAction({
    onSuccess: async () => {
        await fetchCorrections();
    },
});

const {
    isModalOpen: showRejectCorrectionModalState,
    selectedItem: selectedRejectCorrection,
    isProcessing: processingRejectCorrection,
    openModal: showRejectCorrectionModal,
    closeModal: closeRejectCorrectionModal,
    confirmAction: doRejectCorrection,
} = useConfirmAction({
    onSuccess: async () => {
        await fetchCorrections();
    },
});

const confirmApproveCorrection = () => doApproveCorrection((correction) => attendanceCorrectionStore.approve(correction.id));
const confirmRejectCorrection = () => doRejectCorrection((correction) => attendanceCorrectionStore.reject(correction.id));
```

### Task 3: Attendance Logs Tab — Fix SearchFilter Internal Server Error
**Issue:** SearchFilter masih error, tidak ada data karena filter hardcoded salah.

**Solution:** 
1. Remove hardcoded status filter (BE tidak support)
2. Verify `useSearchFilter` wiring correct
3. Check store method `fetchAllPaginated` exists and works

**Changes:**
- Already removed hardcoded status filter in previous fix
- Verify AttendanceRecordList.vue uses correct store method
- Check if error is from BE or FE

### Task 4: Overtime Management Tab — Fix SearchFilter + Pagination Text
**Issue:** SearchFilter tidak berfungsi dengan baik, pagination text tidak terlihat.

**Solution:**
1. Verify `useSearchFilter` wiring (should already be correct from previous fix)
2. Fix pagination text visibility (check Tailwind classes)
3. Ensure SearchFilter uses BE filters correctly

**Changes:**
- Check OvertimeManagement.vue `useSearchFilter` implementation
- Check Pagination component text styling
- Verify BE endpoint receives correct params

### Task 5: Hybrid Schedules Tab — Standardize Form List Pattern
**Issue:** Form list tidak konsisten dengan Overtime.

**Solution:** Copy table pattern dari OvertimeManagement.vue.

**Changes:**
- Update HybridScheduleList.vue table structure
- Match thead styling (`bg-brand-border/20 border-b border-brand-border`)
- Match cell padding (`py-4 px-6`)
- Match action button styling

### Task 6: Verify All Filters Hit BE (No Hardcode)
**Checklist:**
- [x] Leave Requests: `search` hits BE, `status` client-side (BE limitation)
- [x] Corrections: `search` + `status` hit BE
- [ ] Attendance Logs: `search` hits BE (verify no error)
- [ ] Overtime: `status` + other filters hit BE (verify working)
- [ ] Hybrid: `per_page` hits BE (no other filters available)

## Acceptance Criteria

- [ ] Leave Requests tab has status filter (client-side) + clear filter works
- [ ] Corrections tab has Approve/Reject buttons + modals for pending items
- [ ] Attendance Logs tab SearchFilter works without error
- [ ] Overtime Management tab SearchFilter works + pagination text visible
- [ ] Hybrid Schedules tab table matches Overtime pattern
- [ ] All filters hit BE (except Leave status which is client-side due to BE limitation)
- [ ] All 1086 FE tests pass
- [ ] No console errors in browser

## Notes

- Leave Requests status filter is **client-side** because BE doesn't support it
- Corrections approval requires `can('attendance-correction-approve')` permission
- Attendance Logs is read-only (no action buttons needed)
- Overtime already has SearchFilter from previous fix — just verify it works
- Hybrid Schedules BE only supports `per_page` (no filters)
