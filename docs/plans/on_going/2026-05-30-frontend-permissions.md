# Frontend Permissions

> **Phase:** 2 of 4
> **Status:** Implementation Complete (pending PR review)
> **Dependencies:** Phase 1 (Backend Permissions)

---

## Overview

Frontend permission gate changes for ProjectDetail, TaskBoard, and TaskDetailModal components.

---

## 1. ProjectDetail.vue

### File Location
`team-sync-fe/src/views/admin/project/ProjectDetail.vue`

### Changes

**Import permission helper:**
```javascript
import { can } from "@/helpers/permissionHelper";
```

**Hide sections for staff:**

```vue
<!-- Squad Snapshot — hide for staff -->
<div v-if="can('project-statistic')" class="...">
    <!-- Squad Snapshot content -->
</div>

<!-- Budget — hide for staff -->
<div v-if="can('project-statistic')" class="...">
    <!-- Budget content -->
</div>

<!-- Danger Zone — hide for staff -->
<div v-if="can('project-delete')" class="...">
    <!-- Danger Zone content -->
</div>

<!-- Project Leader Profile Link — hide for staff -->
<router-link v-if="can('staff-member-detail')" :to="...">
    <!-- Profile link -->
</router-link>
```

**Add "Change Project Leader" button for manager:**
```vue
<div v-if="can('project-edit')" class="...">
    <button @click="openLeaderEditModal">
        Change Project Leader
    </button>
</div>
```

**Guard API calls:**
```javascript
onMounted(async () => {
    await handleFetchProject();
    
    // Only fetch squad summary if user has permission
    if (can('project-statistic')) {
        await handleFetchSquadSummary();
    }
});
```

---

## 2. TaskBoard.vue

### File Location
`team-sync-fe/src/components/admin/project/detail/TaskBoard.vue`

### Changes

**Show create button for project leader only:**
```vue
<!-- Create Task button — show only if user can create tasks in this project -->
<button v-if="project.can_create_task" @click="openCreateModal">
    Create New Task
</button>
```

**Note:** `project.can_create_task` is a backend-driven flag from ProjectResource.

---

## 3. TaskDetailModal.vue

### File Location
`team-sync-fe/src/components/admin/project/detail/TaskDetailModal.vue`

### Changes

**Import permission helper:**
```javascript
import { can } from "@/helpers/permissionHelper";
```

**Guard fetchStaffMembers call:**
```javascript
onMounted(async () => {
    // Only fetch staff members if user has permission (for assignee dropdown)
    if (can('staff-member-list')) {
        await fetchStaffMembers({
            limit: 6,
            project_id: props.projectId,
        });
    }
    
    await loadTaskCollaborationData();
    // ... rest of mount logic
});
```

**Guard search watch:**
```javascript
watch(searchAssignee, async (newValue) => {
    // Only search staff members if user has permission
    if (!can('staff-member-list')) {
        return;
    }
    
    // ... existing search logic
});
```

**Migrate hasRole() to can() for UI gating:**

Replace:
```javascript
const canManageAssignee = computed(() => hasRole("manager") || hasRole("hr") || isProjectLeader.value);
const canEditDueDate = computed(() => hasRole("manager") || hasRole("hr"));
const canDeleteTask = computed(() => hasRole("manager") || hasRole("hr"));
```

With:
```javascript
const canManageAssignee = computed(() => {
    if (isReviewPhaseLocked.value) return false;
    return can("task-edit") && (can("project-edit") || isProjectLeader.value);
});
const canEditDueDate = computed(() => can("task-edit") && can("project-edit") && !isReviewPhaseLocked.value);
const canDeleteTask = computed(() => can("task-delete"));
```

**Note:** `isProjectLeader` should come from the project data (backend-driven flag).

---

## 4. Frontend Tests

### Test Cases

1. **Staff cannot see Squad Snapshot**
   - Login as staff
   - Navigate to project detail
   - Assert Squad Snapshot section not visible

2. **Staff cannot see Budget**
   - Login as staff
   - Navigate to project detail
   - Assert Budget section not visible

3. **Staff cannot see Danger Zone**
   - Login as staff
   - Navigate to project detail
   - Assert Danger Zone section not visible

4. **Staff cannot see Create Task button**
   - Login as staff
   - Navigate to task board
   - Assert Create Task button not visible

5. **Project leader can see Create Task button**
   - Login as project leader
   - Navigate to task board
   - Assert Create Task button visible

6. **Manager can see Change Project Leader button**
   - Login as manager
   - Navigate to project detail
   - Assert Change Project Leader button visible

7. **fetchStaffMembers not called for staff**
   - Login as staff
   - Open task detail modal
   - Assert fetchStaffMembers not called

8. **fetchStaffMembers called for HR**
   - Login as HR
   - Open task detail modal
   - Assert fetchStaffMembers called

---

## Completion Checklist

- [x] Update ProjectDetail.vue (hide sections, add "Change Project Leader")
- [x] Update TaskBoard.vue (show create button via `can_create_task` prop from ProjectDetail)
- [x] Update TaskDetailModal.vue (guard API calls, migrate hasRole to can)
- [x] Add `updateProjectLeader` and `fetchEligibleLeaders` actions to project store
- [x] Run frontend tests (1099 passed)
- [x] All tests pass
