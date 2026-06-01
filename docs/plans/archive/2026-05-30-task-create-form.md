# Task Create Form

> **Phase:** 3 of 4
> **Status:** Pending
> **Dependencies:** Phase 1 (Backend Permissions), Phase 2 (Frontend Permissions)

---

## Overview

Changes to TaskCreateModal component for auto-status, optional assignee, and empty states.

---

## 1. TaskCreateModal.vue

### File Location
`team-sync-fe/src/components/admin/project/detail/TaskCreateModal.vue`

### Changes

**Remove status dropdown, show read-only text:**

Replace:
```vue
<select v-model="formData.status">
    <option value="todo">To Do</option>
    <option value="in_progress">In Progress</option>
    <option value="review">Review</option>
    <option value="done">Done</option>
</select>
```

With:
```vue
<div class="flex items-center gap-2">
    <span class="text-sm text-gray-500">Status:</span>
    <span class="px-2 py-1 bg-gray-100 rounded text-sm">To Do</span>
</div>
```

**Add optional assignee dropdown:**

```vue
<div>
    <label for="task-assignee" class="block text-sm font-semibold text-gray-700 mb-2">
        Assign To
    </label>
    <select
        id="task-assignee"
        v-model="formData.assignee_id"
        class="w-full px-4 py-3 border border-gray-200 rounded-lg"
    >
        <option value="">Unassigned</option>
        <option
            v-for="member in projectMembers"
            :key="member.id"
            :value="member.id"
        >
            {{ member.name }}
        </option>
    </select>
    
    <!-- Empty state for no members -->
    <p v-if="projectMembers.length === 0" class="text-sm text-gray-500 mt-2">
        No team members available. Add teams to the project first.
    </p>
</div>
```

**Fetch project members on mount:**

```javascript
import { ref, onMounted } from 'vue';
import { useProjectStore } from '@/stores/project';

const projectStore = useProjectStore();
const projectMembers = ref([]);

onMounted(async () => {
    if (props.projectId) {
        const members = await projectStore.fetchProjectMembers(props.projectId);
        projectMembers.value = members || [];
    }
});
```

**Update form data:**

```javascript
const formData = ref({
    name: '',
    description: '',
    priority: 'medium',
    status: 'todo',  // Auto-set, not editable
    assignee_id: '',  // Optional
    project_id: props.projectId,
});
```

**Submit handler:**

```javascript
const handleSubmit = async () => {
    // Remove empty assignee_id before submit
    const payload = { ...formData.value };
    if (!payload.assignee_id) {
        delete payload.assignee_id;
    }
    
    await taskStore.createTask(payload);
    // ... handle success/error
};
```

---

## 2. Project Store Changes

### File Location
`team-sync-fe/src/stores/project.js`

### Add fetchProjectMembers method:

```javascript
async fetchProjectMembers(projectId) {
    this.loadingMembers = true;
    
    try {
        const response = await axiosInstance.get(`projects/${projectId}/members`);
        this.projectMembers = response.data?.data || [];
        return this.projectMembers;
    } catch (error) {
        if (error.response?.status === 403) {
            this.projectMembers = [];
            return [];
        }
        this.error = handleError(error);
        this.projectMembers = [];
        return [];
    } finally {
        this.loadingMembers = false;
    }
},
```

---

## 3. Frontend Tests

### Test Cases

1. **Status field is read-only**
   - Open task create modal
   - Assert status field shows "To Do" as text, not dropdown

2. **Assignee dropdown shows project members**
   - Open task create modal
   - Assert assignee dropdown populated with project members

3. **Assignee is optional**
   - Open task create modal
   - Submit without selecting assignee
   - Assert task created successfully

4. **Empty state for no members**
   - Open task create modal for project with no teams
   - Assert empty state message visible

5. **Task created with correct status**
   - Open task create modal
   - Submit task
   - Assert task created with status "todo"

---

## Completion Checklist

- [ ] Update TaskCreateModal.vue (auto-status, optional assignee, empty states)
- [ ] Update project.js store (add fetchProjectMembers)
- [ ] Run frontend tests
- [ ] All tests pass
