# Backend Permissions

> **Phase:** 1 of 4
> **Status:** COMPLETED — shipped via PR [#62](https://github.com/hyaraxco/team-sync/pull/62) and prerequisite fix [#63](https://github.com/hyaraxco/team-sync/pull/63)
> **Dependencies:** None
> **Completed:** 2026-05-30

---

## Overview

Backend changes for the project/task permission overhaul. Includes permission seeder updates, resource changes, new endpoints, and policy updates.

---

## 1. RolePermissionSeeder.php

### File Location
`team-sync-be/database/seeders/RolePermissionSeeder.php`

### Changes

**Staff Role:**
```php
$staff->syncPermissions(
    Permission::whereIn('name', array_merge($selfServiceBaseline, [
        'dashboard-menu',
        'dashboard-view',
        'dashboard-self-view',
        'team-view',
        'project-menu',
        'project-list',
        'task-menu',
        'task-list',
        // 'task-create',  // REMOVED — manager assigns tasks
        'task-edit',       // Keep — staff can edit own tasks
        'overtime-create',
        'meeting-list',
    ]))->get()
);
```

**Manager Role:**
```php
$manager->syncPermissions(
    Permission::whereIn('name', array_merge($selfServiceBaseline, [
        // Dashboard
        'dashboard-menu',
        'dashboard-view',
        'dashboard-team-view',
        // Team management
        'team-menu',
        'team-statistic',
        'team-list',
        'team-create',
        'team-edit',
        'team-delete',
        'team-view',
        // Project management (full CRUD)
        'project-menu',
        'project-statistic',
        'project-list',
        'project-create',
        'project-edit',
        'project-delete',
        // Task view only (delegates task management to project leader)
        'task-menu',
        'task-list',
        // 'task-create',  // REMOVED — project leader creates tasks
        // 'task-edit',    // REMOVED — project leader edits tasks
        // 'task-delete',  // REMOVED — project leader deletes tasks
        // Attendance: team approval context
        'attendance-menu',
        'attendance-list',
        'attendance-correction-list',
        'attendance-correction-approve',
        // Leave: team approval
        'leave-request-list',
        'leave-request-approve',
        // Overtime: team approval
        'overtime-list',
        'overtime-create',
        'overtime-approve',
        // Performance: team reviews & goals
        'review-manager-submit',
        'goal-assign-team',
        'feedback-give',
        'performance-analytics-view',
        // Analytics: team-scoped performance & project only
        'analytics-menu',
        'analytics-team-view',
        'analytics-performance-view',
        'analytics-project-view',
        // Meetings: view list (team meeting context)
        'meeting-list',
    ]))->get()
);
```

**HR Role:**
```php
$hr->syncPermissions($this->permissionsByPrefixes([
    'dashboard-',
    'team-',
    'staff-member-',
    'project-',    // Keep — HR can view projects
    'task-',       // Keep — HR can view tasks
    'attendance-',
    'leave-request-',
    'analytics-',
    'performance-',
    'review-',
    'goal-',
    'feedback-',
    'meeting-',
    'overtime-',
    'settings-',
], [
    // Exclude: project CRUD (HR only views)
    'project-create',
    'project-edit',
    'project-delete',
    // Exclude: task CRUD (HR only views)
    'task-create',
    'task-edit',
    'task-delete',
    // Exclude: task-delete (admin-only destructive)
    'task-delete',
    // Exclude: Manager-only team review submission
    'review-manager-submit',
    // Exclude: Finance-only dashboard/analytics/settings
    'dashboard-finance-view',
    'dashboard-system-view',
    'dashboard-team-view',
    'analytics-finance-view',
    'analytics-team-view',
    'settings-finance-manage',
    'settings-system-manage',
])->merge(
    Permission::whereIn('name', [
        // Payroll: read-only readiness context only
        'payroll-readiness-view',
        'thr-list',
        ...$selfServiceBaseline,
    ])->get()
)->unique('id')->values());
```

**Finance Role:**
```php
$finance->syncPermissions(
    Permission::whereIn('name', array_merge($selfServiceBaseline, [
        // Dashboard
        'dashboard-menu',
        'dashboard-view',
        'dashboard-finance-view',
        // Project: view only (same as staff)
        'project-menu',
        'project-list',
        // Task: view/edit own (same as staff)
        'task-menu',
        'task-list',
        'task-edit',
        // Payroll operations (Finance owns all)
        'payroll-menu',
        'payroll-list',
        'payroll-create',
        'payroll-edit',
        'payroll-delete',
        'payroll-process',
        'payroll-statistics',
        'payroll-readiness-view',
        // THR operations (Finance owns generate/approve/process)
        'thr-list',
        'thr-generate',
        'thr-approve',
        'thr-process',
        // Analytics: payroll/finance scoped
        'analytics-menu',
        'analytics-view',
        'analytics-export',
        'analytics-finance-view',
        // Overtime: payroll context (list only, no approval)
        'overtime-list',
        // Meetings: view list (receive/join)
        'meeting-list',
        // Settings: payroll/finance domain
        'settings-finance-manage',
    ]))->get()
);
```

---

## 2. ProjectResource.php

### File Location
`team-sync-be/app/Http/Resources/ProjectResource.php`

### Changes

Add new fields to the resource:

```php
public function toArray($request)
{
    return [
        // ... existing fields ...
        'is_project_leader' => $this->isProjectLeader($request->user()),
        'can_create_task' => $this->canCreateTask($request->user()),
    ];
}

private function isProjectLeader(User $user): bool
{
    $profile = $user->staffMemberProfile;
    return $profile && $this->project_leader_id === $profile->id;
}

private function canCreateTask(User $user): bool
{
    // Manager can always create tasks (though they delegate to project leader)
    if ($user->hasRole('manager')) {
        return true;
    }
    
    // Project leader can create tasks in their project
    return $this->isProjectLeader($user);
}
```

---

## 3. New Endpoints

### 3.1 Get Project Members

**Endpoint:** `GET /api/v1/projects/{id}/members`

**Controller:** `ProjectController.php`

**Method:**
```php
public function getMembers(Project $project)
{
    $members = StaffMemberProfile::whereHas('teams', function ($query) use ($project) {
        $query->whereIn('team_id', $project->teams->pluck('id'));
    })
    ->whereHas('jobInformation', function ($query) {
        $query->where('job_status', 'active');
    })
    ->with(['jobInformation', 'teams'])
    ->get();
    
    return response()->json(['data' => $members]);
}
```

**Route:** `routes/api.php`
```php
Route::get('projects/{id}/members', [ProjectController::class, 'getMembers']);
```

### 3.2 Get Eligible Leaders

**Endpoint:** `GET /api/v1/projects/{id}/eligible-leaders`

**Controller:** `ProjectController.php`

**Method:**
```php
public function getEligibleLeaders(Request $request, Project $project)
{
    // Only manager can view eligible leaders
    if (!$request->user()->hasRole('manager')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    $minExperience = $request->input('min_experience', 1);
    $skill = $request->input('skill');
    
    $leaders = StaffMemberProfile::whereHas('teams', function ($query) use ($project) {
        $query->whereIn('team_id', $project->teams->pluck('id'));
    })
    ->whereHas('jobInformation', function ($query) {
        $query->where('job_status', 'active');
    })
    ->when($minExperience, function ($query) use ($minExperience) {
        $query->whereHas('jobInformation', function ($q) use ($minExperience) {
            $q->where('employment_level', '>=', $minExperience);
        });
    })
    ->when($skill, function ($query) use ($skill) {
        $query->whereHas('skills', function ($q) use ($skill) {
            $q->where('skill', $skill);
        });
    })
    ->with(['jobInformation', 'teams'])
    ->get();
    
    $warning = null;
    
    // Fallback: if no match, get all active staff in project
    if ($leaders->isEmpty()) {
        $leaders = StaffMemberProfile::whereHas('teams', function ($query) use ($project) {
            $query->whereIn('team_id', $project->teams->pluck('id'));
        })
        ->whereHas('jobInformation', function ($query) {
            $query->where('job_status', 'active');
        })
        ->with(['jobInformation', 'teams'])
        ->get();
        
        $warning = 'No staff with matching skills found. Showing all available staff.';
    }
    
    return response()->json([
        'data' => $leaders,
        'warning' => $warning,
    ]);
}
```

**Route:** `routes/api.php`
```php
Route::get('projects/{id}/eligible-leaders', [ProjectController::class, 'getEligibleLeaders']);
```

### 3.3 Update Project Leader

**Endpoint:** `PUT /api/v1/projects/{id}/leader`

**Controller:** `ProjectController.php`

**Method:**
```php
public function updateLeader(Request $request, Project $project)
{
    // Only manager can change project leader
    if (!$request->user()->hasRole('manager')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    $request->validate([
        'project_leader_id' => 'required|exists:staff_member_profiles,id',
    ]);
    
    // Verify staff member is in a project team
    $isMember = StaffMemberProfile::where('id', $request->project_leader_id)
        ->whereHas('teams', function ($query) use ($project) {
            $query->whereIn('team_id', $project->teams->pluck('id'));
        })
        ->exists();
    
    if (!$isMember) {
        return response()->json(['message' => 'Staff member must be in a project team'], 422);
    }
    
    $project->update(['project_leader_id' => $request->project_leader_id]);
    
    return new ProjectResource($project->refresh());
}
```

**Route:** `routes/api.php`
```php
Route::put('projects/{id}/leader', [ProjectController::class, 'updateLeader']);
```

---

## 4. ProjectTaskPolicy.php

### File Location
`team-sync-be/app/Policies/ProjectTaskPolicy.php`

### Changes

Update `create()` method to allow project leader:

```php
public function create(User $user, array $data = []): Response
{
    $profile = $user->staffMemberProfile;
    if (!$profile) {
        return Response::deny('Your account is not linked to an employee profile.');
    }

    $projectId = $data['project_id'] ?? null;
    if (!$projectId) {
        return Response::deny('Project ID is required.');
    }

    $project = Project::with('teams')->find($projectId);
    if (!$project) {
        return Response::deny('Project not found.');
    }

    // Assignee must be a project member (applies to ALL roles)
    $assigneeId = $data['assignee_id'] ?? null;
    if ($assigneeId !== null) {
        if (!$this->membershipService->isMemberById((int) $assigneeId, $project)) {
            return Response::deny('Assignee must be a member of the project.');
        }
    }

    // Manager can create tasks (though they delegate to project leader)
    if ($user->hasRole('manager')) {
        return Response::allow();
    }

    // Project leader can create tasks in their project
    if ($project->project_leader_id === $profile->id) {
        return Response::allow();
    }

    // HR can view but not create tasks
    if ($user->hasRole('hr')) {
        return Response::deny('HR cannot create tasks.');
    }

    // Staff cannot create tasks
    return Response::deny('Only project leader or manager can create tasks.');
}
```

---

## 5. Backend Tests

### Test Cases

1. **Staff cannot create tasks**
   - Login as staff
   - POST /api/v1/project-tasks
   - Assert 403

2. **Project leader can create tasks in their project**
   - Login as project leader
   - POST /api/v1/project-tasks (with project_id)
   - Assert 201

3. **Project leader cannot create tasks in other projects**
   - Login as project leader
   - POST /api/v1/project-tasks (with different project_id)
   - Assert 403

4. **Manager can view all tasks**
   - Login as manager
   - GET /api/v1/project-tasks
   - Assert 200

5. **HR can view but not create tasks**
   - Login as HR
   - GET /api/v1/project-tasks (assert 200)
   - POST /api/v1/project-tasks (assert 403)

6. **Finance can view/edit own tasks**
   - Login as finance
   - GET /api/v1/project-tasks (assert 200)
   - PUT /api/v1/project-tasks/{own} (assert 200)
   - PUT /api/v1/project-tasks/{other} (assert 403)

7. **Get eligible leaders**
   - Login as manager
   - GET /api/v1/projects/{id}/eligible-leaders
   - Assert 200 with filtered list

8. **Update project leader**
   - Login as manager
   - PUT /api/v1/projects/{id}/leader
   - Assert 200

---

## Completion Checklist

- [ ] Update RolePermissionSeeder.php
- [ ] Update ProjectResource.php
- [ ] Create getMembers endpoint
- [ ] Create getEligibleLeaders endpoint
- [ ] Create updateLeader endpoint
- [ ] Update ProjectTaskPolicy.php
- [ ] Run backend tests
- [ ] All tests pass
