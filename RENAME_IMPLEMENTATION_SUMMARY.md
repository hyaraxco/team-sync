# Role & Permission Rename Implementation Summary

**Date**: April 21, 2026  
**Branch**: `feature/role-permission-rename`  
**Scope**: Rename role `'employee'` → `'staff'` and permissions `'employee-*'` → `'staff-member-*'`

---

## Changes Implemented

### 1. Database Migration
**File**: `team-sync-be/database/migrations/2026_04_21_140240_rename_employee_role_and_permissions.php`

- Atomic transaction-based rename using `DB::transaction()`
- Renames role: `'employee'` → `'staff'`
- Renames all permissions: `'employee-*'` → `'staff-member-*'`
- Automatic cache reset via `PermissionRegistrar::forgetCachedPermissions()`
- Reversible via `down()` method for rollback safety

**Key Features**:
- Foreign key constraints handled automatically (role_has_permissions, model_has_roles)
- Cache invalidation built-in
- Safe rollback support

### 2. Seeders Updated

#### RoleSeeder
- Changed: `'name' => 'employee'` → `'name' => 'staff'`

#### PermissionSeeder
- Changed permission prefix: `'employee'` → `'staff-member'`
- All permissions now use `'staff-member-*'` format

#### RolePermissionSeeder
- Updated role reference: `$employee` → `$staff`
- Updated permission prefix in `permissionsByPrefixes()`: `'employee-'` → `'staff-member-'`
- Updated all permission name references:
  - `'employee-list'` → `'staff-member-list'`
  - `'employee-menu'` → `'staff-member-menu'`
  - etc.

#### MobileDevelopmentDummySeeder
- Updated all role assignments: `'role' => 'employee'` → `'role' => 'staff'`
- Updated role fallback: `?? 'employee'` → `?? 'staff'`

### 3. Repositories Updated

#### ProjectTaskRepository
- Updated all `hasRole('employee')` → `hasRole('staff')`
- Affected methods: Multiple role checks throughout the file

#### AttendanceCorrectionRepository
- Updated: `hasRole('employee')` → `hasRole('staff')`

#### AttendanceRepository
- Updated: `hasRole('employee')` → `hasRole('staff')`

### 4. Factories Updated

#### EmployeeProfileFactory
- Updated all role assignments: `assignRole('employee')` → `assignRole('staff')`
- 3 locations updated

### 5. Tests Updated

**All test files** in `tests/` directory:
- Updated all role references: `'employee'` → `'staff'`
- Affected test files: 20+ test classes

---

## Deployment Checklist

### Pre-Deployment
- [x] All code changes committed
- [x] Migration created with cache reset
- [x] Seeders updated
- [x] Repositories updated
- [x] Factories updated
- [x] Tests updated

### Deployment Steps

1. **Pull latest code**
   ```bash
   git pull origin feature/role-permission-rename
   ```

2. **Run migration**
   ```bash
   php artisan migrate
   ```

3. **Verify cache reset** (automatic in migration, but can be manual)
   ```bash
   php artisan permission:cache-reset
   ```

4. **Verify database consistency**
   ```bash
   php artisan tinker
   >>> \Spatie\Permission\Models\Role::where('name', 'staff')->first()
   >>> \Spatie\Permission\Models\Permission::where('name', 'like', 'staff-member-%')->count()
   ```

5. **Run tests** (optional, for verification)
   ```bash
   php artisan test
   ```

6. **Restart queue workers** (if using Horizon)
   ```bash
   php artisan horizon:terminate
   ```

---

## Files Modified

### Backend (team-sync-be)

**Migrations**:
- `database/migrations/2026_04_21_140240_rename_employee_role_and_permissions.php` (NEW)

**Seeders**:
- `database/seeders/RoleSeeder.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RolePermissionSeeder.php`
- `database/seeders/MobileDevelopmentDummySeeder.php`

**Factories**:
- `database/factories/EmployeeProfileFactory.php`

**Repositories**:
- `app/Repositories/ProjectTaskRepository.php`
- `app/Repositories/AttendanceCorrectionRepository.php`
- `app/Repositories/AttendanceRepository.php`

**Tests**:
- All files in `tests/` directory (20+ files)

---

## Cache Invalidation Strategy

**Automatic**: Migration includes `app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()`

**Manual** (if needed):
```bash
php artisan permission:cache-reset
```

**Configuration** (from `config/permission.php`):
- Cache store: `'default'`
- Expiration: `24 hours`
- Key: `'spatie.permission.cache'`

---

## Verification Commands

### Check role exists
```bash
php artisan tinker
>>> \Spatie\Permission\Models\Role::where('name', 'staff')->first()
```

### Count new permissions
```bash
>>> \Spatie\Permission\Models\Permission::where('name', 'like', 'staff-member-%')->count()
```

### Verify junction tables
```bash
>>> DB::table('role_has_permissions')->where('role_id', $staffRole->id)->count()
>>> DB::table('model_has_roles')->where('role_id', $staffRole->id)->count()
```

### Check old role is gone
```bash
>>> \Spatie\Permission\Models\Role::where('name', 'employee')->first()  // Should be null
```

---

## Rollback Procedure

If rollback is needed:

```bash
php artisan migrate:rollback
php artisan permission:cache-reset
```

This will:
1. Revert role: `'staff'` → `'employee'`
2. Revert permissions: `'staff-member-*'` → `'employee-*'`
3. Clear cache

---

## Notes

- **No breaking changes**: All functionality remains the same, only names changed
- **Atomic operation**: Migration uses transactions to ensure consistency
- **Cache-aware**: Automatic cache reset prevents stale permission data
- **Reversible**: Full rollback support via migration down()
- **Test coverage**: All tests updated to use new role/permission names

---

## Related Documentation

- [Spatie Laravel-Permission Cache Docs](https://spatie.be/docs/laravel-permission/v7/advanced-usage/cache)
- [Spatie Laravel-Permission Events Docs](https://spatie.be/docs/laravel-permission/v7/advanced-usage/events)
- Project config: `config/permission.php`
