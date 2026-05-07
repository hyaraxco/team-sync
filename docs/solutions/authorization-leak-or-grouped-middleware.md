# Authorization Leak Pattern: OR-Grouped Middleware + Missing Create Auth

## Problem Pattern

Two related anti-patterns that caused authorization leaks in the Task & Project modules:

### 1. OR-Grouped Permission Middleware

**What happened:** Multiple controller methods with different permission requirements were grouped under a single OR-based middleware:

```php
// BAD: getStatistics needs project-statistic, but gets project-list access
new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']),
    only: ['index', 'getAllPaginated', 'show', 'getStatistics', 'getSquadSummary']),
```

The `|` operator means ANY permission suffices. Staff has `project-list`, so they could access `getStatistics` (intended for Manager+).

**Fix:** Separate methods that need different permissions into their own middleware lines:

```php
// GOOD: Each permission level gets its own line
new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']),
    only: ['index', 'getAllPaginated', 'show']),
new Middleware(PermissionMiddleware::using(['project-statistic']),
    only: ['getStatistics', 'getSquadSummary']),
```

**Where else this exists:** `StaffMemberProfileController`, `TeamController`, `ProjectTaskController` — all have `getStatistics` grouped with `index`.

### 2. Missing Authorization on Repository Create Methods

**What happened:** `ProjectTaskRepository::create()` had ZERO authorization checks, while `update()` had `authorizeTaskUpdate()` and `delete()` had `authorizeTaskDeletion()`. This allowed staff to:
- Create tasks on projects they're not a member of
- Assign tasks to any employee
- Set initial status to `done` (bypassing workflow)

**Root cause:** The developer added auth to update/delete but forgot create — likely because create was written first when the auth pattern hadn't been established yet.

**Fix:** Added `authorizeTaskCreation()` that enforces:
- Project membership check (same logic as `applyCurrentUserReadScope`)
- Staff can only assign to self
- Staff can only create with status `todo`

## Convention (Opsi B)

Going forward, enforce these rules:

1. **1 permission level = 1 middleware line.** Never group methods with different permission requirements in the same `only:` array.
2. **Every Repository write method (create/update/delete) MUST have an authorization check.** If `update()` has `authorizeX()`, `create()` must too.
3. **Every FormRequest SHOULD have `authorize()`.** Even if it just returns `true` — it documents the decision and provides defense-in-depth.
4. **Controller `store()` methods MUST catch `AuthorizationException`.** The existing pattern catches it in `show()`/`update()`/`destroy()` but was missing in `store()`.

## Detection

Grep for these patterns to find similar issues:

```bash
# Find OR-grouped middleware with getStatistics
grep -rn "PermissionMiddleware::using" app/Http/Controllers/ | grep "|"

# Find repositories with update auth but no create auth
grep -rn "authorizeTask\|authorizeProject\|authorize" app/Repositories/ | grep -v "Interface"

# Find store methods without AuthorizationException catch
grep -A 10 "function store" app/Http/Controllers/ | grep -B 5 "Throwable" | grep -v "AuthorizationException"
```

## Related Files

- `app/Http/Controllers/ProjectController.php` — fixed
- `app/Http/Controllers/ProjectTaskController.php` — fixed
- `app/Repositories/ProjectTaskRepository.php` — fixed
- `app/Repositories/ProjectRepository.php` — fixed
- `tests/Feature/Project/StaffProjectTaskAuthorizationTest.php` — new test coverage

## Future: Migrate to Laravel Policy (Opsi C)

The long-term plan is to migrate authorization to Laravel Policies (`ProjectPolicy`, `ProjectTaskPolicy`). This centralizes all auth logic per model and removes it from repositories. Start with Project/Task module as pilot, then expand to other modules.
