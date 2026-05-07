<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Add staff-member-statistic and team-statistic permissions.
     * Assign team-statistic to manager role.
     * HR already inherits both via prefix-based permission assignment in RolePermissionSeeder.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guardName = 'sanctum';

        // Create new permissions
        $staffMemberStatistic = Permission::firstOrCreate([
            'name' => 'staff-member-statistic',
            'guard_name' => $guardName,
        ]);

        $teamStatistic = Permission::firstOrCreate([
            'name' => 'team-statistic',
            'guard_name' => $guardName,
        ]);

        // Assign team-statistic to manager
        $manager = Role::where('name', 'manager')->where('guard_name', $guardName)->first();
        if ($manager && ! $manager->hasPermissionTo('team-statistic')) {
            $manager->givePermissionTo($teamStatistic);
        }

        // Assign both to HR (explicit, since prefix-based assignment only runs in seeder)
        $hr = Role::where('name', 'hr')->where('guard_name', $guardName)->first();
        if ($hr) {
            if (! $hr->hasPermissionTo('staff-member-statistic')) {
                $hr->givePermissionTo($staffMemberStatistic);
            }
            if (! $hr->hasPermissionTo('team-statistic')) {
                $hr->givePermissionTo($teamStatistic);
            }
        }

        // Assign both to superadmin (has all permissions)
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', $guardName)->first();
        if ($superadmin) {
            if (! $superadmin->hasPermissionTo('staff-member-statistic')) {
                $superadmin->givePermissionTo($staffMemberStatistic);
            }
            if (! $superadmin->hasPermissionTo('team-statistic')) {
                $superadmin->givePermissionTo($teamStatistic);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guardName = 'sanctum';

        // Remove permissions from roles
        $permissions = ['staff-member-statistic', 'team-statistic'];

        foreach (['manager', 'hr', 'superadmin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guardName)->first();
            if ($role) {
                $role->revokePermissionTo($permissions);
            }
        }

        // Delete the permissions
        Permission::whereIn('name', $permissions)
            ->where('guard_name', $guardName)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
