# Project & Task Permission Overhaul — MASTER PLAN

> **Date:** 2026-05-30
> **Status:** Phases 1-3 COMPLETED, Phase 4 PENDING
> **Confidence:** 99%
>
> ## Phase Status
> - **Phase 1 — Backend Permissions:** ✅ COMPLETED — shipped via PR [#62](https://github.com/hyaraxco/team-sync/pull/62) (+ prerequisite [#63](https://github.com/hyaraxco/team-sync/pull/63)). Plan archived at `docs/plans/archive/2026-05-30-backend-permissions.md`.
> - **Phase 2 — Frontend Permissions:** ✅ COMPLETED — shipped via PR [#65](https://github.com/hyaraxco/team-sync/pull/65) (ProjectDetail, TaskBoard, TaskDetailModal Phase 2 gates) + PR [#67](https://github.com/hyaraxco/team-sync/pull/67) (canReviewTask/canCollaborateTask migration + 29 test cases). Plans archived at `docs/plans/archive/2026-05-30-frontend-permissions.md` and `docs/plans/archive/2026-05-31-task-detail-modal-test-coverage.md`.
> - **Phase 3 — Task Create Form:** ✅ COMPLETED — shipped via PR [#70](https://github.com/hyaraxco/team-sync/pull/70) (auto-status, assignee dropdown, race-condition fixes, loading/error tests). Plan archived at `docs/plans/archive/2026-05-30-task-create-form.md`.
> - **Phase 4 — UI Fixes:** ⏳ Pending

---

## Overview

Complete overhaul of the project/task permission system across all roles (Staff, Manager, HR, Finance, Project Leader). Fixes 16 bugs and implements proper role-based access control.

---

## Permission Model (Final)

### Role Permissions Matrix

| Permission | Staff | Manager | HR | Finance | Project Leader |
|------------|:-----:|:-------:|:--:|:-------:|:--------------:|
| **project-menu** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **project-list** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **project-statistic** | ❌ | ✅ | ❌ | ❌ | ✅ |
| **project-create** | ❌ | ✅ | ❌ | ❌ | ❌ |
| **project-edit** | ❌ | ✅ | ❌ | ❌ | ❌ |
| **project-delete** | ❌ | ✅ | ❌ | ❌ | ❌ |
| **task-menu** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **task-list** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **task-create** | ❌ | ❌ | ❌ | ❌ | ✅ |
| **task-edit** | ✅ (own) | ❌ | ❌ | ✅ (own) | ✅ |
| **task-delete** | ❌ | ❌ | ❌ | ❌ | ✅ |
| **staff-member-list** | ❌ | ❌ | ✅ | ❌ | ❌ |

### Role Definitions

| Role | Project Access | Task Access | Notes |
|------|---------------|-------------|-------|
| **Staff** | View (as member) | View/Edit own | Executes assigned tasks |
| **Manager** | Create + View + Edit + Delete | View all tasks | Creates projects, selects project leaders |
| **HR** | View only | View only | Oversight for performance/workforce |
| **Finance** | View (as member) | View/Edit own | Same as staff (financial specialization) |
| **Project Leader** | View only | Full CRUD (own project) | Staff member assigned to lead project |

### Key Rules
- **Staff:** View projects, view/edit own tasks only. Cannot create tasks.
- **Manager:** Full project CRUD, view all tasks. Delegates task management to project leader.
- **HR:** View-only access to projects and tasks. No create/edit/delete.
- **Finance:** Same as staff — view projects, view/edit own tasks.
- **Project Leader:** Staff member assigned to lead a project. Full task CRUD within that project only.

---

## Project Leader System

### Project Leader is NOT a Separate Role
- Project leader is a staff member assigned to lead a specific project
- A staff member can be project leader for multiple projects
- A staff member can be project leader for one project and regular staff for another
- Project leader gets elevated permissions WITHIN THAT PROJECT ONLY

### Project Leader Eligibility
When manager selects a project leader, the system filters eligible staff:

**Criteria:**
1. **Must be project member** — Part of a team in the project
2. **Must be active** — Not resigned or on leave
3. **Seniority filter (optional)** — Default: 1+ year experience
4. **Skill matching (optional)** — Match staff skills to project type

**Fallback:** If no one matches criteria, show all active staff in project with warning message.

### Project Leader Management
- Manager can change project leader after project creation
- "Change Project Leader" button on project leader info card
- Manager can assign any eligible staff as project leader

---

## Sub-Documents

| Document | Contents | Phase |
|----------|----------|-------|
| [Backend Permissions](../archive/2026-05-30-backend-permissions.md) | RolePermissionSeeder, ProjectResource, new endpoints, ProjectTaskPolicy | Phase 1 ✅ |
| [Frontend Permissions](../archive/2026-05-30-frontend-permissions.md) | ProjectDetail, TaskBoard, TaskDetailModal permission gates | Phase 2 ✅ |
| [Task Detail Modal Tests](../archive/2026-05-31-task-detail-modal-test-coverage.md) | canReviewTask/canCollaborateTask migration + test coverage | Phase 2 ✅ |
| [Task Create Form](./2026-05-30-task-create-form.md) | TaskCreateModal changes (auto-status, optional assignee, empty states) | Phase 3 |
| [UI Fixes](./2026-05-30-ui-fixes.md) | Notification badge, ApexCharts, empty states, loading states | Phase 4 |

---

## Execution Order

```
Phase 1: Backend Permissions (RolePermissionSeeder, ProjectResource, endpoints)
    ↓
Phase 2: Frontend Permissions (ProjectDetail, TaskBoard, TaskDetailModal)
    ↓
Phase 3: Task Create Form (TaskCreateModal)
    ↓
Phase 4: UI Fixes (Notification badge, ApexCharts, empty states)
```

---

## Edge Cases Handled

| Edge Case | Resolution |
|-----------|------------|
| Project leader project-scoped | Backend returns `can_create_task` flag per project |
| HR task access | Keep `task-list` (view only), remove CUD |
| Finance role | Gets staff-level permissions explicitly |
| Task assignee optional | Yes, manager can create task then assign later |
| Project members empty | Show empty state with message |
| Project leader resignation | Manager can edit project leader |
| Project leader promotion | No special handling — both roles coexist |
| Multiple project leader | Allowed, no issues expected |
| Loading states | Standardize across app |
| No eligible leaders | Fallback to all active staff with warning |

---

## Confidence: 99%

All edge cases resolved. All questions answered. Ready for execution.
