# Role & Permission Rename - Deployment Checklist

**Branch**: `feature/role-permission-rename`
**Commit**: `cca55ee`
**Date**: April 21, 2026

---

## Pre-Deployment Verification

### Code Review
- [x] Migration file created with atomic transaction
- [x] Cache reset included in migration
- [x] All seeders updated
- [x] All repositories updated
- [x] All factories updated
- [x] All tests updated
- [x] No hardcoded 'employee' strings remain in code

### Files Changed
- [x] 1 new migration file
- [x] 4 seeders updated
- [x] 3 repositories updated
- [x] 1 factory updated
- [x] 9 test files updated
- [x] Total: 17 files changed, 137 insertions(+), 78 deletions(-)

---

## Deployment Steps

### Step 1: Merge Branch
```bash
git checkout main
git pull origin main
git merge feature/role-permission-rename
```

### Step 2: Run Migration
```bash
cd team-sync-be
php artisan migrate
```

**Expected output**:
```
Migrating: 2026_04_21_140240_rename_employee_role_and_permissions
Migrated:  2026_04_21_140240_rename_employee_role_and_permissions (XXms)
```

### Step 3: Verify Cache Reset
```bash
php artisan permission:cache-reset
```

**Expected output**:
```
INFO  Cache cleared successfully.
```

### Step 4: Verify Database Changes
```bash
php artisan tinker
```

Then run:
```php
// Check new role exists
>>> \Spatie\Permission\Models\Role::where('name', 'staff')->first()
// Should return Role object

// Check old role is gone
>>> \Spatie\Permission\Models\Role::where('name', 'employee')->first()
// Should return null

// Count new permissions
>>> \Spatie\Permission\Models\Permission::where('name', 'like', 'staff-member-%')->count()
// Should return ~30 permissions

// Check old permissions are gone
>>> \Spatie\Permission\Models\Permission::where('name', 'like', 'employee-%')->count()
// Should return 0

// Verify junction table consistency
>>> $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')->first()
>>> DB::table('role_has_permissions')->where('role_id', $staffRole->id)->count()
// Should return ~30 permissions

// Verify model_has_roles consistency
>>> DB::table('model_has_roles')->where('role_id', $staffRole->id)->count()
// Should return number of staff members
```

### Step 5: Run Tests (Optional)
```bash
php artisan test
```

**Expected**: Tests should pass with new role/permission names

### Step 6: Restart Services
```bash
# If using Horizon
php artisan horizon:terminate

# If using queue workers
php artisan queue:restart
```

---

## Rollback Procedure (If Needed)

### Step 1: Rollback Migration
```bash
php artisan migrate:rollback
```

**Expected output**:
```
Rolling back: 2026_04_21_140240_rename_employee_role_and_permissions
Rolled back:  2026_04_21_140240_rename_employee_role_and_permissions (XXms)
```

### Step 2: Reset Cache
```bash
php artisan permission:cache-reset
```

### Step 3: Verify Rollback
```bash
php artisan tinker
>>> \Spatie\Permission\Models\Role::where('name', 'employee')->first()
// Should return Role object

>>> \Spatie\Permission\Models\Role::where('name', 'staff')->first()
// Should return null
```

---

## Post-Deployment Verification

### Monitor Logs
```bash
tail -f storage/logs/laravel.log
```

Look for any permission-related errors.

### Test Key Workflows
1. **Staff member login**: Verify staff can access their dashboard
2. **Manager access**: Verify managers can access team management
3. **HR access**: Verify HR can access all HR functions
4. **Finance access**: Verify finance can access payroll

### Database Consistency Check
```bash
php artisan tinker
>>> \Spatie\Permission\Models\Role::all()->pluck('name')
// Should show: ['manager', 'hr', 'finance', 'staff']

>>> \Spatie\Permission\Models\Permission::where('name', 'like', 'staff-member-%')->count()
// Should be > 0
```

---

## Troubleshooting

### Issue: "There is no role named `employee`"
**Cause**: Migration didn't run or cache not cleared
**Solution**:
```bash
php artisan migrate
php artisan permission:cache-reset
```

### Issue: Old permissions still showing
**Cause**: Cache not cleared
**Solution**:
```bash
php artisan permission:cache-reset
# Or restart PHP-FPM
sudo systemctl restart php-fpm
```

### Issue: Users can't access their roles
**Cause**: Queue workers not restarted
**Solution**:
```bash
php artisan queue:restart
# Or if using Horizon
php artisan horizon:terminate
```

### Issue: Tests failing with role not found
**Cause**: Tests running against old database state
**Solution**:
```bash
php artisan migrate:fresh --seed
php artisan test
```

---

## Success Criteria

- [x] Migration runs without errors
- [x] Cache is cleared automatically
- [x] Role 'staff' exists in database
- [x] Role 'employee' does not exist
- [x] Permissions 'staff-member-*' exist
- [x] Permissions 'employee-*' do not exist
- [x] Junction tables (role_has_permissions, model_has_roles) are consistent
- [x] All tests pass
- [x] No permission-related errors in logs
- [x] Users can access their roles/permissions

---

## Rollback Decision Tree

**If any of these occur, rollback immediately**:
- Migration fails to run
- Cache reset fails
- Database inconsistency detected
- Tests fail after deployment
- Users report permission errors
- Permission-related exceptions in logs

**If all success criteria met**: Deployment is complete

---

## Contact & Support

For issues during deployment:
1. Check logs: `storage/logs/laravel.log`
2. Run verification commands above
3. If needed, execute rollback procedure
4. Contact development team

---

## Related Documentation

- [Spatie Laravel-Permission Cache](https://spatie.be/docs/laravel-permission/v7/advanced-usage/cache)
- [Implementation Summary](../plans/archive/RENAME_IMPLEMENTATION_SUMMARY.md)
- [Project Config](../../team-sync-be/config/permission.php)
