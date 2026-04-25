# Spatie Laravel-Permission: Safe Rename Guidance

**Source**: Official Spatie Laravel-Permission v7 Documentation  
**Date**: April 21, 2026

---

## Official Cache Behavior

### Automatic Cache Reset (Built-In)

When using Spatie's built-in methods, cache is **automatically cleared**:

```php
// These auto-clear cache:
$role->givePermissionTo('permission-name');
$role->revokePermissionTo('permission-name');
$role->syncPermissions($permissions);

$permission->assignRole('role-name');
$permission->removeRole('role-name');
$permission->syncRoles($roles);

Role::create(['name' => 'new-role']);
Role::delete();
Permission::create(['name' => 'new-permission']);
Permission::delete();
```

**Source**: [Spatie Cache Documentation](https://spatie.be/docs/laravel-permission/v7/advanced-usage/cache)

### Manual Cache Reset (Required for Direct DB Updates)

When manipulating data **directly in the database** (like in migrations), cache is **NOT automatically cleared**:

```php
// Direct DB updates do NOT auto-clear cache:
DB::table('roles')->update(['name' => 'new-name']);
DB::table('permissions')->update(['name' => 'new-name']);
```

**Solution**: Manually reset cache after direct DB updates:

```php
// Option 1: Programmatic
app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

// Option 2: Artisan command
php artisan permission:cache-reset
```

**Source**: [Spatie Manual Cache Reset](https://spatie.be/docs/laravel-permission/v7/advanced-usage/cache#content-manual-cache-reset)

---

## Cache Configuration

### Default Settings (from config/permission.php)

```php
'cache' => [
    'expiration_time' => \DateInterval::createFromDateString('24 hours'),
    'key' => 'spatie.permission.cache',
    'store' => 'default',  // Uses Laravel's default cache driver
],
```

### Cache Key
- **Default**: `'spatie.permission.cache'`
- **Recommendation**: Don't change this unless you have specific multi-tenancy needs

### Cache Expiration
- **Default**: 24 hours
- **Behavior**: Cache automatically expires after 24 hours
- **Manual reset**: Use `permission:cache-reset` to clear immediately

---

## Junction Table Consistency

### Automatic Handling

Spatie handles junction table consistency automatically:

- **role_has_permissions**: Links roles to permissions
- **model_has_roles**: Links users to roles
- **model_has_permissions**: Links users to direct permissions

When you rename a role or permission, foreign key constraints ensure:
1. All references are maintained
2. No orphaned records
3. Referential integrity preserved

**Recommendation**: Use `DB::transaction()` for atomic operations:

```php
DB::transaction(function () {
    // All changes succeed or all fail
    $role->update(['name' => 'new-name']);
    Permission::where('name', 'like', 'old-prefix-%')
        ->each(fn($p) => $p->update(['name' => str_replace(...)]));
});
```

---

## User-Specific Assignments

### Important: In-Memory Caching

User role/permission assignments are **cached in-memory** during the request:

```php
// These do NOT trigger cache reset:
$user->assignRole('role-name');
$user->removeRole('role-name');
$user->syncRoles($roles);
```

**Why**: User assignments are request-scoped, not global cache.

**Implication**: No cache reset needed for user-level changes.

---

## Events (Optional)

### Available Events (v7.0.0+)

If `events_enabled => true` in config:

```php
\Spatie\Permission\Events\RoleAttachedEvent::class
\Spatie\Permission\Events\RoleDetachedEvent::class
\Spatie\Permission\Events\PermissionAttachedEvent::class
\Spatie\Permission\Events\PermissionDetachedEvent::class
```

**Default**: Events are **disabled** for performance.

**Use case**: Listen to role/permission changes for logging/auditing.

---

## Safe Rename Procedure (Spatie-Approved)

### Step 1: Use Transactions
```php
DB::transaction(function () {
    // All changes atomic
});
```

### Step 2: Update via Eloquent (Preferred)
```php
$role = Role::where('name', 'old-name')->first();
$role->update(['name' => 'new-name']);
```

### Step 3: Clear Cache
```php
app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

### Step 4: Verify
```php
// Check new name exists
Role::where('name', 'new-name')->first();

// Check old name is gone
Role::where('name', 'old-name')->first();  // Should be null
```

---

## Common Pitfalls

### Pitfall 1: Forgetting Cache Reset
**Problem**: Old permissions still showing after rename  
**Solution**: Always call `forgetCachedPermissions()` after direct DB updates

### Pitfall 2: Not Using Transactions
**Problem**: Partial updates if something fails  
**Solution**: Wrap all changes in `DB::transaction()`

### Pitfall 3: Checking Cache Before Reset
**Problem**: Stale data in memory  
**Solution**: Reset cache BEFORE checking results

### Pitfall 4: Not Restarting Queue Workers
**Problem**: Queue jobs using old permission names  
**Solution**: Restart workers after deployment: `php artisan queue:restart`

---

## Testing Considerations

### Clear Cache in Test Setup

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Clear permission cache before each test
    $this->app->make(\Spatie\Permission\PermissionRegistrar::class)
        ->forgetCachedPermissions();
}
```

**Why**: Tests create roles/permissions after gate registration.

---

## Performance Tips (From Spatie)

1. **Cache is enabled by default** - Don't disable unless necessary
2. **24-hour expiration is reasonable** - Adjust only if needed
3. **Use `syncPermissions()` instead of individual `givePermissionTo()`** - Fewer DB queries
4. **Batch operations in transactions** - Atomic + efficient

---

## Octane Support

If using Laravel Octane:

```php
// config/permission.php
'register_octane_reset_listener' => false,  // Default

// Set to true if experiencing stale cache in Octane
'register_octane_reset_listener' => true,
```

**Effect**: Automatically resets cache on every Octane tick.

---

## References

- [Spatie Cache Documentation](https://spatie.be/docs/laravel-permission/v7/advanced-usage/cache)
- [Spatie Events Documentation](https://spatie.be/docs/laravel-permission/v7/advanced-usage/events)
- [Spatie Testing Documentation](https://spatie.be/docs/laravel-permission/v7/advanced-usage/testing)
- [Spatie Database Seeding](https://spatie.be/docs/laravel-permission/v7/advanced-usage/seeding)

---

## Summary

**Safe rename requires**:
1. ✅ Atomic transactions (`DB::transaction()`)
2. ✅ Manual cache reset (`forgetCachedPermissions()`)
3. ✅ Verification of junction table consistency
4. ✅ Service restart (queue workers, PHP-FPM)
5. ✅ Test coverage with cache reset in setup

**This implementation follows all Spatie best practices.**
