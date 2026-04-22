<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Rename role 'employee' → 'staff'
            $employeeRole = Role::where('name', 'employee')->first();
            if ($employeeRole) {
                $employeeRole->update(['name' => 'staff']);
            }

            // 2. Rename all permissions with 'employee-' prefix → 'staff-member-'
            Permission::where('name', 'like', 'employee-%')
                ->get()
                ->each(function ($permission) {
                    $newName = str_replace('employee-', 'staff-member-', $permission->name);
                    $permission->update(['name' => $newName]);
                });
        });

        // 3. Clear cache after database changes
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // Reverse: 'staff' → 'employee'
            $staffRole = Role::where('name', 'staff')->first();
            if ($staffRole) {
                $staffRole->update(['name' => 'employee']);
            }

            // Reverse: 'staff-member-' → 'employee-'
            Permission::where('name', 'like', 'staff-member-%')
                ->get()
                ->each(function ($permission) {
                    $newName = str_replace('staff-member-', 'employee-', $permission->name);
                    $permission->update(['name' => $newName]);
                });
        });

        // Clear cache after rollback
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
